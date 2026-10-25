<?php

namespace App\Services\Attendance;

use App\Helpers\DateHelper;
use App\Models\Leave;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The office calendar behind every attendance figure: which days were
 * expected, and which of those the employee was away on approved leave.
 *
 * Two traps this class exists to hide:
 *
 *  1. `holidays.date_holiday` is a varchar filled with mixed formats
 *     ("11-09-2025" next to "2026-01-07 00:00:00"). MONTH()/DATE() inside a
 *     query returns NULL for the dd-mm-yyyy rows and drops them silently, so
 *     holidays must be normalised in PHP (see DateHelper::holidayDates()).
 *
 *  2. A leave is not a flat date range: `leaves.date_details` carries the
 *     authoritative per-date list, including half days
 *     ([{"date":"2026-07-02","type":"half","period":"morning"}]). from_date /
 *     to_date are only a fallback for legacy rows.
 *
 * Only `status = 'approved'` leaves excuse an absence; pending and
 * disapproved requests must still count as absent.
 */
class AttendanceCalendar
{
    /**
     * Weight of a half-day leave.
     */
    public const HALF_DAY = 0.5;

    public const FULL_DAY = 1.0;

    /**
     * Leave rows already fetched for this request, keyed by user id. Priming
     * them keeps a report with many employees at two queries instead of two
     * per employee.
     *
     * @var array<int, Collection<int, Leave>>
     */
    private array $leaveRows = [];

    /**
     * Holiday dates normalised to 'Y-m-d'.
     *
     * @return array<int, string>
     */
    public function holidays(): array
    {
        return DateHelper::holidayDates();
    }

    /**
     * Working days between two dates (inclusive): Sunday and holidays excluded.
     */
    public function workingDays(Carbon $from, Carbon $to): int
    {
        return DateHelper::countWorkingDaysBetween($from, $to);
    }

    public function isWorkingDay(Carbon $date): bool
    {
        return DateHelper::isWorkingDay($date);
    }

    /**
     * Load the approved leaves of many employees at once.
     *
     * @param  array<int, int|string>  $userIds
     */
    public function primeLeaves(array $userIds, Carbon $from, Carbon $to): void
    {
        $ids = array_values(array_unique(array_map('intval', $userIds)));

        if ($ids === []) {
            return;
        }

        $rows = $this->query($from, $to)
            ->whereIn('user_id', $ids)
            ->get([
                'id',
                'user_id',
                'from_date',
                'to_date',
                'date_details',
                'is_half_day',
            ])
            ->groupBy('user_id');

        foreach ($ids as $id) {
            $this->leaveRows[$id] = $rows->get($id) ?? collect();
        }
    }

    /**
     * Approved leave weight per date for one employee: ['2026-07-02' => 0.5].
     *
     * @return array<string, float>
     */
    public function approvedLeaveDates(int $userId, Carbon $from, Carbon $to): array
    {
        $fromDay = $from->toDateString();
        $toDay   = $to->toDateString();
        $dates   = [];

        foreach ($this->rowsFor($userId, $from, $to) as $leave) {
            foreach ($this->datesOf($leave) as $date => $weight) {
                if ($date < $fromDay || $date > $toDay) {
                    continue;
                }

                // Two overlapping leaves on one day never add up past a full day.
                $dates[$date] = min(self::FULL_DAY, ($dates[$date] ?? 0.0) + $weight);
            }
        }

        return $dates;
    }

    /**
     * Leave weight that actually excuses an absence: approved, inside the
     * range, on a working day, and not already covered by a punch-in.
     *
     * @param  array<string, mixed>  $presentDates  dates the employee attended, as keys
     */
    public function leaveWorkingDays(
        int $userId,
        Carbon $from,
        Carbon $to,
        array $presentDates = []
    ): float {
        $total = 0.0;

        foreach ($this->approvedLeaveDates($userId, $from, $to) as $date => $weight) {
            if (isset($presentDates[$date])) {
                continue;
            }

            if (! $this->isWorkingDay(Carbon::parse($date))) {
                continue;
            }

            $total += $weight;
        }

        return round(min($total, (float) $this->workingDays($from, $to)), 2);
    }

    /**
     * @return Collection<int, Leave>
     */
    private function rowsFor(int $userId, Carbon $from, Carbon $to): Collection
    {
        if (array_key_exists($userId, $this->leaveRows)) {
            return $this->leaveRows[$userId];
        }

        return $this->leaveRows[$userId] = $this->query($from, $to)
            ->where('user_id', $userId)
            ->get([
                'id',
                'user_id',
                'from_date',
                'to_date',
                'date_details',
                'is_half_day',
            ]);
    }

    private function query(Carbon $from, Carbon $to)
    {
        return Leave::query()
            ->where('status', 'approved')
            ->whereDate('from_date', '<=', $to->toDateString())
            ->whereDate('to_date', '>=', $from->toDateString());
    }

    /**
     * Every date a leave covers, with its weight.
     *
     * @return array<string, float>
     */
    private function datesOf(Leave $leave): array
    {
        $dates = $this->datesFromDetails($leave->date_details);

        if ($dates !== []) {
            return $dates;
        }

        // Legacy rows predate date_details: use the range and the old
        // single half-day flag, which only ever applied to the first day.
        $start = $this->parseDate($leave->from_date);

        if ($start === null) {
            return [];
        }

        $end   = $this->parseDate($leave->to_date) ?? $start->copy();
        $dates = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $dates[$day->toDateString()] = self::FULL_DAY;
        }

        if ((bool) $leave->is_half_day && $dates !== []) {
            $dates[$start->toDateString()] = self::HALF_DAY;
        }

        return $dates;
    }

    /**
     * @param  mixed  $details  JSON list written by the leave form
     * @return array<string, float>
     */
    private function datesFromDetails($details): array
    {
        if (! is_string($details) || trim($details) === '') {
            return [];
        }

        $decoded = json_decode($details, true);

        if (! is_array($decoded)) {
            return [];
        }

        $dates = [];

        foreach ($decoded as $entry) {
            $date = is_array($entry) ? ($entry['date'] ?? null) : null;

            if (! is_string($date) || trim($date) === '') {
                continue;
            }

            $day = $this->parseDate($date);

            if ($day === null) {
                continue;
            }

            $type = strtolower((string) ($entry['type'] ?? 'full'));

            $dates[$day->toDateString()] = str_contains($type, 'half')
                ? self::HALF_DAY
                : self::FULL_DAY;
        }

        return $dates;
    }

    private function parseDate($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
