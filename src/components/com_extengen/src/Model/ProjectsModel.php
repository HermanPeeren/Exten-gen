<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component, site
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Site\Model;

use Joomla\Database\ParameterType;
use Yepr\Component\Extengen\Administrator\Model\ProjectsModel as AdministratorProjectsModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The visitor's own projects: step 5.7.
 *
 * The administrator's list query, narrowed to the projects this person made.
 * Somebody who may edit every project sees every project, as in the
 * administrator.
 *
 * @since  1.3.0
 */
class ProjectsModel extends AdministratorProjectsModel
{
    /**
     * @return  \Joomla\Database\QueryInterface
     *
     * @since   1.3.0
     */
    protected function getListQuery()
    {
        $query = parent::getListQuery();
        $user  = $this->getCurrentUser();

        if (!$user->authorise('core.edit', 'com_extengen')) {
            $owner = (int) $user->id;

            $query->where($this->getDatabase()->quoteName('a.created_by') . ' = :owner')
                ->bind(':owner', $owner, ParameterType::INTEGER);
        }

        return $query;
    }
}
