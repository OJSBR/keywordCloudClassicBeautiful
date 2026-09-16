<?php

/**
 * @file plugins/blocks/keywordCloudClassicBeautiful/tests/LocaleFilesTest.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LocaleFilesTest
 *
 * @brief Translations. A key missing from a locale is rendered as ##key##, so an
 *        incomplete file is worse than none.
 */

namespace APP\plugins\blocks\keywordCloudClassicBeautiful\tests;

use PKP\tests\PKPTestCase;

class LocaleFilesTest extends PKPTestCase
{
    /** Locale codes shipped by the plugin, as OJS 3.4 names them. */
    public const LOCALES = [
        'ar', 'az', 'bg', 'ca', 'cs', 'da', 'de', 'el', 'en', 'es', 'eu',
        'fa', 'fi', 'fr_CA', 'fr_FR', 'gl', 'hu', 'hy', 'id', 'it', 'ja', 'ka',
        'mk', 'ms', 'nb', 'nl', 'pl', 'pt_BR', 'pt_PT', 'ro', 'ru', 'sl', 'sr@latin',
        'sv', 'tr', 'uk', 'vi', 'zh_CN',
    ];

    /** Locales reviewed by a fluent speaker; every entry of the others is marked fuzzy. */
    public const REVIEWED = ['en', 'pt_PT', 'pt_BR', 'es', 'ca', 'gl', 'fr_FR', 'fr_CA', 'it', 'de', 'nl'];

    public const MASTER = 'en';

    /** Placeholders each key must keep, exactly once. */
    public const PLACEHOLDERS = [
    ];

    protected function localeDir(): string
    {
        return dirname(__DIR__) . '/locale';
    }

    /** @return array<string, PoFile> */
    protected function files(): array
    {
        $files = [];
        foreach (self::LOCALES as $locale) {
            $path = $this->localeDir() . "/{$locale}/locale.po";
            if (is_file($path)) {
                $files[$locale] = new PoFile($path);
            }
        }
        return $files;
    }

    public function testShipsExactlyTheLocaleCodesOfThisLine(): void
    {
        $dirs = array_map('basename', glob($this->localeDir() . '/*', GLOB_ONLYDIR) ?: []);
        sort($dirs);
        $expected = self::LOCALES;
        sort($expected);

        // A code from another OJS line (fr vs fr_FR, pt vs pt_PT) silently never loads.
        $this->assertSame($expected, $dirs);
        $this->assertCount(count(self::LOCALES), $this->files());
    }

    public function testEveryLocaleHasExactlyTheKeysOfTheMaster(): void
    {
        $files = $this->files();
        $master = array_keys($files[self::MASTER]->entries);
        $this->assertCount(29, $master);

        foreach ($files as $locale => $file) {
            $this->assertSame($master, array_keys($file->entries), "Keys of {$locale} differ from " . self::MASTER . '.');
        }
    }

    public function testEveryKeyTheCodeUsesExists(): void
    {
        $sources = '';
        $root = dirname(__DIR__);
        foreach (array_merge(glob($root . '/*.php'), glob($root . '/classes/*.php'), glob($root . '/classes/*/*.php'), glob($root . '/templates/*.tpl'), glob($root . '/templates/*/*.tpl'), glob($root . '/js/*.js')) as $file) {
            $sources .= file_get_contents($file);
        }
        preg_match_all('/plugins\.block\.keywordCloudClassicBeautiful\.[a-zA-Z0-9_.]*[a-zA-Z0-9_]/', $sources, $m);
        $keys = array_keys($this->files()[self::MASTER]->entries);

        foreach (array_unique($m[0]) as $key) {
            // Class paths of the plugin (component handlers, hooks) share the prefix
            // and are not locale keys.
            if (str_ends_with($key, '.') || preg_match('/\.(classes|controllers|pages|jobs|tests)\./', $key)) {
                continue;
            }
            $this->assertTrue(in_array($key, $keys, true) || $this->isKeyPrefix($key, $keys), "{$key} is used but not translated.");
        }
    }

    /** A key built at runtime from a prefix ("...status." . $name). */
    protected function isKeyPrefix(string $key, array $keys): bool
    {
        foreach ($keys as $known) {
            if (str_starts_with($known, $key . '.')) {
                return true;
            }
        }
        return false;
    }

    public function testNoTranslationIsEmpty(): void
    {
        foreach ($this->files() as $locale => $file) {
            foreach ($file->entries as $key => $value) {
                $this->assertNotEmpty(trim($value), "Empty translation for {$key} in {$locale}.");
            }
        }
    }

    /** Translations from the original authors keep the headers they were published with. */
    public const UPSTREAM_HEADERS = [];

    public function testHeaderDeclaresTheLocaleAndTheTeam(): void
    {
        foreach ($this->files() as $locale => $file) {
            $this->assertStringContainsString("Language: {$locale}\n", $file->header, "Wrong Language header in {$locale}.");
            if (in_array($locale, self::UPSTREAM_HEADERS, true)) {
                continue;
            }
            $this->assertStringContainsString("Last-Translator: OJSBR\n", $file->header, "Missing Last-Translator in {$locale}.");
            $this->assertStringContainsString("Language-Team: OJSBR\n", $file->header, "Missing Language-Team in {$locale}.");
        }
    }

    public function testUnreviewedLocalesAreFuzzyAndReviewedOnesAreNot(): void
    {
        foreach ($this->files() as $locale => $file) {
            $reviewed = in_array($locale, self::REVIEWED, true);
            foreach ($file->entries as $key => $value) {
                $this->assertSame(!$reviewed, $file->fuzzy[$key] ?? false, ($reviewed ? 'Fuzzy' : 'Not fuzzy') . " entry {$key} in {$locale}.");
            }
        }
    }

    public function testPlaceholdersAreKept(): void
    {
        foreach ($this->files() as $locale => $file) {
            foreach ($file->entries as $key => $value) {
                $expected = self::PLACEHOLDERS[$key] ?? [];
                preg_match_all('/\{\$[a-zA-Z0-9_]+\}/', $value, $m);
                $found = array_values(array_unique($m[0]));
                sort($found);
                $this->assertSame($expected, $found, "Placeholders of {$key} in {$locale}.");
                // "count" is reserved by the translator (plural rules).
                $this->assertStringNotContainsString('{$count}', $value, "{$key} uses the reserved {\$count} in {$locale}.");
            }
        }
    }

    public function testTheDescriptionCarriesNoCredit(): void
    {
        foreach ($this->files() as $locale => $file) {
            foreach ($file->entries as $key => $value) {
                if (str_ends_with($key, '.description') && substr_count($key, '.') <= 3) {
                    $this->assertStringNotContainsString('OJSBR', $value, "Credit in {$key} ({$locale}).");
                }
            }
        }
    }
}
