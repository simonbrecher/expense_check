<?php

namespace App\Controls;

use Nette;
use Nette\Application\UI\Form;

class MyForm extends Form
{
    /** @var string */
    private $myHtmlName;

    public function __construct(Nette\ComponentModel\IContainer $parent = null, string $name = null)
    {
        parent::__construct($parent, $name);
        $this->myHtmlName = "";
    }

    /**
    * Adds set of radio button controls to the form.
    * @param  string|object  $label
    */
    public function addMyRadioList(string $name, $label = null, array $items = null): MyRadioList
    {
        return $this[$name] = new MyRadioList($label, $items);
    }

    /**
     * Adds html code.
     */
    public function addMyHtml(string $html): MyHtml
    {
        // unfortunate
        $this->myHtmlName = $this->myHtmlName."_";
        return $this[$this->myHtmlName] = new MyHtml($html, $this->myHtmlName);
    }
}