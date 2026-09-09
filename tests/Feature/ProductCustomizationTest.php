<?php

namespace Tests\Feature;

use App\CatalogPriceCalculator;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCustomizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_and_reorder_product_formats(): void
    {
        [$owner, $restaurant, $product] = $this->productContext();

        $this->actingAs($owner)->post(route('restaurant.menu.products.formats.store', [$restaurant, $product]), [
            'name' => 'Tapa', 'price' => '5,00', 'is_active' => 1, 'is_default' => 1,
        ])->assertRedirect();
        $format = $product->formats()->firstOrFail();
        $this->assertSame(500, $format->price_minor);

        $this->actingAs($owner)->post(route('restaurant.menu.products.formats.store', [$restaurant, $product]), [
            'name' => 'Ración', 'price' => '15,00', 'is_active' => 1,
        ])->assertRedirect();
        $second = $product->formats()->where('name', 'Ración')->firstOrFail();

        $this->actingAs($owner)->patch(route('restaurant.menu.products.formats.move', [$restaurant, $product, $second, 'up']))->assertRedirect();
        $this->assertSame($second->id, $product->formats()->orderBy('position')->first()->id);
    }

    public function test_last_format_cannot_be_archived(): void
    {
        [$owner, $restaurant, $product] = $this->productContext();
        $format = $product->formats()->create(['restaurant_id' => $restaurant->id, 'name' => 'Único', 'price_minor' => 1000, 'is_default' => true, 'is_active' => true]);

        $this->actingAs($owner)->delete(route('restaurant.menu.products.formats.destroy', [$restaurant, $product, $format]))->assertSessionHasErrors('format');
        $this->assertDatabaseHas('product_formats', ['id' => $format->id, 'deleted_at' => null]);
    }

    public function test_owner_can_create_shared_group_and_options(): void
    {
        [$owner, $restaurant, $product] = $this->productContext();

        $this->actingAs($owner)->post(route('restaurant.menu.modifier-groups.store', $restaurant), [
            'name' => 'Extras', 'min_selections' => 0, 'max_selections' => 3, 'allow_quantities' => 1, 'is_active' => 1,
        ])->assertRedirect();
        $group = ModifierGroup::query()->where('restaurant_id', $restaurant->id)->firstOrFail();

        $this->actingAs($owner)->post(route('restaurant.menu.modifier-groups.options.store', [$restaurant, $group]), [
            'name' => 'Bacon', 'supplement' => '1,50', 'max_quantity' => 3, 'instruction' => 'normal', 'is_active' => 1,
        ])->assertRedirect();
        $option = $group->options()->firstOrFail();
        $this->assertSame(150, $option->price_delta_minor);
    }

    public function test_group_can_be_attached_and_reused_by_multiple_products(): void
    {
        [$owner, $restaurant, $product] = $this->productContext();
        $second = $restaurant->products()->create(['category_id' => $product->category_id, 'name' => 'Segundo', 'price_minor' => 1200, 'is_active' => true, 'is_available' => true]);
        $group = $restaurant->modifierGroups()->create(['name' => 'Salsas', 'min_selections' => 0, 'max_selections' => 1, 'is_active' => true]);

        $this->actingAs($owner)->post(route('restaurant.menu.products.modifier-groups.store', [$restaurant, $product]), ['modifier_group_id' => $group->id])->assertRedirect();
        $this->actingAs($owner)->post(route('restaurant.menu.products.modifier-groups.store', [$restaurant, $second]), ['modifier_group_id' => $group->id])->assertRedirect();

        $this->assertSame(2, $group->productAssignments()->count());
    }

    public function test_member_cannot_modify_formats_or_groups(): void
    {
        [$owner, $restaurant, $product] = $this->productContext();
        $member = User::factory()->create();
        $restaurant->users()->attach($member, ['role' => 'member']);

        $this->actingAs($member)->post(route('restaurant.menu.modifier-groups.store', $restaurant), ['name' => 'No'])->assertForbidden();
        $this->actingAs($member)->post(route('restaurant.menu.products.formats.store', [$restaurant, $product]), ['name' => 'No', 'price' => '1'])->assertForbidden();
    }

    public function test_cross_tenant_group_cannot_be_attached(): void
    {
        [$owner, $restaurant, $product] = $this->productContext();
        $other = Restaurant::factory()->create();
        $group = $other->modifierGroups()->create(['name' => 'Ajeno', 'max_selections' => 1]);

        $this->actingAs($owner)->post(route('restaurant.menu.products.modifier-groups.store', [$restaurant, $product]), ['modifier_group_id' => $group->id])->assertSessionHasErrors('modifier_group_id');
        $this->assertDatabaseCount('product_modifier_groups', 0);
    }

    public function test_price_calculator_uses_format_supplements_and_quantities(): void
    {
        [, $restaurant, $product] = $this->productContext(['default_vat' => 10]);
        $format = $product->formats()->create(['restaurant_id' => $restaurant->id, 'name' => 'Familiar', 'price_minor' => 1500, 'is_default' => true, 'is_active' => true]);
        $group = $restaurant->modifierGroups()->create(['name' => 'Extras', 'min_selections' => 0, 'max_selections' => 3, 'allow_quantities' => true, 'is_active' => true]);
        $option = $group->options()->create(['restaurant_id' => $restaurant->id, 'name' => 'Queso', 'price_delta_minor' => 100, 'max_quantity' => 3, 'is_active' => true]);
        $assignment = $product->modifierGroupAssignments()->create(['restaurant_id' => $restaurant->id, 'modifier_group_id' => $group->id, 'position' => 0]);

        $result = (new CatalogPriceCalculator)->calculate($product, $format, [$assignment->id => [$option->id => 2]]);

        $this->assertSame(1700, $result['total_minor']);
        $this->assertSame('10.00', $result['vat_rate']);
        $this->assertSame('EUR', $result['currency']);
    }

    public function test_price_calculator_rejects_required_group_without_selection(): void
    {
        [, $restaurant, $product] = $this->productContext();
        $group = $restaurant->modifierGroups()->create(['name' => 'Punto', 'min_selections' => 1, 'max_selections' => 1, 'is_active' => true]);
        $product->modifierGroupAssignments()->create(['restaurant_id' => $restaurant->id, 'modifier_group_id' => $group->id]);

        $this->expectException(\InvalidArgumentException::class);
        (new CatalogPriceCalculator)->calculate($product, null);
    }

    public function test_shared_group_duplication_keeps_original_assignments_separate(): void
    {
        [$owner, $restaurant, $product] = $this->productContext();
        $group = $restaurant->modifierGroups()->create(['name' => 'Compartido', 'max_selections' => 1, 'is_active' => true]);
        $group->options()->create(['restaurant_id' => $restaurant->id, 'name' => 'Normal', 'is_active' => true]);
        $product->modifierGroupAssignments()->create(['restaurant_id' => $restaurant->id, 'modifier_group_id' => $group->id]);

        $this->actingAs($owner)->post(route('restaurant.menu.products.modifier-groups.duplicate', [$restaurant, $product, $group]))->assertRedirect();
        $this->assertSame(2, $restaurant->modifierGroups()->count());
        $this->assertSame(1, $product->modifierGroupAssignments()->count());
    }

    public function test_owner_can_open_modifier_library_and_product_edit_sections(): void
    {
        [$owner, $restaurant, $product] = $this->productContext();

        $this->actingAs($owner)->get(route('restaurant.menu.modifier-groups.index', $restaurant))->assertOk();
        $this->actingAs($owner)->get(route('restaurant.menu.products.edit', [$restaurant, $product]))->assertOk()->assertSee('Formatos y precios')->assertSee('Modificadores');
    }

    /** @param array<string, mixed> $attributes @return array{0: User, 1: Restaurant, 2: Product} */
    private function productContext(array $attributes = []): array
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create(array_merge(['default_vat' => 10], $attributes));
        $restaurant->users()->attach($owner, ['role' => 'owner']);
        $category = $restaurant->categories()->create(['name' => 'Principales', 'is_active' => true]);
        $product = $restaurant->products()->create(['category_id' => $category->id, 'name' => 'Producto', 'price_minor' => 1200, 'is_active' => true, 'is_available' => true]);

        return [$owner, $restaurant, $product];
    }
}
