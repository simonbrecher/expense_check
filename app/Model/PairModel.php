<?php

declare(strict_types=1);
namespace App\Model;


use App\Presenters\AccessUserException;
use App\Presenters\BasePresenter;
use Nette\Database\Table\Selection;
use Nette\Neon\Exception;

class PairModel extends BaseModel
{
    private const MAX_MANUAL_PAIR_CZK_DIFFERENCE = 3;

    private const MAX_AUTO_PAIR_CZK_DIFFERENCE = 1;
    private const MAX_AUTO_PAIR_TIMESTAMP_DIFFERENCE = 86400;

    public function getNotIdentifiedCashPayments(): Selection
    {
        $cashAccountId = $this->table('cash_account')->fetch()->id;
        return $this->table('payment')->where('cash_account_id', $cashAccountId)->where('type_paidby', 'PAIDBY_CASH')->where('NOT is_identified');
    }

    private function pairByPaymentChannel(): int
    {
        $pairedCount = 0;

        // this try is here so that SQL request reading database don't effect application, because this is automatic process
        // errors in SQL requests updating database are caught in inside try with transaction
        try {

            $paymentChannels = $this->table('payment_channel')->where('is_active')->fetchAssoc('id');

            $payments = $this->getNotIdentifiedPayments();
            foreach ($payments as $payment) {
                foreach ($paymentChannels as $channel) {
                    if ($payment->bank_account_id == $channel['bank_account_id']) {
                        $isInChannel = true;
//                        if ($channel['counter_account_number'] != '' && $channel['counter_account_bank_code'] != '') {
//                            if ($channel['counter_account_number'] != $payment->counter_account_number || $channel['counter_account_number'] != $payment->counter_account_bank_code) {
//                                $isInChannel = false;
//                            }
//                        }
                        if ($channel['var_symbol'] != $payment->var_symbol) {
                            $isInChannel = false;
                        }

                        if ($isInChannel) {
                            try {
                                $this->database->beginTransaction();

                                if ($channel['is_consumption']) {
                                    $head = array(
                                        'user_id' => $this->user->id,
                                        'card_id' => $payment->card_id,
                                        'd_issued' => $payment->d_payment,
                                        'counter_account_number' => $payment->counter_account_number,
                                        'counter_account_bank_code' => $payment->counter_account_bank_code,
                                        'var_symbol' => $payment->var_symbol,
                                        'type_paidby' => $payment->type_paidby,
                                    );
                                    $head = $this->database->table('invoice_head')->insert($head);
                                    $payment->update(['payment_channel_id' => $channel['id'], 'invoice_head_id' => $head->id, 'is_identified' => true]);

                                    $item = array(
                                        'category_id' => $channel['category_id'],
                                        'is_main' => true,
                                        'czk_amount' => - $payment->czk_amount,
                                        'description' => $channel['description'],
                                    );
                                    $head->related('invoice_item')->insert($item);
                                } else {
                                    $payment->update(['payment_channel_id' => $channel['id'], 'is_consumption' => false, 'is_identified' => true]);
                                }

                                $this->database->commit();

                                $pairedCount ++;
                                break;
                            } catch (\Exception) {
                                $this->database->rollBack();
                                break;
                            }
                        }
                    }
                }
            }

        } catch (\PDOException) {
            // this function is only used automatically. in case of bug, this automatic process will do nothing
        }

        return $pairedCount;
    }

