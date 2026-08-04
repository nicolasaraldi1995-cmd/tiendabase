# Guía del negocio — cómo manejar tu tienda

Tu tienda se maneja completa desde el **panel de administración**: entrá a `tudominio.com/admin` con tu usuario y contraseña. No necesitás saber nada técnico ni tocar código.

> **Lo primero que tenés que hacer**: cambiar tu contraseña. Panel → Clientes → tu usuario → Editar → nueva contraseña.

## 1. La identidad de tu tienda (una sola vez)

En **Herramientas → Configuración** cargás todo lo que identifica a tu negocio:

- **Nombre, eslogan y descripción** — aparecen en el sitio, los PDFs y los emails.
- **Logo** — se sube ahí mismo; si no cargás uno, se muestra el nombre en texto.
- **Color principal** — el color de tus botones, links y detalles, también en los PDFs y emails. Elegí un tono medio u oscuro (el texto encima va en blanco). Si lo dejás vacío, se usa el color original.
- **Dirección, teléfono, WhatsApp, Instagram, medios de pago** — lo que dejés vacío simplemente no aparece en la página.
- **Secciones de la tienda** — apagá lo que tu rubro no usa (filtros de alimentos, combos, ofertas, lista de precios mayorista). Podés volver a prenderlas cuando quieras.
- **Email para avisos** — poné tu email y te llega un aviso con el detalle cada vez que entra un pedido por la web, así no dependés de estar mirando el panel. Dejalo vacío si no querés avisos.
- **Envío gratis** — el monto desde el cual el envío es gratis (0 = no ofrecés envío gratis).
- **Control de stock** — si está prendido, no se puede comprar más que el stock cargado.

## 2. Cargar tu catálogo

El orden natural es: **Marcas → Categorías → Productos**.

- En **Catálogo → Productos** creás cada producto con su marca, categoría, foto y sus **presentaciones** (cada tamaño/formato con su precio y stock).
- ¿Tenés el catálogo en una planilla? **Herramientas → Importador** carga productos masivamente desde Excel. Junto con la tienda recibiste `plantilla-catalogo.xlsx` y la guía **GUIA-CARGA-CATALOGO.md**: ahí está el paso a paso, incluso para convertir una lista desordenada usando una IA gratuita.
- Los precios se actualizan de a uno, o en masa con **Herramientas → Actualizar precios** (por porcentaje, por marca, etc.).
- **Precio por mayor** (en cada presentación): lo pagan siempre los clientes registrados como *negocio*. Si además completás "desde esta cantidad", cualquier cliente que lleve esa cantidad o más también lo paga — y la tienda le avisa sola ("Llevando 6: $800 c/u"). Si el producto además está en oferta, el cliente paga el más barato de los dos: nunca se suman los dos descuentos.
- En **Configuración → Venta por mayor** ponés el **pedido mínimo** que se les exige a los clientes mayoristas (0 = sin mínimo). A los particulares nunca se les exige.
- Las ofertas se arman por producto o en masa con **Herramientas → Ofertas masivas**.

## 3. La portada

En **Banners** subís las imágenes grandes de la portada. Cada banner puede llevar a una marca, una categoría o una sección. La portada también muestra sola los más vendidos y las categorías con productos.

## 4. Tus páginas ("Nosotros", "Cómo comprar", "Preguntas frecuentes")

En **Catálogo → Páginas** escribís las páginas propias de tu negocio: contás tu historia, explicás cómo se compra o respondés las preguntas que más te hacen (horarios, zonas de envío, medios de pago).

- Ponés un título y escribís el texto con el editor (negrita, listas, títulos, links).
- Cada página aparece **sola en el pie de la tienda**, en el orden que elijas.
- Si apagás "Publicada", deja de verse en la web pero no se borra.

Una página de preguntas frecuentes bien hecha te ahorra la mitad de las consultas por WhatsApp.

## 5. Los pedidos, día a día

- Cada compra aparece en **Ventas → Pedidos**. Al cambiar el estado (confirmado, preparando, enviado, entregado) el cliente recibe un email automático.
- Desde el pedido podés: escribirle al cliente por **WhatsApp** con un click, descargar el **remito en PDF**, **registrar pagos** (efectivo, transferencia, MercadoPago) y ver el saldo pendiente.
- El stock se descuenta y devuelve solo (si cancelás un pedido, las unidades vuelven).
- **Ventas → Cargar pedido** es para pedidos que te llegan por fuera de la web (teléfono, WhatsApp, mostrador).

## 6. La plata (solo el usuario admin la ve)

- **Finanzas → Caja** — resumen de ingresos por período.
- **Finanzas → Gastos** — registrá tus gastos para ver el resultado real.
- El escritorio del panel muestra el resumen financiero y el stock valorizado.

## 7. Usuarios

- Tus clientes se **registran solos** en la web y pueden ver sus pedidos y repetir compras.
- Podés crear usuarios **operador** para tus empleados: cargan stock y manejan pedidos, pero **no ven costos ni finanzas**.

## 8. Copias de seguridad

La tienda hace un **backup diario automático** de toda la base de datos y guarda los últimos 14. No tenés que hacer nada — pero no borres la carpeta `storage/` del servidor.

## ¿Algo no aparece en la página?

- ¿Falta una sección del menú? Revisá **Configuración → Secciones de la tienda** (puede estar apagada).
- ¿No aparece un producto? Fijate que esté **activo**, que tenga al menos una **presentación activa** y (si controlás stock) que tenga stock.
- ¿No aparece un dato de contacto? En **Configuración** ese campo está vacío.
- ¿No aparece una de tus páginas en el pie? Fijate que esté marcada como **Publicada**.
- ¿No te llegan los avisos de pedidos? Revisá que tengas cargado el **email para avisos** en Configuración, y mirá la carpeta de spam.

Ante cualquier otra cosa, hablá con quien te instaló la tienda.
