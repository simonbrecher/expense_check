<?php

declare(strict_types=1);
namespace App\Presenters;

use ErrorException;
use Nette;
use Nette\Application\UI\Form;

use DateTime;

use App\Model;
use App\Controls\MyForm;

use Tracy\Debugger;

class InvoicePresenter extends Nette\Application\UI\Presenter
{
    /* @var Model\DataLoader */
    private $dataLoader;
    /* @var Model\DataSender */
    private $dataSender;

    private $paidBy;

    // TODO: Make javascript infused form.

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

    public function renderAdd(int $itemCount): void
    {
        $this->template->itemCount = intval($this->getParameters()['itemCount']);
    }

    public function renderAddCash(int $itemCount): void
    {
        $this->template->itemCount = intval($this->getParameters()['itemCount']);
    }

    public function renderAddCard(int $itemCount): void
    {
        $this->template->itemCount = intval($this->getParameters()['itemCount']);
    }

    public function renderAddBank(int $itemCount): void
    {
        $this->template->itemCount = intval($this->getParameters()['itemCount']);
    }

    public function renderShow(): void
    {
        /** @var Nette\Database\Context */
        $invoice_heads = $this->dataLoader->userTable('invoice_head');

        $this->template->dataLoader = $this->dataLoader;
        $this->template->invoice_heads = $invoice_heads;
    }

    protected function createComponentAddInvoice(): MyForm
    {
        $form = new MyForm;

        $itemCount = intval($this->getParameters()['itemCount']);

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
            default:
                throw new ErrorException('Wrong paidBy: '.$this->getParameter("action"));
        }

        $this->paidBy = $paidBy;

        if ($paidBy === 'card') {
            $cards = $this->dataLoader->getFormSelectDict('card', 'number', 'neuvedeno');
            $form->addSelect('card', 'Platební karta:', $cards)
                ->setRequired('Doplňte variabilní kód platební karty.');

        } elseif ($paidBy === "bank") {
            $form->addText('counter_account_number', 'Číslo protiúčtu:')
                ->setMaxLength(17);
            $form->addText('counter_account_bank_code', 'Kód banky protiúčtu:')
                ->setMaxLength(4);
            $form->addText('var_symbol', 'Variabilní symbol:')
                ->setMaxLength(10);
        }

        $form->addText('date', 'Datum platby:');
        $form->addCheckbox('today', 'Zaplaceno dnes');

        for ($i = 0; $i < $itemCount; $i++) {
            if ($i != 0) {
                $form->addMyHtml("");
                $form->addMyHtml("");
            }

            $categories = $this->dataLoader->getFormSelectDict('category', 'name', 'Neuvedeno');

            $form->addSelect('category'.$i, 'Kategorie:', $categories)
                ->setRequired('Doplňte kategorii položky.');

            $consumers = $this->dataLoader->getFormSelectDict('consumer', 'name', 'Všichni');
            $form->addSelect('consumer'.$i, 'Spotřebitel:', $consumers)
                ->setRequired('Doplňte spotřebitele položky.')
                ->setDefaultValue(0);

            if ($i == 0) {
                $form->addText('total_price', 'Celková cena:')
                    ->setRequired('Doplňte celkovou cenu.');
            } else {
                $form->addText('price'.$i, 'Cena položky:')
                    ->setRequired('Doplňte cenu '.($i + 1).' položky.');
            }

            $form->addTextArea('description'.$i, $itemCount == 1 ? 'Název:' : 'Název položky:')
                ->setMaxLength(50);
        }

        $form->addSubmit('send', 'Přidat doklad');

