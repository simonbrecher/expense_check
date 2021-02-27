<?php

declare(strict_types=1);
namespace App\Model;

use App\Form\InvoiceForm;
use Nette\Neon\Exception;

class InvoiceModel extends BaseModel
{
    public const MAX_ITEM_COUNT = 2;
    protected const PAIDBY_TYPES = ['PAIDBY_CASH' => 'Hotovostí', 'PAIDBY_CARD' => 'Kartou', 'PAIDBY_BANK' => 'Bankou'];

    public function constructAddInvoiceValues(InvoiceForm $form): array
    {
        $values = $form->values;

        $head = array(
            'user_id' => $this->user->id,
            'd_issued' => $this->normalizeDateFormat($values->d_issued),
            'type_paidby' => $values->type_paidby,
            'card_id' => $values->type_paidby == 'PAIDBY_CARD' ? $values->card_id : null,
            'var_symbol' => $values->type_paidby == 'PAIDBY_BANK' ? $values->var_symbol : ''
        );

        $items = array();
        $firstItem = array(
            'czk_amount' => $values->czk_total_amount,
            'description' => $values->description ?: $this->getCategoryName($values->category),
            'category_id' => $values->category,
            'consumer_id' => $values->consumer
        );
        $items[] = $firstItem;

        $itemsValues = $values->items ?? array();

        foreach ($itemsValues as $itemValues) {
            $item = array(
                'czk_amount' => $itemValues->czk_amount,
                'description' => $itemValues->description ?: $this->getCategoryName($itemValues->category),
                'category_id' => $values->category,
                'consumer_id' => $values->consumer
            );
            $items[] = $item;

            $items[0]['czk_amount'] -= $itemValues->czk_amount;
        }

        if ($items[0]['czk_amount'] <= 0) {
            throw new InvalidValueException('Celková cena musí být vyšší, než ceny položek.');
        }

        return array('head' => $head, 'items' => $items);
    }

    public function addInvoice(InvoiceForm $form): string|null
    {
        try {
            $this->database->beginTransaction();
            $values = $this->constructAddInvoiceValues($form);

            $invoiceHead = $this->database->table('invoice_head')->insert($values['head']);
            $invoiceHead->related('invoice_item')->insert($values['items']);
            $this->database->commit();
            $errorMessage = null;
        } catch (\PDOException|InvalidValueException $exception) {
            $this->database->rollBack();
            $errorMessage = $exception->getMessage();
        }

        return $errorMessage;
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
}

class InvalidValueException extends Exception
{

}