<script setup>
/**
 * Portada de la plantilla Vidriera. En vez de las filas que se deslizan al
 * costado (que sirven cuando hay cientos de productos y poco lugar), acá cada
 * categoría muestra una grilla corta con un "ver todo": menos productos a la
 * vista, cada uno más grande.
 */
import PublicLayout from '@/Layouts/PublicLayout.vue';
import GrillaProductos from '@/Components/GrillaProductos.vue';
import ImageModal from '@/Components/ImageModal.vue';
import ComboDetailModal from '@/Components/ComboDetailModal.vue';
import BannerSlider from '@/Components/BannerSlider.vue';
import WelcomeGuideModal from '@/Components/WelcomeGuideModal.vue';
import { Link, Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ banners: Array, pasillos: Array, combos: Array, masVendidos: Array, mostrarGuiaBienvenida: Boolean });

const fotoAmpliada = ref(null);
const comboElegido = ref(null);
const mostrarGuia = ref(props.mostrarGuiaBienvenida);

function agregarCombo(id) {
    router.post(route('cart.add-combo'), { combo_id: id }, { preserveScroll: true });
}

// Cuatro por categoría: llena una fila justa en la computadora y dos en el
// celular. El resto está a un toque de "ver todo".
function primeros(productos) {
    return (productos || []).slice(0, 4);
}
</script>

<template>
    <Head title="Inicio">
        <meta name="description" :content="$page.props.negocio.descripcion || $page.props.negocio.nombre" />
        <meta property="og:title" :content="$page.props.negocio.eslogan ? `${$page.props.negocio.nombre} — ${$page.props.negocio.eslogan}` : $page.props.negocio.nombre" />
        <meta v-if="$page.props.negocio.descripcion" property="og:description" :content="$page.props.negocio.descripcion" />
        <meta property="og:type" content="website" />
    </Head>

    <PublicLayout>
        <!-- El banner va de borde a borde: es la vidriera. -->
        <template #encabezado>
            <BannerSlider :banners="banners" />
        </template>

        <WelcomeGuideModal v-if="mostrarGuia" @close="mostrarGuia = false" />

        <div class="py-12 sm:py-16">
            <section v-if="masVendidos.length" class="mb-16 sm:mb-20">
                <div class="flex items-end justify-between mb-7">
                    <h2 class="text-[13px] sm:text-[15px] uppercase tracking-[0.25em] text-text">Lo más elegido</h2>
                </div>
                <GrillaProductos :productos="primeros(masVendidos)" @image-click="fotoAmpliada = $event" />
            </section>

            <section v-for="pasillo in pasillos" :key="pasillo.id" class="mb-16 sm:mb-20">
                <div class="flex items-end justify-between mb-7 gap-4">
                    <h2 class="text-[13px] sm:text-[15px] uppercase tracking-[0.25em] text-text">{{ pasillo.nombre }}</h2>
                    <Link :href="route('productos.index', { vista: 'categorias', categoria: pasillo.id })"
                        class="shrink-0 text-[11px] uppercase tracking-[0.2em] text-text-muted hover:text-accent border-b border-transparent hover:border-accent pb-0.5 transition-colors">
                        Ver los {{ pasillo.total }}
                    </Link>
                </div>
                <GrillaProductos :productos="primeros(pasillo.productos)" @image-click="fotoAmpliada = $event" />
            </section>

            <section v-if="$page.props.secciones.combos && combos.length" class="mb-8">
                <div class="flex items-end justify-between mb-7 gap-4">
                    <h2 class="text-[13px] sm:text-[15px] uppercase tracking-[0.25em] text-text">Combos</h2>
                    <Link :href="route('combos.index')" class="shrink-0 text-[11px] uppercase tracking-[0.2em] text-text-muted hover:text-accent border-b border-transparent hover:border-accent pb-0.5 transition-colors">Ver todos</Link>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="c in combos" :key="c.id" @click="comboElegido = c" class="group cursor-pointer">
                        <div class="relative aspect-[4/3] bg-surface-2 overflow-hidden">
                            <img v-if="c.imagen_url" :src="c.imagen_url" loading="lazy" class="w-full h-full object-cover group-hover:scale-[1.04] transition-transform duration-700" />
                            <span class="absolute top-3 left-3 text-[10px] uppercase tracking-[0.2em] text-white bg-purple-500 px-2.5 py-1">Combo</span>
                        </div>
                        <div class="pt-3 flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="text-[14px] text-text truncate">{{ c.nombre }}</h3>
                                <div class="flex items-baseline gap-2 mt-1">
                                    <span class="text-[16px] price-display text-text">${{ Math.round(c.precio_final).toLocaleString('es-AR') }}</span>
                                    <del v-if="c.descuento_porcentaje && c.precio_sin_descuento !== c.precio_final" class="text-[11px] text-text-muted">${{ Math.round(c.precio_sin_descuento).toLocaleString('es-AR') }}</del>
                                </div>
                            </div>
                            <button @click.stop="agregarCombo(c.id)" class="shrink-0 text-[11px] uppercase tracking-[0.18em] text-white bg-accent hover:bg-accent-bright px-4 py-2.5 transition-colors">Agregar</button>
                        </div>
                    </div>
                </div>
            </section>

            <p v-if="!masVendidos.length && !pasillos.length" class="text-center py-24 text-[12px] uppercase tracking-[0.2em] text-text-muted">
                Todavía no hay productos cargados.
            </p>
        </div>

        <ImageModal :src="fotoAmpliada" @close="fotoAmpliada = null" />
        <ComboDetailModal :combo="comboElegido" @close="comboElegido = null" />
    </PublicLayout>
</template>
