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

    // temporary
    private $itemCount;

    public  function __construct(Model\DataLoader $dataLoader, Model\DataSender $dataSender)
    {
        $this->dataLoader = $dataLoader;
        $this->dataSender = $dataSender;
        $this->dataSender->setDataLoader($this->dataLoader);
    }

    public function startup()
    {
        parent::startup();
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect("Sign:in");
        } else {
            $this->dataLoader->setUser($this->getUser());
            $this->dataSender->setUser($this->getUser());
        }
    }

    public function renderShow()
    {
        /** @var Nette\Database\Context */
        $invoice_heads = $this->dataLoader->getInvoiceHead();

        $this->template->dataLoader = $this->dataLoader;
        $this->template->invoice_heads = $invoice_heads;
    }

    protected function createComponentAddInvoice(): MyForm
    {
        $form = new MyForm;

        $itemCount = 2;
        $this->itemCount = $itemCount;

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
            $cards = $this->dataLoader->getUserAllCardsVS();
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

        if ($itemCount == 1) {
            $form->addText('total_price', $itemCount == 1 ? 'Cena:' : 'Celková cena:')
                ->setRequired('Doplňte datum platby.');
        } else {
            $form->addText('total_price', $itemCount == 1 ? 'Cena:' : 'Celková cena:');
            $form->addCheckbox('count_total_price', 'Dopočítat celkovou cenu automaticky');
        }

        $form->addText('date', 'Datum platby:');
        $form->addCheckbox('today', 'Zaplaceno dnes');

        for ($i = 0; $i < $itemCount; $i++) {
            $form->addMyHtml("-------------------------------");

            $categories = $this->dataLoader->getAllCategories();

            $form->addSelect('category'.$i, 'Utraceno za:', $categories)
                ->setRequired('Doplňte, za co byla položka utracená.');

            $members = $this->dataLoader->getAllMembers();
            $form->addSelect('member'.$i, 'Utraceno pro:', $members)
                ->setRequired('Doplňte, pro kterého člena rodiny byla položka utracená.')
                ->setDefaultValue(0);

            if ($itemCount > 1) {
                $form->addText('price'.$i, 'Cena v kč:');
                $form->addCheckbox('count_price'.$i, 'Dopočítat cenu položky automaticky');
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
    private function isCounterBankAccountCorrect(string $bankAccountNumber, string $bankCode)
    {
        return $bankAccountNumber !== 'a' and $bankCode !== 'a';
    }

    private function isFormCorrect(Form $form, Nette\Utils\ArrayHash $values, int $itemCount): bool
    {
        if ($itemCount <= 0) {
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
            $currentMember = 'member'.$i;
            $memberId = $values->$currentMember;
            if ($memberId != 0) {
                if (!$this->dataLoader->canAccess('member', $memberId)) {
                    throw new ErrorException('User can not access this member.');
                }
            }
        }

        if ($itemCount == 1) {
            if (!$this->isPriceCorrect($values->total_price)) {
                $form->addError('Nesprávná cena.');
                return false;
            }

        } else {
            $toCount = null;
            $amountTotalPrice = 0;
            $amountPrices = 0;
            if ($values->count_total_price) {
                $toCount = -1;
            } else {
                if (!$this->isPriceCorrect($values->total_price)) {
                    $form->addError('Nesprávná celková cena.');
                    return false;
                } else {
                    $amountTotalPrice += $values->total_price;
                }
            }
            for ($i = 0; $i < $itemCount; $i++) {
                $correctPrice = 'price'.$i;
                $correctCountPrice = 'count_price'.$i;

                if ($values->$correctCountPrice) {
                    if ($toCount !== null) {
                        $form->addError('Dopočítat můžete pouze cenu jedné položky, nebo celého dokladu.');
                        return false;
                    } else {
                        $toCount = $i;
                    }
                } else {
                    if (!$this->isPriceCorrect($values->$correctPrice)) {
                        $form->addError('Nesprávná cena '.($i + 1).". položky.");
                        return false;
                    } else {
                        $amountPrices += $values->$correctPrice;
                    }
                }
            }

            // the difference between total price and sum of prices can not be higher than 5
            if ($amountTotalPrice < $amountPrices and $toCount !== -1) {
                $form->addError('Celková cena nesmí být menší, než ceny jednotlivých položek.');
                return false;
            } elseif ($amountTotalPrice == $amountPrices and $toCount !== null) {
                $form->addError('Vypočítaná cena položky nesmí nulovou hodnotu.');
                return false;
            } elseif ($toCount === null) {
                if (abs($amountTotalPrice - $amountPrices) > 5) {
                    $form->addError('Celková cena a součet cen položek se moc liší.
                        Zkontrolujte ceny nebo označte "Spočítat celkovou cenu automaticky".');
                    return false;
                }
            }
        }

        return true;
    }

    private function countDataForAddInvoiceForm(Nette\Utils\ArrayHash $values): Nette\Utils\ArrayHash
    {
        $itemCount = $this->itemCount;

        if ($values->today) {
            $now = date('d.m.Y', time());
            $values->date = $now;
        }
        for ($i = 0; $i < $itemCount; $i++) {
            $correctCategoryId = 'category'.$i;
            if ($values->$correctCategoryId === 0) {
                $values->$correctCategoryId = null;
            }
            $correctMemberId = 'member'.$i;
            if ($values->$correctMemberId === 0) {
                $values->$correctMemberId = null;
            }
        }

        if ($itemCount == 1) {
            $values->price0 = $values->total_price;
        } else {
            $toCount = null;
            if ($values->count_total_price) {
                $toCount = -1;
            } else {
                for ($i = 0; $i < $itemCount; $i++) {
                    $correctCountPriceId = 'count_price'.$i;
                    if ($values->$correctCountPriceId) {
                        $toCount = $i;
                        break;
                    }
                }
            }
            $totalPrice = $values->count_total_price ? 0 : $values->total_price;
            $itemSumPrice = 0;
            for ($i = 0; $i < $itemCount; $i++) {
                $correctCountPriceId = 'count_price'.$i;
                if (!$values->$correctCountPriceId) {
                    $correctPriceId = 'price'.$i;
                    $itemSumPrice += $values->$correctPriceId;
                }
            }
            if ($values->total_price !== null) {
                $values->total_price = floatval($values->total_price);
            }
            for ($i = 0; $i < $itemCount; $i++) {
                $correctPriceId = 'price'.$i;
                if ($values->$correctPriceId !== null) {
                    $values->$correctPriceId = floatval($values->$correctPriceId);
                }
            }

            // count nothing, but make sure the item prices sum up to total price
            if ($toCount === null) {
                $multiplier = $totalPrice / $itemSumPrice;
                for ($i = 0; $i < $itemCount; $i++) {
                    $correctPriceId = 'price'.$i;
                    $values->$correctPriceId *= $multiplier;
                }
            // count total price
            } elseif ($toCount === -1) {
                $values->total_price = $itemSumPrice;
            // count item price
            } else {
                $correctPriceId = 'price'.$toCount;
                $values->$correctPriceId = $totalPrice - $itemSumPrice;
            }
        }

        return $values;
    }

    public function addInvoiceFormSucceeded(Form $form, Nette\Utils\ArrayHash $values): void
    {
        $itemCount = $this->itemCount;

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