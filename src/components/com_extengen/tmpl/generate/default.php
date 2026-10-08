<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component, site
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * The administrator's layout, included rather than copied: step 5.7. The site
 * view has already taken out what the frontend does not show.
 *
 * @var \Yepr\Component\Extengen\Site\View\Generate\HtmlView $this
 */
require JPATH_ADMINISTRATOR . '/components/com_extengen/tmpl/generate/default.php';
