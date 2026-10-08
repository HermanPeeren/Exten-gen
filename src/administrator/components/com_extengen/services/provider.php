<?php
/**
 * @package     Extengen

 * @subpackage  Extengen component
 * @version     0.8.0
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren, 2023. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Association\AssociationExtensionInterface;
use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\ServiceProviderInterface;
use Joomla\CMS\Component\Router\RouterFactoryInterface;

use Joomla\CMS\Extension\Service\Provider\CategoryFactory;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\HTML\Registry;
use Yepr\Component\Extengen\Administrator\Extension\ExtengenComponent;
use Yepr\Component\Extengen\Administrator\Service\Provider\Catalogues;
use Yepr\Component\Extengen\Administrator\Service\Provider\MVCFactory;
use Yepr\Component\Extengen\Administrator\Service\Provider\Repositories;
use Yepr\Gen\Joomla\Metalanguage\MetalanguageCatalogue;
//use Yepr\Component\Extengen\Administrator\Helper\AssociationsHelper;
use Joomla\DI\Container;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;

/**
 * The Extengen service provider: the composition root.
 *
 * Every service this component has is registered here or by a provider named
 * here, and nothing else in the component constructs one. Since 1.3.2 that
 * includes its own: the catalogues of generators and metalanguages and the
 * importer (`Catalogues`) and the project repository (`Repositories`), which
 * this component's MVC factory hands to models
 * and controllers the way Joomla's hands over the database (`MVCFactory`,
 * this component's, in place of Joomla's).
 */
return new class implements ServiceProviderInterface
{
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 */
	public function register(Container $container)
	{
		//$container->set(AssociationExtensionInterface::class, new AssociationsHelper());

		$container->registerServiceProvider(new CategoryFactory('\\Yepr\\Component\\Extengen'));
		$container->registerServiceProvider(new Catalogues());
		$container->registerServiceProvider(new Repositories());
		$container->registerServiceProvider(new MVCFactory('\\Yepr\\Component\\Extengen'));
		$container->registerServiceProvider(new ComponentDispatcherFactory('\\Yepr\\Component\\Extengen'));
        $container->registerServiceProvider(new RouterFactory('\\Yepr\\Component\\Extengen'));

		$container->set(
			ComponentInterface::class,
			function (Container $container)
			{
				$component = new ExtengenComponent($container->get(ComponentDispatcherFactoryInterface::class));

				$component->setRegistry($container->get(Registry::class));
				$component->setMVCFactory($container->get(MVCFactoryInterface::class));
				$component->setCategoryFactory($container->get(CategoryFactoryInterface::class));
				//$component->setAssociationExtension($container->get(AssociationExtensionInterface::class));
                $component->setRouterFactory($container->get(RouterFactoryInterface::class));

				// For what Joomla makes with `new` and so cannot inject: the
				// metalanguage form field asks the booted component.
				$component->setMetalanguageCatalogue($container->get(MetalanguageCatalogue::class));

				return $component;
			}
		);
	}
};
