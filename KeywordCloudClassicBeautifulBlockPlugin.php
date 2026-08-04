<?php

/**
 * @file KeywordCloudClassicBeautifulBlockPlugin.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class KeywordCloudClassicBeautifulBlockPlugin
 *
 * @brief Sidebar keyword cloud that sizes, colours and packs each keyword by how
 *        often it is used across the journal's published articles — a real word
 *        cloud (varied sizes, positions, rotations and colours) drawn with a
 *        vendored, self-contained layout library, so it can never break from a
 *        CDN or library change. Degrades to an accessible list of links.
 */

namespace APP\plugins\blocks\keywordCloudClassicBeautiful;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PKP\context\Context;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\facades\Locale;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\BlockPlugin;

class KeywordCloudClassicBeautifulBlockPlugin extends BlockPlugin
{
    /** Hard ceiling on how many keywords may be rendered. */
    private const MAX_ITEMS_CEILING = 120;

    /** Cache lifetime for the aggregated keyword counts, in days. */
    private const CACHE_DAYS = 2;

    /** Show a representative sample when the journal has (almost) no keywords. */
    private const SAMPLE_MIN_REAL = 4;

    /** Default settings. */
    private const DEFAULTS = [
        'numKeywords' => 40,
        'minFont' => 12,
        'maxFont' => 48,
        'size' => 'large',         // small | medium | large (width-proportional height)
        'heightPx' => 0,           // explicit block height in px; 0 = automatic
        'rotation' => 'diagonal',  // horizontal | orthogonal | diagonal
        'palette' => 'soft',       // soft | vibrant | mono
        'font' => 'serif',         // serif | sans | rounded
        'sampleWhenEmpty' => 1,
    ];

    private bool $lastWasSample = false;

    public function getDisplayName(): string
    {
        return __('plugins.block.keywordCloudClassicBeautiful.displayName');
    }

    public function getDescription(): string
    {
        return __('plugins.block.keywordCloudClassicBeautiful.description');
    }

    public function getContextSpecificPluginSettingsFile(): string
    {
        return $this->getPluginPath() . '/settings.xml';
    }

