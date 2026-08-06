<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Pide el mail para cambiar la contraseña.
     *
     * La respuesta es SIEMPRE la misma, exista o no la cuenta. Como venía, un
     * mail registrado volvía con `status` y uno inexistente con un error en el
     * campo: dos respuestas distinguibles alcanzan para probar direcciones de a
     * una y quedarse con la lista de clientes del negocio. El tope de intentos
     * por hora lo hacía lento, no imposible.
     *
     * Ni siquiera se distingue el caso del tope: Laravel corta antes por "ese
     * usuario no existe", así que el tope SOLO puede saltar para una cuenta que
     * sí existe. Mandando la misma dirección dos veces seguidas, el segundo
     * intento contestaba distinto según hubiera cuenta o no — la misma
     * enumeración por otra puerta. El freno de a cinco por hora de la ruta sigue
     * cortando el barrido, y ese sí devuelve un 429 antes de llegar acá.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        Password::sendResetLink($request->only('email'));

        return back()->with('status', trans(Password::RESET_LINK_SENT));
    }
}
