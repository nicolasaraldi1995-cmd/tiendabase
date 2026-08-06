<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
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
     * El único caso que sí se distingue es el del propio tope, porque ahí hay
     * que decirle a la persona que espere en vez de dejarla probando.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => [trans($status)],
            ]);
        }

        return back()->with('status', trans(Password::RESET_LINK_SENT));
    }
}
