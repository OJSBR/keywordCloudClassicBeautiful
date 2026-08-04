<?php

/**
 * @file SettingsForm.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SettingsForm
 *
 * @brief Settings for the KeywordCloudClassicBeautiful block plugin.
 */

namespace APP\plugins\blocks\keywordCloudClassicBeautiful;

use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;

class SettingsForm extends Form
{
    private const FIELDS = [
        'numKeywords' => 'int',
        'minFont' => 'int',
        'maxFont' => 'int',
        'size' => 'string',
        'heightPx' => 'int',
        'rotation' => 'string',
        'palette' => 'string',
        'font' => 'string',
        'sampleWhenEmpty' => 'bool',
    ];

    private const DEFAULTS = [
        'numKeywords' => 40, 'minFont' => 12, 'maxFont' => 48,
        'size' => 'large', 'heightPx' => 0, 'rotation' => 'diagonal', 'palette' => 'soft',
        'font' => 'serif', 'sampleWhenEmpty' => 1,
    ];

    public function __construct(private KeywordCloudClassicBeautifulBlockPlugin $plugin, private int $contextId)
    {
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    public function initData(): void
    {
        foreach (array_keys(self::FIELDS) as $name) {
            $value = $this->plugin->getSetting($this->contextId, $name);
            $this->setData($name, ($value === null || $value === '') ? self::DEFAULTS[$name] : $value);
        }
        parent::initData();
    }

    public function readInputData(): void
    {
        $this->readUserVars(array_keys(self::FIELDS));

        $num = (int) $this->getData('numKeywords');
        $this->setData('numKeywords', $num > 0 ? min(120, max(5, $num)) : self::DEFAULTS['numKeywords']);

        $minFont = (int) $this->getData('minFont');
        $maxFont = (int) $this->getData('maxFont');
        $minFont = $minFont > 0 ? min(60, max(6, $minFont)) : self::DEFAULTS['minFont'];
        $maxFont = $maxFont > 0 ? min(120, max($minFont + 2, $maxFont)) : self::DEFAULTS['maxFont'];
        $this->setData('minFont', $minFont);
        $this->setData('maxFont', $maxFont);

        $height = (int) $this->getData('heightPx');
        $this->setData('heightPx', $height > 0 ? min(1400, max(120, $height)) : 0);

        foreach (['size' => ['small', 'medium', 'large'], 'rotation' => ['horizontal', 'orthogonal', 'diagonal'], 'palette' => ['soft', 'vibrant', 'mono'], 'font' => ['serif', 'sans', 'rounded']] as $name => $allowed) {
            if (!in_array($this->getData($name), $allowed, true)) {
                $this->setData($name, self::DEFAULTS[$name]);
            }
        }
    }

    public function fetch($request, $template = null, $display = false): string
    {
        $p = 'plugins.block.keywordCloudClassicBeautiful.settings.';
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pluginName' => $this->plugin->getName(),
            'sizeOptions' => [
                'small' => __($p . 'size.small'),
                'medium' => __($p . 'size.medium'),
                'large' => __($p . 'size.large'),
            ],
            'rotationOptions' => [
                'horizontal' => __($p . 'rotation.horizontal'),
                'orthogonal' => __($p . 'rotation.orthogonal'),
                'diagonal' => __($p . 'rotation.diagonal'),
            ],
            'paletteOptions' => [
                'soft' => __($p . 'palette.soft'),
                'vibrant' => __($p . 'palette.vibrant'),
                'mono' => __($p . 'palette.mono'),
            ],
            'fontOptions' => [
                'serif' => __($p . 'font.serif'),
                'sans' => __($p . 'font.sans'),
                'rounded' => __($p . 'font.rounded'),
            ],
        ]);
        return parent::fetch($request, $template, $display);
    }

    public function execute(...$functionArgs)
    {
        foreach (self::FIELDS as $name => $type) {
            $this->plugin->updateSetting($this->contextId, $name, $this->getData($name), $type);
        }
        parent::execute(...$functionArgs);
    }
}
