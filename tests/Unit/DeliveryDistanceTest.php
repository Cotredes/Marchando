<?php

namespace Tests\Unit;

use App\DeliveryDistance;
use PHPUnit\Framework\TestCase;

class DeliveryDistanceTest extends TestCase
{
    public function test_same_point_has_zero_distance(): void
    {
        $this->assertSame(0, (new DeliveryDistance)->meters(40.4168, -3.7038, 40.4168, -3.7038));
    }

    public function test_distance_is_calculated_in_integer_metres(): void
    {
        $distance = (new DeliveryDistance)->meters(40.4168, -3.7038, 40.4268, -3.7038);

        $this->assertGreaterThan(1000, $distance);
        $this->assertLessThan(1200, $distance);
    }
}
