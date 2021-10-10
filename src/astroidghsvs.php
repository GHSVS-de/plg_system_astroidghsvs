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
}
