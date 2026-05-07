<script setup>
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { usePage, router } from '@inertiajs/vue3';
import { ref, onMounted, computed, watch, nextTick, onUnmounted } from 'vue';
import { useForm } from 'vee-validate';
import * as yup from 'yup';
import Functions from '@/Functions';
import { useToast } from 'primevue/usetoast';
import Card from 'primevue/card';
import Calendar from 'primevue/calendar';
import InputGroup from 'primevue/inputgroup';
import InputGroupAddon from 'primevue/inputgroupaddon';
import Button from 'primevue/button';
import axios from 'axios';

import "../../../css/inscripciones.css";

const toast = useToast();
const page = usePage();

const generos = ref([{ label: 'Male', value: 'M' }, { label: 'Female', value: 'F' }]);
const paises = ref(page.props.general.paises || []);
const departamentos = ref([]);
const loadingSearch = ref(false);
const today = new Date();
const esSocio = ref(false);
const hasSearched = ref(false);
const noEncontrado = ref(false);
const alphanumericMessage = ref('');
const mensajeErrorAutor = ref('');
const correoEsValidoAutor = ref(true);

const props = defineProps({
    saved_values: Object,
    tipo_origen: Number,
    perfil_id: Number,
    autores: Array
});


const dniMessage = ref('');
const schema = yup.object({
    tipo_doc: yup.mixed().required('Document type is required'),
    documento: yup.string()
        .required('Document number is required')
        .test('len', 'DNI must be exactly 8 digits', (val, context) => {
            // Solo aplicamos la regla de 8 dígitos si el tipo de doc es DNI (ID 1)
            if (context.parent.tipo_doc === 1) {
                return val?.length === 8;
            }
            return true; // Para otros documentos, no aplicamos esta restricción
        }),
    nombres: yup.string().required('First Name is required'),
    apellido_paterno: yup.string().required('Last Name is required'),
    pais: yup.mixed().required('Country is required'),
    direccionPersona: yup.string().required('Address is required'),
    correo: yup.string().email().required('Email is required'),
    // celular: yup.string()
    //     .matches(/^\+?[0-9]*$/, 'Only numbers are allowed (the + at the beginning is optional)')
    //     .required('Phone is required'),
    celular: yup.string()
        .matches(/^\+?[0-9\s-]*$/, 'Only numbers, spaces, and hyphens are allowed (the + at the beginning is optional)')
        .required('Phone is required'),
    sexo: yup.string().required('Gender is required'),
    fecha_nacimiento: yup.date().required('Date of Birth is required')
        .nullable()
        .test('is-18', 'You must be at least 18 years old', (value) => {
            if (!value) return false;

            const today = new Date();
            const birthDate = new Date(value);
            let age = today.getFullYear() - birthDate.getFullYear();
            const m = today.getMonth() - birthDate.getMonth();

            // Ajuste si el mes actual es menor al de nacimiento
            // o si es el mismo mes pero el día actual es menor al de nacimiento
            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }

            return age >= 18;
        })
});

const { defineField, errors, values, setValues, validate } = useForm({
    validationSchema: schema,
    initialValues: props.saved_values || {}
});

const [tipo_doc, tipo_docAttrs] = defineField('tipo_doc');
const [documento, documentoAttrs] = defineField('documento');
const [nombres, nombresAttrs] = defineField('nombres');
const [apellido_paterno, apellido_paternoAttrs] = defineField('apellido_paterno');
const [pais, paisAttrs] = defineField('pais');
const [direccionPersona, direccionPersonaAttrs] = defineField('direccionPersona');
const [correo, correoAttrs] = defineField('correo');
const [celular, celularAttrs] = defineField('celular');
const [empresa, empresaAttrs] = defineField('empresa');
const [fecha_nacimiento, fecha_nacimientoAttrs] = defineField('fecha_nacimiento');
const [sexo, sexoAttrs] = defineField('sexo');

const fieldNames = {
    tipo_doc: 'Document Type',
    documento: 'Document Number',
    nombres: 'First Name',
    apellido_paterno: 'Last Name',
    pais: 'Country',
    direccionPersona: 'Address',
    correo: 'Email',
    celular: 'Phone',
    sexo: 'Gender',
    fecha_nacimiento: 'Date of Birth'
};

