<?php

namespace App\Filament\Pages;

use App\Models\Configuracion as ConfiguracionModel;
use Filament\Pages\Page;

/**
 * La guía para que el dueño conecte SU cuenta de MercadoPago.
 *
 * Vive en el panel y no en un documento aparte a propósito: el momento en que
 * alguien necesita esto es cuando está mirando el campo vacío, y ahí es cuando
 * menos ganas tiene de ir a buscar un archivo.
 *
 * Dos cosas que un PDF no puede hacer y esta pantalla sí:
 *   - Mostrarle SU dirección de webhook lista para copiar, en vez de un ejemplo
 *     que hay que adaptar. Es justo el dato donde más fácil se equivoca uno.
 *   - Saber en qué paso está: lo que ya cargó aparece resuelto.
 *
 * Los dibujos son esquemáticos y no capturas: las pantallas de MercadoPago
 * cambian seguido, y una captura vieja es peor que ninguna porque el dueño cree
 * que se equivocó de lugar. Un esquema de dónde mirar envejece mucho mejor.
 */
class ConectarMercadoPago extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Herramientas';

    protected static ?string $navigationLabel = 'Conectar MercadoPago';

    protected static ?string $title = 'Cobrar con MercadoPago';

    protected static ?int $navigationSort = 43;

    protected static string $view = 'filament.pages.conectar-mercado-pago';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    /**
     * La dirección a la que MercadoPago le va a avisar de los pagos. Sale de la
     * propia instalación, así que cada tienda ve la suya.
     */
    public function urlDelWebhook(): string
    {
        return url('/webhooks/mercadopago');
    }

    /**
     * En qué paso está, para marcar lo ya resuelto.
     *
     * @return array<string, bool>
     */
    public function loQueYaEsta(): array
    {
        $config = ConfiguracionModel::actual();

        return [
            'modo' => $config->cobraOnline(),
            'token' => $config->tokenMercadoPago() !== null,
            'secreto' => $config->secretoWebhookMercadoPago() !== null,
        ];
    }

    /** Con credenciales de prueba los pagos no son reales: conviene que se lea. */
    public function enModoPrueba(): bool
    {
        return ConfiguracionModel::actual()->cobroOnlineEnPrueba();
    }

    public function todoListo(): bool
    {
        return ConfiguracionModel::actual()->puedeCobrarOnline()
            && ConfiguracionModel::actual()->secretoWebhookMercadoPago() !== null;
    }
}
