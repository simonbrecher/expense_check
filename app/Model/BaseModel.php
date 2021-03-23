<?php

declare(strict_types=1);
namespace App\Model;


use Nette\Database\Table\Selection;
use Nette\Neon\Exception;
use Nette;
use Nette\Utils\DateTime;

class BaseModel
{
    public const DATE_PATTERN_FLEXIBLE = '(0?[1-9]|[12][0-9]|3[01])\. ?(0?[1-9]|1[0-2])(\. ?((20)?[0-9]{2})?)?';
    public const DATE_PATTERN_DD_MM = '(0?[1-9]|[12][0-9]|3[01])\. ?(0?[1-9]|1[0-2])\.?';
    public const DATE_PATTERN_DD_MM_YY = '(0?[1-9]|[12][0-9]|3[01])\. ?(0?[1-9]|1[0-2])\. ?[0-9]{2}';
    public const DATE_PATTERN_DD_MM_YYYY = '(0?[1-9]|[12][0-9]|3[01])\. ?(0?[1-9]|1[0-2])\. ?20[0-9]{2}';

    public const TABLES_WITH_FAMILY_ID = ['category', 'user'];
    public const TABLES_WITH_USER_ID = ['bank_account', 'card', 'cash_account', 'invoice_head', 'payment', 'payment_channel'];

    public const PAIDBY_TYPES = array(
        'PAIDBY_CASH' => 'V hotovosti',
        'PAIDBY_CARD' => 'Kartou',
        'PAIDBY_BANK' => 'Převodem',
        'PAIDBY_ATM' => 'Výběr/vklad hotovosti',
        'PAIDBY_FEE' => 'Poplatek',
        null => 'Neznámý',
    );
    public const PAIDBY_TYPES_INVOICE_FORM = array(
        'PAIDBY_CASH' => 'V hotovosti',
        'PAIDBY_CARD' => 'Kartou',
        'PAIDBY_BANK' => 'Převodem',
        'PAIDBY_ATM' => 'Výběr/vklad hotovosti',
    );
    public const PAIDBY_TYPES_TABLE = array(
        'PAIDBY_CASH' => 'V hotovosti',
        'PAIDBY_CARD' => 'Kartou',
        'PAIDBY_BANK' => 'Převodem',
        'PAIDBY_ATM' => 'Výběr/vklad',
        'PAIDBY_FEE' => 'Poplatek',
        null => 'Neznámý',
    );

    private const ROLE_ISACTIVE = [1 => 'Aktivní', 0 => 'Neaktivní'];

    protected const MAX_BANK_ACCOUNT_NAME_LENGTH = 25;
    protected const MAX_DESCRIPTION_LENGTH = 35;

    public function __construct(
        protected Nette\Database\Explorer $database,
        protected Nette\Security\User $user
    )
    {}

    protected function table(string $tableName): Nette\Database\Table\Selection
    {
        if (in_array($tableName, self::TABLES_WITH_FAMILY_ID)) {
            return $this->database->table($tableName)->where($tableName.'.family_id', $this->user->identity->family_id);
        } elseif (in_array($tableName, self::TABLES_WITH_USER_ID)) {
            return $this->database->table($tableName)->where($tableName.'.user_id', $this->user->id);
        } else {
            throw new Exception('BaseModel->table() - Unknown table: '.$tableName);
        }
    }

    /* Same as BaseModel->table, does payments by bank accounts, not by user */
    protected function tablePayments(): Selection
    {
        $bankAccounts = $this->table('bank_account');
        return $this->database->table('payment')->where('bank_account_id', $bankAccounts);
    }

    public function getFirstDayInMonth(int $month, int $year): DateTime
    {
        return new DateTime('1.'.$month.'.'.$year);
    }

    public function getLastDayInMonth(int $month, int $year): DateTime
    {
        if ($month == 12) {
            return new DateTime('1.1.'.($year + 1).' - 1 day');
        } else {
            return new DateTime('1.'.($month + 1).'.'.$year.' - 1 day');
        }
    }

    public function normalizeDateFormat(string $date): string
    {
        $date = str_replace(' ', '', $date);
        if (preg_match('~^'.self::DATE_PATTERN_DD_MM_YYYY.'$~', $date)) {
            null;
        } elseif (preg_match('~^'.self::DATE_PATTERN_DD_MM_YY.'$~', $date)) {
            $date = substr($date, 0, (strlen($date) - 2)).'20'.substr($date, -2);
        } elseif (preg_match('~^'.self::DATE_PATTERN_DD_MM.'$~', $date)) {
            if ($date[-1] != '.') {
                $date .= '.';
            }
            $date .= date('Y');
        } else {
            throw new Exception('Formát data neumožňuje uložení do databáze.');
        }

        return (new \DateTime($date))->format('Y-m-d');
    }

    public function getIsActiveSelect(): array
    {
        return self::ROLE_ISACTIVE;
    }

    public function getIsActiveLabel(int|bool $isActive): string
    {
        return self::ROLE_ISACTIVE[$isActive];
    }
}