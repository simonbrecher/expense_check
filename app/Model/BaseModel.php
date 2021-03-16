<?php

declare(strict_types=1);
namespace App\Model;

use Nette\Neon\Exception;
use Nette;

class BaseModel
{
    public const DATE_PATTERN_FLEXIBLE = '(0?[1-9]|[12][0-9]|3[01])\. ?(0?[1-9]|1[0-2])(\. ?((20)?[0-9]{2})?)?';
    public const DATE_PATTERN_DD_MM = '(0?[1-9]|[12][0-9]|3[01])\. ?(0?[1-9]|1[0-2])\.?';
    public const DATE_PATTERN_DD_MM_YY = '(0?[1-9]|[12][0-9]|3[01])\. ?(0?[1-9]|1[0-2])\. ?[0-9]{2}';
    public const DATE_PATTERN_DD_MM_YYYY = '(0?[1-9]|[12][0-9]|3[01])\. ?(0?[1-9]|1[0-2])\. ?20[0-9]{2}';

    public const TABLES_WITH_FAMILY_ID = ['category', 'user'];
    public const TABLES_WITH_USER_ID = ['bank_account', 'card', 'cash_account', 'invoice_head', 'payment', 'payment_channel'];

    private const ROLE_ISACTIVE = [1 => 'ANO', 0 => 'NE'];

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
            return $this->database->table($tableName)->where('family_id', $this->user->identity->family_id);
        } elseif (in_array($tableName, self::TABLES_WITH_USER_ID)) {
            return $this->database->table($tableName)->where('user_id', $this->user->id);
        } else {
            throw new Exception('BaseModel->table() - Unknown table: '.$tableName);
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