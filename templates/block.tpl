{**
 * templates/block.tpl
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Sidebar keyword cloud — a real packed cloud (varied sizes, positions,
 * rotations and colours), drawn with a vendored, self-contained layout library
 * (no external CDN). Degrades to an accessible list of links without JavaScript.
 *}
{* Blocks are rendered after the page head, so the stylesheet is linked here. *}
<link rel="stylesheet" type="text/css" href="{$kwcStyleUrl|escape}">
<div class="pkp_block block_keyword_cloud_classic">
	<h2 class="title">{translate key="plugins.block.keywordCloudClassicBeautiful.title"}</h2>
	<div class="content">
		{if $kwcItems}
			<div class="ojsbrKwc" data-rotation="{$kwcRotation|escape}" data-height="{$kwcHeightRatio|escape}" data-heightpx="{$kwcHeightPx|escape}" data-font="{$kwcFontStack|escape}">
				<canvas class="ojsbrKwc__canvas" aria-hidden="true"></canvas>
				<span class="ojsbrKwc__hl" aria-hidden="true"></span>
				<ul class="ojsbrKwc__list">
					{foreach from=$kwcItems item=kw}
						<li><a href="{$kw.url|escape}" data-w="{$kw.font}" data-c="{$kw.color}" style="color:{$kw.color};font-size:{$kw.font}px;font-weight:{$kw.weight};opacity:{$kw.opacity};" title="{$kw.title|escape}">{$kw.text|escape}</a></li>
					{/foreach}
				</ul>
			</div>
			{if $kwcIsSample}
				<p class="ojsbrKwc__sample">{translate key="plugins.block.keywordCloudClassicBeautiful.sampleNote"}</p>
			{/if}
		{/if}
	</div>
</div>