    /**
     * Read a plugin setting for the current context, falling back to the default.
     */
    private function setting(?int $contextId, string $name)
    {
        if ($contextId !== null) {
            $value = $this->getSetting($contextId, $name);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        return self::DEFAULTS[$name] ?? null;
    }

    /**
     * @copydoc BlockPlugin::getContents()
     */
    public function getContents($templateMgr, $request = null)
    {
        $context = $request ? $request->getContext() : null;
        if (!$context) {
            return '';
        }
        $contextId = $context->getId();

        $locale = Locale::getLocale();
        $primaryLocale = Locale::getPrimaryLocale();

        $counts = $this->getCachedCounts($context, $locale);
        if (count($counts) < self::SAMPLE_MIN_REAL && $locale !== $primaryLocale) {
            $counts = $this->getCachedCounts($context, $primaryLocale);
        }

        $this->lastWasSample = false;
        if (count($counts) < self::SAMPLE_MIN_REAL) {
            if (!$this->setting($contextId, 'sampleWhenEmpty')) {
                return '';
            }
            $counts = $this->getSampleCounts($locale);
            $this->lastWasSample = true;
        }

        $numKeywords = min(self::MAX_ITEMS_CEILING, max(5, (int) $this->setting($contextId, 'numKeywords')));
        $counts = array_slice($counts, 0, $numKeywords, true);

        $items = $this->styleItems($counts, $request, $context, $contextId);

        // Vendored, self-contained layout library (never fetched from a CDN at
        // runtime — that is exactly what broke the original plugin).
        $templateMgr->addJavaScript(
            'wordcloud2',
            $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/wordcloud2.js',
            ['contexts' => 'frontend']
        );

        $sizeRatios = ['small' => 0.68, 'medium' => 0.92, 'large' => 1.25];
        $fontStacks = [
            'serif' => 'Georgia, "Times New Roman", "Palatino Linotype", serif',
            'sans' => '"Helvetica Neue", Arial, "Segoe UI", sans-serif',
            'rounded' => '"Trebuchet MS", "Segoe UI", Verdana, sans-serif',
        ];
        $size = $this->setting($contextId, 'size');
        $font = $this->setting($contextId, 'font');

        $templateMgr->assign([
            'kwcItems' => $items,
            'kwcIsSample' => $this->lastWasSample,
            'kwcRotation' => $this->setting($contextId, 'rotation'),
            'kwcHeightRatio' => $sizeRatios[$size] ?? $sizeRatios[self::DEFAULTS['size']],
            'kwcHeightPx' => max(0, (int) $this->setting($contextId, 'heightPx')),
            'kwcFontStack' => $fontStacks[$font] ?? $fontStacks['serif'],
        ]);

        return parent::getContents($templateMgr, $request);
    }

    /**
     * @return array<string,int>
     */
    private function getCachedCounts(Context $context, string $locale): array
    {
        $cacheKey = 'keywordCloudClassicBeautiful_' . $context->getId() . '_' . $locale;
        $expiration = \DateInterval::createFromDateString(self::CACHE_DAYS . ' days');

        return Cache::remember($cacheKey, $expiration, function () use ($context, $locale) {
            return $this->getJournalKeywordCounts($context->getId(), $locale);
        });
    }

    /**
     * Count how many published publications use each keyword.
     *
     * @return array<string,int>
     */
    private function getJournalKeywordCounts(int $journalId, string $locale): array
    {
        $publicationIds = Repo::publication()
            ->getCollector()
            ->filterByContextIds([$journalId])
            ->getQueryBuilder()
            ->whereIn('p.status', [Submission::STATUS_PUBLISHED])
            ->select('p.publication_id')
            ->pluck('p.publication_id')
            ->all();

        if (!$publicationIds) {
            return [];
        }

        // One query for every keyword of every published publication (avoids an
        // N+1). On OJS 3.4 the submission-keyword text is stored in
        // controlled_vocab_entry_settings under setting_name = 'submissionKeyword'.
        $values = DB::table('controlled_vocabs as cv')
            ->join('controlled_vocab_entries as cve', 'cve.controlled_vocab_id', '=', 'cv.controlled_vocab_id')
            ->join('controlled_vocab_entry_settings as cves', 'cves.controlled_vocab_entry_id', '=', 'cve.controlled_vocab_entry_id')
            ->where('cv.symbolic', 'submissionKeyword')
            ->where('cv.assoc_type', Application::ASSOC_TYPE_PUBLICATION)
            ->whereIn('cv.assoc_id', $publicationIds)
            ->where('cves.setting_name', 'submissionKeyword')
            ->where('cves.locale', $locale)
            ->pluck('cves.setting_value');

        // Count case-insensitively but keep the most common display form. Each row
        // is one (publication, keyword) pair, so the count is the number of
        // published publications that use the keyword.
        $counts = [];
        $display = [];
        foreach ($values as $value) {
            $keyword = trim((string) $value);
            if ($keyword === '') {
                continue;
            }
            $key = mb_strtolower($keyword);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
            $display[$key][$keyword] = ($display[$key][$keyword] ?? 0) + 1;
        }
        arsort($counts, SORT_NUMERIC);
        $counts = array_slice($counts, 0, self::MAX_ITEMS_CEILING, true);

        $result = [];
        foreach ($counts as $key => $count) {
            arsort($display[$key]);
            $result[array_key_first($display[$key])] = $count;
        }
        return $result;
    }

    /**
     * Turn a keyword => count map into fully styled cloud items.
     *
     * @param array<string,int> $counts
     *
     * @return array<int,array<string,mixed>>
     */
    private function styleItems(array $counts, $request, Context $context, ?int $contextId): array
    {
        if (!$counts) {
            return [];
        }
        $palette = $this->palette($this->setting($contextId, 'palette'));
        $minFont = max(6, (int) $this->setting($contextId, 'minFont'));
        $maxFont = max($minFont + 2, (int) $this->setting($contextId, 'maxFont'));

        $values = array_values($counts);
        $min = min($values);
        $max = max($values);
        $span = max(1, $max - $min);

        $keys = array_keys($counts);
        mt_srand($context->getId() * 7919 + count($keys));
        for ($i = count($keys) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$keys[$i], $keys[$j]] = [$keys[$j], $keys[$i]];
        }
        mt_srand();

        $dispatcher = $request->getDispatcher();
        $items = [];
        foreach ($keys as $index => $keyword) {
            $count = $counts[$keyword];
            $norm = sqrt(($count - $min) / $span);
            $fontSize = (int) round($minFont + ($maxFont - $minFont) * $norm);
            $weight = 400 + (int) round(300 * $norm);
            $opacity = round(0.72 + 0.28 * $norm, 2);
            $url = $dispatcher->url(
                $request,
                PKPApplication::ROUTE_PAGE,
                null,
                'search',
                null,
                null,
                ['query' => $keyword]
            );
            $items[] = [
                'text' => $keyword,
                'url' => $url,
                'font' => $fontSize,
                'weight' => $weight,
                'color' => $palette[$index % count($palette)],
                'opacity' => $opacity,
                'count' => $count,
                'title' => $count . ' ' . __('plugins.block.keywordCloudClassicBeautiful.uses'),
            ];
        }
        return $items;
    }

    /**
     * @return array<int,string>
     */
    private function palette(string $scheme): array
    {
        $palettes = [
            'soft' => [
                '#5b8bb5', '#b07b98', '#c9a15b', '#7fa08a', '#8f83b3',
                '#c17d7d', '#6a9fb0', '#a9954e', '#9a8f7d', '#7d97c4',
                '#b58a5e', '#6f9e91',
            ],
            'vibrant' => [
                '#2563eb', '#dc2626', '#059669', '#d97706', '#7c3aed',
                '#0891b2', '#db2777', '#65a30d', '#ea580c', '#4f46e5',
                '#0d9488', '#c026d3',
            ],
            'mono' => [
                '#1e3a5f', '#2b537f', '#356295', '#3f72ab', '#5a86ba',
                '#6f97c6', '#84a8d2', '#4a7aa8', '#2f5c88', '#5f8ec0',
            ],
        ];
        return $palettes[$scheme] ?? $palettes['soft'];
    }

