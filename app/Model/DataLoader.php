<?php

declare(strict_types=1);
namespace App\Model;

use Nette;
use Exception;

use Tracy\Debugger;

/**
 * DataLoader class for database version 01.05.
 *
 * FOR ONE FAMILY VERSION ONLY
 */
class DataLoader
{
    /** @var Nette\Database\Context */
    private $database;
    /** @var Nette\Security\User */
    private $user;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }

    /** I don't know how to use getUser() by framework. */
    public function setUser(Nette\Security\User $user)
    {
        $this->user = $user;
    }

    /**
     * Get table from database.
     * Shorter than $this->database->table
     * No difference yet.
     *
     * FOR ONE FAMILY VERSION ONLY
     */
    public function table(string $table): Nette\Database\Table\Selection
    {
        return $this->database->table($table);
    }

    /**
     * Get all data from table for current user.
     * Used for viewing data for a user, for example all invoice_head for a user,
     * or for forms' data.
     *
     * Supported tables:
     * category, consumer (whole table)
     * payment_channel, bank_account, cash_account, card, invoice_head, payment (by user_id)
     * invoice_item (by invoice_head_id)
     *
     * FOR ONE FAMILY VERSION ONLY
     */
    public function userTable(string $table): Nette\Database\Table\Selection
    {
        $wholeTable = ['category', 'comsumer'];
        $byUserId = ['payment_channel', 'bank_account', 'card', 'invoice_head', 'payment'];
        $byInvoiceHeadId = ['invoice_item'];

        if (in_array($table, $wholeTable)) {
            return $this->table($table);
        } elseif (in_array($table, $byUserId)) {
            return $this->table($table)->where('user_id', $this->user->id);
        } elseif (in_array($table, $byInvoiceHeadId)) {
            $invoiceHeadsSelection = $this->userTable('invoice_head');
            $invoiceHeadsIdArray = Convertor::columnToArray($invoiceHeadsSelection, 'id');
            return $this->table($table)->where('invoice_head_id', $invoiceHeadsIdArray);
        } else {
            throw new Exception('Tryed to access unknown table by DataLoader->userTable: '.$table);
        }
    }

    /** If id in table exists. For whole table. */
    private function idExists(string $table, int $id): bool
    {
        return $this->database->table($table)->offsetExists($id);
    }

    /**
     * If user can access id in table.
     * Used for checking form data.
     *
     * Throw error if the id does not exist.
     *
     * Supported tables:
     * category, consumer (always true)
     * bank_account, card, cash_account, invoice_head, payment, payment_channel (by user_id)
     * invoice_item (by invoice_head_id)
     * cash_account_balance (by cash_account_id)
     *
     * FOR ONE FAMILY ONLY
     */
    public function canAccess(string $table, int $id): bool
    {
        $alwaysTrueTables = ['category', 'consumer'];
        $byUserId = ['bank_account', 'card', 'cash_account', 'invoice_head', 'payment', 'payment_channel'];
        // the table to which an important foreign key is pointing
        $byOtherId = ['invoice_item' => 'invoice_head', 'cash_account_balance' => 'cash_account'];
        $supportedTables = array_merge($alwaysTrueTables, $byUserId, array_keys($byOtherId));

        if (!in_array($table, $supportedTables)) {
            throw new Exception( 'Asked if user can access non-supported table by method '.
                'DataLoader->canAccess: '.$table);
        } elseif (!$this->idExists($table, $id)) {
            throw new Exception('Asked if user can access non-existing id: '.$table.", ".$id);
        } elseif (in_array($table, $alwaysTrueTables)) {
            return true;
        } elseif (in_array($table, $byUserId)) {
            return $this->table($table)->get($id)->user_id == $this->user->id;
        } elseif (in_array($table, array_keys($byOtherId))) {
            $foreignKey = $byOtherId[$table].'_id';
            // value of id for next recursion (id in new table, where the foreign key points)
            $newId = $this->table($table)->get($id)->$foreignKey;
            // name of table for new recursion (where the foreign key points)
            $newTable = $byOtherId[$table];
            // whether can access this table/id iff can access table/id where the foreign key is pointing
            return $this->canAccess($newTable, $newId);
        } else {
            throw new Exception('Unexpected error in DataLoader->canAccess. Table: '.$table);
        }
    }

    // TODO: check if the column exists
    /** Get data to view by table and id. Not for null case. */
    public function getForViewById(string $table, string $column, int $id): string
    {
        return $this->table($table)->get($id)->$column;
    }

    // TODO: check if the column exists
    /**
     * Get parameters for select control in form.
     * @param mixed|null $nullValue - null if id can not be null, else $table->$columnValue for $table->id === null
     * @return array($table->id => $table->$column, ...)
     * iff $table->id === null, array key is 0, because null can not be an array index.
     */
    public function getFormSelectDict(string $table, string $column, $nullValue=null): array
    {
        $array = [];
        if ($nullValue !== null) {
            // id === null, but null can not be an array index
            $array[0] = $nullValue;
        }

        foreach ($this->userTable($table) as $row) {
            $array[$row->id] = $row->$column;
        }

        return $array;
    }

    public function test()
    {
        $table = $this->userTable('card');
//        Convertor::ds($table);

//        $result = $this->canAccess('invoice_item', 2);
//        Debugger::barDump($result);
//
//        $result = $this->getForViewById('bank_account', 'number', 2);
//        Debugger::barDump($result);

        Debugger::barDump($this->getFormSelectDict('category', 'name', 'Nezařazeno'));
    }
}