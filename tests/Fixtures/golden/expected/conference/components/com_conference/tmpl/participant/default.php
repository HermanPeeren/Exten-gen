<?php
/**
 * @package    MyConference
 * @subpackage Conference
 * @version    1.0.0
 *
 * @copyright  Herman Peeren - Yepr - 2023
 * @license    GPL 3.0
 */

defined('_JEXEC') or die;

/** @var \Yepr\Component\Conference\Site\View\Participant\HtmlView $this */

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Session\Session;
use Joomla\Utilities\ArrayHelper;

// This page's stylesheet, from the page's custom code in the model. Loaded
// before the layout slot, so that a custom layout which returns early has it.
$this->getDocument()->getWebAssetManager()->registerAndUseStyle(
	'com_conference.participant',
	'com_conference/participant.css'
);

// <extengen id="site.details.layout">
// </extengen>

?>

<div class="item_fields">

    <table class="table">


    </table>

</div>
