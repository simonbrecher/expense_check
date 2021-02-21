<?php


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

            $form->addText('czk_price', 'Cena:');

            $form ->addText('description', 'Název:');

            $categories = $this->invoiceModel->getUserCategories();
            $form->addSelect('category', 'Kategorie:', $categories)
                    ->setPrompt('');

            $consumers = $this->invoiceModel->getUserConsumers();
            $form->addSelect('consumer', 'Spotřebitel:', $consumers)
                    ->setPrompt('');

        $form->addGroup('column1');

            $form->addText('date', 'Datum platby:');

            $paidByChoices = $this->invoiceModel->getPaidbyTypes();
            $paidBy = $form->addRadioList('type_paidby', 'Typ platby', $paidByChoices);

            // paid by card
            $cards = $this->invoiceModel->getUserCards();
            $card = $form->addSelect('card_id', 'Platební karta:', $cards)
                            ->setPrompt('');

            // paid by bank
            $bank = $form->addText('var_symbol', 'Variabilní symbol:')->setMaxLength(10);

            $paidBy->addCondition($form::EQUAL, 'card')->toggle($form::TOGGLE_BOX_HTML_IDS['card_id'])
                    ->elseCondition()->addCondition($form::EQUAL, 'bank')->toggle($form::TOGGLE_BOX_HTML_IDS['var_symbol']);

        $form->addGroup('buttons');

            $form->addSubmit('send', 'Uložit doklad');
            $form->addSubmit('remove', 'Odebrat položku')->setValidationScope([]);
            $form->addSubmit('add', 'Přidat položku')->setValidationScope([]);

        $form->onAnchor[] = [$this, 'invoiceFormAnchor'];
        $form->onSuccess[] = [$this, 'invoiceFormSuccess'];
        return $form;
    }

    public function invoiceFormAnchor(InvoiceForm $form): void
    {
        Debugger::barDump('invoiceFormAnchor');
        $form->createItems();
    }

    public function invoiceFormSuccess(InvoiceForm $form): void
    {
        $submittedBy = $form->isSubmitted()->name;
        Debugger::barDump('invoiceFormSuccess '.$submittedBy);

        switch ($submittedBy) {
            case 'send':
                $this->formSubmitted($form);
                break;
            case 'add':
                $form->addItem();
                break;
            case 'remove':
                $form->removeItem();
        }
    }

    public function formSubmitted(InvoiceForm $form): void
    {

    }
}