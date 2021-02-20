<?php

namespace App\Presenters;

use Nette;
use App\Model;
use Tracy\Debugger;

class BasePresenter extends Nette\Application\UI\Presenter
{
    /* @var Model\DataLoader */
    protected $dataLoader;
    /* @var Model\DataSender */
    protected $dataSender;

    public  function __construct(Model\DataLoader $dataLoader, Model\DataSender $dataSender)
    {
        $this->dataLoader = $dataLoader;
        $this->dataSender = $dataSender;
        $this->dataSender->setDataLoader($this->dataLoader);
    }

    public function startup()
    {
        parent::startup();
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect("Sign:in");
        }

        $this->dataLoader->setUser($this->getUser());
        Debugger::barDump('HERE1');
//        Debugger::barDump($this->dataLoader->user);
        Debugger::barDump('HERE');
        $this->dataSender->setUser($this->getUser());
    }

    public function beforeRender()
    {
//        parent::beforeRender();
        $this->template->cssNumber = '?'.rand(0, 1000);
    }
}
