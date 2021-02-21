<?php

declare(strict_types=1);
namespace App\Presenters;

use Nette;
use App\Form\InvoiceForm;

use Tracy\Debugger;

class InvoicePresenter extends BasePresenter
{
    /** @var int */
    private const MAX_ITEM_COUNT = 5;

    protected function createComponentAddInvoiceForm(): InvoiceForm
    {
        $form = new InvoiceForm();

//        $form->getElementPrototype()->setAttribute('autocomplete', 'off');

        $form->addGroup('column0');

            $form->addText('price', 'Cena');

            $form ->addText('description', 'Název');

            $categories = $this->invoiceModel->getUserCategories();
            $form->addSelect('category', 'Kategorie:', $categories)
                    ->setPrompt('');

            $consumers = $this->invoiceModel->getUserConsumers();
            $form->addSelect('consumer', 'Spotřebitel:', $consumers)
                    ->setPrompt('');

        $form->addGroup('column1');

            $form->addText('date', 'Datum platby:');

            $paidByChoices = $this->invoiceModel->getPaidbyTypes();
            $paidBy = $form->addRadioList('paidBy', 'Typ platby', $paidByChoices);

            // paid by card
            $cards = $this->invoiceModel->getUserCards();
            $card = $form->addSelect('card', 'Platební karta:', $cards);

            // paid by bank
            $bank = $form->addText('varSymbol', 'Variabilní symbol:')->setMaxLength(10);

            $paidBy->addCondition($form::EQUAL, 'card')->toggle($card->getHtmlId())
                    ->elseCondition()->addCondition($form::EQUAL, 'bank')->toggle($bank->getHtmlId());

        $form->addGroup('buttons');

            $form->addSubmit('send', 'Uložit doklad');
            $form->addSubmit('removeItem', 'Odebrat položku')->setValidationScope([]);
            $form->addSubmit('addItem', 'Přidat položku')->setValidationScope([]);

        $form->addGroup('other');

            $form->addHidden('itemCount', 1);

        $form->onAnchor[] = [$this, 'invoiceFormAnchor'];
        $form->onSuccess[] = [$this, 'invoiceFormSuccess'];
        return $form;
    }

    public function invoiceFormAnchor(InvoiceForm $form): void
    {
        $form->createItems();
    }

    public function invoiceFormSuccess(InvoiceForm $form): void
    {
        $submittedBy = $form->isSubmitted()->name;

        switch ($submittedBy) {
            case 'submit':
                $this->formSubmitted($form);
                break;
            case 'add':
                $form->addItem(1);
                break;
            case 'remove':
                $form->removeItem();
        }
    }

    public function formSubmitted(InvoiceForm $form): void
    {

    }
}