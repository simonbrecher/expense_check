<?php


namespace App\Model;


use App\Form\BasicForm;
use App\Presenters\AccessUserException;
use Nette\Database\Table\Selection;

class PaymentModel extends BaseModel
{
    protected const PAIDBY_TYPES = array(
        'PAIDBY_CASH' => 'V hotovosti',
        'PAIDBY_CARD' => 'Kartou',
        'PAIDBY_BANK' => 'Bankou',
        'PAIDBY_ATM' => 'Bankomat',
        'PAIDBY_FEE' => 'Poplatek',
        null => '??',
    );

    public function constructAddPaymentChannelData(BasicForm $form): array
    {
        $oldValues = $form->values;

        if (!$this->canAccessBankAccount($oldValues->bank_account_id)) {
            throw new AccessUserException('Uživatel nemůže zpřístupnit tento bankovní účet.');
        }

        if (!$this->canAccessCategory($oldValues->category_id)) {
            throw new AccessUserException('Uživatel nemůže zpřístupnit tuto kategorii.');
        }

        $values = array(
            'user_id' => $this->user->identity->id,
            'bank_account_id' => $oldValues->bank_account_id,
            'category_id' => $oldValues->category_id,
            'var_symbol' => $oldValues->var_symbol,
            'counter_account_number' => $oldValues->counter_account_number,
            'counter_account_bank_code' => $oldValues->counter_account_bank_code,
            'description' => $oldValues->description,
            'is_active' => $oldValues->is_active,
            'is_consumption_type' => $oldValues->is_consumption_type,
        );

        return $values;
    }

    public function addPaymentChannel(BasicForm $form): void
    {
        try {
            $values = $this->constructAddPaymentChannelData($form);
            $this->database->table('payment_channel')->insert($values);
        } catch (\PDOException) {
            throw new \PDOException('Nepodařilo se trvalý příkaz uložit do databáze.');
        }
    }

    private function canAccessBankAccount(int $id): bool
    {
        $row = $this->table('bank_account')->get($id);
        return (bool) $row;
    }

    private function canAccessCategory(int $id): bool
    {
        $row = $this->table('category')->get($id);
        return (bool) $row;
    }

    public function getBankAccounts(): Selection
    {
        return $this->table('bank_account');
    }

    public function getCategorySelect(): array
    {
        return $this->table('category')->where('is_active')->fetchPairs('id', 'name');
    }

    public function getBankAccountSelect(): array
    {
        return $this->table('bank_account')->where('is_active')->fetchPairs('id', 'number');
    }

    public function getImportIntervalsSorted(int $bankAccountId): Selection
    {
        if (!$this->canAccessBankAccount($bankAccountId)) {
            throw new AccessUserException('Uživatel nemůže zpřístupnit tento bankovní účet.');
        }

        return $this->database->table('ba_import')->where('bank_account_id', $bankAccountId)->order('d_statement_start');
    }

    public function getTypePaidbyName(string|null $typePaidby): string
    {
        return self::PAIDBY_TYPES[$typePaidby];
    }
}