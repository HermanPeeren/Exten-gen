<?php
/**
 * @package    Extengen
 * @subpackage Extengen
 * @version    1.1.0
 *
 * @copyright  Yepr, Herman Peeren
 * @license    GPL 3.0
 */


defined('_JEXEC') or die;

use Yepr\Component\Extengen\Administrator\Extension\ExtengenComponent;
//use Yepr\Component\Extengen\Administrator\Helper\AssociationsHelper;//...

use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\CategoryFactory;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\HTML\Registry;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;

//use Joomla\CMS\Association\AssociationExtensionInterface;//...
// todo: optonally add associations and helpers; also make categories optional (only when used in 1 of the entities)

return new class implements ServiceProviderInterface {
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 */
	public function register(Container $container)
	{
		// Include the Composer autoloader
		//require_once __DIR__ . '/../vendor/autoload.php';

		$componentNamespace = 'Yepr\\Component\\Extengen';

		// Get Joomla services
		$container->registerServiceProvider(new CategoryFactory($componentNamespace));
		$container->registerServiceProvider(new MVCFactory($componentNamespace));
		$container->registerServiceProvider(new ComponentDispatcherFactory($componentNamespace));
		$container->registerServiceProvider(new RouterFactory($componentNamespace));

		$container->set(
			ComponentInterface::class,
			function (Container $container) {
				$component = new ExtengenComponent($container->get(ComponentDispatcherFactoryInterface::class));

				$component->setRegistry($container->get(Registry::class));
				$component->setMVCFactory($container->get(MVCFactoryInterface::class));
				$component->setCategoryFactory($container->get(CategoryFactoryInterface::class));
				$component->setRouterFactory($container->get(RouterFactoryInterface::class));
				//$component->setAssociationExtension($container->get(AssociationExtensionInterface::class));

				return $component;
			}
		);
	}
};
