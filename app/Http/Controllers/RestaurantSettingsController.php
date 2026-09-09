<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOpeningHoursRequest;
use App\Http\Requests\UpdateRestaurantChannelsRequest;
use App\Http\Requests\UpdateRestaurantSettingsRequest;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RestaurantSettingsController extends Controller
{
    public function edit(Restaurant $restaurant): View
    {
        $this->authorize('viewSettings', $restaurant);

        $restaurant->load('openingHours');
        $hours = $this->formatHours($restaurant);

        return view('restaurants.settings', [
            'restaurant' => $restaurant,
            'hours' => $hours,
            'establishmentTypes' => [
                'restaurant' => 'Restaurante',
                'bar' => 'Bar',
                'cafeteria' => 'Cafetería',
                'pizzeria' => 'Pizzería',
                'gastropub' => 'Gastrobar',
                'fast_food' => 'Comida rápida',
                'other' => 'Otro',
            ],
        ]);
    }

    public function updateDetails(UpdateRestaurantSettingsRequest $request, Restaurant $restaurant): RedirectResponse
    {
        $restaurant->update($request->validated());

        return redirect(route('restaurant.settings', $restaurant).'#general')
            ->with('status', 'Datos generales guardados.');
    }

    public function updateChannels(UpdateRestaurantChannelsRequest $request, Restaurant $restaurant): RedirectResponse
    {
        $restaurant->update([
            'dine_in_enabled' => $request->boolean('dine_in_enabled'),
            'takeaway_enabled' => $request->boolean('takeaway_enabled'),
            'takeaway_prep_minutes' => $request->input('takeaway_prep_minutes'),
            'takeaway_use_general_schedule' => $request->boolean('takeaway_use_general_schedule'),
            'delivery_enabled' => $request->boolean('delivery_enabled'),
            'delivery_radius_km' => $request->input('delivery_radius_km'),
            'delivery_fee' => $request->input('delivery_fee'),
            'delivery_minimum_order' => $request->input('delivery_minimum_order'),
            'delivery_prep_minutes' => $request->input('delivery_prep_minutes'),
            'delivery_use_general_schedule' => $request->boolean('delivery_use_general_schedule'),
            'public_menu_enabled' => $request->boolean('public_menu_enabled'),
            'qr_ordering_enabled' => $request->boolean('qr_ordering_enabled'),
            'qr_acceptance_mode' => $request->string('qr_acceptance_mode')->toString(),
            'takeaway_acceptance_mode' => $request->string('takeaway_acceptance_mode')->toString(),
            'delivery_acceptance_mode' => $request->string('delivery_acceptance_mode')->toString(),
            'takeaway_scheduling_enabled' => $request->boolean('takeaway_scheduling_enabled'),
            'delivery_scheduling_enabled' => $request->boolean('delivery_scheduling_enabled'),
        ]);

        return redirect(route('restaurant.settings', $restaurant).'#channels')
            ->with('status', 'Canales guardados.');
    }

    public function updateHours(UpdateOpeningHoursRequest $request, Restaurant $restaurant): RedirectResponse
    {
        $hours = $request->normalizedHours();

        DB::transaction(function () use ($restaurant, $hours): void {
            $restaurant->openingHours()->delete();

            foreach ($hours as $context => $periods) {
                if (! in_array($context, ['general', 'takeaway', 'delivery'], true)) {
                    continue;
                }

                if ($context !== 'general' && (($context === 'takeaway' && (! $restaurant->takeaway_enabled || $restaurant->takeaway_use_general_schedule)) || ($context === 'delivery' && (! $restaurant->delivery_enabled || $restaurant->delivery_use_general_schedule)))) {
                    continue;
                }

                $restaurant->openingHours()->createMany(array_map(
                    fn (array $period): array => ['context' => $context, ...$period],
                    $periods,
                ));
            }
        });

        return redirect(route('restaurant.settings', $restaurant).'#hours')
            ->with('status', 'Horarios guardados.');
    }

    /**
     * @return array<string, array<int, array{enabled: bool, intervals: array<int, array{opens_at: string, closes_at: string}>}>>
     */
    private function formatHours(Restaurant $restaurant): array
    {
        $hours = [];

        foreach (['general', 'takeaway', 'delivery'] as $context) {
            foreach (range(1, 7) as $weekday) {
                $hours[$context][$weekday] = ['enabled' => false, 'intervals' => []];
            }
        }

        foreach ($restaurant->openingHours as $period) {
            $hours[$period->context][$period->iso_weekday]['enabled'] = true;
            $hours[$period->context][$period->iso_weekday]['intervals'][] = [
                'opens_at' => $this->formatMinute($period->start_minute),
                'closes_at' => $this->formatMinute($period->end_minute % 1440),
            ];
        }

        return $hours;
    }

    private function formatMinute(int $minute): string
    {
        return sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }
}
