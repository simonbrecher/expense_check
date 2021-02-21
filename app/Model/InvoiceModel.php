<?php


namespace App\Model;


class InvoiceModel extends BaseModel
{
    public function getUserCategories(): array
    {
        return $this->database->table('category')->where('NOT is_cash_account_balance')
            ->fetchPairs('id', 'name');
    }

    public function getUserConsumers(): array
    {
        return $this->database->table('consumer')
            ->fetchPairs('id', 'name');
    }

    public function getUserCards(): array
    {
        return $this->database->table('card')->fetchPairs('id', 'number');
    }
}