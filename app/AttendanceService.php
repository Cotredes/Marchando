<?php

namespace App;

use App\Models\Employee;
use App\Models\WorkInterval;
use App\Models\WorkIntervalCorrection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AttendanceService
{
    public function punch(Employee $employee, ?int $userId = null): WorkInterval
    {
        return DB::transaction(function () use ($employee, $userId): WorkInterval {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            abort_unless($employee->is_active && $employee->restaurant_id, 422, 'Este empleado no está activo.');
            $open = $employee->workIntervals()->whereNull('ended_at')->lockForUpdate()->first();
            if ($open) {
                $open->update(['ended_at' => now(), 'source' => $userId ? 'panel' : 'pin']);

                return $open->fresh();
            }

            return $employee->workIntervals()->create([
                'restaurant_id' => $employee->restaurant_id,
                'started_at' => now(),
                'source' => $userId ? 'panel' : 'pin',
                'created_by_user_id' => $userId,
            ]);
        });
    }

    public function correct(WorkInterval $interval, CarbonImmutable $startedAt, ?CarbonImmutable $endedAt, string $reason, int $userId, string $actorName): WorkInterval
    {
        if ($endedAt && $endedAt->lessThanOrEqualTo($startedAt)) {
            throw new RuntimeException('La salida debe ser posterior a la entrada.');
        }

        return DB::transaction(function () use ($interval, $startedAt, $endedAt, $reason, $userId, $actorName): WorkInterval {
            $interval = WorkInterval::query()->lockForUpdate()->findOrFail($interval->id);
            $other = WorkInterval::query()->where('employee_id', $interval->employee_id)->whereKeyNot($interval->id)
                ->where('started_at', '<', $endedAt ?: '9999-12-31 23:59:59')
                ->where(function ($query) use ($startedAt) {
                    $query->whereNull('ended_at')->orWhere('ended_at', '>', $startedAt);
                })->exists();
            if ($other) {
                throw new RuntimeException('El intervalo se solapa con otra jornada.');
            }
            WorkIntervalCorrection::create([
                'restaurant_id' => $interval->restaurant_id, 'work_interval_id' => $interval->id, 'user_id' => $userId,
                'actor_name' => $actorName, 'previous_started_at' => $interval->started_at, 'previous_ended_at' => $interval->ended_at,
                'corrected_started_at' => $startedAt, 'corrected_ended_at' => $endedAt, 'reason' => $reason,
            ]);
            $interval->update(['started_at' => $startedAt, 'ended_at' => $endedAt, 'source' => 'correction']);

            return $interval->fresh();
        });
    }

    public function addManual(Employee $employee, CarbonImmutable $startedAt, ?CarbonImmutable $endedAt, string $reason, int $userId, string $actorName): WorkInterval
    {
        if ($endedAt && $endedAt->lessThanOrEqualTo($startedAt)) {
            throw new RuntimeException('La salida debe ser posterior a la entrada.');
        }

        return DB::transaction(function () use ($employee, $startedAt, $endedAt, $reason, $userId, $actorName): WorkInterval {
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $overlap = $employee->workIntervals()->where('started_at', '<', $endedAt ?: '9999-12-31 23:59:59')->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $startedAt))->exists();
            if ($overlap) {
                throw new RuntimeException('El intervalo se solapa con otra jornada.');
            }
            $interval = $employee->workIntervals()->create(['restaurant_id' => $employee->restaurant_id, 'started_at' => $startedAt, 'ended_at' => $endedAt, 'source' => 'manual', 'created_by_user_id' => $userId]);
            WorkIntervalCorrection::create(['restaurant_id' => $employee->restaurant_id, 'work_interval_id' => $interval->id, 'user_id' => $userId, 'actor_name' => $actorName, 'previous_started_at' => null, 'previous_ended_at' => null, 'corrected_started_at' => $startedAt, 'corrected_ended_at' => $endedAt, 'reason' => $reason]);

            return $interval;
        });
    }

    public static function fingerprint(int $restaurantId, string $pin): string
    {
        return hash_hmac('sha256', $restaurantId.'|'.$pin, (string) config('app.key'));
    }
}
