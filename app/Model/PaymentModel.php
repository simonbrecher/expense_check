<?php


namespace App\Model;


use App\Presenters\AccessUserException;
use Nette\Database\Table\Selection;

class PaymentModel extends BaseModel
{
    private function canAccessBankAccount(int $id): bool
    {
        $row = $this->table('bank_account')->get($id);
        return (bool) $row;
    }

    public function getBankAccounts(): Selection
    {
        return $this->table('bank_account');
    }

    public function getImportIntervalsSorted(int $bankAccountId): Selection
    {
        if (!$this->canAccessBankAccount($bankAccountId)) {
            throw new AccessUserException('Uživatel nemůže zpřístupnit tento bankovní účet.');
        }

        return $this->database->table('ba_import')->where('bank_account_id', $bankAccountId)->order('d_statement_start');
    }
}