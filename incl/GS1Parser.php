<?php

class GS1Parser {
    private $barcode;
    private $gtin;
    private $expirationDate;

    public function __construct(string $barcode) {
        $this->barcode = $barcode;
        $this->parse();
    }

    private function parse() {
        // Remove symbology identifier if present (e.g. ]d2 for GS1 DataMatrix)
        $code = $this->barcode;
        if (substr($code, 0, 3) === ']d2') {
            $code = substr($code, 3);
        }

        // Replace group separators (GS) with a common delimiter if needed, 
        // or just rely on regex. ASCII 29 is GS.
        // Some scanners might map it to something else, but let's assume raw input or specific mapping.
        // For now, we'll try to parse standard AIs.

        // AI 01: GTIN (14 digits)
        // AI 17: Expiration Date (YYMMDD)
        // AI 10: Batch/Lot (Variable length) - we might encounter this
        // AI 21: Serial (Variable length)

        // Simple parsing strategy:
        // 1. Look for 01 (GTIN) - fixed length 14
        // 2. Look for 17 (Exp) - fixed length 6
        
        // We need to handle the stream.
        $offset = 0;
        $length = strlen($code);

        while ($offset < $length) {
            $ai2 = substr($code, $offset, 2);
            
            if ($ai2 === '01') {
                // GTIN: 14 digits
                $this->gtin = substr($code, $offset + 2, 14);
                $offset += 16;
            } elseif ($ai2 === '17') {
                // Expiration: 6 digits YYMMDD
                $rawDate = substr($code, $offset + 2, 6);
                $this->expirationDate = $this->parseDate($rawDate);
                $offset += 8;
            } elseif ($ai2 === '10') {
                // Batch: Variable length, terminated by FNC1 or end of string
                $offset += 2;
                $end = $this->findNextSeparator($code, $offset);
                $offset = $end;
            } elseif ($ai2 === '21') {
                // Serial: Variable length
                $offset += 2;
                $end = $this->findNextSeparator($code, $offset);
                $offset = $end;
            } else {
                 // Unknown AI or end of useful data for us. 
                 // If we have what we need, we can stop.
                 // If we encounter something we don't know, we might get stuck if it's variable length.
                 // For now, let's just try to skip 1 char if we don't match known fixed AIs? 
                 // No, that's dangerous. 
                 // Let's assume standard ordering or just regex search if parsing fails.
                 
                 // Fallback: Regex search for 01 and 17 if strict parsing fails?
                 // Let's try to be robust.
                 break;
            }
        }
    }

    private function findNextSeparator($code, $offset) {
        // Check for ASCII 29 (GS)
        $pos = strpos($code, chr(29), $offset);
        if ($pos !== false) {
            return $pos + 1; // Skip the GS
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

        // GS1 Date logic:
        // DD = 00 means last day of month
        
        // Century assumption: 
        // GS1 General Specifications say: 
        // 51-99 = 1951-1999
        // 00-50 = 2000-2050
        // (This is a sliding window, usually +/- 50 years from now, but let's stick to simple logic for now)
        $fullYear = ($yy >= 50 ? 1900 : 2000) + $yy;

        if ($dd === 0) {
             // Last day of month
             $dd = date('t', strtotime("$fullYear-$mm-01"));
        }

        return sprintf("%04d-%02d-%02d", $fullYear, $mm, $dd);
    }

    public function getGtin() {
        return $this->gtin;
    }

    public function getExpirationDate() {
        return $this->expirationDate;
    }
    
    public function isValid() {
        return !empty($this->gtin);
    }
}
