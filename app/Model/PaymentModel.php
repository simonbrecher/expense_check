<?php

declare(strict_types=1);
namespace App\Model;


use Nette\Utils\ArrayHash;
use Tracy\Debugger;

class PaymentModel extends BaseModel
{
    private const HEAD_SCHEMA = array(
        array(
            'sscanf' => 'Výpis č. %[0123456789/] z účtu "%[0123456789-/]"',
            'variables' => [null, 'bank_account_number'],
        ),
        array(
            'sscanf' => 'Období: %s - %s',
            'variables' => ['d_statement_start', 'd_statement_end'],
        ),
    );

    private const LINES_SCHEMA = array(
        0 => 'bank_operation_id',
        1 => 'd_payment',
        2 => 'amount',
        3 => 'currency',
        4 => 'counter_account_number',
        6 => 'counter_account_bank_code',
        5 => 'counter_account_name',
        9 => 'var_symbol',
        11 => 'message_recipient', # TODO: check
        12 => 'message_payer', # TODO: check
        13 => 'payment_type',
    );

    private const LINES_TITLE_SCHEMA = array(
        0 => 'ID operace',
        1 => 'Datum',
        2 => 'Objem',
        3 => 'Měna',
        4 => 'Protiúčet',
        6 => 'Kód banky',
        5 => 'Název protiúčtu',
        9 => 'VS',
        11 => 'Poznámka',
        12 => 'Zpráva pro příjemce',
        13 => 'Typ',
    );

    private const HEAD_LINE_DATE_PATTERN = '(3[01]|[012]?[0-9])\.(1[0-2]|0?[0-9])\.20([0-9]{2})';
    private const FULL_BANK_ACCOUNT_PATTERN = '([0-9]{1,6}-)?[0-9]{2,10}/[0-9]+';

    private const LINE_FIELDS_PATTERN = array(
        'bank_operation_id' => '[0-9]+',
        'd_payment' => self::HEAD_LINE_DATE_PATTERN,
        'amount' => '-?[0-9]+[,.]?[0-9]*',
        'currency' => '.*', # wrong currency throws different error
        'counter_account_number' => '(([0-9]{1,6}-)?[0-9]{2,10})?',
        'counter_account_bank_code' => '[0-9]{0,4}',
        'var_symbol' => '[0-9]*',
    );

    private const PAYMENT_TYPES = array(
        'card' => ['Karetní transakce'],
        'bank' => ['Bezhotovostní platba', 'Okamžitá odchozí platba', 'Bezhotovostní příjem', 'Platba převodem uvnitř banky', 'Inkaso'],
        'cash' => ['Vklad v hotovosti', 'Výběr z hotovosti'], # NOT CASH_TYPE LIKE FOR INVOICE
        'fee' => ['Poplatek.*'],
    );

    private function match(string $pattern, string $str): bool
    {
        return (bool) preg_match('~^'.$pattern.'$~', $str);
    }

