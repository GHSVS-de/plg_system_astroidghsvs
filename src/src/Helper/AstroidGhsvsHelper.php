<?php
/*
* This AstroidTemplateHelper requires the plugin plg_system_astroidghsvs!
* Also template's index.php needs configurations to let SCSS helper run.
*/
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Application\ApplicationHelper;

#[\AllowDynamicProperties]
class AstroidGhsvsHelper
{
	/* prerequites fail. Do absolutely nothing! */
	public const DONOTHING = 0;
	public const RUNSCSSGHSVS = 1;
	/* Include CSS but not compile before */
	public const INCLUDEONLY = 2;

	public static  $mode = '';

	/*
	 * SOURCE *.scss files. Only filenames without extensions
	 * Fill AstroidTemplateHelper::$filesToCompile in index.php of template!
	 *
	 * Example. 2 files: ['template', 'template-zalta|noInsert']:
	 * 'template.scss' wil be compiled to 'template.css' and inserted in <HEAD>.
	 * 'template-zaltas.scss' will be compiled to 'template-zaltas.css' but
	 * NOT(!) inserted in <HEAD> because of '|noInsert' part.
	 *
	 * Watch out! Inserted files sometimes get an template style id postfix.
	 * In above example: You'll get something like 'template-20.css'.
	 * You can disable that with parameter 'includeStyleId'.
	 *
	 * array
	 */
	public static $filesToCompile = null;

	/**
	 * Optional possibility to override helper settings. E.g. in template index.php.
	 * array
	 */
	public static $compileSettingsCustom = [];

	/** The calculated "absolute" settings. Merge of plugin settings and
	* $compileSettingsCustom.
	* array|null (as switch if already defined)
	*/
	protected static $compileSettings = null;

	/**
	* Settings of plugin plg_system_astroidghsvs or empty.
	* Should be passed over by template index.php or so.
	* Registry
	*/
	public static $pluginParams;

	/** Key of $collect[] is REAL CSS file name. Can contain template->id.
	 * Collects all relevant files (SCSS and lastly CSS).
	*/
	protected static $collectedFiles = null;

	/**
	 * A collector array. You don't have to do nothing here.
	 * E.g. a value in $filesToCompile like 'editor|noInsert'
	 * compiles to editor.css but won't be inserted in template via <link>.
	 */
	protected static $doNotInsert = [];

	/**
	 * Uri::root()-ed for <link>. Without filename.
	 * string
	 */
	protected static $cssLink = '';

	/**
	 * Collected <link>s to be inserted in <head>.
	 * string
	 */
	protected static $replaceWith = '';

	/**
	 * ScssPhp compiler instance.
	 */
	protected static $compiler;

	protected static $template = null;

	/**
	 * Absolute path to target folder where *.css will be saved.
	 * string
	 */
	protected static $cssFolderAbs;

	/**
	 * Absolute path to source folder where *.scss are located.
	 * string|null (as switch if variable already populated).
	 */
	protected static $scssFolderAbs = null;

	/** Placeholder in index.php.
	 */
	protected static $replaceThis = '<!--<ghsvs:include type="stylesheets">-->';

	protected static $debug = 0;
	protected static $logFile;

	/*
	@since 2023-12
	Bug fix Creation of dynamic property Astroid\Template::$myPath is deprecated
	*/
	//
	public static $templateMyPath;

	/*
	@since 2023-12
	Bug fix Creation of dynamic property Astroid\Template::$myMediaVersion is deprecated
	*/
	//
	public static $templateMyMediaVersion;

	/** We need some checks BEFORE Atroid renders the template.
	* E.g. to detect if HTMLHelper must be used or not.
	*/
	public static function preCheck()
	{
		$app = Factory::getApplication();

		/* URL-Parameter noScssCompilation um Fehlerseite scssError.php ohne
		Endlos-Loop ausgeben zu können.*/
		if ($app->input->get('noScssCompilation') == 1)
		{
			self::debug('Do nothing. URL-Parameter "noScssCompilation" found');
			return (self::$mode = self::DONOTHING);
		}

		// Prerequites fail.
		if (empty(self::$filesToCompile))
		{
			self::debug('Error. Do nothing. self::$filesToCompile empty');
			return (self::$mode = self::DONOTHING);;
		}

		self::initSettings();

		self::debug('preCheck() runs');

		// User decision.
		if (self::$compileSettings['forceSCSSCompilingGhsvs'] === -1)
		{
			self::debug('Do nothing. "forceSCSSCompilingGhsvs" disabled completely');
			return (self::$mode = self::DONOTHING);
		}

		/* User decision. If placeholderMode is OFF we will use HTMLHelper directly
		 in self::buildLink() */
		if (self::$compileSettings['forceSCSSCompilingGhsvs'] === -2)
		{
			self::debug('Do not compile but include existing CSS via HTMLHelper or placeholder');

			if (self::collectFiles() === false)
			{
				self::debug('Error. No CSS files found');
				return (self::$mode = self::DONOTHING);
			}

			foreach (self::$collectedFiles as $cssFileName => $fileName)
			{
				self::buildLink($fileName, $cssFileName);
			}

			return (self::$mode = self::INCLUDEONLY);
		}

		if (self::$compileSettings['forceSCSSCompilingGhsvs'] >= 0)
		{
			self::debug('Mode RUNSCSSGHSVS detected. Let us see... ');
			return (self::$mode = self::RUNSCSSGHSVS);
		}

		self::debug('Do nothing. All checks passed but have absolutely no idea what to do');
		return (self::$mode = self::DONOTHING);
	}

