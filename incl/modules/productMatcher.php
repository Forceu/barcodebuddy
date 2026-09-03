<?php

/**
 * Barcode Buddy for Grocy
 *
 * PHP version 7
 *
 * LICENSE: This source file is subject to version 3.0 of the GNU General
 * Public License v3.0 that is attached to this project.
 *
 * Fallback lookup used when no tag matches a scanned/looked-up barcode name.
 * Tries to find an existing Grocy product with a near-identical name, or one
 * that shares a significant word with the barcode's name, so that it can be
 * preselected as the "possible match" for the user to confirm.
 *
 * @since File available since Release 1.8
 */

class ProductMatcher {

    /** Minimum similarity (0-100) between two full names to count as "near identical" */
    const NAME_SIMILARITY_THRESHOLD = 75;

    /**
     * A match must beat the runner-up by at least this many percentage points to be
     * trusted automatically. This avoids preselecting the wrong product when several
     * Grocy products have similarly-close names.
     */
    const NAME_SIMILARITY_MARGIN = 5;

    /** Minimum word length to be considered "significant" enough to match on its own */
    const MIN_WORD_LENGTH = 4;

    /**
     * Tries to find a Grocy product whose name is near-identical to $name, or which
     * shares a single significant word with $name.
     *
     * @param string $name Name to look up, e.g. as returned by a barcode lookup provider
     * @return int Grocy product ID, or 0 if no (unambiguous) match was found
     */
    public static function findNearestProductMatch(string $name): int {
        $name = trim($name);
        if ($name === "" || $name === "N/A") {
            return 0;
        }

        $products = API::getAllProductsInfo();
        if ($products == null || sizeof($products) == 0) {
            return 0;
        }

        $match = self::findByNameSimilarity($name, $products);
        if ($match != 0) {
            return $match;
        }

        return self::findBySharedWord($name, $products);
    }

    /**
     * Finds the product whose name is the closest overall match to $name. Only
     * returns a result if the best match is both close enough, and clearly
     * better than the next-best candidate.
     *
     * @param string $name
     * @param GrocyProduct[] $products
     * @return int Grocy product ID, or 0 if no confident match was found
     */
    private static function findByNameSimilarity(string $name, array $products): int {
        $bestId             = 0;
        $bestPercent        = 0.0;
        $secondBestPercent  = 0.0;
        $nameLower          = strtolower($name);

        foreach ($products as $product) {
            similar_text($nameLower, strtolower($product->name), $percent);
            if ($percent > $bestPercent) {
                $secondBestPercent = $bestPercent;
                $bestPercent       = $percent;
                $bestId            = $product->id;
            } elseif ($percent > $secondBestPercent) {
                $secondBestPercent = $percent;
            }
        }

        if ($bestPercent >= self::NAME_SIMILARITY_THRESHOLD &&
            ($bestPercent - $secondBestPercent) >= self::NAME_SIMILARITY_MARGIN) {
            return $bestId;
        }
        return 0;
    }

    /**
     * Finds a product that shares a significant word with $name. If more than one
     * product shares a word, no product is returned, as the match would be ambiguous.
     *
     * @param string $name
     * @param GrocyProduct[] $products
     * @return int Grocy product ID, or 0 if no unambiguous match was found
     */
    private static function findBySharedWord(string $name, array $products): int {
        $words = array_filter(cleanNameForTagLookup($name), function (string $word): bool {
            return strlen($word) >= self::MIN_WORD_LENGTH;
        });
        if (empty($words)) {
            return 0;
        }

        $matchedIds = array();
        foreach ($products as $product) {
            $productWords = cleanNameForTagLookup($product->name);
            foreach ($words as $word) {
                foreach ($productWords as $productWord) {
                    if (strcasecmp($word, $productWord) === 0) {
                        $matchedIds[$product->id] = true;
                        break 2;
                    }
                }
            }
        }

        if (sizeof($matchedIds) == 1) {
            $ids = array_keys($matchedIds);
            return $ids[0];
        }
        return 0;
    }
}
