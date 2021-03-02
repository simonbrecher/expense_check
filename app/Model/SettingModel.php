<?php


namespace App\Model;

use App\Presenters\AccessUserException;
use Nette;
use Tracy\Debugger;

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
        if (!$category) {
            return false;
        } else {
            return $category->family_id == $this->user->identity->family_id and !$category->is_cash_account_balance;
        }
    }

    public function addCategory(Nette\Utils\ArrayHash $values): void
    {
        Debugger::barDump($values);
    }

    public function editCategory(Nette\Utils\ArrayHash $values, int $id): bool
    {
        if (!$this->canAccessCategory($id)) {
            throw new AccessUserException('Nepodařilo se editovat kategorii.');
        }

        $row = $this->table('category')->get($id);

        if ($row->name != $values->name) {
            $sameName = $this->table('category')->where('name', $values->name)->fetch();
            if ($sameName) {
                throw new DupliciteUserException('Jméno je už zabrané.');
            }
        }

        try {
            return $row->update($values);
        } catch (\PDOException) {
            throw new \PDOException('Nepodařilo se editovat kategorii.');
        }
    }

    public function getCategoryParameters(int $id): array
    {
        return $this->table('category')->where('id', $id)->select('name, description')->fetch()->toArray();
    }
}