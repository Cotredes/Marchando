<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Order;
use App\Models\PlatformAudit;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformOwnerTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(string $email = 'owner@marchando.test'): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->forceFill(['is_platform_owner' => true])->save();

        return $user->fresh();
    }

    private function attach(User $user, Restaurant $restaurant, string $role = 'member'): void
    {
        $user->restaurants()->attach($restaurant, ['role' => $role]);
    }

    public function test_ownership_comes_from_the_flag_not_from_the_email_text(): void
    {
        $sameEmail = User::factory()->create(['email' => 'angel@munasa.es']);
        $otherEmail = $this->makeOwner('otra-cuenta@marchando.test');

        $this->assertFalse($sameEmail->isPlatformOwner());
        $this->assertTrue($otherEmail->isPlatformOwner());

        $this->actingAs($sameEmail)->get('/admin')->assertForbidden();
        $this->actingAs($otherEmail)->get('/admin')->assertOk();
    }

    public function test_owner_can_enter_admin_and_sees_its_sections(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->get('/admin')
            ->assertOk()
            ->assertSee('Marchando')
            ->assertSee('Restaurantes')
            ->assertSee('Usuarios')
            ->assertSee('Propietario de Marchando');
    }

    public function test_owner_without_memberships_lands_on_admin_from_app_index(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->get('/app')->assertRedirectToRoute('admin.dashboard');
    }

    public function test_owner_can_list_and_create_restaurants(): void
    {
        $owner = $this->makeOwner();
        $restaurant = Restaurant::factory()->create(['name' => 'Casa Base']);

        $this->actingAs($owner)->get('/admin/restaurantes')->assertOk()->assertSee('Casa Base');

        $response = $this->actingAs($owner)->post('/admin/restaurantes', [
            'name' => 'Restaurante Prueba',
            'timezone' => 'Europe/Madrid',
            'currency' => 'eur',
        ]);

        $created = Restaurant::query()->where('slug', 'restaurante-prueba')->firstOrFail();
        $response->assertRedirectToRoute('admin.restaurants.edit', $created);
        $this->assertTrue($created->is_active);
        $this->assertSame('EUR', $created->currency);
        $this->assertTrue(PlatformAudit::query()->where('action', 'restaurant.created')->where('restaurant_id', $created->id)->exists());
    }

    public function test_owner_can_enter_any_tenant_and_reach_every_module_without_membership(): void
    {
        $owner = $this->makeOwner();
        $restaurant = Restaurant::factory()->create(['slug' => 'local-centro']);

        $pages = [
            'dashboard', 'orders', 'pos', 'kitchen', 'menu', 'restaurant',
            'staff', 'reservations', 'analytics', 'integrations', 'settings',
        ];

        foreach ($pages as $page) {
            $this->actingAs($owner)
                ->get(route('restaurant.'.$page, $restaurant))
                ->assertOk("El propietario no pudo entrar en {$page}");
        }

        // Páginas restringidas a owner dentro del tenant.
        $this->actingAs($owner)->get(route('restaurant.restaurant.manage', $restaurant))->assertOk();
        $this->actingAs($owner)->get(route('restaurant.audit', $restaurant))->assertOk();
        $this->actingAs($owner)->get(route('restaurant.integrations', $restaurant))->assertOk();

        // Escritura sensible sin membership: crear zona.
        $this->actingAs($owner)
            ->post(route('restaurant.restaurant.zones.store', $restaurant), ['name' => 'Salón'])
            ->assertRedirect();
        $this->assertDatabaseHas('zones', ['restaurant_id' => $restaurant->id, 'name' => 'Salón']);
    }

    public function test_owner_switches_tenants_without_mixing_data(): void
    {
        $owner = $this->makeOwner();
        $first = Restaurant::factory()->create(['slug' => 'casa-a']);
        $second = Restaurant::factory()->create(['slug' => 'casa-b']);

        $categoryA = $first->categories()->create(['name' => 'Solo A', 'position' => 0]);
        $categoryB = $second->categories()->create(['name' => 'Solo B', 'position' => 0]);
        $first->products()->create(['category_id' => $categoryA->id, 'name' => 'Plato A', 'position' => 0, 'price_minor' => 1000]);
        $second->products()->create(['category_id' => $categoryB->id, 'name' => 'Plato B', 'position' => 0, 'price_minor' => 1000]);

        $this->actingAs($owner)->get(route('restaurant.menu', $first))
            ->assertOk()->assertSee('Plato A')->assertDontSee('Plato B');
        $this->actingAs($owner)->get(route('restaurant.menu', $second))
            ->assertOk()->assertSee('Plato B')->assertDontSee('Plato A');

        $this->actingAs($owner)->get(route('admin.restaurants.enter', $second))
            ->assertRedirectToRoute('restaurant.dashboard', $second);
    }

    public function test_normal_member_cannot_access_admin(): void
    {
        $restaurant = Restaurant::factory()->create();
        $member = User::factory()->create();
        $this->attach($member, $restaurant);

        $this->actingAs($member)->get('/admin')->assertForbidden();
        $this->actingAs($member)->get('/admin/usuarios')->assertForbidden();
        $this->actingAs($member)->get('/admin/restaurantes')->assertForbidden();
        $this->actingAs($member)->post('/admin/usuarios', [])->assertForbidden();
    }

    public function test_restaurant_owner_cannot_reach_other_tenants_or_admin(): void
    {
        $first = Restaurant::factory()->create(['slug' => 'casa-a']);
        $second = Restaurant::factory()->create(['slug' => 'casa-b']);
        $owner = User::factory()->create();
        $this->attach($owner, $first, 'owner');

        $this->actingAs($owner)->get(route('restaurant.dashboard', $second))->assertForbidden();
        $this->actingAs($owner)->get('/admin')->assertForbidden();
        $this->actingAs($owner)->get(route('restaurant.dashboard', $first))->assertOk()->assertDontSee('Administración');
    }

    public function test_admin_can_create_a_user_and_assign_a_restaurant_role(): void
    {
        $owner = $this->makeOwner();
        $restaurant = Restaurant::factory()->create();

        $this->actingAs($owner)->post('/admin/usuarios', [
            'name' => 'María',
            'email' => 'maria@casamarchando.es',
            'password' => 'secreta123',
            'password_confirmation' => 'secreta123',
            'restaurant_id' => $restaurant->id,
            'role' => 'owner',
        ])->assertRedirect();

        $maria = User::where('email', 'maria@casamarchando.es')->firstOrFail();
        $this->assertFalse($maria->isPlatformOwner());
        $this->assertTrue($maria->is_active);
        $this->assertSame('owner', $maria->restaurants()->whereKey($restaurant)->first()->pivot->role);
        $this->assertTrue(PlatformAudit::query()->where('action', 'user.created')->exists());

        // María administra su restaurante pero no entra en administración global.
        $this->actingAs($maria)->get(route('restaurant.settings', $restaurant))->assertOk();
        $this->actingAs($maria)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_change_roles_and_remove_access_keeping_history(): void
    {
        $owner = $this->makeOwner();
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create();
        $this->attach($user, $restaurant);

        $employee = Employee::query()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $user->id,
            'first_name' => 'Ana',
            'display_name' => 'ANA',
        ]);

        $this->actingAs($owner)
            ->patch(route('admin.users.memberships.update', [$user, $restaurant]), ['role' => 'owner'])
            ->assertRedirect();
        $this->assertSame('owner', $user->restaurants()->whereKey($restaurant)->first()->pivot->role);

        $this->actingAs($owner)
            ->delete(route('admin.users.memberships.detach', [$user, $restaurant]))
            ->assertRedirect();
        $this->assertFalse($user->fresh()->restaurants()->whereKey($restaurant)->exists());

        // El histórico no se toca: el empleado vinculado sigue existiendo.
        $this->assertTrue(Employee::query()->whereKey($employee->id)->where('user_id', $user->id)->exists());
    }

    public function test_deactivated_user_cannot_log_in_but_history_remains(): void
    {
        $owner = $this->makeOwner();
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create(['password' => 'secreta123']);
        $this->attach($user, $restaurant, 'owner');
        $order = $restaurant->orders()->create(['channel' => 'dine_in', 'status' => 'open', 'currency' => 'EUR', 'business_date' => now()->toDateString(), 'opened_at' => now(), 'opened_by_user_id' => $user->id]);

        $this->actingAs($owner)->post(route('admin.users.toggle', $user))->assertRedirect();
        $this->assertFalse($user->fresh()->is_active);

        $this->post('/logout');
        // El login falla con el mensaje genérico, sin revelar el motivo.
        $this->post('/login', ['email' => $user->email, 'password' => 'secreta123'])
            ->assertInvalid(['email']);
        $this->assertGuest();

        $this->assertTrue(Order::query()->whereKey($order->id)->where('opened_by_user_id', $user->id)->exists());
    }

    public function test_platform_owner_cannot_be_deactivated_from_the_ui(): void
    {
        $owner = $this->makeOwner('angel@munasa.es');

        $this->actingAs($owner)->post(route('admin.users.toggle', $owner))->assertStatus(422);
        $this->assertTrue($owner->fresh()->is_active);
    }

    public function test_platform_owner_memberships_cannot_be_touched_from_the_ui(): void
    {
        $owner = $this->makeOwner('angel@munasa.es');
        $restaurant = Restaurant::factory()->create();
        $this->attach($owner, $restaurant, 'owner');

        $this->actingAs($owner)
            ->patch(route('admin.users.memberships.update', [$owner, $restaurant]), ['role' => 'member'])
            ->assertStatus(422);
        $this->actingAs($owner)
            ->delete(route('admin.users.memberships.detach', [$owner, $restaurant]))
            ->assertStatus(422);

        $this->assertSame('owner', $owner->restaurants()->whereKey($restaurant)->first()->pivot->role);
    }

    public function test_admin_forms_cannot_grant_platform_ownership(): void
    {
        $owner = $this->makeOwner();
        $restaurant = Restaurant::factory()->create();

        $this->actingAs($owner)->post('/admin/usuarios', [
            'name' => 'Intruso',
            'email' => 'intruso@example.com',
            'password' => 'secreta123',
            'password_confirmation' => 'secreta123',
            'restaurant_id' => $restaurant->id,
            'role' => 'owner',
            'is_platform_owner' => true,
        ])->assertRedirect();

        $intruder = User::where('email', 'intruso@example.com')->firstOrFail();
        $this->assertFalse($intruder->fresh()->isPlatformOwner());

        $this->actingAs($owner)->patch(route('admin.users.update', $intruder), [
            'name' => 'Intruso',
            'email' => 'intruso@example.com',
            'is_platform_owner' => true,
        ])->assertRedirect();
        $this->assertFalse($intruder->fresh()->isPlatformOwner());
        $this->actingAs($intruder)->get('/admin')->assertForbidden();
    }

    public function test_inactive_restaurant_blocks_members_and_public_but_keeps_owner_and_history(): void
    {
        $owner = $this->makeOwner();
        $restaurant = Restaurant::factory()->create(['slug' => 'local-cierre']);
        $member = User::factory()->create();
        $this->attach($member, $restaurant);
        $category = $restaurant->categories()->create(['name' => 'Carta', 'position' => 0]);
        $restaurant->products()->create(['category_id' => $category->id, 'name' => 'Plato histórico', 'position' => 0, 'price_minor' => 1000]);
        $order = $restaurant->orders()->create(['channel' => 'dine_in', 'status' => 'open', 'currency' => 'EUR', 'business_date' => now()->toDateString(), 'opened_at' => now()]);

        $this->actingAs($owner)->post(route('admin.restaurants.toggle', $restaurant))->assertRedirect();
        $this->assertFalse($restaurant->fresh()->is_active);

        $this->actingAs($member)->get(route('restaurant.dashboard', $restaurant))->assertForbidden();
        $this->get(route('public.catalog', [$restaurant, 'takeaway']))->assertNotFound();
        $this->actingAs($owner)->get(route('restaurant.dashboard', $restaurant))->assertOk();

        // El histórico sigue intacto y el propietario puede reactivar.
        $this->assertDatabaseHas('products', ['restaurant_id' => $restaurant->id, 'name' => 'Plato histórico']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'restaurant_id' => $restaurant->id]);
        $this->actingAs($owner)->post(route('admin.restaurants.toggle', $restaurant))->assertRedirect();
        $this->actingAs($member)->get(route('restaurant.dashboard', $restaurant))->assertOk();
    }

    public function test_tenant_dashboard_marks_the_platform_owner_and_links_admin(): void
    {
        $restaurant = Restaurant::factory()->create();
        $owner = $this->makeOwner();
        $member = User::factory()->create();
        $this->attach($member, $restaurant);

        $this->actingAs($owner)->get(route('restaurant.dashboard', $restaurant))
            ->assertOk()
            ->assertSee('Propietario de Marchando')
            ->assertSee('Administrar Marchando');

        $this->actingAs($member)->get(route('restaurant.dashboard', $restaurant))
            ->assertOk()
            ->assertDontSee('Propietario de Marchando')
            ->assertDontSee('Administrar Marchando');
    }

    public function test_there_is_no_route_to_delete_users_or_restaurants(): void
    {
        $names = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter();

        $this->assertFalse($names->contains('admin.users.destroy'), 'La administración no debe poder borrar usuarios.');
        $this->assertFalse($names->contains('admin.restaurants.destroy'), 'La administración no debe poder borrar restaurantes.');
    }
}
