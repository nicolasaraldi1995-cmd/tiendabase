<script setup>
import { dePlantilla } from '@/Plantillas/resolver';
import TarjetaCatalogo from '@/Plantillas/catalogo/ProductCard.vue';

/**
 * Despachador, igual que PublicLayout: la tarjeta de producto la pone la
 * plantilla activa. Cambiarla acá cambia de una la portada, las categorías,
 * las marcas, ofertas y nuevos, que la usan todas.
 */
defineOptions({ inheritAttrs: false });

const Tarjeta = dePlantilla('ProductCard', TarjetaCatalogo);
</script>

<template>
    <!-- Pasan de largo tanto el producto como el escuchador de @image-click:
         ninguno se declara acá para no tener que repetirlo en cada plantilla. -->
    <component :is="Tarjeta" v-bind="$attrs" />
</template>
