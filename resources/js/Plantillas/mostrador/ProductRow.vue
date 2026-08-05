<script setup>
/**
 * En Catálogo esto es una fila que se desliza al costado. Acá no: los productos
 * ya son filas, así que deslizarlos horizontalmente sería pelear con la lectura.
 * Se muestran los primeros y listo — el "ver todos" de cada sección lleva al
 * resto.
 */
import ProductCard from '@/Components/ProductCard.vue';

defineProps({ productos: { type: Array, default: () => [] } });
const emit = defineEmits(['imageClick']);
</script>

<template>
    <div>
        <ProductCard v-for="p in productos.slice(0, 6)" :key="p.id" :producto="p" @image-click="emit('imageClick', $event)" />
    </div>
</template>