	/** Populates $pluginParams, $compileSettings, $debug, $logFile.

	*/
	private static function initSettings()
	{
		if (self::$compileSettings === null)
		{
			self::$pluginParams = PlgSystemAstroidGhsvs::getPluginParams();

			$compileSettingsDefault = [

				// The CSS file that will include $renderedCSS and variables from
				// Astroid template's style settings.
				'mainCssName' => 'template',

				// The CSS TARGET folder in template dir..
				'cssFolder' => 'css',

				'placeHolderMode' => (bool) self::$pluginParams->get('placeHolderMode', 1),
				'includeStyleId' => (bool) self::$pluginParams->get('includeStyleId', 1),

				// The SCSS SOURCE folder in template dir.
				// The folder where the "$filesToCompile" are located.
				'scssFolder' => self::$pluginParams->get('scssFolder', 'scss-ghsvs'),

				'ignoreAstroidVariables' => (int) self::$pluginParams->get(
					'ignoreAstroidVariables', 0),

				// 0|1|-1(=disabled)
				'forceSCSSCompilingGhsvs' => (int) self::$pluginParams->get(
					'forceSCSSCompilingGhsvs', 0),

				'sourceMaps' => (bool) self::$pluginParams->get('sourceMaps', 0),
				'forceSCSSUtf8Ghsvs' => (int) self::$pluginParams->get(
					'forceSCSSUtf8Ghsvs', 0),
			];

			self::$compileSettings = \array_merge($compileSettingsDefault,
				self::$compileSettingsCustom);

			self::$debug = (integer) self::$pluginParams->get('debug', 0);

			if (self::$debug === 1)
			{
				$cacheFolderAbs = JPATH_ADMINISTRATOR . '/cache/plg_system_astroidghsvs';

				if (!is_dir($cacheFolderAbs))
				{
					Folder::create($cacheFolderAbs);
				}

				self::$logFile = $cacheFolderAbs . '/debugLog.txt';
				self::debug('Debugging starts in plg_system_astroidghsvs '
					. date('Y-m-d H:i:s'));
			}
		}

		self::debug('initSettings() done');
	}

