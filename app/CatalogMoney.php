<?php

namespace App;

class CatalogMoney
{
    public static function toMinorUnits(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = str_replace(',', '.', trim($value));
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = str_pad($fraction, 2, '0');

        return (int) $whole * 100 + (int) substr($fraction, 0, 2);
    }

    public static function format(int $minorUnits): string
    {
        return number_format(intdiv($minorUnits, 100), 0, ',', '.')
            .','.str_pad((string) ($minorUnits % 100), 2, '0', STR_PAD_LEFT);
    }
}
