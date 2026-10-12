<?php

/**
 * @package     Extengen

 * @subpackage  Extengen component
 * @version     0.8.0
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren, 2023. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Extengen\Administrator\View\ERD;

defined('_JEXEC') or die;


use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Yepr\Component\Extengen\Administrator\Diagram\EntityRelationshipUml;
use Yepr\Component\Extengen\Administrator\Helper\ExtengenHelper;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\MVC\View\GenericDataException;


/**
 * View class to make an Entity-Relationship-Diagram (ERD) from the datamodel with PlantUML.
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * Method to display the view.
	 *
	 * @param   string  $tpl  A template file to load. [optional]
	 *
	 * @return  void
     * @throws Genericdataexception
	 */
	public function display($tpl = null): void
	{
		/** @var \Yepr\Component\Extengen\Administrator\Model\ErdModel $model */
		$model = $this->getModel();

		// Get the project_id and put it in the model
		// Todo: this must be done in the (display)controller and probably best via UserState
		$projectId = Factory::getApplication()->getInput()->getInt('project_id');
		$model->setProjectId($projectId);

		// Get the AST from the model
		$project = $model->getAST();

		// todo: make the PlantUML-diagram in the model and only query that here

		// Open the test-uml-file for writing
		$umlfile = fopen('test.puml', "w") or die("Unable to open file!"); // todo: cache this file

		// Built elsewhere so it can be tested, and so a field's kind is asked
		// of FieldKind like everywhere else - this view used to ask it itself,
		// in the spelling 3.5 retired.
		$uml = EntityRelationshipUml::of($project);

		fwrite($umlfile, $uml);
		// Close the uml-file
		fclose($umlfile);

		// Get the picture from PlantUML
		$encode = $this->encodep($uml);
		$erd = "http://www.plantuml.com/plantuml/png/{$encode}";
		// todo: caching when datamodel is unchanged
		// echo $erd ."\n";
		echo "<h2>Entity Relationship Diagram for " . $project->name . "</h2>";
		echo '<p><img src="' . $erd . '" /></p>';

		//parent::display($tpl);
	}

// functions to call plantuml
	private function encodep($text)
    {
		$data = mb_convert_encoding($text, 'UTF-8');
		$compressed = gzdeflate($data, 9);
		return $this->encode64($compressed);
	}

	private function encode6bit($b)
    {
		if ($b < 10) {
			return chr(48 + $b);
		}
		$b -= 10;
		if ($b < 26) {
			return chr(65 + $b);
		}
		$b -= 26;
		if ($b < 26) {
			return chr(97 + $b);
		}
		$b -= 26;
		if ($b == 0) {
			return '-';
		}
		if ($b == 1) {
			return '_';
		}
		return '?';
	}

	private function append3bytes($b1, $b2, $b3)
    {
		$c1 = $b1 >> 2;
		$c2 = (($b1 & 0x3) << 4) | ($b2 >> 4);
		$c3 = (($b2 & 0xF) << 2) | ($b3 >> 6);
		$c4 = $b3 & 0x3F;
		$r = "";
		$r .= $this->encode6bit($c1 & 0x3F);
		$r .= $this->encode6bit($c2 & 0x3F);
		$r .= $this->encode6bit($c3 & 0x3F);
		$r .= $this->encode6bit($c4 & 0x3F);
		return $r;
	}

	private function encode64($c)
    {
		$str = "";
		$len = strlen($c);
		for ($i = 0; $i < $len; $i += 3) {
			if ($i + 2 == $len) {
				$str .= $this->append3bytes(ord(substr($c, $i, 1)), ord(substr($c, $i + 1, 1)), 0);
			} elseif ($i + 1 == $len) {
				$str .= $this->append3bytes(ord(substr($c, $i, 1)), 0, 0);
			} else {
				$str .= $this->append3bytes(
                    ord(substr($c, $i, 1)),
                    ord(substr($c, $i + 1, 1)),
                    ord(substr($c, $i + 2, 1))
                );
			}
		}
		return $str;
	}
}
