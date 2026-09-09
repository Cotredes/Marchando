<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RestaurantFloorPlanController extends Controller
{
    public function index(Request $request, Restaurant $restaurant): View
    {
        $this->authorize('viewFloorPlan', $restaurant);
        $zones = $restaurant->zones()->where('is_active', true)->with(['diningTables' => fn ($query) => $query->where('is_active', true)->with('activeAssignment.order.currentEmployee')])->get();
        $zoneId = $request->integer('zone');
        if ($zoneId) {
            $zones = $zones->where('id', $zoneId)->values();
        }

        return view('restaurant.index', ['restaurant' => $restaurant, 'zones' => $zones, 'allZones' => $restaurant->zones()->where('is_active', true)->get(), 'canManage' => $request->user()->can('manageFloorPlan', $restaurant), 'selectedZone' => $zoneId]);
    }

    public function manage(Request $request, Restaurant $restaurant): View
    {
        $this->authorize('manageFloorPlan', $restaurant);
        $query = $restaurant->diningTables()->with('zone');
        if ($request->filled('q')) {
            $query->where('name', 'like', '%'.$request->string('q').'%');
        }
        if ($request->integer('zone')) {
            $query->where('zone_id', $request->integer('zone'));
        }
        if ($request->input('state') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->input('state') === 'inactive') {
            $query->where('is_active', false);
        }
        if ($request->input('qr') === 'active') {
            $query->where('qr_is_active', true);
        } elseif ($request->input('qr') === 'inactive') {
            $query->where('qr_is_active', false);
        }

        return view('restaurant.manage', ['restaurant' => $restaurant, 'zones' => $restaurant->zones()->orderBy('position')->get(), 'tables' => $query->orderBy('zone_id')->orderBy('position')->orderBy('id')->paginate(25)->withQueryString(), 'canManage' => true]);
    }
}
