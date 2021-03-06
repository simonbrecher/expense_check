<?php

declare(strict_types=1);
namespace App\Model;

use App\Form\InvoiceForm;
use App\Presenters\AccessUserException;
use Nette\Neon\Exception;
use Nette;

class InvoiceModel extends BaseModel
{
    public const MAX_ITEM_COUNT = 2;
    protected const PAIDBY_TYPES = ['PAIDBY_CASH' => 'Hotovostí', 'PAIDBY_CARD' => 'Kartou', 'PAIDBY_BANK' => 'Bankou'];

    public function canAccessInvoice(int $id): bool
    {
        $invoice = $this->database->table('invoice_head')->get($id);
        if (!$invoice) {
            return false;
        } else {
            return $invoice->user_id == $this->user->identity->id and !$invoice->is_cash_account_balance;
        }
    }

    public function constructAddInvoiceValues(InvoiceForm $form): array
    {
        $values = $form->values;

        $head = array(
            'user_id' => $this->user->id,
            'd_issued' => $this->normalizeDateFormat($values->d_issued),
            'type_paidby' => $values->type_paidby,
            'card_id' => $values->type_paidby == 'PAIDBY_CARD' ? $values->card_id : null,
            'var_symbol' => $values->type_paidby == 'PAIDBY_BANK' ? $values->var_symbol : '',
        );

        $items = array();
        $firstItem = array(
            'czk_amount' => $values->czk_total_amount,
            'description' => $values->description ?: $this->getCategoryName($values->category),
            'category_id' => $values->category,
            'consumer_id' => $values->consumer,
            'is_main' => true,
        );
        $items[] = $firstItem;

        $itemsValues = $values->items ?? array();

        foreach ($itemsValues as $itemValues) {
            $item = array(
                'czk_amount' => $itemValues->czk_amount,
                'category_id' => $itemValues->category ?? $values->category,
                'description' => $itemValues->description ?: ( $itemValues->category !== null ? $this->getCategoryName($itemValues->category) : $firstItem['description']),
                'consumer_id' => $itemValues->consumer,
                'is_main' => false,
            );
            $items[] = $item;

            $items[0]['czk_amount'] -= $itemValues->czk_amount;
        }

        if ($items[0]['czk_amount'] <= 0) {
            throw new InvalidValueException('Celková cena musí být vyšší, než ceny položek.');
        }

        return array('head' => $head, 'items' => $items);
    }

    public function addInvoice(InvoiceForm $form): void
    {
        try {
            $this->database->beginTransaction();
            $values = $this->constructAddInvoiceValues($form);

            $invoiceHead = $this->database->table('invoice_head')->insert($values['head']);
            $invoiceHead->related('invoice_item')->insert($values['items']);
            $this->database->commit();
        } catch (\PDOException|InvalidValueException $exception) {
            $this->database->rollBack();
            throw new \PDOException($exception->getMessage());
        }
    }

    public function editInvoice(InvoiceForm $form, int $editId): void
    {
        if (!$this->canAccessInvoice($editId)) {
            throw new AccessUserException('Uživatel nemůže upravit tento doklad.');
        }

        try {
            $this->database->beginTransaction();
            $values = $this->constructAddInvoiceValues($form);

            $head = $this->database->table('invoice_head')->get($editId);
            $head->update($values['head']);
            $head->related('invoice_item')->delete();
            $head->related('invoice_item')->insert($values['items']);

            $this->database->commit();

        } catch (\PDOException $exception) {
            $this->database->rollBack();
            throw new \PDOException($exception->getMessage());
        }
    }

    public function removeInvoice(int $id): void
    {
        if (!$this->canAccessInvoice($id)) {
            throw new \PDOException('Nepodařilo se smazat doklad.');
        }

        $row = $this->table('invoice_head')->get($id);
        if (!$row) {
            throw new \PDOException('Nepodařilo se smazat doklad.');
        }

        try {
            $row->delete();
        } catch (\PDOException) {
            throw new \PDOException('Nepodařilo se smazat doklad.');
        }
    }

    public function getCategoryName(int|null $id): string
    {
        if ($id === null) {
            return 'Nezařazeno';
        }
        return $this->table('category')->get($id)->name;
    }

    public function getUserCategories(): array
    {
        return $this->table('category')->where('NOT is_cash_account_balance')
            ->fetchPairs('id', 'name');
    }

    public function getUserConsumers(): array
    {
        return $this->table('user')
            ->fetchPairs('id', 'name');
    }

    public function getUserCards(): array
    {
        return $this->table('card')->fetchPairs('id', 'number');
    }

    public function getPaidbyTypes(): array
    {
        return self::PAIDBY_TYPES;
    }

    public function getInvoicesForView(): Nette\Database\Table\Selection
    {
        return $this->table('invoice_head')->where('NOT invoice_head.is_cash_account_balance');
    }

    public function getEditInvoiceData(int $id): array
    {
        if (!$this->canAccessInvoice($id)) {
            throw new \PDOException('User can not access this invoice.');
        }

        $headBefore = $this->table('invoice_head')->get($id);
        $itemsBefore = $this->database->table('invoice_item')->where('invoice_head_id', $headBefore->id);

        $invoice = array(
            'czk_total_amount' => 0,
            'd_issued' => $headBefore->d_issued->format('j.n.Y'),
            'type_paidby' => $headBefore->type_paidby,
            'card_id' => $headBefore->card_id,
            'var_symbol' => $headBefore->var_symbol,
            'item_count' => count($itemsBefore),
            'items' => array(),
        );

        $formItemId = 1;
        foreach ($itemsBefore as $itemBefore) {
            $invoice['czk_total_amount'] += $itemBefore->czk_amount;
            if ($itemBefore->is_main) {
                $invoice['czk_amount'] = $itemBefore->czk_amount;
                $invoice['description'] = $itemBefore->description;
                $invoice['category'] = $itemBefore->category_id;
                $invoice['consumer'] = $itemBefore->consumer_id;
            } else {
                $invoiceItem = array(
                    'czk_amount' => $itemBefore->czk_amount,
                    'description' => $itemBefore->description,
                    'category' => $itemBefore->category_id,
                    'consumer' => $itemBefore->consumer_id,
                );

                $invoice['items'][$formItemId] = $invoiceItem;
                $formItemId ++;
            }
        }

        return $invoice;
    }
}

class InvalidValueException extends Exception
{

}