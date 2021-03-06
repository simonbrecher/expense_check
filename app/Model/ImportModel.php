<?php

declare(strict_types=1);
namespace App\Model;


use App\Presenters\AccessUserException;
use App\Utils\ImportIntervals;
use Nette\Database\Table\Selection;
use Nette\Utils\ArrayHash;
use Nette\Utils\DateTime;

class ImportModel extends BaseModel
{
    private const HEAD_SCHEMA = array(
        array(
            'sscanf' => 'Výpis č. %[0123456789/] z účtu %[0123456789-/]',
            'variables' => [null, 'bank_account_number'],
        ),
        array(
            'sscanf' => '﻿Výpis č. %[0123456789/] z účtu %[0123456789-/]', // I have no idea, why this had to be here.
            'variables' => [null, 'bank_account_number'],
        ),
        array(
            'sscanf' => 'Počáteční stav účtu k %[0123456789.]: %[0123456789,] CZK',
            'variables' => ['d_statement_start', 'balance_start'],
        ),
        array(
            'sscanf' => 'Koncový stav účtu k %[0123456789.]: %[0123456789,] CZK',
            'variables' => ['d_statement_end', 'balance_end'],
        ),
    );

    private const LINES_SCHEMA = array(
        0 => 'bank_operation_id',
        1 => 'd_payment',
        2 => 'czk_amount',
        3 => 'currency',
        4 => 'counter_account_number',
        6 => 'counter_account_bank_code',
        5 => 'counter_account_name',
        9 => 'var_symbol',
        11 => 'message_recipient',
        12 => 'message_payer',
        13 => 'type_paidby',
        16 => 'description',
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
        16 => 'Poznámka',
    );

    private const HEAD_LINE_DATE_PATTERN = '(3[01]|[012]?[0-9])\.(1[0-2]|0?[0-9])\.20([0-9]{2})';
    private const HEAD_LINE_AMOUNT_PATTERN = '-?[0-9]+[,.]?[0-9]*';

    /* Only for error messages */
    private const HEAD_FIELDS_TITLES = array(
        'bank_account_number' => 'Číslo bankovního účtu',
        'balance_start' => 'Počáteční stav bankovního účtu',
        'balance_end' => 'Koncový stav bankovního účtu',
        'd_statement_start' => 'Datum začátku bankovního výpisu',
        'd_statement_end' => 'Datum konce bankovního výpisu',
    );

    private const HEAD_FIELDS_PATTERN = array(
        'bank_account_number' => '([0-9]{1,6}-)?[0-9]{2,10}/[0-9]+',
        'balance_start' => self::HEAD_LINE_AMOUNT_PATTERN,
        'balance_end' => self::HEAD_LINE_AMOUNT_PATTERN,
        'd_statement_start' => self::HEAD_LINE_DATE_PATTERN,
        'd_statement_end' => self::HEAD_LINE_DATE_PATTERN,
    );

    private const LINE_FIELDS_PATTERN = array(
        'bank_operation_id' => '[0-9]+',
        'd_payment' => self::HEAD_LINE_DATE_PATTERN,
        'czk_amount' => self::HEAD_LINE_AMOUNT_PATTERN,
        'currency' => '.*', # unsupported currency throws different error
        'counter_account_number' => '(([0-9]{1,6}-)?[0-9]{2,10})?',
        'counter_account_bank_code' => '[0-9]{0,4}',
        'var_symbol' => '[0-9]{0,10}',
    );

