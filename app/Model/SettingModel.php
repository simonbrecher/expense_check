<?php

declare(strict_types=1);
namespace App\Model;

use App\Presenters\AccessUserException;
use Nette;

class SettingModel extends BaseModel
{
    public function getCategories()
    {
        return $this->table('category')->where('NOT is_cash_account_balance')->group('category.id')->order('SUM(:invoice_item.czk_amount) DESC');
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
        $sameName = $this->table('category')->where('name', $values->name)->fetch();
        if ($sameName) {
            throw new DupliciteCategoryException('Jméno je už zabrané.');
        }

        $values->family_id = $this->user->identity->family_id;

        try {
            $this->database->table('category')->insert($values);
        } catch (\PDOException) {
            throw new \PDOException('Nepodařilo se přidat kategorii.');
        }
    }

    public function editCategory(Nette\Utils\ArrayHash $values, int $id): bool
    {
        if (!$this->canAccessCategory($id)) {
            throw new AccessUserException('Nepodařilo se upravit kategorii.');
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
            throw new \PDOException('Nepodařilo se upravit kategorii.');
        }
    }

    public function removeCategory(int $id): void
    {
        if (!$this->canAccessCategory($id)) {
            throw new \PDOException('Nepodařilo se smazat kategorii.');
        }

        $row = $this->table('category')->get($id);
        if (!$row) {
            throw new \PDOException('Nepodařilo se smazat kategorii.');
        }

        try {
            $row->delete();
        } catch (\PDOException) {
            throw new \PDOException('Nepodařilo se smazat kategorii.');
        }
    }

    public function getCategoryParameters(int $id): array
    {
        return $this->table('category')->where('id', $id)->select('name, description, is_active')->fetch()->toArray();
    }

    public function getCategoryItemCount(int $id): int
    {
        return $this->table('category')->get($id)->related('invoice_item')->count();
    }

    public function getCategoryName(int $id): string
    {
        return $this->table('category')->get($id)->name;
    }
}

class DupliciteCategoryException extends \Exception
{

}