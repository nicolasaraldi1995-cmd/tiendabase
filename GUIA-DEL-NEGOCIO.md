# Guía del negocio — cómo manejar tu tienda

Tu tienda se maneja completa desde el **panel de administración**: entrá a `tudominio.com/admin` con tu usuario y contraseña. No necesitás saber nada técnico ni tocar código.

> **Lo primero que tenés que hacer**: cambiar tu contraseña. Panel → Clientes → tu usuario → Editar → nueva contraseña.

## 1. La identidad de tu tienda (una sola vez)

En **Herramientas → Configuración** cargás todo lo que identifica a tu negocio:

- **Nombre, eslogan y descripción** — aparecen en el sitio, los PDFs y los emails.
- **Logo** — se sube ahí mismo; si no cargás uno, se muestra el nombre en texto.
- **Dirección, teléfono, WhatsApp, Instagram, medios de pago** — lo que dejés vacío simplemente no aparece en la página.
- **Secciones de la tienda** — apagá lo que tu rubro no usa (combos, lista de precios mayorista). Podés volver a prenderlas cuando quieras.
- **Envío a domicilio** — si no repartís, apagalo: al confirmar el pedido tu cliente solo va a poder elegir "retiro". Si lo dejás prendido, la tienda le exige tener cargada su dirección y su teléfono antes de pedir un envío.
- **Email para avisos** — poné tu email y te llega un aviso con el detalle cada vez que entra un pedido por la web, así no dependés de estar mirando el panel. Dejalo vacío si no querés avisos.
- **Envío gratis** — el monto desde el cual el envío es gratis (0 = no ofrecés envío gratis).
- **Control de stock** — si está prendido, no se puede comprar más que el stock cargado.

## 2. El aspecto de tu tienda

También en **Herramientas → Configuración**, en la sección *Aspecto de la tienda*, elegís cómo se ve:

- **Plantilla** — la forma de tu tienda. Hay cuatro:
  - **Catálogo** — menú en una barra lateral fija y grilla densa, con el precio y el stock siempre a la vista. Para catálogos grandes, donde el cliente entra buscando un producto puntual. *(Distribuidora, ferretería, dietética, repuestos, librería.)*
  - **Vidriera** — sin barra lateral: el menú va arriba y la foto ocupa casi toda la tarjeta. Para cuando lo que vende es cómo se ve el producto. *(Ropa, calzado, deco, cosmética, regalería.)*
  - **Mostrador** — una lista compacta: miniatura, nombre, precio y los botones + y − en la misma fila. Para el cliente que ya sabe qué quiere y carga el pedido desde el celular. *(Mayorista con clientes que recompran, corralón, proveeduría.)*
  - **Carta** — solapas por categoría y una línea por producto, como una carta de papel. Para catálogos chicos donde la foto no es lo importante. *(Rotisería, panadería, gastronomía, viveros.)*
- **Tipografía** — la letra de toda la tienda: *Inter* (neutra y moderna), *Poppins* (redondeada y amable), *Lora* (clásica, con serifas) o *Archivo* (compacta y firme). No cambia la letra de los PDFs ni la de los emails, que usan su propia fuente.
- **Color principal** — el color de tus botones, links y detalles, también en los PDFs y emails. Elegí un tono medio u oscuro (el texto encima va en blanco). Si lo dejás vacío, se usa el color original.
- **Medidas** (está plegado, tocá para abrirlo) — el tamaño de las piezas del marco:
  - **Tamaño del logo** — de chico a enorme. Si tu logo se ve chiquito, es acá.
  - **Alto de la barra** — la franja de arriba. Si agrandás el logo, dale más alto para que respire.
  - **Ancho del menú lateral** — solo en la plantilla Catálogo, que es la única con menú al costado. Subilo si tus secciones tienen nombres largos.
  - **Aire entre secciones del menú** — cuánto se separa una de otra.

  Andá probando y mirando la tienda en otra pestaña: los cambios se ven apenas guardás.

