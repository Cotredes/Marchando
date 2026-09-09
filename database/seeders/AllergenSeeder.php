<?php

namespace Database\Seeders;

use App\Allergens;
use App\Models\Allergen;
use Illuminate\Database\Seeder;

class AllergenSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Allergens::all() as $allergen) {
            Allergen::query()->updateOrCreate(['code' => $allergen['code']], $allergen);
        }
    }
}
