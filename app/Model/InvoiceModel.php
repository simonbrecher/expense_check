<?php

declare(strict_types=1);
namespace App\Model;


use App\Form\InvoiceForm;
use App\Presenters\AccessUserException;
use Nette\Neon\Exception;
use Nette\Database\Table\Selection;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class InvoiceModel extends BaseModel
{
    private const VAR_SYMBOL_PATTERN = '~^[0-9]{0,10}$~';

    public const MAX_ITEM_COUNT = 4;

    public function canAccessInvoice(int $id): bool
    {
        $invoice = $this->table('invoice_head')->get($id);
        if (!$invoice) {
            return false;
        } else {
            return !$invoice->is_cash_account_balance;
        }
    }

    public function getInvoiceDate(int $id): DateTime
    {
        return $this->table('invoice_head')->get($id)->d_issued;
    }

    public function getInvoiceInterval(): array
    {
        $invoices = $this->getInvoices();
        if ($invoices->count('id') == 0) {
            return [new DateTime(), new DateTime()];
        } else {
            return [$invoices->min('d_issued'), $invoices->max('d_issued')];
        }
    }

    private function validateCategory($id): void
    {
        $category = $this->table('category')->get($id);
        if (!$category) {
            throw new AccessUserException('Uživatel nemá přístup k této kategorii.');
        }
    }

    private function validateConsumer($id): void
    {
        $consumer = $this->table('user')->get($id);
        if (!$consumer) {
            throw new AccessUserException('Uživatel nemá přístup k tomuto členu rodiny.');
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

        if ($values->type_paidby != 'PAIDBY_ATM') {
            if ($values->category) {
                $this->validateCategory((int) $values->category);
            } else {
                throw new InvalidValueException('Kategorie hlavní položky v dokladu musí být vybraná.');
            }

            if ($values->consumer) {
                $this->validateConsumer((int) $values->consumer);
            }
        }

        if ($values->type_paidby == 'PAIDBY_BANK') {
            if (!preg_match(self::VAR_SYMBOL_PATTERN, $values->var_symbol)) {
                throw new InvalidValueException('Neplatný formát variabilního symbolu.');
            }
        }

        if ($values->czk_total_amount === '') {
            throw new InvalidValueException('Vyplňte celkovou cenu.');
        } elseif (! is_numeric($values->czk_total_amount)) {
            throw new InvalidValueException('Neplatný formát celkové ceny ceny.');
        } elseif ($values->czk_total_amount <= 0 && $values->type_paidby !== 'PAIDBY_ATM') {
            throw new InvalidValueException('Celková cena musí být kladná.');
        }

        $items = array();
        $firstItem = array(
            'czk_amount' => round(floatval($values->czk_total_amount)),
            'description' => $values->description ?: $this->getCategoryName($values->category),
            'category_id' => $values->type_paidby === 'PAIDBY_ATM' ? null : $values->category,
            'consumer_id' => $values->type_paidby === 'PAIDBY_ATM' ? null : ($values->consumer ?: null),
            'is_main' => true,
        );
        $items[] = $firstItem;

        $itemsValues = $values->items ?? array();

        if ($values->type_paidby !== 'PAIDBY_ATM') {
            foreach ($itemsValues as $itemValues) {
                if ($itemValues->category) {
                    $this->validateCategory((int) $itemValues->category);
                }

                if ($itemValues->consumer) {
                    $this->validateConsumer((int) $itemValues->consumer);
                }

                if ($itemValues->czk_amount === '') {
                    throw new InvalidValueException('Vyplňte cenu.');
                } elseif (! is_numeric($itemValues->czk_amount)) {
                    throw new InvalidValueException('Neplatný formát ceny.');
                }

                $item = array(
                    'czk_amount' => $itemValues->czk_amount,
                    'category_id' => $itemValues->category ?: $values->category,
                    'description' => $itemValues->description ?: ( $itemValues->category !== null ? $this->getCategoryName($itemValues->category) : $firstItem['description']),
                    'consumer_id' => $itemValues->consumer ?: null,
                    'is_main' => false,
                );
                $items[] = $item;

                $items[0]['czk_amount'] -= $itemValues->czk_amount;
            }

            if ($items[0]['czk_amount'] <= 0) {
                throw new InvalidValueException('Celková cena musí být vyšší, než ceny položek.');
            }
        }

        return array('head' => $head, 'items' => $items);
    }

    /* transaction MUST BE outside of this function */
    private function autoCreatePayment(ActiveRow $head): void
    {
        if ($head->type_paidby == 'PAIDBY_CASH') {
            $payment = array(
                'user_id' => $this->user->id,
                'cash_account_id' => $this->table('cash_account')->fetch()->id,
                'd_payment' => $head->d_issued,
                'czk_amount' => - $head->related('invoice_item')->sum('czk_amount'),
                'description' => $head->related('invoice_item')->where('is_main')->fetch()->description,
                'type_paidby' => 'PAIDBY_CASH',
                'is_identified' => false,
            );

            $head->related('payment')->insert($payment);
        }
    }

    /* transaction MUST BE outside of this function */
    private function unpairPayment(ActiveRow $head): void
    {
        if ($head->type_paidby == 'PAIDBY_CASH') {
            $head->related('payment')->fetch()->delete();
        } else {
            $head->related('payment')->update(['invoice_head_id' => null, 'is_consumption' => true, 'is_identified' => false, 'cash_account_id' => null]);
        }
    }

    /* not edit */
    public function addInvoice(InvoiceForm $form): void
    {
        try {
            $this->database->beginTransaction();
            $values = $this->constructAddInvoiceValues($form);

            $head = $this->database->table('invoice_head')->insert($values['head']);
            $head->related('invoice_item')->insert($values['items']);

            $this->autoCreatePayment($head);

            $this->database->commit();
        } catch (\PDOException) {
            $this->database->rollBack();
            throw new \PDOException('Nepodařilo se doklad uložit do databáze.');
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

            $this->unpairPayment($head);

            $head->update($values['head']);
            $head->related('invoice_item')->delete();
            $head->related('invoice_item')->insert($values['items']);

            $this->autoCreatePayment($head);

            $this->database->commit();
        } catch (\PDOException) {
            $this->database->rollBack();
            throw new \PDOException('Nepodařilo se doklad uložit do databáze.');
        }
    }

    public function removeInvoice(int $id): void
    {
        if (!$this->canAccessInvoice($id)) {
            throw new \PDOException('Nepodařilo se smazat doklad.');
        }

        $head = $this->table('invoice_head')->get($id);
        if (!$head) {
            throw new \PDOException('Nepodařilo se smazat doklad.');
        }

        try {
            $this->database->beginTransaction();

            $this->unpairPayment($head);
            $head->delete();

            $this->database->commit();
        } catch (\PDOException) {
            $this->database->rollBack();
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

    public function getCategorySelect(string|null $editId): array
    {
        $data = $this->table('category')->where('NOT is_cash_account_balance')->where('is_active')->fetchPairs('id', 'name');
        if ($editId !== null) {
            if ($this->canAccessInvoice((int) $editId)) {
                $items = $this->database->table('invoice_item')->where('invoice_head_id', $editId)->select('category.id AS id, category.name AS name');
                foreach ($items as $item) {
                    $data[$item->id] = $item->name;
                }
            } else {
                throw new \PDOException('Uživatel nemá přístup k tomuto dokladu.');
            }
        }
        return $data;
    }

    public function getConsumerSelect(string|null $editId): array
    {
        $data = $this->table('user')->where('is_active')->fetchPairs('id', 'name');
        if ($editId !== null) {
            if ($this->canAccessInvoice((int) $editId)) {
                $items = $this->database->table('invoice_item')->where('invoice_head_id', $editId)->select('consumer_id AS id, consumer_id.name AS name');
                foreach ($items as $item) {
                    $data[$item->id] = $item->name;
                }
            } else {
                throw new \PDOException('Uživatel nemá přístup k tomuto dokladu.');
            }
        }
        return $data;
    }

    public function getCardSelect(string|null $editId): array
    {
        $data = $this->table('card')->where('is_active')->select('id, CONCAT("**", number, " - ", name) AS name')->fetchPairs('id', 'name');
        if ($editId !== null) {
            if ($this->canAccessInvoice((int) $editId)) {
                $invoice = $this->table('invoice_head')->get($editId);
                $card = $invoice->ref('card');
                if ($card) {
                    $data[$card->id] = $card->number.'** - '.$card->name;
                }
            } else {
                throw new \PDOException('Uživatel nemá přístup k tomuto dokladu.');
            }
        }
        return $data;
    }

    public function getInvoicesInInterval(DateTime $startInterval, DateTime $endInterval): Selection
    {
        return $this->getInvoices()->where('d_issued >=', $startInterval)->where('d_issued <=', $endInterval);
    }

    private function getInvoices(): Selection
    {
        return $this->table('invoice_head')->where('NOT type_paidby', 'PAIDBY_FEE')
                    ->where('NOT invoice_head.is_cash_account_balance');
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