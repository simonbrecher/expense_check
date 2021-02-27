<?php

declare(strict_types=1);
namespace App\Form;

use Nette\Application\UI\Form;

class BaseForm extends Form
{
    public function setDefaultValues(array $values): void
    {
        foreach ($values as $name => $value) {
            $this[$name]->setDefaultValue($value);
        }
    }
}