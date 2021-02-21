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

    /** @var Model\InvoiceModel */
    protected $invoiceModel;

    public  function __construct(Model\DataLoader $dataLoader, Model\DataSender $dataSender, Model\InvoiceModel $invoiceModel)
//    public  function __construct(Model\InvoiceModel $invoiceModel)
    {
        $this->dataLoader = $dataLoader;
        $this->dataSender = $dataSender;

        $this->dataSender->setDataLoader($this->dataLoader);

        $this->invoiceModel = $invoiceModel;
    }

    public function startup()
    {
        parent::startup();
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect("Sign:in");
        }

        $this->dataLoader->setUser($this->getUser());
        $this->dataSender->setUser($this->getUser());

        $this->invoiceModel->setUser($this->getUser());
    }

    public function beforeRender()
    {
//        parent::beforeRender();
        $this->template->cssNumber = '?'.rand(0, 1000);
    }
}
