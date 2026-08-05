<script setup>
/**
 * Portada de la plantilla Carta: solapas por categoría, y de a una por vez.
 *
 * Es la única pantalla que Carta necesita pisar. Una carta no se recorre
 * entera: el cliente elige "Empanadas" y quiere ver empanadas, no scrollear
 * catorce secciones. El resto de la tienda (carrito, checkout, pedidos) lo
 * hereda del motor y sale con los renglones de esta plantilla.
 */
import PublicLayout from '@/Layouts/PublicLayout.vue';
import GrillaProductos from '@/Components/GrillaProductos.vue';
import ImageModal from '@/Components/ImageModal.vue';
import ComboDetailModal from '@/Components/ComboDetailModal.vue';
import BannerSlider from '@/Components/BannerSlider.vue';
import WelcomeGuideModal from '@/Components/WelcomeGuideModal.vue';
import { Link, Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ banners: Array, pasillos: Array, combos: Array, masVendidos: Array, mostrarGuiaBienvenida: Boolean });

const fotoAmpliada = ref(null);
const comboElegido = ref(null);
const mostrarGuia = ref(props.mostrarGuiaBienvenida);

function agregarCombo(id) {
    router.post(route('cart.add-combo'), { combo_id: id }, { preserveScroll: true });
}

// "Los más pedidos" va primero cuando hay ventas: es la solapa que más se toca.
const solapas = computed(() => {
    const lista = props.masVendidos?.length
        ? [{ id: 'mas-pedidos', nombre: 'Los más pedidos', productos: props.masVendidos, total: props.masVendidos.length }]
        : [];

    return [...lista, ...(props.pasillos || [])];
});

const elegida = ref(0);

const actual = computed(() => solapas.value[elegida.value] || null);
</script>

<template>
    <Head title="Inicio">
        <meta name="description" :content="$page.props.negocio.descripcion || $page.props.negocio.nombre" />
        <meta property="og:title" :content="$page.props.negocio.eslogan ? `${$page.props.negocio.nombre} — ${$page.props.negocio.eslogan}` : $page.props.negocio.nombre" />
        <meta v-if="$page.props.negocio.descripcion" property="og:description" :content="$page.props.negocio.descripcion" />
        <meta property="og:type" content="website" />
    </Head>

    <PublicLayout>
        <template #encabezado>
            <BannerSlider :banners="banners" />
        </template>

        <WelcomeGuideModal v-if="mostrarGuia" @close="mostrarGuia = false" />

        <div class="py-8">
            <!-- Las solapas: pegadas arriba mientras se recorre la sección, y
                 deslizables con el dedo cuando el negocio tiene muchas. -->
            <div v-if="solapas.length > 1" class="sticky top-[108px] z-30 -mx-4 px-4 py-2.5 bg-surface/95 backdrop-blur-sm mb-2">
                <div class="flex gap-2 overflow-x-auto scrollbar-oculta">
                    <button v-for="(s, i) in solapas" :key="s.id" @click="elegida = i"
                        class="shrink-0 h-9 px-4 rounded-full text-[13px] whitespace-nowrap transition-colors"
                        :class="i === elegida ? 'bg-accent text-white font-semibold' : 'bg-surface-2 text-text-secondary hover:bg-surface-3'">
                        {{ s.nombre }}
                    </button>
                </div>
            </div>

            <section v-if="actual">
                <div class="flex items-baseline justify-between gap-4 mb-1">
                    <h2 class="text-[19px] font-semibold text-text">{{ actual.nombre }}</h2>
                    <Link v-if="actual.id !== 'mas-pedidos' && actual.total > actual.productos.length"
                        :href="route('productos.index', { vista: 'categorias', categoria: actual.id })"
                        class="shrink-0 text-[12.5px] font-medium text-accent hover:text-accent-bright transition">
                        Ver los {{ actual.total }} →
                    </Link>
                </div>

                <GrillaProductos :productos="actual.productos" @image-click="fotoAmpliada = $event" />
            </section>

            <section v-if="$page.props.secciones.combos && combos.length" class="mt-12 max-w-2xl">
                <h2 class="text-[19px] font-semibold text-text mb-1">Combos</h2>
                <div v-for="c in combos" :key="c.id" class="py-4 border-b border-border/70">
                    <div class="flex items-start gap-3.5">
                        <div v-if="c.imagen_url" class="w-14 h-14 rounded-lg overflow-hidden bg-surface-2 shrink-0 cursor-pointer" @click="comboElegido = c">
                            <img :src="c.imagen_url" loading="lazy" class="w-full h-full object-cover" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-baseline gap-2">
                                <h3 class="text-[15px] font-semibold text-text shrink-0 max-w-[70%] truncate cursor-pointer" @click="comboElegido = c">{{ c.nombre }}</h3>
                                <span class="flex-1 border-b border-dotted border-border-hover translate-y-[-3px]"></span>
                                <span class="shrink-0 text-[15px] price-display text-text">${{ Math.round(c.precio_final).toLocaleString('es-AR') }}</span>
                            </div>
                            <p v-if="c.descripcion" class="text-[12.5px] text-text-muted mt-1 line-clamp-2">{{ c.descripcion }}</p>
                            <div class="flex items-center justify-between gap-3 mt-2">
                                <del v-if="c.descuento_porcentaje && c.precio_sin_descuento !== c.precio_final" class="text-[11.5px] text-text-muted">${{ Math.round(c.precio_sin_descuento).toLocaleString('es-AR') }}</del>
                                <span v-else></span>
                                <button @click="agregarCombo(c.id)" class="shrink-0 h-9 px-4 rounded-full text-[12.5px] font-semibold text-white bg-accent hover:bg-accent-bright active:scale-[0.97] transition-all">Agregar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <p v-if="!solapas.length" class="text-center py-20 text-[13px] text-text-muted">
                Todavía no hay productos cargados.
            </p>
        </div>

        <ImageModal :src="fotoAmpliada" @close="fotoAmpliada = null" />
        <ComboDetailModal :combo="comboElegido" @close="comboElegido = null" />
    </PublicLayout>
</template>

<style scoped>
.scrollbar-oculta::-webkit-scrollbar { display: none; }
.scrollbar-oculta { -ms-overflow-style: none; scrollbar-width: none; }
</style>
