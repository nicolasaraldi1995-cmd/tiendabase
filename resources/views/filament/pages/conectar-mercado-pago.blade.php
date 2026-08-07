{{--
    Guía para conectar MercadoPago.

    Los dibujos son ESQUEMÁTICOS, no capturas: las pantallas de MercadoPago
    cambian seguido y una captura vieja hace que el dueño crea que se equivocó
    de lugar. Un esquema de "buscá esto en la columna de la izquierda" envejece
    mucho mejor, y además no mete imágenes de otra empresa adentro del producto.

    OJO CON LOS COLORES: acá NO se pueden usar clases tipo `fill-gray-200`. Esta
    pantalla la sirve Filament con su CSS ya compilado, que no incluye las
    utilidades de fill/stroke — se verían todas en negro. Por eso los dibujos
    heredan el color del texto (currentColor, que se adapta solo a claro y
    oscuro) y usan la variable del color primario del panel para los resaltados.
--}}
@php
    $ya = $this->loQueYaEsta();
    // El verde del panel, para las flechas y los recuadros que señalan.
    $acento = 'rgb(var(--primary-600))';
@endphp

<x-filament-panels::page>

    @if ($this->todoListo())
        <x-filament::section>
            <div class="flex items-start gap-3">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-6 w-6 shrink-0 text-green-500" />
                <div>
                    <p class="font-semibold text-gray-950 dark:text-white">Ya está conectado</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        @if ($this->enModoPrueba())
                            Estás usando <strong>credenciales de prueba</strong>: podés simular compras, pero no entra plata de verdad.
                            Cuando quieras cobrar en serio, repetí estos pasos con las credenciales de producción.
                        @else
                            Tu tienda está cobrando con MercadoPago.
                        @endif
                    </p>
                </div>
            </div>
        </x-filament::section>
    @endif

    <x-filament::section>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Con esto tus clientes pagan con tarjeta o dinero en cuenta al confirmar el pedido, y el pedido
            queda marcado como pagado solo. Son cuatro pasos y se hace una sola vez.
        </p>
        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
            <strong class="text-gray-950 dark:text-white">Las claves son tuyas.</strong>
            Se cargan acá, en tu panel, y quedan guardadas encriptadas. Quien te instaló la tienda no las ve
            ni las necesita: la plata entra directo a tu cuenta de MercadoPago.
        </p>
    </x-filament::section>

    {{-- ─── Paso 1 ─────────────────────────────────────────────────────── --}}
    <x-filament::section>
        <x-slot name="heading">
            <span class="inline-flex items-center gap-2">
                <span @class([
                    'flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold text-white',
                    'bg-primary-600' => ! $ya['token'],
                    'bg-green-600' => $ya['token'],
                ])>1</span>
                Creá tu aplicación en MercadoPago
            </span>
        </x-slot>

        <div class="grid gap-6 lg:grid-cols-2 lg:items-center">
            <div class="space-y-3 text-sm text-gray-500 dark:text-gray-400">
                <p>
                    Entrá a
                    <a href="https://www.mercadopago.com.ar/developers/panel" target="_blank" rel="noopener"
                       class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                        mercadopago.com.ar/developers</a>
                    con la cuenta de tu negocio, y buscá <strong class="text-gray-950 dark:text-white">Tus integraciones</strong>.
                </p>
                <p>Creá una aplicación nueva. Ponele el nombre de tu negocio. Cuando pregunte qué vas a integrar, elegí <strong class="text-gray-950 dark:text-white">pagos online</strong>.</p>
                <p class="text-xs">
                    <strong class="text-gray-950 dark:text-white">Qué es una aplicación:</strong>
                    el registro de tu tienda ante MercadoPago. De ahí salen las llaves que vas a pegar acá.
                </p>
            </div>

            <svg viewBox="0 0 340 150" class="w-full rounded-lg border border-gray-200 text-gray-500 dark:border-gray-700 dark:text-gray-400" role="img" aria-label="Esquema: el botón para crear una aplicación">
                <rect x="10" y="10" width="320" height="130" rx="6" fill="currentColor" opacity="0.05" />
                <rect x="22" y="24" width="120" height="9" rx="3" fill="currentColor" opacity="0.35" />
                <rect x="22" y="52" width="180" height="8" rx="3" fill="currentColor" opacity="0.15" />
                <rect x="22" y="70" width="140" height="8" rx="3" fill="currentColor" opacity="0.15" />
                <rect x="22" y="88" width="160" height="8" rx="3" fill="currentColor" opacity="0.15" />

                <rect x="222" y="44" width="96" height="24" rx="6" fill="{{ $acento }}" />
                <text x="270" y="60" text-anchor="middle" fill="#fff" font-size="9" font-family="sans-serif" font-weight="600">Crear aplicación</text>

                <path d="M270 96 L270 74" stroke="{{ $acento }}" stroke-width="2" fill="none" marker-end="url(#flecha1)" />
                <defs>
                    <marker id="flecha1" markerWidth="8" markerHeight="8" refX="4" refY="7" orient="auto">
                        <path d="M0,0 L4,7 L8,0 Z" fill="{{ $acento }}" />
                    </marker>
                </defs>
                <text x="270" y="115" text-anchor="middle" fill="{{ $acento }}" font-size="9.5" font-family="sans-serif" font-weight="700">Empezá por acá</text>
            </svg>
        </div>
    </x-filament::section>

    {{-- ─── Paso 2 ─────────────────────────────────────────────────────── --}}
    <x-filament::section>
        <x-slot name="heading">
            <span class="inline-flex items-center gap-2">
                <span @class([
                    'flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold text-white',
                    'bg-primary-600' => ! $ya['token'],
                    'bg-green-600' => $ya['token'],
                ])>2</span>
                Copiá tu Access Token
            </span>
        </x-slot>

        <div class="grid gap-6 lg:grid-cols-2 lg:items-center">
            <div class="space-y-3 text-sm text-gray-500 dark:text-gray-400">
                <p>
                    Ya adentro de la aplicación, mirá la <strong class="text-gray-950 dark:text-white">columna de la izquierda</strong>.
                    Vas a ver dos grupos separados: uno de <strong class="text-gray-950 dark:text-white">pruebas</strong> y otro de
                    <strong class="text-gray-950 dark:text-white">producción</strong>.
                </p>
                <p>
                    Entrá al de <strong class="text-gray-950 dark:text-white">pruebas</strong> y copiá el
                    <strong class="text-gray-950 dark:text-white">Access Token</strong>. Empieza con
                    <code class="rounded bg-gray-100 px-1 dark:bg-gray-800">TEST-</code>.
                </p>
                <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-200">
                    <strong>La confusión más común.</strong> Las de <em>producción</em> mueven plata real; las de
                    <em>prueba</em> son plata falsa. Empezá siempre por las de prueba: cuando todo funcione, cambiás.
                </div>
            </div>

            <svg viewBox="0 0 340 180" class="w-full rounded-lg border border-gray-200 text-gray-500 dark:border-gray-700 dark:text-gray-400" role="img" aria-label="Esquema: dónde están las credenciales de prueba en la columna lateral">
                <rect x="10" y="10" width="320" height="160" rx="6" fill="currentColor" opacity="0.05" />
                <rect x="20" y="20" width="104" height="140" rx="5" fill="currentColor" opacity="0.08" />

                <text x="30" y="38" fill="currentColor" opacity="0.6" font-size="7" font-family="sans-serif" letter-spacing="1">PRUEBAS</text>
                <rect x="26" y="44" width="92" height="18" rx="4" fill="{{ $acento }}" />
                <text x="32" y="56" fill="#fff" font-size="7.5" font-family="sans-serif" font-weight="600">Credenciales de prueba</text>
                <rect x="30" y="68" width="66" height="6" rx="2" fill="currentColor" opacity="0.2" />
                <rect x="30" y="80" width="56" height="6" rx="2" fill="currentColor" opacity="0.2" />

                <text x="30" y="102" fill="currentColor" opacity="0.6" font-size="7" font-family="sans-serif" letter-spacing="1">NOTIFICACIONES</text>
                <rect x="30" y="108" width="52" height="6" rx="2" fill="currentColor" opacity="0.2" />

                <text x="30" y="130" fill="currentColor" opacity="0.6" font-size="7" font-family="sans-serif" letter-spacing="1">PRODUCCIÓN</text>
                <rect x="30" y="136" width="70" height="6" rx="2" fill="currentColor" opacity="0.2" />

                <path d="M168 53 L128 53" stroke="{{ $acento }}" stroke-width="2" fill="none" marker-end="url(#flecha2)" />
                <defs>
                    <marker id="flecha2" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto">
                        <path d="M0,0 L7,4 L0,8 Z" fill="{{ $acento }}" />
                    </marker>
                </defs>
                <text x="174" y="50" fill="{{ $acento }}" font-size="9.5" font-family="sans-serif" font-weight="700">Entrá acá</text>
                <text x="174" y="63" fill="currentColor" opacity="0.7" font-size="8" font-family="sans-serif">no a las de producción</text>

                <rect x="140" y="92" width="180" height="60" rx="5" fill="currentColor" opacity="0.08" />
                <text x="152" y="110" fill="currentColor" opacity="0.7" font-size="8" font-family="sans-serif">Access Token</text>
                <rect x="152" y="118" width="150" height="11" rx="3" fill="currentColor" opacity="0.15" />
                <text x="158" y="127" fill="currentColor" opacity="0.75" font-size="7.5" font-family="monospace">TEST-4820················</text>
                <text x="152" y="144" fill="currentColor" opacity="0.5" font-size="7" font-family="sans-serif">copiá esto</text>
            </svg>
        </div>
    </x-filament::section>

    {{-- ─── Paso 3 ─────────────────────────────────────────────────────── --}}
    <x-filament::section>
        <x-slot name="heading">
            <span class="inline-flex items-center gap-2">
                <span @class([
                    'flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold text-white',
                    'bg-primary-600' => ! $ya['secreto'],
                    'bg-green-600' => $ya['secreto'],
                ])>3</span>
                Avisale a MercadoPago dónde notificarte
            </span>
        </x-slot>

        <div class="space-y-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                En la misma columna de la izquierda, buscá el grupo de <strong class="text-gray-950 dark:text-white">notificaciones</strong>
                y entrá a <strong class="text-gray-950 dark:text-white">Webhooks</strong>. Ahí pegás esta dirección —
                es la de <em>tu</em> tienda:
            </p>

            <div x-data="{ copiado: false }" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <code class="flex-1 overflow-x-auto rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-xs text-gray-950 dark:border-gray-600 dark:bg-gray-900 dark:text-white">{{ $this->urlDelWebhook() }}</code>
                <x-filament::button
                    size="sm"
                    x-on:click="navigator.clipboard.writeText(@js($this->urlDelWebhook())); copiado = true; setTimeout(() => copiado = false, 2000)"
                    icon="heroicon-o-clipboard-document">
                    <span x-show="! copiado">Copiar</span>
                    <span x-show="copiado" x-cloak>¡Copiada!</span>
                </x-filament::button>
            </div>

            <div class="grid gap-6 lg:grid-cols-2 lg:items-center">
                <div class="space-y-3 text-sm text-gray-500 dark:text-gray-400">
                    <p>En la lista de eventos marcá solamente <strong class="text-gray-950 dark:text-white">Pagos</strong>. Los demás no los necesitás.</p>
                    <p>Guardá. <strong class="text-gray-950 dark:text-white">Recién ahí MercadoPago genera la clave secreta</strong> y te la muestra abajo.</p>
                    <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-200">
                        <strong>La clave no la inventás vos.</strong> Te la da MercadoPago después de guardar.
                        Copiala en ese momento: algunas pantallas la muestran completa una sola vez.
                    </div>
                </div>

                <svg viewBox="0 0 340 190" class="w-full rounded-lg border border-gray-200 text-gray-500 dark:border-gray-700 dark:text-gray-400" role="img" aria-label="Esquema: dirección, evento Pagos y clave secreta">
                    <rect x="10" y="10" width="320" height="170" rx="6" fill="currentColor" opacity="0.05" />

                    <text x="22" y="30" fill="currentColor" opacity="0.7" font-size="8" font-family="sans-serif">Dirección</text>
                    <rect x="22" y="36" width="296" height="20" rx="4" fill="currentColor" opacity="0.1" stroke="{{ $acento }}" stroke-width="1.5" />
                    <text x="30" y="50" fill="currentColor" opacity="0.75" font-size="7" font-family="monospace">https://tutienda.../webhooks/mercadopago</text>

                    <text x="22" y="78" fill="currentColor" opacity="0.7" font-size="8" font-family="sans-serif">Eventos</text>
                    <rect x="22" y="86" width="11" height="11" rx="2.5" fill="{{ $acento }}" />
                    <path d="M25 91.5 L27.2 94 L31 89.5" stroke="#fff" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                    <text x="40" y="95" fill="currentColor" font-size="8.5" font-family="sans-serif" font-weight="700">Pagos</text>
                    <text x="78" y="95" fill="{{ $acento }}" font-size="8" font-family="sans-serif" font-weight="600">← solo este</text>

                    <rect x="22" y="104" width="11" height="11" rx="2.5" fill="currentColor" opacity="0.15" />
                    <text x="40" y="113" fill="currentColor" opacity="0.45" font-size="8" font-family="sans-serif">Otros eventos</text>
                    <rect x="22" y="122" width="11" height="11" rx="2.5" fill="currentColor" opacity="0.15" />
                    <text x="40" y="131" fill="currentColor" opacity="0.45" font-size="8" font-family="sans-serif">Otros eventos</text>

                    <rect x="22" y="146" width="86" height="22" rx="5" fill="{{ $acento }}" />
                    <text x="65" y="161" text-anchor="middle" fill="#fff" font-size="9" font-family="sans-serif" font-weight="600">Guardar</text>

                    <path d="M118 157 L152 157" stroke="{{ $acento }}" stroke-width="2" fill="none" marker-end="url(#flecha3)" />
                    <defs>
                        <marker id="flecha3" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto">
                            <path d="M0,0 L7,4 L0,8 Z" fill="{{ $acento }}" />
                        </marker>
                    </defs>
                    <rect x="158" y="140" width="160" height="34" rx="5" fill="currentColor" opacity="0.1" />
                    <text x="168" y="154" fill="currentColor" opacity="0.7" font-size="7.5" font-family="sans-serif">Clave secreta (aparece acá)</text>
                    <rect x="168" y="159" width="138" height="9" rx="2.5" fill="currentColor" opacity="0.2" />
                </svg>
            </div>
        </div>
    </x-filament::section>

    {{-- ─── Paso 4 ─────────────────────────────────────────────────────── --}}
    <x-filament::section>
        <x-slot name="heading">
            <span class="inline-flex items-center gap-2">
                <span @class([
                    'flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold text-white',
                    'bg-primary-600' => ! $ya['modo'],
                    'bg-green-600' => $ya['modo'],
                ])>4</span>
                Pegá las dos claves acá
            </span>
        </x-slot>

        <div class="space-y-3 text-sm text-gray-500 dark:text-gray-400">
            <p>
                Andá a <strong class="text-gray-950 dark:text-white">Herramientas → Configuración</strong>, bajá hasta
                <strong class="text-gray-950 dark:text-white">Cobro online</strong> y elegí cómo querés cobrar.
                Recién ahí aparecen los campos para las dos claves.
            </p>
            <p>
                Para cargarlas o cambiarlas te va a pedir <strong class="text-gray-950 dark:text-white">tu contraseña</strong>:
                cambiar el Access Token es cambiar a qué cuenta va la plata de todas tus ventas, así que no puede
                hacerlo cualquiera que encuentre el panel abierto.
            </p>
            <div class="pt-1">
                <x-filament::button tag="a" :href="\App\Filament\Pages\Configuracion::getUrl()" icon="heroicon-o-arrow-right">
                    Ir a Configuración
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>

    {{-- ─── Después ────────────────────────────────────────────────────── --}}
    <x-filament::section heading="Probá que funcione">
        <ol class="ml-4 list-decimal space-y-2 text-sm text-gray-500 marker:text-gray-400 dark:text-gray-400">
            <li>Entrá a tu tienda como si fueras un cliente y hacé un pedido eligiendo <strong class="text-gray-950 dark:text-white">Pagar ahora</strong>.</li>
            <li>Pagá con una de las <strong class="text-gray-950 dark:text-white">tarjetas de prueba</strong> que MercadoPago te da en su panel.</li>
            <li>Volvé al panel: el pedido tiene que figurar <strong class="text-gray-950 dark:text-white">pagado</strong>, y el pago aparece en la cuenta corriente del cliente.</li>
        </ol>

        <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            <strong class="text-gray-950 dark:text-white">Si el pedido tarda unos minutos en marcarse como pagado, es normal.</strong>
            MercadoPago le avisa a tu tienda por su cuenta, y si el servidor estaba en reposo el aviso se reintenta solo.
            El pago no se pierde.
        </div>
    </x-filament::section>

</x-filament-panels::page>
