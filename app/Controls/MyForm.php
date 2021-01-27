<?php

namespace App\Controls;

use Nette\Application\UI\Form;
use App\Controls\MyRadioList;

class MyForm extends Form
{
    /**
    * Adds set of radio button controls to the form.
    * @param  string|object  $label
    */
    public function addMyRadioList(string $name, $label = null, array $items = null): MyRadioList
    {
        return $this[$name] = new MyRadioList($label, $items);
    }
}