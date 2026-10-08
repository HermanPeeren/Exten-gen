<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component, site
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Site\Helper;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Whose project is it, and where does that person's output go: step 5.7.
 *
 * The frontend is for people who are not administrators - the SaaS case - so
 * every screen and task that takes a project id asks this first. Somebody
 * else's project is "not found", not "not allowed": a 403 says the id exists,
 * which is one more thing to probe.
 *
 * @since  1.3.0
 */
final class Ownership
{
    /**
     * Whether the user may open this project on the frontend.
     *
     * Their own, or any project when they may edit everybody's.
     *
     * @since  1.3.0
     */
    public static function mayOpen(int $projectId, User $user): bool
    {
        if ($projectId <= 0 || $user->guest) {
            return false;
        }

        if ($user->authorise('core.edit', 'com_extengen')) {
            return true;
        }

        $database = Factory::getContainer()->get(DatabaseInterface::class);
        $query    = $database->getQuery(true)
            ->select($database->quoteName('created_by'))
            ->from($database->quoteName('#__extengen_projects'))
            ->where($database->quoteName('id') . ' = :id')
            ->bind(':id', $projectId, ParameterType::INTEGER);

        $owner = $database->setQuery($query)->loadResult();

        return $owner !== null && (int) $owner === (int) $user->id && $user->authorise('core.edit.own', 'com_extengen');
    }

    /**
     * Refuse, as "not found", unless the user may open the project.
     *
     * @throws \RuntimeException  404.
     *
     * @since  1.3.0
     */
    public static function assertMayOpen(int $projectId, User $user): void
    {
        if (!self::mayOpen($projectId, $user)) {
            throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }
    }

    /**
     * Where one user's generated output goes.
     *
     * Not the administrator's `generated/<Component>/` folder, where two people
     * who both call their project Conference would overwrite each other. And
     * not a folder named after the user id either: everything under the site
     * root can be fetched by URL, so the name is a keyed hash of the id, which
     * nobody can work out for somebody else without the site's secret.
     *
     * @since  1.3.0
     */
    public static function outputRoot(User $user): string
    {
        $secret = (string) Factory::getApplication()->get('secret');

        return JPATH_ROOT . '/administrator/components/com_extengen/generated/site/'
            . substr(hash_hmac('sha256', 'user:' . (int) $user->id, $secret), 0, 32);
    }
}
