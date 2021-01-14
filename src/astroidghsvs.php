<?php
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;

JLoader::registerNamespace('Astroid', JPATH_LIBRARIES . '/astroid/framework/library/astroid', false, false, 'psr4');

class PlgSystemastroidghsvs extends CMSPlugin
{
	protected $app;
 
	protected $execute = null;
	
	// media/ path
	protected static $basepath = 'plg_system_astroidghsvs';
	
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

			if (file_exists($templateDirAbs . '/helper.php'))
			{
				 JLoader::register('AstroidTemplateHelper', $templateDirAbs . '/helper.php');
			}
			else
			{
				return;
			}
			
			$document = Astroid\Framework::getDocument();
			
			// Wird für eigenes Compiling benötigt und deshalb hier abgerufen.
			$renderedCSS = $document->renderCss();
			
			// Und übergeben an eigenes Compiling via AstroidTemplateHelper.
			$css = AstroidTemplateHelper::runScssGhsvs($renderedCSS);
		}
	}
	
	/**
	* Unfertig!!! Da ich eigene CSS verwenden will, können hiermit
	* die compiled-*.css gelöscht werden.
	*/
	public function onAfterInitialise()
	{
		return;

		if (
			$this->app->isClient('administrator')
			|| $this->params->get('deleteAstroidCss', 0) === 0)
		{
			return;
		}

		$cssFolder = JPATH_SITE . '/' . $this->params->get('cssFolder', 'templates/kujm/css');
		$cssFiles = Folder::files($cssFolder, '.css$', false, false);
		
		foreach ($cssFiles as $file)
		{
			if (strpos($file, 'compiled-') === 0)
			{
				File::delete($cssFolder . '/' . $file);
			}
		}
	}
}
