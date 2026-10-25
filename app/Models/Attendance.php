<?php

// app/Models/Attendance.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'location_id',
        'assigned_location_id',
        'clock_in_location_id',
        'clock_out_location_id',
        'clock_in_match',
        'clock_out_match',
        'duty_branch_id',
        'duty_note',
        'date',
        'clock_in',
        'clock_in_image',
        'clock_out_image',
        'clock_out',
        'break_start',
        'break_end',
        'total_hours',
        'break_hours',
        'overtime_hours',
        'status',
        'notes',
        'ip_address',
        'location',
        'clock_in_distance_m',
        'clock_out_distance_m',
        'clock_in_accuracy_m',
        'clock_out_accuracy_m',
        'auto_closed_at',
    ];

    protected $casts = [
        'date'                => 'date',
        'location_id'         => 'integer',
        'assigned_location_id' => 'integer',
        'clock_in_location_id' => 'integer',
        'clock_out_location_id' => 'integer',
        'duty_branch_id'      => 'integer',
        'clock_in_distance_m' => 'integer',
        'clock_out_distance_m' => 'integer',
        'clock_in_accuracy_m' => 'float',
        'clock_out_accuracy_m' => 'float',
        'auto_closed_at'      => 'datetime',
    ];

    protected $appends = ['formatted_clock_in', 'formatted_clock_out'];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getFormattedClockInAttribute()
    {
        return $this->clock_in ? \Carbon\Carbon::parse($this->clock_in)->format('h:i A') : '--';
    }

    public function getFormattedClockOutAttribute()
    {
        return $this->clock_out ? \Carbon\Carbon::parse($this->clock_out)->format('h:i A') : '--';
    }

    public function calculateHours()
    {
        if ($this->clock_in && $this->clock_out) {
            $clockIn = \Carbon\Carbon::parse($this->clock_in);
            $clockOut = \Carbon\Carbon::parse($this->clock_out);

            $totalMinutes = $clockOut->diffInMinutes($clockIn);
            $this->total_hours = round($totalMinutes / 60, 2);

            if ($this->break_start && $this->break_end) {
                $breakStart = \Carbon\Carbon::parse($this->break_start);
                $breakEnd = \Carbon\Carbon::parse($this->break_end);
                $breakMinutes = $breakEnd->diffInMinutes($breakStart);
                $this->break_hours = round($breakMinutes / 60, 2);
                $this->total_hours -= $this->break_hours;
            }

            // Calculate overtime (assuming standard work day is 8 hours)
            if ($this->total_hours > 8) {
                $this->overtime_hours = $this->total_hours - 8;
            }

            $this->save();
        }
    }
    public static function getTodaySummary()
    {
        return self::whereDate('created_at', today())->get();
    }
}
