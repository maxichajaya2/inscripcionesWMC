<script setup>
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Divider from 'primevue/divider';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Checkbox from 'primevue/checkbox';
import RadioButton from 'primevue/radiobutton';
import Card from 'primevue/card';
import InputGroup from 'primevue/inputgroup';
import { ref, onMounted, computed, watch, nextTick, onUnmounted } from 'vue'; // Agregado nextTick
import { useForm } from 'vee-validate';
import * as yup from 'yup';
import { usePage, router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import Functions from '@/Functions';
import FileUpload from 'primevue/fileupload';
import InputGroupAddon from 'primevue/inputgroupaddon';

import "../../../css/inscripciones.css";

const page = usePage();
const props = defineProps({
    data_persona: Object,
    categorias: Object,
    saved_values: Object,
    cupones: Object,
    activarModal: Boolean,
});

const toast = useToast();
const empresaCupon = ref(null);
const codigoVoucher = ref('');
const loadingCupon = ref(false);
const cuponAplicado = ref(false);
const mensajeVoucher = ref({ texto: '', tipo: '' });

const empresasAliadas = computed(() => {
    if (!props.cupones) return [];

    const lista = Object.values(props.cupones).map(c => {
        // Validamos si aún tiene stock disponible
        const tieneStock = parseInt(c.usos_actuales) < parseInt(c.limite_usos);

        return {
            id: c.id,
            nombre: tieneStock ? c.razon_social : `${c.razon_social} (CUPONES AGOTADOS)`,
            ruc: c.num_documento,
            codigo: c.codigo_cupon,
            valor: c.valor,
            tipo_doc: c.tipo_documento,
            disabled: !tieneStock // Nueva propiedad para deshabilitar en el Select
        };
    });

    return lista.sort((a, b) => a.nombre.localeCompare(b.nombre));
});

const fieldNames = {
    selected_categoria: 'Registration Category',
    tipoDocumentoEmpresa: 'Billing Document Type',
    documentoEmpresa: 'Billing Document Number',
    razonSocial: 'Business Name / Full Name',
    direccionEmpresa: 'Billing Address',
    responsable: 'Billing Contact Name',
    correo_facturador: 'Billing Email',
    reglamento: 'Terms and Conditions Acceptance',
    uploadDocument: 'Category Required Document'
};

const es_socio = ref(false);
const loading_doc = ref(false);
const yaMostrado = ref(false);
const show_days = ref(false);
const show_document = ref(false);
const upload_instruction = ref('');
const showManualAlert = ref(false);
const showSuccessAlert = ref(false);
const total = ref(0);
const src = ref(null);
const block_direction = ref(false);
const fileErrors = ref([]);
const maxSize = 6291456;
const allowedTypes = ['application/pdf', 'image/png', 'image/jpg', 'image/jpeg'];
const fileupload = ref(null);
const alphanumericMessage = ref('');
const descuentoAplicadoMonto = ref(0);
const tipoDescuentoAplicado = ref('');
// Agregamos un estado para controlar si el usuario puede editar manualmente
const isEditingBilling = ref(false);
const cuponIdSeleccionado = ref(null);
let current_price = 0;
const tipoDocumento = computed(() => page.props.general.tipDocEmp)
const showInfoModal = ref(false); // Esta ya la tienes
const modalConfig = ref({ title: '', message: '', icon: '', colorClass: '' });

const days = { 'mie': 'Wednesday', 'jue': 'Thursday', 'vie': 'Friday' };
const current_days = { 'lun': false, 'mar': false, 'mie': false, 'jue': false, 'vie': false };

const formManualErrors = ref({ reglamento: null, total: null, uploadDocument: null });


const showWelcomeBillingModal = ref(false); // Controla el nuevo modal de bienvenida
const isPeruanoGlobal = computed(() => {
    const p = props.data_persona?.persona || props.data_persona;
    return p?.pais == 1 || p?.id_pais == 1 || p?.tipo_doc == 1 || p?.tipo_doc == 2 || p?.nacionalidad?.toLowerCase() === 'peruano';
});


const { defineField, errors, setValues, values, validate } = useForm({
    validationSchema: yup.object({
        selected_categoria: yup.mixed().required('Category is required'),
        tipoDocumentoEmpresa: yup.mixed().required('Document type is required'),
        documentoEmpresa: yup.string()
            .required('Document number is required')
            .test('len', 'Invalid format', function (value) {
                const { tipoDocumentoEmpresa } = this.parent;

                // --- VALIDACIÓN PARA PERUANOS ---
                if (tipoDocumentoEmpresa === 1) { // DNI
                    return value?.length === 8 || this.createError({ message: 'DNI must be exactly 8 digits' });
                }
                if (tipoDocumentoEmpresa === 2) { // RUC
                    return value?.length === 11 || this.createError({ message: 'RUC must be exactly 11 digits' });
                }

                // --- FLUJO EXTRANJERO ---
                // Permitimos cualquier longitud alfanumérica, solo validamos que exista
                return value?.length > 0;
            }),


        razonSocial: yup.string().required('Business name is required'),
        direccionEmpresa: yup.string()
            .trim()
            .required('La dirección de la empresa es obligatoria')
            .min(5, 'La dirección es demasiado corta')
            .test('no-garbage', 'Por favor, ingrese una dirección válida', (value) => {
                if (!value) return false;
                // Verifica que contenga al menos 3 letras o números
                // y que no sean solo símbolos repetidos como --- o ***
                const hasContent = /[a-zA-Z0-9]{3,}/.test(value);
                const isNotJustSymbols = !/^[ \-*.;,_]+$/.test(value);
                return hasContent && isNotJustSymbols;
            }),
        responsable: yup.string().required('Responsible party name is required'),
        correo_facturador: yup.string()
            .email('Invalid email format')
            .required('Billing email is required'),
        reglamento: yup.boolean().oneOf([true], 'You must accept the regulations'),
    })
});

const dniMessageEmpresa = ref('');

const [documentoEmpresa] = defineField('documentoEmpresa');
const [razonSocial] = defineField('razonSocial');
const [responsable] = defineField('responsable');
const [correo_facturador] = defineField('correo_facturador');
const [tipoDocumentoEmpresa] = defineField('tipoDocumentoEmpresa');
const [direccionEmpresa] = defineField('direccionEmpresa');
const [selectTipoPago] = defineField('selectTipoPago');
const [selectTipoDocPago] = defineField('selectTipoDocPago');
const [selected_categoria, selected_categoriaAttrs] = defineField('selected_categoria');
const [selectedDays, selectedDaysAttrs] = defineField('selectedDays');
const [uploadDocument] = defineField('uploadDocument');

const is_category_fixed = ref(false);
const urlParams = new URLSearchParams(window.location.search);
const esSeccionViajes = computed(() => urlParams.get('section') === 'viajes');
const noAsociadoCupon = computed(() => urlParams.get('profile') === '6');

const MODAL_DATA = {
    DNI: {
        title: 'Receipt Information',
        message: 'By selecting DNI (National ID), an electronic Sales Receipt will be issued in the name of the individual.',
        icon: 'pi pi-user',
        colorClass: 'text-blue-600'
    },
    RUC: {
        title: 'Invoice Information',
        message: 'By selecting RUC (Tax ID), an electronic Commercial Invoice will be issued in the name of the company or legal entity.',
        icon: 'pi pi-building',
        colorClass: 'text-purple-600'
    }
};

function changeCategory(id, precioRecibido) {
    if (!id) return;

    const categoria = props.categorias.find(c => c.id === id);
    if (!categoria) return;

    // LÓGICA CRÍTICA: Forzamos a 0 si la sección es 'viajes'
    if (esSeccionViajes.value) {
        current_price = 0;
    } else {
        current_price = (precioRecibido > 0) ? precioRecibido : (categoria.precio_disponible?.valor || 0);
    }

    nextTick(() => {
        show_document.value = Boolean(categoria.requiere_documento);

        if (show_document.value) {
            const nombre = categoria.nombre_en.toUpperCase();
            if (nombre.includes('STUDENT') || nombre.includes('ESTUDIANTE')) {
                upload_instruction.value = "Rate applicable to undergraduate students, presentation of enrollment proof required.";
            } else if (nombre.includes('FACULTY') || nombre.includes('DOCENTE')) {
                upload_instruction.value = "Special rate for undergraduate faculty members, who must present valid proof of their status.";
            } else {
                upload_instruction.value = "Please upload the required document for this category.";
            }
        }

        if (id == 39) {
            show_days.value = true;
            // Al multiplicar por current_price (que es 0), el total será 0
            let count = Object.values(current_days).filter(v => v).length;
            total.value = count * current_price;
        } else {
            show_days.value = false;
            total.value = current_price; // Será 0
        }
    });
}

const esRuc20 = computed(() => {
    return tipoDocumentoEmpresa.value === 2 && documentoEmpresa.value?.startsWith('20');
});

const getInscripcion = async () => {
    const result = await validate();
    formManualErrors.value.total = null;

    if (selected_categoria.value == 39) {
        const tieneDias = Object.values(current_days).some(v => v === true);
        if (!tieneDias) {
            formManualErrors.value.total = "Attention: You must select at least one day.";
            return { validate: false };
        }
    }

    // Si es viajes, permitimos pasar con 0
    const totalValido = esSeccionViajes.value ? true : (total.value > 0);

    if (!result.valid || !totalValido || (show_document.value && !uploadDocument.value)) {
        return { validate: false };
    }

    return {
        validate: true,
        formInscription: {
            ...values,
            codigo_cupon: codigoVoucher.value, // Enviamos el código escrito
            empresa_id: empresaCupon.value?.id, // Enviamos el ID de la empresa aliada
            id_cupon: cuponIdSeleccionado.value,
        },
        total_final: total.value // Aquí enviará 0 si es viajes
    };
};

const validarCuponLocal = async () => {
    // 1. Validar que existan los datos necesarios
    if (!codigoVoucher.value || !empresaCupon.value) {
        mensajeVoucher.value = { texto: 'Please select a company and code.', tipo: 'error' };
        return;
    }

    loadingCupon.value = true;

    // 2. REINICIO TOTAL (Para evitar que el mensaje de error de la imagen se quede pegado)
    mensajeVoucher.value = { texto: '', tipo: '' };
    cuponAplicado.value = false;
    descuentoAplicadoMonto.value = 0;

    try {
        // 3. Normalizamos datos para la búsqueda
        const idBuscado = Number(empresaCupon.value.id);
        const codigoIngresado = codigoVoucher.value.toString().trim().toUpperCase();

        const cuponData = Object.values(props.cupones).find(c => {
            const idBD = Number(c.id);
            const codigoBD = c.codigo_cupon.toString().trim().toUpperCase();
            return idBD === idBuscado && codigoBD === codigoIngresado;
        });

        if (cuponData) {
            // --- ÉXITO ---
            cuponAplicado.value = true;
            descuentoAplicadoMonto.value = Number(cuponData.valor);
            cuponIdSeleccionado.value = cuponData.id;

            tipoDescuentoAplicado.value = cuponData.tipo_descuento?.toLowerCase() || 'porcentaje';

            const textoValor = tipoDescuentoAplicado.value === 'fijo'
                ? `USD ${cuponData.valor}`
                : `${cuponData.valor}%`;

            mensajeVoucher.value = {
                texto: `${words.msg_coupon_of} ${textoValor} ${words.msg_coupon_valid_for} ${cuponData.razon_social}!`,
                tipo: 'success'
            }

            // mensajeVoucher.value = {
            //     texto: `¡Coupon of ${cuponData.valor}% valid for ${cuponData.razon_social}!`,
            //     tipo: 'success'
            // };

            toast.add({
                severity: 'success',
                summary: 'Coupon Validated',
                detail: 'Billing data loaded successfully.',
                life: 4000
            });
        } else {
            // --- ERROR: NO COINCIDE ---
            mensajeVoucher.value = {
                texto: 'The code does not match the selected company.',
                tipo: 'error'
            };
        }
    } catch (e) {
        console.error("Error validando:", e);
        // mensajeVoucher.value = { texto: 'Error validating coupon.', tipo: 'error' };
    } finally {
        loadingCupon.value = false;
    }
};

onMounted(() => {
    // Configuraciones iniciales
    tipoDocumentoEmpresa.value = 1;
    selectTipoDocPago.value = 2;
    selectTipoPago.value = 3;

    // 2. Lógica de URL y categorías
    const urlParams = new URLSearchParams(window.location.search);
    const categoryIdFromUrl = urlParams.get('category');
    let targetCategoryId = null;

    if (props.saved_values && props.saved_values.selected_categoria) {
        targetCategoryId = props.saved_values.selected_categoria;
        setValues(props.saved_values);
    } else if (categoryIdFromUrl) {
        targetCategoryId = parseInt(categoryIdFromUrl);
        is_category_fixed.value = true;
    }

    if (targetCategoryId) {
        selected_categoria.value = targetCategoryId;
        const cat = props.categorias.find(c => c.id === targetCategoryId);
        setTimeout(() => {
            changeCategory(targetCategoryId, cat?.precio_disponible?.valor || 0);
        }, 150);
    }

    if (esSeccionViajes.value) {
        cuponAplicado.value = false;
        descuentoAplicadoMonto.value = 0;
        empresaCupon.value = null;
        codigoVoucher.value = '';
    }

    if (isPeruanoGlobal.value) {
        setTimeout(() => {
            showWelcomeBillingModal.value = true;
        }, 800); // Un pequeño delay para que no sea tan brusco tras cargar la página
    }

    if (!props.saved_values && !props.data_persona?.documento) {
        tipoDocumentoEmpresa.value = 1;
    }

    if (props.data_persona && props.data_persona.documentoEmpresa) {
        setValues({
            tipoDocumentoEmpresa: props.data_persona.tipoDocumentoEmpresa,
            documentoEmpresa: props.data_persona.documentoEmpresa,
            razonSocial: props.data_persona.razonSocial,
            direccionEmpresa: props.data_persona.direccionEmpresa,
            responsable: props.data_persona.responsable,
            correo_facturador: props.data_persona.correo_facturador,
            selected_categoria: props.data_persona.selected_categoria
        });

        // Disparamos la lógica visual de la categoría
        changeCategory(props.data_persona.selected_categoria);
    }


});

watch(selected_categoria, (newId) => {
    if (!newId) return; // Si es nulo, no hacer nada

    const cat = props.categorias.find(c => c.id === newId);
    if (cat) {
        // Solo ejecutamos changeCategory si el total actual es 0
        // o si la categoría cambió realmente por una acción que no sea el llenado inicial
        // console.log("Watcher detectó cambio de categoría a:", newId);
        changeCategory(newId, cat.precio_disponible?.valor || 0);
    }
});

watch(documentoEmpresa, (newVal) => {
    if (!newVal) return;

    // Solo aplicamos restricción numérica y de longitud para DNI (1) y RUC (2)
    if (tipoDocumentoEmpresa.value === 1 || tipoDocumentoEmpresa.value === 2) {
        // 1. Eliminar todo lo que no sea número (por si pegan texto)
        const cleanedValue = newVal.replace(/\D/g, '');

        // 2. Definir el máximo permitido
        const maxLength = tipoDocumentoEmpresa.value === 1 ? 8 : 11;

        // 3. Aplicar recortes si excede el largo
        if (newVal !== cleanedValue || newVal.length > maxLength) {
            documentoEmpresa.value = cleanedValue.slice(0, maxLength);
        }
    }
});

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

watch(documentoEmpresa, (newValue) => {
    // CAMBIO AQUÍ: tipoDocumentoEmpresa en lugar de tipo_doc
    if (tipoDocumentoEmpresa.value !== 1 && newValue) {
        const cleaned = newValue.replace(/[^a-zA-Z0-9]/g, '');
        if (cleaned !== newValue) {
            documentoEmpresa.value = cleaned;
            alphanumericMessage.value = "Special characters were removed";
            setTimeout(() => { alphanumericMessage.value = ''; }, 3000);
        }
    }
});



// En FormInscription.vue
watch(tipoDocumentoEmpresa, (newVal, oldVal) => {
    const p = props.data_persona?.persona || props.data_persona;
    const esPeruano = p?.pais == 1 || p?.id_pais == 1 || p?.tipo_doc == 1 || p?.tipo_doc == 2;

    // CRÍTICO: Si oldVal es undefined o null, significa que es la carga inicial.
    // NO debemos limpiar los campos en la carga inicial porque borraríamos el autocompletado.
    if (oldVal === undefined || oldVal === null) {
        setTipoDocPago(); // Solo configuramos si es Boleta o Factura
        return;
    }

    // Si llegamos aquí, es porque el usuario CAMBIÓ el tipo de documento manualmente
    console.log("Cambio manual de doc:", newVal);

    setValues({
        ...values,
        documentoEmpresa: '',
        razonSocial: '',
        direccionEmpresa: '',
        responsable: '',
        correo_facturador: ''
    });

    if (esPeruano) {
        if (newVal === 1) { // DNI
            modalConfig.value = MODAL_DATA.DNI;
            showInfoModal.value = true;
        } else if (newVal === 2) { // RUC
            modalConfig.value = MODAL_DATA.RUC;
            showInfoModal.value = true;
        }
    }
    setTipoDocPago();
});

watch(empresaCupon, () => {
    codigoVoucher.value = '';
    mensajeVoucher.value = { texto: '', tipo: '' };
    cuponAplicado.value = false;
    descuentoAplicadoMonto.value = 0;
    tipoDescuentoAplicado.value = '';
});


watch(() => props.activarModal, (valor) => {
    // Si el padre puso la variable en true y no lo hemos mostrado antes
    if (valor === true && !yaMostrado.value) {
        showWelcomeBillingModal.value = true;
        yaMostrado.value = true;
    }
});

const getEmpresaData = async () => {
    // 1. Limpieza preventiva: Blanqueamos campos y ocultamos alertas previas
    razonSocial.value = '';
    direccionEmpresa.value = '';
    showManualAlert.value = false;
    showSuccessAlert.value = false;

    // Sincronizamos con vee-validate para que no queden valores viejos en el objeto 'values'
    setValues({
        ...values,
        razonSocial: '',
        direccionEmpresa: ''
    });

    if (!documentoEmpresa.value) return;

    loading_doc.value = true;

    try {
        const empresaData = await Functions.getEmpresaData(documentoEmpresa.value, tipoDocumentoEmpresa.value);

        // Si la API responde con éxito y trae datos
        if (empresaData?.status && empresaData?.empresa) {
            razonSocial.value = empresaData.empresa.nombre;
            direccionEmpresa.value = empresaData.empresa.direccionEmpresa;

            // Mostrar Alerta de Éxito
            showSuccessAlert.value = true;


            if (esRuc20.value) {
                isEditingBilling.value = false; // Bloquea edición manual para RUC 20
                block_direction.value = true;   // Refuerza bloqueo de dirección
            } else {
                isEditingBilling.value = true;  // Permite editar si es RUC 10 o DNI
                block_direction.value = false;
            }



            // block_direction.value = false;
            // isEditingBilling.value = true;
            // Actualizar vee-validate
            setValues({
                ...values,
                razonSocial: empresaData.empresa.nombre,
                direccionEmpresa: empresaData.empresa.direccionEmpresa
            });
        } else {
            // Caso: No encontrado
            showManualAlert.value = true;
            isEditingBilling.value = true;
            block_direction.value = false;
        }
    } catch (e) {
        console.error(e);
        showManualAlert.value = true;
        isEditingBilling.value = true;
    } finally {
        loading_doc.value = false;
    }
}

const onFileSelect = (event) => {
    // 1. Verificamos que haya archivo
    if (!event.files || event.files.length === 0) return;

    const file = event.files[0];

    // 2. Reiniciamos errores y variables
    fileErrors.value = [];
    uploadDocument.value = file;
    src.value = null;

    // --- VALIDACIÓN 1: FORMATO ---
    if (!allowedTypes.includes(file.type)) {
        fileErrors.value.push("Invalid file format. Only PDF documents or Images (PNG, JPG, JPEG) are accepted.");
    }

    // --- VALIDACIÓN 2: TAMAÑO (6MB) ---
    if (file.size > maxSize) {
        fileErrors.value.push("File size exceeds the limit. Maximum allowed is 6MB.");
    }

    // 3. Si hay errores, limpiamos la selección para que el usuario deba elegir otro
    if (fileErrors.value.length > 0) {
        uploadDocument.value = null;
        if (fileupload.value) {
            fileupload.value.clear(); // Limpia el componente visualmente
        }
        return;
    }

    // 4. Si todo está OK, generar vista previa
    const reader = new FileReader();
    reader.onload = (e) => {
        if (file.type === "application/pdf") {
            src.value = '/images/pdf-file-document.png'; // Icono para PDFs
        } else {
            src.value = e.target.result; // Imagen real para PNG/JPG
        }
    };
    reader.readAsDataURL(file);
}

function selectDays(id) {
    current_days[id] = !current_days[id];
    let count = Object.values(current_days).filter(v => v).length;
    total.value = count * current_price;
}

function setTipoDocPago() {
    if (tipoDocumentoEmpresa.value == 2) {
        selectTipoDocPago.value = 1;
        block_direction.value = true;
    } else {
        selectTipoDocPago.value = 2;
        block_direction.value = false;
    }
}

const fillBillingData = (p) => {
    if (!p) return;

    const nombreCompleto = `${p.nombres || ''} ${p.apellido_paterno || ''}`.trim();
    // CAMBIO AQUÍ: ID 3 por defecto si es extranjero y no tiene tipo de documento
    const docTipo = p.id_tipo_documento || p.tipo_doc || (isPeruanoGlobal.value ? 1 : 3);
    const docNum = p.documento || '';

    tipoDocumentoEmpresa.value = docTipo;
    documentoEmpresa.value = docNum;
    razonSocial.value = nombreCompleto;
    direccionEmpresa.value = p.direccionPersona || p.direccion || '';
    responsable.value = nombreCompleto;
    correo_facturador.value = p.correo || '';

    setValues({
        tipoDocumentoEmpresa: docTipo,
        documentoEmpresa: docNum,
        razonSocial: nombreCompleto,
        direccionEmpresa: direccionEmpresa.value,
        responsable: nombreCompleto,
        correo_facturador: p.correo || '',
        selectTipoDocPago: docTipo == 2 ? 1 : 2,
        selectTipoPago: 3
    });

    block_direction.value = (docTipo == 2);
};

// --- LÓGICA DE WATCH UNIFICADA Y CORREGIDA ---
// watch(() => props.data_persona, (newVal) => {
//     if (!newVal) return;
//     const data = newVal.persona ? newVal.persona : newVal;

//     if (isPeruanoGlobal.value) {
//         // PERUANO
//         if (data.documentoEmpresa || data.razonSocial || data.documento) {
//             fillBillingData(data);
//         }
//     } else {
//         // EXTRANJERO
//         if (data.documentoEmpresa || data.razonSocial) {
//             // Si ya hay datos previos de facturación, llenarlos
//             fillBillingData(data);
//         } else {
//             // Si no hay datos, limpiar y poner ID 3 por defecto
//             tipoDocumentoEmpresa.value = 3;
//             setValues({
//                 ...values,
//                 documentoEmpresa: '',
//                 razonSocial: '',
//                 direccionEmpresa: '',
//                 responsable: '',
//                 correo_facturador: ''
//             });
//         }
//     }
// }, { immediate: true, deep: true });

// --- LÓGICA DE WATCH UNIFICADA Y CORREGIDA ---
watch(() => props.data_persona, (newVal) => {
    if (!newVal) return;
    const data = newVal.persona ? newVal.persona : newVal;

    if (isPeruanoGlobal.value) {
        // PERUANO
        // Quitamos "data.documento" para que no use el DNI personal por defecto
        if (data.documentoEmpresa || data.razonSocial) {
            fillBillingData(data);
        } else {
            // Si no hay datos de facturación previos, seleccionamos DNI (1) y limpiamos
            tipoDocumentoEmpresa.value = 1;
            setValues({
                ...values,
                documentoEmpresa: '',
                razonSocial: '',
                direccionEmpresa: '',
                responsable: '',
                correo_facturador: ''
            });
        }
    } else {
        // EXTRANJERO
        if (data.documentoEmpresa || data.razonSocial) {
            fillBillingData(data);
        } else {
            // Si no hay datos de facturación previos, seleccionamos ID 3 y limpiamos
            tipoDocumentoEmpresa.value = 3;
            setValues({
                ...values,
                documentoEmpresa: '',
                razonSocial: '',
                direccionEmpresa: '',
                responsable: '',
                correo_facturador: ''
            });
        }
    }
}, { immediate: true, deep: true });

// ----------------------------------------------

const filteredDocTypes = computed(() => {
    const p = props.data_persona?.persona || props.data_persona;

    // Es peruano si el país es 1
    // O si ya trae un tipo_doc 1 (DNI) o 2 (RUC) aunque el ID de país diga otra cosa
    const esPeruano = p?.pais == 1 ||
        p?.id_pais == 1 ||
        p?.tipo_doc == 1 ||
        p?.tipo_doc == 2 ||
        p?.nacionalidad?.toLowerCase() === 'peruano';

    // console.log("¿Es detectado como Peruano?:", esPeruano);

    if (!tipoDocumento.value) return [];

    if (esPeruano) {
        // Retorna SOLO DNI (1) y RUC (2)
        const filtrados = tipoDocumento.value.filter(d => d.id == 1 || d.id == 2);
        // console.log("Documentos para Peruano:", filtrados);
        return filtrados;
    } else {
        // Retorna PASAPORTE, CE, etc. (quita DNI y RUC)
        const filtrados = tipoDocumento.value.filter(d => d.id != 1 && d.id != 2);
        // console.log("Documentos para Extranjero:", filtrados);
        return filtrados;
    }
});

const missingFields = computed(() => {
    return Object.keys(errors.value).map(key => fieldNames[key] || key);
});

const onlyNumberKey = (event) => {
    const charCode = event.which ? event.which : event.keyCode;

    // Si es DNI o RUC, solo permitir números
    if (tipoDocumentoEmpresa.value === 1 || tipoDocumentoEmpresa.value === 2) {
        if (charCode > 31 && (charCode < 48 || charCode > 57)) {
            event.preventDefault();
            return false;
        }

        // Bloquear si ya llegó al máximo permitido
        const max = tipoDocumentoEmpresa.value === 1 ? 8 : 11;

        if (documentoEmpresa.value?.length >= max) {
            // --- NUEVO: Activación del mensaje ---
            dniMessageEmpresa.value = `Solo se permiten ${max} dígitos`;

            // Limpiamos el mensaje después de 3 segundos
            setTimeout(() => {
                dniMessageEmpresa.value = '';
            }, 3000);
            // -------------------------------------

            event.preventDefault();
            return false;
        } else {
            // Si el usuario está escribiendo y aún no llega al máximo, limpiamos el mensaje
            dniMessageEmpresa.value = '';
        }
    }
    return true;
}

// const montoDescuentoEfectivo = computed(() => {
//     // Calculamos cuánto dinero representa el % de descuento sobre el total
//     return (total.value * descuentoAplicadoMonto.value) / 100;
// });

const montoDescuentoEfectivo = computed(() => {
    if (!cuponAplicado.value) return 0;

    if (tipoDescuentoAplicado.value === 'fijo') {
        // Lógica Fija: El descuento es directamente la cantidad.
        // Usamos Math.min por seguridad: si el total es $50 y el cupón fijo es de $100,
        // solo descontamos $50 para que el totalFinal no sea negativo.
        return Math.min(Number(descuentoAplicadoMonto.value), Number(total.value));
    } else {
        // Lógica original de Porcentaje
        return (Number(total.value) * Number(descuentoAplicadoMonto.value)) / 100;
    }
});

const totalFinalConDescuento = computed(() => {
    return total.value - montoDescuentoEfectivo.value;
});

defineExpose({ getInscripcion });


</script>

<template>

    <div class="gap-6 p-6 w-full justify-around overflow-visible">

        <!--          CATEGORIAS                      -->
        <!-- ======================================== -->
        <div class="text-green-iimp font-bold p-4">
            <Card class="mt-5 overflow-hidden shadow-lg border border-gray-200">
                <template #header>
                    <div
                        class="w-full py-3 text-xl font-bold text-center bg-lightblue-wmc border-blue-wmc text-blue-900">
                        Category Details
                    </div>
                </template>

                <template #content>
                    <div class="px-2">

                        <div v-if="is_category_fixed || esSeccionViajes"
                            class="w-full p-4 bg-blue-50 border border-blue-200 rounded-xl shadow-sm flex justify-between items-center">
                            <div class="flex flex-col">
                                <span class="text-[10px] uppercase text-blue-400 font-black tracking-widest">Selected
                                    Profile</span>
                                <h4 class="text-lg font-bold text-blue-900 leading-tight">
                                    {{categorias.find(c => c.id === selected_categoria)?.nombre_en}}
                                </h4>
                            </div>
                            <div v-if="!esSeccionViajes" class="text-right">
                                <p class="text-yellow-price font-black text-xl">
                                    USD {{categorias.find(c => c.id === selected_categoria)?.precio_disponible?.valor
                                        || '0.00'}}
                                </p>
                            </div>
                        </div>

                        <div v-else>
                            <div v-for="(categoria) in categorias" :key="categoria.id"
                                class="w-full border-b border-gray-100 last:border-0 py-3 px-3 rounded-lg transition-colors duration-200"
                                :class="{
                                    'bg-blue-50 border border-blue-200 shadow-sm': selected_categoria === categoria.id,
                                    'hover:bg-gray-50': selected_categoria !== categoria.id
                                }">
                                <div class="flex items-start w-full">
                                    <div class="flex-none pt-1">
                                        <!-- <RadioButton v-model="selected_categoria" v-bind="selected_categoriaAttrs"
                                            name="selected_categoria" :value='categoria.id' class="radio-green-iimp"
                                            @click="changeCategory(categoria.id, categoria.precio_disponible.valor)" /> -->
                                        <!-- <RadioButton v-model="selected_categoria" :value="categoria.id"
                                            @click="changeCategory(categoria.id, categoria.precio_disponible?.valor)" /> -->
                                        <RadioButton v-model="selected_categoria" :value="categoria.id" />
                                    </div>
                                    <div class="flex flex-col sm:flex-row sm:justify-between w-full pl-3 cursor-pointer"
                                        @click="changeCategory(categoria.id, categoria.precio_disponible.valor)">
                                        <label
                                            class="text-sm sm:text-base text-gray-700 leading-tight mb-1 sm:mb-0 cursor-pointer"
                                            :class="{ 'font-bold text-blue-900': selected_categoria === categoria.id }">
                                            {{ es_socio ? categoria.nombre_es :
                                                categoria.nombre_en }}
                                        </label>
                                        <p
                                            class="text-yellow-price font-bold text-sm sm:text-base whitespace-nowrap sm:pl-4">
                                            USD {{ categoria.precio_disponible?.valor ?? '0.00' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- =========== POR DIAS  ========== -->
                    <!-- ================================ -->
                    <Card v-if="show_days" class="mt-6 border border-dashed border-blue-300 bg-blue-50/30">
                        <template #content>
                            <div v-if="formManualErrors.total"
                                class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 flex items-center gap-4 animate-fade-in-down shadow-sm">
                                <div class="bg-red-500 rounded-full p-2 flex-none">
                                    <i class="pi pi-exclamation-triangle text-white text-lg"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-red-800 font-black text-sm uppercase">Selection Required</span>
                                    <p class="text-red-700 text-sm font-medium leading-tight">
                                        {{ formManualErrors.total }}
                                    </p>
                                </div>

                            </div>

                            <p class="text-sm text-blue-800 font-bold mb-4 text-center">
                                <i class="pi pi-calendar-plus mr-2"></i>Select the specific days of attendance:
                            </p>

                            <div class="flex justify-around flex-wrap gap-4">
                                <div v-for="(day, key) in days" :key="key" class="flex items-center">
                                    <Checkbox :inputId="day" :value="key" v-model="selectedDays"
                                        v-bind="selectedDaysAttrs" name="selectedDays" @click="selectDays(key)" />
                                    <label :for="day" class="pl-2 text-sm text-gray-700 font-semibold cursor-pointer">{{
                                        day }}</label>
                                </div>
                            </div>

                            <div class="flex justify-center mt-6 pt-4 border-t border-blue-200">
                                <div class="text-blue-900 font-black flex items-center gap-4">
                                    <span class="text-sm uppercase tracking-wider">Subtotal:</span>
                                    <span class="text-2xl text-yellow-price">USD {{ total }}</span>
                                </div>

                            </div>
                            <span class="text-[10px] text-blue-700 font-bold italic uppercase tracking-wider">
                                * Rate per day of attendance
                            </span>
                        </template>
                    </Card>
                    <!-- =========== CARGAR DOCUMENTO  ========== -->
                    <!-- ================================= -->
                    <Card v-if="show_document" class="mt-6 border border-dashed border-green-300">
                        <template #content>
                            <div v-if="show_document && !uploadDocument"
                                class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 flex items-center gap-4 animate-fade-in-down shadow-sm">
                                <div class="bg-red-500 rounded-full p-2 flex-none">
                                    <i class="pi pi-exclamation-triangle text-white text-lg"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-red-800 font-black text-sm uppercase">Selection Required</span>
                                    <p class="text-red-700 text-sm font-medium leading-tight">
                                        The selected category requires an <strong>attachment</strong>. Please upload
                                        your
                                        document
                                        below.
                                    </p>
                                </div>
                            </div>

                            <div v-if="upload_instruction"
                                class="mb-4 p-4 bg-blue-50 border-l-4 border-blue-500 text-blue-700">
                                <p class="text-sm font-bold">Requirement:</p>
                                <p class="text-sm">{{ upload_instruction }}</p>
                            </div>

                            <div class="flex justify-center mb-4 w-full">
                                <img v-if="src" :src="src" alt="Preview"
                                    class="shadow-md rounded-lg border border-gray-200 max-w-[200px] max-h-[200px] object-contain" />
                            </div>

                            <div class="flex flex-col items-center justify-center w-full">
                                <!-- <div v-if="formManualErrors.uploadDocument"
                                    class="w-full mb-4 flex items-center gap-3 rounded border-l-4 border-red-500 bg-red-50 px-4 py-2 text-red-800 shadow-sm">
                                    <i class="pi pi-times-circle"></i>
                                    <span class="text-xs font-bold">{{ formManualErrors.uploadDocument }}</span>
                                </div> -->
                                <div v-if="fileErrors.length > 0"
                                    class="w-full md:w-3/4 mb-4 p-3 bg-red-50 border border-red-200 rounded-md text-center mx-auto animate-fade-in">
                                    <div v-for="(error, index) in fileErrors" :key="index"
                                        class="flex items-center justify-center gap-2 text-red-600 font-bold mb-1 last:mb-0">
                                        <i class="pi pi-exclamation-triangle"></i>
                                        <span class="text-sm">{{ error }}</span>
                                    </div>
                                </div>
                                <FileUpload ref="fileupload" mode="basic"
                                    class="p-button-outlined text-green-iimp mx-auto" :auto="true" customUpload
                                    :chooseLabel="'Upload Document'" @select="onFileSelect" name="uploadDocument" />

                                <small class="text-slate-500 mt-3 text-center block text-xs">
                                    Accepted: PDF, JPG, PNG (Max 6MB)
                                </small>
                            </div>
                        </template>
                    </Card>

                    <!-- =========== CUPON DE DESCUENTO  ========== -->
                    <!-- ================================= -->
                    <Card v-if="!esSeccionViajes && noAsociadoCupon"
                        class="mt-6 border border-dashed border-blue-400 bg-blue-50/50 shadow-sm">
                        <template #content>
                            <div class="flex items-start gap-3 mb-4 p-3 bg-white/60 rounded-lg border border-blue-100">
                                <i class="pi pi-info-circle text-blue-500 mt-1"></i>
                                <p class="text-xs text-blue-800 leading-tight">
                                    The discount coupon applies exclusively to previously registered <strong>partner
                                        companies and
                                        institutions</strong>. If your organization has an active agreement, select the
                                    name and validate the code.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                                <div class="flex flex-col gap-2">
                                    <label class="text-xs font-black text-blue-900 uppercase tracking-wider">
                                        Company / Institution
                                    </label>
                                    <Select v-model="empresaCupon" :options="empresasAliadas" optionLabel="nombre"
                                        optionDisabled="disabled" placeholder="Type to search your company..."
                                        class="w-full border-blue-300" :filter="true"
                                        filterPlaceholder="Ex: ALS PERU, MDH..." resetFilterOnHide>
                                        <template #option="slotProps">
                                            <div class="flex flex-col"
                                                :class="{ 'opacity-50 cursor-not-allowed': slotProps.option.disabled }">
                                                <div class="flex items-center gap-2">
                                                    <span
                                                        :class="{ 'font-bold text-sm': true, 'text-gray-900': slotProps.option.disabled }">
                                                        {{ slotProps.option.nombre }}
                                                    </span>
                                                    <span v-if="slotProps.option.disabled"
                                                        class="bg-red-100 text-red-600 text-[9px] px-2 py-0.5 rounded-full font-black uppercase">
                                                        Sold Out
                                                    </span>
                                                </div>
                                                <small v-if="slotProps.option.disabled"
                                                    class="text-red-600 italic text-[10px]">
                                                    This corporate coupon has reached its usage limit.
                                                </small>
                                            </div>
                                        </template>
                                    </Select>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <label class="text-xs font-black text-blue-900 uppercase tracking-wider">
                                        Discount Code
                                    </label>
                                    <InputGroup>
                                        <InputText v-model="codigoVoucher" placeholder="Enter code"
                                            class="border-blue-300 uppercase" :disabled="!empresaCupon" />
                                        <Button label="Validate" icon="pi pi-ticket"
                                            class="!bg-blue-700 !border-blue-700 hover:!bg-blue-800"
                                            :loading="loadingCupon" :disabled="!codigoVoucher"
                                            @click="validarCuponLocal" />
                                    </InputGroup>
                                </div>
                            </div>

                            <div v-if="mensajeVoucher.texto" class="mt-3 text-center animate-fade-in">
                                <span :class="mensajeVoucher.tipo === 'success' ? 'text-green-600' : 'text-red-600'"
                                    class="text-xs font-bold flex items-center justify-center gap-2">
                                    <i
                                        :class="mensajeVoucher.tipo === 'success' ? 'pi pi-check-circle' : 'pi pi-times-circle'"></i>
                                    {{ mensajeVoucher.texto }}
                                </span>
                            </div>
                        </template>
                    </Card>

                    <!-- RESUMEN VISUAL DE DESCUENTO -->
                    <div v-if="cuponAplicado"
                        class="mt-6 p-4 bg-green-50 border border-green-200 rounded-xl animate-fade-in">
                        <div class="flex flex-col gap-2">
                            <div class="flex justify-between items-center text-gray-600">
                                <span class="text-sm font-medium">Base Price:</span>
                                <span class="text-sm line-through">USD {{ Number(total || 0).toFixed(2) }}</span>
                            </div>

                            <div class="flex justify-between items-center text-red-600 font-bold">
                                <div class="flex items-center gap-2">
                                    <i class="pi pi-tag text-xs"></i>
                                    <span class="text-sm uppercase tracking-tight">
                                        Corporate Discount ({{ tipoDescuentoAplicado === 'fijo' ? 'USD ' + descuentoAplicadoMonto : descuentoAplicadoMonto + '%' }}):
                                    </span>
                                </div>
                                <span class="text-sm">- USD {{ Number(montoDescuentoEfectivo || 0).toFixed(2) }}</span>
                            </div>

                            <Divider class="!my-1" />

                            <div class="flex justify-between items-center">
                                <span class="text-blue-900 font-black uppercase text-xs tracking-widest">
                                    Total to Pay:
                                </span>
                                <div class="flex flex-col items-end">
                                    <span class="text-2xl font-black text-green-700 leading-none">
                                        USD {{ Number(totalFinalConDescuento || 0).toFixed(2) }}
                                    </span>
                                    <small class="text-[10px] text-green-600 font-bold uppercase tracking-tighter">
                                        Benefit applied successfully!
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                </template>
            </Card>
        </div>
        <!--         DATOS DE FACTURACION             -->
        <!-- ======================================== -->
        <div class="text-green-iimp font-bold p-4">
            <Card class="mt-5 overflow-hidden">
                <template #header>
                    <div class="w-full py-3 text-xl font-bold text-center bg-lightblue-wmc border-blue-wmc">
                        Billing Information
                    </div>
                    <div v-if="missingFields.length > 0"
                        class="flex flex-col p-4 mb-6 text-orange-800 border-t-4 border-orange-300 bg-orange-50 rounded-lg shadow-sm"
                        role="alert">
                        <div class="flex items-center">
                            <i class="pi pi-exclamation-circle mr-2 text-xl"></i>
                            <span class="text-sm font-bold">Billing Information Incomplete</span>
                        </div>
                        <div class="mt-2 text-sm">
                            Please complete the following required fields to proceed to payment:
                            <ul class="list-disc ml-5 mt-1 font-semibold">
                                <li v-for="field in missingFields" :key="field">{{ field }}</li>
                            </ul>
                        </div>
                    </div>

                    <!-- <div v-if="tipoDocumentoEmpresa === 1 || tipoDocumentoEmpresa === 2"
                        class="col-span-2 mb-4 p-3 rounded-lg border flex items-center gap-3 animate-fade-in"
                        :class="tipoDocumentoEmpresa === 1 ? 'bg-blue-50 border-blue-200' : 'bg-purple-50 border-purple-200'">

                        <div :class="tipoDocumentoEmpresa === 1 ? 'bg-blue-500' : 'bg-purple-500'"
                            class="rounded-full p-2 flex-none">
                            <i class="pi pi-info-circle text-white text-sm"></i>
                        </div>

                        <div class="flex flex-col">
                            <span class="font-black text-[12px] uppercase tracking-wider"
                                :class="tipoDocumentoEmpresa === 1 ? 'text-blue-800' : 'text-purple-800'">
                                {{ tipoDocumentoEmpresa === 1 ? ' Receipt Information' :
                                    'Invoice Information' }}
                            </span>

                            <p v-if="tipoDocumentoEmpresa === 1" class="text-xs font-medium text-blue-700">
                                <span class="italic opacity-80">
                                    By selecting <strong>DNI</strong>, an electronic <strong>Sales Receipt</strong> will
                                    be issued in the name of the individual.
                                </span>
                            </p>

                            <p v-else class="text-xs font-medium text-purple-700">
                                <span class="italic opacity-80">
                                    By selecting <strong>RUC</strong>, an electronic <strong>Commercial Invoice</strong>
                                    will be issued in the name of the company or legal entity.
                                </span>
                            </p>
                        </div>
                    </div> -->

                </template>

                <template #content>

                    <div v-if="showSuccessAlert"
                        class="mx-6 mb-6 p-4 rounded-xl bg-green-50 border border-green-200 flex items-center gap-4 animate-fade-in shadow-sm">
                        <div class="bg-green-500 rounded-full p-2 flex-none">
                            <i class="pi pi-check-circle text-white text-lg"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-green-900 font-black text-sm uppercase tracking-wide">Success</span>
                            <p class="text-green-800 text-sm font-medium leading-tight">
                                Data found and loaded successfully.
                            </p>
                        </div>
                        <Button icon="pi pi-times" class="p-button-text p-button-rounded text-green-400 ml-auto"
                            @click="showSuccessAlert = false" />
                    </div>
                    <div v-if="showManualAlert"
                        class="mx-6 mb-6 p-4 rounded-xl bg-blue-50 border border-blue-200 flex items-center gap-4 animate-fade-in shadow-sm">
                        <div class="bg-blue-500 rounded-full p-2 flex-none">
                            <i class="pi pi-info-circle text-white text-lg"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-blue-900 font-black text-sm uppercase tracking-wide">Information</span>
                            <p class="text-blue-800 text-sm font-medium leading-tight">
                                Record not found. Please fill in the billing details manually to proceed with your
                                registration for the World Mining Congress.
                            </p>
                        </div>
                        <Button icon="pi pi-times" class="p-button-text p-button-rounded text-blue-400 ml-auto"
                            @click="showManualAlert = false" />
                    </div>

                    <div class="flex justify-center md:justify-end px-6 mb-6">
                    </div>
                    <div class="grid gap-6 m-6 md:grid-cols-2">
                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="col-span-3 sm:col-span-1">
                                <label class="block mb-1">Document Type <span class="text-red-600">*</span></label>
                                <Select v-model="tipoDocumentoEmpresa" :options="filteredDocTypes" optionLabel="name_en"
                                    optionValue="id" class="w-full border-green-iimp" @change="setTipoDocPago"
                                    translate="no" />
                                <small class="text-red-600" v-if="errors.tipoDocumentoEmpresa">{{
                                    errors.tipoDocumentoEmpresa }}</small>
                            </div>

                            <div class="col-span-3 sm:col-span-1">
                                <label class="block mb-1">Document Number <span class="text-red-600">*</span></label>
                                <InputGroup>
                                    <InputText v-model="documentoEmpresa" class="border-green-iimp"
                                        @keypress="onlyAlphanumericKey" @paste="onlyNumberKey" :maxlength="12" />
                                    <Button icon="pi pi-search" class="bg-green-iimp" @click="getEmpresaData"
                                        :loading="loading_doc" :disabled="!documentoEmpresa" />
                                </InputGroup>
                                <small v-if="dniMessageEmpresa" class="text-orange-600 font-bold block mt-1">
                                    <i class="pi pi-info-circle mr-1"></i> {{ dniMessageEmpresa }}
                                </small>

                                <div v-if="alphanumericMessage"
                                    class="text-orange-600 font-bold block mt-1 animate-bounce">
                                    <i class="pi pi-exclamation-triangle mr-1"></i> {{ alphanumericMessage }}
                                </div>
                                <small v-else-if="errors.documentoEmpresa" class="text-red-600 block mt-1">
                                    {{ errors.documentoEmpresa }}
                                </small>
                            </div>
                        </div>

                        <div class="w-full sm:col-span-1">
                            <label class="block mb-1">Business Name / Full Name <span
                                    class="text-red-600">*</span></label>
                            <InputText v-model="razonSocial" class="w-full border-green-iimp"
                                :disabled="esRuc20 || loading_doc" />
                            <small class="text-red-600" v-if="errors.razonSocial">{{ errors.razonSocial }}</small>
                        </div>
                    </div>

                    <div class="grid gap-6 m-6 md:grid-cols-2">
                        <div class="w-full sm:col-span-1">
                            <label class="block mb-1">Address <span class="text-red-600">*</span></label>
                            <InputText v-model="direccionEmpresa" class="w-full border-green-iimp"
                                :disabled="esRuc20 || loading_doc" />
                            <small class="text-red-600" v-if="errors.direccionEmpresa">{{ errors.direccionEmpresa
                                }}</small>
                        </div>

                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="w-full sm:col-span-1">
                                <label class="block mb-1">Billing Contact <span class="text-red-600">*</span></label>
                                <InputText v-model="responsable" class="w-full border-green-iimp"
                                    :disabled="loading_doc" />
                                <small class="text-red-600" v-if="errors.responsable">{{ errors.responsable }}</small>
                            </div>

                            <div class="w-full sm:col-span-1">
                                <label class="block mb-1">Billing Email <span class="text-red-600">*</span></label>
                                <InputText v-model="correo_facturador" class="w-full border-green-iimp"
                                    :disabled="loading_doc" />
                                <small class="text-red-600" v-if="errors.correo_facturador">{{ errors.correo_facturador
                                    }}</small>
                            </div>
                        </div>
                    </div>
                </template>
                <pre class="bg-red-100 text-red-700 p-4">
            Errores actuales: {{ errors }}
        </pre>
            </Card>
        </div>

        <!--         MODAL TIPO DOCUMENTO          -->
        <!-- ======================================== -->
        <Dialog v-model:visible="showInfoModal" modal header=" " :style="{ width: '25rem' }"
            :breakpoints="{ '1199px': '75vw', '575px': '90vw' }" class="custom-billing-modal" appendTo="body">
            <div class="flex flex-col items-center p-2 text-center">
                <div class="rounded-full w-20 h-20 flex items-center justify-center mb-6 animate-bounce-short"
                    :class="tipoDocumentoEmpresa === 1 ? 'bg-blue-50 text-blue-500' : 'bg-purple-50 text-purple-500'">
                    <i :class="modalConfig.icon" style="font-size: 2.5rem"></i>
                </div>

                <h3 class="text-xl font-black mb-2 uppercase tracking-tight" :class="modalConfig.colorClass">
                    {{ modalConfig.title }}
                </h3>

                <p class="text-slate-600 leading-relaxed mb-6 font-medium">
                    {{ modalConfig.message }}
                </p>

                <Button label="Got it"
                    :class="tipoDocumentoEmpresa === 1 ? '!bg-blue-600 !border-blue-600' : '!bg-purple-600 !border-purple-600'"
                    class="w-full font-bold py-3 shadow-lg" @click="showInfoModal = false" />
            </div>
        </Dialog>


        <Dialog v-model:visible="showWelcomeBillingModal" modal header=" " :style="{ width: '30rem' }"
            :breakpoints="{ '1199px': '75vw', '575px': '95vw' }" class="welcome-billing-modal" appendTo="body">

            <div class="flex flex-col items-center p-4 text-center">
                <div
                    class="rounded-full w-20 h-20 bg-orange-50 text-orange-500 flex items-center justify-center mb-6 shadow-sm border border-orange-100">
                    <i class="pi pi-exclamation-circle" style="font-size: 3rem"></i>
                </div>

                <h2 class="text-2xl font-black mb-4 text-slate-800 leading-tight uppercase tracking-tight">
                    Important Billing Notice
                </h2>

                <p class="text-slate-600 leading-relaxed mb-6 font-medium">
                    Dear participant, to ensure the correct issuance of your electronic payment documents for the
                    <span class="text-blue-900 font-bold">World Mining Congress 2026</span>,
                    please carefully select your billing document type:
                </p>

                <div class="grid grid-cols-2 gap-4 w-full mb-8">
                    <div class="p-4 bg-blue-50 rounded-xl border border-blue-100 flex flex-col items-center">
                        <i class="pi pi-user text-blue-600 mb-2"></i>
                        <span class="text-xs font-black text-blue-800 uppercase">National ID (DNI)</span>
                        <span class="text-[10px] text-blue-600 font-bold italic">Sales Receipt Issuance</span>
                    </div>
                    <div class="p-4 bg-purple-50 rounded-xl border border-purple-100 flex flex-col items-center">
                        <i class="pi pi-building text-purple-600 mb-2"></i>
                        <span class="text-xs font-black text-purple-800 uppercase">Tax ID (RUC)</span>
                        <span class="text-[10px] text-purple-600 font-bold italic">Commercial Invoice Issuance</span>
                    </div>
                </div>

                <div class="bg-slate-50 p-4 rounded-lg mb-8 border-l-4 border-slate-400">
                    <p class="text-[11px] text-slate-500 text-left leading-tight italic">
                        * Please note that once the document is issued, any cancellations or changes are subject to
                        administrative validation. Avoid delays by selecting the correct option according to your
                        accounting
                        requirements.
                    </p>
                </div>

                <Button label="I understand, continue with registration"
                    class="w-full !bg-green-iimp !border-green-iimp font-black py-4 shadow-lg hover:scale-105 transition-transform"
                    @click="showWelcomeBillingModal = false" />
            </div>
        </Dialog>

    </div>

</template>
