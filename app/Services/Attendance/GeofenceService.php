<?php

namespace App\Services\Attendance;

use App\Models\Location;

/**
 * Single source of truth for "is this employee inside their geofence?".
 *
 * The browser only reports coordinates and accuracy; every accept/reject
 * decision is taken here so the manual punch card and the QR phone page
 * always produce the same verdict.
 */
class GeofenceService
{
    /**
     * Fallback for the coarsest accuracy (meters) still accepted. The real
     * value is config('attendance.max_accuracy_m'): devices without a GPS
     * chip report 1000-5000 m, and the old 100 m ceiling rejected every punch
     * from them before a single distance was measured.
     */
    public const MAX_ACCURACY_M = 1000.0;

    /**
     * Part of the reported accuracy that is forgiven when comparing the
     * distance, so a normal GPS jitter does not reject a valid punch.
     * config('attendance.tolerance_m') can override it.
     */
    public const ACCURACY_TOLERANCE_M = 100.0;

    /**
     * @return array{
     *     allowed: bool,
     *     distance_m: float,
     *     accuracy_m: float,
     *     tolerance_m: float,
     *     effective_distance_m: float,
     *     allowed_radius_m: int,
     *     location_id: int|null,
     *     location_name: string|null,
     *     message: string|null
     * }
     */
    public function check(Location $location, float $latitude, float $longitude, float $accuracy): array
    {
        $radius = (int) ($location->radius_m ?: Location::DEFAULT_RADIUS_M);

        if ($this->isZeroCoordinate($latitude, $longitude)) {
            return $this->result(
                false,
                0.0,
                $accuracy,
                0.0,
                $radius,
                $location,
                'Could not read your GPS position. Turn on location services and try again.'
            );
        }

        if ($accuracy > $this->maxAccuracy()) {
            return $this->result(
                false,
                0.0,
                $accuracy,
                0.0,
                $radius,
                $location,
                $this->weakAccuracyMessage($accuracy)
            );
        }

        $distance  = $this->distanceMeters($latitude, $longitude, (float) $location->latitude, (float) $location->longitude);
        $tolerance = $this->toleranceFor($accuracy);
        $effective = max(0.0, $distance - $tolerance);
        $allowed   = $effective <= $radius;

        $message = $allowed ? null : sprintf(
            'You are %s m away from %s. You must be within %s m (GPS +/- %s m).',
            round($distance),
            $location->name,
            $radius,
            round($accuracy)
        );

        return $this->result($allowed, $distance, $accuracy, $tolerance, $radius, $location, $message);
    }

