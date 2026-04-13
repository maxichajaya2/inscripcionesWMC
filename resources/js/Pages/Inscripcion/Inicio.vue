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
    perfil_id: Number,
    autores: Array,
    course: Number,
    cupones: Object

})


const formDataPayment = ref(null);
const data_persona = ref({});
const mostrarModalFacturacion = ref(false);
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

const saltoCursos = ref(true);

// Definimos la lista de pasos completa
const pasosCompletos = [
    { value: "1", label: "Personal Details" },
    { value: "2", label: "Billing Information" },
    { value: "3", label: "Courses or Tours" },
    { value: "4", label: "Payment Process" }
];



const pasosVisibles = computed(() => {
    // 1. Filtramos para quitar el paso de cursos si es necesario
    let listaFiltrada = pasosCompletos.filter(p => {
        if (saltoCursos.value) return p.value !== "3";
        return true;
    });

    // 2. REASIGNAMOS los valores para que siempre sean "1", "2", "3"...
    return listaFiltrada.map((paso, index) => {
        const nuevoValor = (index + 1).toString();
        return {
            ...paso,
            value: nuevoValor, // <--- Esto convierte el "4" en "3" automáticamente
            labelReal: paso.label
        };
    });
});

const handleIrACursosDesdeModal = () => {
    saltoCursos.value = false; // <--- AQUÍ: Al ser false, la computada RE-CALCULA y muestra el paso 3
    showConfirmNoExtrasModal.value = false;

    // Damos un respiro al DOM para que PrimeVue renderice el nuevo paso antes de saltar a él
    setTimeout(() => {
        activeStep.value = "3";
    }, 50);
};

const handleSaltarCursosEIrAPago = async () => {
    saltoCursos.value = true; // MANTIENE OCULTO EL PASO 3
    showConfirmNoExtrasModal.value = false;
    loading.value = true;
    await confirmarYProcesar([]); // Va directo al paso 4
    loading.value = false;
};

const validate = async (value) => {
    loading.value = true;
    switch (value) {
        case "Documento":
            const resDoc = await childFormValidacionDoc.value.getValidacionDoc();
            if (resDoc.validate) {
                // Guardamos DNI y TipoDoc aquí para usarlos en el siguiente paso
                data_persona.value = resDoc.formValidacionDoc;
                mostrarModalFacturacion.value = true;
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

    // console.log("Nacionalidad:", origen);
    // console.log("ID Numérico:", tipo_origen.value); // Para que veas en consola que se guardó
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
            // formDataPayment.value = response.data.formulario;
            formDataPayment.value = response.data

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
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'An error occurred while processing your request.'
        });
        loading.value = false;
    }
};

// const handleInscripcionHaciaCursos = async () => {
//     loading.value = true;
//     const resIns = await childFormInscription.value.getInscripcion();

//     if (resIns.validate) {
//         tempResIns.value = resIns;

//         // ESTO ES LO QUE DISPARA EL MODAL
//         showConfirmNoExtrasModal.value = true;
//         // await confirmarYProcesar([]);
//     }
//     loading.value = false;
// };

const handleInscripcionHaciaCursos = async () => {
    loading.value = true;
    const resIns = await childFormInscription.value.getInscripcion();

    if (resIns.validate) {
        tempResIns.value = resIns;

        // --- LÓGICA AUTOMÁTICA PARA ID 40 ---
        if (categoriaIdActual.value == '40') {
            // Si es la categoría 40, saltamos el modal y procesamos directo
            console.log("Categoría 40 detectada: Saltando modal de cursos...");
            await confirmarYProcesar([]);
        } else {
            // Para cualquier otra categoría, mostramos el modal de "Upgrade"
            showConfirmNoExtrasModal.value = true;
        }
    }
    loading.value = false;
};

const handleFinalizarTodo = async () => {
    loading.value = true;

    // 1. Validamos la selección de cursos
    if (childFormTourCourse.value) {
        const esValido = childFormTourCourse.value.validarSeleccion();
        if (!esValido) {
            loading.value = false;
            return;
        }

        // 2. Obtenemos los IDs seleccionados
        const idsExtras = childFormTourCourse.value.extras_seleccionados || [];
        extras_para_mostrar.value = childFormTourCourse.value.selectedObjects || [];

        // 3. ENVIAMOS TODO AL BACKEND (Esto ahora sí hace el activeStep = "4")
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
            window.fbq('track', 'Personal Details WMC', { step: 'Personal Details' });
            break;
        case "2":
            window.fbq('track', 'Billing Information WMC', { step: 'Billing Information' });
            break;
        case "3":
            window.fbq('track', 'Courses or Visit WMC', { step: 'Courses or Tours' });
            break;
        case "4":
            window.fbq('track', 'Payment Process WMC', { step: 'Payment Process' });
            break;
    }
});

