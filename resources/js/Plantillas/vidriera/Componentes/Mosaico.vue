<script setup>
/**
 * El azulejo con el que Vidriera lista categorías y marcas. Catálogo las
 * muestra en círculos, que es un gesto de góndola; acá van cuadradas y grandes,
 * con el nombre sobre la foto, que es como se arma una vidriera.
 *
 * Vive en Componentes/ y no en la raíz de la plantilla porque ahí solo van los
 * archivos que pisan al motor (Layout y ProductCard).
 */
import { Link } from '@inertiajs/vue3';

defineProps({
    href: { type: String, required: true },
    nombre: { type: String, required: true },
    imagen: { type: String, default: null },
    cantidad: { type: Number, default: null },
    // Las fotos de categoría se recortan; los logos de marca tienen que entrar
    // enteros o se les come el nombre.
    ajuste: { type: String, default: 'cover' },
});
</script>

<template>
    <Link :href="href" class="group block">
        <div class="relative aspect-square bg-surface-2 overflow-hidden">
            <img v-if="imagen" :src="imagen" :alt="nombre" loading="lazy"
                class="w-full h-full transition-transform duration-700 ease-out group-hover:scale-[1.05]"
                :class="ajuste === 'contain' ? 'object-contain p-6' : 'object-cover'" />
            <div v-else class="w-full h-full flex items-center justify-center">
                <span class="text-5xl font-light text-surface-4">{{ nombre.charAt(0) }}</span>
            </div>

            <!-- Degradado solo abajo: el nombre se lee sobre cualquier foto sin
                 tapar la imagen entera. -->
            <div v-if="ajuste === 'cover'" class="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-black/60 to-transparent pointer-events-none"></div>
            <p v-if="ajuste === 'cover'" class="absolute bottom-3 left-3 right-3 text-[12px] uppercase tracking-[0.18em] text-white leading-snug">
                {{ nombre }}
                <span v-if="cantidad !== null" class="block text-[10.5px] tracking-[0.2em] text-white/70 mt-1">{{ cantidad }}</span>
            </p>
        </div>

        <!-- Con logo, el nombre va debajo: encima quedaría sobre fondo claro. -->
        <p v-if="ajuste === 'contain'" class="mt-3 text-center text-[12px] uppercase tracking-[0.18em] text-text group-hover:text-accent transition-colors">
            {{ nombre }}
            <span v-if="cantidad !== null" class="block text-[10.5px] tracking-[0.2em] text-text-muted mt-1">{{ cantidad }}</span>
        </p>
    </Link>
</template>
