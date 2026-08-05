<script setup>
/**
 * Cómo se acomodan los productos en la plantilla Catálogo: una grilla densa,
 * que en una pantalla ancha llega a seis columnas.
 *
 * Que esto sea un componente y no clases sueltas en cada página es lo que
 * permite que una plantilla muestre los productos como LISTA en vez de grilla
 * (Mostrador, Carta) sin tener que reescribir cada pantalla: con cambiar este
 * archivo, cambian la portada, las categorías, las marcas, las ofertas, los
 * nuevos y hasta los recomendados del carrito.
 */
import ProductCard from '@/Components/ProductCard.vue';

defineProps({
    productos: { type: Array, required: true },
    // Cada lugar donde aparecen productos tiene su ancho disponible.
    variante: { type: String, default: 'catalogo' },
});

const emit = defineEmits(['imageClick']);

const CLASES = {
    catalogo: 'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-4',
    relacionados: 'grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-4',
    carrito: 'grid grid-cols-2 sm:grid-cols-4 gap-3',
    sugerencias: 'grid grid-cols-2 md:grid-cols-4 gap-3',
};
</script>

<template>
    <div :class="CLASES[variante] || CLASES.catalogo">
        <ProductCard v-for="p in productos" :key="p.id" :producto="p" @image-click="emit('imageClick', $event)" />
    </div>
</template>
