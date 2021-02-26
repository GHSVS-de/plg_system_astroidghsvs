<?php
/**
* The AstroidTemplateHelper requires the plugin plg_system_astroidghsvs!
* Also template's index.php needs configurations to let helper run.
*/
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Filesystem\Path;

class AstroidTemplateHelper
{
	/**
	 * SOURCE *.scss files. Only filenames without extensions
	 * Fill AstroidTemplateHelper::$filesToCompile in index.php of template!
	 *
	 * Example. 2 files: ['template', 'template-zalta|noInsert']:
	 * 'template.scss' wil be compiled to 'template.css' and inserted in <HEAD>.
	 * 'template-zaltas.scss' will be compiled to 'template-zaltas.css' but
	 * NOT(!) inserted in <HEAD> because of '|noInsert' part.
	 *
	 * Watch out! All inserted files get an template style id postfix.
	 * In above example: You'll get something like 'template-20.css'.
	 *
	 * array
	 */
	public static $filesToCompile = [];

	/**
	 * Optional possibility to override helper settings in template index.php.
	 * array
	 */
	public static $compileSettingsCustom = [];

	/**
	 * Default helper settings. Can be overriden by $compileSettings.
	 * array
	 */
	protected static $compileSettingsDefault = [
		// The CSS file that will include $renderedCSS and variables from
		// Astroid template's style settings.
		'mainCssName' => 'template',

		// The SCSS SOURCE folder in template dir.
		// The folder where the "$filesToCompile" are located.
		'scssFolder' => 'scss-ghsvs',

		// The CSS TARGET folder in template dir..
		'cssFolder' => 'css',

		// Create SourceMaps? true|false.
		'sourceMaps' => false,
	];

	protected static $compileSettings = [];

	/**
	* Settings of plugin plg_system_astroidghsvs or empty.
	* Should be passed over by template index.php or so.
	* Registry
	*/
	public static $pluginParams;

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
	protected static $cssLink;

	/**
	 * Collected <link>s to be inserted in <head>.
	 * string
	 */
	protected static $replaceWith = '';

	/**
	 * ScssPhp compiler instance.
	 */
	protected static $compiler;

	/**
	 * Absolute path to target folder where *.css will be saved.
	 * string
	 */
	protected static $cssFolderAbs;

	protected static $mediaVersion;

