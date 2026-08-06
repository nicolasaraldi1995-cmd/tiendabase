<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import GrillaProductos from '@/Components/GrillaProductos.vue';
import ImageModal from '@/Components/ImageModal.vue';
import EtiquetasDelProducto from '@/Components/EtiquetasDelProducto.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
const props = defineProps({ items: Array, total: Number, envioGratis: Boolean, recomendados: Array, cliente: Object, faltaParaElMinimo: { type: Number, default: 0 }, avisos: { type: Array, default: () => [] }, pagoOnline: { type: Object, default: () => ({ disponible: false, obligatorio: false }) } });
const page = usePage();
const modalImage = ref(null);
// El cliente elige cómo pagar solo si el negocio cobra online y no lo exige.
// Si lo exige, no hay nada que preguntar; si no cobra online, tampoco.
const eligeComoPagar = computed(() => props.pagoOnline.disponible && !props.pagoOnline.obligatorio);
// Si el negocio no reparte, la única opción posible es el retiro: arrancar en
// "envío" dejaba elegida una entrega que el servidor iba a rechazar.
const form = useForm({ entrega: page.props.haceEnvios ? 'envio' : 'retiro', notas: '', forma_pago: 'online' });
// El botón dice lo que va a pasar: mandar a pagar a otra pantalla no es lo
// mismo que confirmar un pedido, y conviene que el cliente lo sepa antes.
const vaAPagarAhora = computed(() => props.pagoOnline.disponible && (props.pagoOnline.obligatorio || form.forma_pago === 'online'));
const textoDelBoton = computed(() => {
    if (form.processing) return 'Procesando...';
    return vaAPagarAhora.value ? `Pagar $${props.total.toLocaleString('es-AR')}` : 'Confirmar pedido';
});
// El envío solo se ofrece si el negocio reparte (Configuración → Envío): antes
// era una opción fija y se podía confirmar un domicilio sin domicilio.
const opcionesDeEntrega = computed(() => {
    const retiro = { v: 'retiro', l: 'Retiro en el local', d: page.props.negocio.direccion || 'Coordinamos por WhatsApp' };

    if (!page.props.haceEnvios) return [retiro];

    return [{ v: 'envio', l: 'Envío a domicilio', d: props.envioGratis ? 'Gratis' : 'A coordinar' }, retiro];
});

