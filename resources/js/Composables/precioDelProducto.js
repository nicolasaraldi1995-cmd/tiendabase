import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

/**
 * Toda la cuenta de una tarjeta de producto: qué presentación está elegida, qué
 * precio le corresponde a este cliente, si hay stock y cómo se agrega al
 * carrito.
 *
 * Vive acá y no adentro de cada tarjeta porque cada plantilla tiene la suya: si
 * la cuenta estuviera repetida cuatro veces, un arreglo de precios hecho en una
 * dejaría a las otras tres mostrando mal la plata. La plantilla decide cómo se
 * ve; esto decide cuánto sale.
 */
export function precioDelProducto(producto) {
    const page = usePage();

    const elegida = ref(0);
    const cantidad = ref(1);

    const presentaciones = computed(() => producto.value?.presentaciones || []);
    const seleccionada = computed(() => presentaciones.value[elegida.value]);
    const precioOriginal = computed(() => (seleccionada.value ? parseFloat(seleccionada.value.precio) : 0));

    const enOferta = computed(() => {
        const p = seleccionada.value;
        if (!p) return false;
        if (!p.oferta_porcentaje && !p.oferta_precio) return false;
        const hoy = new Date().toISOString().split('T')[0];
        if (p.oferta_inicio && p.oferta_inicio > hoy) return false;
        if (p.oferta_fin && p.oferta_fin < hoy) return false;
        return true;
    });

    const precioFinal = computed(() => {
        const p = seleccionada.value;
        if (!p) return 0;
        if (enOferta.value) {
            if (p.oferta_precio) return parseFloat(p.oferta_precio);
            if (p.oferta_porcentaje) return Math.round(precioOriginal.value * (1 - p.oferta_porcentaje / 100) * 100) / 100;
        }
        return precioOriginal.value;
    });

    const descuento = computed(() => {
        const p = seleccionada.value;
        if (!enOferta.value || !p) return 0;
        if (p.oferta_porcentaje) return Math.round(p.oferta_porcentaje);
        if (p.oferta_precio) return Math.round((1 - parseFloat(p.oferta_precio) / precioOriginal.value) * 100);
        return 0;
    });

    // Los precios solo se muestran a clientes con cuenta: el backend
    // directamente no los manda si no hay sesión (ver Presentacion::toArray).
    const puedeVerPrecios = computed(() => !!page.props.auth?.puedeVerPrecios);

    // El backend decide si hay un precio por mayor que ofrecerle a este cliente.
    const mayoristaDesde = computed(() => seleccionada.value?.mayorista_desde || null);

    // Cuántas unidades quedan es dato del sistema: afuera solo se sabe si hay o
    // no, que es lo que decide si el botón va habilitado. El tope real lo pone
    // el servidor, que avisa cuando se pidió de más (ver AvisoDeError).
    const controlarStock = computed(() => page.props.controlarStock);
    const sinStock = computed(() => controlarStock.value && seleccionada.value?.hay_stock === false);

    const enCarrito = computed(() => {
        if (!seleccionada.value) return false;
        return page.props.cartPresentacionIds?.includes(seleccionada.value.id) ?? false;
    });

    /** "$4.200/kg" cuando la unidad se puede llevar a kilo o litro. */
    const precioPorUnidad = computed(() => {
        if (!seleccionada.value) return null;
        const unidad = seleccionada.value.unidad?.toLowerCase() || '';
        const precio = precioFinal.value;
        const coincide = unidad.match(/^(\d+(?:[.,]\d+)?)\s*(g|gr|grs?|kg|kgs?|ml|cc|lt?|lts?|litros?)$/i);
        if (!coincide) return null;
        const valor = parseFloat(coincide[1].replace(',', '.'));
        const medida = coincide[2].toLowerCase();
        if (['g', 'gr', 'grs'].includes(medida)) return { precio: Math.round(precio / valor * 1000), unidad: 'kg' };
        if (['kg', 'kgs'].includes(medida)) return { precio: Math.round(precio / valor), unidad: 'kg' };
        if (['ml', 'cc'].includes(medida)) return { precio: Math.round(precio / valor * 1000), unidad: 'lt' };
        if (['l', 'lt', 'lts', 'litros'].includes(medida)) return { precio: Math.round(precio / valor), unidad: 'lt' };
        return null;
    });

    const imagen = computed(() => seleccionada.value?.imagen_url || producto.value?.imagen_url || null);

    const MAXIMO = 9999;

    function elegir(i) {
        elegida.value = i;
        cantidad.value = 1;
    }

    function ajustar(delta) {
        cantidad.value = Math.min(Math.max(1, cantidad.value + delta), MAXIMO);
    }

    function agregarAlCarrito(alTerminar = null) {
        if (!seleccionada.value || sinStock.value) return;

        router.post(route('cart.add'), {
            presentacion_id: seleccionada.value.id,
            cantidad: cantidad.value,
        }, { preserveScroll: true });

        cantidad.value = 1;
        alTerminar?.();
    }

    /** 4200 -> "4.200", como se escribe la plata acá. */
    function comoPlata(valor) {
        return valor.toLocaleString('es-AR', { maximumFractionDigits: 0 });
    }

    return {
        elegida, cantidad, presentaciones, seleccionada,
        precioOriginal, precioFinal, enOferta, descuento,
        puedeVerPrecios, mayoristaDesde, sinStock, enCarrito,
        precioPorUnidad, imagen, MAXIMO,
        elegir, ajustar, agregarAlCarrito, comoPlata,
    };
}
