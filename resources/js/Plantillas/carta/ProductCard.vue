<script setup>
/**
 * Tarjeta de la plantilla Carta: un renglón, como en una carta de papel. El
 * nombre a la izquierda, el precio a la derecha, y una guía de puntos entre los
 * dos para que el ojo no se pierda cuando la lista es larga.
 *
 * La foto es opcional y chica: en este rubro el catálogo es corto y lo que
 * decide la compra es el nombre, no la imagen. Si el negocio cargó foto se
 * muestra como miniatura; si no, el renglón queda limpio y no hay huecos.
 */
import { toRef } from 'vue';
import { precioDelProducto } from '@/Composables/precioDelProducto';
import EtiquetasDelProducto from '@/Components/EtiquetasDelProducto.vue';

const props = defineProps({ producto: Object });
const emit = defineEmits(['imageClick']);

const {
    elegida, presentaciones, seleccionada,
    precioOriginal, precioFinal, enOferta,
    puedeVerPrecios, mayoristaDesde, sinStock, enCarrito,
    imagen,
    elegir, agregarAlCarrito, comoPlata,
} = precioDelProducto(toRef(props, 'producto'));
</script>

<template>
    <div class="py-4 border-b border-border/70">
        <div class="flex items-start gap-3.5">
            <div v-if="imagen" class="w-14 h-14 rounded-lg overflow-hidden bg-surface-2 shrink-0 cursor-pointer"
                @click="emit('imageClick', imagen)">
                <img :src="imagen" :alt="producto.nombre" loading="lazy" class="w-full h-full object-cover" />
            </div>

            <div class="flex-1 min-w-0">
                <!-- Nombre y precio en la misma línea base, unidos por la guía
                     de puntos: es el gesto que hace que esto se lea como carta. -->
                <div class="flex items-baseline gap-2">
                    <h3 class="text-[15px] font-semibold text-text shrink-0 max-w-[70%] truncate">{{ producto.nombre }}</h3>
                    <span class="flex-1 border-b border-dotted border-border-hover translate-y-[-3px]"></span>
                    <span v-if="puedeVerPrecios && seleccionada" class="shrink-0 text-[15px] price-display" :class="enOferta ? 'text-red-500' : 'text-text'">
                        ${{ comoPlata(precioFinal) }}
                    </span>
                    <a v-else-if="!puedeVerPrecios" :href="route('login')" class="shrink-0 text-[12px] font-semibold text-accent hover:text-accent-bright">Ver precio</a>
                </div>

                <div class="flex items-center flex-wrap gap-x-2.5 gap-y-1 mt-1">
                    <span v-if="producto.marca?.nombre" class="text-[12px] text-text-muted">{{ producto.marca.nombre }}</span>
                    <del v-if="puedeVerPrecios && enOferta" class="text-[11.5px] text-text-muted">${{ comoPlata(precioOriginal) }}</del>
                    <span v-if="producto.nuevo" class="text-[10.5px] font-bold uppercase tracking-wide text-amber-600">Nuevo</span>
                    <EtiquetasDelProducto :etiquetas="producto.etiquetas" variante="enLinea" />
                    <span v-if="enCarrito" class="text-[10.5px] font-bold uppercase tracking-wide text-accent">En pedido</span>
                    <span v-if="puedeVerPrecios && mayoristaDesde" class="text-[11.5px] font-semibold text-accent">
                        Llevando {{ mayoristaDesde.cantidad }}: ${{ comoPlata(mayoristaDesde.precio) }} c/u
                    </span>
                </div>

                <div class="flex items-center justify-between gap-3 mt-2">
                    <!-- Las presentaciones acá son porciones o tamaños. -->
                    <div v-if="presentaciones.length > 1" class="flex flex-wrap gap-1.5">
                        <button v-for="(p, i) in presentaciones" :key="p.id" @click="elegir(i)"
                            class="min-h-[34px] px-3 text-[12.5px] rounded-full border transition-colors"
                            :class="i === elegida ? 'border-accent text-accent bg-accent/10 font-semibold' : 'border-border text-text-secondary hover:border-border-hover'">
                            {{ p.unidad }}
                        </button>
                    </div>
                    <span v-else-if="presentaciones.length === 1 && presentaciones[0].unidad" class="text-[12px] text-text-muted">{{ presentaciones[0].unidad }}</span>
                    <span v-else></span>

                    <template v-if="puedeVerPrecios">
                        <span v-if="sinStock" class="shrink-0 text-[12px] font-semibold text-text-muted">Sin stock</span>
                        <button v-else @click="agregarAlCarrito()"
                            class="shrink-0 h-9 px-4 rounded-full text-[12.5px] font-semibold text-white bg-accent hover:bg-accent-bright active:scale-[0.97] transition-all">
                            {{ enCarrito ? 'Sumar otro' : 'Agregar' }}
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
