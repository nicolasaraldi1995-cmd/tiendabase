<script setup>
import { dePlantilla } from '@/Plantillas/resolver';
import FilaCatalogo from '@/Plantillas/catalogo/ProductRow.vue';

/**
 * Despachador. En Catálogo esto es una fila que se desliza al costado, que es
 * lo que conviene cuando hay cientos de productos y poco lugar. Una plantilla
 * de lista (Mostrador, Carta) la reemplaza por algo vertical.
 */
defineOptions({ inheritAttrs: false });

const Fila = dePlantilla('ProductRow', FilaCatalogo);
</script>

<template>
    <component :is="Fila" v-bind="$attrs" />
</template>
