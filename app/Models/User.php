<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'join_date',
        'branch_id',
        'department_id',
        'location_id',
        'is_representative',
        'is_department_representative',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_representative' => 'boolean',
            'is_department_representative' => 'boolean',
        ];
    }

    // ============================================================
    //  Domain relationships
    // ============================================================

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Dated working-location postings (temporary transfers).
     */
    public function locationAssignments(): HasMany
    {
        return $this->hasMany(EmployeeLocationAssignment::class)->orderByDesc('starts_on');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    // ============================================================
    //  Attendance geofence resolver
    // ============================================================

    /**
     * Cached resolution for the current request: [Location|null, source].
     *
     * @var array{0: \App\Models\Location|null, 1: string}|null
     */
    private ?array $resolvedAttendanceLocation = null;

    /**
     * The geofence this user is expected to punch from on a given day.
     *
     * Resolution order: active dated posting -> personal location -> branch
     * location. There is no global fallback: an employee nobody assigned a
     * site to has no expected geofence.
     */
    public function expectedLocationFor($date = null): ?Location
    {
        return $this->resolveAttendanceLocation($date)[0];
    }

    /**
     * Today's expected location.
     */
    public function attendanceLocation(): ?Location
    {
        return $this->resolveAttendanceLocation()[0];
    }

    /**
     * Where the resolved location came from: assignment | user | branch | none.
     */
    public function attendanceLocationSource(): string
    {
        return $this->resolveAttendanceLocation()[1];
    }

    /**
     * The location mapped to this user's branch, ignoring personal postings.
     */
    public function branchLocation(): ?Location
    {
        $location = $this->branch?->location;

        return $location && $location->is_active ? $location : null;
    }

    /**
     * @return array{0: \App\Models\Location|null, 1: string}
     */
    private function resolveAttendanceLocation($date = null): array
    {
        if ($date === null && $this->resolvedAttendanceLocation !== null) {
            return $this->resolvedAttendanceLocation;
        }

        $resolved = $this->computeAttendanceLocation($date);

        if ($date === null) {
            $this->resolvedAttendanceLocation = $resolved;
        }

        return $resolved;
    }

    /**
     * @return array{0: \App\Models\Location|null, 1: string}
     */
    private function computeAttendanceLocation($date): array
    {
        if ($this->exists) {
            $posting = EmployeeLocationAssignment::query()
                ->forUser($this->getKey())
                ->activeOn($date)
                ->with('location')
                ->orderByDesc('starts_on')
                ->orderByDesc('id')
                ->first();

            if ($posting && $posting->location && $posting->location->is_active) {
                return [$posting->location, 'assignment'];
            }
        }

        if ($this->location && $this->location->is_active) {
            return [$this->location, 'user'];
        }

        $branchLocation = $this->branchLocation();

        if ($branchLocation) {
            return [$branchLocation, 'branch'];
        }

        return [null, 'none'];
    }
}