    /**
     * @return array<string,int>
     */
    private function getSampleCounts(string $locale): array
    {
        $lang = explode('_', $locale)[0];
        $sets = $this->sampleSets();
        return $sets[$lang] ?? $sets['en'];
    }

    /**
     * @return array<string,array<string,int>>
     */
    private function sampleSets(): array
    {
        return [
            'pt' => [
                'Educação' => 34, 'Saúde pública' => 29, 'Sustentabilidade' => 25,
                'Metodologia científica' => 22, 'Políticas públicas' => 20,
                'Tecnologia' => 19, 'Inovação' => 17, 'Ensino superior' => 16,
                'Gestão' => 14, 'Cultura' => 13, 'Meio ambiente' => 12,
                'Direitos humanos' => 11, 'Economia' => 10, 'Psicologia' => 9,
                'História' => 9, 'Linguística' => 8, 'Enfermagem' => 8,
                'Inteligência artificial' => 7, 'Comunicação' => 7, 'Ética' => 6,
                'Biodiversidade' => 6, 'Gênero' => 5, 'Educação a distância' => 5,
                'Nutrição' => 4, 'Agroecologia' => 4, 'Bioética' => 4,
                'Literatura' => 3, 'Epidemiologia' => 3, 'Currículo' => 3,
                'Governança' => 2, 'Mobilidade urbana' => 2, 'Patrimônio cultural' => 2,
            ],
            'es' => [
                'Educación' => 34, 'Salud pública' => 29, 'Sostenibilidad' => 25,
                'Metodología científica' => 22, 'Políticas públicas' => 20,
                'Tecnología' => 19, 'Innovación' => 17, 'Educación superior' => 16,
                'Gestión' => 14, 'Cultura' => 13, 'Medio ambiente' => 12,
                'Derechos humanos' => 11, 'Economía' => 10, 'Psicología' => 9,
                'Historia' => 9, 'Lingüística' => 8, 'Enfermería' => 8,
                'Inteligencia artificial' => 7, 'Comunicación' => 7, 'Ética' => 6,
                'Biodiversidad' => 6, 'Género' => 5, 'Educación a distancia' => 5,
                'Nutrición' => 4, 'Agroecología' => 4, 'Bioética' => 4,
                'Literatura' => 3, 'Epidemiología' => 3, 'Currículo' => 3,
                'Gobernanza' => 2, 'Movilidad urbana' => 2, 'Patrimonio cultural' => 2,
            ],
            'en' => [
                'Education' => 34, 'Public health' => 29, 'Sustainability' => 25,
                'Research methods' => 22, 'Public policy' => 20,
                'Technology' => 19, 'Innovation' => 17, 'Higher education' => 16,
                'Management' => 14, 'Culture' => 13, 'Environment' => 12,
                'Human rights' => 11, 'Economics' => 10, 'Psychology' => 9,
                'History' => 9, 'Linguistics' => 8, 'Nursing' => 8,
                'Artificial intelligence' => 7, 'Communication' => 7, 'Ethics' => 6,
                'Biodiversity' => 6, 'Gender' => 5, 'Distance learning' => 5,
                'Nutrition' => 4, 'Agroecology' => 4, 'Bioethics' => 4,
                'Literature' => 3, 'Epidemiology' => 3, 'Curriculum' => 3,
                'Governance' => 2, 'Urban mobility' => 2, 'Cultural heritage' => 2,
            ],
        ];
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $verb): array
    {
        $actions = parent::getActions($request, $verb);
        if (!$this->getEnabled()) {
            return $actions;
        }
        $router = $request->getRouter();
        $url = $router->url(
            $request,
            null,
            null,
            'manage',
            null,
            ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'blocks']
        );
        array_unshift(
            $actions,
            new LinkAction('settings', new AjaxModal($url, $this->getDisplayName()), __('manager.plugins.settings'))
        );
        return $actions;
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request): JSONMessage
    {
        if ($request->getUserVar('verb') !== 'settings') {
            return parent::manage($args, $request);
        }
        $form = new SettingsForm($this, $request->getContext()->getId());
        if (!$request->getUserVar('save')) {
            $form->initData();
            return new JSONMessage(true, $form->fetch($request));
        }
        $form->readInputData();
        if (!$form->validate()) {
            return new JSONMessage(true, $form->fetch($request));
        }
        $form->execute();
        // Aggregated keywords are cached; drop the cache so changes show at once.
        (new NotificationManager())->createTrivialNotification($request->getUser()->getId());
        return new JSONMessage(true);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\blocks\keywordCloudClassicBeautiful\KeywordCloudClassicBeautifulBlockPlugin', '\KeywordCloudClassicBeautifulBlockPlugin');
}
