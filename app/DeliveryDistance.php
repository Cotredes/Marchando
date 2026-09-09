<?php

namespace App;

class DeliveryDistance
{
    public function meters(float $originLatitude, float $originLongitude, float $destinationLatitude, float $destinationLongitude): int
    {
        $lat = deg2rad($destinationLatitude - $originLatitude);
        $lon = deg2rad($destinationLongitude - $originLongitude);
        $a = sin($lat / 2) ** 2 + cos(deg2rad($originLatitude)) * cos(deg2rad($destinationLatitude)) * sin($lon / 2) ** 2;

        return (int) round(6371008.8 * 2 * atan2(sqrt(min(1, max(0, $a))), sqrt(1 - min(1, max(0, $a)))));
    }
}
