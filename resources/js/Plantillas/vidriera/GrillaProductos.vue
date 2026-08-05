<script setup>
/**
 * En Vidriera los productos van más grandes y más separados: cuatro columnas
 * como máximo (Catálogo llega a seis) y mucho más aire vertical, que es lo que
 * hace que una foto se lea como vidriera y no como góndola.
 */
import ProductCard from '@/Components/ProductCard.vue';

defineProps({
    productos: { type: Array, required: true },
    variante: { type: String, default: 'catalogo' },
});

const emit = defineEmits(['imageClick']);

const CLASES = {
    catalogo: 'grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-x-4 sm:gap-x-6 gap-y-10',
    relacionados: 'grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-x-4 sm:gap-x-6 gap-y-10',
    carrito: 'grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-8',
    sugerencias: 'grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-8',
};
</script>

<template>
    <div :class="CLASES[variante] || CLASES.catalogo">
        <ProductCard v-for="p in productos" :key="p.id" :producto="p" @image-click="emit('imageClick', $event)" />
    </div>
</template>
