# TiendaBase

Motor de tienda online autogestiva, listo para instalar para cualquier negocio: catálogo público (Inertia + Vue 3) y panel de administración (Filament 3) para gestionar productos, precios, stock, pedidos, pagos y la identidad del negocio (nombre, logo, textos, contacto) — todo desde el panel, sin tocar código.

## Cómo funciona el modelo

Una instalación por negocio: se clona este repo, se instala, y el dueño del negocio carga **su** logo, **sus** banners, **sus** productos e imágenes desde el panel. El código no tiene ninguna marca fija.

Al entregar la tienda, pasale al cliente tres archivos: [GUIA-DEL-NEGOCIO.md](GUIA-DEL-NEGOCIO.md) (manual de autogestión del panel, para gente no técnica), [GUIA-CARGA-CATALOGO.md](GUIA-CARGA-CATALOGO.md) (cómo armar el catálogo desde cualquier lista de precios, con ayuda de una IA si hace falta) y [plantilla-catalogo.xlsx](plantilla-catalogo.xlsx) (la planilla modelo que lee el Importador).

## Stack

- **Backend:** Laravel 13, PHP 8.3, Sanctum
- **Frontend:** Inertia.js + Vue 3 + Tailwind CSS (Vite)
- **Panel admin:** Filament 3 (`/admin`)
- **Otros:** `barryvdh/laravel-dompdf` (listas de precios y pedidos en PDF), `phpoffice/phpspreadsheet` (importación de catálogo), `ziggy` (rutas de Laravel disponibles en JS)

## Requisitos

