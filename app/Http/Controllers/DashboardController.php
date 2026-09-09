<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\PilotReadiness;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Restaurant $restaurant): View
    {
        session(['current_restaurant_id' => $restaurant->getKey()]);

        $readiness = app(PilotReadiness::class)->check($restaurant);
        $attention = app(PilotReadiness::class)->attention($restaurant);

        return view('dashboard', compact('restaurant', 'readiness', 'attention'));
    }

    public function module(Restaurant $restaurant, string $module): View
    {
        abort_unless(array_key_exists($module, self::modules()), 404);

        return view('modules.placeholder', [
            'restaurant' => $restaurant,
            'module' => self::modules()[$module],
        ]);
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function modules(): array
    {
        return [
            'orders' => ['label' => 'Pedidos', 'description' => 'Consulta y gestiona la operación de pedidos del restaurante.'],
            'pos' => ['label' => 'TPV', 'description' => 'Gestiona mesas, pedidos, cobros y caja desde el terminal de punto de venta.'],
            'kitchen' => ['label' => 'Cocina', 'description' => 'Consulta la cola de cocina, las estaciones y el estado de cada preparación.'],
            'menu' => ['label' => 'Carta', 'description' => 'Gestiona los productos, categorías y modificadores disponibles.'],
            'restaurant' => ['label' => 'Restaurante', 'description' => 'Configura las zonas, mesas y operación del establecimiento.'],
            'staff' => ['label' => 'Personal', 'description' => 'Gestiona empleados, funciones y control horario.'],
            'reservations' => ['label' => 'Reservas', 'description' => 'Organiza el calendario, la disponibilidad y la capacidad.'],
            'analytics' => ['label' => 'Analítica', 'description' => 'Ventas, facturación, stock, auditoría y rendimiento del restaurante.'],
            'integrations' => ['label' => 'Integraciones', 'description' => 'Hardware, pagos online, fiscalidad, contabilidad y promociones.'],
            'settings' => ['label' => 'Configuración', 'description' => 'Ajusta la identidad, los canales, los horarios y las opciones del restaurante.'],
        ];
    }
}
