<?php

namespace App\Controls;

use Nette\Forms\Controls\BaseControl;
use Nette\Utils\Html;

class MyHtml extends BaseControl
{
    /** @var string */
    private $html;

    public function __construct($html, $caption = null)
    {
        parent::__construct($caption);
        $this->html = $html;
    }

    /**
     * Generates control's HTML element.
     * @return Html
     */
    public function getControl() : Html
    {
        $html = Html::el('')->setHtml($this->html);
        return $html;
    }
}
