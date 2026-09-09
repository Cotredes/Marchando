<?php

namespace App;

use App\Models\Restaurant;
use Carbon\CarbonInterface;

class RestaurantAvailability
{
    public function isOpenAt(Restaurant $restaurant, CarbonInterface $instant, string $context = 'general'): bool
    {
        $local = $instant->copy()->setTimezone($restaurant->timezone);
        $weekday = $local->isoWeekday();
        $minute = ($local->hour * 60) + $local->minute;

        return $this->matchesPeriods($restaurant, $context, $weekday, $minute);
    }

    public function isTakeawayOpenAt(Restaurant $restaurant, CarbonInterface $instant): bool
    {
        return $restaurant->takeaway_enabled
            && $this->isOpenAt(
                $restaurant,
                $instant,
                $restaurant->takeaway_use_general_schedule ? 'general' : 'takeaway',
            );
    }

    public function isDeliveryOpenAt(Restaurant $restaurant, CarbonInterface $instant): bool
    {
        return $restaurant->delivery_enabled
            && $this->isOpenAt(
                $restaurant,
                $instant,
                $restaurant->delivery_use_general_schedule ? 'general' : 'delivery',
            );
    }

    public function isChannelOpenAt(Restaurant $restaurant, string $channel, CarbonInterface $instant): bool
    {
        return match ($channel) {
            'dine_in' => $restaurant->dine_in_enabled && $this->isOpenAt($restaurant, $instant),
            'takeaway' => $this->isTakeawayOpenAt($restaurant, $instant),
            'delivery' => $this->isDeliveryOpenAt($restaurant, $instant),
            default => false,
        };
    }

    private function matchesPeriods(Restaurant $restaurant, string $context, int $weekday, int $minute): bool
    {
        $periods = $restaurant->openingHours()
            ->where('context', $context)
            ->whereIn('iso_weekday', [$weekday, $weekday === 1 ? 7 : $weekday - 1])
            ->get();

        foreach ($periods as $period) {
            if ($period->iso_weekday === $weekday && $minute >= $period->start_minute && $minute < min($period->end_minute, 1440)) {
                return true;
            }

            if ($period->iso_weekday === ($weekday === 1 ? 7 : $weekday - 1)
                && $period->end_minute > 1440
                && ($minute + 1440) >= $period->start_minute
                && ($minute + 1440) < $period->end_minute) {
                return true;
            }
        }

        return false;
    }
}
