<?php
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;

if (is_dir(JPATH_LIBRARIES . '/astroid/framework/library/astroid'))
{
	JLoader::registerNamespace('Astroid',
		JPATH_LIBRARIES . '/astroid/framework/library/astroid',
		false, false, 'psr4'
	);
}

class PlgSystemastroidghsvs extends CMSPlugin
{
	protected $app;

	// media/ path
	##protected static $basepath = 'plg_system_astroidghsvs';

	function __construct(&$subject, $config = array())
	{
		parent::__construct($subject, $config);

		// Lade bzw. registriere Namespaces für ScssPHP früh. Keine Garantie,
		//  dass das klappt.
		if (
			$this->params->get('loadScssPhpEarly', 0) === 1
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
	}

	public function onBeforeAstroidRender()
	{
		return;

		//Framework::getTemplate()->isAstroid;
	}

	/**
	* Astroid-Framework arbeitet eigen beim Kompilieren und Einsetzen
	* von SCSS und CSS.
	*/
	public function onAfterAstroidRender()
	{
		if (
			// Scheint nur bei Astroid-Templates ein 1 zu liefern
			Astroid\Framework::isSite()
			&& ($template = Astroid\Framework::getTemplate())

			// Wohl gar nicht nötig. Paranoia.
			&& $template->isAstroid
		){
			$templateDir = 'templates/' . $template->template;
			$templateDirAbs = JPATH_SITE . '/' . $templateDir;

			if (!file_exists($templateDirAbs . '/helper.php'))
			{
				return;
			}

			JLoader::register('AstroidTemplateHelper',
				$templateDirAbs . '/helper.php'
			);

			if (method_exists('AstroidTemplateHelper', 'runScssGhsvs'))
			{
				$document = Astroid\Framework::getDocument();

				// $renderedCSS Wird für eigenes Compiling benötigt und deshalb
				//  hier abgerufen, nachdem das Template durch Astroid bereits
				//  gerendert ist..
				$renderedCSS = $document->renderCss();

				// Und übergeben an eigenes Compiling via AstroidTemplateHelper.
				$css = AstroidTemplateHelper::runScssGhsvs($renderedCSS);
			}
		}
	}
}
