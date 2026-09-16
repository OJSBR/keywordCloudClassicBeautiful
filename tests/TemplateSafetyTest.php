<?php

/**
 * @file plugins/blocks/keywordCloudClassicBeautiful/tests/TemplateSafetyTest.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TemplateSafetyTest
 *
 * @brief Static checks on the templates and the source files.
 */

namespace APP\plugins\blocks\keywordCloudClassicBeautiful\tests;

use PKP\tests\PKPTestCase;

class TemplateSafetyTest extends PKPTestCase
{
    /** @return string[] */
    protected function templates(): array
    {
        $root = dirname(__DIR__);
        return array_merge(glob($root . '/templates/*.tpl') ?: [], glob($root . '/templates/*/*.tpl') ?: []);
    }

    public function testFormsPostWithACsrfToken(): void
    {
        $this->assertTrue(true);
        foreach ($this->templates() as $file) {
            $source = (string) file_get_contents($file);
            if (preg_match('/<form\b[^>]*method="post"/i', $source)) {
                $this->assertStringContainsString('{csrf}', $source, basename($file) . ' posts without a CSRF token.');
            }
        }
    }

    public function testTranslationsInAttributesAreEscaped(): void
    {
        // {translate key="x"|escape} escapes the key, not the translation.
        foreach ($this->templates() as $file) {
            $this->assertSame(0, preg_match('/\{translate key="[^"]+"\|escape\}/', (string) file_get_contents($file)), basename($file));
        }
        $this->assertTrue(true);
    }

    public function testNoCoreTemplateIsReplaced(): void
    {
        foreach (glob(dirname(__DIR__) . '/*.php') as $file) {
            $source = (string) file_get_contents($file);
            $this->assertSame(0, preg_match("/Hook(Registry)?::(add|register)\\(\\s*'TemplateResource::getFilename'/", $source), basename($file) . ' replaces a core template.');
        }
    }

    public function testSourceHasTheStandardHeader(): void
    {
        $root = dirname(__DIR__);
        $files = array_merge(glob($root . '/*.php'), glob($root . '/classes/*.php'), glob($root . '/classes/*/*.php'), glob(__DIR__ . '/*.php'), $this->templates(), glob($root . '/js/*.js'), glob($root . '/css/*.css'));
        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            $this->assertMatchesRegularExpression('/Copyright \(c\) (\d{4}-)?2026 OJSBR \(https:\/\/ojsbr\.com\)/', $source, basename($file) . ' lacks the header.');
            $this->assertStringNotContainsString('https://ojsbr.com' . '.br', $source, basename($file) . ' points at the old address.');
        }
    }
}
