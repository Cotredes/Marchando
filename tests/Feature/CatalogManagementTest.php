<?php

namespace Tests\Feature;

use App\Models\Allergen;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_member_can_view_catalog(): void
    {
        [$owner, $restaurant] = $this->ownedRestaurant();

        $this->get(route('restaurant.menu', $restaurant))->assertRedirectToRoute('login');
        $this->actingAs($owner)->get(route('restaurant.menu', $restaurant))->assertOk()->assertSee('Carta');
    }

    public function test_member_can_view_but_not_modify_catalog(): void
    {
        $member = User::factory()->create();
        $restaurant = Restaurant::factory()->create();
        $restaurant->users()->attach($member, ['role' => 'member']);

        $this->actingAs($member)
            ->post(route('restaurant.menu.categories.store', $restaurant), ['name' => 'Privado'])
            ->assertForbidden();
    }

    public function test_owner_can_create_category_and_product_with_exact_money_and_allergens(): void
    {
        [$owner, $restaurant] = $this->ownedRestaurant(['default_vat' => 10]);
        $allergens = Allergen::factory()->count(3)->create();

        $this->actingAs($owner)->post(route('restaurant.menu.categories.store', $restaurant), [
            'name' => 'Entrantes',
            'is_active' => 1,
            'available_dine_in' => 1,
        ])->assertRedirect();
        $category = Category::query()->where('restaurant_id', $restaurant->id)->firstOrFail();

        $this->actingAs($owner)->post(route('restaurant.menu.products.store', $restaurant), [
            'name' => 'Croquetas',
            'category_id' => $category->id,
            'price' => '9,50',
            'cost' => '2.10',
            'is_active' => 1,
            'is_available' => 1,
            'available_dine_in' => 1,
            'available_takeaway' => 1,
            'allergen_ids' => $allergens->pluck('id')->all(),
        ])->assertRedirect();

        $product = Product::query()->firstOrFail();
        $this->assertSame(950, $product->price_minor);
        $this->assertSame(210, $product->cost_minor);
        $this->assertNull($product->vat_rate);
        $this->assertCount(3, $product->allergens);
    }

    public function test_category_from_another_restaurant_cannot_be_used(): void
    {
        [$owner, $restaurant] = $this->ownedRestaurant(['default_vat' => 10]);
        $other = Restaurant::factory()->create();
        $category = $other->categories()->create(['name' => 'Ajena']);

        $this->actingAs($owner)
            ->post(route('restaurant.menu.products.store', $restaurant), [
                'name' => 'Intrusión',
                'category_id' => $category->id,
                'price' => '5.00',
                'vat_rate' => 10,
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseMissing('products', ['name' => 'Intrusión']);
    }

    public function test_product_availability_can_be_changed_without_deleting_it(): void
    {
        [$owner, $restaurant] = $this->ownedRestaurant();
        $category = $restaurant->categories()->create(['name' => 'Postres']);
        $product = $restaurant->products()->create([
            'category_id' => $category->id,
            'name' => 'Tarta',
            'price_minor' => 650,
            'vat_rate' => 10,
        ]);

        $this->actingAs($owner)->patch(route('restaurant.menu.products.availability', [$restaurant, $product]), ['is_available' => 0])->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_available' => 0]);
    }

    public function test_effective_channel_availability_requires_restaurant_category_and_product(): void
    {
        [$owner, $restaurant] = $this->ownedRestaurant(['takeaway_enabled' => true]);
        $category = $restaurant->categories()->create(['name' => 'Burgers', 'available_takeaway' => true]);
        $product = $restaurant->products()->create([
            'category_id' => $category->id,
            'name' => 'Burger',
            'price_minor' => 1200,
            'is_active' => true,
            'is_available' => true,
            'available_takeaway' => true,
        ]);
        $product->load(['restaurant', 'category']);

        $this->assertTrue($product->isEffectivelyAvailable('takeaway'));
        $category->update(['available_takeaway' => false]);
        $product->load('category');
        $this->assertFalse($product->isEffectivelyAvailable('takeaway'));
    }

    public function test_category_with_products_cannot_be_archived_and_empty_category_can(): void
    {
        [$owner, $restaurant] = $this->ownedRestaurant();
        $category = $restaurant->categories()->create(['name' => 'Con productos']);
        $restaurant->products()->create(['category_id' => $category->id, 'name' => 'Producto', 'price_minor' => 500]);

        $this->actingAs($owner)->delete(route('restaurant.menu.categories.destroy', [$restaurant, $category]))->assertSessionHasErrors('category');
        $empty = $restaurant->categories()->create(['name' => 'Vacía']);
        $this->actingAs($owner)->delete(route('restaurant.menu.categories.destroy', [$restaurant, $empty]))->assertRedirect();
        $this->assertSoftDeleted('categories', ['id' => $empty->id]);
    }

    public function test_owner_can_reorder_categories_and_products(): void
    {
        [$owner, $restaurant] = $this->ownedRestaurant();
        $first = $restaurant->categories()->create(['name' => 'Primera', 'position' => 0]);
        $second = $restaurant->categories()->create(['name' => 'Segunda', 'position' => 10]);

        $this->actingAs($owner)->patch(route('restaurant.menu.categories.move', [$restaurant, $second, 'up']))->assertRedirect();
        $this->assertSame($second->id, $restaurant->categories()->orderBy('position')->first()->id);

        $one = $restaurant->products()->create(['category_id' => $second->id, 'name' => 'Uno', 'price_minor' => 500, 'position' => 0]);
        $two = $restaurant->products()->create(['category_id' => $second->id, 'name' => 'Dos', 'price_minor' => 600, 'position' => 10]);
        $this->actingAs($owner)->patch(route('restaurant.menu.products.move', [$restaurant, $two, 'up']))->assertRedirect();
        $this->assertSame($two->id, $second->products()->orderBy('position')->first()->id);
        $this->assertTrue($first->exists);
        $this->assertTrue($one->exists);
    }

    public function test_product_image_is_stored_on_the_public_disk(): void
    {
        Storage::fake('public');
        [$owner, $restaurant] = $this->ownedRestaurant(['default_vat' => 10]);
        $category = $restaurant->categories()->create(['name' => 'Fotos']);

        $this->actingAs($owner)->post(route('restaurant.menu.products.store', $restaurant), [
            'name' => 'Producto con foto',
            'category_id' => $category->id,
            'price' => '7.50',
            'image' => UploadedFile::fake()->image('producto.jpg'),
        ])->assertRedirect();

        $product = Product::query()->where('name', 'Producto con foto')->firstOrFail();
        $this->assertNotNull($product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
    }

    public function test_catalog_factories_keep_category_and_product_in_the_same_tenant(): void
    {
        $product = Product::factory()->create();

        $this->assertSame($product->restaurant_id, $product->category->restaurant_id);
    }

    public function test_owner_can_open_category_and_product_forms(): void
    {
        [$owner, $restaurant] = $this->ownedRestaurant();
        $category = $restaurant->categories()->create(['name' => 'Formularios']);
        $product = $restaurant->products()->create(['category_id' => $category->id, 'name' => 'Producto', 'price_minor' => 500]);

        $this->actingAs($owner)->get(route('restaurant.menu.categories.create', $restaurant))->assertOk();
        $this->actingAs($owner)->get(route('restaurant.menu.categories.edit', [$restaurant, $category]))->assertOk();
        $this->actingAs($owner)->get(route('restaurant.menu.products.create', $restaurant))->assertOk();
        $this->actingAs($owner)->get(route('restaurant.menu.products.edit', [$restaurant, $product]))->assertOk();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: User, 1: Restaurant}
     */
    private function ownedRestaurant(array $attributes = []): array
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create($attributes);
        $restaurant->users()->attach($owner, ['role' => 'owner']);

        return [$owner, $restaurant];
    }
}
