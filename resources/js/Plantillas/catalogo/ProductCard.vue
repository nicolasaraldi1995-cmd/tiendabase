<script setup>
/**
 * Tarjeta de la plantilla Catálogo: densa, con el precio y el botón siempre a
 * la vista. La cuenta (oferta, mayorista, stock, precio por kilo) sale del
 * composable que comparten todas las plantillas; acá solo se decide cómo se ve.
 */
import { ref, toRef } from 'vue';
import { precioDelProducto } from '@/Composables/precioDelProducto';

const props = defineProps({ producto: Object });
const emit = defineEmits(['imageClick']);

const mostrarControles = ref(false);

const {
    elegida, cantidad, presentaciones, seleccionada,
    precioOriginal, precioFinal, enOferta, descuento,
    puedeVerPrecios, mayoristaDesde, sinStock, enCarrito,
    precioPorUnidad, imagen, MAXIMO,
    elegir, agregarAlCarrito, comoPlata,
} = precioDelProducto(toRef(props, 'producto'));

function agregar() {
    agregarAlCarrito(() => { mostrarControles.value = false; });
}
</script>

<template>
    <div class="bg-surface-1 rounded-xl overflow-hidden flex flex-col h-full transition-all duration-300"
        :class="enCarrito ? 'border border-accent/40 shadow-sm shadow-accent/5' : 'border border-[rgba(0,0,0,0.14)] hover:border-border-hover hover:shadow-lg hover:shadow-black/8'">

        <!-- Image -->
        <div class="relative aspect-[5/4] bg-surface-2 overflow-hidden cursor-pointer shrink-0" @click="imagen && emit('imageClick', imagen)">
            <img v-if="imagen" :src="imagen" :alt="producto.nombre" loading="lazy" class="w-full h-full object-cover hover:scale-[1.03] transition-transform duration-500" />
            <div v-else class="w-full h-full flex items-center justify-center">
                <svg class="w-8 h-8 text-surface-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>

            <span v-if="enOferta && descuento > 0" class="absolute top-2 right-2 bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-lg shadow-sm">-{{ descuento }}%</span>
            <span v-if="enCarrito" class="absolute top-2 left-2 bg-accent text-white text-[9px] font-bold px-2 py-0.5 rounded-lg flex items-center gap-1 shadow-sm">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                En carrito
            </span>

            <!-- Insignias comerciales: agrupadas en la esquina de la imagen para no ocupar espacio del contenido -->
            <div class="absolute bottom-2 left-2 right-2 flex flex-wrap gap-1">
                <span v-if="producto.nuevo" class="text-[8px] font-bold uppercase tracking-wider text-white bg-amber-500 px-1.5 py-0.5 rounded shadow-sm">Nuevo</span>
                <span v-if="producto.sin_tacc" class="text-[8px] font-bold uppercase tracking-wider text-accent-dim bg-white/95 px-1.5 py-0.5 rounded shadow-sm">Sin TACC</span>
                <span v-if="producto.frio" class="text-[8px] font-bold uppercase tracking-wider text-white bg-sky-500 px-1.5 py-0.5 rounded shadow-sm">Frío</span>
                <span v-if="producto.congelado" class="text-[8px] font-bold uppercase tracking-wider text-white bg-blue-600 px-1.5 py-0.5 rounded shadow-sm">Congelado</span>
            </div>
        </div>

        <div class="p-3.5 flex-1 flex flex-col">
            <!-- Marca como pill translúcida: se diferencia claramente del nombre del producto -->
            <span class="brand-label inline-block max-w-full truncate text-accent-dim bg-accent-muted px-2.5 py-1 rounded-full">{{ producto.marca?.nombre }}</span>

            <h3 class="product-title text-text text-[14.5px] leading-tight line-clamp-2 min-h-[2.3rem] mt-2">{{ producto.nombre }}</h3>

            <!-- Pills: alto fijo y recorte de overflow para que productos con muchas o pocas presentaciones ocupen el mismo espacio. mb-3 fijo asegura que nunca queden pegadas al bloque de precio/botón, incluso cuando el mt-auto de ese bloque no tiene espacio extra para empujar -->
            <div class="mt-2 mb-3 min-h-[30px] overflow-hidden">
                <div v-if="presentaciones.length > 1" class="flex flex-wrap gap-1.5">
                    <button v-for="(p, i) in presentaciones" :key="p.id" @click="elegir(i)"
                        class="px-3 py-[6px] text-[11.5px] font-semibold rounded-lg transition-all duration-200"
                        :class="i === elegida ? 'bg-accent text-white shadow-sm shadow-accent/30 scale-[1.04]' : 'bg-surface-3 text-text-secondary hover:bg-surface-4 hover:text-text'">
                        {{ p.unidad }}
                    </button>
                </div>
                <span v-else-if="presentaciones.length === 1" class="inline-block px-2.5 py-1 text-[11px] font-medium text-text-secondary bg-surface-3 rounded-lg">{{ presentaciones[0].unidad }}</span>
            </div>

            <!-- Bloque comercial (borde + precio + botón) anclado al fondo como una unidad: así el precio y el botón quedan a la misma altura en toda la fila, sin importar cuánto contenido (marca/nombre/presentaciones) tenga cada card arriba -->
            <div class="mt-auto">
                <div class="border-t border-[rgba(0,0,0,0.08)] pt-2.5">
                    <!-- Sin sesión no hay precio que mostrar: el backend no lo manda -->
                    <div v-if="!puedeVerPrecios" class="flex items-center gap-1.5 py-0.5">
                        <svg class="w-3.5 h-3.5 text-text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                        <span class="text-[12px] font-semibold text-text-secondary">Precio para clientes</span>
                    </div>
                    <div v-else-if="seleccionada" class="flex items-baseline gap-2">
                        <span class="text-2xl price-display truncate" :class="enOferta ? 'text-red-500' : 'text-text'">${{ comoPlata(precioFinal) }}</span>
                        <del v-if="enOferta" class="text-[11px] text-text-muted shrink-0">${{ comoPlata(precioOriginal) }}</del>
                    </div>
                    <div v-if="puedeVerPrecios && precioPorUnidad" class="flex items-center gap-1.5 mt-1.5">
                        <p class="text-[9.5px] text-text-muted">${{ precioPorUnidad.precio.toLocaleString('es-AR') }}/{{ precioPorUnidad.unidad }}</p>
                    </div>
                    <!-- Precio por mayor: lo calcula el backend (ver Presentacion::mejorPrecioPorCantidad) -->
                    <p v-if="puedeVerPrecios && mayoristaDesde" class="text-[10px] font-semibold text-accent mt-1.5">
                        Llevando {{ mayoristaDesde.cantidad }}: ${{ comoPlata(mayoristaDesde.precio) }} c/u
                    </p>
                </div>

                <!-- Add to cart: mutable button -->
                <div class="pt-3">
                    <Transition name="fade" mode="out-in">
                        <a v-if="!puedeVerPrecios" key="login" :href="route('login')"
                            class="w-full flex items-center justify-center gap-1.5 text-[13px] font-bold py-2.5 rounded-lg transition-all duration-200 bg-surface-3 text-text hover:bg-surface-4 active:scale-[0.98]">
                            Iniciá sesión para comprar
                        </a>
                        <div v-else-if="mostrarControles && !sinStock" key="controls" class="flex items-center gap-2">
                            <div class="inline-flex items-center bg-surface-3 rounded-lg shrink-0">
                                <button @click="cantidad = Math.max(1, cantidad - 1)"
                                    class="w-8 h-8 flex items-center justify-center text-text-muted hover:text-text text-sm transition">−</button>
                                <input type="number" :value="cantidad" @change="e => { let v = parseInt(e.target.value) || 1; cantidad = Math.max(1, Math.min(v, MAXIMO)); e.target.value = cantidad; }"
                                    min="1" :max="MAXIMO"
                                    class="w-10 h-8 text-center text-[12px] font-semibold text-text bg-transparent border-0 p-0 focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none" />
                                <button @click="cantidad = Math.min(cantidad + 1, MAXIMO)"
                                    class="w-8 h-8 flex items-center justify-center text-text-muted hover:text-text text-sm transition">+</button>
                            </div>
                            <button @click="agregar"
                                class="flex-1 text-[13px] font-bold py-2.5 rounded-lg bg-accent text-white shadow-sm shadow-accent/25 hover:bg-accent-bright hover:shadow-md hover:shadow-accent/30 active:scale-[0.98] transition-all">
                                Agregar
                            </button>
                        </div>

                        <button v-else-if="sinStock" key="sinstock" disabled
                            class="w-full text-[13px] font-bold py-2.5 rounded-lg bg-surface-3 text-text-muted cursor-not-allowed">
                            Sin stock
                        </button>

                        <button v-else key="add" @click="mostrarControles = true"
                            class="w-full flex items-center justify-center gap-1.5 text-[13px] font-bold py-2.5 rounded-lg transition-all duration-200 bg-accent text-white shadow-sm shadow-accent/25 hover:bg-accent-bright hover:shadow-md hover:shadow-accent/30 active:scale-[0.98]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 1.876-4.788 2.234-7.393a1.126 1.126 0 00-1.106-1.257H5.106M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
                            </svg>
                            {{ enCarrito ? 'Agregar más' : 'Agregar al carrito' }}
                        </button>
                    </Transition>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.product-title {
    font-weight: 700;
    letter-spacing: -0.01em;
}
</style>
