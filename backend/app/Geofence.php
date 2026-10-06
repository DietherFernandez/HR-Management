<?php

final class Geofence
{
    /** Haversine — layo ng dalawang GPS point (meters) */
    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000; // Earth radius (m)
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dp = deg2rad($lat2 - $lat1);
        $dl = deg2rad($lng2 - $lng1);
        $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
        return 2 * $r * asin(sqrt($a));
    }

    /**
     * @return array{verified:bool, distance_m:float}
     */
    public static function check(?float $lat, ?float $lng): array
    {
        if ($lat === null || $lng === null) {
            return ['verified' => false, 'distance_m' => 0.0];
        }
        $d = self::distanceMeters($lat, $lng, COMPANY_LAT, COMPANY_LNG);
        return ['verified' => $d <= GEOFENCE_RADIUS_M, 'distance_m' => round($d, 1)];
    }
}
