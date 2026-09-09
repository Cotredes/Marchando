<?php

namespace App\Http\Requests;

use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOpeningHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        $restaurant = $this->route('restaurant');

        return $restaurant instanceof Restaurant && $this->user()->can('updateSettings', $restaurant);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'hours' => ['required', 'array'],
            'hours.*' => ['array'],
            'hours.*.*.enabled' => ['sometimes', 'boolean'],
            'hours.*.*.intervals' => ['array', 'max:8'],
            'hours.*.*.intervals.*.opens_at' => ['required', 'date_format:H:i'],
            'hours.*.*.intervals.*.closes_at' => ['required', 'date_format:H:i'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hours = $this->input('hours', []);

            foreach ($hours as $context => $days) {
                if (! in_array($context, ['general', 'takeaway', 'delivery'], true)) {
                    $validator->errors()->add('hours', 'El contexto de horario no es válido.');

                    continue;
                }

                $periods = [];

                foreach ($days as $weekday => $day) {
                    if (! in_array((int) $weekday, range(1, 7), true)) {
                        $validator->errors()->add('hours', 'El día de la semana no es válido.');

                        continue;
                    }

                    if (! filter_var($day['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                        continue;
                    }

                    foreach ($day['intervals'] ?? [] as $index => $interval) {
                        if (! isset($interval['opens_at'], $interval['closes_at'])) {
                            continue;
                        }

                        $start = $this->toMinutes($interval['opens_at']);
                        $end = $this->toMinutes($interval['closes_at']);

                        if ($start === $end) {
                            $validator->errors()->add("hours.$context.$weekday.intervals.$index.closes_at", 'La hora de cierre debe ser diferente a la de apertura.');

                            continue;
                        }

                        if ($end <= $start) {
                            $end += 1440;
                        }

                        if (($end - $start) > 1440) {
                            $validator->errors()->add("hours.$context.$weekday.intervals.$index.closes_at", 'Una franja no puede durar más de 24 horas.');

                            continue;
                        }

                        $periods[] = [
                            'weekday' => (int) $weekday,
                            'start' => (((int) $weekday - 1) * 1440) + $start,
                            'end' => (((int) $weekday - 1) * 1440) + $end,
                            'key' => "hours.$context.$weekday.intervals.$index",
                        ];
                    }

                    if (($day['intervals'] ?? []) === []) {
                        $validator->errors()->add("hours.$context.$weekday.intervals", 'Añade al menos una franja o marca el día como cerrado.');
                    }
                }

                foreach ($periods as $index => $period) {
                    foreach (array_slice($periods, $index + 1) as $other) {
                        foreach ([-10080, 0, 10080] as $shift) {
                            if (max($period['start'], $other['start'] + $shift) < min($period['end'], $other['end'] + $shift)) {
                                $validator->errors()->add($period['key'], 'Las franjas se solapan con otra franja del mismo horario.');
                                break 2;
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * @return array<string, array<int, array{iso_weekday: int, start_minute: int, end_minute: int}>>
     */
    public function normalizedHours(): array
    {
        $normalized = [];

        foreach ($this->validated('hours') as $context => $days) {
            if (! in_array($context, ['general', 'takeaway', 'delivery'], true)) {
                continue;
            }

            foreach ($days as $weekday => $day) {
                if (! filter_var($day['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }

                foreach ($day['intervals'] ?? [] as $interval) {
                    $start = $this->toMinutes($interval['opens_at']);
                    $end = $this->toMinutes($interval['closes_at']);

                    if ($end <= $start) {
                        $end += 1440;
                    }

                    $normalized[$context][] = [
                        'iso_weekday' => (int) $weekday,
                        'start_minute' => $start,
                        'end_minute' => $end,
                    ];
                }
            }
        }

        return $normalized;
    }

    private function toMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return ($hours * 60) + $minutes;
    }
}
