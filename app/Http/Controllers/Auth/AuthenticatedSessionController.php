<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Las cuentas desactivadas no pueden iniciar sesión. El mensaje es
        // genérico a propósito para no revelar qué cuentas existen.
        if (! Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Las credenciales no son válidas.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('app.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
