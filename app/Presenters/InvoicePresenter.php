<?php

declare(strict_types=1);
namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use App\Model;

use DateTime;

use Tracy\Debugger;

class InvoicePresenter extends Nette\Application\UI\Presenter
{
    /* @var Model\DataLoader */
    private $dataLoader;

    public  function __construct(Model\DataLoader $dataLoader)
    {
        $this->dataLoader = $dataLoader;
    }

    public function startup()
    {
        parent::startup();
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect("Sign:in");
        } else {
            $this->dataLoader->setUser($this->getUser());
        }
    }

    public function renderShow()
    {
        /** @var Nette\Database\Context */
        $invoice_heads = $this->dataLoader->getInvoiceHead();

        $this->template->dataLoader = $this->dataLoader;
        $this->template->invoice_heads = $invoice_heads;
    }

    protected function createComponentAddInvoice(string $paidBy): Form
    {
        $form = new Form;
        $form->values->paidBy = $paidBy;

        $form->getElementPrototype()->setAttribute('autocomplete', 'off');

        switch ($this->getParameter("action")) {
            case "addCash":
                $paidBy = "cash";
                break;
            case "addCard":
                $paidBy = "card";
                break;
            case "addBank":
                $paidBy = "bank";
                break;
        }

        if ($paidBy === 'card') {
            $cards = $this->dataLoader->getUserAllCardsVS();
            $form->addRadioList('card', 'Platební karta:', $cards)
                ->setRequired('Doplňte variabilní kód platební karty.');

        } elseif ($paidBy === "bank") {
            $bankAccounts = $this->dataLoader->getUserAllBankAccountNumbers();
            $form->addRadioList('bank_account', 'Bankovní účet:', $bankAccounts)
                ->setRequired('Doplňte číslo bankovního účtu.');
        }

        $form->addText('date', 'Datum platby:')
            ->setRequired('Doplňte datum platby');

        $form->addText('description', 'Poznámka:');

        $form->addSubmit('send', 'Přidat doklad');

        $form->onSuccess[] = [$this, 'addInvoiceFormSucceeded'];
        return $form;
    }

    private function controlDate($date) {
        $dt = DateTime::createFromFormat("d.m.Y", $date);
        return $dt !== false && !array_sum($dt::getLastErrors());
    }

    public function addInvoiceFormSucceeded(Form $form, \stdClass $values): void
    {
        if (!$this->controlDate($values->date)) {
            $form->addError('Nesprávné datum.');
        } else {
            $this->flashMessage("Doklad byl úspěšně přidaný.");
            $this->redirect("this");
        }
    }
}