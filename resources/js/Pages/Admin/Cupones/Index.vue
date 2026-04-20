<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import * as yup from 'yup';

const props = defineProps({
    cupones: Array
});

// ==========================================
// 1. ESTADOS BÁSICOS
// ==========================================
const searchQuery = ref('');
const isModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
// NUEVO: Estados para el modal del ojito (Ver Usos)
const isViewModalOpen = ref(false);
const viewingCupon = ref(null);

const editingCupon = ref(null);
const cuponToDelete = ref(null);
const showToast = ref(false);
const toastMessage = ref('');
const currentPage = ref(1);
const itemsPerPage = 8;

// ==========================================
// 2. FORMULARIO (Inertia)
// ==========================================
const form = useForm({
    codigo_cupon: '',
    tipo_descuento: 'porcentaje',
    valor: '',
    razon_social: '',
    tipo_documento: '6', // 6 = RUC por defecto para Empresas
    num_documento: '',
    eci_cod: '',
    limite_usos: '',
    fecha_inicio: '',
    fecha_fin: '',
    is_active: true
});

// ==========================================
// 3. ESQUEMA DE VALIDACIÓN (Yup)
// ==========================================
const schema = yup.object({
    codigo_cupon: yup.string().trim().required('El código del cupón es obligatorio'),
    tipo_descuento: yup.string().required('El tipo de descuento es obligatorio'),
    valor: yup.number().typeError('Debe ingresar un monto válido').positive('El valor debe ser mayor a 0').required('El valor del descuento es obligatorio'),
    razon_social: yup.string().trim().required('La razón social de la empresa es obligatoria'),
    tipo_documento: yup.string().required('Debe seleccionar un tipo de documento'),
    num_documento: yup.string().trim()
        .required('El número de documento es obligatorio')
        .test('len', 'El RUC debe tener exactamente 11 dígitos', function (val) {
            if (this.parent.tipo_documento === '6') return val?.length === 11;
            return val?.length > 0;
        }),
    limite_usos: yup.number().typeError('Debe ingresar un número entero').positive('Debe ser al menos 1').required('El límite de usos es obligatorio'),
    fecha_inicio: yup.string().required('La fecha de inicio es obligatoria'),
    fecha_fin: yup.string().required('La fecha de fin es obligatoria')
});

// ==========================================
// 4. LÓGICA DE VALIDACIÓN EN TIEMPO REAL
// ==========================================
const validateForm = async () => {
    try {
        await schema.validate(form.data(), { abortEarly: false });
        form.clearErrors();
        return true;
    } catch (err) {
        const validationErrors = {};
        err.inner?.forEach(e => {
            if (!validationErrors[e.path]) {
                validationErrors[e.path] = e.message;
            }
        });
        form.clearErrors();
        form.setError(validationErrors);
        return false;
    }
};

watch(
    () => form.data(),
    () => {
        if (isModalOpen.value) validateForm();
    },
    { deep: true }
);

// ==========================================
// 5. COMPUTADOS (Búsqueda y Paginación)
// ==========================================
const filteredCupones = computed(() => {
    const query = searchQuery.value.toLowerCase();
    return props.cupones.filter(cupon =>
        (cupon.codigo_cupon && cupon.codigo_cupon.toLowerCase().includes(query)) ||
        (cupon.razon_social && cupon.razon_social.toLowerCase().includes(query))
    );
});

watch(searchQuery, () => { currentPage.value = 1; });

const totalPages = computed(() => Math.max(1, Math.ceil(filteredCupones.value.length / itemsPerPage)));

const paginatedCupones = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredCupones.value.slice(start, start + itemsPerPage);
});

// NUEVAS Funciones de Navegación Completas
const goToFirstPage = () => { currentPage.value = 1; };
const prevPage = () => { if (currentPage.value > 1) currentPage.value--; };
const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++; };
const goToLastPage = () => { currentPage.value = totalPages.value; };

// ==========================================
// 6. MÉTODOS Y FUNCIONES
// ==========================================
const formatDateTime = (dateString) => dateString ? dateString.replace(' ', 'T').slice(0, 16) : '';

const displayToast = (message) => {
    toastMessage.value = message;
    showToast.value = true;
    setTimeout(() => showToast.value = false, 3000);
};

const openViewModal = (cupon) => {
    viewingCupon.value = cupon;
    isViewModalOpen.value = true;
};

