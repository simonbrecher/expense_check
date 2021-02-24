<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model;
use App\Form\InvoiceForm;

use Tracy\Debugger;

class InvoicePresenter extends BasePresenter
{
    /** @var int */
    private const MAX_ITEM_COUNT = 5;

    /** @var Model\InvoiceModel */
    public $invoiceModel;

    public  function __construct(Model\InvoiceModel $invoiceModel)
    {
        $this->invoiceModel = $invoiceModel;
    }

    public function startup(): void
    {
        parent::startup();

        $this->invoiceModel->setUser($this->getUser());
    }

    protected function createComponentAddInvoiceForm(): InvoiceForm
    {
        $form = new InvoiceForm($this->invoiceModel);

        $form->addGroup('column0');

            $form->addText('czk_total_price', 'Celková cena:')
                    ->addRule($form::NUMERIC, 'Celková cena musí být číslo.')
                    ->setRequired('Vyplňte prosím celkovou cenu.');

            $form ->addText('description', 'Název:')->setMaxLength(35);

            $categories = $this->invoiceModel->getUserCategories();
            $form->addSelect('category', 'Kategorie:', $categories)
                    ->setPrompt('');

            $consumers = $this->invoiceModel->getUserConsumers();
            $form->addSelect('consumer', 'Spotřebitel:', $consumers)
                    ->setPrompt('');

        $form->addGroup('column1');

            $form->addText('date', 'Datum platby:')
                    ->addRule($form::PATTERN, 'Formát data musí být 13.2 / 13.2.21 / 13.2.2021', $this->invoiceModel::DATE_PATTERN_FLEXIBLE)
                    ->setRequired('Vyplňte prosím datum vystavení dokladu.');

            $paidByChoices = $this->invoiceModel->getPaidbyTypes();
            $paidBy = $form->addRadioList('type_paidby', 'Typ platby', $paidByChoices)
                            ->setRequired('Vyberte prosím typ platby.');

            // paid by card
            $cards = $this->invoiceModel->getUserCards();
            $card = $form->addSelect('card_id', 'Platební karta:', $cards)
                            ->setPrompt('');

            // paid by bank
            $varSymbol = $form->addText('var_symbol', 'Variabilní symbol:')->setMaxLength(10);

            $paidBy->addCondition($form::EQUAL, 'PAIDBY_CARD')->toggle($form::TOGGLE_BOX_HTML_IDS['card_id'])
                    ->elseCondition()->addCondition($form::EQUAL, 'PAIDBY_BANK')->toggle($form::TOGGLE_BOX_HTML_IDS['var_symbol']);

            $varSymbol->addConditionOn($paidBy, $form::EQUAL, 'PAIDBY_BANK')
                        ->setRequired('Vyplňte prosím variabilní symbol.');

            $card->addConditionOn($paidBy, $form::EQUAL, 'PAIDBY_CARD')
                ->setRequired('Vyberte prosím platební kartu.');

        $form->addGroup('buttons');

            $form->addSubmit('submit', 'Uložit doklad');
            $form->addSubmit('add', 'Přidat položku')->setValidationScope([]);
            $form->addSubmit('remove', 'Odebrat položku')->setValidationScope([]);

        $form->onAnchor[] = [$this, 'invoiceFormAnchor'];
        $form->onSuccess[] = [$this, 'invoiceFormSuccess'];
        return $form;
    }

    public function invoiceFormAnchor(InvoiceForm $form): void
    {
        $submittedBy = $form->isSubmitted();

        if ($submittedBy) {
            switch ($submittedBy->name) {
                case 'add':
                    $form->addItem();
                    break;
                case 'remove':
                    $form->removeItem();
                    break;
                case 'submit':
                    $form->createItems();
            }
        } else {
            $form->createItems();
        }
    }

    public function invoiceFormSuccess(InvoiceForm $form): void
    {
        $submittedBy = $form->isSubmitted();

        if ($submittedBy->name == 'submit') {
            $values = $this->invoiceModel->constructAddInvoiceValues($form);

            if ($values) {
                $this->invoiceModel->addInvoice($values);
            }
        }
    }

    public function formSubmitted(InvoiceForm $form): void
    {

    }
}