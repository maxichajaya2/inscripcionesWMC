<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import colorbar from '@/Components/colorbar.vue';
import { router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import Dialog from 'primevue/dialog';
import FormValidacionDoc from './FormValidacionDoc.vue';
import FormInscription from './FormInscription.vue';
import FormTourCourse from './FormTourCourse.vue';
import FormPayment from './FormPayment.vue';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import Card from 'primevue/card';
import Stepper from 'primevue/stepper';
import StepList from 'primevue/steplist';
import StepPanels from 'primevue/steppanels';
import StepPanel from 'primevue/steppanel';
import Step from 'primevue/step';
import "../../../css/inscripciones.css";

const visible = ref(true);
const loading = ref(false);
const toast = useToast();
const bloqueoExtranjero = ref(false);
const categoriaIdActual = ref(null);
const uploading = ref(false);
const uploadProgress = ref(0);


const props = defineProps({
    title: String,
    categorias: Object,
    adicionales: Array,
    section: String,
    course:Number
})


const formDataPayment = ref(null);
const data_persona = ref({});
const showRequisitosModal = ref(false); // Controla el modal
const tempResIns = ref(null);
const isPaying = ref(false);
const nacionalidadSeleccionada = ref(null);
const childFormValidacionDoc = ref();
const childFormInscription = ref(null);
const childFormTourCourse = ref(null);
const tipo_origen = ref(0);
const categoria_seleccionada = ref({});
const extras_para_mostrar = ref([]);
const urlParams = new URLSearchParams(window.location.search);
const sectionUrl = urlParams.get('section') || 'inscripciones';
const showConfirmNoExtrasModal = ref(false);
const resumen_dinamico = ref({
    total: 0,
    dias_seleccionados: [],
    requiere_doc: false,
    tiene_doc: false
});

const actualizarResumen = (datos) => {
    resumen_dinamico.value = { ...resumen_dinamico.value, ...datos };
};

const validate = async (value) => {
    loading.value = true;
    switch (value) {
        case "Documento":
            const resDoc = await childFormValidacionDoc.value.getValidacionDoc();
            if (resDoc.validate) {
                // Guardamos DNI y TipoDoc aquí para usarlos en el siguiente paso
                data_persona.value = resDoc.formValidacionDoc;
                loading.value = false;
                return true;
            }
            break;

        case "Inscripcion":
            const resIns = await childFormInscription.value.getInscripcion();
            if (resIns.validate) {
                try {
                    const payload = new FormData();

                    // 1. PASAMOS TODOS LOS DATOS DE LA PERSONA (PASO 1)
                    // Esto incluye: tipo_doc, documento, nombres, pais, sexo, fecha_nacimiento, etc.
                    Object.keys(data_persona.value).forEach(key => {
                        payload.append(key, data_persona.value[key]);
                    });

                    // 2. PASAMOS LOS DATOS DE INSCRIPCIÓN/FACTURACIÓN (PASO 2)
                    Object.keys(resIns.formInscription).forEach(key => {
                        // Si el campo es el archivo, lo agregamos tal cual
                        if (key === 'uploadDocument') {
                            if (resIns.formInscription[key]) {
                                payload.append(key, resIns.formInscription[key]);
                            }
                        } else {
                            // Solo agregamos si no existe ya (para no duplicar datos del paso 1)
                            if (!payload.has(key)) {
                                payload.append(key, resIns.formInscription[key]);
                            }
                        }
                    });

                    const response = await axios.post('/pago/getform', payload, {
                        headers: { 'Content-Type': 'multipart/form-data' }
                    });

                    if (response.data.status && response.data.formulario) {
                        formDataPayment.value = response.data.formulario;
                        const cat = props.categorias.find(c => c.id == resIns.formInscription.selected_categoria);
                        if (cat) categoria_seleccionada.value = cat;
                        loading.value = false;
                        return true;
                    } else {
                        toast.add({ severity: 'error', summary: 'Error', detail: response.data.message });
                    }
                } catch (error) {
                    console.error("Error:", error);
                    toast.add({
                        severity: 'error',
                        summary: 'Error',
                        detail: 'Payment processing failed. Please try again.'
                    });
                }
            }
            break;

    }
    loading.value = false;
    return false;
}

const seleccionarOrigen = (origen, id_numerico) => {
    nacionalidadSeleccionada.value = origen; // Guarda el texto 'peruano'/'extranjero'
    tipo_origen.value = id_numerico;         // Guarda el número 1 o 2
    visible.value = false;

    console.log("Nacionalidad:", origen);
    console.log("ID Numérico:", tipo_origen.value); // Para que veas en consola que se guardó
};

const goStart = () => {
    router.get(route('inscripcion.index'));
};


// 2. Función que simplemente avanza (se usará en ambos casos)
const proceedToBilling = () => {
    showConfirmNoExtrasModal.value = false;
    activeStep.value = "3";
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const activeStep = ref("1"); // Control del paso actual

const confirmarYProcesar = async (extras = []) => {
    showRequisitosModal.value = false;
    loading.value = true;
    isPaying.value = true;

    try {
        const payload = new FormData();

        // 1. Datos Persona
        Object.keys(data_persona.value).forEach(key => {
            payload.append(key, data_persona.value[key]);
        });

        // 2. Datos Inscripción
        if (tempResIns.value && tempResIns.value.formInscription) {
            Object.keys(tempResIns.value.formInscription).forEach(key => {
                if (key === 'uploadDocument') {
                    if (tempResIns.value.formInscription[key]) {
                        payload.append(key, tempResIns.value.formInscription[key]);
                    }
                } else {
                    if (!payload.has(key)) {
                        payload.append(key, tempResIns.value.formInscription[key]);
                    }
                }
            });
        }

        const urlParams = new URLSearchParams(window.location.search);
        const profileId = urlParams.get('profile');
        if (profileId) {
            payload.append('profile', profileId);
        }

        // ==========================================================
        // AGREGA ESTA LÍNEA AQUÍ (CRÍTICO):
        // ==========================================================
        payload.append('section', props.section);
        // ==========================================================
        // 3. Datos Extras (JSON String)
        payload.append('extras_seleccionados', JSON.stringify(extras));

        // 4. Enviar al Backend
        const response = await axios.post('/pago/getform', payload, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        if (response.data.status && response.data.formulario) {
            formDataPayment.value = response.data.formulario;

            // Avanzamos al paso 4
            activeStep.value = "4";

            loading.value = false;

            // Actualizar referencia de categoría
            const catId = tempResIns.value.formInscription.selected_categoria;
            const encontrada = props.categorias.find(c => c.id == catId);
            if (encontrada) {
                categoria_seleccionada.value = encontrada;
            }

            const totalFinal = response.data.total_real || tempResIns.value.total_final;
            actualizarResumen({ total: totalFinal });

        } else {
            toast.add({ severity: 'error', summary: 'Error', detail: response.data.message });
            loading.value = false;
        }

    } catch (error) {
        console.error("Error en confirmarYProcesar:", error);
        toast.add({ severity: 'error', summary: 'Error', detail: 'Ocurrió un error al procesar la solicitud.' });
        loading.value = false;
    }
};


const handleCursosHaciaFacturacion = async () => {
    loading.value = true;

    if (childFormTourCourse.value) {
        // Validamos primero (por si en sección viajes es obligatorio)
        const esValido = childFormTourCourse.value.validarSeleccion();
        if (!esValido) {
            loading.value = false;
            return;
        }

        // Chequeamos si la lista de seleccionados está vacía
        const tieneSeleccion = childFormTourCourse.value.extras_seleccionados.length > 0;

        if (!tieneSeleccion) {
            // SI NO TIENE NADA: Mostramos el modal "bonito" de advertencia
            showConfirmNoExtrasModal.value = true;
            loading.value = false;
            return;
        }

        // Si tiene selección, guardamos los objetos para el resumen
        extras_para_mostrar.value = childFormTourCourse.value.selectedObjects || [];
    }

    // Si todo está ok y tiene selección, avanzamos directo
    proceedToBilling();
    loading.value = false;
};
// NUEVO HANDLER: Del paso 3 (Facturación) al 4 (Pago Final)
const handleInscripcionFinal = async () => {
    loading.value = true;

    // Validamos el formulario de Inscripción/Facturación
    const resIns = await childFormInscription.value.getInscripcion();

    if (resIns.validate) {
        // Guardamos la data de facturación
        tempResIns.value = resIns;

        // Como ya tenemos los cursos guardados del paso anterior,
        // disparamos la función que envía TODO al backend
        const idsExtras = childFormTourCourse.value?.extras_seleccionados || [];
        await confirmarYProcesar(idsExtras);
    }

    loading.value = false;
};

onMounted(() => {
    // 1. Leer el ID de la URL (ej: ?category=5)
    const urlParams = new URLSearchParams(window.location.search);
    const categoryId = urlParams.get('category');

    categoriaIdActual.value = categoryId;

    // Lógica de bloqueo para categorías 35 y 29
    if (categoryId == '35' || categoryId == '29') {
        bloqueoExtranjero.value = true;
    }

    if (categoryId && props.categorias) {
        // 2. Buscar en la lista de categorías que mandó el controlador
        // Convertimos a array por si viene como objeto indexado de PHP
        const listaCategorias = Object.values(props.categorias);
        const encontrada = listaCategorias.find(c => c.id == categoryId);

        if (encontrada) {
            // 3. SE ASIGNA AL REF QUE USA EL RESUMEN
            categoria_seleccionada.value = encontrada;
            console.log("Resumen actualizado con:", encontrada.nombre_en);
        }
    }

});

const handleBeforeUnload = (event) => {
    // SI isPaying es true, esta función termina aquí y NO sale ninguna alerta
    if (isPaying.value) return;

    // Solo si NO está pagando y hay datos, lanza la advertencia
    if (data_persona.value.documento || data_persona.value.nombres) {
        event.preventDefault();
        event.returnValue = ''; // Esto activa la alerta estándar del navegador
    }
};

onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);
});