function submit() { form.post(route('checkout.store')); }
function updateQty(id, q) { router.patch(route('cart.update'), { presentacion_id: id, cantidad: q }, { preserveScroll: true }); }
function removeItem(id) { router.delete(route('cart.remove'), { data: { presentacion_id: id }, preserveScroll: true }); }
</script>
<template>
    <Head title="Finalizar pedido" />
    <PublicLayout>
        <div class="max-w-5xl mx-auto px-6 py-8">
            <h1 class="text-xl font-semibold text-text mb-6">Revisá tu pedido</h1>

            <!-- Los avisos que el negocio puso en sus etiquetas (Catálogo → Etiquetas). -->
            <div v-for="(a, i) in avisos" :key="i" class="mb-4 bg-surface-2 border border-border rounded-xl px-5 py-3.5 flex items-start gap-3">
                <svg class="w-5 h-5 text-accent shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                <p class="text-[13px] text-text leading-relaxed">{{ a.texto }}</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                <div class="lg:col-span-3 space-y-6">
                    <div class="bg-surface-1 rounded-2xl border border-border">
                        <div class="px-6 py-4 border-b border-border">
                            <h2 class="font-medium text-text">Productos ({{ items.length }})</h2>
                            <p class="text-[11px] text-text-muted">Modificá cantidades o eliminá antes de confirmar</p>
                        </div>
                        <div class="divide-y divide-border">
                            <div v-for="item in items" :key="item.presentacion_id" class="px-6 py-4 flex items-center gap-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-[13px] font-medium text-text truncate">
                                        {{ item.nombre }}
                                        <EtiquetasDelProducto :etiquetas="item.etiquetas" variante="enLinea" />
                                    </p>
                                    <p class="text-[11px] text-text-muted">{{ item.marca }} · {{ item.unidad }} · ${{ item.precio.toLocaleString('es-AR') }} c/u</p>
                                </div>
                                <div class="flex items-center bg-surface-3 rounded-lg shrink-0">
                                    <button @click="updateQty(item.presentacion_id, item.cantidad - 1)" class="w-7 h-7 flex items-center justify-center text-text-muted hover:text-text text-xs transition">−</button>
                                    <span class="w-6 h-7 flex items-center justify-center text-[12px] font-semibold text-text">{{ item.cantidad }}</span>
                                    <button @click="updateQty(item.presentacion_id, item.cantidad + 1)" class="w-7 h-7 flex items-center justify-center text-text-muted hover:text-text text-xs transition">+</button>
                                </div>
                                <p class="text-[13px] font-semibold text-text w-20 text-right shrink-0">${{ item.subtotal.toLocaleString('es-AR') }}</p>
                                <button @click="removeItem(item.presentacion_id)" class="text-text-muted hover:text-red-400 transition"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            </div>
                        </div>
                        <div class="px-6 py-4 border-t border-border flex justify-between">
                            <Link :href="route('productos.index')" class="text-[13px] text-accent hover:text-accent-bright transition">+ Agregar más</Link>
                            <span class="font-semibold text-text">${{ total.toLocaleString('es-AR') }}</span>
                        </div>
                    </div>
                    <div class="bg-surface-1 rounded-2xl border border-border p-6">
                        <h2 class="font-medium text-text mb-4">Entrega</h2>
                        <div class="flex gap-3 mb-4">
                            <label v-for="o in opcionesDeEntrega" :key="o.v"
                                class="flex-1 border rounded-xl p-4 cursor-pointer transition-all" :class="form.entrega===o.v?'border-accent bg-accent/10':'border-border hover:border-border-hover'">
                                <input v-model="form.entrega" type="radio" :value="o.v" class="hidden" />
                                <p class="text-[13px] font-medium text-text">{{ o.l }}</p>
                                <p class="text-[11px] text-text-muted mt-0.5">{{ o.d }}</p>
                            </label>
                        </div>
                        <div v-if="eligeComoPagar" class="mb-4">
                            <h2 class="font-medium text-text mb-3">¿Cómo querés pagar?</h2>
                            <div class="flex gap-3">
                                <label v-for="o in [{v:'online',l:'Pagar ahora',d:'Tarjeta, débito o dinero en cuenta. Tu pedido queda confirmado al instante.'},{v:'coordinar',l:'Coordinar el pago',d:'Confirmás el pedido y arreglamos el pago por WhatsApp.'}]" :key="o.v"
                                    class="flex-1 border rounded-xl p-4 cursor-pointer transition-all" :class="form.forma_pago===o.v?'border-accent bg-accent/10':'border-border hover:border-border-hover'">
                                    <input v-model="form.forma_pago" type="radio" :value="o.v" class="hidden" />
                                    <p class="text-[13px] font-medium text-text">{{ o.l }}</p>
                                    <p class="text-[11px] text-text-muted mt-0.5 leading-relaxed">{{ o.d }}</p>
                                </label>
                            </div>
                        </div>

                        <label class="block text-[13px] text-text-secondary mb-1.5">Notas (opcional)</label>
                        <textarea v-model="form.notas" rows="2" placeholder="Horario, indicaciones..." class="w-full bg-surface-2 border border-border rounded-xl px-4 py-3 text-[13px] text-text placeholder:text-text-muted focus:border-accent focus:ring-1 focus:ring-accent/20 transition"></textarea>
                    </div>
                    <div v-if="recomendados.length">
                        <div class="flex items-center gap-3 mb-5"><div class="w-0.5 h-5 rounded-full bg-accent"></div><h2 class="text-[15px] font-semibold text-text">Te puede interesar</h2></div>
                        <GrillaProductos :productos="recomendados" variante="sugerencias" @image-click="modalImage=$event" />
                    </div>
                </div>
                <div class="lg:col-span-2">
                    <div class="sticky top-20 space-y-4">
                        <div class="bg-surface-1 rounded-2xl border border-border p-6">
                            <div class="flex items-center justify-between mb-3"><h2 class="font-medium text-text">Tus datos</h2><Link :href="route('profile.edit')" class="text-[11px] text-accent hover:text-accent-bright transition">Editar</Link></div>
                            <div class="space-y-1 text-[13px] text-text-secondary">
                                <p class="font-medium text-text">{{ cliente.nombre }}</p>
                                <p v-if="cliente.negocio" class="text-[11px]">{{ cliente.negocio }}</p>
                                <p>{{ cliente.celular }}</p><p>{{ cliente.email }}</p><p>{{ cliente.direccion }}</p>
                                <p>{{ cliente.ciudad }}<span v-if="cliente.provincia">, {{ cliente.provincia }}</span></p>
                            </div>
                        </div>
                        <div class="bg-surface-1 rounded-2xl border border-border p-6">
                            <div class="flex justify-between text-[13px] text-text-secondary mb-1"><span>Subtotal</span><span>${{ total.toLocaleString('es-AR') }}</span></div>
                            <div class="flex justify-between text-[13px] text-text-secondary mb-4"><span>Envío</span><span>{{ form.entrega==='retiro'?'Retiro':(envioGratis?'Gratis':'A coordinar') }}</span></div>
                            <div class="h-px bg-border mb-4"></div>
                            <div class="flex justify-between font-semibold text-lg text-text mb-5"><span>Total</span><span>${{ total.toLocaleString('es-AR') }}</span></div>
                            <form @submit.prevent="submit">
                                <div v-if="Object.keys(form.errors).length" class="mb-3 bg-red-500/10 border border-red-500/20 rounded-xl px-4 py-2.5">
                                    <p v-for="(error, key) in form.errors" :key="key" class="text-[12px] text-red-400">{{ error }}</p>
                                </div>
                                <!-- El backend igual lo frena; acá se avisa antes de que lo intente. -->
                                <p v-if="faltaParaElMinimo > 0" class="mb-3 text-[12px] text-amber-600 bg-amber-500/10 border border-amber-500/20 rounded-xl px-4 py-2.5">
                                    Te faltan ${{ faltaParaElMinimo.toLocaleString('es-AR') }} para llegar al pedido mínimo.
                                </p>
                                <button type="submit" :disabled="form.processing || faltaParaElMinimo > 0" class="w-full bg-accent hover:bg-accent-bright text-white font-medium py-3 rounded-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed">{{ textoDelBoton }}</button>
                            </form>
                            <p v-if="vaAPagarAhora" class="text-[10px] text-center text-text-muted mt-3">Te llevamos a MercadoPago para completar el pago</p>
                            <p v-else class="text-[10px] text-center text-text-muted mt-3">Podés modificar desde "Mis pedidos"</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <ImageModal :src="modalImage" @close="modalImage=null" />
    </PublicLayout>
</template>
