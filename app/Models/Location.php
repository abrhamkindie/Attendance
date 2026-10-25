<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory;

    /**
     * Fallback radius when a location has none configured.
     */
    public const DEFAULT_RADIUS_M = 100;

    protected $fillable = [
        'name',
        'address',
        'longitude',
        'latitude',
        'radius_m',
        'is_active',
    ];

    protected $casts = [
        'longitude'  => 'float',
        'latitude'   => 'float',
        'radius_m'   => 'integer',
        'is_active'  => 'boolean',
    ];

    protected $attributes = [
        'radius_m'  => self::DEFAULT_RADIUS_M,
        'is_active' => true,
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getCoordinatesAttribute(): string
    {
        return $this->latitude . ', ' . $this->longitude;
    }
}
