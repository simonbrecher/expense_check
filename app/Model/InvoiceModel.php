<?php

declare(strict_types=1);
namespace App\Model;

use App\Form\InvoiceForm;
use Tracy\Debugger;

class InvoiceModel extends BaseModel
{
    public const MAX_ITEM_COUNT = 2;
    protected const PAIDBY_TYPES = ['PAIDBY_CASH' => 'Hotovostí', 'PAIDBY_CARD' => 'Kartou', 'PAIDBY_BANK' => 'Bankou'];

    public function constructAddInvoiceValues(InvoiceForm $form): array|false
    {
        $values = $form->values;

        Debugger::barDump('updateAddInvoiceValues');
        Debugger::barDump($values);

        $head = array(
            'date' => $this->normalizeDateFormat($values->date),
            'type_paidby' => $values->type_paidby,
            'card_id' => $values->type_paidby == 'PAIDBY_CARD' ? $values->card_id : null,
            'var_symbol' => $values->type_paidby == 'PAIDBY_BANK' ? $values->var_symbol : null
        );

        $items = array();
        $firstItem = array(
            'czk_price' => $values->czk_total_price,
            'description' => $values->description ?: $this->getCategoryName($values->category),
            'category' => $values->category,
            'consumer' => $values->consumer
        );
        $items[] = $firstItem;

        $itemsValues = $values->items ?? array();

        foreach ($itemsValues as $itemValues) {
            Debugger::barDump($itemValues);

            $item = array(
                'czk_price' => $itemValues->czk_price,
                'description' => $itemValues->description ?: $this->getCategoryName($itemValues->category),
                'category' => $values->category,
                'consumer' => $values->consumer
            );
            $items[] = $item;

            $items[0]['czk_price'] -= $itemValues->czk_price;
        }

        Debugger::barDump($head);
        Debugger::barDump($items);

        return false;
    }

    public function addInvoice(array $values): bool
    {
        Debugger::barDump('addInvoice');
        Debugger::barDump($values);

        return false;
    }

    public function getCategoryName(int|null $id): string
    {
        Debugger::barDump($id);
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
        return $this->table('consumer')
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