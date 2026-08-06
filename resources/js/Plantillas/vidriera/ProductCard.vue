<script setup>
/**
 * Tarjeta de la plantilla Vidriera: la foto se lleva casi toda la tarjeta, en
 * vertical, y el texto queda al mínimo. Sin marco ni sombra: lo que separa un
 * producto del otro es el aire, no una caja.
 *
 * El control de compra está siempre visible y no aparece al pasar el mouse: en
 * un celular no hay mouse, y esconderlo ahí sería dejar la tienda sin botón.
 */
import { ref, toRef } from 'vue';
import { precioDelProducto } from '@/Composables/precioDelProducto';
import EtiquetasDelProducto from '@/Components/EtiquetasDelProducto.vue';

const props = defineProps({ producto: Object });
const emit = defineEmits(['imageClick']);

const mostrarControles = ref(false);

const {
    elegida, cantidad, presentaciones, seleccionada,
    precioOriginal, precioFinal, enOferta, descuento,
    puedeVerPrecios, mayoristaDesde, sinStock, enCarrito,
    imagen, MAXIMO,
    elegir, ajustar, agregarAlCarrito, comoPlata,
} = precioDelProducto(toRef(props, 'producto'));

function agregar() {
    agregarAlCarrito(() => { mostrarControles.value = false; });
}
</script>

<template>
    <div class="group flex flex-col h-full">
        <!-- Foto vertical: es la proporción con la que se fotografía la ropa y
             el calzado, y deja ver la prenda entera. -->
        <div class="relative aspect-[3/4] bg-surface-2 overflow-hidden cursor-pointer"
            @click="imagen && emit('imageClick', imagen)">
            <img v-if="imagen" :src="imagen" :alt="producto.nombre" loading="lazy"
                class="w-full h-full object-cover group-hover:scale-[1.04] transition-transform duration-700 ease-out" />
            <div v-else class="w-full h-full flex items-center justify-center">
                <svg class="w-9 h-9 text-surface-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>

            <span v-if="producto.nuevo" class="absolute top-3 left-3 text-[10px] uppercase tracking-[0.16em] text-text bg-surface-1/95 px-2.5 py-1">Nuevo</span>
            <span v-if="enOferta && descuento > 0" class="absolute top-3 right-3 text-[10px] uppercase tracking-[0.16em] text-white bg-red-500 px-2.5 py-1">-{{ descuento }}%</span>
            <span v-if="enCarrito" class="absolute bottom-3 left-3 text-[10px] uppercase tracking-[0.16em] text-white bg-accent px-2.5 py-1">En carrito</span>

            <div v-if="producto.etiquetas?.length" class="absolute bottom-3 right-3 flex flex-wrap justify-end gap-1 max-w-[70%]">
                <EtiquetasDelProducto :etiquetas="producto.etiquetas" />
            </div>
        </div>

        <div class="pt-3 flex-1 flex flex-col">
            <span v-if="producto.marca?.nombre" class="text-[10.5px] uppercase tracking-[0.2em] text-text-muted">{{ producto.marca.nombre }}</span>

            <h3 class="text-[14px] leading-snug text-text line-clamp-2 mt-1 min-h-[2.5rem]">{{ producto.nombre }}</h3>

            <!-- En ropa esto es el talle, así que va antes que el precio. Los
                 botones son de 40px: elegir talle es EL gesto de esta plantilla
                 y se hace con el pulgar, no con un mouse. -->
            <div v-if="presentaciones.length > 1" class="flex flex-wrap gap-1.5 mt-2.5">
                <button v-for="(p, i) in presentaciones" :key="p.id" @click="elegir(i)"
                    class="min-w-[42px] min-h-[40px] px-2.5 text-[12px] uppercase tracking-wider transition-all"
                    :class="i === elegida ? 'bg-text text-surface-1' : 'bg-surface-2 text-text-secondary hover:bg-surface-3'">
                    {{ p.unidad }}
                </button>
            </div>
            <span v-else-if="presentaciones.length === 1 && presentaciones[0].unidad" class="text-[11px] uppercase tracking-wider text-text-muted mt-2.5">{{ presentaciones[0].unidad }}</span>

            <div class="mt-auto pt-3">
                <div v-if="!puedeVerPrecios">
                    <a :href="route('login')" class="block w-full text-center text-[11px] uppercase tracking-[0.18em] text-text border border-border hover:border-text py-3 transition-colors">
                        Ingresá para ver el precio
                    </a>
                </div>

                <template v-else>
                    <div class="flex items-baseline gap-2 mb-2.5">
                        <span class="text-[17px] price-display" :class="enOferta ? 'text-red-500' : 'text-text'">${{ comoPlata(precioFinal) }}</span>
                        <del v-if="enOferta" class="text-[11px] text-text-muted">${{ comoPlata(precioOriginal) }}</del>
                    </div>
                    <p v-if="mayoristaDesde" class="text-[10px] uppercase tracking-wider text-accent -mt-1.5 mb-2.5">
                        Llevando {{ mayoristaDesde.cantidad }}: ${{ comoPlata(mayoristaDesde.precio) }} c/u
                    </p>

                    <button v-if="sinStock" disabled
                        class="w-full text-[11px] uppercase tracking-[0.18em] text-text-muted border border-border py-3 cursor-not-allowed">
                        Sin stock
                    </button>

                    <!-- Los botones de cantidad son de 44px de alto: es la
                         medida en la que un dedo no falla. -->
                    <div v-else-if="mostrarControles" class="flex items-stretch gap-2">
                        <div class="flex items-center border border-border shrink-0">
                            <button @click="ajustar(-1)" aria-label="Menos" class="w-10 h-11 text-text-muted hover:text-text transition">−</button>
                            <span class="w-8 text-center text-[13px] font-semibold tabular-nums">{{ cantidad }}</span>
                            <button @click="ajustar(1)" aria-label="Más" class="w-10 h-11 text-text-muted hover:text-text transition">+</button>
                        </div>
                        <button @click="agregar" class="flex-1 text-[11px] uppercase tracking-[0.18em] text-white bg-accent hover:bg-accent-bright transition-colors">
                            Agregar
                        </button>
                    </div>

                    <button v-else @click="mostrarControles = true"
                        class="w-full text-[11px] uppercase tracking-[0.18em] text-text border border-text/70 hover:text-white hover:bg-text hover:border-text py-3 transition-colors">
                        {{ enCarrito ? 'Agregar más' : 'Agregar' }}
                    </button>
                </template>
            </div>
        </div>
    </div>
</template>
