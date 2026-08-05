<script setup>
/**
 * Igual que en Mostrador: los productos ya son renglones, así que deslizarlos
 * al costado no tendría sentido. Se muestran los primeros de la sección.
 */
import ProductCard from '@/Components/ProductCard.vue';

defineProps({ productos: { type: Array, default: () => [] } });
const emit = defineEmits(['imageClick']);
</script>

<template>
    <div class="max-w-2xl">
        <ProductCard v-for="p in productos.slice(0, 8)" :key="p.id" :producto="p" @image-click="emit('imageClick', $event)" />
    </div>
</template>
