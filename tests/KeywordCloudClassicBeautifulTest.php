<?php

/**
 * @file plugins/blocks/keywordCloudClassicBeautiful/tests/KeywordCloudClassicBeautifulTest.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class KeywordCloudClassicBeautifulTest
 *
 * @brief The settings and their defaults, how a keyword count becomes a styled
 *        word, the sample sets and the assets of the block.
 */

namespace APP\plugins\blocks\keywordCloudClassicBeautiful\tests;

use APP\plugins\blocks\keywordCloudClassicBeautiful\KeywordCloudClassicBeautifulBlockPlugin;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\tests\PKPTestCase;
use ReflectionClass;
use ReflectionMethod;

#[CoversClass(KeywordCloudClassicBeautifulBlockPlugin::class)]
class KeywordCloudClassicBeautifulTest extends PKPTestCase
{
    protected function plugin(array $settings = []): KeywordCloudClassicBeautifulBlockPlugin
    {
        return new class ($settings) extends KeywordCloudClassicBeautifulBlockPlugin {
            public function __construct(private array $settings)
            {
                parent::__construct();
            }

            public function getSetting($contextId, $name)
            {
                return $this->settings[$name] ?? null;
            }
        };
    }

    protected function call(KeywordCloudClassicBeautifulBlockPlugin $plugin, string $method, array $args = [])
    {
        $reflection = new ReflectionMethod(KeywordCloudClassicBeautifulBlockPlugin::class, $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($plugin, $args);
    }

    public function testASettingThatWasNeverSavedFallsBackToItsDefault(): void
    {
        $defaults = (new ReflectionClass(KeywordCloudClassicBeautifulBlockPlugin::class))->getConstant('DEFAULTS');
        $plugin = $this->plugin(['numKeywords' => 15, 'palette' => '']);

        $this->assertSame(15, $this->call($plugin, 'setting', [1, 'numKeywords']));
        // An empty setting is not a choice: the default answers for it.
        $this->assertSame($defaults['palette'], $this->call($plugin, 'setting', [1, 'palette']));
        $this->assertSame($defaults['minFont'], $this->call($plugin, 'setting', [1, 'minFont']));
        $this->assertSame($defaults['size'], $this->call($plugin, 'setting', [null, 'size']));
    }

    public function testEveryPaletteHasColoursAndAnUnknownOneFallsBack(): void
    {
        $plugin = $this->plugin();
        foreach (['soft', 'vibrant', 'mono'] as $scheme) {
            $colours = $this->call($plugin, 'palette', [$scheme]);
            $this->assertNotEmpty($colours);
            foreach ($colours as $colour) {
                $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $colour);
            }
        }
        $this->assertSame($this->call($plugin, 'palette', ['soft']), $this->call($plugin, 'palette', ['nonsense']));
    }

    public function testTheSampleSetsAreCompleteAndFallBackToEnglish(): void
    {
        $plugin = $this->plugin();
        $sets = $this->call($plugin, 'sampleSets');
        $this->assertArrayHasKey('en', $sets);
        foreach ($sets as $language => $set) {
            $this->assertNotEmpty($set, "The sample set of {$language} is empty.");
            foreach ($set as $keyword => $count) {
                $this->assertIsString($keyword);
                $this->assertIsInt($count);
                $this->assertGreaterThan(0, $count);
            }
        }

        $this->assertSame($sets['pt'], $this->call($plugin, 'getSampleCounts', ['pt_BR']));
        $this->assertSame($sets['en'], $this->call($plugin, 'getSampleCounts', ['xx_YY']));
    }

    public function testNoMoreKeywordsThanTheCeilingAndTheCacheIsPerContextAndLanguage(): void
    {
        $reflection = new ReflectionClass(KeywordCloudClassicBeautifulBlockPlugin::class);
        $this->assertSame(120, $reflection->getConstant('MAX_ITEMS_CEILING'));
        $this->assertGreaterThan(0, $reflection->getConstant('CACHE_DAYS'));

        $source = (string) file_get_contents(dirname(__DIR__) . '/KeywordCloudClassicBeautifulBlockPlugin.php');
        $this->assertStringContainsString("'keywordCloudClassicBeautiful_' . \$context->getId() . '_' . \$locale", $source);
        // The counts come from one query, never one per publication.
        $this->assertSame(1, substr_count($source, 'DB::table('));
    }

    public function testTheStylesAndTheScriptAreStaticFilesOfThePlugin(): void
    {
        $root = dirname(__DIR__);
        foreach (['css/keywordCloud.css', 'js/keywordCloud.js', 'lib/wordcloud2/wordcloud2.js'] as $asset) {
            $this->assertFileExists($root . '/' . $asset);
        }

        $template = (string) file_get_contents($root . '/templates/block.tpl');
        $this->assertStringNotContainsString('<script', $template);
        $this->assertStringNotContainsString('<style', $template);

        $source = (string) file_get_contents($root . '/KeywordCloudClassicBeautifulBlockPlugin.php');
        // The layout library is the vendored copy, never a CDN.
        $this->assertStringContainsString("/lib/wordcloud2/wordcloud2.js', ['contexts' => 'frontend']", $source);
        $this->assertStringNotContainsString('//cdn', $source);
        // Blocks render after the head, so the stylesheet is linked by the template.
        $this->assertStringContainsString("'kwcStyleUrl' => \$assetsUrl . '/css/keywordCloud.css'", $source);
        $this->assertStringContainsString('{$kwcStyleUrl|escape}', $template);
    }

    public function testTheKeywordsAreDrawnBetweenTheConfiguredSizesAndLinkToTheSearch(): void
    {
        $plugin = $this->plugin(['minFont' => 10, 'maxFont' => 30, 'palette' => 'mono']);
        $request = new class () {
            public function getDispatcher(): object
            {
                return new class () {
                    public function url($request, $route, $context, $page, $op, $path, $params)
                    {
                        return 'https://journal.example.org/search?query=' . urlencode($params['query']);
                    }
                };
            }
        };
        // The journal or press of the installation the suite runs on.
        $context = \APP\core\Application::getContextDAO()->newDataObject();
        $context->setId(3);

        $items = $this->call($plugin, 'styleItems', [['alpha' => 10, 'beta' => 1], $request, $context, 1]);

        $this->assertCount(2, $items);
        $byText = array_column($items, null, 'text');
        $this->assertSame(30, $byText['alpha']['font'], 'The most used keyword gets the largest size.');
        $this->assertSame(10, $byText['beta']['font'], 'The least used keyword gets the smallest size.');
        foreach ($items as $item) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $item['color']);
            $this->assertStringContainsString('query=', $item['url']);
            $this->assertGreaterThanOrEqual(400, $item['weight']);
            $this->assertLessThanOrEqual(1, $item['opacity']);
        }
        $this->assertSame([], $this->call($plugin, 'styleItems', [[], $request, $context, 1]));
    }
}