    /**
     * Two-tier decision for one punch.
     *
     * Tier 1 - the employee's assigned site                 -> match "assigned"
     * Tier 2 - the nearest other active site they are inside -> match "on_duty"
     *
     * Tier 2 is what lets somebody work at another branch for a day: the punch
     * is accepted, but it is labelled and needs a reason. When several sites
     * overlap, the closest one is reported, so "on duty at ..." is never wrong.
     *
     * @param  iterable<Location>|null  $otherSites  every other active site
     * @return array{
     *     allowed: bool,
     *     match: string|null,
     *     matched_location: Location|null,
     *     expected_location: Location|null,
     *     distance_m: float,
     *     expected_distance_m: float|null,
     *     accuracy_m: float,
     *     tolerance_m: float,
     *     allowed_radius_m: int,
     *     location_name: string|null,
     *     message: string|null
     * }
     */
    public function evaluate(
        ?Location $expected,
        $otherSites,
        float $latitude,
        float $longitude,
        float $accuracy
    ): array {
        $sites = collect($otherSites ?? [])
            ->filter(fn($site) => $site instanceof Location)
            ->when($expected, function ($all) use ($expected) {
                return $all->reject(fn(Location $site) => $site->id === $expected->id);
            })
            ->values();

        $expectedRadius = $expected ? $this->radiusOf($expected) : 0;

        if ($this->isZeroCoordinate($latitude, $longitude)) {
            return $this->evaluation(
                false,
                null,
                null,
                $expected,
                0.0,
                null,
                $accuracy,
                0.0,
                $expectedRadius,
                'Could not read your GPS position. Turn on location services and try again.'
            );
        }

        if ($accuracy > $this->maxAccuracy()) {
            return $this->evaluation(
                false,
                null,
                null,
                $expected,
                0.0,
                null,
                $accuracy,
                0.0,
                $expectedRadius,
                $this->weakAccuracyMessage($accuracy)
            );
        }

        $tolerance = $this->toleranceFor($accuracy);

        foreach ($sites as $index => $site) {
            $sites[$index] = [
                'site'     => $site,
                'distance' => $this->distanceMeters($latitude, $longitude, (float) $site->latitude, (float) $site->longitude),
            ];
        }

        $siteDistances = $sites->all();

        // ---- Tier 1: the assigned site ----
        $expectedDistance = null;

        if ($expected) {
            $expectedDistance = $this->distanceMeters(
                $latitude,
                $longitude,
                (float) $expected->latitude,
                (float) $expected->longitude
            );

            if (max(0.0, $expectedDistance - $tolerance) <= $expectedRadius) {
                return $this->evaluation(
                    true,
                    'assigned',
                    $expected,
                    $expected,
                    $expectedDistance,
                    $expectedDistance,
                    $accuracy,
                    $tolerance,
                    $expectedRadius,
                    null
                );
            }
        }

        // ---- Tier 2: nearest other site that contains the reading ----
        $best = null;

        foreach ($siteDistances as $candidate) {
            $radius = $this->radiusOf($candidate['site']);

            if (max(0.0, $candidate['distance'] - $tolerance) > $radius) {
                continue;
            }

            if ($best === null || $candidate['distance'] < $best['distance']) {
                $best = $candidate;
            }
        }

        if ($best !== null) {
            return $this->evaluation(
                true,
                'on_duty',
                $best['site'],
                $expected,
                $best['distance'],
                $expectedDistance,
                $accuracy,
                $tolerance,
                $this->radiusOf($best['site']),
                null
            );
        }

        // ---- Nothing matched: name the closest option so the message helps ----
        $nearest = $expectedDistance === null
            ? null
            : ['site' => $expected, 'distance' => $expectedDistance];

        foreach ($siteDistances as $candidate) {
            if ($nearest === null || $candidate['distance'] < $nearest['distance']) {
                $nearest = $candidate;
            }
        }

        if ($nearest === null) {
            return $this->evaluation(
                false,
                null,
                null,
                $expected,
                0.0,
                $expectedDistance,
                $accuracy,
                $tolerance,
                0,
                'No attendance location is configured. Please contact HR.'
            );
        }

        return $this->evaluation(
            false,
            null,
            null,
            $expected,
            $nearest['distance'],
            $expectedDistance,
            $accuracy,
            $tolerance,
            $this->radiusOf($nearest['site']),
            sprintf(
                'You are %s m away from %s. You must be within %s m of your assigned site or of any office.',
                round($nearest['distance']),
                $nearest['site']->name,
                $this->radiusOf($nearest['site'])
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluation(
        bool $allowed,
        ?string $match,
        ?Location $matched,
        ?Location $expected,
        float $distance,
        ?float $expectedDistance,
        float $accuracy,
        float $tolerance,
        int $allowedRadius,
        ?string $message
    ): array {
        return [
            'allowed'              => $allowed,
            'match'                => $match,
            'matched_location'     => $matched,
            'expected_location'    => $expected,
            'distance_m'           => round($distance, 2),
            'expected_distance_m'  => $expectedDistance === null ? null : round($expectedDistance, 2),
            'accuracy_m'           => round($accuracy, 2),
            'tolerance_m'          => round($tolerance, 2),
            'allowed_radius_m'     => $allowedRadius,
            'location_name'        => $matched ? $matched->name : $expected?->name,
            'message'              => $message,
        ];
    }

    private function radiusOf(Location $location): int
    {
        return (int) ($location->radius_m ?: Location::DEFAULT_RADIUS_M);
    }

    private function maxAccuracy(): float
    {
        $configured = config('attendance.max_accuracy_m');

        return is_numeric($configured) && (float) $configured > 0
            ? (float) $configured
            : self::MAX_ACCURACY_M;
    }

    /**
     * How much of the reported accuracy is forgiven on the distance.
     *
     * 0 / negative means "the browser could not say" (emulated locations,
     * some Android builds): the coordinates are still usable, but nothing is
     * forgiven because we have no idea how far off they are.
     */
    private function toleranceFor(float $accuracy): float
    {
        if ($accuracy <= 0) {
            return 0.0;
        }

        $cap = (float) config('attendance.tolerance_m', self::ACCURACY_TOLERANCE_M);

        return min($accuracy, $cap > 0 ? $cap : self::ACCURACY_TOLERANCE_M);
    }

    private function weakAccuracyMessage(float $accuracy): string
    {
        return sprintf(
            'GPS accuracy is too weak (%s m, limit %s m). Move to an open area and try again.',
            round($accuracy),
            round($this->maxAccuracy())
        );
    }

    /**
     * Great-circle distance in meters.
     */
    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000.0;

        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $deltaPhi = deg2rad($lat2 - $lat1);
        $deltaLambda = deg2rad($lng2 - $lng1);

        $a = sin($deltaPhi / 2) ** 2
            + cos($phi1) * cos($phi2) * sin($deltaLambda / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function isZeroCoordinate(float $latitude, float $longitude): bool
    {
        return abs($latitude) < 0.000001 && abs($longitude) < 0.000001;
    }

    private function result(
        bool $allowed,
        float $distance,
        float $accuracy,
        float $tolerance,
        int $radius,
        Location $location,
        ?string $message
    ): array {
        return [
            'allowed'               => $allowed,
            'distance_m'            => round($distance, 2),
            'accuracy_m'            => round($accuracy, 2),
            'tolerance_m'           => round($tolerance, 2),
            'effective_distance_m'  => round(max(0.0, $distance - $tolerance), 2),
            'allowed_radius_m'      => $radius,
            'location_id'           => $location->id,
            'location_name'         => $location->name,
            'message'               => $message,
        ];
    }
}