const missingFields = computed(() => {
    return Object.keys(errors.value).map(key => fieldNames[key] || key);
});

const searchPerson = async () => {
    if (!tipo_doc.value || !documento.value) {
        toast.add({ severity: 'warn', summary: 'Warning', detail: 'Please enter document type and number', life: 3000 });
        return;
    }

    loadingSearch.value = true;

    // 1. IMPORTANTE: Reseteamos estados para que no se vean banners viejos
    hasSearched.value = false;
    noEncontrado.value = false;
    esSocio.value = false;

    try {
        const response = await axios.post('/api/getperson', {
            id_tipo_documento: tipo_doc.value,
            numero_documento: documento.value
        });

        const data = response.data;

        if (data.status && data.persona) {
            // 2. Definimos 'p' PRIMERO
            const p = data.persona;

            // 3. Ahora sí podemos usar 'p'
            esSocio.value = p.es_socio;
            noEncontrado.value = false;

            const paisAsignado = props.tipo_origen === 1 ? 75 : (p.pais || p.id_pais);

            if (p.pais || p.id_pais) {
                pais.value = p.pais || p.id_pais;
                await loadDepartamentos();
            }

            setValues({
                tipo_doc: p.id_tipo_documento,
                documento: p.documento,
                nombres: p.nombres || '',
                apellido_paterno: p.apellido_paterno || '',
                correo: p.correo || '',
                celular: p.celular || '',
                direccionPersona: p.direccionPersona || p.direccion?.direccion || '',
                empresa: p.empresa_nombre || p.empresa || '',
                pais: p.pais || p.id_pais || paisAsignado,
                sexo: p.sexo || '',
                // fecha_nacimiento: p.fecha_nacimiento ? new Date(p.fecha_nacimiento) : null
                fecha_nacimiento: p.fecha_nacimiento ? new Date(p.fecha_nacimiento.replace(/-/g, '\/')) : null
            });

            if (props.autores && props.autores.length > 0) {
                const existeEnAutores = props.autores.some(a =>
                    a.autor_correo.toLowerCase().trim() === p.correo?.toLowerCase().trim()
                );

                if (!existeEnAutores) {
                    mensajeErrorAutor.value = "The email associated with this ID (" + p.correo + ") is not registered in the Authors list.";
                    correoEsValidoAutor.value = false;
                    loadingSearch.value = false;
                    hasSearched.value = true; // Mostramos error de autor
                    return;
                }
            }

            // 4. RECIÉN AQUÍ activamos el éxito para que los banners se actualicen de una sola vez
            correoEsValidoAutor.value = true;
            hasSearched.value = true;

            toast.add({ severity: 'success', summary: 'Found', detail: 'Information loaded successfully', life: 3000 });

        } else {
            // Lógica para cuando NO se encuentra la persona
            noEncontrado.value = true;
            esSocio.value = props.tipo_origen === 2; // true si es extranjero

            setValues({
                tipo_doc: tipo_doc.value,
                documento: documento.value,
                nombres: '',
                apellido_paterno: '',
                correo: '',
                celular: '',
                direccionPersona: '',
                empresa: '',
                sexo: '',
                fecha_nacimiento: null
            });

            hasSearched.value = true; // Mostramos banner de "No encontrado"
            toast.add({ severity: 'info', summary: 'Not Found', detail: 'No record found. Please fill manually.', life: 3000 });
        }
    } catch (error) {
        console.error("Search error:", error);
        hasSearched.value = false;
    } finally {
        loadingSearch.value = false;
    }
};

const onlyNumberKey = (event) => {
    const charCode = event.which ? event.which : event.keyCode;

    // 1. Permitir teclas de control (Backspace, Delete, Tab, flechas)
    if ([8, 46, 9, 37, 39].includes(charCode)) return true;

    // 2. Permitir combinaciones de teclas (Ctrl+A, Ctrl+C, Ctrl+V)
    if (event.ctrlKey || event.metaKey) return true;

    if (tipo_doc.value == 1) {
        // 3. Si hay una selección de texto (el usuario seleccionó todo para borrar/sobrescribir)
        // permitimos la entrada porque la selección será reemplazada.
        const selection = window.getSelection().toString();
        if (selection.length > 0 && selection.length === documento.value?.length) {
            return true;
        }

        // 4. Bloquear si no es número
        if (charCode < 48 || charCode > 57) {
            event.preventDefault();
            return false;
        }

        // 5. Bloquear si ya llegó a 8 dígitos Y MOSTRAR MENSAJE
        if (documento.value?.length >= 8) {
            dniMessage.value = "Solo se permiten 8 dígitos";

            // Limpiar el mensaje automáticamente después de 3 segundos
            setTimeout(() => {
                dniMessage.value = '';
            }, 3000);

            event.preventDefault();
            return false;
        } else {
            dniMessage.value = ''; // Limpiar si el usuario borra y vuelve a tener espacio
        }
    }
    return true;
};

