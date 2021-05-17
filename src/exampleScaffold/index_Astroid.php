<?php
defined('_JEXEC') or die;
defined('_ASTROID') or die(
	'Error: Please install and activate the Astroid Framework to use this
	template! (www.astroidframework.com).');

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;
use Joomla\CMS\Language\Text;

$isRobot = Factory::getApplication()->client->robot;

// Is plg_system_astroidghsvs installed and activated?
$pluginParams = PluginHelper::getPlugin('system', 'astroidghsvs');

if ($pluginParams)
{
	$pluginParams = new Registry($pluginParams->params);

	if ($isRobot)
	{
		$pluginParams->set('forceSCSSCompilingGhsvs', -1);
	}
}
else
{
	$pluginParams = new Registry();
	$pluginParams->set('forceSCSSCompilingGhsvs', -1);
}

if (file_exists(__DIR__ . '/helper.php'))
{
	JLoader::register('AstroidTemplateHelper', __DIR__ . '/helper.php');

	/**
	 * OPTIONAL possibility to override all or some parameters of helper's
	 *  '$compileSettingsDefault'.
	 * But you don't have to! Leave array empty if you like default settings.
	 * Heads up! The Helper does not protect you in the case of incorrect
	 * 	entries like wrong folders or so.
	 */
	AstroidTemplateHelper::$compileSettingsCustom = [
		// Create SourceMaps? true|false.
		'sourceMaps' => true,
	];

	// These scss files (enter without extension!) must be in scss folder of
	//  this template (see parameter 'scssFolder' (default: 'scss-ghsvs'))!
	//
	// Only 'template' will include Astroid variables automatically. Others not.
	// 	(See parameter 'mainCssName' (default: 'template')).
	// 'template' will compile 'scss-ghsvs/template.scss' to 'css/template.css'
	//  and 'css/template.min.css'
	//  and according sourcemap files if activated (see parameter 'sourceMaps'
	//  (default: false)).
	// The resulting CSS files will be included in the template if not marked
	//  with a '|noInsert'. See
	// Der Befehl zum Kompilieren wird in einem System-Plugin in
	//  "public function onAfterAstroidRender()" abgefeuert.
	/**
	 *
	 */
	AstroidTemplateHelper::$filesToCompile = array(
		'mod_jd_skillset|noInsert',
		'slick|noInsert',
		'template-zalta|noInsert',
		'template',
		'tpportfolio_basic|noInsert',
	);

	AstroidTemplateHelper::$pluginParams = $pluginParams;
}

$document = Astroid\Framework::getDocument();

if ((int) $pluginParams->get('forceSCSSCompilingGhsvs', 0) === -1)
{
	HTMLHelper::_('stylesheet', 'template.min.css',
		array('version' => 'auto', 'relative' => true));
}

############ JAVASCRIPT START.
// Are own overrides within template, because Astroid's way  partly too
//  complicated to override and obscure. In addition, so easier to use more
//  current libraries.
HTMLHelper::_('bootstrap.framework');
HTMLHelper::_('script', 'vendor/jquery.easing.min.js',
	array('version' => 'auto', 'relative' => true));

HTMLHelper::_('script', 'vendor/jquery.astroidmobilemenu.min.js',
	array('version' => 'auto', 'relative' => true));

HTMLHelper::_('script', 'vendor/jquery.jdmegamenu.min.js',
	array('version' => 'auto', 'relative' => true));

HTMLHelper::_('script', 'vendor/jquery.offcanvas.min.js',
	array('version' => 'auto', 'relative' => true));

HTMLHelper::_('script', 'script.min.js',
	array('version' => 'auto', 'relative' => true));

HTMLHelper::_('script', 'custom.js',
	array('version' => 'auto', 'relative' => true));
############ JAVASCRIPT ENDE.
?>
<!DOCTYPE html>
<html lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>" class="no-js jsNotActive">
<head>
		<!-- astroid head-meta -->
		<astroid:include type="head-meta" />
		<!-- /astroid head-meta -->

		<!-- joomla-head -->
		<jdoc:include type="head" />
		<!-- /joomla-head -->

<!--DO-NOT-REMOVE-OR-CHANGE-THIS-COMMENT_START
<astroid:include type="head-stylessssssssssssssss" />
/DO-NOT-REMOVE-OR-CHANGE-THIS-COMMENT_END-->

<!--DO-NOT-REMOVE-OR-CHANGE-THE-FOLLOWING-COMMENT:-->
<!--<ghsvs:include type="stylesheets">-->

		<!-- astroid head-scripts -->
		<astroid:include type="head-scripts" />
		<!-- /astroid head-scripts -->

		<!-- astroid body-scripts -->
<!--DO-NOT-REMOVE-OR-CHANGE-THIS-COMMENT_START
<astroid:include type="body-scriptssssssssssssssss" />
/DO-NOT-REMOVE-OR-CHANGE-THIS-COMMENT_END-->
		<!-- /astroid body-scripts -->
</head>

<body class="<?php echo $bodyClasses; ?>">

<div id="div4all">

<?php $document->include('document.body', array(
	'templatePath' => 'templates/' . $this->template
)); ?>

<jdoc:include type="modules" name="debug" />
<jdoc:include type="message" />

</div><!--/#div4all-->
</body>
</html>
