<script setup>
import { ref, onMounted, computed, watch, nextTick } from 'vue';
import Card from 'primevue/card';

const props = defineProps({
    categoria_seleccionada: Object,
    data_persona: Object,
    formulario: Object,
    vouchers: Object,
    tipo_origen: [String, Number],
    datos_facturacion: Object,
    perfil_id: [String, Number],
    extras_seleccionados: {
        type: Array,
        default: () => []
    }
});


const urlParams = new URLSearchParams(window.location.search);
const esSeccionViajes = computed(() => urlParams.get('section') === 'viajes');
// Controla si el usuario aceptó los términos para habilitar el botón
const termsAccepted = ref(false);
const procesandoPago = ref(false);
const confirmacionComprobante = ref(false);


const esPeruano = computed(() => {
    return Number(props.tipo_origen) === 1;
});

const puedePagar = computed(() => {
    // 1. Siempre debe aceptar términos
    if (!termsAccepted.value) return false;

    // 2. Si es peruano, el check de comprobante es OBLIGATORIO
    if (esPeruano.value) {
        return confirmacionComprobante.value;
    }

    // 3. Si es extranjero, con los términos basta
    return true;
});

const esFactura = computed(() => {
    // 1. Prioridad absoluta al selector del formulario anterior
    // Si es 2 es Factura, si es 1 (o cualquier otro) es Boleta.
    const tipoDoc = props.datos_facturacion?.tipoDocumentoEmpresa;

    if (tipoDoc === 2) return true;
    if (tipoDoc === 1) return false;

    // 2. Backup por longitud (solo si el selector falla o es nulo)
    // El RUC en Perú siempre tiene 11 dígitos.
    const documento = props.datos_facturacion?.tipoDocumentoEmpresa?.toString().trim() || "";
    return documento.length === 11;
});

const tipoComprobanteTexto = computed(() => {
    return esFactura.value ? 'Commercial Invoice (Factura)' : 'Sales Receipt (Boleta)';
});

const precioInscripcion = computed(() => {
    return props.categoria_seleccionada?.precio_disponible?.valor || '0.00';
});

const montoDescuento = computed(() => {
    // Si pasaste la prop :descuento directamente
    if (props.descuento !== undefined && props.descuento !== null) {
        return props.descuento;
    }
    // Backup: Por si acaso viniera dentro de formulario (aunque con el cambio de arriba ya no es necesario)
    return props.formulario?.descuento || 0;
});

const mountNiubiz = async (data) => {
    let config = data;
    if (data?.formulario) config = data.formulario;
    if (!config || !config.script) return;

    await nextTick();

    const form_holder = document.getElementById('form_holder');
    if (form_holder) {
        // 1. LIMPIEZA EXTREMA DE OBJETOS GLOBALES
        window.VisanetCheckout = undefined;
        window.v_checkout = undefined;
        delete window.VisanetCheckout;

        form_holder.innerHTML = "";
        const existingScripts = document.querySelectorAll('script[src*="checkout.js"]');
        existingScripts.forEach(s => s.remove());

        const residuals = document.querySelectorAll('.v-modal, .niubiz-visible, #visa_checkout, #visa_ads, .main-checkout');
        residuals.forEach(r => r.remove());

        // O simplemente: script.dataset.buttontext = "Pay Now"
        // 2. INYECCIÓN DE LA PASARELA
        setTimeout(() => {
            const form = document.createElement("form");
            form.action = config.form.action;
            form.method = "POST";
            form.id = "niubiz_form";

            const script = document.createElement("script");
            script.src = config.script.src + "?ts=" + new Date().getTime();

            // ============================================================
            // CONFIGURACIÓN DEL BOTÓN (ESTO ES LO QUE BUSCABAS)
            // ============================================================
            // PROBAMOS CON AMBOS ATRIBUTOS PARA ASEGURARNOS
            const textoBoton = "Pay USD " + config.script.amount;

            script.setAttribute('data-buttontext', textoBoton);
            script.setAttribute('data-untokenized-buttontext', textoBoton); // Refuerzo para Niubiz
            script.dataset.buttontext = textoBoton;

            // Color del botón (Azul WMC)
            script.dataset.formbuttoncolor = "#1d4ed8";
            // ============================================================

            // Dataset (Merchant ID 651054910 según contrato)
            script.dataset.sessiontoken = config.script.sessiontoken;
            script.dataset.channel = config.script.channel;
            script.dataset.merchantid = config.script.merchantid;
            script.dataset.merchantlogo = config.script.merchantlogo;
            script.dataset.formbuttoncolor = config.script.formbuttoncolor;
            script.dataset.amount = config.script.amount;
            script.dataset.purchasenumber = config.script.purchasenumber;
            script.dataset.cardholdername = config.script.cardholdername;
            script.dataset.cardholderlastname = config.script.cardholderlastname;
            script.dataset.cardholderemail = config.script.cardholderemail;
            script.dataset.expirationminutes = config.script.expirationminutes;
            script.dataset.timeouturl = config.script.timeouturl;

            script.dataset.canvas = "form_holder";

            // --- SOLUCIÓN DEFINITIVA: VIGILANTE DEL BOTÓN ---
            const detectorBotones = setInterval(() => {
                // Niubiz suele renderizar un botón con la clase .v-button o dentro de un div específico
                const btnNiubiz = document.querySelector('.v-button') ||
                    document.querySelector('#form_holder button') ||
                    document.querySelector('#niubiz_form button');

                if (btnNiubiz) {
                    console.log("¡Botón de Niubiz detectado! Pegando evento de carga...");

                    btnNiubiz.addEventListener('click', () => {
                        // Le damos un respiro de 300ms para que Niubiz procese el clic
                        // y luego lanzamos nuestro spinner
                        setTimeout(() => {
                            procesandoPago.value = true;
                            console.log("Spinner activado por clic en botón Niubiz");
                        }, 300);
                    });

                    // Una vez que le pegamos el evento al botón, dejamos de buscar
                    clearInterval(detectorBotones);
                }
            }, 1000); // Revisa cada segundo hasta que el botón aparezca

            form.appendChild(script);
            form_holder.appendChild(form);

            console.log("Niubiz instanciado en modo preventivo.");
        }, 200);
    }
};

