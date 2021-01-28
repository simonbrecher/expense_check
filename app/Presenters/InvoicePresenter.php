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

    // temporary
    private $itemCount;

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
        }

        if ($paidBy === 'card') {
            $cards = $this->dataLoader->getUserAllCardsVS();
            $form->addSelect('card', 'Platební karta:', $cards)
                ->setRequired('Doplňte variabilní kód platební karty.');

        } elseif ($paidBy === "bank") {
            $bankAccounts = $this->dataLoader->getUserAllBankAccountNumbers();
            $form->addSelect('bank_account', 'Bankovní účet:', $bankAccounts)
                ->setRequired('Doplňte číslo bankovního účtu.');
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

        $form->addTextArea('description', 'Poznámka:')->setMaxLength(50);

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
        Debugger::barDump(floatval($string));
        return floatval($string) != 0 or $string == "0";
    }

    private function isFormCorrect(Form $form, \stdClass $values, int $itemCount): bool
    {
        if ($itemCount <= 0) {
            throw new ErrorException('Wrong $itemcount.');
        }

        switch($this->getParameter("action")) {
            case 'addCash':
                break;
            case 'addCard':
                if (!$this->dataLoader->canAccess('card', $values->card)) {
                    throw new ErrorException('User can not access this card.');
                }
                break;
            case 'addBank':
                if ($values->bank_account === 0 and $values->var_symbol === '') {
                    $form->addError('Doplňte bankovní účet nebo variabilní symbol.');
                    return false;
                } else {
                    if ($values->bank_account !== 0) {
                        if (!$this->dataLoader->canAccess('bank_account', $values->bank_account)) {
                            throw new ErrorException('User can not access this bank account.');
                        }
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
            for ($i = 0; $i < $itemCount; $i ++) {
                $correct_price = 'price'.$i;
                $correct_count_price = 'count_price'.$i;

                if ($values->$correct_count_price) {
                    if ($toCount !== null) {
                        Debugger::barDump("HERE");
                        $form->addError('Dopočítat můžete pouze cenu jedné položky, nebo celého dokladu.');
                        return false;
                    } else {
                        $toCount = $i;
                    }
                } else {
                    if (!$this->isPriceCorrect($values->$correct_price)) {
                        $form->addError('Nesprávná cena '.($i + 1).". položky.");
                        return false;
                    } else {
                        $amountPrices += $values->$correct_price;
                    }
                }
            }

            Debugger::barDump($toCount);

            // the difference between total price and sum of prices can not be higher than 5
            if ($amountTotalPrice < $amountPrices - 5 and $toCount !== -1 and $toCount !== null) {
                $form->addError('Celková cena nesmí být menší, než ceny jednotlivých položek.');
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

    public function addInvoiceFormSucceeded(Form $form, \stdClass $values): void
    {
        Debugger::barDump($values);
        $itemCount = $this->itemCount;

        $isFormCorrect = $this->isFormCorrect($form, $values, $itemCount);

        if ($isFormCorrect) {
            $this->flashMessage("Doklad byl úspěšně přidaný.");
            $this->redirect("this");
        }
    }
}