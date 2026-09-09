<?php

namespace App;

use App\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class SalesPeriod
{
    /** @return array{0:CarbonImmutable,1:CarbonImmutable,2:string} */
    public static function resolve(Restaurant $restaurant, Request $request): array
    {
        $key = (string) $request->input('period', 'today');
        $now = CarbonImmutable::now($restaurant->timezone);
        $day = fn (CarbonImmutable $date) => [$date->startOfDay(), $date->endOfDay()];

        switch ($key) {
            case 'yesterday':
                [$from, $to] = $day($now->subDay());
                break;
            case '7d':
                $from = $now->subDays(6)->startOfDay();
                $to = $now->endOfDay();
                break;
            case 'month':
                $from = $now->startOfMonth();
                $to = $now->endOfDay();
                break;
            case 'custom':
                try {
                    $from = CarbonImmutable::parse($request->input('from'), $restaurant->timezone)->startOfDay();
                    $to = CarbonImmutable::parse($request->input('to'), $restaurant->timezone)->endOfDay();
                } catch (\Throwable) {
                    $key = 'today';
                    [$from, $to] = $day($now);
                }
                break;
            default:
                $key = 'today';
                [$from, $to] = $day($now);
        }
        if ($to->lessThan($from)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return [$from, $to, $key];
    }
}
