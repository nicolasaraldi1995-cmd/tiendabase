<script setup>
/**
 * En Carta los productos son renglones, uno abajo del otro y en una sola
 * columna angosta: leer una carta es recorrerla de arriba a abajo, no barrer la
 * pantalla en zigzag.
 */
import ProductCard from '@/Components/ProductCard.vue';

defineProps({
    productos: { type: Array, required: true },
    variante: { type: String, default: 'catalogo' },
});

const emit = defineEmits(['imageClick']);
</script>

<template>
    <div class="max-w-2xl">
        <ProductCard v-for="p in productos" :key="p.id" :producto="p" @image-click="emit('imageClick', $event)" />
    </div>
</template>
