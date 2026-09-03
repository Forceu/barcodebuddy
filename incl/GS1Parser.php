<?php

class GS1Parser {
    private $barcode;
    private $gtin;
    private $expirationDate;
    private $batch;
    private $serial;

    public function __construct(string $barcode) {
        $this->barcode = trim($barcode);
        $this->parse();
    }

    private function parse() {
        $code = $this->barcode;

        // 1. Remove symbology identifier if present (e.g. ]d2, ]D2, ]e0)
        $code = preg_replace('/^\][a-zA-Z0-9]{2}/', '', $code);

        // 2. Normalize Human-Readable input by removing AI parentheses if present: "(01)...(17)..." -> "01...17..."
        if (strpos($code, '(') !== false && strpos($code, ')') !== false) {
            $code = str_replace(['(', ')'], '', $code);
        }

        $offset = 0;
        $length = strlen($code);

        while ($offset < $length) {
            // Skip any leading Group Separator (ASCII 29) before reading the next AI
            if (ord($code[$offset]) === 29) {
                $offset++;
                continue;
            }

            $ai2 = substr($code, $offset, 2);

            if ($ai2 === '01') {
                // GTIN: 14 digits fixed length
                $this->gtin = substr($code, $offset + 2, 14);
                $offset += 16;
            } elseif ($ai2 === '17') {
                // Expiration: 6 digits YYMMDD fixed length
                $rawDate = substr($code, $offset + 2, 6);
                $this->expirationDate = $this->parseDate($rawDate);
                $offset += 8;
            } elseif ($ai2 === '10') {
                // Batch/Lot: Variable length (up to 20 chars)
                $offset += 2;
                $end = $this->findNextSeparator($code, $offset);
                $this->batch = substr($code, $offset, $end - $offset);
                $offset = $end;
            } elseif ($ai2 === '21') {
                // Serial: Variable length (up to 20 chars)
                $offset += 2;
                $end = $this->findNextSeparator($code, $offset);
                $this->serial = substr($code, $offset, $end - $offset);
                $offset = $end;
            } else {
                // Stop parsing if an unknown AI is encountered to prevent infinite loops
                break;
            }
        }
    }

    private function findNextSeparator($code, $offset) {
        // Look for ASCII 29 (GS)
        $pos = strpos($code, chr(29), $offset);
        if ($pos !== false) {
            return $pos; // Returns index of GS separator so the caller extracts data up to it
        }
        return strlen($code);
    }

    private function parseDate($yymmdd) {
        if (strlen($yymmdd) !== 6 || !is_numeric($yymmdd)) {
            return null;
        }

        $yy = intval(substr($yymmdd, 0, 2));
        $mm = intval(substr($yymmdd, 2, 2));
        $dd = intval(substr($yymmdd, 4, 2));

        if ($mm < 1 || $mm > 12) {
            return null;
        }

        // GS1 Sliding Window Century Calculation:
        // Rolling window: -51 years to +48 years relative to current year
        $currentYear = intval(date('Y'));
        $currentCentury = intval(floor($currentYear / 100) * 100);
        $fullYear = $currentCentury + $yy;

        if ($fullYear - $currentYear > 48) {
            $fullYear -= 100;
        } elseif ($fullYear - $currentYear < -51) {
            $fullYear += 100;
        }

        // GS1 Date logic: DD = 00 means last day of specified month
        if ($dd === 0) {
            $dd = intval(date('t', strtotime(sprintf('%04d-%02d-01', $fullYear, $mm))));
        }

        return sprintf("%04d-%02d-%02d", $fullYear, $mm, $dd);
    }

    public function getGtin() {
        return $this->gtin;
    }

    public function getExpirationDate() {
        return $this->expirationDate;
    }

    public function getBatch() {
        return $this->batch;
    }

    public function getSerial() {
        return $this->serial;
    }

    public function isValid() {
        return !empty($this->gtin) && strlen($this->gtin) === 14;
    }
}
