<script setup>
/**
 * En Mostrador los productos son filas, así que van uno abajo del otro. En
 * pantalla ancha se parten en dos columnas para no dejar medio monitor vacío;
 * en el celular, que es donde se usa, siempre es una sola lista.
 */
import ProductCard from '@/Components/ProductCard.vue';

defineProps({
    productos: { type: Array, required: true },
    variante: { type: String, default: 'catalogo' },
});

const emit = defineEmits(['imageClick']);

const CLASES = {
    catalogo: 'grid grid-cols-1 2xl:grid-cols-2 2xl:gap-x-8',
    relacionados: 'grid grid-cols-1 2xl:grid-cols-2 2xl:gap-x-8',
    // En el carrito y el checkout la lista va en una columna angosta.
    carrito: 'grid grid-cols-1',
    sugerencias: 'grid grid-cols-1',
};
</script>

<template>
    <div :class="CLASES[variante] || CLASES.catalogo">
        <ProductCard v-for="p in productos" :key="p.id" :producto="p" @image-click="emit('imageClick', $event)" />
    </div>
</template>