> **Cambiar de plantilla no borra nada.** Tus productos, tu menú, tus páginas, tus banners, tu logo, tu color y tus pedidos quedan exactamente igual: la plantilla es solo la ropa que se le pone arriba a los mismos datos. Probá la que quieras y volvé cuando quieras.

## 3. Cargar tu catálogo

El orden natural es: **Marcas → Categorías → Productos**.

- En **Catálogo → Productos** creás cada producto con su marca, categoría, foto y sus **presentaciones** (cada tamaño/formato con su precio y stock).
- ¿Tenés el catálogo en una planilla? **Herramientas → Importador** carga productos masivamente desde Excel. Junto con la tienda recibiste `plantilla-catalogo.xlsx` y la guía **GUIA-CARGA-CATALOGO.md**: ahí está el paso a paso, incluso para convertir una lista desordenada usando una IA gratuita.
- Los precios se actualizan de a uno, o en masa con **Herramientas → Actualizar precios** (por porcentaje, por marca, etc.).
- **Precio por mayor** (en cada presentación): lo pagan siempre los clientes registrados como *negocio*. Si además completás "desde esta cantidad", cualquier cliente que lleve esa cantidad o más también lo paga — y la tienda le avisa sola ("Llevando 6: $800 c/u"). Si el producto además está en oferta, el cliente paga el más barato de los dos: nunca se suman los dos descuentos.
- En **Configuración → Venta por mayor** ponés el **pedido mínimo** que se les exige a los clientes mayoristas (0 = sin mínimo). A los particulares nunca se les exige.
- Las ofertas se arman por producto o en masa con **Herramientas → Ofertas masivas**.

## 4. Tus etiquetas

En **Catálogo → Etiquetas** creás las etiquetas de tus productos: "Sin TACC", "Inoxidable", "Importado", "Bajo pedido", "Planta de interior" — lo que tenga sentido para lo que vendés.

Cada etiqueta tiene tres cosas que decidís vos:

- **Color** — el del cartelito sobre la foto. Si lo dejás vacío usa el color principal de tu tienda.
- **Mostrar como filtro en el menú** — le agrega un acceso al menú para ver solo esos productos.
- **Aviso en el carrito** — si el pedido lleva un producto con esa etiqueta, la tienda muestra ese texto y le ofrece al cliente sacarlos. Por ejemplo: *"Bajo pedido: puede demorar 5 días"* o *"Consultá disponibilidad para tu zona antes de confirmar"*.

Después, en cada producto (Catálogo → Productos) elegís qué etiquetas lleva. Si son muchos productos, seleccionalos en la lista y usá **Agregar etiqueta** de una sola vez.

> Si un cliente ya conoce esas condiciones y no querés que le aparezcan los avisos, marcalo en su ficha (Clientes → editar → *No mostrarle los avisos de etiquetas*).

## 5. La portada

En **Banners** subís las imágenes grandes de la portada. Cada banner puede llevar a una marca, una categoría o una sección. La portada también muestra sola los más vendidos y las categorías con productos.

## 6. Tus páginas ("Nosotros", "Cómo comprar", "Preguntas frecuentes")

En **Catálogo → Páginas** escribís las páginas propias de tu negocio: contás tu historia, explicás cómo se compra o respondés las preguntas que más te hacen (horarios, zonas de envío, medios de pago).

- Ponés un título y escribís el texto con el editor (negrita, listas, títulos, links).
- Cada página aparece **sola en el pie de la tienda**, en el orden que elijas.
- Si apagás "Publicada", deja de verse en la web pero no se borra.

Una página de preguntas frecuentes bien hecha te ahorra la mitad de las consultas por WhatsApp.

## 7. Los pedidos, día a día

- Cada compra aparece en **Ventas → Pedidos**. Al cambiar el estado (confirmado, preparando, enviado, entregado) el cliente recibe un email automático.
- Desde el pedido podés: escribirle al cliente por **WhatsApp** con un click, descargar el **remito en PDF**, **registrar pagos** (efectivo, transferencia, MercadoPago) y ver el saldo pendiente.
- El stock se descuenta y devuelve solo (si cancelás un pedido, las unidades vuelven).
- **Ventas → Cargar pedido** es para pedidos que te llegan por fuera de la web (teléfono, WhatsApp, mostrador).