	/**
	* Kompiliere eigene CSS-Dateien wie template.css, die dann in's Template
	* <head> eingebunden werden oder nicht eingebunden werden, wenn in
	* $filesToCompile entsprechend markiert ('mod_jd_skillset|noInsert').
	* BEACHTE: Hier ist es zu spät für HTMLHelper (und geht auch nicht früher
	*  im Astroid Framework)! Deshalb dieser "schräge" Weg via
	*  $app->setBody($body).
	* $renderedCSS ist das im Plugin plg_system_astroidghsvs abgeholte
	*  Astroid-CSS, das norm. im 2. "compiled-..css" des Astroid-Frameworks.
	* Das Plugin ruft diese Methode in onAfterAstroidRender() auf!
	*/
	public static function runScssGhsvs($renderedCSS)
	{
		/* Beachte, dass diese Methode auch von außerhalb rufbar ist, bevor das Plugin
		vorbereitet hat. Deshalb:
		*/
		if (self::$compileSettings === null)
		{
			PlgSystemAstroidGhsvs::getModeAndThings();
		}

		self::debug('runScssGhsvs() runs');

		if (self::$mode === self::RUNSCSSGHSVS)
		{
			self::debug('Mode is self::RUNSCSSGHSVS');
			$force = 0;
			$lastCompileTime = 0;
			self::collectFiles();

			if (self::$compileSettings['forceSCSSCompilingGhsvs'] === 1)
			{
				$force = 1;
			}

			$isAstroid = !empty(self::$template->isAstroid);

			#### Last compile time is...? Has something changed? New compilation or not?

			$ghsvsHashFile = 'ghsvsHash-' . self::$templateMyMediaVersion
				. '.ghsvsHash';

			if ($force === 0)
			{
				if (!is_file(self::$cssFolderAbs . '/' . $ghsvsHashFile))
				{
					$force = 1;
					$lastCompileTime = 0;

					// Delete older hash files of this template style id.
					$styles = Folder::files(
						self::$cssFolderAbs, '^ghsvsHash-' . self::$template->id  . '_', true, true
					);

					foreach ($styles as $style)
					{
						unlink($style);
					}
				}
				else
				{
					$lastCompileTime = filemtime(self::$cssFolderAbs . '/' . $ghsvsHashFile);
				}
			}

			// Check if relevant CSS files are missing. If yes force compiling.
			if ($force === 0)
			{
				foreach (self::$collectedFiles as $fileName => $value)
				{
					if (!is_file(self::$cssFolderAbs . '/' . $fileName . '.css'))
					{
						$force = 1;
						break;
					}
				}
			}

			// Check if SCSS files were changed since last compile.
			if ($force === 0)
			{
				foreach(Folder::files(self::$scssFolderAbs, '\.scss$', true, true)
					as $scssFile)
				{
					if (filemtime($scssFile) > $lastCompileTime)
					{
						$force = 1;
						break;
					}
				}
			}

			if ($force === 1)
			{
				self::debug('$force is 1. Start SCSS compiling');

				try
				{
					ini_set('memory_limit', '1024M');
					self::$compiler = new ScssPhp\ScssPhp\Compiler;
					self::$compiler->setImportPaths(self::$scssFolderAbs);

					foreach (self::$collectedFiles as $cssFileName => $fileName)
					{
						$content = '';

						if (self::$compileSettings['forceSCSSUtf8Ghsvs'] === 1)
						{
							$content = '@charset "UTF-8";';
						}

						$content .= '@import "' . $fileName . '.scss";';

						if ($fileName === self::$compileSettings['mainCssName'])
						{
							$content .= '/* Astrroid renderedCSS */' . $renderedCSS;

							// These are the color settings from Astroid template
							//  style.
							// In *.scss you can use them then as variables like
							//  $purple.
							// And it sets/overrides the --xyz CSS variables from
							//  bootrsp/_root.scss.
							if ($isAstroid === true
								&& self::$compileSettings['ignoreAstroidVariables'] === 0)
							{
								$variables = self::$template->getThemeVariables();
							}

							if (!empty($variables))
							{
								self::$compiler->setVariables($variables);
							}
						}

						##### UNMINIFIED START #####
						$outputFileName = $cssFileName . '.css';
						self::$compiler->setOutputStyle(
							\ScssPhp\ScssPhp\OutputStyle::EXPANDED
						);

						if (self::$compileSettings['sourceMaps'] === true)
						{
							self::setOrResetSourceMap($outputFileName);
						}
						else
						{
							self::setOrResetSourceMap();
						}

						$css = self::$compiler->compileString($content)->getCss();
						file_put_contents(self::$cssFolderAbs . '/' . $outputFileName, $css);
						##### UNMINIFIED END #####

						##### MINIFIED START #####
						$outputFileName = $cssFileName . '.min.css';
						self::$compiler->setOutputStyle(
							\ScssPhp\ScssPhp\OutputStyle::COMPRESSED
						);

						if (self::$compileSettings['sourceMaps'] === true)
						{
							self::setOrResetSourceMap($outputFileName);
						}
						else
						{
							self::setOrResetSourceMap();
						}

						$css = self::$compiler->compileString($content)->getCss();
						file_put_contents(self::$cssFolderAbs . '/' . $outputFileName, $css);

						$gzFilename = self::$cssFolderAbs . '/' . $outputFileName . '.gz';

						if (self::$pluginParams->get('gzFiles', 1) === 1)
						{
							$gzFile = gzencode($css, $level = 9, FORCE_GZIP);
							file_put_contents($gzFilename, $gzFile);
						}
						elseif (is_file($gzFilename))
						{
							unlink($gzFilename);
						}
						##### MINIFIED END #####

						self::buildLink($fileName, $cssFileName);
					}

					file_put_contents(self::$cssFolderAbs . '/' . $ghsvsHashFile, '');
				}
				// This is a bit awkward. Needs a scssError.php in template folder
				//  with <jdoc:include type="message" />.
				// Problem is that an $app->enqueueMessage would otherwise run into
				//  the void because the page content is already rendered.
				catch (\Exception $e)
				{
					$app = Factory::getApplication();
					$msg = $e->getMessage() . PHP_EOL . '<br>' . PHP_EOL . '<br><br>';
					$msg .= '<b>Bitte um Entschuldigung!' . PHP_EOL . '<br><br>' . 'Das Kompilieren der SCSS-Dateien wurde fatal abgebrochen. Das kann an einer Überlastung des Systems liegen oder auch ein Fehler in einer SCSS-Datei sein.' . PHP_EOL . '<br><br>' . '<a href="' . JUri::root() . '">Noch mal probieren? Zur Startseite!</a>.' . PHP_EOL . '<br><br>' . 'Info an Administrator schicken? Danke dafür! <a href="mailto:schraefl-bedachungen@ghsvs.de">schraefl-bedachungen[AT]ghsvs.de</a>.' . PHP_EOL . '<br><br>' . 'Vielen Dank für Ihr Verständnis.</b>';
					// IN ASTROID TEMPLATES only: In order for the message to be displayed, a new page request
					//  must unfortunately be made directly.
					$app->enqueueMessage($msg);
					self::debug($msg);

					// noScssCompilation=1 is a protection against endless loop.
					// See above.
					if ($isAstroid)
					{
						$app->redirect(Uri::root() . '?noScssCompilation=1&tmpl=scssError',
							500);
					}

					return false;
				}
			}
			// $force === 0
			else
			{
				foreach (self::$collectedFiles as $cssFileName => $fileName)
				{
					self::buildLink($fileName, $cssFileName);
				}
			}
		}

		self::replacePlaceholder();

		return true;
	}

