<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
#use Joomla\CMS\User\UserFactoryInterface;
#use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use GHSVS\Plugin\System\AstroidGhsvs\Extension\AstroidGhsvs;

#use GHSVS\Plugin\System\AstroidGhsvs\Helper\AstroidGhsvsHelper;

return new class () implements ServiceProviderInterface {
	public function register(Container $container): void
	{
		$container->set(
			PluginInterface::class,
			function (Container $container)
			{
				$dispatcher = $container->get(DispatcherInterface::class);
				$plugin = new AstroidGhsvs(
					$dispatcher,
					(array) PluginHelper::getPlugin('system', 'astroidghsvs'),
					// Kann dann in public function __construct() im Plugin zugewiesen werden. Siehe z.B. in ImportfontsGhsvs-Plugin. Oft nicht nötig!
					#new AstroidGhsvsHelper(),
					#new Cssparser(),
					#new FilterFieldHelper(),
				);
				$plugin->setApplication(Factory::getApplication());
				#$plugin->setDatabase($container->get(DatabaseInterface::class));
				#$plugin->setUserFactory($container->get(UserFactoryInterface::class));

				return $plugin;
			}
		);
	}
};
