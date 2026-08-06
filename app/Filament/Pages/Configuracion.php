<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ProductoResource;
use App\Models\Configuracion as ConfiguracionModel;
use App\Models\Marca;
use App\Services\RestaurarTienda;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * @property-read Form $form Propiedad mágica de InteractsWithForms.
 */
class Configuracion extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Herramientas';

    protected static ?string $navigationLabel = 'Configuración';

    protected static ?string $title = 'Configuración del sitio';

    protected static ?int $navigationSort = 42;

    protected static string $view = 'filament.pages.configuracion';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    /**
     * Vive en el encabezado y no entre los campos: es lo único de esta
     * pantalla que borra datos, así que conviene que esté lejos de "Guardar".
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('restaurar')
                ->label('Restaurar valores de fábrica')
                ->icon('heroicon-o-arrow-path')
                ->color('danger')
                ->outlined()
                ->modalHeading('Restaurar valores de fábrica')
                ->modalDescription('Se borran tus productos, marcas, categorías, combos y banners, y la configuración y el menú vuelven a como venían. Tus pedidos, pagos, gastos y clientes NO se tocan. Esto no se puede deshacer.')
                ->modalSubmitActionLabel('Restaurar')
                ->form([
                    Forms\Components\TextInput::make('confirmacion')
                        ->label('Escribí RESTAURAR para confirmar')
                        ->required()
                        ->rule('in:RESTAURAR')
                        ->validationMessages(['in' => 'Escribí RESTAURAR (en mayúsculas) para confirmar.']),
                ])
                ->action(function (RestaurarTienda $servicio) {
                    $borrado = $servicio->ejecutar();

                    $this->mount();

                    Notification::make()
                        ->title('La tienda volvió a los valores de fábrica')
                        ->body("Se borraron {$borrado['productos']} productos, {$borrado['marcas']} marcas y {$borrado['categorias']} categorías.")
                        ->success()
                        ->send();

                    $this->redirect(static::getUrl());
                }),
        ];
    }

    public function mount(): void
    {
        $this->form->fill(ConfiguracionModel::actual()->only([
            'nombre_negocio', 'eslogan', 'descripcion', 'direccion', 'ciudad',
            'telefono', 'whatsapp', 'instagram', 'logo', 'medios_pago',
            'plantilla', 'tipografia', 'color_acento', 'logo_alto', 'barra_alto',
            'menu_ancho', 'menu_espacio', 'marca_destacada_id', 'email_avisos',
            'envio_gratis_desde', 'pedido_minimo_mayorista', 'controlar_stock',
            'hace_envios', 'mostrar_lista_precios', 'mostrar_combos',
            'modo_cobro',
            'factura_activa', 'cuit', 'punto_venta', 'condicion_iva', 'arca_ambiente',
        ]));

        // Las credenciales de MercadoPago NO se precargan a propósito: el campo
        // arranca vacío y solo se guarda si escriben algo (ver dehydrated), así
        // que entrar a esta pantalla y guardar no las pisa. El estado de lo que
        // ya hay se muestra aparte, en estadoDeLasCredenciales().
    }

    /**
     * Qué le falta al negocio para que el cobro online funcione de verdad. Se
     * calcula contra lo guardado y no contra el formulario, porque el campo
     * vacío no significa que no haya credencial: significa que no la están
     * cambiando ahora.
     */
    private function estadoDeLasCredenciales(): string
    {
        $config = ConfiguracionModel::actual();

        if ($config->tokenMercadoPago() === null) {
            return 'Falta el Access Token. Hasta que lo cargues, la tienda sigue cobrando como antes.';
        }

        if ($config->secretoWebhookMercadoPago() === null) {
            return 'Falta la clave secreta de las notificaciones. Vas a poder mandar a pagar, pero los pagos no se van a acreditar solos.';
        }

        return $config->cobroOnlineEnPrueba()
            ? 'Listo, pero con credenciales DE PRUEBA: los pagos no son reales.'
            : 'Listo: cobrando de verdad.';
    }

    /** Qué le falta al negocio para poder emitir comprobantes de verdad. */
    private function estadoDeLaFacturacion(): string
    {
        $config = ConfiguracionModel::actual();

        $falta = collect([
            'el CUIT' => filled($config->cuit),
            'el punto de venta' => filled($config->punto_venta),
            'tu condición frente al IVA' => isset(ConfiguracionModel::CONDICIONES_IVA[$config->condicion_iva]),
            'el certificado' => $config->certificadoArca() !== null,
            'la clave privada' => $config->clavePrivadaArca() !== null,
        ])->reject()->keys();

        if ($falta->isNotEmpty()) {
            return 'Falta cargar '.$falta->join(', ', ' y ').'. Hasta entonces los pedidos no se facturan.';
        }

        return $config->arcaEnHomologacion()
            ? 'Listo, pero en HOMOLOGACIÓN: los comprobantes son de prueba y no tienen validez legal.'
            : 'Listo: emitiendo comprobantes con validez legal.';
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Section::make('Identidad del negocio')
                ->description('El nombre, logo y textos que ven tus clientes en la tienda, los PDFs y los emails.')
                ->schema([
                    Forms\Components\TextInput::make('nombre_negocio')
                        ->label('Nombre del negocio')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('eslogan')
                        ->label('Eslogan')
                        ->maxLength(255)
                        ->helperText('Frase corta debajo del nombre, ej: "Distribuidora mayorista". Opcional.'),
                    Forms\Components\Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(2)
                        ->maxLength(500)
                        ->helperText('Se muestra en el pie de página y en los buscadores. Opcional.'),
                    Forms\Components\FileUpload::make('logo')
                        ->label('Logo')
                        ->image()
                        ->acceptedFileTypes(ProductoResource::IMAGENES)
                        ->maxSize(4096)
                        ->directory('branding')
                        ->visibility('public')
                        ->imagePreviewHeight('80')
                        ->helperText('Si no cargás logo, se muestra el nombre del negocio en texto.'),
                ]),
            Forms\Components\Section::make('Aspecto de la tienda')
                ->description('Cambiar de plantilla no borra nada: tus productos, tu menú, tus páginas y tus pedidos quedan igual. Probá la que quieras y volvé cuando quieras.')
                ->schema([
                    Forms\Components\Radio::make('plantilla')
                        ->label('Plantilla')
                        ->required()
                        // Una plantilla que no existe se ve igual que Catálogo,
                        // así que sin esto el dueño no se enteraría del error.
                        ->in(array_keys(ConfiguracionModel::PLANTILLAS))
                        ->options(fn () => array_map(fn ($p) => $p['nombre'], ConfiguracionModel::PLANTILLAS))
                        ->descriptions(fn () => array_map(
                            fn ($p) => $p['descripcion'].' — '.$p['rubros'],
                            ConfiguracionModel::PLANTILLAS
                        )),
                    Forms\Components\Select::make('tipografia')
                        ->label('Tipografía')
                        ->required()
                        ->in(array_keys(ConfiguracionModel::TIPOGRAFIAS))
                        ->selectablePlaceholder(false)
                        ->options(fn () => array_map(fn ($t) => $t['nombre'], ConfiguracionModel::TIPOGRAFIAS))
                        ->helperText('La letra de toda la tienda. No cambia la de los PDFs ni la de los emails, que usan su propia fuente.'),
                    Forms\Components\ColorPicker::make('color_acento')
                        ->label('Color principal')
                        ->nullable()
                        ->helperText('Botones, links y detalles de la tienda (también en PDFs y emails). Elegí un tono medio u oscuro: el texto encima va en blanco. Dejalo vacío para usar el color original.'),
                ]),
            Forms\Components\Section::make('Medidas')
                ->description('El tamaño de las piezas del marco. Andá probando y mirando la tienda: los cambios se ven apenas guardás.')
                ->collapsed()
                ->schema(
                    collect(ConfiguracionModel::MEDIDAS)
                        ->map(fn (array $medida, string $campo) => Forms\Components\Select::make($campo)
                            ->label($medida['etiqueta'])
                            ->required()
                            ->selectablePlaceholder(false)
                            ->in(array_keys($medida['opciones']))
                            ->options(collect($medida['opciones'])
                                ->map(fn (string $nombre, int $px) => $nombre.' — '.$px.$medida['sufijo'])
                                ->all())
                            ->helperText($medida['ayuda']))
                        ->values()
                        ->all()
                ),
            Forms\Components\Section::make('Contacto y redes')
                ->description('Todo es opcional: lo que dejés vacío no aparece en la página.')
                ->schema([
                    Forms\Components\TextInput::make('direccion')->label('Dirección')->maxLength(255),
                    Forms\Components\TextInput::make('ciudad')->label('Ciudad / provincia')->maxLength(255),
                    Forms\Components\TextInput::make('telefono')->label('Teléfono')->maxLength(255),
                    Forms\Components\TextInput::make('whatsapp')
                        ->label('WhatsApp')
                        ->maxLength(30)
                        ->regex('/^\d+$/')
                        ->helperText('Solo números, con código de país y sin el 15. Ej: 5492477504048. Activa el botón flotante de la tienda.'),
                    Forms\Components\TextInput::make('instagram')
                        ->label('Instagram')
                        ->url()
                        ->maxLength(255)
                        ->helperText('El link completo, ej: https://www.instagram.com/tunegocio'),
                    Forms\Components\TextInput::make('medios_pago')
                        ->label('Medios de pago')
                        ->maxLength(255)
                        ->helperText('Separados por coma, ej: Efectivo, Transferencia, MercadoPago. Se muestran en el pie de página.'),
                ]),
            Forms\Components\Section::make('Marca destacada')
                ->description('Si tenés productos de marca propia, elegila acá: aparece como sección propia en el menú de la tienda.')
                ->schema([
                    Forms\Components\Select::make('marca_destacada_id')
                        ->label('Marca')
                        ->options(fn () => Marca::orderBy('nombre')->pluck('nombre', 'id'))
                        ->searchable()
                        ->nullable()
                        ->placeholder('Sin marca destacada'),
                ]),
            Forms\Components\Section::make('Avisos')
                ->description('Para enterarte de un pedido nuevo sin tener que entrar al panel.')
                ->schema([
                    Forms\Components\TextInput::make('email_avisos')
                        ->label('Email para avisos de pedidos')
                        ->email()
                        ->maxLength(255)
                        ->helperText('Te llega un email con el detalle cada vez que un cliente hace un pedido en la web. Dejalo vacío para no recibir avisos.'),
                ]),
            Forms\Components\Section::make('Secciones de la tienda')
                ->description('Los ítems del menú se manejan en Catálogo → Menú de la tienda. Acá quedan las opciones que no son del menú.')
                ->schema([
                    Forms\Components\Toggle::make('mostrar_combos')
                        ->label('Combos en la portada')
                        ->helperText('La franja de combos en la página de inicio. El ítem "Combos" del menú se prende y apaga desde Menú de la tienda.'),
                    Forms\Components\Toggle::make('mostrar_lista_precios')
                        ->label('Lista de precios')
                        ->helperText('La herramienta interna de lista de precios (HTML/PDF) que ve tu personal. Pensada para venta mayorista.'),
                ]),
            Forms\Components\Section::make('Envío')
                ->description('Si repartís, definí desde qué monto sale gratis. Si no repartís, apagalo y el checkout ofrece solo retiro.')
                ->schema([
                    Forms\Components\Toggle::make('hace_envios')
                        ->label('Hacés envíos a domicilio')
                        ->helperText('Apagado, el cliente solo puede elegir "retiro" al confirmar el pedido. Prendido, se le pide la dirección: antes se podía confirmar un envío sin domicilio ni teléfono y el negocio no sabía dónde entregar.'),
                    Forms\Components\TextInput::make('envio_gratis_desde')
                        ->label('Envío gratis a partir de')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->prefix('$')
                        ->helperText('Se muestra en el carrito y en el checkout. Dejalo en 0 si no ofrecés envío gratis: la franja no se muestra.'),
                ]),
            Forms\Components\Section::make('Cobro online')
                ->description('Para que tus clientes paguen en el momento con MercadoPago. Apagado, todo funciona como hasta ahora: el pedido se confirma y el pago lo arreglás vos.')
                ->schema([
                    Forms\Components\Select::make('modo_cobro')
                        ->label('Cómo cobrás')
                        ->options(ConfiguracionModel::MODOS_DE_COBRO)
                        ->required()
                        ->live()
                        ->helperText('Si vendés a clientes de cuenta corriente, "Solo pago online" no te sirve: elegí que el cliente pueda coordinar.'),

                    // Solo aparecen si el negocio eligió cobrar online: pedirle
                    // credenciales a alguien que no las va a usar es ruido.
                    Forms\Components\Placeholder::make('estado_credenciales')
                        ->label('Estado')
                        ->visible(fn (Forms\Get $get) => $get('modo_cobro') !== 'coordinar')
                        ->content(fn () => $this->estadoDeLasCredenciales()),

                    Forms\Components\TextInput::make('mp_access_token')
                        ->label('Access Token de MercadoPago')
                        ->password()
                        ->revealable()
                        ->visible(fn (Forms\Get $get) => $get('modo_cobro') !== 'coordinar')
                        // No se precarga y solo se guarda si escribieron algo:
                        // así abrir la pantalla y guardar no pisa el token que
                        // ya estaba con un campo vacío. Mismo criterio que la
                        // contraseña en Clientes.
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText('Lo sacás de tu cuenta de MercadoPago, en "Tus integraciones". Se guarda encriptado. Dejalo vacío para conservar el que ya cargaste.'),

                    Forms\Components\TextInput::make('mp_webhook_secret')
                        ->label('Clave secreta de las notificaciones')
                        ->password()
                        ->revealable()
                        ->visible(fn (Forms\Get $get) => $get('modo_cobro') !== 'coordinar')
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText('MercadoPago te la da al configurar las notificaciones. Sin esto no podemos comprobar que un aviso de pago sea auténtico, así que los pagos no se van a acreditar solos.'),

                    Forms\Components\Placeholder::make('url_webhook')
                        ->label('Dirección para las notificaciones')
                        ->visible(fn (Forms\Get $get) => $get('modo_cobro') !== 'coordinar')
                        ->content(fn () => url('/webhooks/mercadopago'))
                        ->helperText('Copiala en MercadoPago → Tus integraciones → Webhooks, y elegí el evento "Pagos".'),
                ]),
            Forms\Components\Section::make('Factura electrónica')
                ->description('Desde julio de 2026 facturar electrónicamente es obligatorio. Con esto el pedido se convierte en factura sin cargarlo de nuevo en ARCA.')
                ->schema([
                    Forms\Components\Toggle::make('factura_activa')
                        ->label('Emitir facturas desde la tienda')
                        ->live(),

                    Forms\Components\Placeholder::make('estado_arca')
                        ->label('Estado')
                        ->visible(fn (Forms\Get $get) => (bool) $get('factura_activa'))
                        ->content(fn () => $this->estadoDeLaFacturacion()),

                    Forms\Components\Select::make('arca_ambiente')
                        ->label('Ambiente')
                        ->options([
                            'homologacion' => 'Homologación (pruebas, sin validez legal)',
                            'produccion' => 'Producción (comprobantes reales)',
                        ])
                        ->default('homologacion')
                        ->visible(fn (Forms\Get $get) => (bool) $get('factura_activa'))
                        ->helperText('Probá siempre primero en homologación. El certificado es distinto en cada ambiente.'),

                    Forms\Components\TextInput::make('cuit')
                        ->label('CUIT del negocio')
                        ->maxLength(11)
                        ->regex('/^\d{11}$/')
                        ->visible(fn (Forms\Get $get) => (bool) $get('factura_activa'))
                        ->helperText('Los 11 números, sin guiones.'),

                    Forms\Components\TextInput::make('punto_venta')
                        ->label('Punto de venta')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(99999)
                        ->visible(fn (Forms\Get $get) => (bool) $get('factura_activa'))
                        ->helperText('El que habilitaste en ARCA para facturación electrónica por web service.'),

                    Forms\Components\Select::make('condicion_iva')
                        ->label('Tu condición frente al IVA')
                        ->options(ConfiguracionModel::CONDICIONES_IVA)
                        ->visible(fn (Forms\Get $get) => (bool) $get('factura_activa'))
                        ->helperText('Define qué comprobante se emite. Si no coincide con lo que ARCA tiene registrado, los comprobantes salen rechazados.'),

                    Forms\Components\Textarea::make('arca_certificado')
                        ->label('Certificado')
                        ->rows(4)
                        ->visible(fn (Forms\Get $get) => (bool) $get('factura_activa'))
                        // Mismo criterio que el token de MercadoPago: no se
                        // precarga y solo se guarda si pegan algo, así que
                        // guardar otra cosa no lo borra por accidente.
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText('El archivo .crt que te dio ARCA, pegado entero (incluidas las líneas BEGIN y END). Se guarda encriptado.'),

                    Forms\Components\Textarea::make('arca_clave_privada')
                        ->label('Clave privada')
                        ->rows(4)
                        ->visible(fn (Forms\Get $get) => (bool) $get('factura_activa'))
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText('La clave con la que generaste el pedido de certificado. Sin ella no se puede firmar nada. Se guarda encriptada.'),
                ]),
            Forms\Components\Section::make('Venta por mayor')
                ->description('El precio por mayor de cada producto se carga en su presentación (Catálogo → Productos).')
                ->schema([
                    Forms\Components\TextInput::make('pedido_minimo_mayorista')
                        ->label('Pedido mínimo para clientes mayoristas')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->prefix('$')
                        ->helperText('Solo se les exige a los clientes registrados como negocio. Dejalo en 0 si no querés pedido mínimo.'),
                ]),
            Forms\Components\Section::make('Stock')
                ->description('Definí si el stock cargado limita lo que se puede comprar.')
                ->schema([
                    Forms\Components\Toggle::make('controlar_stock')
                        ->label('Controlar stock')
                        ->helperText('Si lo apagás, se puede comprar cualquier producto en cualquier cantidad sin importar el stock cargado. El número de stock sigue existiendo y se sigue actualizando con cada pedido (podés reactivar el control cuando quieras), solo deja de frenar la compra.'),
                ]),
        ]);
    }

    public function guardar(): void
    {
        ConfiguracionModel::actual()->update($this->form->getState());

        Notification::make()
            ->title('Configuración guardada')
            ->success()
            ->send();
    }
}