    # PAIDBY_CASH is not here - it is only automatically generated
    # null iff unknown
    private const PAYMENT_TYPES = array(
        'PAIDBY_CARD' => ['Karetní transakce'], # card or ATM - we don't know
        'PAIDBY_BANK' => ['Bezhotovostní platba', 'Okamžitá odchozí platba', 'Bezhotovostní příjem', 'Platba převodem uvnitř banky', 'Inkaso'],
        'PAIDBY_ATM' => ['Vklad v hotovosti', 'Výběr v hotovosti'], # not ATM, but bank counter
        'PAIDBY_FEE' => ['Poplatek.*'],
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

    /* transaction MUST BE outside of this function */
    private function autoCreateInvoices(Selection $payments): void
    {
        // WARNING: if to add new type_paidby - check if the database object can be used in two for cycles, or has to be copied
        foreach ($payments->where('type_paidby', 'PAIDBY_FEE') as $payment) {
            $head = array(
                'user_id' => $this->user->id,
                'd_issued' => $payment->d_payment,
                'type_paidby' => 'PAIDBY_FEE',
            );
            $head = $this->database->table('invoice_head')->insert($head);
            $payment->update(['invoice_head_id' => $head->id, 'is_identified' => true]);

            $item = array(
                'is_main' => true,
                'czk_amount' => - $payment->czk_amount,
                'description' => 'poplatek',
            );
            $head->related('invoice_item')->insert($item);
        }
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

        $separator = ';';
        foreach ($fileData as $line) {
            if ($line != '') {
                if ($line[-1] == ',') {
                    $separator = ',';
                    break;
                } elseif ($line[-1] == ';') {
                    $separator = ';';
                    break;
                }
            }
        }

        foreach (self::HEAD_SCHEMA as $format) {
            for ($i = 0; $i < count($fileData); $i++) {
                $field = str_getcsv($fileData[$i], $separator)[0];
                if ($field !== null) {
                    $field = str_replace('"', '', $field);
                }
                if ($field != '' && $field !== null) {
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
            $exploded = str_getcsv($fileData[$i], $separator);
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
            $line = str_getcsv($fileData[$start], $separator);
            foreach (self::LINES_TITLE_SCHEMA as $i => $title) {
                if ($line[$i] != $title) {
                    throw new InvalidFileFormatException('Neplatný formát výpisu z bankovního účtu.');
                }
            }
        }

        $payments = [];
        for ($i = $start + 1; $i < count($fileData); $i++) {
            $line = str_getcsv($fileData[$i], $separator);
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

        foreach (self::HEAD_FIELDS_PATTERN as $name => $pattern) {
            if (!$this->match($pattern, $head[$name])) {
                throw new InvalidFileValueException('Nesprávný formát pro: '.self::HEAD_FIELDS_TITLES[$name].' - hodnota: '.$head[$name]);
            }
        }

        $startDate = strtotime($head['d_statement_start']);
        $endDate = strtotime($head['d_statement_end']);

        if (!$this->checkDate($head['d_statement_start'])) {
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
                if ($this->matchOne($patterns, $payment['type_paidby'])) {
                    $newPaymentType = $id;
                    break;
                }
            }

            /* UNCOMMENT TO THROW ERROR FOR UNKNOWN PAYMENT TYPE */
//            if ($newPaymentType === null) {
//                throw new InvalidFileValueException('Neznámý typ platby: '.$payment['type_paidby']);
//            }

            $payments[$i]['type_paidby'] = $newPaymentType;
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

    private function constructImportData(array $values): array
    {
        $head = $values['head'];

        list($bankAccountNumber, $bankCode) = explode('/', $head['bank_account_number']);
        $head = array(
            'bank_account_number' => $bankAccountNumber,
            'bank_code' => $bankCode,
            'balance_start' => (int) round((float) $head['balance_start']),
            'balance_end' => (int) round((float) $head['balance_end']),
            'd_statement_start' => new DateTime($head['d_statement_start']),
            'd_statement_end' => new DateTime($head['d_statement_end']),
        );

        $payments = $values['payments'];

        #REMOVED: currency
        foreach ($payments as $i => $oldPayment) {
            $counterAccountBankCode = $oldPayment['counter_account_bank_code'];
            if ($counterAccountBankCode != '') {
                $counterAccountBankCode = str_repeat('0', 4 - strlen($counterAccountBankCode)).$counterAccountBankCode;
            }
            $payment = array(
                'bank_operation_id' => (int) $oldPayment['bank_operation_id'],
                'd_payment' => new DateTime($oldPayment['d_payment']),
                'czk_amount' => (int) round((float) $oldPayment['czk_amount']),
                'counter_account_number' => $oldPayment['counter_account_number'],
                'counter_account_bank_code' => $counterAccountBankCode,
                'counter_account_name' => mb_substr($oldPayment['counter_account_name'], 0, self::MAX_BANK_ACCOUNT_NAME_LENGTH, 'utf-8'),
                'var_symbol' => $oldPayment['var_symbol'],
                'message_recipient' => mb_substr($oldPayment['message_recipient'], 0, self::MAX_DESCRIPTION_LENGTH, 'utf-8'),
                'message_payer' => mb_substr($oldPayment['message_payer'], 0, self::MAX_DESCRIPTION_LENGTH, 'utf-8'),
                'description' => mb_substr($oldPayment['description'], 0, self::MAX_DESCRIPTION_LENGTH, 'utf-8'),
            );

            $newPaymentType = null;
            foreach (self::PAYMENT_TYPES as $id => $patterns) {
                if ($this->matchOne($patterns, $oldPayment['type_paidby'])) {
                    $newPaymentType = $id;
                    break;
                }
            }
            $payment['type_paidby'] = $newPaymentType;

            $payments[$i] = $payment;
        }

        return ['head' => $head, 'payments' => $payments];
    }

    /* Construct foreign keys and validate user access rights. */
    private function constructImportDatabaseData(array $values): array
    {
        #REMOVED: balance_account_number, balance_account_code
        #ADDED: bank_account_id
        $oldHead = $values['head'];
        $head = array(
            'balance_start' => $oldHead['balance_start'],
            'balance_end' => $oldHead['balance_end'],
            'd_statement_start' => $oldHead['d_statement_start'],
            'd_statement_end' => $oldHead['d_statement_end'],
        );

        $userBankAccounts = $this->table('bank_account');
        $bankAccount = $userBankAccounts->where('number', $oldHead['bank_account_number'])->where('bank.bank_code', $oldHead['bank_code'])->fetch();
        if (!$bankAccount) {
            throw new AccessUserException('Uživatel nemá přístup k bankovnímu účtu: '.$oldHead['bank_account_number'].'/'.$oldHead['bank_code']);
        }
        $head['bank_account_id'] = $bankAccount->id;

        $oldPayments = $values['payments'];
        $payments = [];

        # to reduce number of SQL requests
        $cardsFound = [];
        #REMOVED: var_symbol
        #ADDED: card_id, var_symbol, user_id, cash_account_id, bank_account_id
        foreach ($oldPayments as $oldPayment) {
            $payment = array(
                'user_id' => $this->user->id,
                'bank_account_id' => $head['bank_account_id'],
                'card_id' => null,
                'cash_account_id' => null,

                'bank_operation_id' => $oldPayment['bank_operation_id'],
                'd_payment' => $oldPayment['d_payment'],
                'czk_amount' => $oldPayment['czk_amount'],
                'var_symbol' => $oldPayment['var_symbol'],
                'counter_account_number' => $oldPayment['counter_account_number'],
                'counter_account_bank_code' => $oldPayment['counter_account_bank_code'],
                'counter_account_name' => $oldPayment['counter_account_name'],
                'message_recipient' => $oldPayment['message_recipient'],
                'message_payer' => $oldPayment['message_payer'],
                'description' => $oldPayment['description'],
                'type_paidby' => $oldPayment['type_paidby'],
            );

            if ($payment['type_paidby'] == 'PAIDBY_CARD') {
                $varSymbol = str_repeat('0', 4 - strlen($oldPayment['var_symbol'])).$oldPayment['var_symbol'];
                $userCards = $this->table('card')->where('bank_account_id', $head['bank_account_id']);

                # to reduce number of SQL requests
                if (array_key_exists($varSymbol, $cardsFound)) {
                    $cardId = $cardsFound[$varSymbol];

                } else {
                    $card = $userCards->where('number', $varSymbol)->fetch();

                    if (!$card) {
                        if (strlen($varSymbol) == 4) {
                            throw new AccessUserException('Na bankovním účtu není karta s koncem: **'.$varSymbol);
                        } else {
                            throw new InvalidFileValueException('Platba placená kartou má špatnou délku variabilního symbolu: '.$varSymbol.' správná délka je 4.');
                        }
                    }

                    $cardId = $card->id;
                    $cardsFound[$varSymbol] = $cardId;
                }

                $payment['card_id'] = $cardId;
            }

            if ($payment['type_paidby'] == 'PAIDBY_ATM') {
                $cashAccountId = $this->table('cash_account')->fetch()->id;
                $payment['cash_account_id'] = $cashAccountId;
            }

            $payments[] = $payment;
        }

        return ['head' => $head, 'payments' => $payments];
    }

    private function isImportDuplicate(array $head): bool
    {
        $headDateInterval = ['start' => $head['d_statement_start'], 'end' => $head['d_statement_end']];
        $headBalanceInterval = ['start' => $head['balance_start'], 'end' => $head['balance_end']];
        $headImportInterval = ['date' => $headDateInterval, 'balance' => $headBalanceInterval];

        $alreadyDateIntervalsSelection = $this->database->table('ba_import')->where('bank_account_id', $head['bank_account_id']);
        $alreadyDateIntervals = [];
        foreach ($alreadyDateIntervalsSelection as $row) {
            $dateInterval = ['start' => $row['d_statement_start'], 'end' => $row['d_statement_end']];
            $balanceInterval = ['start' => $row['balance_start'], 'end' => $row['balance_end']];
            $alreadyDateIntervals[] = ['date' => $dateInterval, 'balance' => $balanceInterval];
        }

        $isImportDuplicate = ImportIntervals::isImportIntervalDuplicate($headImportInterval, $alreadyDateIntervals);

        return $isImportDuplicate;
    }

    private function removeDuplicates(array $values): array
    {
        $head = $values['head'];
        $oldPayments = $values['payments'];

        $paymentsTable = $this->database->table('payment')->where('bank_account_id', $head['bank_account_id']);
        $alreadyBankOperationIds = [];
        foreach($paymentsTable as $row) {
            $alreadyBankOperationIds[$row->bank_operation_id] = null;
        }

        $payments = [];
        $countDuplicate = 0;
        foreach ($oldPayments as $payment) {
            if (!array_key_exists($payment['bank_operation_id'], $alreadyBankOperationIds)) {
                $payments[] = $payment;
            } else {
                $countDuplicate ++;
            }
        }

        $info = array(
            'countToSave' => count($payments),
            'countDuplicate' => $countDuplicate,
            'isImportDuplicate' => false,
        );

        if ($info['countToSave'] == 0) {
            $info['isImportDuplicate'] = $this->isImportDuplicate($head);
        }

        return ['head' => $head, 'payments' => $payments, 'info' => $info];
    }

    private function saveImport(array $values): void
    {
        $database = $this->database;

        $head = $values['head'];
        $payments = $values['payments'];
        $info = $values['info'];

        try {
            $database->beginTransaction();

            $import = $database->table('ba_import')->insert($head);

            if ($info['countToSave'] > 0) {
                $import->related('payment')->insert($payments);
            }

            $payments = $this->database->table('payment')->where('ba_import_id', $import->id);
            $this->autoCreateInvoices($payments);

            $database->commit();
        } catch (\PDOException) {
            $database->rollBack();

            throw new \PDOException('Výpis se nepodařilo uložit do databáze.');
        }
    }

    /* return array('countSaved', 'countDuplicate') */
    public function import(ArrayHash $values): array
    {
        $values = $this->loadImportData($values);

        $this->validateImportDataPattern($values);

        $values = $this->constructImportData($values);

        $values = $this->constructImportDatabaseData($values);

        $values = $this->removeDuplicates($values);

        if ($values['info']['isImportDuplicate']) {
            throw new DuplicateImportException('Všechny položky z bankovního účtu už jste importovali. Žádné položky se neuloží několikrát.');
        }

        $this->saveImport($values);

        return ['countSaved' => $values['info']['countToSave'], 'countDuplicate' => $values['info']['countDuplicate']];
    }
}

class InvalidFileFormatException extends \Exception
{

}

class InvalidFileValueException extends \Exception
{

}

class DuplicateImportException extends \Exception
{

}