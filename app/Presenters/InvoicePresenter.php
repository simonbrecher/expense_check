<?php

declare(strict_types=1);

namespace App\Presenters;


use App\Model;
use App\Form\InvoiceForm;

class InvoicePresenter extends BasePresenter
{
    public  function __construct(public Model\InvoiceModel $invoiceModel)
    {}

    public function actionDefault(): void
    {
        $this->redirect(':view');
    }

    public function actionAdd(int $id=null): void
    {
        if ($id !== null) {
            if (!$this->invoiceModel->canAccessInvoice($id)) {
                $this->redirect(':view');
            }
        }
    }

    public function renderAdd(int $id=null): void
    {
        if ($id === null) {
            $this->template->year = null;
            $this->template->month = null;
        } else {
            $date = $this->invoiceModel->getInvoiceDate($id);
            $this->template->year = $date->format('Y');
            $this->template->month = $date->format('n');
        }
    }

    protected function createComponentAddInvoiceForm(): InvoiceForm
    {
        $form = new InvoiceForm($this->invoiceModel);

        $editId = $this->getParameter('id');

        $form->addGroup('column0');

            $form->addText('czk_total_amount', 'Celková cena:')->addRule($form::NUMERIC, 'Celková cena musí být číslo.')->setRequired('Vyplňte celkovou cenu.');

            $form ->addText('description', 'Název:')->setMaxLength(35);

            $categories = $this->invoiceModel->getCategorySelect($editId);
            $form->addSelect('category', 'Kategorie:', $categories)->setPrompt('');

            $consumers = $this->invoiceModel->getConsumerSelect($editId);
            $form->addSelect('consumer', 'Spotřebitel:', $consumers)->setPrompt('');

        $form->addGroup('column1');

            $form->addText('d_issued', 'Datum platby:')
                    ->addRule($form::PATTERN, 'Formát data musí být 13.2 / 13.2.21 / 13.2.2021', $this->invoiceModel::DATE_PATTERN_FLEXIBLE)
                    ->setRequired('Vyplňte datum vystavení dokladu.');

            $paidByChoices = $this->invoiceModel::PAIDBY_TYPES_INVOICE_FORM;
            $paidBy = $form->addRadioList('type_paidby', 'Typ platby:', $paidByChoices)->setRequired('Vyberte typ platby.');

            // paid by card
            $cards = $this->invoiceModel->getCardSelect($editId);
            $card = $form->addSelect('card_id', 'Platební karta:', $cards)->setPrompt('');

            // paid by bank
            $varSymbol = $form->addText('var_symbol', 'Variabilní symbol:')->setMaxLength(10);

            $paidBy->addCondition($form::EQUAL, 'PAIDBY_CARD')->toggle($form::TOGGLE_BOX_HTML_IDS['card_id'])
                    ->elseCondition()->addCondition($form::EQUAL, 'PAIDBY_BANK')->toggle($form::TOGGLE_BOX_HTML_IDS['var_symbol'])
                    ->elseCondition()->addCondition($form::EQUAL, 'PAIDBY_ATM')->toggle('toggle-paidby-atm');

            $paidBy->addCondition($form::NOT_EQUAL, 'PAIDBY_ATM')->toggle('toggle-not-paidby-atm');
            $paidBy->addCondition($form::BLANK)->toggle('toggle-not-paidby-atm');

            $varSymbol->addConditionOn($paidBy, $form::EQUAL, 'PAIDBY_BANK')->setRequired('Vyplňte variabilní symbol.');

            $card->addConditionOn($paidBy, $form::EQUAL, 'PAIDBY_CARD')->setRequired('Vyberte platební kartu.');

        $form->addGroup('buttons');

            $form->addSubmit('submit', 'Uložit doklad');
            $form->addSubmit('add', 'Přidat položku')->setValidationScope([]);
            $form->addSubmit('remove', 'Odebrat položku')->setValidationScope([]);

            if ($this->getParameter('id') !== null) {
                $form->addSubmit('delete', 'Smazat')->setValidationScope([]) ->setHtmlAttribute('class', 'delete');
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
                $form->addItem($data['item_count'] - 1);
                unset($data['item_count']);
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
                } catch (\PDOException|Model\InvalidValueException|AccessUserException $exception) {
                    $this->flashMessage($exception->getMessage(), 'error');
                }

            } else {
                try {
                    $this->invoiceModel->editInvoice($form, (int) $editId);

                    $this->flashMessage('Doklad byl úspěšně upravený.', 'success');
                    $this->redirect(':view');
                } catch (\PDOException|Model\InvalidValueException|AccessUserException $exception) {
                    $this->flashMessage($exception->getMessage(), 'error');
                }
            }
        } elseif ($submittedBy->name == 'delete') {
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
    }

    public function handleRemove(int $id): void
    {
        $year = null;
        $month = null;

        try {
            $date = $this->invoiceModel->getInvoiceDate($id);
            $year = (int) $date->format('Y');
            $month = (int) $date->format('n');

            $this->invoiceModel->removeInvoice($id);
            $this->flashMessage('Doklad byl úspěšně smazaný.', 'success');
        } catch (\PDOException $exception) {
            $this->flashMessage($exception->getMessage(), 'error');
        }

        $this->redirect(':view', [$year, $month]);
    }

    public function handleNotRemove(int $id): void
    {
        $this->flashMessage('Doklad nebyl smazaný.', 'info');

        $date = $this->invoiceModel->getInvoiceDate($id);

        $this->redirect(':view', [(int) $date->format('Y'), (int) $date->format('n')]);
    }

    public function renderRemove(int $id): void
    {
        $this->template->id = $id;
    }

    public function actionView(int $year=null, int $month=null): void
    {
        $startInterval = $this->invoiceModel->getStartInterval();
        $endInterval = $this->invoiceModel->getEndInterval();

        $startYear = (int) $startInterval->format('Y');
        $startMonth = (int) $startInterval->format('n');
        $endYear = (int) $endInterval->format('Y');
        $endMonth = (int) $endInterval->format('n');

        if ($year === null || $month === null) {
            $this->redirect(':view', [$endYear, $endMonth]);
        } elseif ($year < $startYear || ($year == $startYear && $month < $startMonth)) {
            $this->redirect(':view', [$startYear, $startMonth]);
        } elseif ($year > $endYear || ($year == $endYear && $month > $endMonth)) {
            $this->redirect(':view', [$endYear, $endMonth]);
        }
    }

    public function renderView(int $year=null, int $month=null): void
    {
        $startInterval = $this->invoiceModel->getStartInterval();
        $endInterval = $this->invoiceModel->getEndInterval();

        $this->template->startYear = (int) $startInterval->format('Y');
        $this->template->startMonth = (int) $startInterval->format('n');
        $this->template->endYear = (int) $endInterval->format('Y');
        $this->template->endMonth = (int) $endInterval->format('n');
        $this->template->renderYear = $year;
        $this->template->renderMonth = $month;

        $startInterval = $this->invoiceModel->getFirstDayInMonth($month, $year);
        $endInterval = $this->invoiceModel->getLastDayInMonth($month, $year);

        $this->template->invoices = $this->invoiceModel->getInvoices()->where('d_issued >=', $startInterval)->where('d_issued <=', $endInterval);
        $this->template->invoiceModel = $this->invoiceModel;
    }
}