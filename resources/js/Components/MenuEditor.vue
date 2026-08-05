<script setup>
/**
 * Editor del menú sobre la propia tienda: el dueño arrastra para ordenar,
 * renombra en el lugar y agrega secciones donde las va a ver. El panel sigue
 * teniendo la pantalla completa (Catálogo → Menú de la tienda).
 */
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import draggable from 'vuedraggable';

const page = usePage();
const editor = computed(() => page.props.menuEditor);

// El layout necesita saberlo para esconder el menú normal mientras se edita.
const editando = defineModel({ type: Boolean, default: false });

const items = ref([]);
const editandoItem = ref(null);
const creando = ref(false);
const nuevo = ref({ titulo: '', emoji: '', destino_tipo: 'categoria', destino_valor: '' });

// Se trabaja sobre una copia: mientras se arrastra, la lista cambia de orden
// en pantalla antes de que el servidor confirme.
function copiarItems() {
    // Sin editor no hay nada que copiar: al cliente no le viaja la prop.
    items.value = (editor.value?.items || []).map(i => ({ ...i }));
}

// La copia se rehace cada vez que se abre, venga de donde venga: el botón de
// acá abajo (Catálogo) o uno de afuera (Vidriera lo abre desde la barra del
// menú). Si esto dependiera solo del botón propio, abrirlo desde afuera
// mostraría el editor con la lista vacía.
watch(editando, (abierto) => {
    if (abierto) copiarItems();
}, { immediate: true });

function abrir() {
    editando.value = true;
}

function cerrar() {
    editando.value = false;
    editandoItem.value = null;
    creando.value = false;
}

function alSoltar() {
    router.post(route('menu-tienda.reordenar'), { ids: items.value.map(i => i.id) }, {
        preserveScroll: true,
        onSuccess: () => { items.value = editor.value.items.map(i => ({ ...i })); },
    });
}

function guardarItem(item) {
    router.patch(route('menu-tienda.update', item.id), {
        titulo: item.titulo,
        emoji: item.emoji,
        activo: item.activo,
    }, {
        preserveScroll: true,
        onSuccess: () => { editandoItem.value = null; items.value = editor.value.items.map(i => ({ ...i })); },
    });
}

function alternarVisible(item) {
    item.activo = !item.activo;
    guardarItem(item);
}

function borrar(item) {
    if (!confirm(`¿Sacar "${item.titulo}" del menú? Se borra la sección, no tus productos.`)) return;

    router.delete(route('menu-tienda.destroy', item.id), {
        preserveScroll: true,
        onSuccess: () => { items.value = editor.value.items.map(i => ({ ...i })); },
    });
}

const necesitaValor = computed(() => editor.value.destinosConValor.includes(nuevo.value.destino_tipo));

const opcionesDeValor = computed(() => {
    if (nuevo.value.destino_tipo === 'categoria') return editor.value.categorias.map(c => ({ valor: String(c.id), texto: c.nombre }));
    if (nuevo.value.destino_tipo === 'marca') return editor.value.marcas.map(m => ({ valor: String(m.id), texto: m.nombre }));
    if (nuevo.value.destino_tipo === 'pagina') return editor.value.paginas.map(p => ({ valor: p.slug, texto: p.titulo }));
    return [];
});

function crear() {
    router.post(route('menu-tienda.store'), nuevo.value, {
        preserveScroll: true,
        onSuccess: () => {
            nuevo.value = { titulo: '', emoji: '', destino_tipo: 'categoria', destino_valor: '' };
            creando.value = false;
            items.value = editor.value.items.map(i => ({ ...i }));
        },
    });
}

const EMOJIS = ['🏠', '🗂️', '🏷️', '📦', '✨', '🔥', '🍯', '🧸', '🔌', '🧴', '🍫', '🛠️', '🎁', '👕', '📚', '🐾'];
</script>