    private function matchOne(array $patterns, string $str): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->match($pattern, $str)) {
                return true;
            }
        }
        return false;
    }

    private function checkDate(string $date): bool
    {
        list($day, $month, $year) = explode('.', $date);
        return checkdate((int) $month, (int) $day, (int) $year);
    }

    private function isDateInRange(string $start, string $end, string $date): bool
    {
        $start = strtotime($start);
        $end = strtotime($end);
        $date = strtotime($date);

        return (($date >= $start) && ($date <= $end));
    }

    private function loadImportData(ArrayHash $values): array
    {
        $file = $values->import;

        $fileData = explode("\r\n", $file->getContents());

        $head = [];

        foreach (self::HEAD_SCHEMA as $format) {
            for ($i = 0; $i < count($fileData); $i++) {
                $field = str_getcsv($fileData[$i])[0];
                if ($field != '') {
                    $list = sscanf($field, $format['sscanf']);
                    if ($list[count($list) - 1] !== null) {
                        foreach ($format['variables'] as $i2 => $name) {
                            if ($name !== null) {
                                $head[$name] = $list[$i2];
                            }
                        }
                        break;
                    }
                }
            }
        }

        foreach (self::HEAD_SCHEMA as $format) {
            foreach ($format['variables'] as $name) {
                if ($name !== null) {
                    if (!array_key_exists($name, $head)) {
                        throw new InvalidFileFormatException('Neplatný formát výpisu z bankovního účtu.');
                    }
                }
            }
        }

        $start = null;
        foreach ($fileData as $i => $line) {
            $exploded = str_getcsv($fileData[$i]);
            if (count($exploded) != 0) {
                $field = $exploded[0];
                if ($field == 'ID operace') {
                    $start = $i;
                    break;
                }
            }
        }

        if ($start === null) {
            throw new InvalidFileFormatException('Neplatný formát výpisu z bankovního účtu.');
        } else {
            $line = str_getcsv($fileData[$start]);
            foreach (self::LINES_TITLE_SCHEMA as $i => $title) {
                if ($line[$i] != $title) {
                    throw new InvalidFileFormatException('Neplatný formát výpisu z bankovního účtu.');
                }
            }
        }

        $payments = [];
        for ($i = $start + 1; $i < count($fileData); $i++) {
            $line = str_getcsv($fileData[$i]);
            if (count($line) > 1) {
                $payment = [];
                foreach (self::LINES_SCHEMA as $columnId => $columnName) {
                    $payment[$columnName] = $line[$columnId];
                }
                $payments[] = $payment;
            }
        }

        return array('head' => $head, 'payments' => $payments);
    }

    private function validateImportDataPattern(array $values): void
    {
        $head = $values['head'];

        if (!$this->match(self::FULL_BANK_ACCOUNT_PATTERN, $head['bank_account_number'])) {
            throw new InvalidFileValueException('Nesprávný formát čísla bankovního účtu: '.$head['bank_account_number'].' - mělo by být ve formátu: ČÍSLO ÚČTU/ČÍSLO BANKY');
        }

        $startDate = strtotime($head['d_statement_start']);
        $endDate = strtotime($head['d_statement_end']);

        if (!$this->match(self::HEAD_LINE_DATE_PATTERN, $head['d_statement_start'])) {
            throw new InvalidFileValueException('Nesprávný formát data: '.$head['d_statement_start']);
        } elseif (!$this->match(self::HEAD_LINE_DATE_PATTERN, $head['d_statement_end'])) {
            throw new InvalidFileValueException('Nesprávný formát data: '.$head['d_statement_end']);
        } elseif (!$this->checkDate($head['d_statement_start'])) {
            throw new InvalidFileValueException('Neexistující datum: '.$head['d_statement_start']);
        } elseif (!$this->checkDate($head['d_statement_end'])) {
            throw new InvalidFileValueException('Neexistující datum: '.$head['d_statement_end']);
        } elseif ($startDate > $endDate) {
            throw new InvalidFileValueException('Datum začátku výpisu: '.$head['d_statement_start'].' je později, než datum konce výpisu: '.$head['d_statement_end']);
        } elseif ($endDate > time()) {
            throw new InvalidFileValueException('Neplatné datum konce výpisu: '.$head['d_statement_end'].' - je v budoucnosti.');
        }

        $payments = $values['payments'];

        foreach ($payments as $i => $payment) {
            $newPaymentType = null;
            foreach (self::PAYMENT_TYPES as $id => $patterns) {
                if ($this->matchOne($patterns, $payment['payment_type'])) {
                    $newPaymentType = $id;
                    break;
                }
            }

            /* UNCOMMENT TO THROW ERROR FOR UNKNOWN PAYMENT TYPE */
//            if ($newPaymentType === null) {
//                throw new InvalidFileValueException('Neznámý typ platby: '.$payment['payment_type']);
//            }

            $payments[$i]['payment_type'] = $newPaymentType;
        }

        foreach ($payments as $i => $payment) {
            foreach (self::LINE_FIELDS_PATTERN as $name => $pattern) {
                if (!$this->match($pattern, $payment[$name])) {
                    $columnTitle = self::LINES_TITLE_SCHEMA[array_search($name, self::LINES_SCHEMA)];
                    throw new InvalidFileValueException('Nesprávný formát hodnoty ve sloupci: '.$columnTitle.' - hodnota: '.$payment[$name]);
                }
            }

            if ($payment['currency'] != 'CZK') {
                throw new InvalidFileValueException('Nesprávná měna: '.$payment['currency'].' - podporujeme pouze CZK.');
            }

            $date = $payment['d_payment'];
            $startDate = $head['d_statement_start'];
            $endDate = $head['d_statement_end'];
            if (!$this->checkDate($date)) {
                throw new InvalidFileValueException('Neexistující datum: '.$date);
            }
            if (!$this->isDateInRange($startDate, $endDate, $date)) {
                throw new InvalidFileValueException('Nesprávné datum: '.$date.' - není v intervalu času výpisu (mezi: '.$startDate.' a '.$endDate.')');
            }
        }
    }



    public function import(ArrayHash $values): void
    {
        $values = $this->loadImportData($values);

        $this->validateImportDataPattern($values);

        Debugger::barDump($values['head']);
        Debugger::barDump($values['payments']);


    }
}

class InvalidFileFormatException extends \Exception
{

}

class InvalidFileValueException extends \Exception
{

}