<script setup>
/**
 * Plantilla Carta: centrada y angosta, como una carta de papel. El menú va
 * arriba, sin barra lateral y sin buscador grande — con catálogos chicos, el
 * cliente recorre las secciones, no busca.
 *
 * El buscador queda igual, pero discreto: si el negocio crece de veinte a
 * doscientos productos, sigue estando.
 */
import { Link, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import MenuEditor from '@/Components/MenuEditor.vue';
import AvisoDeError from '@/Components/AvisoDeError.vue';

const page = usePage();
const cajonAbierto = ref(false);
const editandoMenu = ref(false);
const editandoMenuMovil = ref(false);
const buscadorVisible = ref(false);

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

function buscar() {
    if (busqueda.value.length >= 2) {
        router.get(route('productos.index'), { buscar: busqueda.value });
    }
}
</script>

<template>
    <div class="min-h-screen bg-surface text-text font-sans">
        <header class="bg-surface-1 border-b border-border sticky top-0 z-50">
            <div class="max-w-4xl mx-auto px-4">
                <div class="flex items-center justify-between min-h-[var(--barra-alto)] py-2 gap-3">
                    <button @click="cajonAbierto = true" aria-label="Menú" class="md:hidden p-2 -ml-2 text-text-secondary hover:text-text transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    <!-- La marca al centro: es lo primero que se lee en una carta. -->
                    <Link :href="route('home')" class="flex items-center gap-2.5 md:absolute md:left-1/2 md:-translate-x-1/2">
                        <img v-if="negocio.logo" :src="negocio.logo" :alt="negocio.nombre" class="h-[var(--logo-alto)] object-contain" />
                        <div v-else class="text-center leading-tight">
                            <span class="block text-[18px] font-semibold tracking-wide">{{ negocio.nombre }}</span>
                            <span v-if="negocio.eslogan" class="block text-[10.5px] text-text-muted tracking-[0.2em] uppercase">{{ negocio.eslogan }}</span>
                        </div>
                    </Link>

                    <div class="flex items-center gap-1 md:ml-auto">
                        <button @click="buscadorVisible = !buscadorVisible" aria-label="Buscar" class="p-2 text-text-secondary hover:text-accent transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </button>
                        <Link v-if="page.props.auth.user" :href="route('profile.edit')" aria-label="Mi cuenta" class="hidden sm:flex p-2 text-text-secondary hover:text-accent transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                        </Link>
                        <Link v-else :href="route('login')" class="hidden sm:block text-[12.5px] font-medium text-text-secondary hover:text-accent px-2.5 py-2 transition">Ingresar</Link>

                        <Link :href="route('cart.index')" aria-label="Pedido" class="relative flex items-center gap-1.5 px-3 h-10 rounded-full transition"
                            :class="cartCount > 0 ? 'bg-accent text-white hover:bg-accent-bright' : 'text-text-secondary hover:bg-surface-2'">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
                            </svg>
                            <span v-if="cartCount > 0" class="text-[13px] font-bold leading-none">{{ cartCount }}</span>
                        </Link>
                    </div>
                </div>

                <div v-if="buscadorVisible" class="pb-3">
                    <input v-model="busqueda" @keyup.enter="buscar" type="text" placeholder="Buscar en la carta..."
                        class="w-full h-10 px-3.5 text-[13px] bg-surface-2 border border-border rounded-full focus:border-accent focus:ring-2 focus:ring-accent/15 placeholder:text-text-muted transition" />
                </div>

                <nav class="flex items-center justify-center gap-[var(--menu-espacio)] h-11 overflow-x-auto scrollbar-oculta border-t border-border/60">
                    <Link v-for="item in menu" :key="item.id" :href="item.url"
                        class="shrink-0 flex items-center gap-1.5 px-3 py-2 text-[13px] whitespace-nowrap transition-colors border-b-2"
                        :class="esActivo(item) ? 'text-accent border-accent font-semibold' : 'text-text-secondary border-transparent hover:text-text'">
                        <span v-if="item.emoji" class="text-[14px] leading-none">{{ item.emoji }}</span>
                        {{ item.titulo }}
                    </Link>

                    <button v-if="puedeEditarMenu" @click="editandoMenu = !editandoMenu"
                        class="hidden md:flex shrink-0 items-center gap-1.5 ml-2 px-3 py-1.5 text-[12px] text-text-muted border border-dashed border-border rounded-full hover:border-accent hover:text-accent transition">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                        Editar menú
                    </button>
                </nav>
            </div>

            <Transition name="desplegar">
                <div v-if="editandoMenu" class="hidden md:block border-t border-border bg-surface-1">
                    <div class="max-w-sm mx-auto px-4 py-4">
                        <MenuEditor v-model="editandoMenu" />
                    </div>
                </div>
            </Transition>
        </header>

        <div v-if="$slots.encabezado" class="max-w-4xl mx-auto px-4 pt-4">
            <slot name="encabezado" />
        </div>

        <Teleport to="body">
            <Transition name="fundido"><div v-if="cajonAbierto" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm md:hidden" @click="cajonAbierto = false"></div></Transition>
            <Transition name="desde-izquierda">
                <aside v-if="cajonAbierto" class="fixed left-0 top-0 bottom-0 w-[280px] z-[55] bg-surface-1 border-r border-border p-4 overflow-y-auto md:hidden">
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

        <main class="max-w-4xl mx-auto px-4"><slot /></main>

        <footer class="border-t border-border mt-16">
            <div class="max-w-4xl mx-auto px-4 py-10 text-center">
                <p class="text-[15px] font-semibold tracking-wide">{{ negocio.nombre }}</p>
                <p v-if="negocio.descripcion" class="text-[13px] text-text-muted mt-2 max-w-md mx-auto leading-relaxed">{{ negocio.descripcion }}</p>
                <div class="flex flex-wrap justify-center gap-x-5 gap-y-2 mt-5 text-[13px] text-text-muted">
                    <span v-if="negocio.direccion">{{ negocio.direccion }}</span>
                    <span v-if="negocio.telefono">{{ negocio.telefono }}</span>
                    <a v-if="negocio.whatsapp" :href="`https://wa.me/${negocio.whatsapp}`" target="_blank" class="text-accent hover:text-accent-bright transition">WhatsApp</a>
                    <a v-if="negocio.instagram" :href="negocio.instagram" target="_blank" class="text-accent hover:text-accent-bright transition">Instagram</a>
                </div>
                <div v-if="negocio.mediosPago.length" class="flex flex-wrap justify-center gap-1.5 mt-4">
                    <span v-for="m in negocio.mediosPago" :key="m" class="text-[11px] text-text-secondary bg-surface-2 px-2.5 py-1 rounded-full">{{ m }}</span>
                </div>
                <div class="flex flex-wrap justify-center gap-x-4 gap-y-2 mt-5">
                    <Link v-for="p in page.props.paginas" :key="p.slug" :href="route('paginas.show', p.slug)" class="text-[12.5px] text-text-muted hover:text-accent transition">{{ p.titulo }}</Link>
                </div>
                <p class="text-[11px] text-text-muted mt-6">© {{ new Date().getFullYear() }} {{ negocio.nombre }}<template v-if="negocio.ciudad"> — {{ negocio.ciudad }}</template></p>
            </div>
        </footer>

        <div class="fixed bottom-4 right-4 z-40">
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
