<?php


namespace App\Model;


use App\Presenters\AccessUserException;
use Nette\Database\Table\Selection;
use Nette\Neon\Exception;

class PairModel extends BaseModel
{
    private const MAX_MANUAL_PAIR_ABSOLUTE_DIFFERENCE = 3;

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
                $payment->update(['invoice_head_id' => $invoiceId, 'is_paired' => true]);
            }

            if (!$confirmed) {
                $invoiceAmount = $invoice->related('invoice_item')->sum('czk_amount');
                $paymentsAmount = - $this->tablePayments()->where('id', $paymentsId)->sum('czk_amount');
                $difference = abs($invoiceAmount - $paymentsAmount);

                if ($difference > self::MAX_MANUAL_PAIR_ABSOLUTE_DIFFERENCE) {
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

    /* Same as BaseModel->table, does payments by bank accounts, not by user */
    public function tablePayments(): Selection
    {
        $bankAccounts = $this->table('bank_account');
        return $this->database->table('payment')->where('bank_account_id', $bankAccounts);
    }

    public function getPayments(): Selection
    {
        return $this->tablePayments()->where('NOT is_paired');
    }

    public function getInvoices(): Selection
    {
        return $this->table('invoice_head')->where(':payment.invoice_head_id', null);
    }

    public function notConsumption(int $id): void
    {
        $payment = $this->tablePayments()->get($id);
        if (!$payment) {
            throw new AccessUserException('Uživatel nemá přístup k této platbě.');
        }

        $payment->update(['is_consumption' => false, 'is_paired' => true]);
    }
}

class ManualPairDifferenceWarning extends Exception
{

}