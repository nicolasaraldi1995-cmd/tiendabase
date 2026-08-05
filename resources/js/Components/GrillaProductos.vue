<script setup>
import { dePlantilla } from '@/Plantillas/resolver';
import GrillaCatalogo from '@/Plantillas/catalogo/GrillaProductos.vue';

/**
 * Despachador, igual que PublicLayout y ProductCard: cómo se acomodan los
 * productos lo decide la plantilla activa. Las páginas piden "mostrá estos
 * productos" y no saben si van a salir en grilla o en lista.
 */
defineOptions({ inheritAttrs: false });

const Grilla = dePlantilla('GrillaProductos', GrillaCatalogo);
</script>

<template>
    <component :is="Grilla" v-bind="$attrs" />
</template>
