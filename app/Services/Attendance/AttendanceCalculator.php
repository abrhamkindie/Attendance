<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Shared time math for the punch card and the auto-close command.
 *
 * Always pass the *new* values (or fill the model first) before calling
 * totals(); the calculator never reads a stale clock_out/break_end from
 * the database.
 */
class AttendanceCalculator
{
    public const REGULAR_HOURS = 8.0;

    public const AUTO_CLOSE_TIME = '20:00:00';

    /**
     * @return array{break_hours: float, total_hours: float, overtime_hours: float}
     */
    public function totals(Attendance $attendance, ?string $clockOut = null, ?string $breakEnd = null): array
    {
        $date = $this->dateString($attendance);
        $clockIn = $attendance->clock_in;
        $clockOut = $clockOut ?? $attendance->clock_out;
        $breakStart = $attendance->break_start;
        $breakEnd = $breakEnd ?? $attendance->break_end;

        $breakMinutes = $this->breakMinutes($date, $breakStart, $breakEnd);
        $totalHours = 0.0;

        if ($clockIn && $clockOut) {
            $in = $this->at($date, $clockIn);
            $out = $this->at($date, $clockOut);

            if ($out->lt($in)) {
                $out->addDay();
            }

            $workedMinutes = $in->diffInMinutes($out, false) - $breakMinutes;
            $totalHours = round(max($workedMinutes, 0) / 60, 2);
        }

        return [
            'break_hours'    => round($breakMinutes / 60, 2),
            'total_hours'    => $totalHours,
            'overtime_hours' => round(max($totalHours - self::REGULAR_HOURS, 0), 2),
        ];
    }

    public function breakMinutes(string $date, ?string $breakStart, ?string $breakEnd): int
    {
        if (! $breakStart || ! $breakEnd) {
            return 0;
        }

        $start = $this->at($date, $breakStart);
        $end = $this->at($date, $breakEnd);

        if ($end->lt($start)) {
            $end->addDay();
        }

        return max(0, (int) $start->diffInMinutes($end, false));
    }

    public function dateString(Attendance $attendance): string
    {
        $date = $attendance->date;

        if ($date instanceof DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        return $date ? Carbon::parse($date)->toDateString() : Carbon::today()->toDateString();
    }

    private function at(string $date, string $time): Carbon
    {
        return Carbon::parse($date . ' ' . $time, config('app.timezone'));
    }
}
