<?php


namespace App\Model;


use App\Form\BasicForm;
use App\Presenters\AccessUserException;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;

class PaymentModel extends BaseModel
{
    public function getPaymentInterval(): array
    {
        $payments = $this->tablePayments();
        if ($payments->count('id') == 0) {
            return [new DateTime(), new DateTime()];
        } else {
            return [$payments->min('d_payment'), $payments->max('d_payment')];
        }
    }

    public function activatePaymentChannel(int $id): void
    {
        $row = $this->table('payment_channel')->get($id);
        if (!$row) {
            throw new AccessUserException('Uživatel nemá přístup k tomuto trvalému příkazu.');
        }
        $row->update(['is_active' => true]);
    }

    public function deactivatePaymentChannel(int $id): void
    {
        $row = $this->table('payment_channel')->get($id);
        if (!$row) {
            throw new AccessUserException('Uživatel nemá přístup k tomuto trvalému příkazu.');
        }
        $row->update(['is_active' => false]);
    }

    public function removePaymentChannel(int $id): void
    {
        $row = $this->table('payment_channel')->get($id);
        if (!$row) {
            throw new AccessUserException('Uživatel nemá přístup k tomuto trvalému příkazu.');
        }
        if ($row->related('payment')->count() != 0) {
            throw new AccessUserException('Tento trvalý příkaz nelze smazat, protože podle něj byly roztřízené platby.');
        }
        $row->delete();
    }

    public function constructAddPaymentChannelData(BasicForm $form): array
    {
        $oldValues = $form->values;

        if (!$this->canAccessBankAccount($oldValues->bank_account_id)) {
            throw new AccessUserException('Uživatel nemá přístup k tomuto bankovnímu účtu.');
        }

        if ($oldValues->is_consumption) {
            if ($oldValues->category_id === null) {
                throw new InvalidValueException('Doplňte kategorii pro výdajový trvalý příkaz.');
            }
            if (!$this->canAccessCategory($oldValues->category_id)) {
                throw new AccessUserException('Uživatel nemá přístup k této kategorii.');
            }
        }

        $values = array(
            'user_id' => $this->user->identity->id,
            'bank_account_id' => $oldValues->bank_account_id,
            'category_id' => $oldValues->is_consumption ? $oldValues->category_id : null,
            'var_symbol' => $oldValues->var_symbol,
            'counter_account_number' => $oldValues->counter_account_number,
            'counter_account_bank_code' => $oldValues->counter_account_bank_code,
            'description' => $oldValues->description,
            'is_active' => $oldValues->is_active,
            'is_consumption' => $oldValues->is_consumption,
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

    public function getPaymentChannels(): Selection
    {
        return $this->table('payment_channel');
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
            throw new AccessUserException('Uživatel nemá přístup k tomuto bankovnímu účtu.');
        }

        return $this->database->table('ba_import')->where('bank_account_id', $bankAccountId)->order('d_statement_start');
    }
}