<?php

namespace App\Models;

use App\Helpers\DateHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A company holiday.
 *
 * IMPORTANT: `date_holiday` is a VARCHAR holding mixed formats ("2024-05-01",
 * "1/5/2024", "1 May 2024", ...). It can never be compared in SQL reliably —
 * always normalise through DateHelper::parseLooseDate() / holidayDate().
 */
class Holiday extends Model
{
    use HasFactory;

    protected $table = 'holidays';

    protected $fillable = [
        'date_holiday',
        'name',
        'year',
    ];

    /**
     * The parsed, normalised date for this holiday. Null when the stored
     * free-text value cannot be understood.
     */
    public function holidayDate(): ?Carbon
    {
        return DateHelper::parseLooseDate($this->date_holiday);
    }

    /**
     * Eager-friendly accessor mirroring holidayDate().
     */
    public function getHolidayDateAttribute(): ?Carbon
    {
        return $this->holidayDate();
    }

    /**
     * Holidays whose parsed date falls inside [from, to].
     * Filtering happens in PHP because the column is free text.
     */
    public static function between(Carbon $from, Carbon $to): array
    {
        return static::all()
            ->filter(fn(self $h) => ($d = $h->holidayDate()) && $d->betweenIncluded($from->copy()->startOfDay(), $to->copy()->startOfDay()))
            ->all();
    }
}
