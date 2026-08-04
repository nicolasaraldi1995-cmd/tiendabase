<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ pagina: Object });
</script>

<template>
    <Head :title="pagina.titulo" />
    <PublicLayout>
        <div class="max-w-3xl mx-auto px-6 py-10">
            <h1 class="text-2xl font-semibold text-text mb-6">{{ pagina.titulo }}</h1>
            <!-- El contenido lo escribe el dueño desde el panel (editor con formato). -->
            <div v-if="pagina.contenido" class="contenido text-[15px] text-text-secondary leading-relaxed" v-html="pagina.contenido"></div>
        </div>
    </PublicLayout>
</template>

<style scoped>
.contenido :deep(h2) { font-size: 1.15rem; font-weight: 600; color: rgb(var(--accent-dim)); margin: 1.75rem 0 0.6rem; }
.contenido :deep(h3) { font-size: 1rem; font-weight: 600; margin: 1.4rem 0 0.5rem; }
.contenido :deep(p) { margin-bottom: 0.9rem; }
.contenido :deep(ul), .contenido :deep(ol) { margin: 0 0 0.9rem 1.25rem; }
.contenido :deep(ul) { list-style: disc; }
.contenido :deep(ol) { list-style: decimal; }
.contenido :deep(li) { margin-bottom: 0.35rem; }
.contenido :deep(a) { color: rgb(var(--accent)); text-decoration: underline; }
.contenido :deep(strong) { font-weight: 600; }
.contenido :deep(blockquote) { border-left: 3px solid rgb(var(--accent) / 0.4); padding-left: 1rem; margin: 1rem 0; font-style: italic; }
</style>
