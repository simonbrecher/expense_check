<?php

declare(strict_types=1);
namespace App\Presenters;

use Nette;
use App\Model;

use Tracy\Debugger;

class InvoicePresenter extends Nette\Application\UI\Presenter
{
    /* @var Model\DataLoader */
    private $dataLoader;

    public function startup()
    {
        parent::startup();
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect("Sign:in");
        } else {
            $this->dataLoader->setUser($this->getUser());
        }
    }

    public  function __construct(Model\DataLoader $dataLoader)
    {
        $this->dataLoader = $dataLoader;
    }

    public function renderShow()
    {
        /** @var Nette\Database\Context */
        $invoice_heads = $this->dataLoader->getInvoiceHead();

        $this->template->dataLoader = $this->dataLoader;
        $this->template->invoice_heads = $invoice_heads;
    }
}