onMounted(() => {
    if (props.formulario) mountNiubiz(props.formulario);
    console.log("El perfil_id recibido es:", props.perfil_id);

});

// Vigilamos si la data del formulario cambia para recargar la pasarela
watch(() => props.formulario, (newVal) => {
    if (newVal) mountNiubiz(newVal);
}, { deep: true, immediate: true });

// Helper para extraer datos del script de Niubiz
const scriptData = computed(() => {
    if (!props.formulario) return null;
    return props.formulario.formulario ? props.formulario.formulario.script : props.formulario.script;
});



</script>

<template>

    <div id="FormPaymentFinish" class="w-full">
        <div class="flex flex-col items-center p-6 w-full">

            <div class="text-blue-900 font-bold text-center text-2xl mb-6 tracking-wide uppercase">
                Finalize Registration
            </div>

            <Card class="w-full max-w-md shadow-2xl border-t-4 border-blue-600 rounded-xl bg-white overflow-hidden">
                <template #content>

                    <div v-if="formulario">

                        <!--
                        <div v-if="datos_facturacion" class="mt-4 p-3 bg-gray-900 rounded-lg border border-gray-700">
                            <div class="text-[10px] text-gray-400 uppercase font-bold mb-2 tracking-widest">
                                Billing Data Debug:
                            </div>
                            <pre class="text-[10px] text-green-400 overflow-x-auto font-mono leading-tight">
                        {{ JSON.stringify(datos_facturacion, null, 2) }}
                            </pre>
                        </div> -->
                        <div class="mb-4 border-b pb-6 p-4">
                            <div class="flex justify-between items-center py-3 border-b border-gray-100">
                                <span
                                    class="font-bold text-gray-500 uppercase text-xs tracking-wider">Participant</span>
                                <span class="text-gray-800 font-semibold text-right">
                                    {{ data_persona?.nombres }} {{ data_persona?.apellido_paterno }}
                                </span>
                            </div>

                            <div class="flex justify-between items-center py-3 border-b border-gray-100">
                                <span class="font-bold text-gray-500 uppercase text-xs tracking-wider">Category</span>
                                <span class="text-blue-600 font-bold text-right">
                                    {{ (categoria_seleccionada && categoria_seleccionada.nombre_en) ?
                                        categoria_seleccionada.nombre_en : (categoria_seleccionada &&
                                            categoria_seleccionada.nombre ? categoria_seleccionada.nombre : 'WMC 2026 Delegate')
                                    }}
                                </span>
                            </div>
                            <div v-if="!esSeccionViajes"
                                class="flex justify-between items-start mb-1 text-sm pl-2 border-l-2 border-gray-100 p-1 ">
                                <div class="flex flex-col w-2/3">
                                    <span class="text-blue-600 font-medium leading-tight">
                                        Register
                                    </span>
                                </div>
                                <span class="text-gray-600 font-medium text-right w-1/3">
                                    USD {{ precioInscripcion || '0.00' }}
                                </span>

                            </div>
                            <div v-if="montoDescuento > 0"
                                class="flex justify-between items-start mb-1 text-sm pl-2 border-l-2 border-green-500 p-2 bg-green-50">
                                <div class="flex flex-col w-2/3">
                                    <span class="text-green-700 font-bold italic">Descuento Cupón</span>
                                </div>
                                <span class="text-green-700 font-bold text-right w-1/3">
                                    - USD {{ montoDescuento }}
                                </span>
                            </div>

                            <div v-for="extra in extras_seleccionados" :key="extra.id"
                                class="flex justify-between items-start mb-1 text-sm pl-2 border-l-2 border-gray-100 p-1 ">
                                <div class="flex flex-col w-2/3">
                                    <span class="text-blue-600 font-medium leading-tight">
                                        {{ extra.titulo || extra.nombre_en }}
                                    </span>
                                    <span class="text-[10px] text-gray-400 uppercase">
                                        {{ extra.tipo === 'viaje' ? 'Technical Visit' : 'Course' }}
                                    </span>
                                </div>
                                <span class="text-gray-600 font-medium text-right w-1/3">
                                    USD {{ extra.precio_disponible?.valor || '0.00' }}
                                </span>

                            </div>

                            <div
                                class="flex justify-between items-center py-4 mt-4 bg-blue-50 px-3 rounded-lg border border-blue-100">
                                <span class="font-bold text-blue-800">Total to Pay</span>
                                <span class="font-bold text-blue-900 text-2xl">
                                    USD {{ scriptData?.amount }}
                                </span>
                            </div>
                        </div>

                        <div v-if="perfil_id == 10"
                            class="my-4 p-4 bg-blue-50 border-l-4 border-blue-400 rounded-r-lg shadow-sm">
                            <div class="flex gap-3">
                                <i class="pi pi-info-circle text-blue-600 text-lg mt-1"></i>
                                <div class="text-xs text-blue-900 leading-relaxed">
                                    <p class="font-bold mb-1 uppercase tracking-tight">Important Information:</p>
                                    <p class="font-bold border-t border-blue-200 pt-2">
                                        Please note: Registration for this program DOES NOT include access to the WMC
                                        Congress or the Trade Fair.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="px-4 mb-6">
                            <div class="flex items-start gap-3 p-3 bg-orange-50 border border-orange-200 rounded-lg">
                                <input type="checkbox" id="check_terms" v-model="termsAccepted"
                                    class="mt-1 w-5 h-5 cursor-pointer accent-blue-600" />
                                <!-- <label for="check_terms"
                                    class="text-xs text-gray-700 leading-tight cursor-pointer select-none">
                                    I accept the
                                    <a href="/documents/reglamento.pdf" target="_blank"
                                        class="text-blue-700 font-bold underline">Terms and Conditions</a>,
                                    the
                                    <a href="/documents/politicas.pdf" target="_blank"
                                        class="text-blue-700 font-bold underline">Registration Policies</a>
                                    and the
                                    <a href="/documents/privacy_policy.pdf" target="_blank"
                                        class="text-blue-700 font-bold underline">Privacy Policy</a>
                                    of WMC 2026.
                                </label> -->
                                <label for="check_terms"
                                    class="text-xs text-gray-700 leading-tight cursor-pointer select-none">
                                    I accept the

                                    <span v-if="perfil_id != 10">
                                        <a href="/documents/reglamento.pdf" target="_blank"
                                            class="text-blue-700 font-bold underline">Terms and Conditions</a>,
                                        the
                                        <a href="/documents/politicas.pdf" target="_blank"
                                            class="text-blue-700 font-bold underline">Registration Policies</a>
                                        and the
                                    </span>

                                    <a href="/documents/privacy_policy.pdf" target="_blank"
                                        class="text-blue-700 font-bold underline">Privacy Policy</a>
                                    of WMC 2026.
                                </label>
                            </div>
                        </div>

                        <!-- COMPROBANTE DE PAGO -->
                        <div v-if="esPeruano" class="mb-6 p-4 rounded-xl border-2 transition-all duration-300"
                            :class="confirmacionComprobante ? 'border-green-500 bg-green-50' : 'border-orange-200 bg-orange-50'">

                            <div class="flex items-center gap-3 mb-3">
                                <i class="pi text-xl"
                                    :class="[esFactura ? 'pi-building text-purple-600' : 'pi-user text-blue-600']"></i>
                                <span class="font-black uppercase text-sm tracking-tight text-slate-800">
                                    {{ tipoComprobanteTexto }} Confirmation
                                </span>
                            </div>

                            <div
                                class="text-[11px] leading-relaxed text-slate-600 mb-4 text-justify bg-white/50 p-3 rounded-lg border border-orange-100">
                                <p v-if="esFactura">
                                    You are requesting a <strong>COMMERCIAL INVOICE</strong> under the name of
                                    <span class="text-purple-700 font-bold">{{ datos_facturacion?.razonSocial || 'the company' }}</span>
                                    with RUC
                                    <span class="text-purple-700 font-bold">{{ datos_facturacion?.documentoEmpresa
                                        }}</span>.
                                </p>
                                <p v-else>
                                    You are requesting a <strong>SALES RECEIPT</strong> under the name of
                                    <span class="text-blue-700 font-bold">{{ datos_facturacion?.razonSocial || 'no encontrado' }} </span>.
                                </p>

                                <p class="mt-2 text-red-600 font-semibold italic">
                                    Before continuing, please make sure your information is correct. Once the document
                                    has been issued, no changes or refunds can be made.
                                </p>
                            </div>

                            <div class="flex items-start gap-3">
                                <input type="checkbox" id="check_comprobante" v-model="confirmacionComprobante"
                                    class="mt-1 w-5 h-5 cursor-pointer accent-green-600" />
                                <label for="check_comprobante"
                                    class="text-[11px] text-slate-700 font-bold cursor-pointer select-none">
                                    I confirm that the billing information is correct and I assume responsibility
                                    for the issuance of this document.
                                </label>
                            </div>
                        </div>

                        <div class="relative">
                            <div v-if="!puedePagar" class="absolute inset-0 z-10 cursor-not-allowed"
                                title="Accept requirements to enable payment"></div>

                            <div id="form_holder"
                                class="flex justify-center p-4 min-h-[100px] border-2 border-blue-100 bg-blue-50/30 rounded-lg overflow-hidden transition-all duration-300"
                                :style="{
                                    opacity: puedePagar ? '1' : '0.4',
                                    filter: puedePagar ? 'grayscale(0)' : 'grayscale(1)',
                                    pointerEvents: puedePagar ? 'auto' : 'none'
                                }">
                                <div class="flex flex-col items-center text-gray-400 py-4">
                                    <i class="pi pi-spin pi-spinner mb-2"></i>
                                    <span class="text-xs">Connecting to secure gateway...</span>
                                </div>
                            </div>
                        </div>
                        <p class="text-[9px] text-center text-gray-400 mt-6 uppercase tracking-widest">
                            Secure payment gateway by Niubiz
                        </p>
                    </div>

                    <div v-else class="p-10 text-center">
                        <i class="pi pi-spin pi-spinner text-3xl text-blue-600"></i>
                        <p class="mt-2 text-gray-500 font-bold">Obtaining session data...</p>
                    </div>
                </template>
            </Card>
        </div>
    </div>
    <Dialog v-model:visible="procesandoPago" modal :showHeader="false" :closable="false" :baseZIndex="99999"
        class="bg-slate-900/80 backdrop-blur-sm border-none shadow-none m-0 p-0"
        :style="{ width: '100vw', height: '100vh' }" :pt="{
            root: { style: 'z-index: 99999 !important;' },
            mask: { style: 'z-index: 99998 !important; background: rgba(0,0,0,0.85);' }
        }">
        <div class="flex flex-col items-center justify-center">
            <div class="relative w-24 h-24 mb-6">
                <div class="absolute inset-0 border-4 border-blue-500/20 rounded-full"></div>
                <div class="absolute inset-0 border-4 border-t-blue-500 rounded-full animate-spin"></div>
                <i
                    class="pi pi-lock absolute inset-0 flex items-center justify-center text-blue-400 text-2xl animate-pulse"></i>
            </div>

            <h3 class="text-2xl font-black text-white uppercase tracking-widest animate-pulse">
                Processing Secure Payment
            </h3>
            <p class="text-blue-300 text-sm mt-2 font-medium">
                Please do not close or refresh the window...
            </p>

            <div class="w-64 h-1 bg-white/10 rounded-full mt-6 overflow-hidden">
                <div class="h-full bg-gradient-to-r from-blue-600 to-blue-400 animate-infinite-scroll"></div>
            </div>
        </div>
    </Dialog>
</template>
<style scoped>
.pi-lock {
    text-shadow: 0 0 15px rgba(96, 165, 250, 0.8);
    animation: lock-glow 1.5s ease-in-out infinite;
}

@keyframes infiniteScroll {
    0% {
        transform: translateX(-100%);
    }

    100% {
        transform: translateX(100%);
    }
}

.animate-infinite-scroll {
    width: 100%;
    animation: infiniteScroll 2s linear infinite;
}
</style>