const maxAdultDate = computed(() => {
    const date = new Date();
    date.setFullYear(date.getFullYear() - 18);
    return date;
});

const mostrarBannerBloqueo = computed(() => {
    // Si ya buscó y NO es perfil 1 o 5, el banner debe desaparecer
    if (hasSearched.value && ![1, 5].includes(props.perfil_id)) {
        return false;
    }
    // De lo contrario, sigue las reglas normales de bloqueo
    return camposBloqueados.value;
});

const camposBloqueados = computed(() => {
    // 1. Condición de búsqueda (Solo para peruanos que no han buscado aún)
    const faltaBuscarPeruano = esPeruano.value && !hasSearched.value;

    // 2. Condición de Perfil Crítico (Bloqueo TOTAL e incondicional para perfil 1 o 5)
    const esPerfilBloqueado = [1, 5].includes(props.perfil_id);

    // Si cualquiera de las dos es verdadera, el campo se bloquea
    return faltaBuscarPeruano || esPerfilBloqueado;
});

const esCampoBloqueado = (valorCampo) => {
    // 1. Si es peruano y no ha buscado, todo bloqueado
    if (esPeruano.value && !hasSearched.value) return true;

    // 2. Si es perfil crítico (1 o 5)
    if ([1, 5].includes(props.perfil_id)) {
        // BLOQUEA solo si el campo NO está vacío (tiene contenido previo)
        // Usamos trim() para evitar espacios en blanco
        return valorCampo !== null && valorCampo !== undefined && String(valorCampo).trim() !== '';
    }

    return false;
};

const tiposDocumentoFiltrados = computed(() => {
    const todos = page.props.general.tipDocPer || [];
    if (props.tipo_origen === 1) {
        return todos.filter(d => d.id === 1 || d.id === 2);
    } else if (props.tipo_origen === 2) {
        return todos.filter(d => d.id !== 1 && d.id !== 2);
    }
    return todos;
});

const esPeruano = computed(() => props.tipo_origen === 1);

const loadDepartamentos = async () => {
    if (pais.value) {
        try {
            const res = await axios.post(route('padre.departamentos'), { id: pais.value });
            departamentos.value = res.data.departamentos;
        } catch (e) {
            console.error("Error cargando departamentos", e);
        }
    }
};

const esCategoriaDeSocio = computed(() => {
    // CAMBIO CLAVE: Si es extranjero (2), NUNCA bloqueamos por socio
    if (props.tipo_origen === 2) return false;

    const urlParams = new URLSearchParams(window.location.search);
    const categoryId = urlParams.get('category');
    const categoriasSocio = ['35', '29'];
    return categoriasSocio.includes(categoryId);
});

const getValidacionDoc = async () => {
    const result = await validate();

    // Validamos socio solo para las categorías que lo exigen Y si no es extranjero
    if (esCategoriaDeSocio.value) {
        if (!hasSearched.value || !esSocio.value) {
            return { validate: false };
        }
    }

    if (result.valid) {
        return {
            validate: true,
            formValidacionDoc: values
        };
    } else {
        console.log("Errores:", errors.value);
        return { validate: false };
    }
}

defineExpose({
    getValidacionDoc,
    esSocio,
    hasSearched,
    esCategoriaDeSocio,
    correoEsValidoAutor
});

const onlyPhoneKeys = (event) => {
    const charCode = event.charCode ? event.charCode : event.keyCode;
    const charStr = String.fromCharCode(charCode);
    if (charStr === '+' && event.target.selectionStart === 0 && !celular.value?.includes('+')) {
        return true;
    }
    if (charCode >= 48 && charCode <= 57) {
        return true;
    }
    event.preventDefault();
    return false;
};

