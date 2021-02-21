<?php


namespace App\Presenters;

use Nette;

class BasePresenter extends Nette\Application\UI\Presenter
{
    public function startup(): void
    {
        parent::startup();
        if (!$this->getUser()->isLoggedIn() and $this->getName() != 'SignPresenter') {
            $this->redirect("Sign:in");
        }
    }

    public function beforeRender(): void
    {
        $this->template->cssNumber = '?'.rand(0, 1000);
    }
}
