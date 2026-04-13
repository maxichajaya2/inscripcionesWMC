<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    cupon: Object,
    inscritos: Object,      // Paginación de Laravel (para la tabla)
    inscripciones: Array,   // Datos mapeados con info de Niubiz (para el modal)
    filters: Object
});

// ==========================================
// ESTADOS, BÚSQUEDA Y MODAL
// ==========================================
const search = ref(props.filters.search || '');
const showModal = ref(false);
const selectedInscrito = ref(null);

// Buscador en tiempo real (Recarga la página vía Inertia)
watch(search, (value) => {
    router.get(route('cupones.show', props.cupon.id), { search: value }, {
        preserveState: true,
        replace: true
    });
});

// NUEVO: Navegación de Paginación para Laravel
const goToPage = (page) => {
    if (page < 1 || page > props.inscritos.last_page) return;

    router.get(route('cupones.show', props.cupon.id), {
        search: search.value,
        page: page
    }, {
        preserveState: true,
        preserveScroll: true
    });
};

const openDetails = (inscritoId) => {
    // Buscamos en el array de inscripciones el objeto que tiene toda la info detallada
    const detallado = props.inscripciones.find(i => i.id === inscritoId);
    if (detallado) {
        selectedInscrito.value = detallado;
        showModal.value = true;
        document.body.style.overflow = 'hidden';
    }
};

const closeModal = () => {
    showModal.value = false;
    setTimeout(() => selectedInscrito.value = null, 300);
    document.body.style.overflow = 'auto';
};

// ==========================================
// UTILIDADES VISUALES
// ==========================================
const getInitials = (name) => {
    if (!name || name === 'Sin nombre') return 'NN';
    const parts = name.trim().split(' ');
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return name.substring(0, 2).toUpperCase();
};

const statusStyle = (status) => {
    if (status === 'PAGADO') return 'bg-emerald-50 text-emerald-700 border-emerald-200 ring-emerald-600/20';
    return 'bg-slate-50 text-slate-600 border-slate-200 ring-slate-500/20';
};
</script>

