<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dated posting: "this employee works at this site from X to Y".
 *
 * Resolution only reads rows that cover the day being punched, so a transfer
 * ends by itself when ends_on passes - the employee simply falls back to the
 * site derived from their branch at that time.
 */
class EmployeeLocationAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'location_id',
        'starts_on',
        'ends_on',
        'note',
        'created_by',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on'   => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Postings that cover the given day. A null ends_on runs indefinitely.
     */
    public function scopeActiveOn(Builder $query, $date = null): Builder
    {
        $day = $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d')
            : (string) ($date ?: now()->toDateString());

        return $query
            ->whereDate('starts_on', '<=', $day)
            ->where(function (Builder $inner) use ($day) {
                $inner->whereNull('ends_on')->orWhereDate('ends_on', '>=', $day);
            });
    }

    public function scopeForUser(Builder $query, $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Human label for the report screens.
     */
    public function getStateAttribute(): string
    {
        $today = now()->startOfDay();

        if ($this->starts_on && $this->starts_on->gt($today)) {
            return 'scheduled';
        }

        if ($this->ends_on && $this->ends_on->lt($today)) {
            return 'expired';
        }

        return 'active';
    }
}
