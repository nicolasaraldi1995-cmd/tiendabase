<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import MenuEditor from '@/Components/MenuEditor.vue';
import AvisoDeError from '@/Components/AvisoDeError.vue';

const page = usePage();
const sidebarOpen = ref(false);
// Mientras el dueño edita el menú, el editor reemplaza a la lista normal.
// Uno por vista (computadora y celular): nunca están las dos a la vez, pero
// así ninguna arrastra el estado de la otra.
const editandoMenu = ref(false);
const editandoMenuMovil = ref(false);

const cartCount = computed(() => page.props.cartCount || 0);
// Identidad del negocio y secciones activas, cargadas desde el panel (Configuración).
const negocio = computed(() => page.props.negocio);
const secciones = computed(() => page.props.secciones);
const menu = computed(() => page.props.menu || []);
// Las etiquetas que el negocio marcó para que salgan como filtro en el menú.
const filtros = computed(() => page.props.filtros || []);
// La columnita del emoji está para que los títulos queden alineados cuando unos
// ítems tienen y otros no. Si el negocio no usa ninguno sobra: sin esto quedaba
// una fila de puntos al costado de cada sección.
const hayEmojis = computed(() => menu.value.some((i) => i.emoji));

// El ítem del menú que corresponde a la página actual, comparando la dirección
// completa: así funciona igual para las pantallas del motor y para los ítems
// que el negocio arma a mano (una categoría suya, una página, un link).
function esActivo(item) {
    const actual = window.location.pathname + window.location.search;
    const destino = item.url.replace(window.location.origin, '');

    return destino === '/' ? actual === '/' : actual.startsWith(destino);
}

const searchQuery = ref('');
const searchResults = ref([]);
const searchOpen = ref(false);
let searchTimeout = null;

function onSearchInput(e) {
    const val = e.target.value;
    searchQuery.value = val;
    clearTimeout(searchTimeout);
    if (val.length < 2) { searchResults.value = []; searchOpen.value = false; return; }
    searchTimeout = setTimeout(async () => {
        try {
            const res = await fetch(`/api/buscar?q=${encodeURIComponent(val)}`);
            searchResults.value = await res.json();
            searchOpen.value = searchResults.value.length > 0;
        } catch { searchResults.value = []; }
    }, 300);
}

function goToProduct(slug) {
    searchOpen.value = false;
    searchQuery.value = '';
    searchResults.value = [];
    router.get(route('productos.show', slug));
}

function searchSubmit() {
    if (searchQuery.value.length >= 2) {
        searchOpen.value = false;
        router.get(route('productos.index'), { buscar: searchQuery.value });
    }
}

</script>

