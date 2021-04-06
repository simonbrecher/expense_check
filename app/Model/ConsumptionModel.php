<?php

declare(strict_types=1);
namespace App\Model;


use Nette\Utils\ArrayHash;
use Nette\Utils\DateTime;

class ConsumptionModel extends BaseModel
{
    public function getInitialCashAccountState(): array
    {
        $cashAccount = $this->table('cash_account')->fetch();
        $balance = $this->database->table('cash_account_balance')->where('cash_account_id', $cashAccount)->order('dt_created DESC')->fetch();
        if ($balance) {
            return [(int) $balance->czk_amount, $balance->d_balance];
        } else {
            return [0, $cashAccount->dt_created];
        }
    }

    public function getActualCashAccountAmount(int $initialBalance, DateTime $initialDate): int
    {
        $balance = $initialBalance;
        $balance -= $cashPayments = $this->table('invoice_head')->where('type_paidby', 'PAIDBY_CASH')->sum(':invoice_item.czk_amount');
        $balance += $this->table('payment')->where('payment.type_paidby', 'PAIDBY_ATM')->sum('invoice_head:invoice_item.czk_amount');

        return (int) $balance;
    }

    public function getStartBalanceDefaults(): array
    {
        list($balance, $date) = $this->getInitialCashAccountState();

        return ['czk_amount' => $balance, 'd_balance' => $date->format('j.n.y')];
    }

    public function editStartBalance(ArrayHash $values): void
    {
        $cashAccount = $this->table('cash_account')->fetch();

        $date = new DateTime($this->normalizeDateFormat($values->d_balance));

        if ($date->getTimeStamp() > date_timestamp_get(new DateTime())){
            throw new InvalidValueException('Datum počátečního stavu hotovosti musí být v minulosti.');
        }

        $data = array(
            'd_balance' => $date,
            'czk_amount' => $values->czk_amount,
        );

        try {
            $cashAccount->related('cash_account_balance')->insert($data);
        } catch (\PDOException){
            throw new \PDOException('Počáteční stav hotovosti se nepodařilo uložit do databáze');
        }
    }
}