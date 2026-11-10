<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\Feed;

/**
 * Turns a catalogue value into text that belongs in the feed: valid XML 1.0 characters only, and no HTML
 * entities. Catalogue data carries them (the Luma sample data has "LumaTech&trade; V-Tee"); XML knows only
 * five, so named and numeric entities become the character they stand for. Escaping the characters XML
 * reserves is left to the writer, which knows whether it escapes itself (XMLWriter) or not (SimpleXML).
 */
class Sanitizer
{
    /**
     * Named HTML entity => character, built once per process
     *
     * @var array<string, string>|null
     */
    private ?array $namedEntities = null;

    public function text($value): string
    {
        $value = is_string($value) ? $value : (string)$value;

        if (!mb_detect_encoding($value, 'UTF-8', true)) {
            $value = mb_convert_encoding($value, 'UTF-8');
        }

        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';

        $value = preg_replace_callback(
            '/&([a-zA-Z][a-zA-Z0-9]{1,31});/',
            fn (array $match): string => $this->namedEntities()['&' . $match[1] . ';'] ?? $match[0],
            $value
        ) ?? '';

        return mb_decode_numericentity($value, [0x20, 0x10FFFF, 0, 0x10FFFF], 'UTF-8');
    }

    /**
     * @return array<string, string>
     */
    private function namedEntities(): array
    {
        if ($this->namedEntities === null) {
            $this->namedEntities = array_flip(
                get_html_translation_table(HTML_ENTITIES, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            );
        }

        return $this->namedEntities;
    }
}