	/**
	* Kompiliere eigene CSS-Dateien wie template.css, die dann in's Template
	* <head> eingebunden werden oder nicht eingebunden werden, wenn in
	* $filesToCompile entsprechend markiert.
	* BEACHTE: Hier ist es zu spät für HTMLHelper (und geht auch nicht früher
	*  im Astroid Framework)! Deshalb dieser "schräge" Weg via
	*  $app->setBody($body).
	* $renderedCSS ist das im Plugin plg_system_astroidghsvs abgeholte
	*  Astroid-CSS, das norm. im 2. "compiled-..css" des Astroid-Frameworks.
	* Das Plugin ruft diese Methode in onAfterAstroidRender() auf!
	*/
	public static function runScssGhsvs($renderedCSS)
	{
		$app = Factory::getApplication();
		// Um Fehlerseite scssError.php ohne Endlos-Loop ausgeben zu können.
		if (
			$app->input->get('noScssCompilation') == 1
			|| !(self::$pluginParams instanceof \Joomla\Registry\Registry)
		){
			return;
		}

		// 0|1|-1(=disabled)
		$force = (int) self::$pluginParams->get('forceSCSSCompilingGhsvs', 0);

		if ($force === -1 || !self::$filesToCompile)
		{
			return;
		}

		$template = Astroid\Framework::getTemplate();

		self::$compileSettings = \array_merge(
			self::$compileSettingsDefault,
			self::$compileSettingsCustom,
		);

		$cssFolder = self::$compileSettings['cssFolder'];
		$sourceMaps = self::$compileSettings['sourceMaps'] === true;

		$templateDir    = 'templates/' . $template->template;
		$templateDirAbs = JPATH_SITE . '/' . $templateDir;
		$scssFolderAbs  = $templateDirAbs . '/'
			. self::$compileSettings['scssFolder'];
		self::$cssFolderAbs = $templateDirAbs . '/' . $cssFolder;

		if (!is_dir(self::$cssFolderAbs))
		{
			Folder::create(self::$cssFolderAbs);
		}

		self::$cssLink = Uri::root() . $templateDir . '/' . $cssFolder;

		// Placeholder in index.php.
		$replaceThis    = '<!--<ghsvs:include type="stylesheets">-->';

		$collect = array();

		// Check if all provided scss fileNames exist and ignore not existing
		//  ones.
		foreach (AstroidTemplateHelper::$filesToCompile as $key => $fileName)
		{
			$fileName = explode('|', $fileName);

			if (is_file($scssFolderAbs . '/' . $fileName[0] . '.scss'))
			{
				// Key of $collect is REAL CSS file name. Can contain
				//  template->id.
				$cssFilePostfix = '-' . $template->id;

				// A '|' found after filename. Means don't insert in <head>.
				if (isset($fileName[1]))
				{
					self::$doNotInsert[$fileName[0]] = 1;
					$cssFilePostfix = '';
				}

				$collect[$fileName[0] . $cssFilePostfix] = $fileName[0];
			}
		}

		// No existing scss fileNames at all. Leave.
		if (!$collect)
		{
			return;
		}

		// Last compile time is...? Has anything changed? New compilation or
		//  not?

		// Create hash file name. Is indicator if settings have been changed.
		self::$mediaVersion = $template->id . '_' . md5(
				serialize(self::$compileSettings)
				. serialize($template)
				. serialize($collect)
				. serialize(self::$pluginParams->toArray())
			) . '_' . $template->hash;

		$ghsvsHashFile =
			'ghsvsHash-' . self::$mediaVersion . '.ghsvsHash';

		if (!is_file(self::$cssFolderAbs . '/' . $ghsvsHashFile))
		{
			$force = 1;
			$lastCompileTime = 0;

			// Delete older hashfiles of current template style.
			/*
			$styles = preg_grep(
				'~^ghsvsHash-' . $template->id  . '_'  . '.*\.(ghsvsHash)$~',
				scandir(self::$cssFolderAbs)
			);
			*/

			$styles = Folder::files(
				self::$cssFolderAbs,
				'^ghsvsHash-' . $template->id  . '_',
				true,
				true
			);

			foreach ($styles as $style)
			{
				unlink($style);
			}
		}
		else
		{
			$lastCompileTime = filemtime(self::$cssFolderAbs . '/'
				. $ghsvsHashFile);
		}

		// Check if relevant CSS files are missing. If yes force compiling.
		if ($force === 0)
		{
			foreach ($collect as $fileName => $value)
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
			foreach(
				Folder::files(
					$scssFolderAbs,
					'\.scss$',
					true,
					true
				) as $scssFile
			){
				if (filemtime($scssFile) > $lastCompileTime)
				{
					$force = 1;
					break;
				}
			}
		}

		if ($force === 1)
		{
			try
			{
				ini_set('memory_limit', '1024M');
				self::$compiler = new ScssPhp\ScssPhp\Compiler;
				self::$compiler->setImportPaths($scssFolderAbs);
				$document = Astroid\Framework::getDocument();

				foreach ($collect as $cssFileName => $fileName)
				{
					$content = '';

					if (
						(int) self::$pluginParams->get('forceSCSSUtf8Ghsvs', 0)
							=== 1
					){
						$content = '@charset "UTF-8";';
					}

					$content .= '@import "' . $fileName . '.scss";';

					if ($fileName === self::$compileSettings['mainCssName'])
					{
						$content .= '/* Android renderedCSS */' . $renderedCSS;

						// These are the color settings from Astroid template
						//  style.
						// In *.scss you can use them then as variables like
						//  $purple.
						// And it sets/overrides the --xyz CSS variables from
						//  bootrsp/_root.scss.
						$variables = $template->getThemeVariables();

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

					if ($sourceMaps === true)
					{
						self::setOrResetSourceMap($outputFileName);
					}
					else
					{
						self::setOrResetSourceMap();
					}

					$css = self::$compiler->compile($content);
					file_put_contents(
						self::$cssFolderAbs . '/' . $outputFileName, $css
					);
					##### UNMINIFIED END #####

					##### MINIFIED START #####
					$outputFileName = $cssFileName . '.min.css';
					self::$compiler->setOutputStyle(
						\ScssPhp\ScssPhp\OutputStyle::COMPRESSED
					);

					if ($sourceMaps === true)
					{
						self::setOrResetSourceMap($outputFileName);
					}
					else
					{
						self::setOrResetSourceMap();
					}

					$css = self::$compiler->compile($content);
					file_put_contents(
						self::$cssFolderAbs . '/' . $outputFileName, $css
					);
					##### MINIFIED END #####

					self::buildLink($fileName, $cssFileName);
				}
				file_put_contents(
					self::$cssFolderAbs . '/' . $ghsvsHashFile, ''
				);
			}
			// This is a bit awkward. Needs a scssError.php in template folder
			//  with <jdoc:include type="message" />.
			// Problem is that an $app->enqueueMessage would otherwise run into
			//  the void because the page content is already rendered.
			catch (\Exception $e)
			{
				$msg = $e->getMessage() . PHP_EOL . '<br>' . PHP_EOL . '<br><br>';
				$msg .= '<b>Bitte um Entschuldigung!' . PHP_EOL . '<br><br>' . 'Das Kompilieren der SCSS-Dateien wurde fatal abgebrochen. Das kann an einer Überlastung des Systems liegen oder auch ein Fehler in einer SCSS-Datei sein.' . PHP_EOL . '<br><br>' . '<a href="' . JUri::root() . '">Noch mal probieren? Zur Startseite!</a>.' . PHP_EOL . '<br><br>' . 'Info an Administrator schicken? Danke dafür! <a href="mailto:schraefl-bedachungen@ghsvs.de">schraefl-bedachungen[AT]ghsvs.de</a>.' . PHP_EOL . '<br><br>' . 'Vielen Dank für Ihr Verständnis.</b>';
				// In order for the message to be displayed, a new page request
				//  must unfortunately be made directly.
				$app->enqueueMessage($msg);
				// noScssCompilation=1 is a protection against endless loop.
				// See above.
				$app->redirect(
					Uri::root() . '?noScssCompilation=1&tmpl=scssError', 500
				);
				return;
			}
	 	}
		// $force === 0
		else
		{
			foreach ($collect as $cssFileName => $fileName)
			{
				self::buildLink($fileName, $cssFileName);
			}
		}

		if (self::$replaceWith !== '')
		{
			$body = $app->getBody();
			$body = str_replace($replaceThis, self::$replaceWith, $body);
			$app->setBody($body);
		}
	}

	private static function buildLink($fileName, $cssFileName)
	{
		if (!isset(self::$doNotInsert[$fileName]))
		{
			$href = self::$cssLink . '/' . $cssFileName;

			if (JDEBUG)
			{
				$href .= '.css';
			}
			else
			{
				$href .= '.min.css?' . self::$mediaVersion;
			}

			self::$replaceWith .= '<link href="' . $href
				. '" rel="stylesheet" />' . PHP_EOL;
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
}
