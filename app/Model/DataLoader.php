<?php

declare(strict_types=1);
namespace App\Model;

use Nette;

use Tracy\Debugger;

class DataLoader
{
    /** @var Nette\Database\Context */
    private $database;
    /** @var Nette\Security\User */
    private $user;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }

    /** I don't know how to use getUser() by framework. */
    public function setUser(Nette\Security\User $user)
    {
        $this->user = $user;
    }

    public function idExists(string $table, int $id) {
        return $this->database->table($table)->offsetExists($id);
    }

    /**
     * If the current user can access data from $table with $id without any admin powers.
     * No users are admins yet.
     * false if id does not exist.
     * For database 01.02
     */
    public function canAccess(string $table, int $id)
    {
        if (!$this->idExists($table, $id)) {
            return false;
        }
        switch($table) {
            case 'db_version':
                return false;
            case 'bank':
            case 'family':
                return true;
            case 'ba_import':
                $bank_account_id = $this->database->table($table)->wherePrimary($id)
                    ->fetch()->bank_account_id;
                return $this->canAccess('bank_account', $bank_account_id);
            case 'cash_account_balance':
                $cash_account_id = $this->database->table($table)->wherePrimary($id)
                    ->fetch()->cash_account_id;
                return $this->canAccess('cash_account', $cash_account_id);
            case 'user':
                return $this->user->id == $id;
            case 'payment':
                $payment = $this->database->table($table)->wherePrimary($id)->fetch();
                if ($payment->bank_account_id !== null) {
                    return $this->canAccess('bank_account', $payment->bank_account_id);
                } elseif ($payment->bank_account_id !== null) {
                    return $this->canAccess('cash_account', $payment->cash_account_id);
                } else {
                    return false;
                }
            case 'invoice_item':
                $invoice_head_id = $this->database->table($table)->wherePrimary($id)
                    ->fetch()->invoice_head_id;
                return $this->canAccess('invoice_head', $invoice_head_id);
            case 'category':
            case 'member':
                $family_id = $this->database->table($table)->wherePrimary($id)
                    ->fetch()->family_id;
                $user_family_id = $this->database->table($table)->wherePrimary($this->user->id)
                    ->fetch()->family_id;
                return $family_id == $user_family_id;
            case 'order':
            case 'card':
            case 'bank_account':
            case 'cash_account':
            case 'invoice_head':
            $user_id = $this->database->table($table)->wherePrimary($id)
                ->fetch()->user_id;
            return $user_id == $this->user->id;
            default:
                return false;
        }
    }

    /** get table invoice_head */
    public function getInvoiceHead()
    {
        return $this->database->table("invoice_head");
    }

    /** get invoice_item(s) by invoice_head_id */
    public function getInvoiceItem(int $invoice_head_id)
    {
        $invoice_items = $this->database->table("invoice_item")
                            ->where("invoice_head_id = ", $invoice_head_id);
        return $invoice_items;
    }

    /** get user name by id */
    public function getUserName(int $userId)
    {
        $user = $this->database->table("user")->where("id = ", $userId)->fetch();
        return $user->name;
    }

    /** get card card_name by id */
    public function getCardName(int $cardId)
    {
        $card = $this->database->table("card")->where("id = ", $cardId)->fetch();
        return $card->card_name;
    }

    /** get member name by id */
    public function getMemberName($memberId)
    {
        if ($memberId === null) {
            return "Všichni";
        } else {
            $member = $this->database->table("member")->where("id = ", $memberId)->fetch();
            return $member->name;
        }
    }

    /** get card category_name by id */
    public function getCategoryName($categoryId)
    {
        if ($categoryId === null) {
            return "Nezařazeno";
        } else {
            $category = $this->database->table("category")->where("id = ", $categoryId)->fetch();
            return $category->name;
        }
    }

    /** return dictionary of all id(s) and var_symbol(s) from card for current user */
    public function getUserAllCardsVS()
    {
        $cards = $this->database->table("card")->where("user_id = ", $this->user->id);
        $varSymbols = [];
        foreach ($cards as $card) {
            $cardNumber = $card->public_card_num;
            $varSymbols[$card->id] = $this->getVSFromCardNumber($cardNumber);
        }
        return $varSymbols;
    }

    /** return dictionary of all id(s) and var_symbol(s) from card for current user */
    public function getUserAllBankAccountNumbers()
    {
        $bankAccounts = $this->database->table("bank_account")->where("user_id = ", $this->user->id);
        // Not null, because otherwise it coultn't be set as default value
        $bankAccountNumbers = [0 => "Neuvedeno"];
        foreach ($bankAccounts as $bankAccount) {
            $bankAccountNumbers[$bankAccount->id] = $bankAccount->number_account;
        }
        return $bankAccountNumbers;
    }

    /** return dictionary of all id(s) and var_symbol(s) from card for current user */
    public function getAllCategories()
    {
        $familyId = $this->getUserFamily();
        $categories = $this->database->table("category")->where("family_id = ", $familyId);
        // Not null, because otherwise it coultn't be set as default value
        $dictionary = [0 => "Nezařazeno"];
        foreach ($categories as $category) {
            if (!$category->is_cash_account_balance) {
                $dictionary[$category->id] = $category->name;
            }
        }
        return $dictionary;
    }

    /** return dictionary of all id(s) and var_symbol(s) from card for current user */
    public function getAllMembers()
    {
        $familyId = $this->getUserFamily();
        $members = $this->database->table("member")->where("family_id = ", $familyId);
        /** It will be changed to null after the form is used,
         *  but it has to ve 0 now, otherwise it could not be set as default value for radiolist.
         */
        $dictionary = [0 => "Všichni"];
        foreach ($members as $member) {
            $dictionary[$member->id] = $member->name;
        }
        return $dictionary;
    }

    /** return family_id for current user */
    public function getUserFamily()
    {
        return $this->database->table('user')->wherePrimary($this->user->id)->fetch()->family_id;
    }

    private function getVSFromCardNumber(string $cardNumber)
    {
        return substr($cardNumber, -4);
    }
}