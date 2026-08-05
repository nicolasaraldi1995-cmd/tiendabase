<script setup>
/**
 * Tarjeta de la plantilla Mostrador: no es una tarjeta, es una fila. Miniatura,
 * nombre, precio y los botones + y − a mano, sin pasos intermedios.
 *
 * Está pensada para el cliente que ya sabe qué quiere y viene a repetir un
 * pedido desde el celular: en una pantalla entran diez o doce productos en vez
 * de cuatro, y agregar uno son dos toques y no cuatro.
 */
import { toRef } from 'vue';
import { precioDelProducto } from '@/Composables/precioDelProducto';

const props = defineProps({ producto: Object });
const emit = defineEmits(['imageClick']);

const {
    elegida, cantidad, presentaciones, seleccionada,
    precioOriginal, precioFinal, enOferta, descuento,
    puedeVerPrecios, mayoristaDesde, sinStock, enCarrito,
    precioPorUnidad, imagen,
    elegir, ajustar, agregarAlCarrito, comoPlata,
} = precioDelProducto(toRef(props, 'producto'));
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 py-3 border-b border-border transition-colors"
        :class="enCarrito ? 'bg-accent/5' : 'hover:bg-surface-2/60'">

        <div class="w-14 h-14 sm:w-16 sm:h-16 bg-surface-2 rounded-lg overflow-hidden shrink-0 cursor-pointer"
            @click="imagen && emit('imageClick', imagen)">
            <img v-if="imagen" :src="imagen" :alt="producto.nombre" loading="lazy" class="w-full h-full object-cover" />
            <div v-else class="w-full h-full flex items-center justify-center">
                <svg class="w-5 h-5 text-surface-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
        </div>

        <!-- Nombre y presentación: se lleva todo el ancho que sobre. -->
        <div class="flex-1 min-w-[45%]">
            <div class="flex items-center gap-2 flex-wrap">
                <span v-if="producto.marca?.nombre" class="text-[11px] font-medium uppercase tracking-wide text-accent-dim">{{ producto.marca.nombre }}</span>
                <span v-if="producto.nuevo" class="text-[10px] font-bold uppercase tracking-wide text-white bg-amber-500 px-1.5 py-0.5 rounded">Nuevo</span>
                <span v-if="enOferta && descuento > 0" class="text-[10px] font-bold text-white bg-red-500 px-1.5 py-0.5 rounded">-{{ descuento }}%</span>
                <span v-if="enCarrito" class="text-[10px] font-bold uppercase tracking-wide text-accent">En carrito</span>
            </div>
            <h3 class="text-[14px] font-semibold text-text leading-snug mt-0.5">{{ producto.nombre }}</h3>

            <div v-if="presentaciones.length > 1" class="flex flex-wrap gap-1.5 mt-1.5">
                <button v-for="(p, i) in presentaciones" :key="p.id" @click="elegir(i)"
                    class="min-h-[32px] px-2.5 text-[12px] font-semibold rounded-lg transition-all"
                    :class="i === elegida ? 'bg-accent text-white' : 'bg-surface-3 text-text-secondary hover:bg-surface-4'">
                    {{ p.unidad }}
                </button>
            </div>
            <span v-else-if="presentaciones.length === 1 && presentaciones[0].unidad" class="text-[12px] text-text-muted">{{ presentaciones[0].unidad }}</span>
        </div>

        <!-- Precio: alineado a la derecha para que toda la columna se lea de un
             vistazo cuando el cliente compara. -->
        <div class="text-right shrink-0 min-w-[86px]">
            <template v-if="puedeVerPrecios && seleccionada">
                <div class="flex items-baseline justify-end gap-1.5">
                    <span class="text-[17px] price-display" :class="enOferta ? 'text-red-500' : 'text-text'">${{ comoPlata(precioFinal) }}</span>
                    <del v-if="enOferta" class="text-[11px] text-text-muted">${{ comoPlata(precioOriginal) }}</del>
                </div>
                <p v-if="precioPorUnidad" class="text-[10.5px] text-text-muted">${{ precioPorUnidad.precio.toLocaleString('es-AR') }}/{{ precioPorUnidad.unidad }}</p>
                <p v-if="mayoristaDesde" class="text-[11px] font-semibold text-accent leading-tight">
                    x{{ mayoristaDesde.cantidad }}: ${{ comoPlata(mayoristaDesde.precio) }}
                </p>
            </template>
            <a v-else :href="route('login')" class="text-[12px] font-semibold text-accent hover:text-accent-bright">Ver precio</a>
        </div>

        <!-- El paso a paso del catálogo (tocar "Agregar", después elegir
             cantidad) acá sobra: se pide de a muchos y siempre lo mismo. -->
        <div v-if="puedeVerPrecios" class="shrink-0 ml-auto sm:ml-0">
            <span v-if="sinStock" class="text-[12px] font-semibold text-text-muted px-2">Sin stock</span>
            <div v-else class="flex items-center gap-1.5">
                <!-- 44px en el celular y más compactos en la computadora: acá
                     el pedido se carga con el pulgar, y por debajo de eso el
                     dedo le pega al botón de al lado. Con mouse no hace falta. -->
                <div class="flex items-center bg-surface-3 rounded-lg">
                    <button @click="ajustar(-1)" aria-label="Menos" class="w-11 h-11 sm:w-9 sm:h-10 text-text-secondary hover:text-text text-base transition">−</button>
                    <span class="w-7 text-center text-[13px] font-bold tabular-nums">{{ cantidad }}</span>
                    <button @click="ajustar(1)" aria-label="Más" class="w-11 h-11 sm:w-9 sm:h-10 text-text-secondary hover:text-text text-base transition">+</button>
                </div>
                <button @click="agregarAlCarrito()" aria-label="Agregar al carrito"
                    class="h-11 sm:h-10 px-4 sm:px-3.5 rounded-lg bg-accent text-white hover:bg-accent-bright active:scale-[0.97] transition-all">
                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>