</script>

<template>
    <AppLayout class="bg-gradient-wmc">


        <!-- <div class="bg-slate-900 text-white p-6 rounded-xl my-6 text-xs border-l-4 border-blue-500 shadow-2xl">
            <h2 class="text-lg font-bold mb-4 text-blue-400 flex items-center gap-2">
                <i class="pi pi-search"></i> Lógica de Filtrado de Categoría
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-black/40 p-3 rounded-lg border border-white/10">
                    <p class="text-yellow-500 font-black uppercase mb-1">1. Entrada desde URL:</p>
                    <p>ID detectado (?category=): <span class="text-white text-sm font-mono bg-blue-900 px-2 rounded">{{
                            categoriaIdActual || 'Ninguno' }}</span></p>
                </div>

                <div class="bg-black/40 p-3 rounded-lg border border-white/10">
                    <p class="text-green-500 font-black uppercase mb-1">2. Resultado del Match:</p>
                    <p>Nombre: <span class="text-white text-sm">{{ categoria_seleccionada?.nombre_en || 'Buscando...'
                            }}</span></p>
                    <p>Precio base: <span class="text-white text-sm">${{ categoria_seleccionada?.precio || '0.00'
                            }}</span></p>
                </div>
            </div>

            <div class="mt-4">
                <p class="text-purple-400 font-black uppercase mb-1">3. Lista Completa de Categorías en Props:</p>
                <div class="overflow-auto max-h-40 bg-black/20 p-2 rounded">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-white/10 text-gray-400">
                                <th class="p-1">ID</th>
                                <th class="p-1">Nombre (EN)</th>
                                <th class="p-1">Match?</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="cat in Object.values(props.categorias)" :key="cat.id"
                                :class="{ 'bg-blue-500/20 text-blue-300': cat.id == categoriaIdActual }">
                                <td class="p-1 font-mono">{{ cat.id }}</td>
                                <td class="p-1">{{ cat.nombre_en }}</td>
                                <td class="p-1">
                                    <i v-if="cat.id == categoriaIdActual" class="pi pi-check-circle"></i>
                                    <span v-else>-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div> -->

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
                        <Step v-for="paso in pasosVisibles" :key="paso.value" :value="paso.value"
                            :class="{ 'pointer-events-none': activeStep !== paso.value }">
                            {{ paso.labelReal }}
                        </Step>
                    </StepList>
                    <StepPanels>
                        <!-- ========== Personal Details ==========
                         ==========================================  -->
                        <StepPanel v-slot="{ activateCallback }" value="1"
                            class="rounded-2xl border-2 border-green-iimp bg-white-price shadow-wmc">
                            <FormValidacionDoc ref="childFormValidacionDoc" :tipo_origen="tipo_origen"
                                :autores="autores" :perfil_id="props.perfil_id" />
                            <div
                                class="sticky bottom-0 left-0 w-full p-4 md:p-6 bg-white/95 backdrop-blur-md border-t border-gray-200 shadow-[0_-5px_20px_rgba(0,0,0,0.1)] z-[50] flex justify-end gap-3 rounded-b-2xl">

                                <Button label="Validate" icon="pi pi-arrow-right" iconPos="right"
                                    class="bg-degradient border-rounded-full" :loading="loading"
                                    :perfil_id="props.perfil_id" :disabled="
                                        // 1. Bloqueo si es categoría de socio y no es socio
                                        (childFormValidacionDoc?.esCategoriaDeSocio && childFormValidacionDoc?.hasSearched && !childFormValidacionDoc?.esSocio) ||
                                        // 2. NUEVO BLOQUEO: Si el correo no es válido para autores
                                        (childFormValidacionDoc?.correoEsValidoAutor === false)
                                        " @click="async () => {
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

                        <!-- ========== Billing Information ==========
                         ==========================================  -->
                        <StepPanel v-slot="{ activateCallback }" value="2"
                            class="rounded-2xl border-2 border-green-iimp bg-white shadow-wmc">

                            <FormInscription ref="childFormInscription" :data_persona="data_persona"
                                :cupones="props.cupones" :activarModal="mostrarModalFacturacion"
                                :categorias="props.categorias" />

                            <div
                                class="sticky bottom-0 left-0 w-full p-4 md:p-6 bg-white/95 backdrop-blur-md border-t border-gray-200 shadow-[0_-5px_20px_rgba(0,0,0,0.1)] z-[50] flex justify-between gap-3 rounded-b-2xl">
                                <Button label="Back" severity="secondary" icon="pi pi-arrow-left"
                                    class="flex-1 md:flex-none" @click="activateCallback('1')" />
                                <Button label="Register & Pay" iconPos="right" icon="pi pi-arrow-right"
                                    class="bg-degradient border-rounded-full flex-1 md:flex-none" :loading="loading"
                                    @click="handleInscripcionHaciaCursos" />
                            </div>
                        </StepPanel>

                        <!-- ========== Courses or Tours ==========
                         ==========================================  -->
                        <StepPanel v-if="!saltoCursos" v-slot="{ activateCallback }" value="3"
                            class="rounded-2xl border-2 border-green-iimp bg-white shadow-wmc">

                            <FormTourCourse ref="childFormTourCourse" :data_persona="data_persona"
                                :adicionales="props.adicionales" :section="sectionUrl" :course="props.course" />

                            <div
                                class="sticky bottom-0 left-0 w-full p-4 md:p-6 bg-white/95 backdrop-blur-md border-t border-gray-200 shadow-[0_-5px_20px_rgba(0,0,0,0.1)] z-[50] flex justify-between gap-3 rounded-b-2xl">
                                <!-- <Button label="Back" severity="secondary" icon="pi pi-arrow-left"
                                    class="flex-1 md:flex-none p-3 font-bold" @click="activateCallback('2')" /> -->
                                <Button label="Back" severity="secondary" icon="pi pi-arrow-left"
                                    class="flex-1 md:flex-none p-3 font-bold" @click="activeStep = '2'" />
                                <Button label="Continue to Billing" iconPos="right" icon="pi pi-arrow-right"
                                    class="bg-degradient border-rounded-full flex-1 md:flex-none" :loading="loading"
                                    @click="handleFinalizarTodo" />
                            </div>
                        </StepPanel>

                        <!-- ========== Payment Process ==========
                         ==========================================  -->
                        <StepPanel v-slot="{ activateCallback }" value="4"
                            class="rounded-2xl border-2 border-green-iimp bg-white shadow-wmc">

                            <FormPayment ref="childFormPayment" :data_persona="data_persona"
                                :formulario="formDataPayment" :categoria_seleccionada="categoria_seleccionada"
                                :descuento="formDataPayment?.descuento" :extras_seleccionados="extras_para_mostrar"
                                :datos_facturacion="tempResIns?.formInscription" :tipo_origen="tipo_origen" />

                            <div
                                class="sticky bottom-0 left-0 w-full p-4 md:p-6 bg-white/95 backdrop-blur-md border-t border-gray-200 shadow-[0_-5px_20px_rgba(0,0,0,0.1)] z-[50] flex justify-between gap-3 rounded-b-2xl">
                                <!-- <Button label="Back" severity="secondary" icon="pi pi-arrow-left"
                                    @click="activateCallback('3')" /> -->
                                <Button label="Back" severity="secondary" icon="pi pi-arrow-left" @click="() => {
                                    if (saltoCursos) {
                                        activeStep = '2'; // Si no hay cursos visibles, regresa a Billing
                                    } else {
                                        activeStep = '3'; // Si el usuario eligió cursos, regresa a Cursos
                                    }
                                }" />
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

        <!-- REQUERIMIENTOS MODAL =======
         ============================== -->
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

        <!-- CONFIRM VIAJES Y CURSOS =======
         ============================== -->
        <!-- <Dialog v-model:visible="showConfirmNoExtrasModal" modal :showHeader="false" :closable="false"
            :style="{ width: '550px' }" class="rounded-3xl overflow-hidden border-none shadow-2xl animate-modal-entry">

            <div class="p-0 relative overflow-hidden">
                <div
                    class="bg-gradient-to-r from-blue-900 via-blue-700 to-blue-900 p-8 text-center relative overflow-hidden">
                    <div class="absolute inset-0 shine-effect"></div>

                    <div class="relative z-10">
                        <div
                            class="w-20 h-20 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-4 backdrop-blur-md border border-white/20 animate-bounce-slow">
                            <i
                                class="pi pi-sparkles text-yellow-400 text-4xl drop-shadow-[0_0_15px_rgba(250,204,21,0.6)]"></i>
                        </div>
                        <h3 class="text-2xl font-black text-white uppercase tracking-tighter italic">
                            Upgrade Your Registration
                        </h3>
                        <div class="h-1 w-20 bg-yellow-400 mx-auto mt-2 rounded-full"></div>
                    </div>
                </div>

                <div class="p-10 bg-white text-center">
                    <p class="text-slate-700 text-xl leading-tight font-bold mb-4">
                        ¿Deseas agregar <span class="text-blue-700">Cursos Cortos</span> o <span
                            class="text-blue-700">Visitas Técnicas</span> a tu registro para mejorar tu experiencia?
                    </p>

                    <p class="text-slate-600 text-sm leading-relaxed mb-6">
                        Aprovecha esta oportunidad única para especializarte con expertos globales y conocer de cerca
                        las operaciones mineras más importantes del Perú.
                    </p>

                    <p
                        class="text-blue-500 text-xs font-black uppercase tracking-[0.1em] bg-blue-50 p-4 rounded-2xl border border-blue-100">
                        "La excelencia se alcanza con capacitación constante y experiencia en campo."
                    </p>

                    <div class="mt-8 flex flex-col gap-4">
                        <button @click="handleIrACursosDesdeModal"
                            class="group relative w-full py-4 px-6 rounded-2xl bg-blue-900 text-white font-black uppercase tracking-widest overflow-hidden transition-all hover:scale-[1.02] active:scale-95 shadow-[0_10px_20px_rgba(30,58,138,0.3)]">
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:animate-shine-fast">
                            </div>
                            <span class="relative flex items-center justify-center gap-3">
                                <i class="pi pi-plus-circle"></i>
                                ¡Sí, quiero agregarlos ahora!
                            </span>
                        </button>

                        <button @click="handleSaltarCursosEIrAPago"
                            class="w-full py-2 text-slate-400 text-[10px] font-black uppercase tracking-[0.2em] hover:text-red-500 transition-colors duration-300">
                            No por ahora, proceder con el pago básico
                        </button>
                    </div>
                </div>
            </div>
        </Dialog> -->

        <Dialog v-model:visible="showConfirmNoExtrasModal" modal :showHeader="false" :closable="false"
            :style="{ width: '550px' }" class="rounded-3xl overflow-hidden border-none shadow-2xl animate-modal-entry">

            <div class="p-0 relative overflow-hidden">
                <div
                    class="bg-gradient-to-r from-blue-900 via-blue-700 to-blue-900 p-8 text-center relative overflow-hidden">
                    <div class="absolute inset-0 shine-effect"></div>

                    <div class="relative z-10">
                        <div
                            class="w-20 h-20 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-4 backdrop-blur-md border border-white/20 animate-bounce-slow">
                            <i
                                class="pi pi-sparkles text-yellow-400 text-4xl drop-shadow-[0_0_15px_rgba(250,204,21,0.6)]"></i>
                        </div>
                        <h3 class="text-2xl font-black text-white uppercase tracking-tighter italic">
                            Upgrade Your Registration
                        </h3>
                        <div class="h-1 w-20 bg-yellow-400 mx-auto mt-2 rounded-full"></div>
                    </div>
                </div>

                <div class="p-10 bg-white text-center">
                    <p class="text-slate-700 text-xl leading-tight font-bold mb-4">
                        Would you like to add <span class="text-blue-700">Short Courses</span> or <span
                            class="text-blue-700">Technical Visits</span> to enhance your experience?
                    </p>

                    <p class="text-slate-600 text-sm leading-relaxed mb-6">
                        Don't miss this unique opportunity to specialize with global experts and get an exclusive
                        on-site look at the most significant mining operations in Peru.
                    </p>

                    <p
                        class="text-blue-500 text-xs font-black uppercase tracking-[0.1em] bg-blue-50 p-4 rounded-2xl border border-blue-100">
                        "Excellence is achieved through continuous training and hands-on field experience."
                    </p>

                    <div class="mt-8 flex flex-col gap-4">
                        <button @click="handleIrACursosDesdeModal"
                            class="group relative w-full py-4 px-6 rounded-2xl bg-blue-900 text-white font-black uppercase tracking-widest overflow-hidden transition-all hover:scale-[1.02] active:scale-95 shadow-[0_10px_20px_rgba(30,58,138,0.3)]">
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:animate-shine-fast">
                            </div>
                            <span class="relative flex items-center justify-center gap-3">
                                <i class="pi pi-plus-circle"></i>
                                Yes, I want to add them now!
                            </span>
                        </button>

                        <button @click="handleSaltarCursosEIrAPago"
                            class="w-full py-2 text-slate-400 text-[10px] font-black uppercase tracking-[0.2em] hover:text-red-500 transition-colors duration-300">
                            Not for now, proceed to standard checkout
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

/* :deep(.p-step) {
    cursor: not-allowed;
} */
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