- PHP 8.3+ con extensión `sqlite3` (para tests) y `pdo_mysql`
- MySQL 8+
- Node 20+
- Composer
- [Laragon](https://laragon.org/) (recomendado en Windows: genera automáticamente el dominio local `tiendabase.test`)

## Nueva tienda para un cliente (~10 minutos)

Cada tienda es un clon de este repo con su propia base y su propio dominio. Con Laragon corriendo:

**1. Clonar el motor** (el nombre de la carpeta define el dominio local `<carpeta>.test`):

```bash
git clone C:/laragon/www/tiendabase C:/laragon/www/nombrecliente
```

**2. Crear la base de datos** (o desde el menú de Laragon → MySQL):

```bash
mysql -u root -e "CREATE DATABASE nombrecliente CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

**3. Configurar el `.env`**: copiá `.env.example` a `.env` y cambiá:

- `APP_NAME` — el nombre del negocio
- `APP_URL` — `http://nombrecliente.test`
- `DB_DATABASE` — `nombrecliente`
- **`APP_ENV=local` y `APP_DEBUG=true`** — el ejemplo viene en modo producción a propósito (olvidarse tiene que ser seguro, no peligroso). Si los dejás así en tu máquina, la cookie de sesión sale exigiendo HTTPS y **no vas a poder ni entrar al panel**: el navegador la descarta sobre `http://…test` y todo POST te va a dar 419.

En el servidor, en cambio, esos dos no se tocan.

**4. Instalar todo de una** (dependencias, clave, migraciones + usuarios, link de storage, assets):

```bash
composer run setup
```

**5. Puesta a punto en el panel**: el paso anterior imprime las contraseñas de las dos cuentas — **anotalas, no se vuelven a mostrar**. Entrá a `http://nombrecliente.test/admin` con `admin@tienda.test` y seguí el checklist de más abajo ("Puesta a punto de una tienda nueva").

El `setup` es idempotente: se puede correr de nuevo sin romper ni duplicar nada (aunque regenera la `APP_KEY`; para tiendas ya andando usá `actualizar`).

## Actualizar una tienda ya instalada

Cuando el motor mejora, cada tienda trae los cambios desde su clon:

```bash
git pull origin main
composer run actualizar
```

`actualizar` corre dependencias, migraciones nuevas y assets, sin tocar la `APP_KEY` ni los datos.

### Correr todo junto (server + queue + logs + vite)

```bash
composer run dev
```

## Puesta a punto de una tienda nueva

El seeder crea dos usuarios y **sortea una contraseña para cada uno, que imprime una sola vez** al correr `composer run setup` (no hay ninguna clave escrita en el repositorio). Si preferís fijarlas de antemano, poné `CLAVE_ADMIN` y `CLAVE_OPERADOR` en el `.env` antes de instalar.

Si nadie anotó la contraseña, se recupera desde la consola del servidor:

```bash
php artisan usuarios:listar
php artisan usuarios:clave admin@tienda.test una-clave-nueva
```

Las dos cuentas son:

- `admin@tienda.test` (rol admin)
- `operador@tienda.test` (rol operador)

Checklist para dejar la tienda con identidad propia — todo desde el panel:

1. **Panel → Herramientas → Configuración**: nombre del negocio, eslogan, descripción, logo, dirección, teléfono, WhatsApp, Instagram, medios de pago, envío gratis (0 = no se ofrece), control de stock. Lo que quede vacío no aparece en la página.
2. **Configuración → Aspecto de la tienda**: plantilla (Catálogo, Vidriera, Mostrador o Carta), tipografía y color principal. Se puede cambiar cuando sea: no afecta a los datos cargados.
3. **Panel → Banners**: cargar los banners de la portada.
4. **Panel → Catálogo**: marcas, categorías y productos (o importar todo desde Excel con Herramientas → Importador).
5. (Opcional) **Configuración → Marca destacada**: si el negocio tiene marca propia, elegirla ahí — aparece como sección en el menú de la tienda.

## El correo lo configurás vos (el cliente no)

Sin esto **no sale un solo correo**: ni la recuperación de contraseña, ni el aviso de pedido nuevo, ni el cambio de estado. `MAIL_MAILER` viene en `log`, que escribe a un archivo y no manda nada.

Va en el `.env` del servidor, no en el panel. Y **la pregunta que define el camino es una sola: ¿el negocio tiene dominio propio?**

**Si NO tiene** (solo Instagram y WhatsApp, que es lo más común) → **su propio Gmail de negocio**. Esa dirección ya es su identidad, la que sus clientes conocen.

Necesita verificación en dos pasos activada en esa cuenta de Google, y de ahí una **clave de aplicación** ([myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords)) — la contraseña normal de Gmail no sirve:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=elnegocio@gmail.com
MAIL_PASSWORD=los16caracteres
MAIL_FROM_ADDRESS=elnegocio@gmail.com
MAIL_FROM_NAME="Nombre del negocio"
```

`MAIL_USERNAME` y `MAIL_FROM_ADDRESS` **tienen que ser la misma dirección**: Gmail no deja mandar en nombre de otra y rechaza el envío. Es el error más común. Tope: ~500 correos por día, de sobra para una tienda chica.

**Si SÍ tiene dominio** → **Resend** con su dominio verificado: los correos salen de `pedidos@sunegocio.com`, llegan mejor y se ve serio. `MAIL_MAILER=resend` y `RESEND_API_KEY`, ya soportado en `config/mail.php`.

**Nunca uses una sola cuenta para varios clientes.** Si una tienda manda mucho o alguien la marca como spam, se caen los correos de todas.

**Verificalo antes de entregar** — que el comando no falle no alcanza, tiene que llegar:

```bash
php artisan tinker --execute='Mail::raw("Prueba", function($m) { $m->to("vos@ejemplo.com")->subject("Prueba"); });'
```

Y por último, en el panel: **Configuración → Avisos → "Email para avisos de pedidos"**. Sin ese campo el negocio no se entera de las ventas nuevas.

## Antes de entregar la tienda

- **Dale de baja tu cuenta de admin**, o bajala a operador, después de que el dueño cree la suya. Mientras exista, podés cambiarle las credenciales de cobro — o sea, a qué cuenta va su plata. Sacártelo de encima es un argumento de venta, no una molestia.
- **Si el negocio va a cobrar online**, mostrale **Herramientas → Conectar MercadoPago**: es la guía paso a paso que ya trae el panel, con su dirección de notificaciones lista para copiar. Las credenciales las carga él; vos no las necesitás ni las ves.
- **Verificá que el programador de tareas del hosting corra** (`schedule:run` cada minuto). De él dependen el backup diario y —si cobra online— el comando que libera el stock de los pedidos abandonados y rescata los pagos cuyo aviso se perdió.

## Roles

- **admin**: acceso completo al panel, incluye precios, costos y pagos.
- **operador**: acceso operativo al panel (stock, pedidos) sin ver precios de costo ni finanzas.

Se asignan en el campo `role` del usuario (`App\Models\User`).

## Tests

Los tests corren contra SQLite en memoria, no requieren MySQL levantado:

```bash
php artisan test
vendor/bin/pint --test   # chequeo de estilo, sin modificar archivos
vendor/bin/pint          # aplica el estilo automáticamente
```

## Backups

`php artisan backup:database` genera un dump comprimido (`.sql.gz`) de la base MySQL en `storage/app/backups/` (nunca se sube a git — son datos reales de clientes) y borra automáticamente los backups más viejos que los últimos 14 (`--keep=N` para cambiar la cantidad).

```bash
php artisan backup:database
```

**Activar el backup diario automático (Windows/Laragon local):** correr una sola vez, con Laragon cerrado o abierto, en una terminal (ajustá la ruta del proyecto y de PHP si difieren):

```powershell
schtasks /create /tn "TiendaBase DB Backup" /tr "\"C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe\" \"C:\laragon\www\tiendabase\artisan\" backup:database" /sc daily /st 20:00 /f
```

Esto corre el backup todos los días a las 20:00 **si la PC está prendida y Laragon (MySQL) está corriendo** en ese momento — cambiá `/st 20:00` por el horario que más te convenga. Para desactivarlo: `schtasks /delete /tn "TiendaBase DB Backup" /f`.

**En un hosting real** (Linux con cron), no hace falta el paso anterior: alcanza con la entrada de cron estándar de Laravel corriendo cada minuto —

```
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

— porque el backup diario ya está registrado en `routes/console.php` (`Schedule::command('backup:database')->dailyAt('03:00')`).

**Restaurar un backup** (reemplaza todo el contenido actual de la base — usar con cuidado):

```bash
gzip -dc storage/app/backups/tiendabase-2026-08-04_20-00-00.sql.gz | mysql -u root tiendabase
```

## Deploy a producción

El `.env.example` viene con los valores **de producción** puestos. En tu máquina bajás `APP_ENV` a `local` y `APP_DEBUG` a `true`; en el servidor no tocás nada. Está al revés a propósito: olvidarse tiene que ser seguro, no peligroso.

| Variable | Local | Producción |
|---|---|---|
| `APP_ENV` | `local` | `production` (viene así) |
| `APP_DEBUG` | `true` | `false` (viene así) — con `true` en vivo, un error muestra rutas de archivos y consultas a cualquiera |
| `APP_URL` | `http://tiendabase.test` | `https://tu-dominio-real.com` |
| `SESSION_SECURE_COOKIE` | **(comentada)** | **(comentada)** — `config/session.php` la deduce sola. Escribirla, aunque sea vacía, pisa esa deducción y la cookie viaja también por HTTP |
| `LOG_LEVEL` | `debug` | `error` — con `debug` se escribe todo y el archivo crece sin techo |
| `LOG_STACK` | `single` | `daily` — rota por día y borra los viejos solo |
| `DB_HOST` / `DB_USERNAME` / `DB_PASSWORD` | localhost, root, sin clave | los que te dé el hosting |
| `MAIL_MAILER` | `log` | el proveedor real que elijas (Resend, SES, etc.) |

Con `APP_ENV=production`, el sitio ya fuerza que todas las URLs generadas usen `https://` automáticamente (`AppServiceProvider`), así que no hace falta tocar código para eso — solo el `.env` del servidor.

**Antes de anunciar el sitio a clientes:**
1. **Corré `php artisan key:generate` en cada instalación nueva.** Nunca reutilices el `APP_KEY` de otra tienda: si dos clientes comparten clave, la cookie de sesión de uno vale en la tienda del otro.
2. **Verificá que el vhost apunte a `public/`, no a la raíz del proyecto.** Es el error de instalación más caro: con la raíz expuesta, `/.env` se descarga como texto plano con la clave de la base adentro. Comprobalo con `curl -i https://tu-dominio.com/.env` — tiene que dar 404, nunca 200.
3. Cambiá los correos y las contraseñas de las cuentas sembradas (`admin@tienda.test` y `operador@tienda.test`) desde el panel → Clientes, y poné las del dueño real.
4. Corré `php artisan config:cache` y `php artisan route:cache` en el servidor después de cada deploy (acelera bastante; si no lo hacés no rompe nada, pero es más lento).
5. Verificá que `storage/` y `bootstrap/cache/` tengan permisos de escritura para el usuario del servidor web.

**Cosas que dependen del servidor y no del código** (nginx no lee `.htaccess`, así que si el hosting usa nginx nada de lo que hay en `public/.htaccess` corre):

- Bloquear los archivos que empiezan con punto (`.env`, `.git/`) a nivel servidor, como segunda barrera.
- `expose_php = Off` y `display_errors = Off` en el `php.ini`.
- `storage/app/backups` fuera del alcance web: esos `.sql.gz` tienen la base entera.
- No subir `node_modules/` ni `.git/` al servidor.

## Notas de arquitectura

- La identidad del negocio (nombre, logo, contacto, marca destacada, etc.) vive en la tabla `configuraciones` (`App\Models\Configuracion`, un solo registro) y se edita en el panel → Herramientas → Configuración. `HandleInertiaRequests` la comparte con todas las páginas Vue como `$page.props.negocio`; los PDFs y emails la leen con `Configuracion::actual()`.
- **Plantillas**: el aspecto de la tienda sale de una carpeta en `resources/js/Plantillas/<clave>/`, elegida en `configuraciones.plantilla` (las opciones están en `Configuracion::PLANTILLAS`). Una plantilla pisa los archivos que quiere y hereda el resto del motor; con estos cuatro alcanza para cambiarla entera sin tocar ninguna página:

  | Archivo | Qué controla |
  |---|---|
  | `Layout.vue` | El marco: barra, menú, pie. Lo usan las 17 páginas públicas. |
  | `ProductCard.vue` | Cómo se ve un producto (caja, fila, renglón). |
  | `GrillaProductos.vue` | Cómo se acomodan (grilla densa, lista de una columna). |
  | `ProductRow.vue` | Las tiras por sección de la portada. |

  El reparto lo hacen tres despachadores (`Components/ProductCard.vue`, `Components/GrillaProductos.vue`, `Components/ProductRow.vue` y `Layouts/PublicLayout.vue`) contra `Plantillas/resolver.js`; `app.js` hace lo mismo con las pantallas, buscando primero en `Plantillas/<clave>/Pages/`. Lo que la plantilla no define cae al motor, así que el carrito, el checkout y "mis pedidos" salen bien sin escribirlos. La cuenta de precios NO vive en las tarjetas: está en `Composables/precioDelProducto.js`, compartida por las cuatro. **Para agregar una plantilla**: creá la carpeta, agregá la entrada en `Configuracion::PLANTILLAS` y listo — `PlantillasTest` avisa si declarás una que no existe en disco.
- La tipografía y el color de acento son variables CSS (`--fuente`, `--accent`): el default está en `resources/css/app.css` y `app.blade.php` inyecta el override elegido en el panel. La tipografía llega a la tienda, no a los PDFs (dompdf usa sus propias fuentes).
- El stock de `Presentacion` se reserva/libera automáticamente vía `PedidoItemObserver` cada vez que se crea, actualiza o elimina un `PedidoItem` (checkout, autoservicio del cliente en "Mis pedidos", o edición desde el panel admin). Al cancelar un pedido desde el panel, `Pedido::restaurarStock()` devuelve las unidades reservadas.
- La lógica de carrito (sesión) vive en `App\Services\CartService`, compartida entre `CartController` y `CheckoutController`.
- **Cobro online con MercadoPago (Checkout Pro)**, apagado de fábrica (`configuraciones.modo_cobro` en `coordinar`). Las credenciales son de cada negocio y viven encriptadas en la base, no en el `.env`: el dueño las carga solo desde el panel. Un pedido que se paga online nace en `awaiting_payment` —reserva stock pero no entra al circuito del negocio— y lo único que puede marcarlo pagado es el webhook firmado (`/webhooks/mercadopago`), que además le vuelve a preguntar el monto y el estado a MercadoPago en vez de creerle al aviso. El alta del pago es idempotente por el índice único de `pagos.mp_payment_id`, porque MercadoPago reintenta el mismo aviso por diseño. Los pedidos impagos vencen a los `Pedido::MINUTOS_PARA_PAGAR` y devuelven el stock, pero antes se consulta si se pagó: cancelar una venta cobrada es el peor error del circuito.
- Los pagos que carga el negocio a mano (efectivo, transferencia, MercadoPago informado) siguen igual y conviven con los online en el mismo modelo `Pago`. La Caja excluye MercadoPago del arqueo de efectivo a propósito: liquida por afuera.
