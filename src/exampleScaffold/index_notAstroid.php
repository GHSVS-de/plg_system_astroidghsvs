<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;

$isRobot = $this->params->get('isRobot');

if ($done = PluginHelper::isEnabled('system', 'astroidghsvs'))
{
	JLoader::register('AstroidGhsvsHelper',
		JPATH_PLUGINS . '/system/astroidghsvs/src/Helper/AstroidGhsvsHelper.php');

	/*
	 * OPTIONAL possibility to override all or some parameters of
	 *  AstroidGhsvsHelper::$compileSettingsDefault (= Settings of plg__astroidghsvs).
	 * But you don't have to! Leave array empty or remove if you like default
	 * 	settings.
	 * Heads up! The Helper does not protect you in the case of incorrect
	 * 	entries like wrong folders or so.
	 */
	/*AstroidGhsvsHelper::$compileSettingsCustom = [
		'sourceMaps' => false,
		'scssFolder' => 'scss-ghsvs',
		// ## Needs AstroidGhsvsHelper::$replaceThis comment in template index.php:
		'placeHolderMode' => true,
		// ## Set to -1 if you want to disable compiling completely.
		'forceSCSSCompilingGhsvs' => 0,
		'includeStyleId' => true,
	];*/

	if ($isRobot)
	{
		AstroidGhsvsHelper::$compileSettingsCustom = [
			'forceSCSSCompilingGhsvs' => -1,
		];
		$done = false;
	}
	else
	{
		/*
		These scss files (enter without extension!) must be in scss folder of
		this template (see parameter 'scssFolder' (default: 'scss-ghsvs'))!
		Only 'template' will include Astroid variables automatically. Others not.
			(See parameter 'mainCssName' (default: 'template')).
		'template' will compile 'scss-ghsvs/template.scss' to 'css/template.css'
		and 'css/template.min.css'
		and according sourcemap files if activated (see parameter 'sourceMaps'
		(default: false)).
		The resulting CSS files will be included in the template if not marked
		with a '|noInsert'. See
		Der Befehl zum Kompilieren wird in einem System-Plugin in
		"public function onAfterAstroidRender()" abgefeuert.
		*/
		AstroidGhsvsHelper::$filesToCompile = [
			'editor-inserttagsghsvs|noInsert',
			'editor-prism|noInsert',
			'print|noInsert',
			'prism-ghsvs|noInsert',
			'venobox|noInsert',
			'template',
		];

		/* Because this is a non-Astroid template the plugin method
		onAfterAstroidRender() is not fired. Therefore: */
		$done = AstroidGhsvsHelper::runScssGhsvs('');
	}
}

// PhpScss compilation failed or deactivated?
if ($done === false)
{
	HTMLHelper::_('stylesheet',
		'templates/' . $this->template . '/' . $this->params->get('templateCSS', 'css/template.min') . '.css',
		array('version' => 'auto', 'relative' => false)
	);
}
