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

use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Yepr\Component\Extengen\Administrator\Model\ProjectModel as AdministratorProjectModel;
use Yepr\Component\Extengen\Site\Helper\Ownership;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One project, edited on the frontend: step 5.7.
 *
 * The administrator's model, so there is one place where a language's forms
 * are merged in and one place where a project is saved - 4.5 found what two
 * copies of that would have cost. What differs is said here:
 *
 * - the forms and the table are the administrator's, so they are asked for there;
 * - a project that is not the visitor's is "not found";
 * - the half of a project that is a Joomla item - published, category,
 *   access, ordering, language, display options - is not the visitor's to set,
 *   so those fields are taken out of the form. Taken out rather than hidden:
 *   `Form::filter()` drops what the form does not declare, so a post that
 *   names them anyway changes nothing.
 *
 * @since  1.3.0
 */
class ProjectModel extends AdministratorProjectModel
{
    /**
     * The fields of `project_chrome.xml` a frontend user does not set.
     *
     * @var    string[]
     * @since  1.3.0
     */
    private const NOT_ON_THE_SITE = ['published', 'catid', 'access', 'ordering', 'language', 'alias'];

    /**
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data.
     *
     * @return  Form|false
     *
     * @since   1.3.0
     */
    public function getForm($data = [], $loadData = true)
    {
        Form::addFormPath(JPATH_ROOT . '/administrator/components/com_extengen/forms');

        $form = parent::getForm($data, $loadData);

        if ($form instanceof Form) {
            foreach (self::NOT_ON_THE_SITE as $field) {
                $form->removeField($field);
            }

            $form->removeGroup('params');
        }

        return $form;
    }

    /**
     * The administrator's table: there is one table, and it lives there.
     *
     * @param   string  $name     The table name.
     * @param   string  $prefix   Ignored: always the administrator's.
     * @param   array   $options  Configuration array for the table.
     *
     * @return  \Joomla\CMS\Table\Table
     *
     * @since   1.3.0
     */
    public function getTable($name = 'Project', $prefix = 'Administrator', $options = [])
    {
        return parent::getTable($name, 'Administrator', $options);
    }

    /**
     * The project, when it is the visitor's to open.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  mixed
     *
     * @throws  \RuntimeException  404, for somebody else's project.
     *
     * @since   1.3.0
     */
    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if (\is_object($item) && (int) ($item->id ?? 0) > 0 && !Ownership::mayOpen((int) $item->id, $this->getCurrentUser())) {
            throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }

        return $item;
    }
}
