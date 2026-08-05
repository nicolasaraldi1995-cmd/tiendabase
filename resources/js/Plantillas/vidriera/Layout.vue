<script setup>
/**
 * Plantilla Vidriera: sin barra lateral. El menú va arriba, centrado, y la
 * pantalla queda entera para las fotos. Pensada para rubros donde lo que vende
 * es cómo se ve el producto (ropa, calzado, deco, cosmética).
 */
import { Link, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import MenuEditor from '@/Components/MenuEditor.vue';
import AvisoDeError from '@/Components/AvisoDeError.vue';

const page = usePage();
const cajonAbierto = ref(false);
// El editor del menú no cambia entre plantillas, pero acá no hay columna donde
// meterlo: en la computadora baja como un panel desde la barra del menú y en el
// celular vive en el cajón, que es donde el arrastre táctil se siente bien.
const editandoMenu = ref(false);
const editandoMenuMovil = ref(false);

const cartCount = computed(() => page.props.cartCount || 0);
const negocio = computed(() => page.props.negocio);
const secciones = computed(() => page.props.secciones);
const menu = computed(() => page.props.menu || []);
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
        <header class="bg-surface-1/90 backdrop-blur-2xl border-b border-border sticky top-0 z-50">
            <div class="max-w-[1500px] mx-auto px-4 sm:px-8">
                <!-- Fila 1: marca, buscador y cuenta -->
                <div class="flex items-center justify-between min-h-[var(--barra-alto)] py-2 gap-3">
                    <button @click="cajonAbierto = true" aria-label="Menú" class="md:hidden p-2 -ml-2 text-text-secondary hover:text-text transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    <Link :href="route('home')" class="flex items-center gap-3 shrink-0 group">
                        <img v-if="negocio.logo" :src="negocio.logo" :alt="negocio.nombre" class="h-[var(--logo-alto)] object-contain group-hover:opacity-80 transition" />
                        <div v-else class="leading-tight text-center md:text-left">
                            <span class="block text-[17px] sm:text-[22px] font-semibold tracking-[0.18em] uppercase">{{ negocio.nombre }}</span>
                            <span v-if="negocio.eslogan" class="block text-[10px] sm:text-[11px] text-text-muted tracking-[0.3em] uppercase">{{ negocio.eslogan }}</span>
                        </div>
                    </Link>

                    <!-- El buscador vive a la derecha y sin caja: en una vidriera
                         la barra ancha del catálogo le roba peso a la marca. -->
                    <div class="flex items-center gap-1 sm:gap-3">
                        <div class="relative hidden sm:block" @keydown.escape="buscadorAbierto = false">
                            <input type="text" placeholder="Buscar"
                                :value="busqueda"
                                @input="alEscribir"
                                @keyup.enter="buscar"
                                @focus="resultados.length && (buscadorAbierto = true)"
                                class="w-36 lg:w-52 focus:w-64 pl-9 pr-3 py-2 text-[13px] bg-transparent border-0 border-b border-border rounded-none focus:ring-0 focus:border-accent placeholder:text-text-muted transition-all duration-300" />
                            <svg class="w-4 h-4 text-text-muted absolute left-2 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <div v-if="buscadorAbierto" class="absolute top-full right-0 w-80 mt-2 bg-surface-1 border border-border rounded-xl shadow-xl shadow-black/10 overflow-hidden z-[100]">
                                <button v-for="r in resultados" :key="r.id" @mousedown.prevent="irAlProducto(r.slug)"
                                    class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-surface-2 transition text-left">
                                    <div class="w-10 h-12 rounded bg-surface-2 overflow-hidden shrink-0">
                                        <img v-if="r.imagen" :src="r.imagen" class="w-full h-full object-cover" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[13px] text-text truncate">{{ r.nombre }}</p>
                                        <p class="text-[11px] text-text-muted uppercase tracking-wider">{{ r.marca }}</p>
                                    </div>
                                </button>
                                <button @mousedown.prevent="buscar" class="w-full px-4 py-2.5 text-[11px] uppercase tracking-[0.2em] text-accent hover:bg-surface-2 transition text-left border-t border-border">
                                    Ver todo
                                </button>
                            </div>
                        </div>

                        <template v-if="page.props.auth.user">
                            <Link v-if="page.props.auth.esStaff && secciones.listaPrecios" :href="route('lista-precios')"
                                class="hidden lg:block text-[11px] uppercase tracking-[0.18em] text-text-secondary hover:text-accent px-2 py-2 transition">Precios</Link>
                            <Link :href="route('mis-pedidos')" class="hidden lg:block text-[11px] uppercase tracking-[0.18em] text-text-secondary hover:text-accent px-2 py-2 transition">Pedidos</Link>
                            <Link :href="route('profile.edit')" aria-label="Mi cuenta" class="p-2 text-text-secondary hover:text-accent transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.4"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                            </Link>
                            <Link :href="route('logout')" method="post" as="button" class="hidden sm:block text-[11px] uppercase tracking-[0.18em] text-text-muted hover:text-red-400 px-2 py-2 transition">Salir</Link>
                        </template>
                        <template v-else>
                            <Link :href="route('login')" class="hidden sm:block text-[11px] uppercase tracking-[0.18em] text-text-secondary hover:text-accent px-3 py-2 transition">Ingresar</Link>
                            <Link :href="route('register')" class="hidden lg:block text-[11px] uppercase tracking-[0.18em] text-white bg-accent hover:bg-accent-bright px-4 py-2.5 transition">Crear cuenta</Link>
                        </template>

                        <Link :href="route('cart.index')" aria-label="Carrito" class="relative p-2 transition"
                            :class="cartCount > 0 ? 'text-accent' : 'text-text-secondary hover:text-accent'">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12A1.125 1.125 0 0119.75 21.75H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z"/>
                            </svg>
                            <span v-if="cartCount > 0" class="absolute -top-0.5 -right-0.5 min-w-[17px] h-[17px] px-1 rounded-full bg-accent text-white text-[10px] font-bold flex items-center justify-center">{{ cartCount }}</span>
                        </Link>
                    </div>
                </div>

                <!-- Fila 2: el menú, centrado. En el celular se desliza con el
                     dedo en vez de esconderse: son las secciones del negocio y
                     tienen que estar a un toque. -->
                <nav class="relative flex items-center justify-center gap-[var(--menu-espacio)] h-11 -mt-1 overflow-x-auto scrollbar-oculta">
                    <Link v-for="item in menu" :key="item.id" :href="item.url"
                        class="shrink-0 flex items-center gap-1.5 px-3 py-2 text-[11px] uppercase tracking-[0.18em] whitespace-nowrap transition-colors border-b-2"
                        :class="esActivo(item) ? 'text-accent border-accent' : 'text-text-secondary border-transparent hover:text-text'">
                        <span v-if="item.emoji" class="text-[13px] leading-none">{{ item.emoji }}</span>
                        {{ item.titulo }}
                    </Link>

                    <!-- Solo lo ve el dueño. En la computadora abre un panel; el
                         editor adentro es el mismo de siempre. -->
                    <button v-if="puedeEditarMenu" @click="editandoMenu = !editandoMenu"
                        class="hidden md:flex shrink-0 items-center gap-1.5 ml-2 px-3 py-1.5 text-[11px] uppercase tracking-[0.15em] text-text-muted border border-dashed border-border hover:border-accent hover:text-accent transition">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                        Editar menú
                    </button>
                </nav>
            </div>

            <!-- El panel del editor, colgado de la barra -->
            <Transition name="desplegar">
                <div v-if="editandoMenu" class="hidden md:block border-t border-border bg-surface-1">
                    <div class="max-w-sm mx-auto px-4 py-4">
                        <MenuEditor v-model="editandoMenu" />
                    </div>
                </div>
            </Transition>
        </header>

        <!-- Ancho completo y sin margen: en esta plantilla el encabezado es la
             vidriera propiamente dicha. -->
        <div v-if="$slots.encabezado">
            <slot name="encabezado" />
        </div>

        <!-- Cajón del celular: acá van la cuenta y el editor del menú. Las
             secciones no se repiten, ya están en la fila de arriba. -->
        <Teleport to="body">
            <Transition name="fundido"><div v-if="cajonAbierto" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm md:hidden" @click="cajonAbierto = false"></div></Transition>
            <Transition name="desde-izquierda">
                <aside v-if="cajonAbierto" class="fixed left-0 top-0 bottom-0 w-[290px] z-[55] bg-surface-1 border-r border-border p-5 overflow-y-auto md:hidden">
                    <div class="flex justify-between items-center mb-6">
                        <span class="text-[11px] uppercase tracking-[0.25em] text-text-muted">Mi cuenta</span>
                        <button @click="cajonAbierto = false" aria-label="Cerrar" class="p-1.5 text-text-muted hover:text-text transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="space-y-1">
                        <template v-if="!page.props.auth.user">
                            <Link :href="route('login')" class="block px-3 py-3 text-[12px] uppercase tracking-[0.18em] text-text-secondary hover:text-text hover:bg-surface-2 transition" @click="cajonAbierto = false">Ingresar</Link>
                            <Link :href="route('register')" class="block px-3 py-3 text-[12px] uppercase tracking-[0.18em] text-accent hover:bg-accent/10 transition" @click="cajonAbierto = false">Crear cuenta</Link>
                        </template>
                        <template v-else>
                            <Link :href="route('mis-pedidos')" class="block px-3 py-3 text-[12px] uppercase tracking-[0.18em] text-text-secondary hover:text-text hover:bg-surface-2 transition" @click="cajonAbierto = false">Mis pedidos</Link>
                            <Link :href="route('profile.edit')" class="block px-3 py-3 text-[12px] uppercase tracking-[0.18em] text-text-secondary hover:text-text hover:bg-surface-2 transition" @click="cajonAbierto = false">Mi cuenta</Link>
                            <Link v-if="page.props.auth.esStaff && secciones.listaPrecios" :href="route('lista-precios')" class="block px-3 py-3 text-[12px] uppercase tracking-[0.18em] text-text-secondary hover:text-text hover:bg-surface-2 transition" @click="cajonAbierto = false">Lista de precios</Link>
                            <Link :href="route('logout')" method="post" as="button" class="block w-full text-left px-3 py-3 text-[12px] uppercase tracking-[0.18em] text-text-muted hover:text-red-400 transition">Salir</Link>
                        </template>
                    </div>

                    <div v-if="puedeEditarMenu" class="mt-6 pt-5 border-t border-border">
                        <p class="text-[11px] uppercase tracking-[0.25em] text-text-muted mb-3">Menú de la tienda</p>
                        <MenuEditor v-model="editandoMenuMovil" />
                    </div>
                </aside>
            </Transition>
        </Teleport>

        <main class="max-w-[1500px] mx-auto px-4 sm:px-8"><slot /></main>

        <footer class="border-t border-border mt-24">
            <div class="max-w-[1500px] mx-auto px-4 sm:px-8 py-14">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-10 text-center md:text-left">
                    <div>
                        <img v-if="negocio.logo" :src="negocio.logo" :alt="negocio.nombre" class="h-[var(--logo-alto)] mb-4 opacity-70 mx-auto md:mx-0" />
                        <p v-else class="text-[14px] tracking-[0.2em] uppercase mb-4">{{ negocio.nombre }}</p>
                        <p v-if="negocio.descripcion" class="text-[13px] text-text-muted leading-relaxed max-w-xs mx-auto md:mx-0">{{ negocio.descripcion }}</p>
                    </div>
                    <div>
                        <h3 class="text-[11px] uppercase tracking-[0.25em] text-text-muted mb-5">Tienda</h3>
                        <div class="space-y-3">
                            <Link :href="route('productos.index')" class="block text-[13px] text-text-secondary hover:text-accent transition">Ver todo</Link>
                            <Link :href="route('productos.index', { vista: 'categorias' })" class="block text-[13px] text-text-secondary hover:text-accent transition">Categorías</Link>
                            <Link :href="route('productos.index', { vista: 'marcas' })" class="block text-[13px] text-text-secondary hover:text-accent transition">Marcas</Link>
                            <Link v-if="page.props.auth.esStaff && secciones.listaPrecios" :href="route('lista-precios')" class="block text-[13px] text-text-secondary hover:text-accent transition">Lista de precios</Link>
                            <Link v-for="p in page.props.paginas" :key="p.slug" :href="route('paginas.show', p.slug)" class="block text-[13px] text-text-secondary hover:text-accent transition">{{ p.titulo }}</Link>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-[11px] uppercase tracking-[0.25em] text-text-muted mb-5">Contacto</h3>
                        <div class="space-y-3 text-[13px] text-text-muted">
                            <p v-if="negocio.direccion">{{ negocio.direccion }}</p>
                            <p v-if="negocio.telefono">{{ negocio.telefono }}</p>
                            <a v-if="negocio.whatsapp" :href="`https://wa.me/${negocio.whatsapp}`" target="_blank" class="block text-accent hover:text-accent-bright transition">Escribir por WhatsApp</a>
                            <a v-if="negocio.instagram" :href="negocio.instagram" target="_blank" class="block text-accent hover:text-accent-bright transition">Instagram</a>
                        </div>
                        <div v-if="negocio.mediosPago.length" class="flex flex-wrap justify-center md:justify-start gap-1.5 mt-5">
                            <span v-for="m in negocio.mediosPago" :key="m" class="text-[11px] uppercase tracking-wider text-text-muted border border-border px-2 py-1">{{ m }}</span>
                        </div>
                    </div>
                </div>
                <div class="mt-12 pt-6 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-2 text-[11px] uppercase tracking-[0.2em] text-text-muted">
                    <span>© {{ new Date().getFullYear() }} {{ negocio.nombre }}</span>
                    <span v-if="negocio.ciudad">{{ negocio.ciudad }}</span>
                </div>
            </div>
        </footer>

        <div class="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-40 flex flex-col gap-2.5">
            <a v-if="negocio.instagram" :href="negocio.instagram" target="_blank" title="Instagram"
                class="w-11 h-11 sm:w-12 sm:h-12 rounded-full bg-gradient-to-br from-purple-600 via-pink-500 to-orange-400 flex items-center justify-center shadow-lg shadow-pink-500/20 hover:scale-110 transition-transform">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
            </a>
            <a v-if="negocio.whatsapp" :href="`https://wa.me/${negocio.whatsapp}`" target="_blank" title="WhatsApp"
                class="w-11 h-11 sm:w-12 sm:h-12 rounded-full bg-[#25D366] flex items-center justify-center shadow-lg shadow-green-500/20 hover:scale-110 transition-transform">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            </a>
        </div>

        <!-- Igual que en Catálogo: va en el marco para que cubra todas las
             pantallas de una (el carrito y la ficha rechazan cosas en silencio
             si esto no está). -->
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
