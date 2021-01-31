<?php

declare(strict_types=1);
namespace App\Model;

use Nette;
use Exception;

use Tracy\Debugger;

/**
 * Used for converting table selection to array and "fancy" dumping it.
 */
class Convertor
{
    // TODO: control if the column exists
    /** Convert column in Nette\Database\Table\Selection to array */
    public static function columnToArray(Nette\Database\Table\Selection $table, string $column): array
    {
        $array = [];
        foreach ($table as $row) {
            $array[] = $row->$column;
        }
        return $array;
    }

    /** Convert Nette\Database\Table\Selection to 2d array */
    public static function tableToArray(Nette\Database\Table\Selection $table): array
    {
        $array = [];
        foreach ($table as $row) {
            $array[] = $row->toArray();
        }
        return $array;
    }

    /** Debugger::barDump Nette\Database\Table\Selection */
    public static function ds(Nette\Database\Table\Selection $table): void
    {
        $array = self::tableToArray($table);
        Debugger::barDump($array);
    }
}