const goToHome = () => {
    // Solo preguntamos si hay algo escrito (por ejemplo, si ya puso su documento)
    if (documento.value) {
        const confirmacion = confirm("Are you sure you want to leave? All progress in this registration will be lost.");
        if (confirmacion) {
            router.get('/');
        }
    } else {
        router.get('/');
    }
};

const clearDocument = async () => {
    // Siempre limpiamos el número de documento al cambiar el tipo
    documento.value = "";

    if (props.tipo_origen === 1) {
        // --- FLUJO PERUANO: Blanqueo total porque los datos dependen del DNI ---
        hasSearched.value = false;
        setValues({
            nombres: '',
            apellido_paterno: '',
            empresa: '',
            correo: '',
            celular: '',
            direccionPersona: '',
            sexo: '',
            fecha_nacimiento: null
        });
    } else {
        // --- FLUJO EXTRANJERO: NO blanqueamos detalles personales ---
        // Solo aseguramos que los campos sigan editables
        hasSearched.value = true;

        // Mantenemos los valores actuales del formulario (nombres, correo, etc.)
        // y solo reseteamos el documento que ya hicimos arriba.
    }
};


const onlyAlphanumericKey = (event) => {
    const charCode = event.which ? event.which : event.keyCode;
    const charStr = String.fromCharCode(charCode);

    // 1. Permitir teclas de control
    if ([8, 9, 13, 27, 37, 38, 39, 40].includes(charCode)) return true;

    // 2. Validar Alfanumérico
    if (!/^[a-zA-Z0-9]+$/.test(charStr)) {
        event.preventDefault();
        alphanumericMessage.value = "Only letters and numbers are allowed (no spaces or symbols)";

        setTimeout(() => {
            alphanumericMessage.value = '';
        }, 3000);

        return false;
    }
    return true;
};

const handleAutorChange = (event) => {
    const data = event.value; // Aquí viene todo el objeto del autor/trabajo
    if (data) {
        setValues({
            ...values,
            nombres: data.autor_nombre || '',
            apellido_paterno: '', // El excel no separa apellidos, podrías ponerlo todo en nombres o procesarlo
            correo: data.autor_correo || '',
            celular: data.autor_celular || '',
            empresa: data.autor_empresa || '',
            // Puedes agregar más campos si los tienes
        });

        // Marcamos como buscado para que se desbloqueen los campos
        hasSearched.value = true;

        toast.add({
            severity: 'info',
            summary: 'Data Loaded',
            detail: `Information for ${data.correlativo} loaded`,
            life: 3000
        });
    }
};

// Creamos estados individuales para los campos principales
const bloqueoNombres = computed(() => esCampoBloqueado(nombres.value));
const bloqueoApellidos = computed(() => esCampoBloqueado(apellido_paterno.value));
const bloqueoCorreo = computed(() => esCampoBloqueado(correo.value));
const bloqueoCelular = computed(() => esCampoBloqueado(celular.value));
const bloqueoFechaNac = computed(() => esCampoBloqueado(fecha_nacimiento.value));
const bloqueoSexo = computed(() => esCampoBloqueado(sexo.value));
const bloqueoDireccion = computed(() => esCampoBloqueado(direccionPersona.value));

// EXTREMA SEGURIDAD: Watcher para limpiar si pegan texto con símbolos
watch(documento, (newValue) => {
    if (tipo_doc.value !== 1 && newValue) { // Solo si NO es DNI
        const cleaned = newValue.replace(/[^a-zA-Z0-9]/g, '');
        if (cleaned !== newValue) {
            documento.value = cleaned;
            alphanumericMessage.value = "Special characters were removed";
            setTimeout(() => { alphanumericMessage.value = ''; }, 3000);
        }
    }
});
// UNICO WATCHER PARA TIPO_ORIGEN - BLINDADO
watch(() => props.tipo_origen, async (newOrigen) => {
    // Si el origen no es 1 ni 2, no hacemos nada para evitar resets accidentales
    if (!newOrigen) return;

    if (newOrigen === 1) {
        // --- LÓGICA NACIONAL (DNI) ---
        tipo_doc.value = 1; // Forzamos ID 1 (DNI)
        pais.value = 75;    // Forzamos ID 75 (Perú)
        await loadDepartamentos();

        // Sincronizamos vee-validate de forma manual para asegurar el tiro
        setValues({
            ...values,
            pais: 75,
            tipo_doc: 1
        });

        hasSearched.value = false;

    } else if (newOrigen === 2) {
        // --- LÓGICA INTERNACIONAL (EXTRANJERO) ---
        // Solo cambiamos a Passport si el documento actual es DNI
        if (tipo_doc.value === 1 || !tipo_doc.value) {
            tipo_doc.value = 3; // O el ID que corresponda a Pasaporte en tu BD
        }

        pais.value = null;
        esSocio.value = true;
        hasSearched.value = true;

        await nextTick();
        validate();
    }
}, { immediate: true });

