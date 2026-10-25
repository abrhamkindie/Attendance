<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Coarsest GPS reading accepted for a punch (meters)
    |--------------------------------------------------------------------------
    |
    | A phone with a GPS chip usually reports 5-30 m, but any device without
    | one - office desktops, laptops, tablets on Wi-Fi, phones with location
    | services in power saving mode - falls back to network/Cell-ID position
    | and reports 1000-5000 m. Because the fence itself is decided from the
    | coordinates, not from this number, a high ceiling does not widen the
    | fence; it only stops those devices from being locked out before the
    | distance is ever measured. Readings above this are considered garbage.
    |
    */

    'max_accuracy_m' => (float) env('ATTENDANCE_MAX_ACCURACY_M', 1000),

    /*
    |--------------------------------------------------------------------------
    | Accuracy tolerance (meters)
    |--------------------------------------------------------------------------
    |
    | Part of the reported accuracy forgiven when comparing the distance, so
    | normal GPS jitter does not reject a punch that is a few meters outside
    | the radius. It never forgives more than this.
    |
    */

    'tolerance_m' => (float) env('ATTENDANCE_TOLERANCE_M', 30),

];
