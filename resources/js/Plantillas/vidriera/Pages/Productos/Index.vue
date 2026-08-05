<script setup>
/**
 * La pantalla de navegación de Vidriera. Son los mismos modos que en Catálogo
 * (categorías, marcas, búsqueda, listado), pero categorías y marcas se muestran
 * en azulejos cuadrados grandes en vez de círculos, y la grilla de productos es
 * más aireada: cuatro columnas como máximo en vez de seis.
 */
import PublicLayout from '@/Layouts/PublicLayout.vue';
import GrillaProductos from '@/Components/GrillaProductos.vue';
import Pagination from '@/Components/Pagination.vue';
import ImageModal from '@/Components/ImageModal.vue';
import Mosaico from '../../Componentes/Mosaico.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({ modo: String, productos: Object, productosPorCategoria: Array, totalResultados: Number, items: Array, breadcrumb: Array, marcas: Array, categorias: Array, categoriaActual: Object, marcaActual: Object, filtros: Object });

const fotoAmpliada = ref(null);
const buscar = ref(props.filtros.buscar || '');
let esperando = null;

watch(buscar, (valor) => {
    clearTimeout(esperando);
    esperando = setTimeout(() => {
        if (valor.length >= 2) router.get(route('productos.index'), { buscar: valor }, { preserveState: true, replace: true });
        else if (!valor) router.get(route('productos.index'), {}, { preserveState: true, replace: true });
    }, 400);
});
</script>

<template>
    <Head title="Productos" />
    <PublicLayout>
        <div class="py-10 sm:py-14">
            <nav v-if="breadcrumb?.length" class="flex items-center gap-2 text-[11px] uppercase tracking-[0.2em] text-text-muted mb-8">
                <Link :href="route('home')" class="hover:text-accent transition">Inicio</Link>
                <template v-for="(c, i) in breadcrumb" :key="i">
                    <span>/</span>
                    <Link v-if="c.url" :href="c.url" class="hover:text-accent transition">{{ c.label }}</Link>
                    <span v-else class="text-text">{{ c.label }}</span>
                </template>
            </nav>

            <!-- En el celular la barra de arriba no tiene buscador (el ancho se
                 lo lleva la marca), así que este es el único: no puede faltar. -->
            <div class="mb-10 max-w-md">
                <div class="relative">
                    <input v-model="buscar" type="text" placeholder="Buscar"
                        class="w-full pl-9 pr-4 py-3 bg-transparent border-0 border-b border-border rounded-none text-[13px] focus:ring-0 focus:border-accent placeholder:text-text-muted transition-colors" />
                    <svg class="w-4 h-4 text-text-muted absolute left-1.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>

            <template v-if="modo === 'categorias'">
                <h1 class="text-[13px] uppercase tracking-[0.25em] text-text mb-8">Categorías</h1>
                <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
                    <Mosaico v-for="cat in items" :key="cat.id"
                        :href="route('productos.index', { vista: 'categorias', categoria: cat.id })"
                        :nombre="cat.nombre" :imagen="cat.imagen_url" :cantidad="cat.productos_count" />
                </div>
            </template>

            <template v-else-if="modo === 'marcas_en_categoria'">
                <h1 class="text-[13px] uppercase tracking-[0.25em] text-text mb-1">{{ categoriaActual?.nombre }}</h1>
                <p class="text-[11px] uppercase tracking-[0.2em] text-text-muted mb-8">{{ items.length }} marcas</p>
                <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
                    <Mosaico v-for="m in items" :key="m.id"
                        :href="route('productos.index', { vista: 'categorias', categoria: categoriaActual.id, marca: m.id })"
                        :nombre="m.nombre" :imagen="m.logo_url" :cantidad="m.productos_count" ajuste="contain" />
                </div>
            </template>

            <template v-else-if="modo === 'marcas'">
                <h1 class="text-[13px] uppercase tracking-[0.25em] text-text mb-8">Marcas</h1>
                <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
                    <Mosaico v-for="m in items" :key="m.id"
                        :href="route('productos.index', { vista: 'marcas', marca: m.id })"
                        :nombre="m.nombre" :imagen="m.logo_url" :cantidad="m.productos_count" ajuste="contain" />
                </div>
            </template>

            <template v-else-if="modo === 'categorias_en_marca'">
                <div class="flex items-center gap-4 mb-1">
                    <div v-if="marcaActual?.logo_url" class="w-16 h-16 bg-surface-1 border border-border flex items-center justify-center p-2 shrink-0">
                        <img :src="marcaActual.logo_url" :alt="marcaActual.nombre" class="max-w-full max-h-full object-contain" />
                    </div>
                    <h1 class="text-[13px] uppercase tracking-[0.25em] text-text">{{ marcaActual?.nombre }}</h1>
                </div>
                <p class="text-[11px] uppercase tracking-[0.2em] text-text-muted mb-8 mt-2">{{ items.length }} categorías</p>
                <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
                    <Mosaico v-for="cat in items" :key="cat.id"
                        :href="route('productos.index', { vista: 'marcas', marca: marcaActual.id, categoria: cat.id })"
                        :nombre="cat.nombre" :imagen="cat.imagen_url" :cantidad="cat.productos_count" />
                </div>

                <!-- Los productos de la marca, acá mismo: las categorías de
                     arriba siguen sirviendo para filtrar, pero ya no hace falta
                     entrar a una para ver qué hay. -->
                <div v-if="productos?.data?.length" class="mt-16">
                    <h2 class="text-[13px] uppercase tracking-[0.25em] text-text mb-8">Todo — {{ productos.total }}</h2>
                    <GrillaProductos :productos="productos.data" @image-click="fotoAmpliada = $event" />
                    <Pagination :links="productos.links" />
                </div>
            </template>

            <template v-else-if="modo === 'busqueda' && productosPorCategoria?.length">
                <p class="text-[11px] uppercase tracking-[0.2em] text-text-muted mb-10">{{ totalResultados }} resultados para "{{ filtros.buscar }}"</p>
                <div v-for="g in productosPorCategoria" :key="g.nombre" class="mb-16">
                    <h2 class="text-[13px] uppercase tracking-[0.25em] text-text mb-8">{{ g.nombre }} — {{ g.productos.length }}</h2>
                    <GrillaProductos :productos="g.productos" @image-click="fotoAmpliada = $event" />
                </div>
            </template>

            <template v-else-if="modo === 'productos' && productos">
                <GrillaProductos v-if="productos.data.length" :productos="productos.data" @image-click="fotoAmpliada = $event" />
                <p v-else class="text-center py-24 text-[12px] uppercase tracking-[0.2em] text-text-muted">Sin resultados.</p>
                <Pagination :links="productos.links" />
            </template>

            <p v-else class="text-center py-24 text-[12px] uppercase tracking-[0.2em] text-text-muted">Sin resultados.</p>
        </div>

        <ImageModal :src="fotoAmpliada" @close="fotoAmpliada = null" />
    </PublicLayout>
</template>
