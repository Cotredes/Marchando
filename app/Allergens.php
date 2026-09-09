<?php

namespace App;

class Allergens
{
    /**
     * @return array<int, array{code: string, name: string, position: int}>
     */
    public static function all(): array
    {
        return [
            ['code' => 'gluten', 'name' => 'Gluten', 'position' => 1],
            ['code' => 'crustaceans', 'name' => 'Crustáceos', 'position' => 2],
            ['code' => 'eggs', 'name' => 'Huevos', 'position' => 3],
            ['code' => 'fish', 'name' => 'Pescado', 'position' => 4],
            ['code' => 'peanuts', 'name' => 'Cacahuetes', 'position' => 5],
            ['code' => 'soy', 'name' => 'Soja', 'position' => 6],
            ['code' => 'milk', 'name' => 'Leche y lácteos', 'position' => 7],
            ['code' => 'nuts', 'name' => 'Frutos de cáscara', 'position' => 8],
            ['code' => 'celery', 'name' => 'Apio', 'position' => 9],
            ['code' => 'mustard', 'name' => 'Mostaza', 'position' => 10],
            ['code' => 'sesame', 'name' => 'Sésamo', 'position' => 11],
            ['code' => 'sulphites', 'name' => 'Sulfitos', 'position' => 12],
            ['code' => 'lupin', 'name' => 'Altramuces', 'position' => 13],
            ['code' => 'molluscs', 'name' => 'Moluscos', 'position' => 14],
        ];
    }
}
