<?php

namespace App;

use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CustomerService
{
    public function normalizePhone(?string $phone): ?string
    {
        if (! filled($phone)) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone);
        if (! $digits || strlen($digits) < 6 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }

    public function findForSale(Restaurant $restaurant, ?string $phone, ?string $email, ?string $taxId): ?Customer
    {
        if ($normalized = $this->normalizePhone($phone)) {
            $found = $restaurant->customers()->where('phone_normalized', $normalized)->first();
            if ($found) {
                return $found;
            }
        }
        if (filled($email)) {
            $found = $restaurant->customers()->where('email', mb_strtolower(trim($email)))->first();
            if ($found) {
                return $found;
            }
        }
        if (filled($taxId)) {
            $found = $restaurant->customers()->where('tax_id', mb_strtoupper(trim($taxId)))->first();
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    public function findOrCreateForSale(Restaurant $restaurant, array $data): ?Customer
    {
        $name = filled($data['name'] ?? null) ? mb_substr(trim($data['name']), 0, 150) : null;
        $phone = filled($data['phone'] ?? null) ? mb_substr(trim($data['phone']), 0, 40) : null;
        $email = filled($data['email'] ?? null) ? mb_strtolower(mb_substr(trim($data['email']), 0, 160)) : null;
        $normalized = $this->normalizePhone($phone);

        if (! $normalized && ! $email) {
            return null;
        }

        return DB::transaction(function () use ($restaurant, $name, $phone, $email, $normalized): ?Customer {
            $existing = $this->findForSale($restaurant, $phone, $email, null);
            if ($existing) {
                if (! $existing->display_name && $name) {
                    $existing->update(['display_name' => $name]);
                }

                return $existing->fresh();
            }

            return $restaurant->customers()->create([
                'display_name' => $name, 'phone' => $phone, 'phone_normalized' => $normalized, 'email' => $email,
            ]);
        });
    }

    public function updateFiscal(Restaurant $restaurant, Customer $customer, array $fiscal, User $user): Customer
    {
        abort_unless($customer->restaurant_id === $restaurant->id, 404);
        $taxId = mb_strtoupper(trim((string) ($fiscal['tax_id'] ?? '')));
        if ($taxId === '') {
            throw new InvalidArgumentException('El NIF/CIF es obligatorio para facturar.');
        }
        $conflict = $restaurant->customers()->where('tax_id', $taxId)->whereKeyNot($customer->id)->exists();
        if ($conflict) {
            throw new InvalidArgumentException('Ese NIF/CIF ya pertenece a otro cliente.');
        }

        $customer->update([
            'display_name' => filled($fiscal['display_name'] ?? null) ? mb_substr(trim($fiscal['display_name']), 0, 150) : $customer->display_name,
            'is_company' => (bool) ($fiscal['is_company'] ?? $customer->is_company),
            'legal_name' => mb_substr(trim((string) ($fiscal['legal_name'] ?? '')), 0, 200) ?: null,
            'tax_id' => $taxId,
            'fiscal_address' => filled($fiscal['fiscal_address'] ?? null) ? mb_substr(trim($fiscal['fiscal_address']), 0, 500) : null,
            'postal_code' => filled($fiscal['postal_code'] ?? null) ? mb_substr(trim($fiscal['postal_code']), 0, 20) : null,
            'city' => filled($fiscal['city'] ?? null) ? mb_substr(trim($fiscal['city']), 0, 100) : null,
            'province' => filled($fiscal['province'] ?? null) ? mb_substr(trim($fiscal['province']), 0, 100) : null,
            'country' => filled($fiscal['country'] ?? null) ? mb_substr(trim($fiscal['country']), 0, 100) : 'España',
            'email' => filled($fiscal['email'] ?? null) ? mb_strtolower(mb_substr(trim($fiscal['email']), 0, 160)) : $customer->email,
            'phone' => filled($fiscal['phone'] ?? null) ? mb_substr(trim($fiscal['phone']), 0, 40) : $customer->phone,
            'phone_normalized' => filled($fiscal['phone'] ?? null) ? ($this->normalizePhone($fiscal['phone']) ?? $customer->phone_normalized) : $customer->phone_normalized,
        ]);

        return $customer->fresh();
    }
}
