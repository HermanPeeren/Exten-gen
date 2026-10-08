<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component, site
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Site\Controller;

use Yepr\Component\Extengen\Administrator\Controller\GenerateController as AdministratorGenerateController;
use Yepr\Component\Extengen\Administrator\Model\GenerateModel;
use Yepr\Component\Extengen\Site\Helper\Ownership;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Downloading a generated package from the frontend: step 5.7.
 *
 * The administrator's task, with the two things that differ here. Whether
 * somebody may have the package is whether the project is theirs, rather than
 * whether they may manage the component. And the package is looked for in that
 * person's own output folder, which is where the frontend generated it.
 *
 * `install` is inherited and refuses on the site before it checks anything
 * else, whatever the component's options say.
 *
 * @since  1.3.0
 */
class GenerateController extends AdministratorGenerateController
{
    /**
     * @since  1.3.0
     */
    protected function assertMayGenerate(): void
    {
        Ownership::assertMayOpen($this->app->getInput()->getInt('project_id'), $this->app->getIdentity());
    }

    /**
     * @since  1.3.0
     */
    protected function generateModel(): GenerateModel
    {
        $model = parent::generateModel();

        $model->setOutputRoot(Ownership::outputRoot($this->app->getIdentity()));

        return $model;
    }
}
