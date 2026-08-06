<script setup>
/**
 * Plantilla Mostrador: utilitaria. El buscador se lleva el lugar de honor
 * porque el cliente que usa esta tienda ya sabe qué quiere y viene a
 * encontrarlo rápido, no a mirar. Todo compacto: en una pantalla tiene que
 * entrar la mayor cantidad de renglones posible.
 */
import { Link, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import MenuEditor from '@/Components/MenuEditor.vue';
import AvisoDeError from '@/Components/AvisoDeError.vue';

const page = usePage();
const cajonAbierto = ref(false);
const editandoMenu = ref(false);
const editandoMenuMovil = ref(false);

const cartCount = computed(() => page.props.cartCount || 0);
const negocio = computed(() => page.props.negocio);
const secciones = computed(() => page.props.secciones);
const menu = computed(() => page.props.menu || []);
const filtros = computed(() => page.props.filtros || []);
const puedeEditarMenu = computed(() => !!page.props.menuEditor);

function esActivo(item) {
    const actual = window.location.pathname + window.location.search;
    const destino = item.url.replace(window.location.origin, '');

    return destino === '/' ? actual === '/' : actual.startsWith(destino);
}

const busqueda = ref('');
const resultados = ref([]);
const buscadorAbierto = ref(false);
let esperando = null;

function alEscribir(e) {
    const valor = e.target.value;
    busqueda.value = valor;
    clearTimeout(esperando);
    if (valor.length < 2) { resultados.value = []; buscadorAbierto.value = false; return; }
    esperando = setTimeout(async () => {
        try {
            const res = await fetch(`/api/buscar?q=${encodeURIComponent(valor)}`);
            resultados.value = await res.json();
            buscadorAbierto.value = resultados.value.length > 0;
        } catch { resultados.value = []; }
    }, 300);
}

function irAlProducto(slug) {
    buscadorAbierto.value = false;
    busqueda.value = '';
    resultados.value = [];
    router.get(route('productos.show', slug));
}

function buscar() {
    if (busqueda.value.length >= 2) {
        buscadorAbierto.value = false;
        router.get(route('productos.index'), { buscar: busqueda.value });
    }
}
</script>

<template>
    <div class="min-h-screen bg-surface text-text font-sans">
        <header class="bg-surface-1 border-b border-border sticky top-0 z-50 shadow-sm shadow-black/5">
            <div class="max-w-6xl mx-auto px-3 sm:px-5">
                <div class="flex items-center gap-2 sm:gap-4 min-h-[var(--barra-alto)] py-2">
                    <button @click="cajonAbierto = true" aria-label="Menú" class="lg:hidden p-2 -ml-1.5 text-text-secondary hover:text-text transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    <Link :href="route('home')" class="flex items-center gap-2 shrink-0">
                        <img v-if="negocio.logo" :src="negocio.logo" :alt="negocio.nombre" class="h-[var(--logo-alto)] object-contain" />
                        <span v-else class="text-[15px] font-bold uppercase tracking-tight truncate max-w-[140px] sm:max-w-none">{{ negocio.nombre }}</span>
                    </Link>

                    <!-- El buscador es el centro de esta plantilla: acá se viene
                         a encontrar un producto, no a pasear. -->
                    <div class="flex-1 relative" @keydown.escape="buscadorAbierto = false">
                        <input type="text" placeholder="Buscar producto o marca..."
                            :value="busqueda"
                            @input="alEscribir"
                            @keyup.enter="buscar"
                            @focus="resultados.length && (buscadorAbierto = true)"
                            class="w-full h-10 pl-9 pr-3 text-[13px] bg-surface-2 border border-border rounded-lg focus:border-accent focus:ring-2 focus:ring-accent/15 placeholder:text-text-muted transition" />
                        <svg class="w-[17px] h-[17px] text-text-muted absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <div v-if="buscadorAbierto" class="absolute top-full left-0 right-0 mt-1 bg-surface-1 border border-border rounded-lg shadow-xl shadow-black/10 overflow-hidden z-[100]">
                            <button v-for="r in resultados" :key="r.id" @mousedown.prevent="irAlProducto(r.slug)"
                                class="w-full flex items-center gap-3 px-3 py-2 hover:bg-surface-2 transition text-left">
                                <div class="w-9 h-9 rounded bg-surface-2 overflow-hidden shrink-0">
                                    <img v-if="r.imagen" :src="r.imagen" class="w-full h-full object-cover" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[13px] text-text truncate font-medium">{{ r.nombre }}</p>
                                    <p class="text-[11px] text-text-muted">{{ r.marca }}</p>
                                </div>
                            </button>
                            <button @mousedown.prevent="buscar" class="w-full px-3 py-2 text-[12px] font-semibold text-accent hover:bg-surface-2 transition text-left border-t border-border">
                                Ver todos los resultados →
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center gap-1 shrink-0">
                        <Link v-if="page.props.auth.user && page.props.auth.esStaff && secciones.listaPrecios" :href="route('lista-precios')"
                            class="hidden xl:block text-[12.5px] font-medium text-text-secondary hover:text-text px-2.5 py-2 rounded-lg hover:bg-surface-2 transition">Precios</Link>
                        <Link v-if="page.props.auth.user" :href="route('mis-pedidos')"
                            class="hidden lg:block text-[12.5px] font-medium text-text-secondary hover:text-text px-2.5 py-2 rounded-lg hover:bg-surface-2 transition">Pedidos</Link>
                        <Link v-if="page.props.auth.user" :href="route('profile.edit')" aria-label="Mi cuenta" class="hidden sm:flex p-2 text-text-secondary hover:text-text rounded-lg hover:bg-surface-2 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                        </Link>
                        <Link v-if="!page.props.auth.user" :href="route('login')" class="text-[12.5px] font-medium text-text-secondary hover:text-text px-2.5 py-2 rounded-lg hover:bg-surface-2 transition">Ingresar</Link>

                        <Link :href="route('cart.index')" aria-label="Carrito" class="relative flex items-center gap-1.5 px-2.5 h-10 rounded-lg transition"
                            :class="cartCount > 0 ? 'bg-accent text-white hover:bg-accent-bright' : 'text-text-secondary hover:bg-surface-2'">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
                            </svg>
                            <span v-if="cartCount > 0" class="text-[13px] font-bold leading-none">{{ cartCount }}</span>
                        </Link>
                    </div>
                </div>

                <!-- Las secciones, en una tira que se desliza con el dedo. -->
                <nav class="flex items-center gap-[var(--menu-espacio)] h-10 overflow-x-auto scrollbar-oculta">
                    <Link v-for="item in menu" :key="item.id" :href="item.url"
                        class="shrink-0 flex items-center gap-1.5 px-3 h-7 rounded-full text-[12.5px] font-medium whitespace-nowrap transition-colors"
                        :class="esActivo(item) ? 'bg-accent text-white' : 'bg-surface-2 text-text-secondary hover:bg-surface-3 hover:text-text'">
                        <span v-if="item.emoji" class="text-[13px] leading-none">{{ item.emoji }}</span>
                        {{ item.titulo }}
                    </Link>


                    <!-- Los filtros por etiqueta, a continuación de las secciones. -->
                    <Link v-for="f in filtros" :key="'f' + f.id" :href="route('productos.index', { etiqueta: f.id })"
                        class="shrink-0 flex items-center gap-1.5 px-3 py-1.5 text-[11px] whitespace-nowrap rounded-full border border-border text-text-secondary hover:text-text hover:border-border-hover transition-colors">
                        <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="f.color ? '' : 'bg-accent'" :style="f.color ? { backgroundColor: f.color } : {}"></span>
                        {{ f.nombre }}
                    </Link>
                    <button v-if="puedeEditarMenu" @click="editandoMenu = !editandoMenu"
                        class="hidden lg:flex shrink-0 items-center gap-1.5 px-3 h-7 rounded-full text-[12px] font-medium text-text-muted border border-dashed border-border hover:border-accent hover:text-accent transition">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                        Editar menú
                    </button>
                </nav>
            </div>

            <Transition name="desplegar">
                <div v-if="editandoMenu" class="hidden lg:block border-t border-border bg-surface-1">
                    <div class="max-w-sm mx-auto px-4 py-4">
                        <MenuEditor v-model="editandoMenu" />
                    </div>
                </div>
            </Transition>
        </header>

        <div v-if="$slots.encabezado" class="max-w-6xl mx-auto px-3 sm:px-5 pt-3">
            <slot name="encabezado" />
        </div>

        <Teleport to="body">
            <Transition name="fundido"><div v-if="cajonAbierto" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm lg:hidden" @click="cajonAbierto = false"></div></Transition>
            <Transition name="desde-izquierda">
                <aside v-if="cajonAbierto" class="fixed left-0 top-0 bottom-0 w-[280px] z-[55] bg-surface-1 border-r border-border p-4 overflow-y-auto lg:hidden">
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-[13px] font-semibold text-text">Mi cuenta</span>
                        <button @click="cajonAbierto = false" aria-label="Cerrar" class="p-1.5 text-text-muted hover:text-text rounded-lg hover:bg-surface-2 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="space-y-0.5">
                        <template v-if="!page.props.auth.user">
                            <Link :href="route('login')" class="block px-3 py-2.5 text-[13px] text-text-secondary hover:text-text hover:bg-surface-2 rounded-lg transition" @click="cajonAbierto = false">Ingresar</Link>
                            <Link :href="route('register')" class="block px-3 py-2.5 text-[13px] text-accent hover:bg-accent/10 rounded-lg transition" @click="cajonAbierto = false">Crear cuenta</Link>
                        </template>
                        <template v-else>
                            <Link :href="route('mis-pedidos')" class="block px-3 py-2.5 text-[13px] text-text-secondary hover:text-text hover:bg-surface-2 rounded-lg transition" @click="cajonAbierto = false">Mis pedidos</Link>
                            <Link :href="route('profile.edit')" class="block px-3 py-2.5 text-[13px] text-text-secondary hover:text-text hover:bg-surface-2 rounded-lg transition" @click="cajonAbierto = false">Mi cuenta</Link>
                            <Link v-if="page.props.auth.esStaff && secciones.listaPrecios" :href="route('lista-precios')" class="block px-3 py-2.5 text-[13px] text-text-secondary hover:text-text hover:bg-surface-2 rounded-lg transition" @click="cajonAbierto = false">Lista de precios</Link>
                            <Link :href="route('logout')" method="post" as="button" class="block w-full text-left px-3 py-2.5 text-[13px] text-text-muted hover:text-red-400 rounded-lg transition">Salir</Link>
                        </template>
                    </div>

                    <div v-if="puedeEditarMenu" class="mt-5 pt-4 border-t border-border">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-text-muted mb-2.5">Menú de la tienda</p>
                        <MenuEditor v-model="editandoMenuMovil" />
                    </div>
                </aside>
            </Transition>
        </Teleport>

        <main class="max-w-6xl mx-auto px-3 sm:px-5"><slot /></main>

        <footer class="bg-surface-1 border-t border-border mt-12">
            <div class="max-w-6xl mx-auto px-3 sm:px-5 py-8">
                <div class="flex flex-wrap gap-x-10 gap-y-6 justify-between">
                    <div class="max-w-sm">
                        <p class="text-[14px] font-bold uppercase tracking-tight">{{ negocio.nombre }}</p>
                        <p v-if="negocio.descripcion" class="text-[13px] text-text-muted mt-1.5 leading-relaxed">{{ negocio.descripcion }}</p>
                        <p v-if="negocio.direccion" class="text-[13px] text-text-muted mt-2">{{ negocio.direccion }}</p>
                        <p v-if="negocio.telefono" class="text-[13px] text-text-muted">{{ negocio.telefono }}</p>
                        <div v-if="negocio.mediosPago.length" class="flex flex-wrap gap-1.5 mt-3">
                            <span v-for="m in negocio.mediosPago" :key="m" class="text-[11px] text-text-secondary bg-surface-2 px-2 py-0.5 rounded">{{ m }}</span>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-[12px] font-semibold text-text mb-3">Tienda</h3>
                        <div class="space-y-2">
                            <Link :href="route('productos.index')" class="block text-[13px] text-text-muted hover:text-accent transition">Catálogo</Link>
                            <Link :href="route('productos.index', { vista: 'categorias' })" class="block text-[13px] text-text-muted hover:text-accent transition">Categorías</Link>
                            <Link :href="route('productos.index', { vista: 'marcas' })" class="block text-[13px] text-text-muted hover:text-accent transition">Marcas</Link>
                            <Link v-for="p in page.props.paginas" :key="p.slug" :href="route('paginas.show', p.slug)" class="block text-[13px] text-text-muted hover:text-accent transition">{{ p.titulo }}</Link>
                        </div>
                    </div>
                </div>
                <div class="mt-8 pt-5 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-2 text-[11px] text-text-muted">
                    <span>© {{ new Date().getFullYear() }} {{ negocio.nombre }}</span>
                    <span v-if="negocio.ciudad">{{ negocio.ciudad }}</span>
                </div>
            </div>
        </footer>

        <div class="fixed bottom-4 right-4 z-40 flex flex-col gap-2.5">
            <a v-if="negocio.whatsapp" :href="`https://wa.me/${negocio.whatsapp}`" target="_blank" title="WhatsApp"
                class="w-12 h-12 rounded-full bg-[#25D366] flex items-center justify-center shadow-lg shadow-green-500/20 hover:scale-110 transition-transform">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            </a>
        </div>

        <AvisoDeError />
    </div>
</template>

<style scoped>
.scrollbar-oculta::-webkit-scrollbar { display: none; }
.scrollbar-oculta { -ms-overflow-style: none; scrollbar-width: none; }
.fundido-enter-active, .fundido-leave-active { transition: opacity .25s ease; }
.fundido-enter-from, .fundido-leave-to { opacity: 0; }
.desde-izquierda-enter-active, .desde-izquierda-leave-active { transition: transform .3s cubic-bezier(.16,1,.3,1); }
.desde-izquierda-enter-from, .desde-izquierda-leave-to { transform: translateX(-100%); }
.desplegar-enter-active, .desplegar-leave-active { transition: opacity .2s ease; }
.desplegar-enter-from, .desplegar-leave-to { opacity: 0; }
</style>
