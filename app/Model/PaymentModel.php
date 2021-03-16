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

    public function constructImportData(ArrayHash $values): array
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

    public function import(ArrayHash $values): void
    {
        $output = $this->constructImportData($values);

        Debugger::barDump($output['head']);
        Debugger::barDump($output['payments']);
    }
}

class InvalidFileFormatException extends \Exception
{

}