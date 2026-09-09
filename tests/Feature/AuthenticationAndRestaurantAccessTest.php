<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationAndRestaurantAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/')->assertRedirectToRoute('login');
        $this->get('/app')->assertRedirectToRoute('login');
    }

    public function test_user_can_log_in_and_reach_their_restaurant_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => 'password',
        ]);
        $restaurant = Restaurant::factory()->create(['slug' => 'mi-restaurante']);
        $user->restaurants()->attach($restaurant, ['role' => 'owner']);

        $this->post('/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->assertRedirectToRoute('app.index');

        $this->get('/app/mi-restaurante')
            ->assertOk()
            ->assertSee($restaurant->name)
            ->assertSee('Buenos días');
    }

    public function test_user_cannot_access_a_restaurant_they_do_not_belong_to(): void
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['slug' => 'otro-restaurante']);

        $this->actingAs($user)
            ->get('/app/otro-restaurante')
            ->assertForbidden();
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirectToRoute('login');

        $this->assertGuest();
    }
}
