<?php
use ArPHP\I18N\Arabic;

/**
 * Shape pure Arabic text to presentation forms (keeps English digits).
 */
if (! function_exists('arabic')) {
    function arabic(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        static $arabic = null;
        if ($arabic === null) {
            $arabic = new Arabic(); // default 'Glyphs' mode internally
        }

        // Turn base Arabic letters into shaped glyphs so DomPDF joins them
        $processed = $arabic->utf8Glyphs($text);

        // Force English digits (convert Persian/Arabic-Indic to ASCII)
        return strtr($processed, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}

/**
 * Smart Arabic text handler:
 * - No Arabic letters  -> returns original
 * - Plain text Arabic  -> shapes via arabic()
 * - HTML with Arabic   -> shapes only text nodes, preserves tags/attributes
 */
if (! function_exists('arabic_text')) {
    function arabic_text(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        // If it has no HTML tags:
        if ($text === strip_tags($text)) {
            if (preg_match('/\p{Arabic}/u', $text)) {
                return arabic($text);
            }
            return $text;
        }

        // HTML: parse and shape only text nodes containing Arabic
        $dom = new DOMDocument('1.0', 'UTF-8');

        // Avoid warnings from malformed HTML
        $internalErrors = libxml_use_internal_errors(true);

        // Ensure UTF-8
        $html = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $text;
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//text()') as $node) {
            $nodeValue = $node->nodeValue;
            if ($nodeValue !== '' && preg_match('/\p{Arabic}/u', $nodeValue)) {
                $node->nodeValue = arabic($nodeValue);
            }
        }

        return $dom->saveHTML();
    }
}
