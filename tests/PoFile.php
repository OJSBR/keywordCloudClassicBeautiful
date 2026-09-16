<?php

/**
 * @file plugins/blocks/keywordCloudClassicBeautiful/tests/PoFile.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PoFile
 *
 * @brief Minimal reader for the plugin's gettext files, used by the locale tests.
 */

namespace APP\plugins\blocks\keywordCloudClassicBeautiful\tests;

class PoFile
{
    /** @var array<string, string> msgid => msgstr, header excluded */
    public array $entries = [];

    /** @var array<string, bool> msgid => marked fuzzy */
    public array $fuzzy = [];

    public string $header = '';

    public function __construct(string $path)
    {
        $current = null;
        $target = null;
        $fuzzyNext = false;

        foreach (preg_split('/\r?\n/u', (string) file_get_contents($path)) as $line) {
            if (preg_match('/^#,.*\bfuzzy\b/', $line)) {
                $fuzzyNext = true;
            } elseif (preg_match('/^msgid "(.*)"$/', $line, $m)) {
                $current = stripcslashes($m[1]);
                $target = 'msgid';
            } elseif (preg_match('/^msgstr "(.*)"$/', $line, $m)) {
                if ($current !== '' && $current !== null) {
                    $this->fuzzy[$current] = $fuzzyNext;
                }
                $fuzzyNext = false;
                $this->store($current, stripcslashes($m[1]), false);
                $target = 'msgstr';
            } elseif (preg_match('/^"(.*)"$/', $line, $m)) {
                if ($target === 'msgid') {
                    $current .= stripcslashes($m[1]);
                } elseif ($target === 'msgstr') {
                    $this->store($current, stripcslashes($m[1]), true);
                }
            }
        }
    }

    protected function store(?string $msgid, string $value, bool $append): void
    {
        if ($msgid === '') {
            $this->header = $append ? $this->header . $value : $value;
            return;
        }

        $this->entries[$msgid] = $append ? ($this->entries[$msgid] ?? '') . $value : $value;
    }
}