<template>
    <div class="min-h-screen bg-surface text-text font-sans">
        <!-- Topbar -->
        <nav class="bg-surface-1/80 backdrop-blur-2xl border-b border-border sticky top-0 z-50">
            <!-- Sin tope de ancho, igual que el resto: así el logo queda a la
                 misma altura que el panel de la izquierda. -->
            <div class="px-6 flex items-center justify-between min-h-[var(--barra-alto)] py-2">
                <button @click="sidebarOpen = !sidebarOpen" aria-label="Menú" class="lg:hidden p-2 -ml-2 text-text-muted hover:text-text transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                <Link :href="route('home')" class="flex items-center gap-3 group shrink-0">
                    <img v-if="negocio.logo" :src="negocio.logo" :alt="negocio.nombre" class="h-[var(--logo-alto)] object-contain group-hover:opacity-90 transition" />
                    <div class="leading-none" :class="negocio.logo ? 'hidden sm:block' : 'block'">
                        <span class="text-[16px] font-bold text-text tracking-tight uppercase">{{ negocio.nombre }}</span>
                        <span v-if="negocio.eslogan" class="block text-[10px] text-text-muted tracking-widest uppercase">{{ negocio.eslogan }}</span>
                    </div>
                </Link>

                <div class="flex items-center flex-1 max-w-2xl mx-4 sm:mx-8 relative" @keydown.escape="searchOpen = false">
                    <input type="text" placeholder="Buscar productos o marcas..."
                        :value="searchQuery"
                        @input="onSearchInput"
                        @keyup.enter="searchSubmit"
                        @focus="searchResults.length && (searchOpen = true)"
                        class="w-full pl-10 pr-3 py-2.5 text-sm bg-surface-1 border border-border rounded-xl shadow-sm focus:border-accent focus:ring-2 focus:ring-accent/15 placeholder:text-text-muted transition" />
                    <svg class="w-[18px] h-[18px] text-text-muted absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <!-- Autocomplete dropdown -->
                    <div v-if="searchOpen" class="absolute top-full left-0 right-0 mt-1 bg-surface-1 border border-border rounded-xl shadow-xl shadow-black/10 overflow-hidden z-[100]">
                        <button v-for="r in searchResults" :key="r.id" @mousedown.prevent="goToProduct(r.slug)"
                            class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-surface-2 transition text-left">
                            <div class="w-10 h-10 rounded-lg bg-surface-2 overflow-hidden shrink-0">
                                <img v-if="r.imagen" :src="r.imagen" class="w-full h-full object-cover" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[13px] text-text truncate font-medium">{{ r.nombre }}</p>
                                <p class="text-[11px] text-text-muted">{{ r.marca }}</p>
                            </div>
                        </button>
                        <button @mousedown.prevent="searchSubmit" class="w-full px-4 py-2.5 text-[12px] text-accent font-medium hover:bg-surface-2 transition text-left border-t border-border">
                            Ver todos los resultados →
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-1.5">
                    <template v-if="page.props.auth.user">
                        <!-- Herramienta interna: solo la ve el personal -->
                        <Link v-if="page.props.auth.esStaff && secciones.listaPrecios" :href="route('lista-precios')" class="hidden sm:flex items-center gap-2 text-[13px] text-text-secondary hover:text-text px-3 py-2 rounded-xl hover:bg-surface-2 transition-all">
                            Precios
                        </Link>
                        <Link :href="route('mis-pedidos')" class="hidden sm:flex items-center gap-2 text-[13px] text-text-secondary hover:text-text px-3 py-2 rounded-xl hover:bg-surface-2 transition-all">
                            Pedidos
                        </Link>
                        <Link :href="route('profile.edit')" class="hidden sm:flex items-center gap-2 text-[13px] text-text-secondary hover:text-text px-3 py-2 rounded-xl hover:bg-surface-2 transition-all">
                            <span class="w-6 h-6 rounded-full bg-accent/20 flex items-center justify-center text-[10px] font-bold text-accent">
                                {{ page.props.auth.user.name.charAt(0).toUpperCase() }}
                            </span>
                            {{ page.props.auth.user.name }}
                        </Link>
                        <Link :href="route('logout')" method="post" as="button" class="text-[12px] text-text-muted hover:text-red-400 px-2 py-2 rounded-xl hover:bg-surface-2 transition-all">
                            Salir
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="hidden sm:block text-[13px] text-text-secondary hover:text-text px-3 py-2 rounded-xl hover:bg-surface-2 transition-all">
                            Ingresar
                        </Link>
                        <Link :href="route('register')" class="hidden sm:block text-[13px] font-medium text-white bg-accent hover:bg-accent-bright px-4 py-2 rounded-xl transition-all">
                            Crear cuenta
                        </Link>
                    </template>

                    <Link :href="route('cart.index')" aria-label="Carrito" class="relative flex items-center gap-1.5 pl-2.5 pr-3 py-2.5 rounded-xl transition-all ml-1"
                        :class="cartCount > 0 ? 'bg-accent text-white hover:bg-accent-bright shadow-sm shadow-accent/25' : 'text-text-secondary hover:text-text hover:bg-surface-2'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
                        </svg>
                        <span v-if="cartCount > 0" class="text-[13px] font-bold leading-none">{{ cartCount }}</span>
                    </Link>
                </div>
            </div>
        </nav>

        <!-- Espacio de ancho completo, arriba del panel de navegación: el
             banner va acá para ocupar toda la pantalla y no sólo la columna de
             contenido. Debajo la página sigue igual, con el menú a la izquierda. -->
        <div v-if="$slots.encabezado" class="px-3 sm:px-4 pt-3 sm:pt-4">
            <slot name="encabezado" />
        </div>

        <!-- Ancho completo: el panel va pegado al borde izquierdo y el contenido
             se queda con todo lo que sobra. -->
        <div class="flex">
            <!-- Sidebar -->
            <aside class="hidden lg:block w-[var(--menu-ancho)] shrink-0 sticky top-[var(--barra-alto)] h-[calc(100vh-var(--barra-alto))] overflow-y-auto border-r border-border px-4 py-6">
                <div class="space-y-[var(--menu-espacio)]">
                    <!-- Solo lo ve el dueño: editar el menú sin salir de la tienda. -->
                    <MenuEditor v-model="editandoMenu" />
                    <!-- El menú lo arma el negocio desde el panel o desde acá. -->
                    <Link v-for="item in (editandoMenu ? [] : menu)" :key="item.id" :href="item.url"
                        class="flex items-center gap-3 pl-[10px] pr-3 py-2.5 rounded-xl text-[13px] font-medium transition-all border-l-[3px]"
                        :class="esActivo(item) ? 'bg-accent/10 text-accent border-accent' : 'border-transparent text-text-secondary hover:bg-surface-2 hover:text-text'">
                        <span v-if="hayEmojis" class="w-[18px] text-[15px] leading-none shrink-0 text-center">{{ item.emoji }}</span>
                        {{ item.titulo }}
                    </Link>

                    <!-- Los filtros salen de las etiquetas que el negocio marcó
                         para el menú (Catálogo → Etiquetas). Antes eran tres
                         links fijos de alimentos escritos acá. -->
                    <template v-if="filtros.length && !editandoMenu">
                        <div class="h-px bg-border my-4"></div>
                        <p class="text-[10px] font-semibold text-text-muted uppercase tracking-[0.15em] px-3 pb-1">Filtros</p>
                        <Link v-for="f in filtros" :key="f.id" :href="route('productos.index', { etiqueta: f.id })"
                            class="flex items-center gap-3 px-3 py-2 rounded-xl text-[13px] text-text-secondary hover:bg-surface-2 hover:text-text transition-all">
                            <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="f.color ? '' : 'bg-accent'" :style="f.color ? { backgroundColor: f.color } : {}"></span>
                            {{ f.nombre }}
                        </Link>
                    </template>
                </div>
            </aside>

            <!-- Mobile sidebar -->
            <Teleport to="body">
                <Transition name="fade"><div v-if="sidebarOpen" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm" @click="sidebarOpen = false"></div></Transition>
                <Transition name="slide-left">
                    <aside v-if="sidebarOpen" class="fixed left-0 top-0 bottom-0 w-[280px] z-[55] bg-surface-1 border-r border-border p-5 overflow-y-auto">
                        <div class="flex justify-between items-center mb-5">
                            <span class="font-semibold text-text">Menú</span>
                            <button @click="sidebarOpen = false" class="p-1.5 text-text-muted hover:text-text hover:bg-surface-2 rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="space-y-[var(--menu-espacio)]">
                            <!-- El mismo editor que en la computadora: acá es donde
                                 mejor se siente, porque el arrastre es táctil. -->
                            <MenuEditor v-model="editandoMenuMovil" />
                            <Link v-for="item in menu" v-show="!editandoMenuMovil" :key="item.id" :href="item.url"
                                class="flex items-center gap-3 px-3 py-2.5 text-[13px] text-text-secondary hover:text-text hover:bg-surface-2 rounded-xl transition" @click="sidebarOpen=false">
                                <span v-if="hayEmojis" class="w-[18px] text-[15px] leading-none shrink-0 text-center">{{ item.emoji }}</span>
                                {{ item.titulo }}
                            </Link>
                            <div class="h-px bg-border my-3"></div>
                            <template v-if="!page.props.auth.user">
                                <Link :href="route('login')" class="block px-3 py-2.5 text-[13px] text-text-secondary hover:text-text hover:bg-surface-2 rounded-xl transition" @click="sidebarOpen=false">Ingresar</Link>
                                <Link :href="route('register')" class="block px-3 py-2.5 text-[13px] text-accent hover:bg-accent/10 rounded-xl transition" @click="sidebarOpen=false">Crear cuenta</Link>
                            </template>
                            <template v-else>
                                <Link :href="route('mis-pedidos')" class="block px-3 py-2.5 text-[13px] text-text-secondary hover:text-text hover:bg-surface-2 rounded-xl transition" @click="sidebarOpen=false">Mis pedidos</Link>
                                <Link :href="route('profile.edit')" class="block px-3 py-2.5 text-[13px] text-text-secondary hover:text-text hover:bg-surface-2 rounded-xl transition" @click="sidebarOpen=false">Mi cuenta</Link>
                            </template>
                        </div>
                    </aside>
                </Transition>
            </Teleport>

            <main class="flex-1 min-w-0"><slot /></main>
        </div>

        <!-- Footer -->
        <footer class="bg-surface-1 border-t border-border mt-16">
            <div class="px-6 py-12">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-10">
                    <div class="md:col-span-2">
                        <img v-if="negocio.logo" :src="negocio.logo" :alt="negocio.nombre" class="h-[var(--logo-alto)] mb-4 opacity-60" />
                        <p v-else class="text-[15px] font-bold text-text tracking-tight uppercase mb-4">{{ negocio.nombre }}</p>
                        <p v-if="negocio.descripcion" class="text-sm text-text-muted leading-relaxed max-w-sm">{{ negocio.descripcion }}</p>
                        <div v-if="negocio.direccion" class="flex items-center gap-2 mt-3 text-sm text-text-muted">
                            <svg class="w-4 h-4 text-accent shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                            {{ negocio.direccion }}
                        </div>
                        <div v-if="negocio.telefono" class="flex items-center gap-2 mt-2 text-sm text-text-muted">
                            <svg class="w-4 h-4 text-accent shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h1.5a1.5 1.5 0 001.5-1.5v-2.379a1.5 1.5 0 00-1.5-1.5h-2.379a1.5 1.5 0 00-1.061.439l-1.13 1.13a12.005 12.005 0 01-6.55-6.55l1.13-1.13a1.5 1.5 0 00.439-1.061V6.75a1.5 1.5 0 00-1.5-1.5H3.75a1.5 1.5 0 00-1.5 1.5z"/></svg>
                            {{ negocio.telefono }}
                        </div>
                        <div v-if="negocio.mediosPago.length" class="flex flex-wrap items-center gap-1.5 mt-4">
                            <span class="text-[11px] text-text-muted mr-1">Medios de pago:</span>
                            <span v-for="m in negocio.mediosPago" :key="m" class="text-[11px] text-text-secondary bg-surface-2 px-2 py-0.5 rounded-md">{{ m }}</span>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-[13px] font-semibold text-text mb-4">Navegación</h3>
                        <div class="space-y-2.5">
                            <Link :href="route('productos.index')" class="block text-sm text-text-muted hover:text-accent transition">Catálogo</Link>
                            <Link :href="route('productos.index', { vista: 'categorias' })" class="block text-sm text-text-muted hover:text-accent transition">Categorías</Link>
                            <Link :href="route('productos.index', { vista: 'marcas' })" class="block text-sm text-text-muted hover:text-accent transition">Marcas</Link>
                            <Link v-if="page.props.auth.esStaff && secciones.listaPrecios" :href="route('lista-precios')" class="block text-sm text-text-muted hover:text-accent transition">Lista de precios</Link>
                            <!-- Páginas que escribe el negocio desde el panel. -->
                            <Link v-for="p in page.props.paginas" :key="p.slug" :href="route('paginas.show', p.slug)" class="block text-sm text-text-muted hover:text-accent transition">{{ p.titulo }}</Link>
                        </div>
                    </div>
                    <div v-if="negocio.whatsapp">
                        <h3 class="text-[13px] font-semibold text-text mb-4">Atención al cliente</h3>
                        <div class="space-y-2.5 text-sm text-text-muted">
                            <p>Consultas y pedidos coordinados por WhatsApp.</p>
                            <a :href="`https://wa.me/${negocio.whatsapp}`" target="_blank" class="inline-flex items-center gap-1.5 text-accent hover:text-accent-bright transition font-medium">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347"/><path d="M12.05 21.785h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884M20.463 3.488A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413"/></svg>
                                Escribir por WhatsApp
                            </a>
                        </div>
                    </div>
                </div>
                <div class="mt-10 pt-6 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-2 text-[11px] text-text-muted">
                    <span>© {{ new Date().getFullYear() }} {{ negocio.nombre }}<template v-if="negocio.eslogan"> — {{ negocio.eslogan }}</template></span>
                    <span v-if="negocio.ciudad">{{ negocio.ciudad }}</span>
                </div>
            </div>
        </footer>

        <!-- Floating social buttons -->
        <div class="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-50 flex flex-col gap-2.5">
            <a v-if="negocio.instagram" :href="negocio.instagram" target="_blank"
                class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-gradient-to-br from-purple-600 via-pink-500 to-orange-400 flex items-center justify-center shadow-lg shadow-pink-500/20 hover:scale-110 transition-transform"
                title="Instagram">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
            </a>
            <a v-if="negocio.whatsapp" :href="`https://wa.me/${negocio.whatsapp}`" target="_blank"
                class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-[#25D366] flex items-center justify-center shadow-lg shadow-green-500/20 hover:scale-110 transition-transform"
                title="WhatsApp">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            </a>
        </div>

        <!-- Va acá, en el marco, para que cubra todas las pantallas de una: el
             carrito, la ficha del producto y la edición del pedido rechazaban
             cosas en silencio y el cliente no entendía por qué no pasaba nada. -->
        <AvisoDeError />
    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .25s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-left-enter-active, .slide-left-leave-active { transition: transform .3s cubic-bezier(.16,1,.3,1); }
.slide-left-enter-from, .slide-left-leave-to { transform: translateX(-100%); }
</style>