## 8. Cobrar online con MercadoPago (opcional)

De fábrica la tienda **no cobra online**: el cliente confirma el pedido y el pago lo arreglás vos por transferencia, efectivo o WhatsApp, como siempre. Si querés que pague en el momento, se conecta tu cuenta de MercadoPago.

**Tenés una guía paso a paso adentro del panel: Herramientas → Conectar MercadoPago.** Te muestra dónde buscar cada dato y te da tu dirección de notificaciones lista para copiar. Empezá por ahí.

Lo que conviene saber antes:

- **Las claves son tuyas y la plata va directo a tu cuenta.** Se cargan en tu panel, quedan guardadas encriptadas y nadie más las ve — ni siquiera quien te instaló la tienda.
- **Elegís cómo cobrás** en Configuración → Cobro online: que el pago online sea la única opción, que el cliente elija entre pagar ahora o coordinar, o seguir coordinando siempre. Si vendés a clientes de cuenta corriente, dejale la opción de coordinar.
- **Para cargar o cambiar las claves te pide tu contraseña.** Es a propósito: cambiar esa clave es cambiar a qué cuenta va la plata de todas tus ventas, y no puede hacerlo cualquiera que encuentre el panel abierto.
- **Probá siempre primero con las credenciales de prueba.** MercadoPago te da dos juegos: uno de prueba (plata falsa) y uno de producción (plata real). El panel te avisa cuando estás en modo prueba.
- **MercadoPago se queda una comisión de cada venta.** La fija ellos, no la tienda, y depende de cuándo quieras la plata disponible. Miralo en tu cuenta antes de activarlo.

Cuando alguien paga, el pedido se marca **pagado** solo y el pago aparece en la cuenta corriente del cliente. Si el pedido tarda unos minutos en marcarse, es normal: MercadoPago avisa por su cuenta y reintenta si hace falta. El pago no se pierde.

Y si alguien empieza a pagar y abandona, el pedido se cancela solo al rato y **el stock vuelve al catálogo** — pero antes se le pregunta a MercadoPago si en realidad pagó, así que una venta cobrada nunca se cancela por error.

## 9. La plata (solo el usuario admin la ve)

- **Finanzas → Caja** — resumen de ingresos por período.
- **Finanzas → Gastos** — registrá tus gastos para ver el resultado real.
- El escritorio del panel muestra el resumen financiero y el stock valorizado.

## 10. Usuarios

- Tus clientes se **registran solos** en la web y pueden ver sus pedidos y repetir compras.
- Podés crear usuarios **operador** para tus empleados: cargan stock y manejan pedidos, pero **no ven costos ni finanzas**.
- Si alguien se registra pidiendo **precio mayorista**, no se lo damos solo: queda pendiente y lo habilitás vos desde **Ventas → Clientes**. Vas a ver un número al lado de "Clientes" cuando haya alguien esperando.

## 11. Copias de seguridad

La tienda hace un **backup diario automático** de toda la base de datos y guarda los últimos 14. No tenés que hacer nada — pero no borres la carpeta `storage/` del servidor.

## ¿Algo no aparece en la página?

- ¿Falta una sección del menú? Revisá **Catálogo → Menú de la tienda** (puede estar apagada).
- ¿Falta un filtro? Revisá que la etiqueta tenga prendido *Mostrar como filtro en el menú* (Catálogo → Etiquetas).
- ¿No aparece un producto? Fijate que esté **activo**, que tenga al menos una **presentación activa** y (si controlás stock) que tenga stock.
- ¿No aparece un dato de contacto? En **Configuración** ese campo está vacío.
- ¿No aparece una de tus páginas en el pie? Fijate que esté marcada como **Publicada**.
- ¿No te llegan los avisos de pedidos? Revisá que tengas cargado el **email para avisos** en Configuración, y mirá la carpeta de spam.

Ante cualquier otra cosa, hablá con quien te instaló la tienda.
