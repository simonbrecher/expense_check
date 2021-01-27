<?php

namespace App\Controls;

use Nette\Forms\Controls\RadioList;
use Nette\Utils\Html;

use Tracy\Debugger;

class MyRadioList extends RadioList
{
    /**
     * Generates control's HTML element.
     * @return Html
     */
     public function getControl() : Html
     {
         $input = parent::getControl();
         Debugger::barDump($input);
         $children = $input->getChildren();

         $children = str_replace("<br>", "", $children);

         Debugger::barDump($children);

         $input->removeChildren();
         foreach ($children as $child) {
             $input->addHtml($child);
         }

         return $input;
     }
}