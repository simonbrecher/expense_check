<?php


namespace App\Utils;


class ImportIntervals
{
    private const DAY_TIMESTAMP_DIFFERENCE = 86400;

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

    public static function isImportIntervalDuplicate(array $importInterval, array $inputImportIntervals): bool
    {
        $intervals = self::joinImportIntervals($inputImportIntervals);

        foreach ($intervals as $interval) {
            if (self::isFirstImportIntervalInside($importInterval, $interval)) {
                return true;
            }
        }

        return false;
    }
}