<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component, site
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Site\Dispatcher;

use Joomla\Application\WebApplicationInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcher;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The frontend's way in: step 5.7.
 *
 * Two things every request needs before any controller runs. A visitor who is
 * not logged in is sent to the login form and brought back, because every
 * screen here is about the visitor's own projects. And the administrator's
 * language file is loaded too: the frontend reuses the administrator's
 * layouts, so it uses the same strings, and one file is one place to keep them.
 *
 * @since  1.3.0
 */
class Dispatcher extends ComponentDispatcher
{
    /**
     * @return  void
     *
     * @since   1.3.0
     */
    public function dispatch()
    {
        if ($this->app->getIdentity()->guest && $this->app instanceof WebApplicationInterface) {
            $return = base64_encode(Uri::getInstance()->toString());

            $this->app->enqueueMessage(Text::_('COM_EXTENGEN_SITE_LOGIN_FIRST'), 'notice');
            $this->app->redirect(Route::_('index.php?option=com_users&view=login&return=' . $return, false));

            return;
        }

        parent::dispatch();
    }

    /**
     * The site's language file, then the administrator's, which holds the strings.
     *
     * @return  void
     *
     * @since   1.3.0
     */
    protected function loadLanguage()
    {
        parent::loadLanguage();

        $this->app->getLanguage()->load('com_extengen', JPATH_ROOT . '/administrator/components/com_extengen');
    }
}