onUnmounted(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
});

watch(activeStep, () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

watch(activeStep, (newStep) => {
    if (!window.fbq) return;

    switch (newStep) {
        case "1":
            window.fbq('track', 'Personal Details WMC 2026 COURSE - VISIT', { step: 'Personal Details' });
            break;
        case "2":
            window.fbq('track', 'Courses and Technical Visits WMC 2026 COURSE - VISIT', { step: 'Courses and Technical Visits' });
            break;
        case "3":
            window.fbq('track', 'Billing Information WMC 2026 COURSE - VISIT', { step: 'Billing Information' });
            break;

        case "4":
            window.fbq('track', 'Payment Process WMC 2026 COURSE - VISIT', { step: 'Payment Process' });
            break;
    }
});

</script>

<template>
    <AppLayout class="bg-gradient-wmc">


        <div class="px-3 mx-auto max-w-7xl md:px-6 lg:px-8 relative">

            <div id="titulo_inicial" class="mt-8 mb-8">
                <h1 class="text-3xl text-green-iimp font-bold mb-2 text-yellow-price">{{ props.title }}</h1>
                <colorbar class="block w-auto" />
            </div>

            <div class="mt-6 mb-6">
                <!-- ============= PASOS =============
                 ================================== -->
                <Stepper v-model:value="activeStep" class="w-full">
                    <StepList class="text-black-price bg-degradient">
                        <Step value="1">Personal Details</Step>
                        <!-- <Step value="2">Billing Information</Step>
                        <Step value="3">Courses or Tours</Step> -->

                        <Step value="2">Courses or Tours</Step>
                        <Step value="3">Billing Information</Step>
                        <Step value="4">Payment Process</Step>
                    </StepList>


                    <StepPanels>
                        <!-- ========== Personal Details ==========
                         ==========================================  -->
                        <StepPanel v-slot="{ activateCallback }" value="1"
                            class="rounded-2xl border-2 border-green-iimp bg-white-price shadow-wmc">
                            <FormValidacionDoc ref="childFormValidacionDoc" :tipo_origen="tipo_origen"  v-if="activeStep === '1'"/>

                            <!-- <div class="fixed bottom-4 right-4 z-50 md:hidden animate-fade-in-up">
                                <Button label="Validate" icon="pi pi-arrow-right" iconPos="right"
                                    class="bg-degradient border-rounded-full" :loading="loading"
                                    :disabled="childFormValidacionDoc?.esCategoriaDeSocio && childFormValidacionDoc?.hasSearched && !childFormValidacionDoc?.esSocio"
                                    @click="async () => {
                                        const isValid = await validate('Documento');


                                        if (isValid) {
                                            if (childFormValidacionDoc?.esCategoriaDeSocio) {
                                                if (childFormValidacionDoc?.esSocio) activateCallback('2');
                                            } else {
                                                activateCallback('2');
                                            }
                                        }
                                    }" />
                            </div> -->

                            <div
                                class="sticky bottom-0 left-0 w-full p-4 md:p-6 bg-white/95 backdrop-blur-md border-t border-gray-200 shadow-[0_-5px_20px_rgba(0,0,0,0.1)] z-[50] flex justify-end gap-3 rounded-b-2xl">

                                <Button label="Validate" icon="pi pi-arrow-right" iconPos="right"
                                    class="bg-degradient border-rounded-full" :loading="loading"
                                    :disabled="childFormValidacionDoc?.esCategoriaDeSocio && childFormValidacionDoc?.hasSearched && !childFormValidacionDoc?.esSocio"
                                    @click="async () => {
                                        const isValid = await validate('Documento');

                                        if (isValid) {
                                            if (childFormValidacionDoc?.esCategoriaDeSocio) {
                                                if (childFormValidacionDoc?.esSocio) activateCallback('2');
                                            } else {
                                                activateCallback('2');
                                            }
                                        }
                                    }" />
                            </div>
                        </StepPanel>


                        <!-- ========== Courses or Tours ==========
                         ==========================================  -->


                        <StepPanel v-slot="{ activateCallback }" value="2"
                            class="rounded-2xl border-2 border-green-iimp bg-white shadow-wmc">

                            <FormTourCourse ref="childFormTourCourse" :data_persona="data_persona"
                                :adicionales="props.adicionales" :section="sectionUrl" :course="props.course" />

                            <div
                                class="sticky bottom-0 left-0 w-full p-4 md:p-6 bg-white/95 backdrop-blur-md border-t border-gray-200 shadow-[0_-5px_20px_rgba(0,0,0,0.1)] z-[50] flex justify-between gap-3 rounded-b-2xl">
                                <!-- <Button label="Back" severity="secondary" icon="pi pi-arrow-left"
                                    class="flex-1 md:flex-none" @click="activateCallback('1')" /> -->
                                <Button label="Back" severity="secondary" icon="pi pi-arrow-left"
                                    class="flex-1 md:flex-none p-3 font-bold" @click="activateCallback('1')" />
                                <Button label="Continue to Billing" iconPos="right" icon="pi pi-arrow-right"
                                    class="bg-degradient border-rounded-full flex-1 md:flex-none" :loading="loading"
                                    @click="handleCursosHaciaFacturacion" />
                            </div>
                        </StepPanel>



                        <!-- ========== Billing Information ==========
                         ==========================================  -->

                        <StepPanel v-slot="{ activateCallback }" value="3"
                            class="rounded-2xl border-2 border-green-iimp bg-white shadow-wmc">

                            <FormInscription ref="childFormInscription" :data_persona="data_persona"
                                :categorias="props.categorias" />

                            <div
                                class="sticky bottom-0 left-0 w-full p-4 md:p-6 bg-white/95 backdrop-blur-md border-t border-gray-200 shadow-[0_-5px_20px_rgba(0,0,0,0.1)] z-[50] flex justify-between gap-3 rounded-b-2xl">
                                <Button label="Back" severity="secondary" icon="pi pi-arrow-left"
                                    class="flex-1 md:flex-none" @click="activateCallback('2')" />
                                <Button label="Register & Pay" iconPos="right" icon="pi pi-arrow-right"
                                    class="bg-degradient border-rounded-full flex-1 md:flex-none" :loading="loading"
                                    @click="handleInscripcionFinal" />
                            </div>
                        </StepPanel>
                        <!-- ========== Payment Process ==========
                         ==========================================  -->
                        <StepPanel v-slot="{ activateCallback }" value="4"
                            class="rounded-2xl border-2 border-green-iimp bg-white shadow-wmc">

                            <FormPayment ref="childFormPayment" :data_persona="data_persona"
                                :formulario="formDataPayment" :categoria_seleccionada="categoria_seleccionada"
                                :extras_seleccionados="extras_para_mostrar" />

                            <div
                                class="sticky bottom-0 left-0 w-full p-4 md:p-6 bg-white/95 backdrop-blur-md border-t border-gray-200 shadow-[0_-5px_20px_rgba(0,0,0,0.1)] z-[50] flex justify-between gap-3 rounded-b-2xl">
                                <Button label="Back" severity="secondary" icon="pi pi-arrow-left"
                                    @click="activateCallback('3')" />
                            </div>
                        </StepPanel>
                    </StepPanels>
                </Stepper>
            </div>

        </div>

        <!-- NACIONALIDAD MODAL =======
         ============================== -->
        <Dialog v-model:visible="visible" modal :showHeader="false" :closable="false" :style="{ width: '900px' }"
            class="bg-transparent shadow-none border-none px-0 overflow-hidden" :pt="{
                mask: { class: 'bg-slate-900/90 backdrop-blur-md' },
                content: { class: 'bg-transparent px-0 py-0 border-none shadow-none overflow-hidden' }
            }">


            <div
                class="bg-gradient-to-r from-[#001e3d] via-[#002855] to-[#003366] px-8 py-8 border-b-4 border-yellow-500 flex items-left justify-left text-left relative overflow-hidden">


                <div class="relative z-10">
                    <div class="mb-3 animate-fade-in-up" style="animation-delay: 0.1s;">
                        <span
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-yellow-500/10 border border-yellow-500/30 backdrop-blur-md">
                            <span class="relative flex h-2 w-2">
                                <span
                                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-yellow-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-yellow-500"></span>
                            </span>
                            <span class="text-[10px] font-bold text-yellow-400 uppercase tracking-widest">Registration
                                Open</span>
                        </span>
                    </div>

                    <h2
                        class=" text-2xl md:text-4xl text-transparent bg-clip-text bg-gradient-to-r from-yellow-200 via-yellow-400 to-yellow-600">
                        World Mining Congress 2026

                    </h2>
                </div>
            </div>

            <div class="p-8 md:p-12 bg-slate-50 px-4 overflow-y-auto flex-1">
                <div v-if="bloqueoExtranjero"
                    class="p-4 mb-6 rounded-xl bg-amber-50 border border-amber-200 flex items-start gap-3 animate-fade-in-up">
                    <i class="pi pi-info-circle text-amber-600 text-xl mt-0.5"></i>
                    <p class="text-sm text-amber-800 leading-relaxed">
                        The <b>International</b> option for Author Members or Member Participants is not available
                        through
                        this portal. If you are an international attendee and an active member, please contact
                        <a href="mailto:asociados@iimp.org.pe"
                            class="font-bold underline hover:text-amber-900">asociados@iimp.org.pe</a> for
                        assistance.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">


                    <button @click="seleccionarOrigen('peruano', 1)"
                        class="group relative h-auto rounded-2xl bg-white border border-slate-200 shadow-lg hover:shadow-2xl hover:shadow-red-900/20 transition-all duration-300 flex flex-col overflow-hidden hover:-translate-y-2">

                        <div class="h-1 w-full bg-red-600"></div>

                        <div class="p-5 md:p-8 flex flex-col items-center text-center h-full">
                            <div
                                class="w-16 h-12 md:w-24 md:h-24 mb-4 md:mb-6 relative drop-shadow-lg group-hover:scale-110 transition-transform duration-300">
                                <svg viewBox="0 0 300 200" class="w-full h-full rounded-lg shadow-sm">
                                    <rect width="300" height="200" fill="#ffffff" stroke="#e2e8f0" stroke-width="2" />
                                    <rect width="100" height="200" fill="#D91023" />
                                    <rect x="200" width="100" height="200" fill="#D91023" />
                                </svg>
                                <div class="absolute inset-0 bg-red-500/20 blur-2xl -z-10 rounded-full"></div>
                            </div>

                            <h3
                                class="text-xl md:text-2xl font-black text-slate-800 group-hover:text-red-700 transition-colors uppercase">
                                National
                            </h3>
                            <p class="text-xs md:text-sm text-slate-500 mt-2 mb-4 md:mb-8 leading-relaxed font-medium">
                                Peruvian citizen or resident with DNI.
                            </p>

                            <div class="mt-auto w-full">
                                <span
                                    class="block w-full py-2.5 md:py-3 px-4 rounded-xl bg-gradient-to-r from-red-700 to-red-600 text-white font-bold text-xs md:text-sm tracking-wider uppercase shadow-md group-hover:shadow-lg group-hover:from-red-600 group-hover:to-red-500 transition-all flex items-center justify-center gap-2">
                                    Continue Purchase <i class="pi pi-arrow-right text-[10px] md:text-xs"></i>
                                </span>
                            </div>
                        </div>
                    </button>

                    <button @click="!bloqueoExtranjero && seleccionarOrigen('extranjero', 2)"
                        :disabled="bloqueoExtranjero" :class="[
                            'group relative h-auto rounded-2xl bg-white border border-slate-200 shadow-lg transition-all duration-300 flex flex-col overflow-hidden',
                            bloqueoExtranjero
                                ? 'opacity-60 cursor-not-allowed grayscale'
                                : 'hover:shadow-2xl hover:shadow-blue-900/20 hover:-translate-y-2'
                        ]">

                        <div class="h-1 w-full bg-blue-600"></div>

                        <div class="p-8 flex flex-col items-center text-center h-full">

                            <div
                                class="w-24 h-24 mb-6 relative drop-shadow-lg group-hover:scale-110 group-hover:rotate-12 transition-all duration-500">
                                <svg viewBox="0 0 24 24" fill="none" class="w-full h-full text-blue-600">
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"
                                        fill="#eff6ff" />
                                    <path d="M2.3 12H21.7" stroke="currentColor" stroke-width="1.5"
                                        stroke-linecap="round" />
                                    <path
                                        d="M12 2.3C14.5 5 16 8.5 16 12C16 15.5 14.5 19 12 21.7C9.5 19 8 15.5 8 12C8 8.5 9.5 5 12 2.3Z"
                                        stroke="currentColor" stroke-width="1.5" fill="#dbeafe" />
                                </svg>
                                <div class="absolute inset-0 bg-blue-500/20 blur-2xl -z-10 rounded-full"></div>
                            </div>

                            <h3
                                class="text-2xl font-black text-slate-800 group-hover:text-blue-700 transition-colors uppercase">
                                International
                            </h3>
                            <p class="text-sm text-slate-500 mt-2 mb-8 leading-relaxed font-medium">
                                Joining from abroad (Foreigner).
                            </p>

                            <div class="mt-auto w-full">
                                <span :class="[
                                    'block w-full py-3 px-4 rounded-xl text-white font-bold text-sm tracking-wider uppercase shadow-md transition-all flex items-center justify-center gap-2',
                                    bloqueoExtranjero
                                        ? 'bg-gray-400'
                                        : 'bg-gradient-to-r from-[#002855] to-blue-700 group-hover:from-blue-800 group-hover:to-blue-600'
                                ]">
                                    {{ bloqueoExtranjero ? 'Not Available' : 'Continue Purchase' }}
                                    <i v-if="!bloqueoExtranjero" class="pi pi-arrow-right text-xs"></i>
                                </span>
                            </div>
                        </div>
                    </button>

                </div>
            </div>

            <div class="bg-gray-50 p-6 border-t border-slate-200 text-center">
                <button @click="goStart"
                    class="group flex items-center justify-center gap-2 text-xs text-slate-400 font-bold uppercase tracking-widest hover:text-red-500 transition-colors mx-auto">
                    <i class="pi pi-times-circle text-lg group-hover:scale-110 transition-transform"></i>
                    Cancel Process
                </button>
            </div>

        </Dialog>


        <Dialog v-model:visible="uploading" modal :closable="false" :showHeader="false" :style="{ width: '350px' }">
            <div class="flex flex-col items-center p-6 text-center">
                <div class="mb-4 text-green-iimp relative">
                    <i class="pi pi-cloud-upload text-5xl animate-bounce"></i>
                </div>
                <h3 class="text-xl font-black text-blue-900 mb-2">Uploading Document</h3>
                <p class="text-sm text-gray-500 mb-6">Please wait while we process your file...</p>

                <div class="w-full bg-gray-100 rounded-full h-4 mb-2 overflow-hidden border border-gray-200">
                    <div class="bg-gradient-to-r from-green-500 to-green-300 h-full transition-all duration-300"
                        :style="{ width: uploadProgress + '%' }"></div>
                </div>
                <span class="text-xs font-bold text-green-600">{{ uploadProgress }}% Completed</span>
            </div>
        </Dialog>

        <Dialog v-if="false" v-model:visible="showRequisitosModal" modal header="Requirements and Conditions"
            :style="{ width: '50vw' }" :breakpoints="{ '1199px': '75vw', '575px': '90vw' }">
            <div class="flex flex-col gap-4">
                <p class="text-gray-600">Please review the requirements before proceeding to payment.</p>

                <div class="w-full h-[400px] border rounded overflow-hidden">
                    <iframe src="/documents/reglamento.pdf" class="w-full h-full" frameborder="0">
                    </iframe>
                </div>

                <div class="flex justify-end gap-3 mt-4">
                    <Button label="Cancel" icon="pi pi-times" @click="showRequisitosModal = false"
                        class="p-button-text p-button-secondary" />
                    <Button label="I Accept & Continue to Payment" icon="pi pi-check" @click="confirmarYProcesar"
                        class="p-button-success" :loading="loading" />
                </div>
            </div>
        </Dialog>

        <Dialog v-model:visible="showConfirmNoExtrasModal" modal :showHeader="false" :closable="false"
            :style="{ width: '500px' }" class="rounded-3xl overflow-hidden border-none shadow-2xl animate-modal-entry">

            <div class="p-0 relative overflow-hidden">
                <div
                    class="bg-gradient-to-r from-blue-900 via-blue-700 to-blue-900 p-8 text-center relative overflow-hidden">
                    <div class="absolute inset-0 shine-effect"></div>

                    <div class="relative z-10">
                        <div
                            class="w-20 h-20 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-4 backdrop-blur-md border border-white/20 animate-bounce-slow">
                            <i
                                class="pi pi-shopping-cart text-yellow-400 text-4xl drop-shadow-[0_0_15px_rgba(250,204,21,0.6)]"></i>
                        </div>
                        <h3 class="text-2xl font-black text-white uppercase tracking-tighter italic">Enhance your
                            Experience</h3>
                        <div class="h-1 w-20 bg-yellow-400 mx-auto mt-2 rounded-full"></div>
                    </div>
                </div>

                <div class="p-10 bg-white text-center">
                    <p class="text-slate-700 text-lg leading-tight font-medium">
                        Are you sure you want to proceed without adding <span class="text-blue-700 font-extrabold">Short
                            Courses</span> or <span class="text-blue-700 font-extrabold">Technical Visits</span>?
                    </p>

                    <p
                        class="mt-5 text-sm text-slate-400 font-medium italic bg-slate-50 p-4 rounded-2xl border border-slate-100">
                        "Don't miss the chance to learn from global experts and visit Peru's premier mining sites."
                    </p>

                    <div class="mt-8 flex flex-col gap-4">
                        <button @click="showConfirmNoExtrasModal = false"
                            class="group relative w-full py-4 px-6 rounded-2xl bg-blue-900 text-white font-black uppercase tracking-widest overflow-hidden transition-all hover:scale-[1.02] active:scale-95 shadow-[0_10px_20px_rgba(30,58,138,0.3)]">
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:animate-shine-fast">
                            </div>
                            <span class="relative flex items-center justify-center gap-3">
                                <i class="pi pi-arrow-left animate-pulse"></i>
                                Review Courses & Visits
                            </span>
                        </button>

                        <button @click="proceedToBilling"
                            class="w-full py-2 text-slate-400 text-[10px] font-black uppercase tracking-[0.2em] hover:text-red-500 transition-colors duration-300">
                            No thanks, proceed to billing anyway
                        </button>
                    </div>
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
/* 1. Aseguramos que el panel del Stepper permita el posicionamiento sticky */
:deep(.p-steppanel) {
    display: flex;
    flex-direction: column;
    height: 100%;
    position: relative;
}

