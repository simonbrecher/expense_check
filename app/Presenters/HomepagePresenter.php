<?php


namespace App\Presenters;

use Nette;

class HomepagePresenter extends Nette\Application\UI\Presenter
{
    public function startup()
    {
        parent::startup();
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect("Sign:in");
        }
    }
}