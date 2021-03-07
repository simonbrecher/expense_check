<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model;
use App\Form\InvoiceForm;
use Tracy\Debugger;

class InvoicePresenter extends BasePresenter
{
    private const MAX_ITEM_COUNT = 5;

    public  function __construct(public Model\InvoiceModel $invoiceModel)
    {}

    public function actionAdd(int $id=null): void
    {
        if ($id !== null) {
            if (!$this->invoiceModel->canAccessInvoice($id)) {
                $this->redirect(':view');
            }
        }
    }

    protected function createComponentAddInvoiceForm(): InvoiceForm
    {
        $form = new InvoiceForm($this->invoiceModel);

        $editId = $this->getParameter('id');

        $form->addGroup('column0');

            $form->addText('czk_total_amount', 'Celková cena:')
                    ->addRule($form::NUMERIC, 'Celková cena musí být číslo.')
                    ->setRequired('Vyplňte celkovou cenu.');

            $form ->addText('description', 'Název:')->setMaxLength(35);

            $categories = $this->invoiceModel->getUserCategories($editId);
            $form->addSelect('category', 'Kategorie:', $categories)
                    ->setRequired('Vyplňte kategorii první položky.')
                    ->setPrompt('');

            $consumers = $this->invoiceModel->getUserConsumers($editId);
            Debugger::barDump($consumers);
            $form->addSelect('consumer', 'Spotřebitel:', $consumers)
                    ->setPrompt('');

        $form->addGroup('column1');

            $form->addText('d_issued', 'Datum platby:')
                    ->addRule($form::PATTERN, 'Formát data musí být 13.2 / 13.2.21 / 13.2.2021', $this->invoiceModel::DATE_PATTERN_FLEXIBLE)
                    ->setRequired('Vyplňte datum vystavení dokladu.');

            $paidByChoices = $this->invoiceModel->getPaidbyTypes();
            $paidBy = $form->addRadioList('type_paidby', 'Typ platby', $paidByChoices)
                            ->setRequired('Vyberte typ platby.');

            // paid by card
            $cards = $this->invoiceModel->getUserCards();
            $card = $form->addSelect('card_id', 'Platební karta:', $cards)
                            ->setPrompt('');

            // paid by bank
            $varSymbol = $form->addText('var_symbol', 'Variabilní symbol:')->setMaxLength(10);

            $paidBy->addCondition($form::EQUAL, 'PAIDBY_CARD')->toggle($form::TOGGLE_BOX_HTML_IDS['card_id'])
                    ->elseCondition()->addCondition($form::EQUAL, 'PAIDBY_BANK')->toggle($form::TOGGLE_BOX_HTML_IDS['var_symbol']);

            $varSymbol->addConditionOn($paidBy, $form::EQUAL, 'PAIDBY_BANK')
                        ->setRequired('Vyplňte variabilní symbol.');

            $card->addConditionOn($paidBy, $form::EQUAL, 'PAIDBY_CARD')
                ->setRequired('Vyberte platební kartu.');

        $form->addGroup('buttons');

            $form->addSubmit('submit', 'Uložit doklad');
            $form->addSubmit('add', 'Přidat položku')->setValidationScope([]);
            $form->addSubmit('remove', 'Odebrat položku')->setValidationScope([]);

            if ($this->getParameter('id') !== null) {
                $form->addSubmit('delete', 'Smazat')->setValidationScope([])
                        ->setHtmlAttribute('class', 'delete');
            }

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

            $editId = $this->getParameters()['id'];
            if ($editId !== null) {
                $data = $this->invoiceModel->getEditInvoiceData((int) $editId);
                Debugger::barDump($data);
                $form->addItem($data['item_count'] - 1);
                $form->setDefaults($data);
            }
        }
    }

    public function invoiceFormSuccess(InvoiceForm $form): void
    {
        $submittedBy = $form->isSubmitted();

        if ($submittedBy->name == 'submit') {
            $editId = $this->getParameter('id');
            if ($editId === null) {
                try {
                    $this->invoiceModel->addInvoice($form);

                    $this->flashMessage('Doklad byl úspěšně uložený.', 'success');
                    $this->redirect('this');
                } catch (\PDOException $exception) {
                    $this->flashMessage($exception->getMessage(), 'error');
                }

            } else {
                try {
                    $this->invoiceModel->editInvoice($form, (int) $editId);

                    $this->flashMessage('Doklad byl úspěšně upravený.', 'success');
                    $this->redirect(':view');
                } catch (\PDOException $exception) {
                    $this->flashMessage($exception->getMessage(), 'error');
                }
            }
        } elseif ($submittedBy->name == 'delete') {
            Debugger::barDump('HERE');
            $removeId = $this->getParameter('id');
            if ($removeId !== null) {
                $this->redirect(':remove', $removeId);
            }
        }
    }

    public function actionRemove(int $id): void
    {
        if (!$this->invoiceModel->canAccessInvoice($id)) {
            $this->redirect(':default');
        }

        # TODO: something if there are payments for the invoice_head
    }

    public function handleRemove(int $id): void
    {
        try {
            $this->invoiceModel->removeInvoice($id);
            $this->flashMessage('Doklad byl úspěšně smazaný.', 'success');
        } catch (\PDOException $exception) {
            $this->flashMessage($exception->getMessage(), 'error');
        }
        $this->redirect(':view');
    }

    public function handleNotRemove(): void
    {
        $this->flashMessage('Doklad nebyl smazaný.', 'info');
        $this->redirect(':view');
    }

    public function renderRemove(int $id): void
    {
        $this->template->id = $id;
    }

    public function renderView(): void
    {
        $this->template->invoices = $this->invoiceModel->getInvoicesForView();
    }
}