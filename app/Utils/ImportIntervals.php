<?php

declare(strict_types=1);
namespace App\Utils;


use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;
use Tracy\Debugger;

class ImportIntervals
{
    private const DAY_TIMESTAMP_DIFFERENCE = 86400;
    private const LAST_IMPORT_TIMESTAMP_DIFFERENCE = 7 * self::DAY_TIMESTAMP_DIFFERENCE;

    /* return int for sorting */
    private static function doesFirstImportIntervalStartLater(array $a, array $b): int
    {
        return $a['date']['start']->getTimeStamp() > $b['date']['start']->getTimeStamp();
    }

    private static function doesFirstImportIntervalEndLater(array $a, array $b): bool
    {
        return $a['date']['end']->getTimeStamp() > $b['date']['end']->getTimeStamp();
    }

    private static function isFirstImportIntervalInside(array $a, array $b): bool
    {
        $firstStart = $a['date']['end']->getTimeStamp();
        $firstEnd = $a['date']['start']->getTimeStamp();
        $secondStart = $b['date']['end']->getTimeStamp();
        $secondEnd = $b['date']['start']->getTimeStamp();

        if ($firstStart < $secondStart) {
            return false;
        } elseif ($firstStart == $secondStart && $a['balance']['start'] != $b['balance']['start']) {
            return false;
        }

        if ($firstEnd > $secondEnd) {
            return false;
        } elseif ($firstEnd == $secondEnd && $a['balance']['end'] != $b['balance']['end']) {
            return false;
        }

        return true;
    }

    private static function doImportIntervalsIntersect(array $a, array $b): int
    {
        if (self::doesFirstImportIntervalStartLater($a, $b)) {
            return self::doImportIntervalsIntersect($b, $a);
        }

        $firstEnd = $a['date']['end']->getTimeStamp();
        $secondStart = $b['date']['start']->getTimeStamp();

        if ($firstEnd + self::DAY_TIMESTAMP_DIFFERENCE < $secondStart) {
            return false;
        } elseif ($firstEnd < $secondStart) {
            return $a['balance']['end'] == $b['balance']['start'];
        } else {
            return true;
        }
    }

    private static function joinImportIntervals(array $inputImportIntervals): array
    {
        if (count($inputImportIntervals) == 0) {
            return [];
        }

        usort($inputImportIntervals, 'self::doesFirstImportIntervalStartLater');

        $outputImportIntervals = [];
        foreach ($inputImportIntervals as $addInterval) {
            $length = count($outputImportIntervals);
            if ($length == 0) {
                $outputImportIntervals[] = $addInterval;
            } elseif (self::doImportIntervalsIntersect($outputImportIntervals[$length - 1], $addInterval)) {
                if (self::doesFirstImportIntervalEndLater($addInterval, $outputImportIntervals[$length - 1])) {
                    $outputImportIntervals[$length - 1]['date']['end'] = $addInterval['date']['end'];
                    $outputImportIntervals[$length - 1]['balance']['end'] = $addInterval['balance']['end'];
                }
            } else {
                $outputImportIntervals[] = $addInterval;
            }
        }

        return $outputImportIntervals;
    }

    public static function getMissingImportIntervalsFromDatabase(Selection $importsSelection): array
    {
        $intervals = [];
        foreach ($importsSelection as $row) {
            $importDates = ['start' => $row->d_statement_start, 'end' => $row->d_statement_end];
            $importBalances = ['start' => $row->balance_start, 'end' => $row->balance_end];
            $import = ['date' => $importDates, 'balance' => $importBalances];
            $intervals[] = $import;
        }

        $intervals = self::joinImportIntervals($intervals);

        $missingIntervals = [];
        $endDate = null;
        foreach ($intervals as $interval) {
            if ($endDate === null) {
                $endDate = $interval['date']['end'];
            } else {
                $missingInterval = ['start' => $endDate, 'end' => $interval['date']['start']];
                $missingIntervals[] = $missingInterval;
                $endDate = $interval['date']['end'];
            }
        }
        if ($endDate !== null) {
            $endTimeStamp = $endDate->getTimeStamp();
            $now = new DateTime();
            $currentTimeStamp = $now->getTimestamp();
            if ($endTimeStamp + self::LAST_IMPORT_TIMESTAMP_DIFFERENCE < $currentTimeStamp) {
                $missingInterval = ['start' => $endDate, 'end' => $now];
                $missingIntervals[] = $missingInterval;
            }
        }

        return $missingIntervals;
    }

    public static function fancyDumpImportIntervals(array $intervals): void
    {
        $isFirst = true;
        foreach ($intervals as $interval) {
            $text = $interval['date']['start']->format('j.n').' '.$interval['date']['end']->format('j.n').' '.$interval['balance']['start'].' '.$interval['balance']['end'];
            if ($isFirst) {
                Debugger::barDump($text, 'fancyDumpImportIntervals');
                $isFirst = false;
            } else {
                Debugger::barDump($text);
            }
        }
        if ($isFirst) {
            Debugger::barDump('', 'fancyDumpImportIntervals');
        }
    }

    public static function isImportIntervalDuplicate(array $importInterval, array $inputImportIntervals): bool
    {
        foreach ($inputImportIntervals as $interval) {
            if (self::isFirstImportIntervalInside($importInterval, $interval)) {
                return true;
            }
        }

        $intervals = self::joinImportIntervals($inputImportIntervals);

        foreach ($intervals as $interval) {
            if (self::isFirstImportIntervalInside($importInterval, $interval)) {
                return true;
            }
        }

        return false;
    }
}