<template>

    <Head :title="'Reporte Cupón - ' + cupon.codigo_cupon" />

    <AuthenticatedLayout>
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8 py-8">

            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex items-center gap-4">
                    <Link :href="route('cupones.index')"
                        class="p-2.5 bg-slate-50 border border-slate-200 rounded-xl hover:bg-slate-100 transition-all text-slate-600 shadow-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <div>
                        <h2 class="text-2xl font-black text-slate-900 tracking-tight">Cupón: {{ cupon.codigo_cupon }}</h2>
                        <p class="text-sm text-slate-500 mt-1 uppercase font-bold tracking-wider">Empresa: {{ cupon.razon_social }}</p>
                    </div>
                </div>

                <!-- <div class="relative w-full sm:w-80">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                    </span>
                    <input v-model="search" type="text" placeholder="Buscar inscrito o documento..." class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all text-sm outline-none text-slate-700 placeholder-slate-400" />
                </div> -->
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center font-black text-xl border border-indigo-100">
                        {{ cupon.usos_actuales }}
                    </div>
                    <div>
                        <p class="text-xs font-black text-slate-400 uppercase tracking-widest">Usos Registrados</p>
                        <p class="text-sm font-bold text-slate-700">Límite: {{ cupon.limite_usos }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 flex flex-col overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">Participante</th>
                                <th class="px-6 py-4 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">Documento</th>
                                <th class="px-6 py-4 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider">Fecha de Uso</th>
                                <th class="px-6 py-4 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="item in inscritos.data" :key="item.id" class="hover:bg-slate-50/80 transition-colors group">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-xs shadow-sm">
                                            {{ getInitials(item.persona?.nombres) }}
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-sm font-bold text-slate-900">{{ item.persona?.nombres }} {{ item.persona?.apellido_paterno }}</span>
                                            <span class="text-[11px] text-slate-500 italic">ID Inscripción: #{{ item.id }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 font-medium">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase mr-1">{{ item.persona?.tipo_documento?.name_es }}:</span>
                                    {{ item.persona?.documento }}
                                </td>
                                <td class="px-6 py-4 text-center text-sm text-slate-500 font-mono">
                                    {{ item.created_at.slice(0, 16).replace('T', ' ') }}
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <button @click="openDetails(item.id)"
                                        class="inline-flex items-center justify-center p-2 rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-indigo-600 hover:border-indigo-200 hover:bg-indigo-50 transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        title="Ver detalle completo">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="inscritos.data.length === 0">
                                <td colspan="4" class="px-6 py-20">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="bg-slate-50 p-4 rounded-full mb-4">
                                            <svg class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                            </svg>
                                        </div>
                                        <h3 class="text-sm font-black text-slate-600 uppercase tracking-wider">Sin registros</h3>
                                        <p class="text-xs text-slate-400 mt-1 max-w-[200px] text-center leading-relaxed">No se encontraron usos registrados para este cupón en la base de datos.</p>
                                        <button v-if="search" @click="search = ''" class="mt-4 text-[10px] font-bold text-indigo-600 hover:text-indigo-800 uppercase tracking-widest border-b border-indigo-200">
                                            Limpiar búsqueda
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="inscritos.data.length > 0" class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <span class="text-xs text-slate-500 font-medium">
                        Mostrando <span class="font-bold text-slate-700">{{ inscritos.from || 0 }}</span> a
                        <span class="font-bold text-slate-700">{{ inscritos.to || 0 }}</span>
                        de <span class="font-bold text-slate-700">{{ inscritos.total }}</span> registros
                    </span>

                    <div class="flex items-center gap-1.5">
                        <button @click="goToPage(1)" :disabled="inscritos.current_page === 1"
                            class="px-3 py-1.5 text-[11px] font-bold rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all shadow-sm uppercase tracking-wider"
                            title="Ir al inicio">
                            Inicio
                        </button>

                        <button @click="goToPage(inscritos.current_page - 1)" :disabled="inscritos.current_page === 1"
                            class="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all shadow-sm"
                            title="Página anterior">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </button>

                        <span class="px-3 py-1 text-xs font-black text-slate-700 bg-slate-200/50 rounded-md mx-1">
                            Pág. {{ inscritos.current_page }} de {{ Math.max(1, inscritos.last_page) }}
                        </span>

                        <button @click="goToPage(inscritos.current_page + 1)" :disabled="inscritos.current_page === inscritos.last_page"
                            class="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all shadow-sm"
                            title="Página siguiente">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </button>

                        <button @click="goToPage(inscritos.last_page)" :disabled="inscritos.current_page === inscritos.last_page"
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
        <Transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="showModal" class="fixed inset-0 z-[150] overflow-y-auto">
                <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="closeModal"></div>

                <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                    <Transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" enter-to-class="opacity-100 translate-y-0 sm:scale-100" leave-active-class="ease-in duration-200" leave-from-class="opacity-100 translate-y-0 sm:scale-100" leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">

                        <div v-if="selectedInscrito" class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 w-full max-w-4xl border border-slate-200">

                            <div class="flex flex-col max-h-[90vh]">

                                <div class="shrink-0 bg-white/90 backdrop-blur-md px-6 py-5 border-b border-slate-200 flex items-center justify-between z-10">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-500">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-black text-slate-900 leading-tight">Detalle de Inscripción #{{ selectedInscrito.id }}</h3>
                                            <p class="text-xs text-slate-500 mt-0.5">Registrado el {{ selectedInscrito.fecha_registro }}</p>
                                        </div>
                                    </div>
                                    <button @click="closeModal" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors focus:outline-none focus:ring-2 focus:ring-orange-500">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="flex-1 overflow-y-auto px-6 py-6 sm:p-8 bg-slate-50/50 space-y-8 custom-scrollbar">

                                    <div>
                                        <h4 class="text-xs font-black tracking-widest text-slate-400 uppercase mb-4 pl-1">Detalles del Programa</h4>
                                        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">

                                                <div class="p-4">
                                                    <p class="text-[10px] uppercase font-bold text-slate-400">Categoría de Inscripción</p>
                                                    <p v-if="selectedInscrito.categoria_inscripcion" class="text-sm font-bold text-slate-900 mt-1">
                                                        {{ selectedInscrito.categoria_inscripcion.nombre_es }}
                                                    </p>
                                                    <p v-else class="text-sm font-medium text-slate-400 mt-1 italic">
                                                        No especificada
                                                    </p>
                                                </div>

                                                <div class="p-4">
                                                    <p class="text-[10px] uppercase font-bold text-slate-400 mb-2">Cursos / Viajes Adicionales</p>

                                                    <div v-if="selectedInscrito.categoria_cursos_viajes && selectedInscrito.categoria_cursos_viajes.length > 0" class="flex flex-col gap-3 mt-1">
                                                        <div v-for="(curso, index) in selectedInscrito.categoria_cursos_viajes" :key="index" class="flex flex-col items-start gap-1 border-l-2 border-cyan-200 pl-2">
                                                            <p class="text-sm font-bold text-slate-900 leading-tight">
                                                                {{ curso.nombre_es }}
                                                            </p>
                                                            <span v-if="curso.tipo" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-cyan-50 text-cyan-700 border border-cyan-200 uppercase tracking-wide">
                                                                {{ curso.tipo }}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <p v-else class="text-sm font-medium text-slate-400 mt-1 italic">
                                                        Ninguno
                                                    </p>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-black tracking-widest text-slate-400 uppercase mb-4 pl-1">Información del Participante</h4>
                                        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                                                <div class="p-4">
                                                    <p class="text-[10px] uppercase font-bold text-slate-400">Nombre Completo</p>
                                                    <p class="text-sm font-bold text-slate-900 mt-1">{{ selectedInscrito.nombres }}</p>
                                                </div>
                                                <div class="p-4">
                                                    <p class="text-[10px] uppercase font-bold text-slate-400">Documento</p>
                                                    <p class="text-sm font-bold text-slate-900 mt-1">{{ selectedInscrito.documento }}</p>
                                                </div>
                                                <div class="p-4">
                                                    <p class="text-[10px] uppercase font-bold text-slate-400">Correo Electrónico</p>
                                                    <p class="text-sm font-bold text-orange-600 mt-1 truncate" :title="selectedInscrito.email">{{ selectedInscrito.email }}</p>
                                                </div>
                                                <div class="p-4">
                                                    <p class="text-[10px] uppercase font-bold text-slate-400">Cargo / Origen</p>
                                                    <p class="text-sm font-bold text-slate-900 mt-1">{{ selectedInscrito.cargo }} <span class="text-slate-400 font-normal">({{ selectedInscrito.origen }})</span></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <h4 class="text-xs font-black tracking-widest text-slate-400 uppercase mb-4 pl-1">Detalles de Facturación</h4>
                                        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                                            <div class="p-4 bg-slate-50/80 border-b border-slate-100 flex items-center justify-between">
                                                <span class="text-xs font-bold text-slate-600 uppercase">Monto Total a Pagar</span>
                                                <span class="text-lg font-black text-emerald-600">${{ selectedInscrito.facturacion.monto_total }}</span>
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                                                <div class="p-4 space-y-3">
                                                    <div>
                                                        <p class="text-[10px] uppercase font-bold text-slate-400">Razón Social</p>
                                                        <p class="text-sm font-bold text-slate-900">{{ selectedInscrito.facturacion.razon_social }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="text-[10px] uppercase font-bold text-slate-400">Doc. Facturación</p>
                                                        <p class="text-sm font-mono text-slate-700">
                                                            <span class="font-bold mr-1">{{ selectedInscrito.facturacion.tipo_documento }}:</span>
                                                            {{ selectedInscrito.facturacion.ruc }}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="p-4 space-y-3">
                                                    <div>
                                                        <p class="text-[10px] uppercase font-bold text-slate-400">Dirección</p>
                                                        <p class="text-sm font-medium text-slate-700">{{ selectedInscrito.facturacion.direccion }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="text-[10px] uppercase font-bold text-slate-400">Correo Facturación</p>
                                                        <p class="text-sm font-medium text-slate-700">{{ selectedInscrito.facturacion.correo_facturador }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex justify-between items-end mb-4 pl-1">
                                            <h4 class="text-xs font-black tracking-widest text-slate-400 uppercase">Registro Niubiz (Pagos)</h4>
                                            <span :class="[statusStyle(selectedInscrito.estado_pago), 'px-2 py-0.5 rounded text-[10px] font-bold border']">{{ selectedInscrito.estado_pago }}</span>
                                        </div>
                                    </div>

                                    <div>
                                        <h4 class="text-xs font-black tracking-widest text-slate-400 uppercase mb-4 pl-1">Descuentos</h4>
                                        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">

                                            <div v-if="selectedInscrito.cupon" class="p-4 bg-indigo-50/50 flex items-center justify-between">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 shadow-sm border border-indigo-200 shrink-0">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <p class="text-[10px] uppercase font-bold text-indigo-400 tracking-widest">
                                                            Cupón de: {{ selectedInscrito.cupon.razon_social }}
                                                        </p>
                                                        <p class="text-sm font-black text-indigo-700 uppercase tracking-tight mt-0.5">
                                                            {{ selectedInscrito.cupon.codigo }}
                                                        </p>
                                                    </div>
                                                </div>
                                                <span class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-white text-indigo-600 border border-indigo-200 shadow-sm uppercase tracking-wider">
                                                    Aplicado
                                                </span>
                                            </div>

                                            <div v-else class="p-6 text-center bg-slate-50/50 border-dashed border-slate-200">
                                                <svg class="mx-auto h-6 w-6 text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z" />
                                                </svg>
                                                <p class="text-sm font-bold text-slate-500">Sin cupón de descuento</p>
                                                <p class="text-xs text-slate-400 mt-0.5">El participante no aplicó ningún código promocional.</p>
                                            </div>

                                        </div>
                                    </div>

                                    <div>
                                        <h4 class="text-xs font-black tracking-widest text-slate-400 uppercase mb-4 pl-1">
                                            Accesos y Viaje
                                        </h4>
                                        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">

                                                <div class="p-6 flex flex-col items-center justify-center text-center">
                                                    <p class="text-[10px] uppercase font-bold text-slate-400 mb-4 w-full text-left">Código QR de Acceso</p>

                                                    <div v-if="selectedInscrito.qr" class="flex flex-col items-center">
                                                        <img :src="`https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${selectedInscrito.qr}`"
                                                            alt="Código QR"
                                                            class="w-32 h-32 p-2 border border-slate-200 rounded-xl shadow-sm bg-white mb-2 transition-transform hover:scale-105" />
                                                        <p class="text-[11px] font-mono text-slate-500 font-medium tracking-widest">
                                                            ID: {{ selectedInscrito.qr }}</p>
                                                    </div>

                                                    <div v-else class="flex flex-col items-center py-6 w-full bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
                                                        <p class="text-sm font-medium text-slate-400 italic">QR no asignado</p>
                                                    </div>
                                                </div>

                                                <div class="p-6 flex flex-col items-center justify-center text-center">
                                                    <p class="text-[10px] uppercase font-bold text-slate-400 mb-4 w-full text-left">Cupón de Viaje</p>

                                                    <div v-if="selectedInscrito.cupon_viaje" class="w-full">
                                                        <div class="px-4 py-6 bg-cyan-50/80 border border-cyan-200 rounded-xl border-dashed flex flex-col items-center justify-center h-full">
                                                            <p class="text-2xl font-black text-cyan-700 uppercase tracking-widest">
                                                                {{ selectedInscrito.cupon_viaje }}</p>
                                                            <p class="text-[10px] font-bold text-cyan-500 mt-2 uppercase tracking-wider">
                                                                Código de Viaje</p>
                                                        </div>
                                                    </div>

                                                    <div v-else class="flex flex-col items-center py-6 w-full bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
                                                        <p class="text-sm font-medium text-slate-400 italic">Sin cupón de viaje</p>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="shrink-0 bg-white/90 backdrop-blur-md px-6 py-4 border-t border-slate-100 flex justify-end gap-3 z-10">
                                    <button type="button" @click="closeModal" class="px-6 py-2.5 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors uppercase">Cerrar Detalles</button>
                                </div>
                            </div>
                        </div>
                    </Transition>
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
    background: #cbd5e1;
    border-radius: 10px;
}
</style>
