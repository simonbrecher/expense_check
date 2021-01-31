<?php

declare(strict_types=1);
namespace App\Model;

use Exception;
use Nette;

use Tracy\Debugger;

class DataSender
{
    /** @var Nette\Security\User */
    private $user;
    /** @var DataLoader */
    private $dataLoader;

    /** I don't know how to use getUser() by framework. */
    public function setUser(Nette\Security\User $user)
    {
        $this->user = $user;
    }

    /** So the DataLoader object is the same as for Presenter (might not be necessary) */
    public function setDataLoader(DataLoader $dataLoader)
    {
        $this->dataLoader = $dataLoader;
    }

    /** Remove added data from database */
    public function removeAddedData(array $added) {
        foreach (array_reverse($added) as $addedOne) {
            list($tableName, $id) = $addedOne;
            $this->dataLoader->table($tableName)->wherePrimary($id)->delete();
        }
    }

    /** return iff database the data were successfully added */
    public function sendAddInvoiceForm(Nette\Utils\ArrayHash $values): bool
    {
        $added = [];

        try {
            $invoice_head = [];
            $invoice_head['user_id'] = $this->user->id;
            $invoice_head['d_issue'] = $values->date;
            $invoice_head['is_paidby'.$values->paidby] = 1;

            if ($values->paidby == 'card') {
                $invoice_head['card_id'] = $values->card_id;
                $invoice_head['var_symbol'] = $values->var_symbol;
            } elseif ($values->paidby == 'bank') {
                $invoice_head['counter_account_number'] = $values->counter_account_number;
                $invoice_head['counter_account_bank_code'] = $values->counter_account_bank_code;
                $invoice_head['var_symbol'] = $values->var_symbol;
            }

            $table = $this->dataLoader->table('invoice_head');
            $last = $table->insert($invoice_head);

            $invoice_head_id = $last->id;
            $added[] = ['invoice_head', $invoice_head_id];

            for ($i = 0; $i < $values->item_count; $i++) {
                $invoice_item = [];
                $invoice_item['invoice_head_id'] = $invoice_head_id;
                $correctCategoryId = 'category'.$i;
                $invoice_item['category_id'] = $values->$correctCategoryId;
                $correctMemberId = 'member'.$i;
                $invoice_item['member_id'] = $values->$correctMemberId;
                $correctPriceId = 'price'.$i;
                // make error after first item
                if ($i == 0) {
                    $invoice_item['amount'] = $values->$correctPriceId;
                }
//                $invoice_item['amount'] = $values->$correctPriceId;
                $correctDescriptionId = 'description'.$i;
                $invoice_item['description'] = $values->$correctDescriptionId;

                $table = $this->dataLoader->table('invoice_item');
                $last = $table->insert($invoice_item);

                $invoice_item_id = $last->id;
                $added[] = ['invoice_item', $invoice_item_id];
            }


        } catch (Exception $exception) {
            $this->removeAddedData($added);
            return false;
        }

        return false;
    }
}