        $form->onSuccess[] = [$this, 'addInvoiceFormSucceeded'];
        return $form;
    }

    private function isDateExist($date): bool
    {
        $dt = DateTime::createFromFormat("d.m.Y", $date);
        return $dt !== false && !array_sum($dt::getLastErrors());
    }

    private function isVarSymbolCorrect(string $string): bool
    {
        if (strlen($string) == 0 or strlen($string) > 10) {
            return false;
        }
        $digits = '0123456789';
        foreach (str_split($string) as $digit) {
            if (strpos($digits, $digit) === false) {
                return false;
            }
        }
        return true;
    }

    private function isPriceCorrect(string $string): bool
    {
        if (strlen($string) == 0 or strlen($string) > 11) {
            return false;
        }
        return floatval($string) > 0;
    }

    // to be finished
    private function isCounterBankAccountCorrect(string $bankAccountNumber, string $bankCode): bool
    {
        return $bankAccountNumber !== 'a' and $bankCode !== 'a';
    }

    private function isFormCorrect(Form $form, Nette\Utils\ArrayHash $values, int $itemCount): bool
    {
        if ($itemCount <= 0 or $itemCount > 100) {
            throw new ErrorException('Wrong $itemcount.');
        }

        switch($values->paidby) {
            case 'cash':
                break;
            case 'card':
                if (!$this->dataLoader->canAccess('card', $values->card)) {
                    throw new ErrorException('User can not access this card.');
                }
                break;
            case 'bank':
                if (($values->counter_account_number === '' or $values->counter_account_bank_code === '')
                        and $values->var_symbol === '') {
                    $form->addError('Doplňte čislo protiúčtu a bankovní kód protiúčtu účet nebo variabilní symbol.');
                    return false;
                } else {
                    if (!$this->isCounterBankAccountCorrect($values->counter_account_number,
                            $values->counter_account_bank_code)) {
                        $form->addError('Doplňte správé číslo protiúčtu a bankovní kód protiúčtu.');
                        return false;
                    }
                    if ($values->var_symbol !== '') {
                        if (!$this->isVarSymbolCorrect($values->var_symbol)) {
                            $form->addError('Variabilní symbol není ve správném formátu.');
                            return false;
                        }
                    }
                }
                break;
            default:
                Debugger::barDump($this->getParameter("action"));
                throw new ErrorException('Wrong type of payment.');
        }

        if (!$values->today and !$this->isDateExist($values->date)) {
            $form->addError('Nesprávné datum.');
            return false;
        }

        for ($i = 0; $i < $itemCount; $i++) {
            $currentCategory = 'category'.$i;
            $categoryId = $values->$currentCategory;
            if ($categoryId != 0) {
                if (!$this->dataLoader->canAccess('category', $categoryId)) {
                    throw new ErrorException('User can not access this category.');
                }
            }
            $currentConsumer = 'consumer'.$i;
            $consumerId = $values->$currentConsumer;
            if ($consumerId != 0) {
                if (!$this->dataLoader->canAccess('consumer', $consumerId)) {
                    throw new ErrorException('User can not access this consumer.');
                }
            }
        }

        if ($this->isPriceCorrect($values->total_price)) {
            $values->offsetSet('total_price', intval($values->total_price));
        } else {
            $form->addError('Nesprávná cena.');
            return false;
        }
        if ($itemCount > 1) {
            for ($i = 1; $i < $itemCount; $i++) {
                $correctPriceId = 'price'.$i;
                if ($this->isPriceCorrect($values->$correctPriceId)) {
                    $values->offsetSet($correctPriceId, intval($values->$correctPriceId));
                } else {
                    $form->addError('Nesprávná cena '.($i + 1).". položky.");
                    return false;
                }
            }
        }
        if ($itemCount > 1) {
            $priceSum = 0;
            for ($i = 1; $i < $itemCount; $i++) {
                $correctPriceId = 'price'.$i;
                $priceSum += $values->$correctPriceId;
            }
            if ($priceSum >= $values->total_price) {
                $form->addError("Celková cena musí být vyšší, než součet cen položek.");
                return false;
            }
        }

        return true;
    }

    private function countDataForAddInvoiceForm(Nette\Utils\ArrayHash $values): Nette\Utils\ArrayHash
    {
        $itemCount = intval($this->getParameters()['itemCount']);

        if ($values->today) {
            $now = date('Y-m-d', time());
            $values->date = $now;
        } else {
            $values->date = date('Y-m-d', strtotime($values->date));
        }

        for ($i = 0; $i < $itemCount; $i++) {
            $correctCategoryId = 'category'.$i;
            if ($values->$correctCategoryId == 0) {
                $values->offsetSet($correctCategoryId, null);
            }
            $correctConsumerId = 'consumer'.$i;
            if ($values->$correctConsumerId == 0) {
                $values->offsetSet($correctConsumerId, null);
            }
        }

        if ($itemCount > 1) {
            $priceSum = 0;
            for ($i = 1; $i < $itemCount; $i++) {
                $correctPriceId = 'price'.$i;
                $priceSum += $values->$correctPriceId;
            }
            $values->offsetSet('price0', $values->total_price - $priceSum);
        }

        return $values;
    }

    public function addInvoiceFormSucceeded(Form $form, Nette\Utils\ArrayHash $values): void
    {
        $itemCount = intval($this->getParameters()['itemCount']);

        $values->offsetSet('paidby', $this->paidBy);
        $values->offsetSet('item_count', $itemCount);

        $isFormCorrect = $this->isFormCorrect($form, $values, $itemCount);

        if ($isFormCorrect) {
            $values = $this->countDataForAddInvoiceForm($values);

            $wasDatabaseSuccessful = $this->dataSender->sendAddinvoiceForm($values);

            if ($wasDatabaseSuccessful) {
                $this->flashMessage("Doklad byl úspěšně přidaný.", "success");
                $this->redirect("this");
            } else {
                $form->addError("Bohužel se nám nepodařilo doklad uložit do databáze.");
            }
        }
    }
}