const openModal = async (cupon = null) => {
    editingCupon.value = cupon;
    form.clearErrors();

    if (cupon) {
        form.codigo_cupon = cupon.codigo_cupon;
        form.tipo_descuento = cupon.tipo_descuento;
        form.valor = cupon.valor;
        form.razon_social = cupon.razon_social || '';
        form.tipo_documento = cupon.tipo_documento || '6';
        form.num_documento = cupon.num_documento || '';
        form.eci_cod = cupon.eci_cod || '';
        form.limite_usos = cupon.limite_usos;
        form.fecha_inicio = formatDateTime(cupon.fecha_inicio);
        form.fecha_fin = formatDateTime(cupon.fecha_fin);
        form.is_active = cupon.is_active === 1 || cupon.is_active === true;
    } else {
        form.reset();
        const hoy = new Date();
        form.fecha_inicio = hoy.toISOString().slice(0, 16);
    }

    isModalOpen.value = true;
    await validateForm();
};

const submit = async () => {
    const isValid = await validateForm();
    if (!isValid) return;

    const isEditing = !!editingCupon.value;
    const action = isEditing ? route('cupones.update', editingCupon.value.id) : route('cupones.store');
    const method = isEditing ? 'put' : 'post';

    form[method](action, {
        onSuccess: () => {
            isModalOpen.value = false;
            const identificador = form.codigo_cupon || 'del cupón';
            const message = isEditing
                ? `¡Actualizado! Los cambios en el cupón "${identificador}" se guardaron con éxito.`
                : `¡Excelente! El cupón "${identificador}" ha sido generado correctamente.`;

            displayToast(message);
            form.reset();
        },
        onError: (errors) => {
            if (errors.codigo_cupon) {
                displayToast(`Error: El código "${form.codigo_cupon}" ya está registrado.`);
            } else {
                displayToast("Vaya, hubo un inconveniente al procesar el cupón. Revisa los datos.");
            }
        },
        preserveScroll: true
    });
};

const confirmDelete = (cupon) => {
    cuponToDelete.value = cupon;
    isDeleteModalOpen.value = true;
};

const executeDelete = () => {
    if (cuponToDelete.value) {
        router.delete(route('cupones.destroy', cuponToDelete.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                isDeleteModalOpen.value = false;
                cuponToDelete.value = null;
                displayToast('Cupón eliminado exitosamente.');
            }
        });
    }
};
</script>

