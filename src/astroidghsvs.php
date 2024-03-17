<?php
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Astroid\Framework;
use Astroid\Helper;
use Astroid\Helper\Template;

if (is_dir(JPATH_LIBRARIES . '/astroid/framework/library/astroid'))
{
	JLoader::registerNamespace('Astroid',
		JPATH_LIBRARIES . '/astroid/framework/library/astroid',
		false, false, 'psr4'
	);
}

JLoader::register('AstroidGhsvsHelper',
	__DIR__ . '/src/Helper/AstroidGhsvsHelper.php');

class PlgSystemAstroidGhsvs extends CMSPlugin
{
	protected $app;

	protected static $mode;

	// media/ path
	##protected static $basepath = 'plg_system_astroidghsvs';

	/* for public static getter function. Via
		PlgSystemAstroidGhsvs::getPluginParams. */
	protected static $plgParams;

	// Also usable in other files via PlgSystemBS3Ghsvs::$isJ3.
	public static $isJ3 = true;

	// Used in other files via $wa =  PlgSystemAstroidGhsvs::getWa().
	protected static $wa = null;

	function __construct(&$subject, $config = array())
	{
		parent::__construct($subject, $config);

		self::$isJ3 = version_compare(JVERSION, '4', 'lt');

		// Lade bzw. registriere Namespaces für ScssPHP früh. Keine Garantie,
		//  dass das klappt. Aber es kann fatal enden, falls in Joomla 5 nicht.
		if (
			$this->params->get('loadScssPhpEarly', 1) === 1
			&& $this->app->isClient('site')
		){
			// Leider funktioniert das nicht im Zusammenspiel mit
			// Astroid-Template. da das derzeit autoloader für SCSSPHP nicht
			// verwendet(?)
			require_once __DIR__ . '/vendor/autoload.php';

			// Also fieser "Trick". Block loading via "require_once scss.inc.php".
			// Geht aber nur, falls nicht jemand früher dran war.
			if (! class_exists('ScssPhp\ScssPhp\Version', false))
			{
				include_once __DIR__ . '/vendor/scssphp/scssphp/src/Version.php';
			}
		}

		// For getter method from outside.
		static::$plgParams = $this->params;
	}

	/* Absolut keine Idee, warum der AstroidGhsvsHelper gelegentlich sein
	self::-Variablen verliert. */
	public function onBeforeRender()
	{
		if (empty(self::$mode))
		{
			// Does several things.
			self::getModeAndThings();
		}
	}

	public function onBeforeAstroidRender()
	{
		//Framework::getTemplate()->isAstroid;
	}

	/**
	* Astroid-Framework arbeitet eigen beim Kompilieren und Einsetzen
	* von SCSS und CSS.
	*/
	public function onAfterAstroidRender()
	{
		/* 2 scenarios: HTMLHelper has already done it's job.
		Or self::$replaceWith is populated and placeholder will be replaced */
		if (self::$mode === AstroidGhsvsHelper::INCLUDEONLY)
		{
			AstroidGhsvsHelper::replacePlaceholder();
			return;
		}

		if (self::$mode !== AstroidGhsvsHelper::RUNSCSSGHSVS)
		{
			return;
		}

		if (
			// Scheint nur bei Astroid-Templates ein 1 zu liefern
			Astroid\Framework::isSite()
			&& ($template = Astroid\Framework::getTemplate())

			// Wohl gar nicht nötig. Paranoia.
			&& $template->isAstroid
		){

				$document = Astroid\Framework::getDocument();

				// $renderedCSS Wird für eigenes Compiling benötigt und deshalb
				//  hier abgerufen, nachdem das Template durch Astroid bereits
				//  gerendert ist..
				$renderedCSS = $document->renderCss();

				// Und übergeben an eigenes Compiling via AstroidGhsvsHelper.
				AstroidGhsvsHelper::runScssGhsvs($renderedCSS);
			}
		}

