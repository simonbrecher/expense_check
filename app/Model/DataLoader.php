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

    /** get table invoice_head */
    public function getInvoiceHead()
    {
        return $this->database->table("invoice_head");
    }

    /** get invoice_item(s) by invoice_head_id */
    public function getInvoiceItem($invoice_head_id)
    {
//        Debugger::barDump($invoice_head_id);

        $invoice_items = $this->database->table("invoice_item")
                            ->where("invoice_head_id = ", $invoice_head_id);

//        foreach ($invoice_items as $invoice_item) {
//            Debugger::barDump($invoice_item);
//        }

        return $invoice_items;
    }

    /** get user name by id */
    public function getUserName($userId) {
        $user = $this->database->table("user")->where("id = ", $userId)->fetch();
        return $user->name;
    }

    /** get card card_name by id */
    public function getCardName($cardId) {
        $card = $this->database->table("card")->where("id = ", $cardId)->fetch();
        return $card->card_name;
    }

    /** get member name by id */
    public function getMemberName($memberId) {
        if ($memberId === NULL) {
            return "Všichni";
        } else {
            $member = $this->database->table("member")->where("id = ", $memberId)->fetch();
            return $member->name;
        }
    }

    /** get card category_name by id */
    public function getCategoryName($categoryId) {
        if ($categoryId === NULL) {
            return "Nezařazeno";
        } else {
            $category = $this->database->table("category")->where("id = ", $categoryId)->fetch();
            return $category->name;
        }
    }

    /** return dictionary of all id(s) and var_symbol(s) from card for current user */
    public function getUserAllCardsVS() {
        $cards = $this->database->table("card")->where("user_id = ", $this->user->id);
        $varSymbols = [];
        foreach ($cards as $card) {
            $cardNumber = $card->public_card_num;
            $varSymbols[$card->id] = $this->getVSFromCardNumber($cardNumber);
        }
//        Debugger::barDump($varSymbols);
        return $varSymbols;
    }

    /** return dictionary of all id(s) and var_symbol(s) from card for current user */
    public function getUserAllBankAccountNumbers() {
        $bankAccounts = $this->database->table("bank_account")->where("user_id = ", $this->user->id);
        $varSymbols = [];
        foreach ($bankAccounts as $bankAccount) {
            $varSymbols[$bankAccount->id] = $bankAccount->number_account;
        }
//        Debugger::barDump($varSymbols);
        return $varSymbols;
    }

    private function getVSFromCardNumber($cardNumber) {
        return substr($cardNumber, -4);
    }
}