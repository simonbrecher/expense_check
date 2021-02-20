<?php

declare(strict_types=1);
namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;

use DateTime;

use Tracy\Debugger;

class InvoicePresenter extends BasePresenter
{
    /* @var int */
    private const MAX_ITEM_COUNT = 5;

    private function getAddInvoiceSection(): Nette\Http\SessionSection
    {
        $section = $this->session->getSection('addInvoice');

        if (!isset($section->itemCount)) {
            $section->itemCount = 1;
        }

        return $section;
    }

    public function renderAdd(): void
    {
        $section = $this->getAddInvoiceSection();

        $this->template->maxItemCount = self::MAX_ITEM_COUNT;
        $this->template->itemCount = $section->itemCount;
    }

    protected function createComponentAddInvoice(): Form
    {
        $form = new Form;

        $form->getElementPrototype()->setAttribute('autocomplete', 'off');

        $section = $this->getAddInvoiceSection();
        $form->addHidden('itemCount', $section->itemCount);

        $paidByChoices = ['cash' => 'V hotovosti', 'card' => 'Kartou', 'bank' => 'Bankovním převodem'];
        $form->addSelect('paidBy', 'Typ platby', $paidByChoices)
                ->addCondition($form::EQUAL, 'card')
                    ->toggle('card')
                ->elseCondition()
                ->addCondition($form::EQUAL, 'bank')
                    ->toggle('bank')
                ->elseCondition();

        // paid by card
        $cards = $this->dataLoader->getFormSelectDict('card', 'number', 'neuvedeno');
        $form->addSelect('card', 'Platební karta:', $cards)
                ->addRule($form::FILLED, 'Doplňte variabilní kód platební karty.');

        // paid by bank
        $form->addText('counterAccountNumber', 'Číslo protiúčtu:')->setMaxLength(17);
        $form->addText('counterAccountBankCode', 'Kód banky protiúčtu:')->setMaxLength(4);
        $form->addText('varSymbol', 'Variabilní symbol:')->setMaxLength(10);

        $date = $form->addText('date', 'Datum platby:');

        $form->addCheckbox('isToday', 'Zaplaceno dnes')
                ->addCondition($form::BLANK)
                    ->toggle('date');

        $date->addConditionOn($form['isToday'], $form::BLANK)
                ->addRule($form::FILLED, 'Datum musí být vyplněné.');

//        for ($i = 0; $i < self::MAX_ITEM_COUNT; $i++) {
        for ($i = 0; $i < self::MAX_ITEM_COUNT; $i++) {
            $container = $form->addContainer($i);

            $categories = $this->dataLoader->getFormSelectDict('category', 'name', 'Neuvedeno');
            $container->addSelect('category', 'Kategorie:', $categories);

            $consumers = $this->dataLoader->getFormSelectDict('consumer', 'name', 'Všichni');
            $container->addSelect('consumer', 'Spotřebitel:', $consumers);
            
            if ($i == 0) {
                $form->addText('totalPrice', 'Celková cena:')
                    ->addRule($form::FILLED, 'Doplňte celkovou cenu.');
            } else {
                $container->addText('price', 'Cena položky:')
                    ->addCondition($form::FILLED, 'Doplňte cenu '.($i + 1).' položky.');
            }

            $container->addText('description', $i == 0 ? 'Název:' : 'Název položky:')
                ->setMaxLength(35);
        }

        $form->addSubmit('removeItem', 'Odebrat položku')->setValidationScope([])
            ->onClick[] = [$this, 'changeItemCount'];
        $form->addSubmit('addItem', 'Přidat položku')->setValidationScope([])
            ->onClick[] = [$this, 'changeItemCount'];

        // TODO: remove fake setValidationScope
//        $form->addSubmit('send', 'Uložit doklad')->setValidationScope([$form['itemCount']]);
        $form->addSubmit('send', 'Uložit doklad');

        for ($i = 0; $i < self::MAX_ITEM_COUNT; $i++) {
            $form->getComponent('paidBy')
                ->addConditionOn($form['itemCount'], $form::MIN, $i + 1)
                    ->toggle('item'.$i);
        }

        $form->onSuccess[] = [$this, 'addInvoiceFormSucceeded'];
        $form->onValidate[] = [$this, 'validateAddInvoice'];
        return $form;
    }

    public function changeItemCount(Nette\Forms\Controls\Button $button, $data): void
    {
        $form = $button->getForm();
        $section = $this->getAddInvoiceSection();
        if ($button->getName() == 'addItem') {
            if ($section->itemCount < self::MAX_ITEM_COUNT) {
                $section->itemCount ++;
            }
        } else {
            if ($section->itemCount > 1) {
                $section->itemCount --;
            }
        }
        $form->getComponent('itemCount')->setValue($section->itemCount);
    }

    private function isDateExist($date): bool
    {
        $dt = DateTime::createFromFormat("d.m.Y", $date);
        return $dt !== false && !array_sum($dt::getLastErrors());
    }

    public function validateAddInvoice(Form $form): void
    {
        if ($form['send']->isSubmittedBy()) {
            $values = $form->getValues();

            Debugger::barDump($values);

//            $itemCount = (int) $values->itemCount;
//            if ($itemCount < 1 or $itemCount > self::MAX_ITEM_COUNT) {
//                $form->addError('Nesprávný počet položek.');
//            }

            switch ($values->paidBy) {
                case 'cash':

                    break;
                case 'card':

                    break;
                case 'bank':

                    break;
                default:
                    $form->addError('Nesprávný typ platby.');
            }

            if (!$values->isToday) {
                if (!$this->isDateExist($values->date)) {
                    $form->addError('Nesprávné datum.');
                }
            }
        }
    }

    public function addInvoiceFormSucceeded(Form $form, Nette\Utils\ArrayHash $values): void
    {
    }
}