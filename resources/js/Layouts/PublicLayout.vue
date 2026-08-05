<script setup>
import { dePlantilla } from '@/Plantillas/resolver';
import MarcoCatalogo from '@/Plantillas/catalogo/Layout.vue';

/**
 * Despachador: el marco de la tienda (topbar, menú, pie) lo pone la plantilla
 * que el negocio eligió en el panel. Las páginas siguen importando este archivo
 * y no se enteran de cuál está activa, así que el carrito, el checkout y "mis
 * pedidos" cambian de aspecto sin tocarles una línea.
 *
 * El default es el de la plantilla Catálogo: una plantilla que solo quiera
 * cambiar la tarjeta de producto no está obligada a escribir su propio marco.
 */
const Marco = dePlantilla('Layout', MarcoCatalogo);
</script>

<template>
    <component :is="Marco">
        <!-- Se reenvían todas las ranuras que haya pasado la página: la de
             siempre y #encabezado, que usa la portada para el banner de ancho
             completo. Solo se crea la ranura que llegó, así que el marco puede
             seguir preguntando por $slots.encabezado. -->
        <template v-for="(_, ranura) in $slots" #[ranura]="datos">
            <slot :name="ranura" v-bind="datos || {}" />
        </template>
    </component>
</template>