watch(correo, (newCorreo) => {
    if (props.autores && props.autores.length > 0 && newCorreo) {
        const esAutor = props.autores.some(a =>
            a.autor_correo.toLowerCase().trim() === newCorreo.toLowerCase().trim()
        );

        if (!esAutor) {
            mensajeErrorAutor.value = "This email is not registered as a valid Author for WMC 2026.";
            correoEsValidoAutor.value = false;
        } else {
            mensajeErrorAutor.value = '';
            correoEsValidoAutor.value = true;
        }
    }
});

onMounted(() => {

    if (esPeruano.value) {
        tipo_doc.value = 1;
        pais.value = 75;
    } else {
        // Si es extranjero, pon el ID de Passport por defecto, ej: 3
        tipo_doc.value = 5;
    }

    console.log("Lista de Autores recibida:", props.autores);
});



</script>

<template>
    <div class="font-bold p-4 relative">
        <div class="flex justify-end pr-2 mb-4">
            <Button @click="goToHome" class="wmc-btn-international shadow-xl flex items-center">
                <i class="pi pi-home mr-3 text-lg"></i>
                <div class="flex flex-col items-start leading-none">
                    <span class="text-xs font-bold uppercase tracking-[0.3em] ml-4">Main Menu</span>
                </div>
            </Button>
        </div>

        <Card class="mt-5 overflow-hidden shadow-lg border border-gray-200">
            <template #header>
                <div class="w-full py-3 text-xl font-bold text-center bg-lightblue-wmc border-blue-wmc">
                    {{ esPeruano ? 'Search' : 'Identification' }}
                </div>
            </template>
            <template #content>
                <div class="w-full px-4 pb-4">
                    <!-- <div v-if="hasSearched && esSocio && !noEncontrado && esCategoriaDeSocio"
                        class="flex items-center p-4 mb-4 text-green-800 border-t-4 border-green-300 bg-green-50 rounded-lg"
                        role="alert">
                        <i class="pi pi-check-circle mr-2 text-xl"></i>
                        <div class="text-sm font-medium">Verification successful. You are an <strong>Active
                                Member</strong>.</div>
                    </div> -->
                    <div class="flex items-start p-3 mb-5 bg-blue-50 border border-blue-200 rounded-lg shadow-sm">
                        <i class="pi pi-shield text-blue-600 text-2xl mr-3 mt-1"></i>
                        <div>
                            <span class="block text-sm font-bold text-blue-900 mb-1">
                                Data Protection Guaranteed
                            </span>
                            <p class="text-[11px] text-blue-800 leading-relaxed pr-2">
                                All information provided is <b>securely encrypted and transmitted</b>.
                                Your personal data is strictly protected under International Data Privacy Laws
                                (including GDPR) and will be used
                                <strong>exclusively</strong> for your registration and participation in the World Mining
                                Congress 2026.
                            </p>
                        </div>
                    </div>
                    <div v-if="hasSearched && esSocio && !noEncontrado && esCategoriaDeSocio"
                        class="flex flex-col p-4 mb-4 text-green-800 border-t-4 border-green-300 bg-green-50 rounded-lg"
                        role="alert">

                        <div class="flex items-center">
                            <i class="pi pi-check-circle mr-2 text-xl"></i>
                            <div class="text-sm font-medium">
                                Verification successful. You are an <strong>Active Member</strong>.
                            </div>
                        </div>

                        <div v-if="[1, 5].includes(props.perfil_id)" class="mt-3 ml-7 pt-3 border-t border-green-200">
                            <p class="text-xs leading-relaxed">
                                If you wish to <strong>edit your personal details</strong>, please contact our Associate
                                Coordinator:
                            </p>
                            <div class="mt-2 flex flex-col sm:flex-row sm:gap-6 text-xs italic">
                                <span class="font-bold text-green-900">
                                    <i class="pi pi-user mr-1"></i> Liset Otoya
                                </span>
                                <span>
                                    <i class="pi pi-whatsapp mr-1"></i>
                                    <a href="https://wa.me/51982097019" target="_blank" class="hover:underline">+51 982
                                        097 019</a>
                                </span>
                                <span>
                                    <i class="pi pi-envelope mr-1"></i>
                                    <a href="mailto:Liset.otoya@iimp.org.pe"
                                        class="hover:underline">Liset.otoya@iimp.org.pe</a>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="hasSearched && !esSocio && esCategoriaDeSocio"
                        class="flex flex-col p-4 mb-4 text-red-800 border-t-4 border-red-300 bg-red-50 rounded-lg"
                        role="alert">
                        <div class="flex items-center">
                            <i class="pi pi-exclamation-triangle mr-2 text-xl"></i>
                            <span class="text-sm font-bold">You are not a member.</span>
                        </div>
                        <div class="mt-2 text-sm">
                            This category is exclusive for members. Please contact <a
                                href="mailto:asociados@iimp.org.pe"
                                class="font-bold underline">asociados@iimp.org.pe</a> or change your category.
                        </div>
                    </div>

                    <div v-if="hasSearched && noEncontrado"
                        class="flex items-center p-4 mb-4 text-blue-800 border-t-4 border-blue-300 bg-blue-50 rounded-lg"
                        role="alert">
                        <i class="pi pi-info-circle mr-2 text-xl"></i>
                        <div class="text-sm font-medium">No records found. <strong>Please complete your details manually
                                in the form below.</strong></div>
                    </div>
                </div>

                <!-- <div v-if="camposBloqueados"
                    class="mx-6 mb-2 p-2 bg-yellow-50 text-yellow-700 border-l-4 border-yellow-400 text-xs font-semibold">
                    <i class="pi pi-lock mr-2"></i> PLEASE SEARCH BY DOCUMENT NUMBER TO UNLOCK THESE FIELDS
                </div> -->

                <div v-if="mostrarBannerBloqueo"
                    class="mx-6 mb-2 p-2 bg-yellow-50 text-yellow-700 border-l-4 border-yellow-400 text-xs font-semibold">
                    <i class="pi pi-lock mr-2"></i>
                    <span v-if="[1, 5].includes(props.perfil_id)">
                        YOUR DATA IS PRE-LOADED BY YOUR PROFILE AND CANNOT BE MODIFIED.
                    </span>
                    <span v-else>
                        PLEASE SEARCH BY DOCUMENT NUMBER TO UNLOCK THESE FIELDS.
                    </span>
                </div>
                <div class="flex gap-6 p-2 w-full justify-around">
                    <div
                        class="text-green-iimp font-bold max-w-[650px] w-full p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="col-span-1">
                            <label for="tipo_doc">Document Type <span class="text-red-600">*</span></label>
                            <Select name="tipo_doc" v-model="tipo_doc" v-bind="tipo_docAttrs" translate="no"
                                :options="tiposDocumentoFiltrados" @change="clearDocument" optionLabel="name_en"
                                optionValue="id" placeholder="Select Document" showClear checkmark
                                class="w-full border-green-iimp" :disabled="esPeruano"
                                :class="{ 'bg-gray-100 opacity-70': esPeruano }" />
                            <small class="text-red-600">{{ errors.tipo_doc }}</small>
                        </div>
                        <div class="col-span-1">
                            <label for="documento" translate="no">
                                {{ esPeruano ? 'Document Number' : 'Identity Card / Passport Number' }} <span
                                    class="text-red-600">*</span>
                            </label>
                            <!-- PERUANO -->
                            <InputGroup v-if="esPeruano">
                                <!-- <InputText name="documento" v-model="documento" v-bind="documentoAttrs"
                                    class="w-full border-green-iimp" @keypress="onlyNumberKey" :maxlength="25" /> -->
                                <InputText name="documento" v-model="documento" v-bind="documentoAttrs"
                                    class="w-full border-green-iimp" @keypress="onlyNumberKey" :maxlength="8"
                                    :disabled="loadingSearch" />
                                <Button icon="pi pi-search" severity="info" @click="searchPerson"
                                    :loading="loadingSearch" />
                            </InputGroup>
                            <!-- EXTRANJERO -->
                            <InputText v-else name="documento" v-model="documento" v-bind="documentoAttrs"
                                class="w-full border-green-iimp" :maxlength="15" placeholder="Enter number"
                                @keypress="onlyAlphanumericKey" />
                            <small v-if="dniMessage" class="text-orange-600 font-bold block mt-1">
                                <i class="pi pi-info-circle mr-1"></i> {{ dniMessage }}
                            </small>
                            <div v-if="alphanumericMessage" class="text-orange-600 font-bold block mt-1 animate-pulse">
                                <i class="pi pi-exclamation-triangle mr-1"></i> {{ alphanumericMessage }}
                            </div>
                            <small v-else class="text-red-600 block mt-1">{{ errors.documento }}</small>
                        </div>
                    </div>
                </div>
            </template>
        </Card>
        <!--PERSONAL DATAILS/
        /==============================  -->
        <Card class="mt-5 overflow-hidden shadow-lg border border-gray-200">
            <template #header>
                <div class="w-full py-3 text-xl font-bold text-center bg-lightblue-wmc border-blue-wmc">Personal Details
                </div>
            </template>
            <template #content>
                <div class="p-2">
                    <div class="w-full px-4 pb-4">
                        <div v-if="missingFields.length > 0"
                            class="flex flex-col p-4 mb-4 text-orange-800 border-t-4 border-orange-300 bg-orange-50 rounded-lg"
                            role="alert">
                            <div class="flex items-center">
                                <i class="pi pi-exclamation-circle mr-2 text-xl"></i>
                                <span class="text-sm font-bold">Required Information Missing</span>
                            </div>
                            <div class="mt-2 text-sm">
                                Please complete the following fields to proceed:
                                <ul class="list-disc ml-5 mt-1">
                                    <li v-for="field in missingFields" :key="field">{{ field }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-6 m-6 md:grid-cols-2">
                        <div class="w-full">
                            <label for="nombres">First Name <span class="text-red-600">*</span></label>
                            <InputText name="nombres" v-model="nombres" v-bind="nombresAttrs" :disabled="bloqueoNombres"
                                class="w-full border-green-iimp" />
                            <small class="text-red-600">{{ errors.nombres }}</small>
                        </div>
                        <div class="w-full">
                            <label for="apellido_paterno">Last Name <span class="text-red-600">*</span></label>
                            <InputText name="apellido_paterno" v-model="apellido_paterno" v-bind="apellido_paternoAttrs"
                                :disabled="bloqueoApellidos" class="w-full border-green-iimp" />
                            <small class="text-red-600">{{ errors.apellido_paterno }}</small>
                        </div>
                    </div>

                    <div class="grid gap-6 m-6 grid-cols-1 md:grid-cols-4">
                        <div class="w-full md:col-span-2">
                            <label for="pais">Country <span class="text-red-600">*</span></label>
                            <Select name="pais" v-model="pais" v-bind="paisAttrs" :options="paises" optionLabel="name"
                                optionValue="id" placeholder="Select" showClear filter @change="loadDepartamentos"
                                translate="no" :disabled="esPeruano" class="w-full border-green-iimp" />
                            <small class="text-red-600">{{ errors.pais }}</small>
                        </div>
                        <div class="w-full md:col-span-2">
                            <label for="direccionPersona">Address <span class="text-red-600">*</span></label>
                            <InputText name="direccionPersona" v-model="direccionPersona" v-bind="direccionPersonaAttrs"
                                :disabled="bloqueoDireccion" class="w-full border-green-iimp" />
                            <small class="text-red-600">{{ errors.direccionPersona }}</small>
                        </div>
                    </div>

                    <div class="grid gap-6 m-6 md:grid-cols-3">
                        <!-- <div class="w-full">
                            <label for="correo" class="">Email Address <span
                                    class="font-normal text-red-600">*</span></label>
                            <InputText name="correo" v-model="correo" v-bind="correoAttrs" :disabled="bloqueoCorreo"
                                class="w-full border-green-iimp" />
                            <span class="font-normal text-red-600">{{ errors.correo }}</span>
                        </div> -->
                        <div class="w-full">
                            <label for="correo">Email Address <span class="text-red-600">*</span></label>
                            <InputText name="correo" v-model="correo" v-bind="correoAttrs" :disabled="bloqueoCorreo"
                                class="w-full border-green-iimp"
                                :class="{ 'border-red-500 bg-red-50': mensajeErrorAutor }" />
                            <small v-if="mensajeErrorAutor" class="text-red-600 font-bold block mt-1 animate-pulse">
                                <i class="pi pi-times-circle mr-1"></i> {{ mensajeErrorAutor }}
                            </small>
                            <span v-else class="font-normal text-red-600">{{ errors.correo }}</span>
                        </div>
                        <div class="w-full">
                            <label for="celular" class="">Phone Number <span
                                    class="font-normal text-red-600">*</span></label>
                            <InputText name="celular" v-model="celular" v-bind="celularAttrs" @keypress="onlyPhoneKeys"
                                :disabled="bloqueoCelular" class="w-full border-green-iimp"
                                placeholder="+51999888777 or 999888777" />
                            <span class="font-normal text-red-600">{{ errors.celular }}</span>
                        </div>
                        <div class="w-full">
                            <label for="empresa" class="">Company <span
                                    class="font-normal text-gray-500 ml-1">(Optional)</span></label>
                            <InputText name="empresa" v-model="empresa" v-bind="empresaAttrs"
                                class="w-full border-green-iimp" />
                            <span class="font-normal text-red-600">{{ errors.empresa }}</span>
                        </div>
                    </div>

                    <div class="grid gap-6 m-6 grid-cols-1 md:grid-cols-4">
                        <div class="w-full">
                            <label for="fecha_nacimiento">Date of Birth <span class="text-red-600">*</span></label>
                            <InputGroup class="w-full h-[42px]">
                                <InputGroupAddon class="border-green-iimp border-r-0 bg-white text-green-iimp">
                                    <i class="pi pi-calendar"></i>
                                </InputGroupAddon>
                                <Calendar name="fecha_nacimiento" v-model="fecha_nacimiento"
                                    v-bind="fecha_nacimientoAttrs" :maxDate="maxAdultDate" dateFormat="yy-mm-dd"
                                    :showTime="false" placeholder="YYYY-MM-DD" class="w-full"
                                    :disabled="bloqueoFechaNac"
                                    inputClass="w-full border-green-iimp border-l-0 shadow-none outline-none bg-white" />
                            </InputGroup>
                            <span class="font-normal text-red-600">{{ errors.fecha_nacimiento }}</span>
                        </div>
                        <div class="w-full">
                            <label for="sexo" class="">Gender <span class="font-normal text-red-600"> *</span></label>
                            <Select name="sexo" v-model="sexo" v-bind="sexoAttrs" optionLabel="label"
                                optionValue="value" placeholder="Select" showClear checkmark :options="generos"
                                :disabled="bloqueoSexo" class="w-full border-green-iimp" />
                            <span class="font-normal text-red-600">{{ errors.sexo }}</span>
                        </div>
                    </div>
                </div>
            </template>
        </Card>
    </div>
</template>

<style>
:deep(.p-select.p-disabled) {
    background-color: #f3f4f6 !important;
    opacity: 1;
}

:deep(.p-select.p-disabled .p-select-label) {
    color: #4b5563 !important;
}

.wmc-btn-international {
    /* Fondo blanco con un toque de gris muy claro */
    background-color: #f8fafc !important;
    /* Color de texto "Steel" (Acero) profesional */
    color: #334155 !important;
    border: 1.5px solid #cbd5e1 !important;
    padding: 0.8rem 1.8rem !important;
    border-radius: 12px !important;
    transition: all 0.3s ease;
}

.wmc-btn-international:hover {
    background-color: #ffffff !important;
    border-color: #94a3b8 !important;
    color: #0f172a !important;
    transform: translateY(-1px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
}

.wmc-btn-international i {
    color: #64748b;
    /* El icono un poco más suave que el texto */
}
</style>
