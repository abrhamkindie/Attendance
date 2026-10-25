<?php

namespace App\Helpers;

use App\Models\Holiday;
use Illuminate\Support\Carbon;

/**
 * Loose date utilities carried over from the legacy attendance app.
 *
 * The legacy `holidays.date_holiday` column and `users.join_date` are free
 * text: values arrive in mixed formats (Y-m-d, d/m/Y, "5 Mar 2024", ...),
 * so nothing can be normalised in SQL. All parsing happens here.
 *
 * Weekend convention in this app: Sunday ONLY (Saturday is a working day).
 */
class DateHelper
{
    /**
     * Formats attempted by parseLooseDate(), in priority order.
     *
     * @var array<int, string>
     */
    public const FORMATS = [
        'Y-m-d',
        'Y/m/d',
        'Y.n.d',
        'd-m-Y',
        'd/m/Y',
        'd.m.Y',
        'm-d-Y',
        'm/d/Y',
        'd-M-Y',
        'd M Y',
        'j M Y',
        'j-n-Y',
        'j/n/Y',
        'M d, Y',
        'M j, Y',
        'F d, Y',
        'F j, Y',
        'Y-m-d H:i:s',
        'd-m-Y H:i:s',
        'd/m/Y H:i:s',
    ];

    /**
     * Parse a date out of almost anything: Carbon/DateTime instances,
     * strings in any of FORMATS, epoch integers or strtotime()-able text.
     *
     * @param  mixed  $value
     */
    public static function parseLooseDate($value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        if (is_int($value)) {
            return Carbon::createFromTimestamp($value)->startOfDay();
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // Pure digits: a Unix timestamp (10-digit) or a compact Ymd (8-digit).
        if (ctype_digit($value)) {
            if (strlen($value) === 8) {
                $dt = Carbon::createFromFormat('Ymd', $value);
                $dt->setTime(0, 0);

                return $dt;
            }

            if (strlen($value) === 10) {
                return Carbon::createFromTimestamp((int) $value)->startOfDay();
            }
        }

        foreach (self::FORMATS as $format) {
            try {
                $date = Carbon::createFromFormat('!' . $format, $value);
                $date->setTime(0, 0);

                return $date;
            } catch (\Throwable) {
                // try the next format
            }
        }

        // Last resort: PHP's own fuzzy parser.
        $ts = strtotime($value);

        return $ts === false ? null : Carbon::createFromTimestamp($ts)->startOfDay();
    }

    /**
     * All known holiday dates as 'Y-m-d' strings, optionally bounded by a
     * range. Rows whose date_holiday cannot be parsed are skipped.
     *
     * @return array<int, string>  sorted ascending, unique
     */
    public static function holidayDates(?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = Holiday::query();

        if ($from && $to) {
            // The column is varchar, so range filtering in SQL is unreliable;
            // normalise in PHP instead. Only a cheap "not empty" filter here.
            $query->whereNotNull('date_holiday');
        }

        $dates = [];

        foreach ($query->get() as $holiday) {
            $date = $holiday->holidayDate();

            if (! $date) {
                continue;
            }

            if ($from && $date->lt($from->copy()->startOfDay())) {
                continue;
            }

            if ($to && $date->gt($to->copy()->startOfDay())) {
                continue;
            }

            $dates[$date->toDateString()] = true;
        }

        $dates = array_keys($dates);
        sort($dates);

        return $dates;
    }

    /**
     * Sunday is the only weekend day in this app.
     *
     * @param  mixed  $date  anything parseLooseDate() accepts
     */
    public static function isWorkingDay($date, ?array $holidays = null): bool
    {
        $date = self::parseLooseDate($date);

        if (! $date) {
            return false;
        }

        if ($date->isSunday()) {
            return false;
        }

        if ($holidays !== null && in_array($date->toDateString(), $holidays, true)) {
            return false;
        }

        return true;
    }

    /**
     * Working days between two dates, inclusive of both endpoints.
     * Excludes Sundays and (by default) every stored holiday.
     *
     * @param  mixed  $from
     * @param  mixed  $to
     * @param  array<int, string>|null  $holidays  'Y-m-d' list; null = load from DB
     */
    public static function countWorkingDaysBetween($from, $to, ?array $holidays = null): int
    {
        $from = self::parseLooseDate($from);
        $to = self::parseLooseDate($to);

        if (! $from || ! $to) {
            return 0;
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($holidays === null) {
            $holidays = self::holidayDates($from, $to);
        }

        $count = 0;
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            if (! $cursor->isSunday() && ! in_array($cursor->toDateString(), $holidays, true)) {
                $count++;
            }

            $cursor->addDay();
        }

        return $count;
    }
}