    private function autoPair(): int
    {
        $pairedCount = 0;

        // this try is here so that SQL request reading database don't effect application, because this is automatic process
        // errors in SQL requests updating database are caught in inside try with transaction
        try {

            $payments = $this->getNotIdentifiedPayments();
            $invoices = $this->getNotIdentifiedInvoices()->group('invoice_head.id')
                ->select('invoice_head.id, invoice_head.type_paidby, d_issued, SUM(:invoice_item.czk_amount) AS czk_amount')->fetchAssoc('id');

            $pairedInvoiceIds = [];
            foreach ($payments as $payment) {
                foreach ($invoices as $invoice) {
                    if (! in_array($invoice['id'], $pairedInvoiceIds)) {
                        // FIO bank doesn't differentiate between card payment and ATM
                        if ($payment->type_paidby == $invoice['type_paidby'] || ($payment->type_paidby == 'PAIDBY_CARD' && $invoice['type_paidby'] == 'PAIDBY_ATM')) {
                            if (abs($payment->d_payment->getTimeStamp() - $invoice['d_issued']->getTimeStamp()) <= self::MAX_AUTO_PAIR_TIMESTAMP_DIFFERENCE) {
                                // payment and invoice has opposite sign
                                if (abs($payment->czk_amount + $invoice['czk_amount']) <= self::MAX_AUTO_PAIR_CZK_DIFFERENCE) {
                                    try {
                                        $this->database->beginTransaction();

                                        $paymentData = array(
                                            'is_identified' => true,
                                            'type_paidby' => $invoice['type_paidby'],
                                            'is_consumption' => $invoice['type_paidby'] != 'PAIDBY_ATM',
                                            'invoice_head_id' => $invoice['id'],
                                        );
                                        $payment->update($paymentData);

                                        $this->database->commit();
                                        $pairedCount ++;
                                        break;
                                    } catch (\PDOException) {
                                        $this->database->rollBack();
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }

        } catch (\PDOException) {
            // this function is only used automatically. in case of bug, this automatic process will do nothing
        }

        return $pairedCount;
    }

    /* Throws no errors; if something is paired - flashmessage */
    public function pairMain(BasePresenter $presenter): void
    {
        $pairedByChannel = $this->pairByPaymentChannel();

        if ($pairedByChannel > 0) {
            $presenter->flashMessage('Celkem '.$pairedByChannel.' plateb bylo identifikováno podle trvalého příkazu.', 'autopair');
        }

        $autoPaired = $this->autoPair();

        if ($autoPaired > 0) {
            $presenter->flashMessage('Celkem '.$autoPaired.' plateb a dokladů bylo automaticky spárováno.', 'autopair');
        }
    }

    public function manualPair(int $invoiceId, array $paymentsId, bool $confirmed): void
    {
        foreach($paymentsId as $i => $paymentId) {
            $paymentsId[$i] = (int) $paymentId;
        }

        try {
            $this->database->beginTransaction();

            $invoice = $this->table('invoice_head')->get($invoiceId);
            if (!$invoice) {
                throw new AccessUserException('Uživatel nemá přístup k tomuto dokladu.');
            }

            foreach ($paymentsId as $paymentId) {
                $payment = $this->tablePayments()->get($paymentId);
                if (!$payment) {
                    throw new AccessUserException('Uživatel nemá přístup k této platbě.');
                }
                $updateData = array(
                    'invoice_head_id' => $invoiceId,
                    'is_consumption' => $invoice->type_paidby != 'PAIDBY_ATM',
                    'is_identified' => true,
                );

                if ($invoice->type_paidby == 'PAIDBY_ATM') {
                    $cashAccount = $this->table('cash_account')->fetch();
                    $updateData['cash_account_id'] = $cashAccount;
                    if ($payment->type_paidby == 'PAIDBY_CARD') {
                        $updateData['type_paidby'] = 'PAIDBY_ATM';
                    }
                }

                if ($payment->type_paidby === null) {
                    $updateData['type_paidby'] = $invoice->type_paidby;
                    if ($invoice->type_paidby == 'PAIDBY_CARD') {
                        $updateData['card_id'] = $invoice->card_id;
                    }
                }

                $payment->update($updateData);
            }

            if (!$confirmed) {
                $invoiceAmount = $invoice->related('invoice_item')->sum('czk_amount');
                $paymentsAmount = - $this->tablePayments()->where('id', $paymentsId)->sum('czk_amount');
                $difference = abs($invoiceAmount - $paymentsAmount);

                if ($difference > self::MAX_MANUAL_PAIR_CZK_DIFFERENCE) {
                    if (count($paymentsId) == 0) {
                        throw new ManualPairDifferenceWarning('Celková částka vybraných plateb a dokladu se liší o: '.$difference.' Kč.');
                    } else {
                        throw new ManualPairDifferenceWarning('Celková částka vybrané platby a dokladu se liší o: '.$difference.' Kč.');
                    }
                }
            }

            $this->database->commit();
        } catch (\PDOException) {
            $this->database->rollBack();
            throw new \PDOException('Spárování dokladů se nepodařilo uložit do databáze.');
        } catch (AccessUserException|ManualPairDifferenceWarning $exception) {
            $this->database->rollBack();
            throw $exception;
        }
    }

    public function getNotIdentifiedPayments(): Selection
    {
        return $this->tablePayments()->where('NOT is_identified');
    }

    public function getNotIdentifiedInvoices(): Selection
    {
        return $this->table('invoice_head')->where('NOT invoice_head.type_paidby', 'PAIDBY_FEE')->where(':payment.invoice_head_id', null);
    }

    public function notConsumption(int $id): void
    {
        $payment = $this->tablePayments()->get($id);
        if (!$payment) {
            throw new AccessUserException('Uživatel nemá přístup k této platbě.');
        }

        $payment->update(['is_consumption' => false, 'is_identified' => true]);
    }

    public function cashConsumption(int $id): void
    {
        $cashAccountId = $this->table('cash_account')->fetch()->id;
        $payment = $this->table('payment')->where('cash_account_id', $cashAccountId)->where('type_paidby', 'PAIDBY_CASH')->get($id);
        if (!$payment) {
            throw new AccessUserException('Uživatel nemá přístup k této platbě.');
        }

        $payment->update(['is_identified' => true]);
    }

    public function cashNotConsumption(int $id): void
    {
        $cashAccountId = $this->table('cash_account')->fetch()->id;
        $payment = $this->table('payment')->where('cash_account_id', $cashAccountId)->where('type_paidby', 'PAIDBY_CASH')->get($id);
        if (!$payment) {
            throw new AccessUserException('Uživatel nemá přístup k této platbě.');
        }

        $payment->update(['is_consumption' => false, 'is_identified' => true]);
    }
}

class ManualPairDifferenceWarning extends Exception
{

}