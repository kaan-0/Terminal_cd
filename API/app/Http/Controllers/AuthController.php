<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    use ValidatesRequests;

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $datos = $this->validate($request, [
            'email' => [
                'required',
                'email',
            ],
            'password' => [
                'required',
                'string',
            ],
            'remember' => [
                'nullable',
                'boolean',
            ],
        ], [
            'email.required' => 'Ingrese su correo electrónico.',
            'email.email' => 'Ingrese un correo electrónico válido.',
            'password.required' => 'Ingrese su contraseña.',
        ]);

        $credenciales = [
            'email' => strtolower(trim($datos['email'])),
            'password' => $datos['password'],
            'activo' => true,
        ];

        if (!Auth::attempt($credenciales, $request->boolean('remember'))) {
            return back()
                ->withErrors([
                    'email' => 'Las credenciales no son correctas o la cuenta está desactivada.',
                ])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $usuario = $request->user();

        if (
            !$usuario->esAdministrador() &&
            (!$usuario->cliente || !$usuario->cliente->activo)
        ) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors([
                    'email' => 'La cuenta no tiene un cliente activo asociado.',
                ])
                ->onlyInput('email');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