	private static function getTemplateData()
	{
		self::debug('getTemplateData() started');

		if (self::$template === null)
		{
			if (defined('_ASTROID'))
			{
				self::$template = Astroid\Framework::getTemplate();
			}
			else
			{
				self::$template = Factory::getApplication()->getTemplate(true);
			}

			// Da auch /media/ sein kann 'templates/' entfernt.
			self::$templateMyPath = self::$template->template;

			// At least needed for mediaVersion:
			if (empty(self::$template->id))
			{
				self::$template->id = Factory::getApplication()->getTemplate(true)->id;
			}

			/* needed for mediaVersion.
			*/
			if  (!isset(self::$template->hash))
			{
				// Funktioniert nicht wegen meinen BodyClasses, die sich ja ständig ändern
				// self::$template->hash = md5(serialize(self::$template));

				self::$template->hash = md5((string) self::$template->home . '_'
					. self::$template->template);
			}

			/* Create hash file name. Is indicator if settings have been changed.
			Be aware if JDEBUG that self::$template changes with each call! Because it includes
			the Joomla the changing mediaVersion as property.
			Therefore	self::$templateMyMediaVersion, too. In debug mode => always compilation.
			*/
			self::$templateMyMediaVersion = self::$template->id . '_' . md5(
				serialize(self::$compileSettings) . serialize(self::$collectedFiles)
				. serialize(self::$pluginParams->toArray())
				. '_' . self::$template->hash);
		}
	}

	/** Define absolute paths of scss files (source) and css files (target).
	*/
	private static function getWorkPaths($client_id = 0)
	{
		self::debug('getWorkPaths() started');

		if (self::$scssFolderAbs === null)
		{
			self::getTemplateData();

			$templatePathAbs = JPATH_SITE . '/templates/' . self::$templateMyPath;
			self::$scssFolderAbs = $templatePathAbs . '/'
				. self::$compileSettings['scssFolder'];

			if (!is_dir(self::$scssFolderAbs))
			{
				$templatePathAbs = JPATH_SITE . '/media/templates/'
					.  ApplicationHelper::getClientInfo($client_id)->name . '/'
					. self::$templateMyPath;
			self::$scssFolderAbs = $templatePathAbs . '/'
				. self::$compileSettings['scssFolder'];
			}

			self::$cssFolderAbs = $templatePathAbs . '/'
				. self::$compileSettings['cssFolder'];

			if (!is_dir(self::$cssFolderAbs))
			{
				Folder::create(self::$cssFolderAbs);
			}

			// Normally only in Astroid templates.
			if (self::$compileSettings['placeHolderMode'] === true)
			{
				self::$cssLink = Uri::root() . self::$templateMyPath . '/'
					. self::$compileSettings['cssFolder'] . '/';
			}
		}
	}

