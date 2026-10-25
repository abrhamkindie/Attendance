<?php

namespace App\Models;

use App\Helpers\DateHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A leave request.
 *
 * Day-level detail lives in `date_details` — a JSON list of per-day entries
 * that supports HALF DAYS, e.g.:
 *
 *   [{"date": "2026-03-01", "half_day_type": "morning"},
 *    {"date": "2026-03-02", "half_day_type": null}]
 *
 * Older rows may have no JSON at all; those fall back to the plain
 * from_date..to_date span with every day counted as a full day.
 */
class Leave extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const HALF_MORNING = 'morning';
    public const HALF_AFTERNOON = 'afternoon';

    /** Available leave types (leave_type is stored as one of these strings). */
    public const TYPES = ['Annual', 'Sick', 'Casual', 'Unpaid'];

    /**
     * Annual entitlement per leave type, in working days. Null = not quota
     * limited (Unpaid). Adjust these figures to match company policy.
     */
    public const QUOTAS = [
        'Annual' => 20.0,
        'Sick'   => 12.0,
        'Casual' => 7.0,
        'Unpaid' => null,
    ];

    protected $table = 'leaves';

    protected $fillable = [
        'user_id',
        'leave_type',
        'from_date',
        'to_date',
        'date_details',
        'status',
        'reason',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'approved_at' => 'datetime',
        'date_details' => 'array',
    ];

    // -----------------------------------------------------------------
    // Relations
    // -----------------------------------------------------------------

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // -----------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------

    /** Approved requests only — the list every report counts from. */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    // -----------------------------------------------------------------
    // Day entries (normalised)
    // -----------------------------------------------------------------

    /**
     * Normalised per-day entries for this leave:
     *
     *   [['date' => 'Y-m-d', 'half_day_type' => 'morning'|'afternoon'|null], ...]
     *
     * Prefers the date_details JSON; falls back to the from_date..to_date
     * span (all full days) when the JSON is missing or unusable.
     *
     * @return array<int, array{date: string, half_day_type: string|null}>
     */
    public function dayEntries(): array
    {
        $entries = [];

        foreach ((array) $this->date_details as $row) {
            if (is_string($row)) {
                // Legacy shape: a bare list of date strings.
                $row = ['date' => $row];
            }

            $date = DateHelper::parseLooseDate($row['date'] ?? null);

            if (! $date) {
                continue;
            }

            $half = strtolower((string) ($row['half_day_type'] ?? ''));

            $entries[] = [
                'date' => $date->toDateString(),
                'half_day_type' => in_array($half, [self::HALF_MORNING, self::HALF_AFTERNOON], true) ? $half : null,
            ];
        }

        if ($entries !== []) {
            return $entries;
        }

        // Fallback: expand the raw span into full days.
        $from = DateHelper::parseLooseDate($this->from_date);
        $to = DateHelper::parseLooseDate($this->to_date) ?? $from;

        if (! $from || ! $to) {
            return [];
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $entries[] = ['date' => $cursor->toDateString(), 'half_day_type' => null];
            $cursor->addDay();
        }

        return $entries;
    }

    /**
     * Leave days that actually count: entries landing on a working day
     * (not Sunday, not a holiday). Half days count as 0.5.
     *
     * @param  array<int, string>|null  $holidays  'Y-m-d' list; null = load from DB
     */
    public function workingDays(?array $holidays = null): float
    {
        $holidays ??= DateHelper::holidayDates(
            DateHelper::parseLooseDate($this->from_date),
            DateHelper::parseLooseDate($this->to_date)
        );

        $days = 0.0;

        foreach ($this->dayEntries() as $entry) {
            if (! DateHelper::isWorkingDay($entry['date'], $holidays)) {
                continue;
            }

            $days += $entry['half_day_type'] ? 0.5 : 1.0;
        }

        return $days;
    }

    // -----------------------------------------------------------------
    // Balances (quota - used) — used by the employee & HR leave screens
    // -----------------------------------------------------------------

    /** Annual quota for a leave type; null when not quota limited. */
    public static function quotaFor(?string $type): ?float
    {
        if ($type === null || ! array_key_exists($type, self::QUOTAS)) {
            return self::QUOTAS[self::TYPES[0]] ?? null;
        }

        return self::QUOTAS[$type];
    }

    /**
     * Sum of credited working days by leave type for one employee in a given
     * calendar year, for an optional status (defaults to approved). A request
     * is attributed to the year of its from_date.
     *
     * @return array<string, float>
     */
    public static function daysUsedByType(int $userId, int $year, string $status = self::STATUS_APPROVED): array
    {
        $leaves = self::where('user_id', $userId)
            ->where('status', $status)
            ->whereYear('from_date', $year)
            ->get();

        $byType = [];

        foreach ($leaves as $leave) {
            $type = $leave->leave_type ?: self::TYPES[0];
            $byType[$type] = ($byType[$type] ?? 0.0) + $leave->workingDays();
        }

        return $byType;
    }

    /**
     * Per-type balance overview for an employee in a calendar year: quota,
     * used (approved), pending, and remaining balance.
     *
     * @return array<int, array{type:string,quota:?float,used:float,pending:float,balance:?float}>
     */
    public static function balances(int $userId, int $year): array
    {
        $approved = self::daysUsedByType($userId, $year, self::STATUS_APPROVED);
        $pending  = self::daysUsedByType($userId, $year, self::STATUS_PENDING);

        $rows = [];

        foreach (self::TYPES as $type) {
            $quota = self::quotaFor($type);
            $used  = round($approved[$type] ?? 0.0, 2);
            $plan  = round($pending[$type] ?? 0.0, 2);

            $rows[] = [
                'type'    => $type,
                'quota'   => $quota,
                'used'    => $used,
                'pending' => $plan,
                'balance' => $quota === null ? null : round($quota - $used - $plan, 2),
            ];
        }

        return $rows;
    }
}
