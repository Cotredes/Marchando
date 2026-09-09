<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformAudit;
use App\Models\Restaurant;
use App\Models\User;
use App\PilotReadiness;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $restaurants = Restaurant::query()->orderBy('name')->get();

        $attention = [];
        foreach ($restaurants as $restaurant) {
            foreach (app(PilotReadiness::class)->attention($restaurant) as $alert) {
                $attention[] = ['restaurant' => $restaurant] + $alert;
            }
        }

        $audits = PlatformAudit::query()->with(['user', 'restaurant'])->latest('id')->limit(15)->get();

        return view('admin.dashboard', [
            'restaurantCount' => $restaurants->count(),
            'activeRestaurantCount' => $restaurants->where('is_active', true)->count(),
            'userCount' => User::query()->count(),
            'activeUserCount' => User::query()->where('is_active', true)->count(),
            'restaurants' => $restaurants,
            'attention' => $attention,
            'audits' => $audits,
        ]);
    }
}
