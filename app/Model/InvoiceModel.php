<?php

declare(strict_types=1);
namespace App\Model;


class InvoiceModel extends BaseModel
{
    public const MAX_ITEM_COUNT = 2;
    protected const PAIDBY_TYPES = ['cash' => 'Hotovostí', 'card' => 'Kartou', 'bank' => 'Bankou'];

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

    public function getPaidbyTypes(): array
    {
        return self::PAIDBY_TYPES;
    }
}