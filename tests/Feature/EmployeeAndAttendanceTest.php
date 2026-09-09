<?php

namespace Tests\Feature;

use App\AttendanceService;
use App\Models\Employee;
use App\Models\OperationalRole;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WorkInterval;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeAndAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_multifunction_employee_with_optional_user_link(): void
    {
        [$owner, $restaurant] = $this->restaurant();
        $service = OperationalRole::create(['restaurant_id' => $restaurant->id, 'name' => 'Camarero', 'code' => 'service']);
        $manager = OperationalRole::create(['restaurant_id' => $restaurant->id, 'name' => 'Encargado', 'code' => 'manager']);

        $this->actingAs($owner)->post(route('restaurant.staff.employees.store', $restaurant), [
            'first_name' => 'Mario', 'last_name' => 'García', 'display_name' => 'MARIO', 'roles' => [$service->id, $manager->id], 'pin' => '4821', 'user_id' => $owner->id, 'is_active' => 1,
        ])->assertRedirect();

        $employee = Employee::query()->firstOrFail();
        $this->assertCount(2, $employee->operationalRoles);
        $this->assertTrue(Hash::check('4821', $employee->pin_hash));
        $this->assertSame($owner->id, $employee->user_id);
        $this->actingAs($owner)->get(route('restaurant.staff.employees.index', $restaurant))->assertOk();
        $this->actingAs($owner)->get(route('restaurant.staff.employees.show', [$restaurant, $employee]))->assertOk();
    }

    public function test_members_can_view_but_not_manage_employees(): void
    {
        [$owner, $restaurant] = $this->restaurant();
        $member = User::factory()->create();
        $restaurant->users()->attach($member, ['role' => 'member']);
        $this->actingAs($member)->get(route('restaurant.staff.employees.index', $restaurant))->assertOk();
        $this->actingAs($member)->post(route('restaurant.staff.employees.store', $restaurant), [])->assertForbidden();
    }

    public function test_pin_is_unique_inside_restaurant_and_inactive_employee_cannot_use_terminal(): void
    {
        [$owner, $restaurant] = $this->restaurant();
        $role = OperationalRole::create(['restaurant_id' => $restaurant->id, 'name' => 'Camarero', 'code' => 'service']);
        $payload = ['first_name' => 'Ana', 'display_name' => 'ANA', 'roles' => [$role->id], 'pin' => '4821', 'is_active' => 1];
        $this->actingAs($owner)->post(route('restaurant.staff.employees.store', $restaurant), $payload)->assertRedirect();
        $this->actingAs($owner)->post(route('restaurant.staff.employees.store', $restaurant), [...$payload, 'display_name' => 'OTRA'])->assertStatus(422);
        $employee = Employee::query()->firstOrFail();
        $employee->update(['is_active' => false, 'pin_fingerprint' => null]);
        $this->actingAs($owner)->post(route('restaurant.staff.clock.identify', $restaurant), ['employee_id' => $employee->id, 'pin' => '4821'])->assertSessionHasErrors('pin');
    }

    public function test_terminal_creates_and_closes_multiple_intervals(): void
    {
        [$owner, $restaurant] = $this->restaurant();
        $role = OperationalRole::create(['restaurant_id' => $restaurant->id, 'name' => 'Camarero', 'code' => 'service']);
        $employee = Employee::create(['restaurant_id' => $restaurant->id, 'first_name' => 'Ana', 'display_name' => 'ANA', 'is_active' => true, 'pin_hash' => Hash::make('4821'), 'pin_fingerprint' => AttendanceService::fingerprint($restaurant->id, '4821')]);
        $employee->operationalRoles()->attach($role);
        CarbonImmutable::setTestNow('2026-09-07 07:03:00');
        $this->actingAs($owner)->post(route('restaurant.staff.clock.identify', $restaurant), ['employee_id' => $employee->id, 'pin' => '4821'])->assertRedirect();
        $this->actingAs($owner)->post(route('restaurant.staff.clock.punch', $restaurant))->assertRedirect()->assertSessionHas('clock_status', 'Entrada registrada a 09:03');
        CarbonImmutable::setTestNow('2026-09-07 12:01:00');
        $this->actingAs($owner)->post(route('restaurant.staff.clock.identify', $restaurant), ['employee_id' => $employee->id, 'pin' => '4821']);
        $this->actingAs($owner)->post(route('restaurant.staff.clock.punch', $restaurant))->assertSessionHas('clock_status', 'Salida registrada a 14:01');
        $this->assertCount(1, $employee->workIntervals()->get());
        $this->assertNotNull($employee->workIntervals()->first()->ended_at);
        CarbonImmutable::setTestNow();
    }

    public function test_correction_is_audited_and_cross_tenant_interval_is_not_editable(): void
    {
        [$owner, $restaurant] = $this->restaurant();
        $employee = Employee::create(['restaurant_id' => $restaurant->id, 'first_name' => 'Ana', 'display_name' => 'ANA', 'is_active' => true]);
        $interval = WorkInterval::create(['restaurant_id' => $restaurant->id, 'employee_id' => $employee->id, 'started_at' => '2026-09-07 07:03:00', 'ended_at' => null, 'source' => 'pin']);
        $this->actingAs($owner)->patch(route('restaurant.staff.attendance.correct', [$restaurant, $interval]), ['started_at' => '2026-09-07T09:03', 'ended_at' => '2026-09-07T14:00', 'reason' => 'Olvidó fichar salida'])->assertRedirect();
        $this->assertDatabaseHas('work_interval_corrections', ['work_interval_id' => $interval->id, 'reason' => 'Olvidó fichar salida', 'actor_name' => $owner->name]);
        $other = Restaurant::factory()->create();
        $otherEmployee = Employee::create(['restaurant_id' => $other->id, 'first_name' => 'Otro', 'display_name' => 'OTRO', 'is_active' => true]);
        $otherInterval = WorkInterval::create(['restaurant_id' => $other->id, 'employee_id' => $otherEmployee->id, 'started_at' => now(), 'source' => 'pin']);
        $this->actingAs($owner)->get(route('restaurant.staff.attendance.edit', [$restaurant, $otherInterval]))->assertNotFound();
    }

    public function test_owner_can_add_an_audited_manual_interval(): void
    {
        [$owner, $restaurant] = $this->restaurant();
        $employee = Employee::create(['restaurant_id' => $restaurant->id, 'first_name' => 'Ana', 'display_name' => 'ANA', 'is_active' => true]);
        $this->actingAs($owner)->post(route('restaurant.staff.attendance.add', $restaurant), ['employee_id' => $employee->id, 'started_at' => '2026-09-07T09:00', 'ended_at' => '2026-09-07T14:00', 'reason' => 'Olvidó fichar'])->assertRedirect();
        $this->assertDatabaseHas('work_intervals', ['employee_id' => $employee->id, 'source' => 'manual']);
        $this->assertDatabaseHas('work_interval_corrections', ['actor_name' => $owner->name, 'previous_started_at' => null]);
    }

    private function restaurant(): array
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create();
        $owner->restaurants()->attach($restaurant, ['role' => 'owner']);

        return [$owner, $restaurant];
    }
}
