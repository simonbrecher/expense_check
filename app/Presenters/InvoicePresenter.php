<?php

declare(strict_types=1);
namespace App\Presenters;

use ErrorException;
use Nette;
use Nette\Application\UI\Form;

use DateTime;

use App\Model;

use Tracy\Debugger;

class InvoicePresenter extends Nette\Application\UI\Presenter
{
    /* @var Model\DataLoader */
    private $dataLoader;
    /* @var Model\DataSender */
    private $dataSender;

    public  function __construct(Model\DataLoader $dataLoader, Model\DataSender $dataSender)
    {
        parent::__construct();

        $this->dataLoader = $dataLoader;
        $this->dataSender = $dataSender;
        $this->dataSender->setDataLoader($this->dataLoader);
    }

    public function startup(): void
    {
        parent::startup();
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect("Sign:in");
        } else {
            $this->dataLoader->setUser($this->getUser());
            $this->dataSender->setUser($this->getUser());
        }
    }

    public function renderShow(): void
    {
        /** @var Nette\Database\Context */
        $invoice_heads = $this->dataLoader->userTable('invoice_head');

        $this->template->dataLoader = $this->dataLoader;
        $this->template->invoice_heads = $invoice_heads;
    }

    protected function createComponentAddInvoice(): Form
    {
        $form = new Form;

        $form->getElementPrototype()->setAttribute('autocomplete', 'off');

        $form->addHidden('maxItemCount', 4);
        $form->addHidden('itemCount', 1);

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

        for ($i = 0; $i < $form->values->maxItemCount; $i++) {
            $categories = $this->dataLoader->getFormSelectDict('category', 'name', 'Neuvedeno');

            $form->addSelect('category'.$i, 'Kategorie:', $categories);

            $consumers = $this->dataLoader->getFormSelectDict('consumer', 'name', 'Všichni');
            $form->addSelect('consumer'.$i, 'Spotřebitel:', $consumers);
            
            if ($i == 0) {
                $form->addText('totalPrice', 'Celková cena:')
                    ->addRule($form::FILLED, 'Doplňte celkovou cenu.');
            } else {
                $form->addText('price'.$i, 'Cena položky:')
                    ->addCondition($form::FILLED, 'Doplňte cenu '.($i + 1).' položky.');
            }

            $form->addText('description'.$i, $i == 0 ? 'Název:' : 'Název položky:')
                ->setMaxLength(50);
        }

        $form->addSubmit('removeItem', 'Odebrat položku')->setValidationScope([])
            ->onClick[] = [$this, 'changeItemCount'];
        $form->addSubmit('addItem', 'Přidat položku')->setValidationScope([])
            ->onClick[] = [$this, 'changeItemCount'];

        $form->addSubmit('send', 'Uložit doklad');

        for ($i = 0; $i < $form->values->maxItemCount; $i++) {
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
        if ($button->getName() == 'addItem') {
            if ($data->itemCount < $data->maxItemCount) {
                $form->getComponent('itemCount')->setValue($data->itemCount + 1);
            }
        } else {
            if ($data->itemCount > 1) {
                $form->getComponent('itemCount')->setValue($data->itemCount - 1);
            }
        }
    }

    public function validateAddInvoice(Form $form): void
    {
        $values = $form->getValues();
    }

//    private function isFormCorrect(Form $form, Nette\Utils\ArrayHash $values, int $itemCount): bool
//    {
//        if ($itemCount <= 0 or $itemCount > 100) {
//            throw new ErrorException('Wrong $itemcount.');
//        }
//
//        switch($values->paidby) {
//            case 'cash':
//                break;
//            case 'card':
//                if (!$this->dataLoader->canAccess('card', $values->card)) {
//                    throw new ErrorException('User can not access this card.');
//                }
//                break;
//            case 'bank':
//                if (($values->counter_account_number === '' or $values->counter_account_bank_code === '')
//                        and $values->var_symbol === '') {
//                    $form->addError('Doplňte čislo protiúčtu a bankovní kód protiúčtu účet nebo variabilní symbol.');
//                    return false;
//                } else {
//                    if (!$this->isCounterBankAccountCorrect($values->counter_account_number,
//                            $values->counter_account_bank_code)) {
//                        $form->addError('Doplňte správé číslo protiúčtu a bankovní kód protiúčtu.');
//                        return false;
//                    }
//                    if ($values->var_symbol !== '') {
//                        if (!$this->isVarSymbolCorrect($values->var_symbol)) {
//                            $form->addError('Variabilní symbol není ve správném formátu.');
//                            return false;
//                        }
//                    }
//                }
//                break;
//            default:
//                Debugger::barDump($this->getParameter("action"));
//                throw new ErrorException('Wrong type of payment.');
//        }
//
//        if (!$values->is_today and !$this->isDateExist($values->date)) {
//            $form->addError('Nesprávné datum.');
//            return false;
//        }
//
//        for ($i = 0; $i < $itemCount; $i++) {
//            $currentCategory = 'category'.$i;
//            $categoryId = $values->$currentCategory;
//            if ($categoryId != 0) {
//                if (!$this->dataLoader->canAccess('category', $categoryId)) {
//                    throw new ErrorException('User can not access this category.');
//                }
//            }
//            $currentConsumer = 'consumer'.$i;
//            $consumerId = $values->$currentConsumer;
//            if ($consumerId != 0) {
//                if (!$this->dataLoader->canAccess('consumer', $consumerId)) {
//                    throw new ErrorException('User can not access this consumer.');
//                }
//            }
//        }
//
//        if ($this->isPriceCorrect($values->total_price)) {
//            $values->offsetSet('total_price', intval($values->total_price));
//        } else {
//            $form->addError('Nesprávná cena.');
//            return false;
//        }
//        if ($itemCount > 1) {
//            for ($i = 1; $i < $itemCount; $i++) {
//                $correctPriceId = 'price'.$i;
//                if ($this->isPriceCorrect($values->$correctPriceId)) {
//                    $values->offsetSet($correctPriceId, intval($values->$correctPriceId));
//                } else {
//                    $form->addError('Nesprávná cena '.($i + 1).". položky.");
//                    return false;
//                }
//            }
//        }
//        if ($itemCount > 1) {
//            $priceSum = 0;
//            for ($i = 1; $i < $itemCount; $i++) {
//                $correctPriceId = 'price'.$i;
//                $priceSum += $values->$correctPriceId;
//            }
//            if ($priceSum >= $values->total_price) {
//                $form->addError("Celková cena musí být vyšší, než součet cen položek.");
//                return false;
//            }
//        }
//
//        return true;
//    }
//
//    private function countDataForAddInvoiceForm(Nette\Utils\ArrayHash $values): Nette\Utils\ArrayHash
//    {
//        $itemCount = intval($this->getParameters()['itemCount']);
//
//        if ($values->is_today) {
//            $now = date('Y-m-d', time());
//            $values->date = $now;
//        } else {
//            Debugger::barDump(strtotime($values->date));
//            Debugger::barDump($values->date);
//            $values->date = date('Y-m-d', strtotime($values->date));
//        }
//
//        for ($i = 0; $i < $itemCount; $i++) {
//            $correctCategoryId = 'category'.$i;
//            if ($values->$correctCategoryId == 0) {
//                $values->offsetSet($correctCategoryId, null);
//            }
//            $correctConsumerId = 'consumer'.$i;
//            if ($values->$correctConsumerId == 0) {
//                $values->offsetSet($correctConsumerId, null);
//            }
//        }
//
//        if ($itemCount > 1) {
//            $priceSum = 0;
//            for ($i = 1; $i < $itemCount; $i++) {
//                $correctPriceId = 'price'.$i;
//                $priceSum += $values->$correctPriceId;
//            }
//            $values->offsetSet('price0', $values->total_price - $priceSum);
//        } else {
//            $values->offsetSet('price0', $values->total_price);
//        }
//
//        return $values;
//    }

    public function addInvoiceFormSucceeded(Form $form, Nette\Utils\ArrayHash $values): void
    {
//        $itemCount = intval($this->getParameters()['itemCount']);

        Debugger::barDump('HERE addInvoiceFormSucceded');

        Debugger::barDump($values);

//        Debugger::barDump("TEST");
//        Debugger::barDump($form->getHttpData());

//        $values->offsetSet('paidby', $this->paidBy);
//        $values->offsetSet('item_count', $itemCount);

//        $isFormCorrect = $this->isFormCorrect($form, $values, $itemCount);
//
//        if ($isFormCorrect) {
//            $values = $this->countDataForAddInvoiceForm($values);
//
//            $wasDatabaseSuccessful = $this->dataSender->sendAddinvoiceForm($values);
//
//            if ($wasDatabaseSuccessful) {
//                $this->flashMessage("Doklad byl úspěšně přidaný.", "success");
//                $this->redirect("this");
//            } else {
//                $form->addError("Bohužel se nám nepodařilo doklad uložit do databáze.");
//            }
//        }
    }
}