	/**
	 * Getter for parameters of this plugin via PlgSystemAstroidGhsvs::getPluginParams()
	 *
	 */
	public static function getPluginParams()
	{
		return static::$plgParams;
	}
	public static function getModeAndThings()
	{
		// You'll get one of the AstroidGhsvsHelper constants.
		self::$mode = AstroidGhsvsHelper::preCheck();
	}

	/*
	See https://github.com/templaza/astroid-framework/commit/397b263242ca80784a8cd1186bcd096687f4ebfa
	and https://github.com/templaza/astroid-framework/issues/67
	*/
	public function onAfterAstroidTemplateFormLoad($template, $form)
	{
		if ($this->params->get('removeFields', 0) === 1
			&& $template->isAstroid && Framework::isAdmin()
		){
			$myForm = $form->getForm();

			/* Siehe libraries\astroid\framework\options\*.xml */
			$removeFieldsByFieldset = [
				'articles' => 1,
				'basic' => 1,
				'astroid_colours' => 1,
				'astroid_custom' => 1,
				'astroid_mscellaneous' => 1,
				'preset' => 1,
				'astroid_social' => 1,
				'astroid_theming' => 1,
				'astroid_typography' => 1,
			];

			$css = [];
			$fieldsets = $myForm->getFieldsets('params');

			foreach ($removeFieldsByFieldset as $fieldsetName => $active)
			{
				if (isset($fieldsets[$fieldsetName]) && $active === 1)
				{
					$fields = $myForm->getFieldset($fieldsetName);
					$label = Text::_($fieldsets[$fieldsetName]->label);

					foreach ($fields as $field)
					{
							$name = $field->getAttribute('name');
							$myForm->removeField($name, 'params');
					}

					$css[$fieldsetName] = 'a#' . $fieldsetName . '-astroid-tab';

					if ($label)
					{
						// Beachte leitendes Komma!
						$css[$fieldsetName] .= ',li[data-sidebar-tooltip="' . $label . '"]';
					}

					if ($fieldsetName === 'preset')
					{
						// Beachte leitendes Komma!
						$css[$fieldsetName] .= ',#astroid-form-fieldset-section-layout_group .astroid-form-preset-load.dropdown';
					}
				}
			}

			if ($css)
			{
				Framework::getDocument()->addStyleDeclaration(implode(',', $css) . '{display:none}');
			}

			/*
			Für leichteres Verständnis z.B.
				libraries\astroid\framework\options\header.xml
			*/

			/*
			Key: Fieldset-Name. Im Unter-Array zu entfernende type="astroidgroup" und
				zugeordnete Felder mit astroidgroup="astroidgroup-Name".
			*/
			$removeFieldsByAstroidGroup = [
				'astroid_header' => [
					'header_offcanvas_options_element',
					'header_animation_options_element',
				]
			];

			foreach ($removeFieldsByAstroidGroup as $fieldsetName => $astroidGroups)
			{
				if (isset($fieldsets[$fieldsetName]))
				{
					$fields = $myForm->getFieldset($fieldsetName);

					foreach ($fields as $field)
					{
						$name = $field->getAttribute('name');
						$isGroupMama = $field->getAttribute('type') === 'astroidgroup'
							&& in_array($name, $astroidGroups);
						$isGroupChild = in_array($field->getAttribute('astroidgroup'),
							$astroidGroups);

						if ($isGroupMama || $isGroupChild)
						{
							$myForm->removeField($name, 'params');
						}
					}
				}
			}

			/*
			Key: Field-Name. Einzelne Felder, die aus verbleibenden Fieldsets oder
				astroidgroups entfernt werden sollen.
			*/
			$removeFieldsByName = [
				'mobile_logo' => 1,
				'enable_sticky_badge' => 1,
				'dropdown_trigger' => 1,
				'dropdown_arrow' => 1,
			];

			foreach ($removeFieldsByName as $name => $active)
			{
				$myForm->removeField($name, 'params');
			}
		}
	}

	public static function getWa()
	{
		if (self::$isJ3 === false && empty(self::$wa))
		{
			self::$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
			self::$wa->getRegistry()->addExtensionRegistryFile('plg_system_astroidghsvs');
		}

		return self::$wa;
	}
}
