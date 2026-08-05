/**
 * El aspecto de la tienda sale de una carpeta: la que el negocio eligió en el
 * panel (Configuración → Aspecto de la tienda). Cada plantilla pisa los
 * archivos que quiere y hereda todo el resto del motor.
 *
 *   Plantillas/vidriera/Layout.vue        pisa el marco
 *   Plantillas/vidriera/ProductCard.vue   pisa la tarjeta de producto
 *   Plantillas/vidriera/Pages/Home.vue    pisa una pantalla
 *
 * Lo que no está en la carpeta se hereda: por eso una plantilla nueva no tiene
 * que reescribir el carrito, el checkout ni "mis pedidos" para verse distinta.
 */

// Lo pone app.blade.php antes de cargar app.js. El default cubre las cargas
// sueltas, sin blade.
export const plantillaActiva = (typeof window !== 'undefined' && window.__plantilla) || 'catalogo';

// Eager a propósito: el marco y la tarjeta se necesitan en el primer pintado, y
// resolverlos con una promesa haría parpadear la tienda entera. Son dos
// archivos por plantilla, no pesan.
const compartidos = import.meta.glob('./*/*.vue', { eager: true });

// Las pantallas sí van perezosas, igual que las del motor: se baja solo la que
// se está mirando.
const pantallas = import.meta.glob('./*/Pages/**/*.vue');

/**
 * El componente de la plantilla activa, o el del motor si esta plantilla no lo
 * pisó. Lo usan los despachadores (Layouts/PublicLayout.vue y
 * Components/ProductCard.vue), que son los que importan las páginas.
 */
export function dePlantilla(nombre, delMotor) {
    return compartidos[`./${plantillaActiva}/${nombre}.vue`]?.default ?? delMotor;
}

/** La pantalla propia de la plantilla, o null si la hereda del motor. */
export function pantallaDePlantilla(nombre) {
    return pantallas[`./${plantillaActiva}/Pages/${nombre}.vue`] ?? null;
}
