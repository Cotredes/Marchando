<?php

namespace Database\Seeders;

use App\AttendanceService;
use App\Models\ActiveTableOrder;
use App\Models\Allergen;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Employee;
use App\Models\KitchenStation;
use App\Models\OperationalRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductFormat;
use App\Models\ProductModifierGroup;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WorkInterval;
use App\Models\Zone;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(AllergenSeeder::class);

        $user = User::query()->updateOrCreate(['email' => 'admin@marchando.test'], [
            'name' => 'Test User',
            'password' => 'password',
        ]);

        $restaurant = Restaurant::query()->updateOrCreate(
            ['slug' => 'casa-marchando'],
            [
                'name' => 'Casa Marchando',
                'establishment_type' => 'restaurant',
                'description' => 'Cocina de mercado y producto local.',
                'contact_email' => 'hola@casamarchando.test',
                'phone' => '+34 900 000 000',
                'address' => 'Calle del Mercado, 12',
                'postal_code' => '28001',
                'city' => 'Madrid',
                'province' => 'Madrid',
                'country' => 'España',
                'timezone' => 'Europe/Madrid',
                'legal_name' => 'Casa Marchando S.L.',
                'tax_id' => 'B00000000',
                'default_vat' => 10,
                'currency' => 'EUR',
                'dine_in_enabled' => true,
                'takeaway_enabled' => true,
                'takeaway_prep_minutes' => 25,
                'takeaway_use_general_schedule' => false,
                'delivery_enabled' => true,
                'delivery_radius_km' => 5,
                'delivery_fee' => 2.5,
                'delivery_minimum_order' => 15,
                'delivery_prep_minutes' => 40,
                'delivery_use_general_schedule' => false,
            ],
        );

        $user->restaurants()->syncWithoutDetaching([$restaurant->id => ['role' => 'owner']]);

        $restaurant->openingHours()->delete();
        $restaurant->openingHours()->createMany([
            ['context' => 'general', 'iso_weekday' => 1, 'start_minute' => 780, 'end_minute' => 960],
            ['context' => 'general', 'iso_weekday' => 1, 'start_minute' => 1200, 'end_minute' => 1410],
            ['context' => 'general', 'iso_weekday' => 2, 'start_minute' => 780, 'end_minute' => 960],
            ['context' => 'general', 'iso_weekday' => 2, 'start_minute' => 1200, 'end_minute' => 1410],
            ['context' => 'general', 'iso_weekday' => 3, 'start_minute' => 780, 'end_minute' => 960],
            ['context' => 'general', 'iso_weekday' => 3, 'start_minute' => 1200, 'end_minute' => 1410],
            ['context' => 'general', 'iso_weekday' => 4, 'start_minute' => 780, 'end_minute' => 960],
            ['context' => 'general', 'iso_weekday' => 4, 'start_minute' => 1200, 'end_minute' => 1410],
            ['context' => 'general', 'iso_weekday' => 5, 'start_minute' => 780, 'end_minute' => 960],
            ['context' => 'general', 'iso_weekday' => 5, 'start_minute' => 1200, 'end_minute' => 1440],
            ['context' => 'takeaway', 'iso_weekday' => 1, 'start_minute' => 780, 'end_minute' => 945],
            ['context' => 'takeaway', 'iso_weekday' => 1, 'start_minute' => 1200, 'end_minute' => 1350],
            ['context' => 'delivery', 'iso_weekday' => 1, 'start_minute' => 1200, 'end_minute' => 1380],
        ]);

        $categories = [
            ['name' => 'Entrantes', 'position' => 0],
            ['name' => 'Principales', 'position' => 10],
            ['name' => 'Postres', 'position' => 20],
            ['name' => 'Bebidas', 'position' => 30],
        ];

        foreach ($categories as $categoryData) {
            $category = Category::query()->updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'name' => $categoryData['name']],
                [
                    ...$categoryData,
                    'available_dine_in' => true,
                    'available_takeaway' => true,
                    'available_delivery' => $categoryData['name'] !== 'Bebidas',
                ],
            );

            $products = match ($category->name) {
                'Entrantes' => [['name' => 'Croquetas de jamón', 'price_minor' => 950, 'allergens' => ['gluten', 'milk', 'eggs']], ['name' => 'Ensaladilla rusa', 'price_minor' => 850, 'allergens' => ['eggs', 'fish']]],
                'Principales' => [['name' => 'Arroz a banda', 'price_minor' => 1450, 'allergens' => ['fish', 'molluscs']], ['name' => 'Entrecot', 'price_minor' => 2000, 'allergens' => []], ['name' => 'Hamburguesa Marchando', 'price_minor' => 1350, 'allergens' => ['gluten', 'milk', 'eggs', 'mustard']]],
                'Postres' => [['name' => 'Tarta de queso', 'price_minor' => 650, 'allergens' => ['gluten', 'milk', 'eggs']]],
                default => [['name' => 'Coca-Cola', 'price_minor' => 250, 'allergens' => []], ['name' => 'Agua mineral', 'price_minor' => 180, 'allergens' => []], ['name' => 'Café con leche', 'price_minor' => 220, 'allergens' => ['milk']]],
            };

            foreach ($products as $position => $productData) {
                $allergenCodes = $productData['allergens'];
                unset($productData['allergens']);
                $product = Product::query()->updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'category_id' => $category->id, 'name' => $productData['name']],
                    [
                        ...$productData,
                        'position' => $position * 10,
                        'vat_rate' => null,
                        'available_dine_in' => true,
                        'available_takeaway' => true,
                        'available_delivery' => $category->available_delivery,
                    ],
                );
                $product->allergens()->sync(Allergen::query()->whereIn('code', $allergenCodes)->pluck('id'));
            }
        }

        $arroz = Product::query()->where('restaurant_id', $restaurant->id)->where('name', 'Arroz a banda')->first();
        if ($arroz) {
            foreach ([['name' => 'Tapa', 'price_minor' => 500, 'position' => 0], ['name' => 'Media ración', 'price_minor' => 900, 'position' => 10], ['name' => 'Ración', 'price_minor' => 1500, 'position' => 20]] as $format) {
                ProductFormat::query()->updateOrCreate(['restaurant_id' => $restaurant->id, 'product_id' => $arroz->id, 'name' => $format['name']], [...$format, 'is_default' => $format['name'] === 'Media ración', 'is_active' => true]);
            }
        }

        $entrecot = Product::query()->where('restaurant_id', $restaurant->id)->where('name', 'Entrecot')->first();
        $burger = Product::query()->where('restaurant_id', $restaurant->id)->where('name', 'Hamburguesa Marchando')->first();
        $cafe = Product::query()->where('restaurant_id', $restaurant->id)->where('name', 'Café con leche')->first();
        $meat = $restaurant->modifierGroups()->updateOrCreate(['name' => 'Punto de la carne'], ['description' => 'Indica el punto de preparación.', 'min_selections' => 1, 'max_selections' => 1, 'allow_quantities' => false, 'is_active' => true]);
        foreach (['Poco hecho', 'Al punto', 'Muy hecho'] as $position => $name) {
            $meat->options()->updateOrCreate(['name' => $name], ['restaurant_id' => $restaurant->id, 'position' => $position * 10, 'is_active' => true, 'max_quantity' => 1, 'price_delta_minor' => 0, 'instruction' => 'normal']);
        }
        $extras = $restaurant->modifierGroups()->updateOrCreate(['name' => 'Extras'], ['description' => 'Añade extras a tu hamburguesa.', 'min_selections' => 0, 'max_selections' => 3, 'allow_quantities' => true, 'is_active' => true]);
        foreach ([['name' => 'Bacon', 'price_delta_minor' => 150], ['name' => 'Queso', 'price_delta_minor' => 100], ['name' => 'Huevo', 'price_delta_minor' => 100]] as $position => $option) {
            $extras->options()->updateOrCreate(['name' => $option['name']], ['restaurant_id' => $restaurant->id, 'position' => $position * 10, 'is_active' => true, 'max_quantity' => 3, 'instruction' => 'normal', 'price_delta_minor' => $option['price_delta_minor']]);
        }
        $milk = $restaurant->modifierGroups()->updateOrCreate(['name' => 'Tipo de leche'], ['description' => 'Elige una leche.', 'min_selections' => 1, 'max_selections' => 1, 'allow_quantities' => false, 'is_active' => true]);
        foreach ([['name' => 'Entera', 'price_delta_minor' => 0], ['name' => 'Sin lactosa', 'price_delta_minor' => 0], ['name' => 'Avena', 'price_delta_minor' => 50]] as $position => $option) {
            $milk->options()->updateOrCreate(['name' => $option['name']], ['restaurant_id' => $restaurant->id, 'position' => $position * 10, 'is_active' => true, 'max_quantity' => 1, 'instruction' => 'normal', 'price_delta_minor' => $option['price_delta_minor']]);
        }
        foreach ([[$entrecot, $meat], [$burger, $extras], [$cafe, $milk]] as [$product, $group]) {
            if ($product) {
                ProductModifierGroup::query()->updateOrCreate(['restaurant_id' => $restaurant->id, 'product_id' => $product->id, 'modifier_group_id' => $group->id], ['position' => 0]);
            }
        }

        foreach ([['name' => 'Salón', 'position' => 0], ['name' => 'Terraza', 'position' => 10], ['name' => 'Barra', 'position' => 20]] as $zoneData) {
            $zone = Zone::query()->firstOrCreate(['restaurant_id' => $restaurant->id, 'name' => $zoneData['name']], [...$zoneData, 'is_active' => true]);
            $tableNames = match ($zone->name) {
                'Salón' => ['Mesa 1', 'Mesa 2', 'Mesa 3', 'Mesa 4', 'Mesa 5'],
                'Terraza' => ['Terraza 1', 'Terraza 2', 'Terraza 3', 'Terraza 4'],
                default => ['Barra 1', 'Barra 2', 'Barra principal'],
            };
            foreach ($tableNames as $position => $name) {
                $table = DiningTable::unguarded(fn () => DiningTable::query()->firstOrCreate(
                    ['restaurant_id' => $restaurant->id, 'zone_id' => $zone->id, 'name' => $name],
                    ['capacity' => 2 + ($position % 3), 'position' => $position * 10, 'is_active' => true, 'qr_token' => bin2hex(random_bytes(32)), 'qr_is_active' => $position === 0],
                ));
                $table->update(['position' => $position * 10, 'capacity' => 2 + ($position % 3)]);
            }
        }

        $roles = collect([['code' => 'manager', 'name' => 'Encargado'], ['code' => 'service', 'name' => 'Camarero'], ['code' => 'kitchen', 'name' => 'Cocina'], ['code' => 'delivery', 'name' => 'Reparto']])->mapWithKeys(fn ($role) => [$role['code'] => OperationalRole::query()->updateOrCreate(['restaurant_id' => $restaurant->id, 'code' => $role['code']], $role)])->all();
        $staff = [
            ['first_name' => 'Ana', 'last_name' => 'López', 'display_name' => 'ANA', 'pin' => '4821', 'roles' => ['service']],
            ['first_name' => 'Celia', 'last_name' => 'Sanz', 'display_name' => 'CELIA', 'pin' => '4822', 'roles' => ['service']],
            ['first_name' => 'Carlos', 'last_name' => 'Ruiz', 'display_name' => 'CARLOS', 'pin' => '4823', 'roles' => ['kitchen']],
            ['first_name' => 'Mario', 'last_name' => 'García', 'display_name' => 'MARIO', 'pin' => '4824', 'roles' => ['manager', 'service'], 'user_id' => $user->id],
            ['first_name' => 'Lucas', 'last_name' => 'Díaz', 'display_name' => 'LUCAS', 'pin' => '4825', 'roles' => ['delivery']],
        ];
        foreach ($staff as $staffData) {
            $pin = $staffData['pin'];
            $roleCodes = $staffData['roles'] ?? [];
            unset($staffData['pin'], $staffData['roles']);
            $staffData['pin_hash'] = Hash::make($pin);
            $staffData['pin_fingerprint'] = AttendanceService::fingerprint($restaurant->id, $pin);
            $staffData['is_active'] = true;
            $employee = Employee::query()->updateOrCreate(['restaurant_id' => $restaurant->id, 'display_name' => $staffData['display_name']], $staffData);
            $employee->operationalRoles()->sync(collect($roleCodes)->map(fn ($code) => $roles[$code]->id)->all());
        }
        $ana = Employee::query()->where('restaurant_id', $restaurant->id)->where('display_name', 'ANA')->first();
        if ($ana) {
            WorkInterval::query()->updateOrCreate(['restaurant_id' => $restaurant->id, 'employee_id' => $ana->id, 'started_at' => '2026-09-07 07:03:00'], ['ended_at' => '2026-09-07 12:01:00', 'source' => 'seed']);
            WorkInterval::query()->updateOrCreate(['restaurant_id' => $restaurant->id, 'employee_id' => $ana->id, 'started_at' => '2026-09-07 15:00:00'], ['ended_at' => null, 'source' => 'seed']);
        }

        $demoTable = DiningTable::query()->where('restaurant_id', $restaurant->id)->where('name', 'Mesa 1')->first();
        $demoProduct = Product::query()->where('restaurant_id', $restaurant->id)->where('name', 'Coca-Cola')->first();
        if ($demoTable && $demoProduct && $ana) {
            $demoOrder = Order::query()->firstOrCreate(['restaurant_id' => $restaurant->id, 'dining_table_id' => $demoTable->id, 'status' => 'open'], ['opened_by_user_id' => $user->id, 'opened_by_employee_id' => $ana->id, 'current_employee_id' => $ana->id, 'channel' => 'dine_in', 'currency' => $restaurant->currency, 'guest_count' => 2, 'business_date' => now($restaurant->timezone)->toDateString(), 'opened_at' => now()->subMinutes(18), 'total_minor' => 500]);
            ActiveTableOrder::query()->firstOrCreate(['restaurant_id' => $restaurant->id, 'dining_table_id' => $demoTable->id], ['order_id' => $demoOrder->id]);
            $demoRound = $demoOrder->rounds()->firstOrCreate(['sequence' => 1], ['restaurant_id' => $restaurant->id, 'created_by_user_id' => $user->id, 'created_by_employee_id' => $ana->id, 'status' => 'submitted', 'submitted_by_user_id' => $user->id, 'submitted_by_employee_id' => $ana->id, 'submitted_at' => now()->subMinutes(17)]);
            $demoRound->lines()->firstOrCreate(['product_id' => $demoProduct->id], ['restaurant_id' => $restaurant->id, 'order_id' => $demoOrder->id, 'employee_id' => $ana->id, 'user_id' => $user->id, 'product_name' => $demoProduct->name, 'format_name' => 'Precio base', 'quantity' => 2, 'unit_base_minor' => $demoProduct->price_minor, 'unit_total_minor' => $demoProduct->price_minor, 'line_total_minor' => $demoProduct->price_minor * 2, 'active_line_total_minor' => $demoProduct->price_minor * 2, 'currency' => $restaurant->currency, 'vat_rate' => $demoProduct->effectiveVat(), 'snapshot' => ['schema_version' => 1, 'product' => ['id' => $demoProduct->id, 'name' => $demoProduct->name], 'format' => ['name' => 'Precio base'], 'quantity' => 2, 'unit_total_minor' => $demoProduct->price_minor, 'line_total_minor' => $demoProduct->price_minor * 2], 'position' => 10]);
        }

        foreach ($restaurant->products()->whereNull('cost_minor')->get() as $seedProduct) {
            $seedProduct->update(['cost_minor' => (int) round($seedProduct->price_minor * 0.4)]);
        }
        $coca = Product::query()->where('restaurant_id', $restaurant->id)->where('name', 'Coca-Cola')->first();
        if ($coca && ! $coca->track_stock) {
            $coca->update(['track_stock' => true, 'stock_quantity' => 48, 'stock_minimum' => 12]);
            $coca->stockMovements()->firstOrCreate(['restaurant_id' => $restaurant->id, 'type' => 'initial'], ['product_id' => $coca->id, 'user_id' => $user->id, 'quantity_delta' => 48, 'resulting_quantity' => 48, 'reason' => 'Stock inicial']);
        }

        $stations = collect([['name' => 'Barra', 'position' => 0], ['name' => 'Cocina', 'position' => 10], ['name' => 'Postres', 'position' => 20]])->mapWithKeys(fn ($station) => [$station['name'] => KitchenStation::query()->updateOrCreate(['restaurant_id' => $restaurant->id, 'name' => $station['name']], [...$station, 'is_active' => true])]);
        $restaurant->products()->whereHas('category', fn ($query) => $query->where('name', 'Bebidas'))->update(['kitchen_station_id' => $stations['Barra']->id]);
        $restaurant->products()->whereHas('category', fn ($query) => $query->where('name', 'Principales'))->update(['kitchen_station_id' => $stations['Cocina']->id]);
        $restaurant->products()->whereHas('category', fn ($query) => $query->where('name', 'Postres'))->update(['kitchen_station_id' => $stations['Postres']->id]);
    }
}
