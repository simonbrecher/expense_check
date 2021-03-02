<?php


namespace App\Model;


class SettingModel extends BaseModel
{
    public function getCategories()
    {
        return $this->table('category')->where('NOT is_cash_account_balance')->group('category.id')
            ->select('category.id, name, category.description, COUNT(:invoice_item.id) AS invoice_item_count, SUM(:invoice_item.czk_amount) AS total_amount')
            ->order('total_amount DESC');
    }

    public function canAccessCategory(int $id): bool
    {
        $category = $this->database->table('category')->get($id);
        return $category?->family_id == $this->user->identity->family_id and !$category->is_cash_account_balance;
    }
}