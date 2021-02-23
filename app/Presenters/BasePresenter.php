<?php

declare(strict_types=1);
namespace App\Presenters;

use Nette;
use Tracy\Debugger;

class BasePresenter extends Nette\Application\UI\Presenter
{
    public function startup(): void
    {
        parent::startup();
        if (!$this->getUser()->isLoggedIn() and $this->getName() != 'Sign') {
            $this->redirect("Sign:in");
        }
    }

    public function beforeRender(): void
    {
        $this->template->cssNumber = '?'.rand(0, 1000);
    }
}
