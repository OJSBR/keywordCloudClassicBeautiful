{**
 * templates/settingsForm.tpl
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * KeywordCloudClassicBeautiful block plugin settings.
 *}
<script>
	$(function() {ldelim}
		$('#keywordCloudClassicBeautifulSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="keywordCloudClassicBeautifulSettingsForm" method="post" action="{url router=PKP\core\PKPApplication::ROUTE_COMPONENT op="manage" category="blocks" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="keywordCloudClassicBeautifulSettingsFormNotification"}

	<div id="description">{translate key="plugins.block.keywordCloudClassicBeautiful.settings.description"}</div>

	{fbvFormArea id="keywordCloudClassicBeautifulSettingsFormArea"}
		{fbvFormSection title="plugins.block.keywordCloudClassicBeautiful.settings.appearance"}
			{fbvElement type="select" id="size" from=$sizeOptions selected=$size translate=false label="plugins.block.keywordCloudClassicBeautiful.settings.size"}
			{fbvElement type="text" id="heightPx" value=$heightPx size=$fbvStyles.size.SMALL label="plugins.block.keywordCloudClassicBeautiful.settings.heightPx"}
			{fbvElement type="select" id="rotation" from=$rotationOptions selected=$rotation translate=false label="plugins.block.keywordCloudClassicBeautiful.settings.rotation"}
			{fbvElement type="select" id="palette" from=$paletteOptions selected=$palette translate=false label="plugins.block.keywordCloudClassicBeautiful.settings.palette"}
			{fbvElement type="select" id="font" from=$fontOptions selected=$font translate=false label="plugins.block.keywordCloudClassicBeautiful.settings.font"}
		{/fbvFormSection}

		{fbvFormSection title="plugins.block.keywordCloudClassicBeautiful.settings.content"}
			{fbvElement type="text" id="numKeywords" value=$numKeywords size=$fbvStyles.size.SMALL label="plugins.block.keywordCloudClassicBeautiful.settings.numKeywords"}
			{fbvElement type="text" id="minFont" value=$minFont size=$fbvStyles.size.SMALL label="plugins.block.keywordCloudClassicBeautiful.settings.minFont"}
			{fbvElement type="text" id="maxFont" value=$maxFont size=$fbvStyles.size.SMALL label="plugins.block.keywordCloudClassicBeautiful.settings.maxFont"}
		{/fbvFormSection}

		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="sampleWhenEmpty" name="sampleWhenEmpty" value="1" checked=$sampleWhenEmpty label="plugins.block.keywordCloudClassicBeautiful.settings.sampleWhenEmpty"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons submitText="common.save"}
</form>
