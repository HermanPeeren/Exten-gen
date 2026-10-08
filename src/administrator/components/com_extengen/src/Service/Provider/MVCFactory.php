<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Service\Provider;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Yepr\Component\Extengen\Administrator\Service\MVCFactory as ExtengenMVCFactory;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Registers this component's MVC factory in place of Joomla's.
 *
 * The same wiring as `Joomla\CMS\Extension\Service\Provider\MVCFactory`, so the
 * models get everything they got before, with one difference: the factory is
 * this component's, and it is given the container its own services are in.
 * There is no API client branch, because this component has no API.
 *
 * @since  1.4.0
 */
final class MVCFactory implements ServiceProviderInterface
{
    /**
     * @param  string  $namespace  The component's namespace.
     *
     * @since  1.4.0
     */
    public function __construct(private readonly string $namespace)
    {
    }

    /**
     * @since  1.4.0
     */
    public function register(Container $container): void
    {
        $container->set(
            MVCFactoryInterface::class,
            function (Container $container) {
                $factory = new ExtengenMVCFactory($this->namespace, $container);

                $factory->setFormFactory($container->get(FormFactoryInterface::class));
                $factory->setDispatcher($container->get(DispatcherInterface::class));
                $factory->setDatabase($container->get(DatabaseInterface::class));
                $factory->setSiteRouter($container->get(SiteRouter::class));
                $factory->setCacheControllerFactory($container->get(CacheControllerFactoryInterface::class));
                $factory->setUserFactory($container->get(UserFactoryInterface::class));
                $factory->setMailerFactory($container->get(MailerFactoryInterface::class));

                return $factory;
            }
        );
    }
}
