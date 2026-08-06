<script setup>
/**
 * Los cartelitos de las etiquetas de un producto ("Sin TACC", "Inoxidable",
 * "Bajo pedido"). Antes esto eran tres insignias fijas escritas en cada
 * tarjeta; ahora salen de lo que cargó el negocio en Catálogo → Etiquetas.
 *
 * Vive en un solo lugar aunque las cuatro plantillas lo usen: el color y el
 * texto los pone el dueño, así que lo único que cambia entre plantillas es el
 * tamaño y dónde se apoya.
 */
defineProps({
    etiquetas: { type: Array, default: () => [] },
    // 'sobreFoto' se lee encima de la imagen; 'enLinea' acompaña al texto.
    variante: { type: String, default: 'sobreFoto' },
});

// Sin color elegido usa el color principal de la tienda, así una etiqueta
// nueva acompaña el diseño en vez de desentonar.
function fondo(etiqueta) {
    return etiqueta.color ? { backgroundColor: etiqueta.color } : {};
}
</script>

<template>
    <template v-if="etiquetas.length">
        <span v-for="e in etiquetas" :key="e.id"
            class="font-bold uppercase tracking-wider text-white shadow-sm"
            :class="[
                variante === 'sobreFoto' ? 'text-[10px] px-1.5 py-0.5 rounded' : 'text-[10.5px] px-2 py-0.5 rounded-full',
                e.color ? '' : 'bg-accent',
            ]"
            :style="fondo(e)">
            {{ e.nombre }}
        </span>
    </template>
</template>