<template>
    <div v-if="editor">
        <!-- Botón de entrada: discreto, arriba del menú -->
        <button v-if="!editando" @click="abrir"
            class="w-full flex items-center justify-center gap-1.5 mb-2 px-3 py-2 rounded-xl text-[12px] font-medium text-text-muted border border-dashed border-border hover:border-accent hover:text-accent transition-all">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
            Editar menú
        </button>

        <div v-else class="mb-3">
            <div class="flex items-center justify-between mb-2 px-1">
                <span class="text-[11px] font-semibold text-accent uppercase tracking-wider">Editando</span>
                <button @click="cerrar" class="text-[12px] font-medium text-text-muted hover:text-text transition">Listo</button>
            </div>
            <p class="text-[10.5px] text-text-muted px-1 mb-2 leading-relaxed">Arrastrá para ordenar. Tocá el lápiz para cambiar nombre o emoji.</p>

            <draggable v-model="items" item-key="id" handle=".agarre" :animation="180"
                :delay="200" :delay-on-touch-only="true" ghost-class="opacity-40" @end="alSoltar" class="space-y-1">
                <template #item="{ element: item }">
                    <div class="rounded-xl border border-border bg-surface-1">
                        <div class="flex items-center gap-1.5 px-2 py-2">
                            <span class="agarre cursor-grab active:cursor-grabbing touch-none text-text-muted hover:text-text px-0.5" title="Arrastrar">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5"/></svg>
                            </span>
                            <span class="text-[14px] w-5 text-center shrink-0">{{ item.emoji || '•' }}</span>
                            <span class="flex-1 text-[12.5px] truncate" :class="item.activo ? 'text-text' : 'text-text-muted line-through'">{{ item.titulo }}</span>

                            <button @click="alternarVisible(item)" class="p-1 text-text-muted hover:text-accent transition" :title="item.activo ? 'Ocultar' : 'Mostrar'">
                                <svg v-if="item.activo" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243"/></svg>
                            </button>
                            <button @click="editandoItem = editandoItem === item.id ? null : item.id" class="p-1 text-text-muted hover:text-accent transition" title="Cambiar nombre o emoji">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                            </button>
                        </div>

                        <div v-if="editandoItem === item.id" class="px-2 pb-2.5 space-y-2 border-t border-border pt-2">
                            <input v-model="item.titulo" type="text" placeholder="Nombre"
                                class="w-full text-[12.5px] px-2.5 py-1.5 rounded-lg bg-surface-2 border border-border focus:border-accent outline-none" />
                            <div class="flex flex-wrap gap-1">
                                <button v-for="e in EMOJIS" :key="e" @click="item.emoji = e"
                                    class="w-7 h-7 rounded-lg text-[14px] transition-all"
                                    :class="item.emoji === e ? 'bg-accent/15 ring-1 ring-accent' : 'bg-surface-2 hover:bg-surface-3'">{{ e }}</button>
                            </div>
                            <div class="flex items-center gap-2">
                                <button @click="guardarItem(item)" class="flex-1 text-[12px] font-medium text-white bg-accent hover:bg-accent-bright py-1.5 rounded-lg transition">Guardar</button>
                                <button @click="borrar(item)" class="text-[12px] text-text-muted hover:text-red-500 px-2 py-1.5 transition">Borrar</button>
                            </div>
                        </div>
                    </div>
                </template>
            </draggable>

            <!-- Nueva sección, en el lugar donde va a quedar -->
            <button v-if="!creando" @click="creando = true"
                class="w-full flex items-center justify-center gap-1.5 mt-1.5 px-3 py-2 rounded-xl text-[12px] font-medium text-accent border border-dashed border-accent/40 hover:bg-accent/5 transition-all">
                + Nueva sección
            </button>

            <div v-else class="mt-1.5 rounded-xl border border-accent/40 bg-surface-1 p-2.5 space-y-2">
                <input v-model="nuevo.titulo" type="text" placeholder="Nombre (ej: Juguetes)"
                    class="w-full text-[12.5px] px-2.5 py-1.5 rounded-lg bg-surface-2 border border-border focus:border-accent outline-none" />
                <div class="flex flex-wrap gap-1">
                    <button v-for="e in EMOJIS" :key="e" @click="nuevo.emoji = e"
                        class="w-7 h-7 rounded-lg text-[14px] transition-all"
                        :class="nuevo.emoji === e ? 'bg-accent/15 ring-1 ring-accent' : 'bg-surface-2 hover:bg-surface-3'">{{ e }}</button>
                </div>
                <select v-model="nuevo.destino_tipo" @change="nuevo.destino_valor = ''"
                    class="w-full text-[12.5px] px-2.5 py-1.5 rounded-lg bg-surface-2 border border-border focus:border-accent outline-none">
                    <option v-for="(texto, valor) in editor.destinos" :key="valor" :value="valor">{{ texto }}</option>
                </select>
                <select v-if="necesitaValor && nuevo.destino_tipo !== 'url'" v-model="nuevo.destino_valor"
                    class="w-full text-[12.5px] px-2.5 py-1.5 rounded-lg bg-surface-2 border border-border focus:border-accent outline-none">
                    <option value="">Elegí cuál...</option>
                    <option v-for="o in opcionesDeValor" :key="o.valor" :value="o.valor">{{ o.texto }}</option>
                </select>
                <input v-if="nuevo.destino_tipo === 'url'" v-model="nuevo.destino_valor" type="url" placeholder="https://..."
                    class="w-full text-[12.5px] px-2.5 py-1.5 rounded-lg bg-surface-2 border border-border focus:border-accent outline-none" />
                <div class="flex items-center gap-2">
                    <button @click="crear" :disabled="!nuevo.titulo || (necesitaValor && !nuevo.destino_valor)"
                        class="flex-1 text-[12px] font-medium text-white bg-accent hover:bg-accent-bright py-1.5 rounded-lg transition disabled:opacity-40 disabled:cursor-not-allowed">Agregar</button>
                    <button @click="creando = false" class="text-[12px] text-text-muted hover:text-text px-2 py-1.5 transition">Cancelar</button>
                </div>
            </div>
        </div>
    </div>
</template>