/* 2. El contenido del formulario debe empujar los botones hacia abajo */
:deep(.p-steppanel-content) {
    flex: 1;
}

/* 3. Estilo para el contenedor Sticky */
.sticky {
    position: -webkit-sticky;
    /* Soporte para Safari */
    position: sticky;
    bottom: -2px;
    /* Un pequeño ajuste para que encaje perfecto con el borde */
    background-color: rgba(255, 255, 255, 0.98);
    z-index: 40;
    margin-top: auto;
    /* Empuja el div al final si el contenido es corto */
}

/* 4. En Web, le damos un redondeado inferior para que coincida con el Card */
@media (min-width: 768px) {
    .sticky {
        border-bottom-left-radius: 1rem;
        border-bottom-right-radius: 1rem;
    }
}



.animate-fade-in-down {
    animation: fadeInDown 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-40px) scale(0.95);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@media (max-width: 768px) {

    /* Añade espacio al final de los paneles para que el footer fijo no tape el contenido */
    :deep(.p-steppanel) {
        padding-bottom: 80px !important;
    }
}

/* Animación de entrada del Modal */
.animate-modal-entry {
    animation: modalSpring 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}

@keyframes modalSpring {
    0% {
        opacity: 0;
        transform: scale(0.8) translateY(50px);
    }

    100% {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* Brillo constante en la cabecera */
.shine-effect {
    background: linear-gradient(to right,
            rgba(255, 255, 255, 0) 0%,
            rgba(255, 255, 255, 0.05) 50%,
            rgba(255, 255, 255, 0) 100%);
    transform: skewX(-25deg);
    animation: shineLoop 3s infinite;
}

@keyframes shineLoop {
    0% {
        transform: translateX(-150%) skewX(-25deg);
    }

    100% {
        transform: translateX(150%) skewX(-25deg);
    }
}

/* Animación de brillo rápido al pasar el mouse por el botón */
.group-hover\:animate-shine-fast {
    animation: shineFast 0.6s forwards;
}

@keyframes shineFast {
    0% {
        transform: translateX(-100%) skewX(-25deg);
    }

    100% {
        transform: translateX(100%) skewX(-25deg);
    }
}

/* Rebote suave para el icono */
.animate-bounce-slow {
    animation: bounceSlow 3s infinite;
}

@keyframes bounceSlow {

    0%,
    100% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-10px);
    }
}

/* Botón flotante - SOLO VISIBLE EN MÓVIL */
.mobile-floating-validate {
    display: none;
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    z-index: 50;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in-up {
    animation: fadeInUp 0.4s ease-out forwards;
}

/* MOSTRAR BOTÓN FLOTANTE SOLO EN MÓVIL */
@media (max-width: 768px) {
    .mobile-floating-validate {
        display: block;
    }
}

@media (max-width: 480px) {
    .mobile-floating-validate {
        bottom: 1rem !important;
        right: 1rem !important;
    }
}

/* Botón flotante Register - SOLO VISIBLE EN MÓVIL */
.mobile-floating-register {
    display: none;
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    z-index: 50;
}

/* MOSTRAR BOTÓN FLOTANTE SOLO EN MÓVIL */
@media (max-width: 768px) {
    .mobile-floating-register {
        display: block;
    }
}

@media (max-width: 480px) {
    .mobile-floating-register {
        bottom: 1rem !important;
        right: 1rem !important;
    }
}
</style>