<template>

    <Head title="Gestión Comercial - Cupones" />

    <AuthenticatedLayout>
        <div class="space-y-6">

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div>
                    <h2 class="font-black text-2xl text-slate-800 tracking-tight">Cupones para Empresas</h2>
                    <p class="text-sm text-slate-500 mt-1">Administra los descuentos asignados a las empresas.</p>
                </div>

                <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
                    <div class="relative w-full sm:w-64">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <input v-model="searchQuery" type="text" placeholder="Buscar código o empresa..."
                            class="w-full pl-9 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all outline-none shadow-sm text-sm" />
                    </div>
                    <button @click="openModal()"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-sm transition-all active:scale-95 whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        Nuevo Cupón
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50/80">
                            <tr>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-slate-500 uppercase tracking-widest">Código / Estado</th>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-slate-500 uppercase tracking-widest">Empresa Asignada</th>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-slate-500 uppercase tracking-widest">Descuento</th>
                                <th class="px-6 py-4 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest">Usos</th>
                                <th class="px-6 py-4 text-right text-[10px] font-black text-slate-500 uppercase tracking-widest">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <tr v-for="cupon in paginatedCupones" :key="cupon.id" class="group hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col gap-1">
                                        <span class="font-black text-sm text-slate-800 tracking-tight">{{ cupon.codigo_cupon }}</span>
                                        <div>
                                            <span v-if="cupon.is_active" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-green-50 text-green-600 text-[9px] font-bold uppercase tracking-widest border border-green-200/60">
                                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Activo
                                            </span>
                                            <span v-else class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-100 text-slate-500 text-[9px] font-bold uppercase tracking-widest border border-slate-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Inactivo
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <p class="font-bold text-xs text-slate-700 uppercase">{{ cupon.razon_social }}</p>
                                    <p class="text-[10px] text-slate-400 font-medium">RUC: {{ cupon.num_documento }} | ECI: {{ cupon.eci_cod }}</p>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <span class="font-black text-indigo-600 text-lg leading-none">
                                            {{ cupon.tipo_descuento === 'porcentaje' ? cupon.valor + '%' : '$' + cupon.valor }}
                                        </span>
                                        <span class="text-[9px] text-slate-400 font-bold uppercase tracking-widest leading-none">{{ cupon.tipo_descuento }}</span>
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <div class="inline-flex flex-col items-center">
                                        <span class="text-sm font-bold text-slate-700">
                                            <span :class="cupon.usos_actuales >= cupon.limite_usos ? 'text-red-500' : 'text-indigo-600'">{{ cupon.usos_actuales }}</span>
                                            <span class="text-slate-400 font-normal"> / {{ cupon.limite_usos }}</span>
                                        </span>
                                        <div class="w-16 h-1.5 bg-slate-100 rounded-full mt-1 overflow-hidden">
                                            <div class="h-full bg-indigo-500 rounded-full transition-all" :style="{ width: Math.min((cupon.usos_actuales / cupon.limite_usos) * 100, 100) + '%' }"></div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <div class="flex justify-end gap-2">
                                        <button @click="router.get(route('cupones.show', cupon.id))"
                                            class="p-2 text-emerald-600 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition-colors"
                                            title="Ver reporte detallado">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>

                                        <button @click="openModal(cupon)"
                                            class="p-2 text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors"
                                            title="Editar Cupón">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button @click="confirmDelete(cupon)"
                                            class="p-2 text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-colors"
                                            title="Eliminar Cupón">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredCupones.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center text-slate-500 font-medium">
                                    No se encontraron cupones que coincidan con la búsqueda.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="filteredCupones.length > 0" class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <span class="text-xs text-slate-500 font-medium">
                        Mostrando <span class="font-bold text-slate-700">{{ (currentPage - 1) * itemsPerPage + 1 }}</span> a
                        <span class="font-bold text-slate-700">{{ Math.min(currentPage * itemsPerPage, filteredCupones.length) }}</span>
                        de <span class="font-bold text-slate-700">{{ filteredCupones.length }}</span> cupones
                    </span>

                    <div class="flex items-center gap-1.5">
                        <button @click="goToFirstPage" :disabled="currentPage === 1"
                            class="px-3 py-1.5 text-[11px] font-bold rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all shadow-sm uppercase tracking-wider"
                            title="Ir al inicio">
                            Inicio
                        </button>

                        <button @click="prevPage" :disabled="currentPage === 1"
                            class="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all shadow-sm"
                            title="Página anterior">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </button>

                        <span class="px-3 py-1 text-xs font-black text-slate-700 bg-slate-200/50 rounded-md mx-1">
                            Pág. {{ currentPage }} de {{ totalPages }}
                        </span>

                        <button @click="nextPage" :disabled="currentPage === totalPages"
                            class="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all shadow-sm"
                            title="Página siguiente">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </button>

                        <button @click="goToLastPage" :disabled="currentPage === totalPages"
                            class="px-3 py-1.5 text-[11px] font-bold rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all shadow-sm uppercase tracking-wider"
                            title="Ir al final">
                            Final
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <Teleport to="body">
        <Transition enter-active-class="transform ease-out duration-300 transition" enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2" enter-to-class="translate-y-0 opacity-100 sm:translate-x-0" leave-active-class="transition ease-in duration-100" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="showToast" class="fixed top-6 right-6 z-[200] max-w-sm w-full bg-slate-900 shadow-2xl rounded-2xl pointer-events-auto flex ring-1 ring-black ring-opacity-5 overflow-hidden">
                <div class="p-4 flex items-center w-full">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-3 w-0 flex-1 pt-0.5">
                        <p class="text-sm font-bold text-white">{{ toastMessage }}</p>
                    </div>
                </div>
            </div>
        </Transition>

        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
            <div v-if="isViewModalOpen" class="fixed inset-0 z-[150] overflow-y-auto bg-slate-900/60 backdrop-blur-sm">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden relative">
                        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                            <h3 class="text-lg font-black text-slate-800 uppercase tracking-tight">Reporte de Usos</h3>
                            <button @click="isViewModalOpen = false" class="text-slate-400 hover:text-slate-600 bg-white p-2 rounded-full border border-slate-200 transition-colors">✕</button>
                        </div>
                        <div class="p-6 space-y-6">
                            <div class="flex flex-col items-center text-center">
                                <div class="w-20 h-20 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center mb-4 border border-emerald-100 shadow-sm ring-4 ring-emerald-50/50">
                                    <span class="text-3xl font-black">{{ viewingCupon?.usos_actuales }}</span>
                                </div>
                                <h4 class="text-xl font-black text-slate-800 tracking-tight">{{ viewingCupon?.codigo_cupon }}</h4>
                                <p class="text-sm text-slate-500 font-medium mt-1">Este cupón ha sido utilizado <span class="font-bold text-slate-700">{{ viewingCupon?.usos_actuales }}</span> veces de un límite máximo de <span class="font-bold text-slate-700">{{ viewingCupon?.limite_usos }}</span>.</p>
                            </div>

                            <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4">
                                <h5 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Personas que lo utilizaron</h5>

                                <div v-if="viewingCupon?.inscritos && viewingCupon.inscritos.length > 0" class="space-y-2 max-h-48 overflow-y-auto custom-scrollbar pr-2">
                                    <div v-for="item in viewingCupon.inscritos" :key="item.id" class="flex justify-between items-center bg-white p-3 border border-slate-100 rounded-xl shadow-sm">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-bold text-slate-700 leading-tight">
                                                {{ item.persona?.nombres }} {{ item.persona?.apellido_paterno }} {{ item.persona?.apellido_materno }}
                                            </span>

                                            <div class="flex items-center gap-2 mt-0.5">
                                                <span class="text-[10px] text-slate-500 font-bold bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200 uppercase">
                                                    {{ item.persona?.tipo_documento?.name_es || 'DOC' }}: {{ item.persona?.documento }}
                                                </span>
                                                <span class="text-slate-300">|</span>
                                                <span class="text-[10px] text-slate-400 uppercase font-medium">
                                                    ID Pago: #{{ item.id }}
                                                </span>
                                            </div>
                                        </div>

                                        <span class="text-xs font-mono text-slate-400">
                                            {{ item.created_at ? item.created_at.slice(0, 10) : '---' }}
                                        </span>
                                    </div>
                                </div>

                                <div v-else class="text-center py-5">
                                    <svg class="mx-auto h-8 w-8 text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                    <p class="text-sm font-bold text-slate-500">Sin registros detallados</p>
                                    <p class="text-[11px] text-slate-400 mt-1 max-w-[250px] mx-auto">No hay personas registradas o la información detallada no ha sido cargada desde el servidor.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
            <div v-if="isModalOpen" class="fixed inset-0 z-[150] overflow-y-auto bg-slate-900/60 backdrop-blur-sm">
                <div class="flex min-h-full items-center justify-center p-4 py-10">
                    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-3xl overflow-hidden relative">
                        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                            <h3 class="text-xl font-black text-slate-800 uppercase tracking-tight">{{ editingCupon ? 'Editar Cupón de Empresa' : 'Crear Cupón de Empresa' }}</h3>
                            <button @click="isModalOpen = false" class="text-slate-400 hover:text-slate-600 bg-white p-2 rounded-full border border-slate-200 transition-colors">✕</button>
                        </div>
                        <form @submit.prevent="submit" class="p-6 space-y-6">
                            <div>
                                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2 mb-4">Detalles del Descuento</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="md:col-span-1">
                                        <label class="block text-xs font-bold text-slate-700 mb-1 uppercase">Código <span class="text-red-500">*</span></label>
                                        <input v-model="form.codigo_cupon" type="text" placeholder="Ej: MINEX-2026" :class="{ 'border-red-500 ring-2 ring-red-100': form.errors.codigo_cupon }" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-indigo-500 outline-none text-sm text-slate-700 font-bold uppercase" />
                                        <span v-if="form.errors.codigo_cupon" class="block text-red-500 text-[10px] mt-1 font-bold">{{ form.errors.codigo_cupon }}</span>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1 uppercase">Tipo <span class="text-red-500">*</span></label>
                                        <select v-model="form.tipo_descuento" :class="{ 'border-red-500': form.errors.tipo_descuento }" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-4 outline-none text-sm font-medium">
                                            <option value="porcentaje">Porcentaje (%)</option>
                                            <option value="fijo">Monto Fijo ($)</option>
                                        </select>
                                        <span v-if="form.errors.tipo_descuento" class="block text-red-500 text-[10px] mt-1 font-bold">{{ form.errors.tipo_descuento }}</span>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1 uppercase">Valor <span class="text-red-500">*</span></label>
                                        <input v-model="form.valor" type="number" step="0.01" :class="{ 'border-red-500': form.errors.valor }" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-4 outline-none text-sm font-bold" />
                                        <span v-if="form.errors.valor" class="block text-red-500 text-[10px] mt-1 font-bold">{{ form.errors.valor }}</span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2 mb-4">Datos de la Empresa</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-bold text-slate-700 mb-1 uppercase">Razón Social <span class="text-red-500">*</span></label>
                                        <input v-model="form.razon_social" type="text" :class="{ 'border-red-500': form.errors.razon_social }" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-4 outline-none text-sm uppercase" />
                                        <span v-if="form.errors.razon_social" class="block text-red-500 text-[10px] mt-1 font-bold">{{ form.errors.razon_social }}</span>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1 uppercase">Tipo Doc. <span class="text-red-500">*</span></label>
                                        <select v-model="form.tipo_documento" :class="{ 'border-red-500': form.errors.tipo_documento }" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-4 outline-none text-sm font-medium">
                                            <option value="6">RUC (6)</option>
                                        </select>
                                        <span v-if="form.errors.tipo_documento" class="block text-red-500 text-[10px] mt-1 font-bold">{{ form.errors.tipo_documento }}</span>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1 uppercase">Nº Documento <span class="text-red-500">*</span></label>
                                        <input v-model="form.num_documento" type="text" :class="{ 'border-red-500': form.errors.num_documento }" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-4 outline-none text-sm font-bold" />
                                        <span v-if="form.errors.num_documento" class="block text-red-500 text-[10px] mt-1 font-bold">{{ form.errors.num_documento }}</span>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-bold text-slate-700 mb-1 uppercase">Código ECI <span class="text-red-500">*</span></label>
                                        <input v-model="form.eci_cod" type="text" :class="{ 'border-red-500': form.errors.eci_cod }" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-4 outline-none text-sm font-bold" />
                                        <span v-if="form.errors.eci_cod" class="block text-red-500 text-[10px] mt-1 font-bold">{{ form.errors.eci_cod }}</span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2 mb-4">Límites y Vigencia</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="md:col-span-2 grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 mb-1 uppercase">Usos Permitidos <span class="text-red-500">*</span></label>
                                            <input v-model="form.limite_usos" type="number" min="1" :class="{ 'border-red-500': form.errors.limite_usos }" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-4 outline-none text-sm font-bold" />
                                            <span v-if="form.errors.limite_usos" class="block text-red-500 text-[10px] mt-1 font-bold">{{ form.errors.limite_usos }}</span>
                                        </div>
                                        <div class="flex items-center pt-5">
                                            <label class="flex items-center gap-3 cursor-pointer">
                                                <input type="checkbox" v-model="form.is_active" class="w-5 h-5 rounded border-slate-300 text-green-600 focus:ring-green-500" />
                                                <span class="text-sm font-bold text-slate-700 uppercase">Habilitar Cupón</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1 uppercase">Inicio <span class="text-red-500">*</span></label>
                                        <input v-model="form.fecha_inicio" type="datetime-local" :class="{ 'border-red-500': form.errors.fecha_inicio }" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-4 outline-none text-sm font-medium" />
                                        <span v-if="form.errors.fecha_inicio" class="block text-red-500 text-[10px] mt-1 font-bold">{{ form.errors.fecha_inicio }}</span>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1 uppercase">Fin <span class="text-red-500">*</span></label>
                                        <input v-model="form.fecha_fin" type="datetime-local" :class="{ 'border-red-500': form.errors.fecha_fin }" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-4 outline-none text-sm font-medium" />
                                        <span v-if="form.errors.fecha_fin" class="block text-red-500 text-[10px] mt-1 font-bold">{{ form.errors.fecha_fin }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                                <button type="button" @click="isModalOpen = false" class="px-6 py-2.5 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors uppercase">Cancelar</button>
                                <button type="submit" :disabled="form.processing" class="px-6 py-2.5 text-sm font-bold text-white bg-indigo-600 rounded-xl shadow-lg hover:bg-indigo-700 transition-colors disabled:opacity-50 uppercase">
                                    {{ editingCupon ? 'Guardar Cambios' : 'Confirmar Cupón' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Transition>

        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
            <div v-if="isDeleteModalOpen" class="fixed inset-0 z-[150] overflow-y-auto bg-slate-900/60 backdrop-blur-sm">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm p-6 text-center">
                        <div class="w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-black text-slate-800 mb-2 uppercase">¿Eliminar Cupón?</h3>
                        <p class="text-sm text-slate-500 mb-6">
                            Vas a eliminar el cupón <span class="font-bold text-slate-800 tracking-tight">{{ cuponToDelete?.codigo_cupon }}</span>. Esta acción no se puede deshacer.
                        </p>
                        <div class="flex gap-3">
                            <button @click="isDeleteModalOpen = false" class="flex-1 py-3 text-sm font-bold text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors uppercase">Cancelar</button>
                            <button @click="executeDelete" class="flex-1 py-3 text-sm font-bold text-white bg-red-600 rounded-xl shadow-lg hover:bg-red-700 transition-colors uppercase">Eliminar</button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
</style>
