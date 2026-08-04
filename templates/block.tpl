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

{if $kwcItems}
	<style>
		/* The block must never be able to force its container open. A <canvas> has
		   an intrinsic size, so without these caps it can pin a flex or grid column
		   and starve its siblings — the theme has no way to defend against it. */
		.block_keyword_cloud_classic { max-width: 100%; min-width: 0; }
		.block_keyword_cloud_classic .ojsbrKwc { position: relative; max-width: 100%; min-width: 0; }
		.block_keyword_cloud_classic .ojsbrKwc__canvas { display: none; width: 100%; max-width: 100%; height: auto; }
		.block_keyword_cloud_classic .ojsbrKwc--rendered .ojsbrKwc__canvas { display: block; margin: 0 auto; }
		.block_keyword_cloud_classic .ojsbrKwc__list {
			list-style: none; margin: 0; padding: 0.3em 0;
			display: flex; flex-wrap: wrap; align-items: center; justify-content: center;
			gap: 0.1em 0.45em; line-height: 1.1;
		}
		.block_keyword_cloud_classic .ojsbrKwc__list li { display: inline; margin: 0; padding: 0; }
		.block_keyword_cloud_classic .ojsbrKwc__list a {
			display: inline-block; text-decoration: none;
			transition: transform 0.15s ease, filter 0.15s ease;
		}
		.block_keyword_cloud_classic .ojsbrKwc__list a:hover,
		.block_keyword_cloud_classic .ojsbrKwc__list a:focus {
			transform: scale(1.12); filter: saturate(1.3) brightness(1.05); opacity: 1 !important;
		}
		.block_keyword_cloud_classic .ojsbrKwc--rendered .ojsbrKwc__list {
			position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
			overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0;
		}
		.block_keyword_cloud_classic .ojsbrKwc__hl {
			position: absolute; left: 0; top: 0;
			transform-origin: center center;
			white-space: nowrap; pointer-events: none; cursor: pointer;
			opacity: 0; z-index: 5;
			text-shadow: 0 1px 3px rgba(0,0,0,0.22), 0 0 12px rgba(255,255,255,0.6);
			transition: transform 0.16s cubic-bezier(.34,1.56,.64,1), opacity 0.16s ease;
		}
		.block_keyword_cloud_classic .ojsbrKwc__hl.is-visible { pointer-events: auto; }
		.block_keyword_cloud_classic .ojsbrKwc__sample {
			margin: 0.4em 0 0; font-size: 11px; line-height: 1.3;
			opacity: 0.6; text-align: center; font-style: italic;
		}
	</style>
	{literal}
	<script>
	(function () {
		function initClouds() {
			var boxes = document.querySelectorAll('.block_keyword_cloud_classic .ojsbrKwc');
			for (var b = 0; b < boxes.length; b++) {
				(function (box) {
					if (box.__kwcInit) { return; }
					var canvas = box.querySelector('.ojsbrKwc__canvas');
					var links = [].slice.call(box.querySelectorAll('.ojsbrKwc__list a'));
					if (!canvas || !links.length) { return; }
					if (typeof WordCloud !== 'function' || (WordCloud.isSupported === false)) { return; }
					box.__kwcInit = true;

					var list = links.map(function (a) {
						return [a.textContent, parseFloat(a.getAttribute('data-w')) || 16];
					});
					// Largest first so big words anchor the centre and small ones fill gaps.
					list.sort(function (x, y) { return y[1] - x[1]; });

					var colorMap = {}, urlMap = {}, sizeMap = {};
					links.forEach(function (a) {
						colorMap[a.textContent] = a.getAttribute('data-c') || '#777';
						urlMap[a.textContent] = a.getAttribute('href');
						sizeMap[a.textContent] = parseFloat(a.getAttribute('data-w')) || 16;
					});

					// Hover highlight overlay: the hovered keyword pops out, enlarged
					// and straightened, and is clickable (the canvas words are just
					// pixels, so this gives them a real hover/click affordance).
					var HL_POP = 1.22; // same pop for every keyword, applied to its real drawn size
					var hl = box.querySelector('.ojsbrKwc__hl');
					var hideTimer;
					function hideHl() { hideTimer = setTimeout(function () { hl.classList.remove('is-visible'); hl.style.opacity = '0'; }, 60); }
					function showHl(word, cx, cy, fontPx, family, rotateRad) {
						clearTimeout(hideTimer);
						// Match the word's real angle so it emphasises IN PLACE, not straightened.
						var deg = -(rotateRad || 0) * 180 / Math.PI;
						var base = 'translate(-50%, -50%) rotate(' + deg + 'deg) ';
						hl.textContent = word;
						hl.style.left = cx + 'px';
						hl.style.top = cy + 'px';
						hl.style.color = colorMap[word] || '#777';
						hl.style.fontFamily = family;
						// Same weight rule as the canvas, so the overlay does not look heavier.
						hl.style.fontWeight = fontPx > 30 ? '700' : (fontPx > 18 ? '600' : '400');
						hl.style.fontSize = Math.round(fontPx) + 'px';
						hl.setAttribute('data-url', urlMap[word] || '');
						// Start exactly as the word is drawn, then a gentle pop in place.
						hl.style.transition = 'none';
						hl.style.transform = base + 'scale(1)';
						hl.style.opacity = '0';
						void hl.offsetWidth;
						hl.style.transition = '';
						hl.style.transform = base + 'scale(' + HL_POP + ')';
						hl.style.opacity = '1';
						hl.classList.add('is-visible');
					}
					if (hl && !hl.__wired) {
						hl.__wired = true;
						hl.addEventListener('mouseenter', function () { clearTimeout(hideTimer); });
						hl.addEventListener('mouseleave', hideHl);
						hl.addEventListener('click', function () {
							var u = hl.getAttribute('data-url');
							if (u) { window.location.href = u; }
						});
					}

					var HALF_PI = Math.PI / 2;
					var rotationModes = {
						horizontal: { rotateRatio: 0, minRotation: 0, maxRotation: 0, rotationSteps: 1 },
						orthogonal: { rotateRatio: 0.35, minRotation: -HALF_PI, maxRotation: HALF_PI, rotationSteps: 2 },
						diagonal: { rotateRatio: 0.6, minRotation: -HALF_PI, maxRotation: HALF_PI, rotationSteps: 7 }
					};
					var rot = rotationModes[box.getAttribute('data-rotation')] || rotationModes.diagonal;
					var heightRatio = parseFloat(box.getAttribute('data-height')) || 0.92;
					var heightPx = parseInt(box.getAttribute('data-heightpx'), 10) || 0;
					var fontFamily = box.getAttribute('data-font') || 'Georgia, "Times New Roman", serif';

					function draw() {
						// Collapse the no-JS fallback list BEFORE measuring. That list is a
						// wide flex-wrap of keyword links, so measuring while it is still
						// expanded returns ITS width rather than the column's. That figure
						// was written to the canvas width attribute — and a canvas carries an
						// intrinsic size, so it then pinned the container open. In a flex
						// sidebar this starved the neighbouring text column down to zero
						// width and pushed the page into horizontal overflow.
						box.classList.add('ojsbrKwc--rendered');
						var w = box.clientWidth || (box.parentNode ? box.parentNode.clientWidth : 0);
						if (!w) {
							// Nothing measurable yet: restore the readable fallback and bail.
							box.classList.remove('ojsbrKwc--rendered');
							return;
						}
						w = Math.max(160, w);
						var h = heightPx > 0 ? heightPx : Math.max(200, Math.round(w * heightRatio));
						canvas.width = w;
						canvas.height = h;
						var scale = w / 260;
						WordCloud(canvas, {
							// A fresh copy every draw: shrinkToFit rewrites the weight of
							// whatever did not fit (weight * 3/4) IN the array it is given,
							// so reusing it made the cloud shrink a little more on every
							// redraw and never grow back when the sidebar widened again.
							list: list.map(function (pair) { return [pair[0], pair[1]]; }),
							gridSize: Math.max(3, Math.round(w / 52)),
							weightFactor: function (s) { return s * scale; },
							fontFamily: fontFamily,
							fontWeight: function (word, weight, fontSize) {
								return fontSize > 30 ? '700' : (fontSize > 18 ? '600' : '400');
							},
							color: function (word) { return colorMap[word] || '#777'; },
							rotateRatio: rot.rotateRatio,
							minRotation: rot.minRotation,
							maxRotation: rot.maxRotation,
							rotationSteps: rot.rotationSteps,
							backgroundColor: 'transparent',
							drawOutOfBound: false,
							shrinkToFit: true,
							clearCanvas: true,
							click: function (item) { var u = urlMap[item[0]]; if (u) { window.location.href = u; } },
							hover: function (item, dimension) {
								canvas.style.cursor = item ? 'pointer' : 'default';
								if (item && dimension) {
									showHl(
										item[0],
										dimension.x + dimension.w / 2,
										dimension.y + dimension.h / 2,
										// The size the word was DRAWN with, not the configured
										// one: shrinkToFit shrinks whatever did not fit, and
										// using the configured size made those keywords pop
										// far more than the others.
										dimension.fontSize || (sizeMap[item[0]] || 16) * scale,
										fontFamily,
										dimension.rotate
									);
								} else {
									hideHl();
								}
							}
						});
						box.classList.add('ojsbrKwc--rendered');
					}

					draw();
					var t;
					window.addEventListener('resize', function () {
						clearTimeout(t);
						// Do NOT drop --rendered here: that re-expands the fallback list and
						// draw() would measure it again instead of the column.
						t = setTimeout(draw, 250);
					});
				})(boxes[b]);
			}
		}
		// The library may finish loading after DOMContentLoaded; poll briefly.
		var tries = 0;
		var iv = setInterval(function () {
			tries++;
			if (typeof WordCloud === 'function' || tries > 25) { clearInterval(iv); initClouds(); }
		}, 150);
		if (document.readyState !== 'loading') { initClouds(); }
		else { document.addEventListener('DOMContentLoaded', initClouds); }
	})();
	</script>
	{/literal}
{/if}
