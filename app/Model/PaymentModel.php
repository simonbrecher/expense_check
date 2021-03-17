<?php

declare(strict_types=1);
namespace App\Model;


use App\Presenters\AccessUserException;
use Nette\Utils\ArrayHash;
use Nette\Utils\DateTime;
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
        array(
            'sscanf' => 'Koncový stav účtu k %[0123456789.]: %[0123456789,] CZK',
            'variables' => [null, 'balance_start'],
        ),
        array(
            'sscanf' => 'Počáteční stav účtu k %[0123456789.]: %[0123456789,] CZK',
            'variables' => [null, 'balance_end'],
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
        11 => 'message_recipient', # TODO: check
        12 => 'message_payer', # TODO: check
        13 => 'payment_type',
        16 => 'description', # TODO: check
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
        'PAIDBY_ATM' => ['Vklad v hotovosti', 'Výběr z hotovosti'], # not ATM, but bank counter
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
                'bank_operation_id' => $oldPayment['bank_operation_id'],
                'd_payment' => new DateTime($oldPayment['d_payment']),
                'czk_amount' => (int) round((float) $oldPayment['czk_amount']),
                'counter_account_number' => $oldPayment['counter_account_number'],
                'counter_account_bank_code' => $counterAccountBankCode,
                'counter_account_name' => substr($oldPayment['counter_account_name'], 0, self::MAX_BANK_ACCOUNT_NAME_LENGTH),
                'var_symbol' => $oldPayment['var_symbol'],
                'message_recipient' => substr($oldPayment['message_recipient'], 0, self::MAX_DESCRIPTION_LENGTH),
                'message_payer' => substr($oldPayment['message_payer'], 0, self::MAX_DESCRIPTION_LENGTH),
                'description' => substr($oldPayment['description'], 0, self::MAX_DESCRIPTION_LENGTH),
            );

            $newPaymentType = null;
            foreach (self::PAYMENT_TYPES as $id => $patterns) {
                if ($this->matchOne($patterns, $oldPayment['payment_type'])) {
                    $newPaymentType = $id;
                    break;
                }
            }
            $payment['payment_type'] = $newPaymentType;

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
            throw new AccessUserException('Uživatel nemá přístuk k bankovnímu účtu: '.$oldHead['bank_account_number'].'/'.$oldHead['bank_code']);
        }
        $head['bank_account_id'] = $bankAccount->id;

        $oldPayments = $values['payments'];
        $payments = [];

        #REMOVED: var_symbol
        #ADDED: card_id, var_symbol, user_id, cash_account_id
        foreach ($oldPayments as $oldPayment) {
            $payment = array(
                'user_id' => $this->user->identity->id,

                'bank_operation_id' => $oldPayment['bank_operation_id'],
                'd_payment' => $oldPayment['d_payment'],
                'czk_amount' => $oldPayment['czk_amount'],
                'counter_account_number' => $oldPayment['counter_account_number'],
                'counter_account_bank_code' => $oldPayment['counter_account_bank_code'],
                'counter_account_name' => $oldPayment['counter_account_name'],
                'message_recipient' => $oldPayment['message_recipient'],
                'message_payer' => $oldPayment['message_payer'],
                'description' => $oldPayment['description'],
                'payment_type' => $oldPayment['payment_type'],
            );

            if ($payment['payment_type'] == 'PAIDBY_CARD') {
                $varSymbol = str_repeat('0', 4 - strlen($oldPayment['var_symbol'])).$oldPayment['var_symbol'];
                $userCards = $this->table('card')->where('bank_account_id', $head['bank_account_id']);
                $card = $userCards->where('number', $varSymbol)->fetch();
                if (!$card) {
                    if (strlen($varSymbol) == 4) {
                        throw new AccessUserException('Na bankovním účtu není karta s koncem: **'.$varSymbol);
                    } else {
                        throw new InvalidFileValueException('Platba placená kartou má špatnou délku variabilního symbolu: '.$varSymbol.' správná délka je 4.');
                    }
                }
                $payment['card_id'] = $card->id;
            } else {
                $payment['var_symbol'] = $oldPayment['var_symbol'];
            }

            if ($payment['payment_type'] == 'PAIDBY_ATM') {
                $cashAccountId = $this->database->table('cash_account')->where('user_id', $this->user->identity->id)->fetch()->id;
                $payment['cash_account_id'] = $cashAccountId;
            }

            $payments[] = $payment;
        }

        return ['head' => $head, 'payments' => $payments];
    }

    public function import(ArrayHash $values): void
    {
        $values = $this->loadImportData($values);

        Debugger::barDump($values['head']);
        Debugger::barDump($values['payments']);

        $this->validateImportDataPattern($values);

        $values = $this->constructImportData($values);

        Debugger::barDump($values['head']);
        Debugger::barDump($values['payments']);

        $values = $this->constructImportDatabaseData($values);

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