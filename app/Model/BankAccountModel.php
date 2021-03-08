<?php


namespace App\Model;


use App\Presenters\AccessUserException;
use Nette\Database\Table\Selection;
use Nette\Neon\Exception;
use Nette\Utils\ArrayHash;

class BankAccountModel extends BaseModel
{
    public function getBankAccounts(): Selection
    {
        return $this->table('bank_account')->order('is_active DESC');
    }

    public function getCards(): Selection
    {
        return $this->table('card')->order('is_active DESC');
    }

    public function getBankSelect(): array
    {
        return $this->database->table('bank')->select('id, CONCAT(bank_code, " - ", name) AS name')->fetchPairs('id', 'name');
    }

    public function getBankAccountSelect(): array
    {
        return $this->table('bank_account')->fetchPairs('id', 'number');
    }

    public function addBankAccount(ArrayHash $values): void
    {
        $values->user_id = $this->user->identity->getId();

        $sameBankAccount = $this->database->table('bank_account')->where('bank_id', $values->bank_id)->where('number', $values->number)->fetch();
        if ($sameBankAccount) {
            throw new DupliciteException('Stejný bankovní účet je už zabraný.');
        }

        try {
            $this->database->table('bank_account')->insert($values);
        } catch (\PDOException) {
            throw new \PDOException('Bankovní účet se nepodařilo uložit.');
        }
    }

    public function addCard(ArrayHash $values): void
    {
        $values->user_id = $this->user->identity->getId();

        $bankAccount = $this->table('bank_account')->fetch();

        if (!$bankAccount) {
            throw new AccessUserException('Uživatel nemá přístup k tomuto bankovnímu účtu.');
        }

        try {
            $this->database->table('card')->insert($values);
        } catch (\PDOException $exception) {
            throw new \PDOException('Platební kartu se nepodařilo uložit.');
        }
    }
}

class DupliciteException extends Exception
{

}