<?php

declare(strict_types=1);
namespace App\Model;


use Nette\Utils\ArrayHash;
use Nette\Utils\DateTime;

class ConsumptionModel extends BaseModel
{
    public function canAccessCategory(int $category): bool
    {
        $row = $this->table('category')->get($category);
        return (bool) $row;
    }

    public function canAccessConsumer(int $consumer): bool
    {
        $row = $this->table('user')->get($consumer);
        return (bool) $row;
    }

    public function getTotalCzkAmount(int|null $year, int|null $month, int|null $category, int|null $consumer): int
    {
        //SHOW CONSUMPTION WRITTEN BY ONE USER
        //$payments = $this->table('payment')->where('is_identified')->where('is_consumption');

        //SHOW CONSUMPTION WRITTEN BY ALL USERS IN FAMILY
        $users = $this->database->table('user')->where('family_id', $this->user->identity->family_id);
        $payments = $this->database->table('payment')->where('user_id', $users)->where('is_identified')->where('is_consumption');

        if ($year !== null) {
            if ($month === null){
                $startDay = $this->getFirstDayInMonth(1, $year);
                $endDay = $this->getLastDayInMonth(12, $year);
            } else {
                $startDay = $this->getFirstDayInMonth($month, $year);
                $endDay = $this->getLastDayInMonth($month, $year);
            }
            $payments->where('d_payment >=', $startDay)->where('d_payment <=', $endDay);
        }

        $invoiceHeads = $this->database->table('invoice_head')->where('id', $payments->fetchPairs('id', 'invoice_head_id'));

        $invoiceItems = $this->database->table('invoice_item')->where('invoice_head_id', $invoiceHeads);

        if ($category !== null) {
            if (! $this->canAccessCategory($category)) {
                throw new Exception('Uživatel nemůže zpřistupnit tuto kategorii');
            } else {
                $invoiceItems->where('category_id', $category);
            }
        }

        if ($consumer !== null) {
            if (! $this->canAccessConsumer($consumer)) {
                throw new Exception('Uživatel nemůže zpřistupnit tohoto člena rodiny');
            } else {
                $invoiceItems->where('consumer_id', $consumer);
            }
        }

        $totalCzkAmount = $invoiceItems->sum('czk_amount');

        return $totalCzkAmount !== null ? (int) $totalCzkAmount : 0;
    }

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
        $balance -= $this->table('invoice_head')->where('type_paidby', 'PAIDBY_CASH')->where('d_issued >=', $initialDate)->sum(':invoice_item.czk_amount');
        $balance += $this->table('payment')->where('payment.type_paidby', 'PAIDBY_ATM')->where('d_issued >=', $initialDate)->sum('invoice_head:invoice_item.czk_amount');

        return (int) $balance;
    }

    public function getStartBalanceDefaults(): array
    {
        list($balance, $date) = $this->getInitialCashAccountState();

        return ['czk_amount' => $balance, 'd_balance' => $date->format('j.n.y')];
    }

    public function getCategories(): array
    {
        return $this->table('category')->fetchPairs('id', 'name');
    }

    public function getConsumers(): array
    {
        return $this->table('user')->fetchPairs('id', 'name');
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