	/** Check if all provided scss fileNames exist and ignore not existing ones.
	 * Add to self::$collectedFiles[] if esxists.
	 * Add to self::$doNotInsert[] if required.
	*/
	private static function collectFiles()
	{
		self::debug('collectFiles() started');

		if (self::$collectedFiles === null)
		{
			self::$collectedFiles = [];
			self::getWorkPaths();

			/*
			$fileValues always starts with scss filename w\o extension followed by
			optional paras separated by |.
			e.g. test|noInsert
			Means: Compile the CSS file but don't insert css file in <head>.

			Crux (B\C hell): If noInsert is NOT set the CSS file gets a template style
			ID as postfix IF plugin setting includeStyleId is YES.
			To avoid that use
			test|noStyleId
			Means: Create CSS file without an ID postfix in filename. Insert that css
			file in <head>.
			*/
			foreach (self::$filesToCompile as $key => $fileValues)
			{
				$cssFilePostfix = '';
				$fileValues = explode('|', $fileValues);

				// The scss file without extension.
				$fileName = $fileValues[0];

				// The absolute scss file path with extension.
				$fileNameAbs = self::$scssFolderAbs . '/' . $fileName . '.scss';

				if (is_file($fileNameAbs))
				{
					self::debug('$filesToCompile: Start file: ' . $fileName);

					// Means: Don't insert in <head>, just compile. No ID postfix.
					if (in_array('noInsert', $fileValues))
					{
						self::$doNotInsert[$fileName] = 1;
					}
					elseif (
						self::$compileSettings['includeStyleId'] === true
						&& !in_array('noStyleId', $fileValues)
					){
						self::getTemplateData();
						$cssFilePostfix = '-' . self::$template->id;
					}

					// Key: CSS file name. Value: SCSS file name.
					self::$collectedFiles[$fileName . $cssFilePostfix] = $fileName;
				}
				else
				{
					self::debug('$filesToCompile: File not found: ' . $fileNameAbs);
				}
			}
		}

		if (empty(self::$collectedFiles))
		{
			return false;
		}

		return true;
	}

	private static function buildLink($fileName, $cssFileName)
	{
		self::debug('buildLink(' . $cssFileName . ') started');

		if (!isset(self::$doNotInsert[$fileName]))
		{
			$href = self::$cssLink . $cssFileName;

			if (JDEBUG)
			{
				$href .= '.css';
			}
			else
			{
				$href .= '.min.css';
			}

			if (self::$compileSettings['placeHolderMode'] === true)
			{
				$href .= '?' . self::$templateMyMediaVersion;
				self::$replaceWith .= '<link href="' . $href
				. '" rel="stylesheet" />' . PHP_EOL;
			}
			else
			{
				if (($wa =  PlgSystemAstroidGhsvs::getWa()))
				{
					$waName = 'plg_system_astroidghsvs.' . str_replace('/', '.', $href);
					$wa->registerStyle(
						$waName,
						$href, ['version' => self::$templateMyMediaVersion]
					)->useStyle($waName);
				}
				else
				{
				HTMLHelper::_('stylesheet', $href,
					['version' => self::$templateMyMediaVersion, 'relative' => true]
				);
				}
			}
		}
	}

	public static function replacePlaceholder()
	{
		self::debug('replacePlaceholder() started');

		if (self::$replaceWith !== '')
		{
			self::debug('... and used');
			$app = Factory::getApplication();
			$body = $app->getBody();
			$body = str_replace(self::$replaceThis, self::$replaceWith, $body);
			$app->setBody($body);
		}
		else
		{
			self::debug('... and NOT used');
		}
	}

	private static function setOrResetSourceMap($outputFileName = null)
	{
		if ($outputFileName === null)
		{
			self::$compiler->setSourceMap(
				ScssPhp\ScssPhp\Compiler::SOURCE_MAP_NONE
			);
		}
		else
		{
			self::$compiler->setSourceMap(
				ScssPhp\ScssPhp\Compiler::SOURCE_MAP_FILE
			);
			self::$compiler->setSourceMapOptions([
				// absolute path to write .map file
				'sourceMapWriteTo'  => self::$cssFolderAbs . '/'
					. $outputFileName . '.map',

				// relative or full url to the above .map file
				// In ghsvs.de /*# sourceMappingURL=template.css.map */
				'sourceMapURL'      => $outputFileName . '.map',

				// (optional) relative or full url to the .css file
				//'sourceMapFilename' => 'my-style.css',

				// partial path (server root) removed (normalized) to create a
				//  relative url
				'sourceMapBasepath' => Path::clean(JPATH_SITE, '/'),

				// (optional) prepended to 'source' field entries for relocating
				//  source files
				'sourceRoot' => Uri::root(),
			]);
		}
	}

	private static function debug(string $msg)
	{
		if (self::$debug === 1)
		{
			file_put_contents(self::$logFile, PHP_EOL . $msg . '.' . PHP_EOL,
				FILE_APPEND);
		}
	}
}
