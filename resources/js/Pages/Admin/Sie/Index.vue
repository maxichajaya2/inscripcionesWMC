<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    inscritos: {
        type: Array,
        default: () => []
    }
});

// ==========================================
// MODAL DE CONFIRMACIÓN PERSONALIZADO
// ==========================================
const showConfirmModal = ref(false);
const confirmMessage = ref('');
const pendingAction = ref(null);

const openConfirm = (message, action) => {
    confirmMessage.value = message;
    pendingAction.value = action;
    showConfirmModal.value = true;
    document.body.style.overflow = 'hidden';
};

const executeConfirm = () => {
    if (pendingAction.value) pendingAction.value();
    closeConfirm();
};

const closeConfirm = () => {
    showConfirmModal.value = false;
    pendingAction.value = null;
    if (!showModal.value && !showJsonModal.value && !isSending.value) {
        document.body.style.overflow = 'auto';
    }
};

// ==========================================
// ESTADOS, BÚSQUEDA, FECHAS Y MODAL DETALLES
// ==========================================
const searchQuery = ref('');
const dateFrom = ref('');
const dateTo = ref('');

const currentPage = ref(1);
const itemsPerPage = 10;

const showModal = ref(false);
const selectedInscrito = ref(null);

const openDetails = (inscrito) => {
    selectedInscrito.value = inscrito;
    showModal.value = true;
    document.body.style.overflow = 'hidden';
};

const closeModal = () => {
    showModal.value = false;
    setTimeout(() => selectedInscrito.value = null, 300);
    document.body.style.overflow = 'auto';
};

// ==========================================
// ESTADOS Y MODAL PARA JSON
// ==========================================
const showJsonModal = ref(false);
const selectedJsonData = ref(null);

const openJsonPreview = (inscrito) => {
    selectedJsonData.value = inscrito;
    showJsonModal.value = true;
    document.body.style.overflow = 'hidden';
};

const closeJsonPreview = () => {
    showJsonModal.value = false;
    setTimeout(() => selectedJsonData.value = null, 300);
    document.body.style.overflow = 'auto';
};

// ==========================================
// LÓGICA DE SELECCIÓN Y ENVÍO A LA API CON PROGRESO
// ==========================================
const selectedItems = ref([]);
const isSending = ref(false);
const totalAEnviar = ref(0);
const enviadoActual = ref(0);

const iniciarProgresoSimulado = () => {
    enviadoActual.value = 0;
    const intervalo = setInterval(() => {
        if (enviadoActual.value < totalAEnviar.value - 1) {
            enviadoActual.value++;
        } else {
            clearInterval(intervalo);
        }
    }, 150);
};

const syncWithApi = () => {
    if (selectedItems.value.length === 0) return;

    // FILTRAMOS Y ENVIAMOS TODO EL OBJETO JSON
    const payloadData = props.inscritos.filter(inscrito => selectedItems.value.includes(inscrito.id));

    openConfirm(
        `¿Estás seguro de ENVIAR AL SIE los ${selectedItems.value.length} inscritos seleccionados?`,
        () => {
            isSending.value = true;
            totalAEnviar.value = selectedItems.value.length;
            document.body.style.overflow = 'hidden';

            router.post(route('sie.enviar-api'), {
                inscritos_data: payloadData // <- Enviamos los datos completos aquí
            }, {
                preserveScroll: true,
                onStart: () => {
                    iniciarProgresoSimulado();
                },
                onFinish: () => {
                    isSending.value = false;
                    document.body.style.overflow = 'auto';
                },
                onSuccess: () => {
                    enviadoActual.value = totalAEnviar.value;
                    selectedItems.value = [];
                }
            });
        }
    );
};

// ==========================================
// DISEÑO Y UTILIDADES
// ==========================================
const getCardBadgeStyle = (marca) => {
    switch (marca) {
        case 'Visa': return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'Mastercard': return 'bg-orange-50 text-orange-700 border-orange-200';
        case 'American Express': return 'bg-cyan-50 text-cyan-700 border-cyan-200';
        case 'Diners Club': return 'bg-slate-100 text-slate-700 border-slate-300';
        case 'UnionPay': return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        default: return 'bg-gray-50 text-gray-500 border-gray-200';
    }
};

const statusStyle = (status) => {
    if (status === 'PAGADO') return 'bg-emerald-50 text-emerald-700 border-emerald-200 ring-emerald-600/20';
    if (status === 'PENDIENTE') return 'bg-amber-50 text-amber-700 border-amber-200 ring-amber-600/20';
    return 'bg-slate-50 text-slate-600 border-slate-200 ring-slate-500/20';
};

const getInitials = (name) => {
    if (!name || name === 'Sin nombre') return 'NN';
    const parts = name.trim().split(' ');
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return name.substring(0, 2).toUpperCase();
};

const parseDateToIso = (dateStr) => {
    if (!dateStr || dateStr === '-') return null;
    const datePart = dateStr.split(' ')[0];
    if (!datePart) return null;
    const [day, month, year] = datePart.split('/');
    if (!day || !month || !year) return null;
    return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
};

// ==========================================
// FILTROS Y PAGINACIÓN
// ==========================================
const filteredInscritos = computed(() => {
    const query = searchQuery.value.toLowerCase();

    return props.inscritos.filter(inscrito => {
        let textMatch = true;
        if (query) {
            const nombresMatch = (inscrito.nombres || '').toLowerCase().includes(query);
            const emailMatch = (inscrito.email || '').toLowerCase().includes(query);
            const idMatch = (inscrito.id || '').toString().includes(query);
            const rucMatch = inscrito.facturacion?.ruc ? inscrito.facturacion.ruc.toLowerCase().includes(query) : false;
            const razonSocialMatch = inscrito.facturacion?.razon_social ? inscrito.facturacion.razon_social.toLowerCase().includes(query) : false;
            const marcaMatch = (inscrito.marca_tarjeta || '').toLowerCase().includes(query);
            const numeroMatch = (inscrito.numero_tarjeta || '').toLowerCase().includes(query);

            textMatch = nombresMatch || emailMatch || idMatch || rucMatch || razonSocialMatch || marcaMatch || numeroMatch;
        }

        let dateMatch = true;
        if (dateFrom.value || dateTo.value) {
            const rowDate = parseDateToIso(inscrito.fecha_registro);
            if (rowDate) {
                if (dateFrom.value && rowDate < dateFrom.value) dateMatch = false;
                if (dateTo.value && rowDate > dateTo.value) dateMatch = false;
            } else {
                dateMatch = false;
            }
        }

        return textMatch && dateMatch;
    });
});

const selectAll = computed({
    get: () => {
        const seleccionables = filteredInscritos.value.filter(p => !p.envio_sie);
        if (seleccionables.length === 0) return false;
        return seleccionables.every(p => selectedItems.value.includes(p.id));
    },
    set: (val) => {
        if (val) {
            filteredInscritos.value.forEach(p => {
                if (!p.envio_sie && !selectedItems.value.includes(p.id)) {
                    selectedItems.value.push(p.id);
                }
            });
        } else {
            selectedItems.value = [];
        }
    }
});

watch([searchQuery, dateFrom, dateTo], () => { currentPage.value = 1; });

const totalPages = computed(() => Math.max(1, Math.ceil(filteredInscritos.value.length / itemsPerPage)));

const paginatedInscritos = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filteredInscritos.value.slice(start, end);
});

const goToFirstPage = () => { currentPage.value = 1; };
const prevPage = () => { if (currentPage.value > 1) currentPage.value--; };
const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++; };
const goToLastPage = () => { currentPage.value = totalPages.value; };

const clearFilters = () => {
    searchQuery.value = '';
    dateFrom.value = '';
    dateTo.value = '';
};
</script>

<template>

    <Head title="Inscritos | Proexplo" />

    <AuthenticatedLayout>
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8 py-8">

            <div
                class="flex flex-col xl:flex-row xl:items-end justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div>
                    <h2 class="text-2xl font-black text-slate-900 tracking-tight">Registro de Inscritos</h2>
                    <p class="text-sm text-slate-500 mt-1">Gestiona los participantes y envía los datos al SIE.</p>
                </div>

                <div class="flex flex-col md:flex-row flex-wrap items-center gap-3 w-full xl:w-auto xl:justify-end">
                    <div
                        class="flex items-center bg-slate-50 border border-slate-200 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-orange-500/20 focus-within:border-orange-500 transition-all w-full md:w-auto h-11">
                        <span
                            class="px-3 text-[11px] font-bold text-slate-500 bg-slate-100 border-r border-slate-200 h-full flex items-center uppercase tracking-wider">Desde</span>
                        <input v-model="dateFrom" type="date"
                            class="relative border-0 bg-transparent text-sm w-full md:w-36 outline-none focus:ring-0 text-slate-700 h-full cursor-pointer" />
                    </div>

                    <div
                        class="flex items-center bg-slate-50 border border-slate-200 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-orange-500/20 focus-within:border-orange-500 transition-all w-full md:w-auto h-11">
                        <span
                            class="px-3 text-[11px] font-bold text-slate-500 bg-slate-100 border-r border-slate-200 h-full flex items-center uppercase tracking-wider">Hasta</span>
                        <input v-model="dateTo" type="date"
                            class="relative border-0 bg-transparent text-sm w-full md:w-36 outline-none focus:ring-0 text-slate-700 h-full cursor-pointer" />
                    </div>

                    <div class="relative w-full md:w-64 h-11">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </span>
                        <input v-model="searchQuery" type="text" placeholder="Buscar participante..."
                            class="w-full pl-10 pr-4 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 transition-all text-sm outline-none text-slate-700 placeholder-slate-400 h-full" />
                    </div>

                    <button v-if="searchQuery || dateFrom || dateTo" @click="clearFilters" title="Limpiar Filtros"
                        class="h-11 w-11 flex-shrink-0 flex items-center justify-center bg-slate-100 hover:bg-slate-200 text-slate-500 rounded-xl transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <Transition enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95">
                        <button v-if="selectedItems.length > 0" @click="syncWithApi" :disabled="isSending"
                            class="h-11 w-full md:w-auto px-5 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-400 text-white text-sm font-bold rounded-xl shadow-lg transition-all flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            ENVIAR SIE ({{ selectedItems.length }})
                        </button>
                    </Transition>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 flex flex-col overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-4 w-12 text-center">
                                    <input type="checkbox" v-model="selectAll"
                                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer disabled:opacity-40">
                                </th>
                                <th scope="col"
                                    class="px-6 py-4 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                    Inscripción</th>
                                <th scope="col"
                                    class="px-6 py-4 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                    Participante</th>
                                <th scope="col"
                                    class="px-6 py-4 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                    Facturación</th>
                                <th scope="col"
                                    class="px-6 py-4 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                    Monto</th>
                                <th scope="col"
                                    class="px-6 py-4 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                    Descuento</th>
                                <th scope="col"
                                    class="px-6 py-4 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                    Tarjeta</th>
                                <th scope="col"
                                    class="px-6 py-4 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                    Estado Niubiz</th>
                                <th scope="col"
                                    class="px-6 py-4 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                    Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="inscrito in paginatedInscritos" :key="inscrito.id"
                                class="transition-colors group" :class="{
                                    'bg-indigo-50/30': selectedItems.includes(inscrito.id),
                                    'hover:bg-slate-50/80': !inscrito.envio_sie,
                                    'bg-slate-50/60 opacity-80': inscrito.envio_sie
                                }">

                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <input type="checkbox" :value="inscrito.id" v-model="selectedItems"
                                        :disabled="inscrito.envio_sie"
                                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col items-start gap-1">
                                        <span class="text-sm font-black"
                                            :class="inscrito.envio_sie ? 'text-slate-500' : 'text-slate-900'">#{{
                                                inscrito.id }}</span>

                                        <span v-if="inscrito.envio_sie"
                                            class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-indigo-100 text-indigo-700 tracking-wider uppercase border border-indigo-200">
                                            <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 13l4 4L19 7" />
                                            </svg>
                                            ENVIADO A SIE
                                        </span>

                                        <span class="text-[11px] font-medium text-slate-500">{{ inscrito.fecha_registro
                                            }}</span>
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-full bg-orange-100 flex items-center justify-center text-orange-700 font-bold text-xs ring-2 ring-white shadow-sm"
                                            :class="{ 'opacity-50 grayscale': inscrito.envio_sie }">
                                            {{ getInitials(inscrito.nombres) }}
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-sm font-bold"
                                                :class="inscrito.envio_sie ? 'text-slate-500' : 'text-slate-900'">{{
                                                    inscrito.nombres }}</span>
                                            <span class="text-[11px] text-slate-500">{{ inscrito.email }}</span>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <div v-if="inscrito.facturacion?.ruc && inscrito.facturacion.ruc !== '-'"
                                        class="flex flex-col">
                                        <span class="text-xs font-bold line-clamp-1"
                                            :class="inscrito.envio_sie ? 'text-slate-500' : 'text-slate-800'"
                                            :title="inscrito.facturacion.razon_social">
                                            {{ inscrito.facturacion.razon_social }}
                                        </span>
                                        <span class="text-[11px] text-slate-500 mt-0.5 font-mono">{{
                                            inscrito.facturacion.tipo_documento }}: {{ inscrito.facturacion.ruc
                                            }}</span>
                                    </div>
                                    <span v-else
                                        class="inline-flex items-center px-2 py-1 rounded text-[10px] font-medium bg-slate-100 text-slate-500 border border-slate-200">
                                        Sin Factura
                                    </span>
                                </td>

                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <span class="text-sm font-black"
                                        :class="inscrito.envio_sie ? 'text-slate-500' : 'text-slate-900'">
                                        {{ inscrito.facturacion?.monto_total > 0 ? '$' +
                                            inscrito.facturacion.monto_total : '-' }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <span v-if="inscrito.cupon"
                                        class="inline-flex items-center px-2 py-1 rounded text-[10px] font-bold"
                                        :class="inscrito.envio_sie ? 'bg-slate-100 text-slate-500 border border-slate-200' : 'bg-indigo-50 text-indigo-600 border border-indigo-200'">SÍ</span>
                                    <span v-else
                                        class="inline-flex items-center px-2 py-1 rounded text-[10px] font-medium bg-slate-50 text-slate-400 border border-slate-200">NO</span>
                                </td>

                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <div v-if="inscrito.numero_tarjeta"
                                        class="flex flex-col items-center justify-center gap-1.5"
                                        :class="{ 'opacity-60': inscrito.envio_sie }">
                                        <span
                                            :class="[getCardBadgeStyle(inscrito.marca_tarjeta), 'px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wider shadow-sm']">
                                            {{ (!inscrito.marca_tarjeta || inscrito.marca_tarjeta ===
                                                'Otra/Desconocida') ? 'TARJETA' : inscrito.marca_tarjeta }}
                                        </span>
                                        <span class="text-[11px] font-mono font-bold tracking-widest"
                                            :class="inscrito.envio_sie ? 'text-slate-400' : 'text-slate-600'">
                                            {{ inscrito.numero_tarjeta }}
                                        </span>
                                    </div>
                                    <span v-else class="text-xs text-slate-400">-</span>
                                </td>

                                <td class="px-6 py-4 text-center whitespace-nowrap"
                                    :class="{ 'opacity-70': inscrito.envio_sie }">
                                    <span
                                        :class="[statusStyle(inscrito.estado_pago), 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-bold border shadow-sm ring-1 ring-inset tracking-wide']">
                                        <span
                                            :class="['w-1.5 h-1.5 rounded-full', inscrito.estado_pago === 'PAGADO' ? 'bg-emerald-500' : 'bg-amber-500']"></span>
                                        {{ inscrito.estado_pago }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <div class="flex justify-center gap-2">
                                        <button @click="openJsonPreview(inscrito)"
                                            class="inline-flex items-center justify-center p-2 rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-indigo-600 hover:border-indigo-200 hover:bg-indigo-50 transition-all shadow-sm focus:outline-none"
                                            title="Ver Datos JSON">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                            </svg>
                                        </button>
                                        <button @click="openDetails(inscrito)"
                                            class="inline-flex items-center justify-center p-2 rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-orange-600 hover:border-orange-200 hover:bg-orange-50 transition-all shadow-sm focus:outline-none"
                                            title="Ver detalle completo">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="filteredInscritos.length === 0">
                                <td colspan="9" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center">
                                        <div
                                            class="h-12 w-12 rounded-full bg-slate-50 flex items-center justify-center border border-slate-100 mb-3 text-slate-400">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                    d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                            </svg>
                                        </div>
                                        <h3 class="text-sm font-bold text-slate-900">No se encontraron inscritos</h3>
                                        <p class="text-xs text-slate-500 mt-1">Intenta ajustando los términos de
                                            búsqueda o el rango de fechas.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="filteredInscritos.length > 0"
                    class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between">
                    <span class="text-xs text-slate-500 font-medium">
                        Mostrando <span class="font-bold text-slate-900">{{ (currentPage - 1) * itemsPerPage + 1
                            }}</span> -
                        <span class="font-bold text-slate-900">{{ Math.min(currentPage * itemsPerPage,
                            filteredInscritos.length) }}</span>
                        de <span class="font-bold text-slate-900">{{ filteredInscritos.length }}</span>
                    </span>

                    <div class="flex items-center gap-1.5">
                        <button @click="goToFirstPage" :disabled="currentPage === 1"
                            class="px-3 py-1.5 text-[11px] font-bold rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all shadow-sm uppercase tracking-wider">Inicio</button>
                        <button @click="prevPage" :disabled="currentPage === 1"
                            class="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all shadow-sm"><svg
                                class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 19l-7-7 7-7" />
                            </svg></button>
                        <span class="px-3 py-1 text-xs font-black text-slate-700 bg-slate-200/50 rounded-md mx-1">Pág.
                            {{ currentPage }} de {{ totalPages }}</span>
                        <button @click="nextPage" :disabled="currentPage === totalPages"
                            class="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all shadow-sm"><svg
                                class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5l7 7-7 7" />
                            </svg></button>
                        <button @click="goToLastPage" :disabled="currentPage === totalPages"
                            class="px-3 py-1.5 text-[11px] font-bold rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all shadow-sm uppercase tracking-wider">Final</button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <Teleport to="body">
        <Transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100"
            leave-active-class="ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="showModal" class="fixed inset-0 z-[150] overflow-y-auto">
                <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="closeModal">
                </div>
                <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                    <Transition enter-active-class="ease-out duration-300"
                        enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                        leave-active-class="ease-in duration-200"
                        leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                        leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <div v-if="selectedInscrito"
                            class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 w-full max-w-4xl border border-slate-200">
                            <div class="flex flex-col max-h-[90vh]">
                                <div
                                    class="shrink-0 bg-white/90 backdrop-blur-md px-6 py-5 border-b border-slate-200 flex items-center justify-between z-10">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="h-10 w-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-500">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-black text-slate-900 leading-tight">Detalle de
                                                Inscripción #{{ selectedInscrito.id }}</h3>
                                            <p class="text-xs text-slate-500 mt-0.5">Registrado el {{
                                                selectedInscrito.fecha_registro }}</p>
                                        </div>
                                    </div>
                                    <button @click="closeModal"
                                        class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors focus:outline-none focus:ring-2 focus:ring-orange-500">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <div
                                    class="flex-1 overflow-y-auto px-6 py-6 sm:p-8 bg-slate-50/50 space-y-8 custom-scrollbar">
                                    <div>
                                        <h4
                                            class="text-xs font-black tracking-widest text-slate-400 uppercase mb-4 pl-1">
                                            Detalles del Programa</h4>
                                        <div
                                            class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                                            <div
                                                class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                                                <div class="p-4">
                                                    <p class="text-[10px] uppercase font-bold text-slate-400">Categoría
                                                        de Inscripción</p>
                                                    <p v-if="selectedInscrito.categoria_inscripcion"
                                                        class="text-sm font-bold text-slate-900 mt-1">{{
                                                            selectedInscrito.categoria_inscripcion.nombre_es }}</p>
                                                    <p v-else class="text-sm font-medium text-slate-400 mt-1 italic">No
                                                        especificada</p>
                                                </div>
                                                <div class="p-4">
                                                    <p class="text-[10px] uppercase font-bold text-slate-400 mb-2">
                                                        Cursos / Viajes Adicionales</p>
                                                    <div v-if="selectedInscrito.categoria_cursos_viajes && selectedInscrito.categoria_cursos_viajes.length > 0"
                                                        class="flex flex-col gap-3 mt-1">
                                                        <div v-for="(curso, index) in selectedInscrito.categoria_cursos_viajes"
                                                            :key="index"
                                                            class="flex flex-col items-start gap-1 border-l-2 border-cyan-200 pl-2">
                                                            <p class="text-sm font-bold text-slate-900 leading-tight">{{
                                                                curso.nombre_es }}</p>
                                                            <span v-if="curso.tipo"
                                                                class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-cyan-50 text-cyan-700 border border-cyan-200 uppercase tracking-wide">{{
                                                                    curso.tipo }}</span>
                                                        </div>
                                                    </div>
                                                    <p v-else class="text-sm font-medium text-slate-400 mt-1 italic">
                                                        Ninguno</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <h4
                                            class="text-xs font-black tracking-widest text-slate-400 uppercase mb-4 pl-1">
                                            Información del Participante</h4>
                                        <div
                                            class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                                            <div
                                                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                                                <div class="p-4">
                                                    <p class="text-[10px] uppercase font-bold text-slate-400">Nombre
                                                        Completo</p>
                                                    <p class="text-sm font-bold text-slate-900 mt-1">{{
                                                        selectedInscrito.nombres }}</p>
                                                </div>
                                                <div class="p-4">
                                                    <p class="text-[10px] uppercase font-bold text-slate-400">Documento
                                                    </p>
                                                    <p class="text-sm font-bold text-slate-900 mt-1">{{
                                                        selectedInscrito.documento }}</p>
                                                </div>
                                                <div class="p-4">
                                                    <p class="text-[10px] uppercase font-bold text-slate-400">Correo
                                                        Electrónico</p>
                                                    <p class="text-sm font-bold text-orange-600 mt-1 truncate"
                                                        :title="selectedInscrito.email">{{ selectedInscrito.email }}</p>
                                                </div>
                                                <div class="p-4">
                                                    <p class="text-[10px] uppercase font-bold text-slate-400">Cargo /
                                                        Origen</p>
                                                    <p class="text-sm font-bold text-slate-900 mt-1">{{
                                                        selectedInscrito.cargo }} <span
                                                            class="text-slate-400 font-normal">({{
                                                                selectedInscrito.origen }})</span></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <h4
                                            class="text-xs font-black tracking-widest text-slate-400 uppercase mb-4 pl-1">
                                            Detalles de Facturación</h4>
                                        <div
                                            class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                                            <div
                                                class="p-4 bg-slate-50/80 border-b border-slate-100 flex items-center justify-between">
                                                <span class="text-xs font-bold text-slate-600 uppercase">Monto Total a
                                                    Pagar</span>
                                                <span class="text-lg font-black text-emerald-600">${{
                                                    selectedInscrito.facturacion?.monto_total || 0 }}</span>
                                            </div>
                                            <div
                                                class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                                                <div class="p-4 space-y-3">
                                                    <div>
                                                        <p class="text-[10px] uppercase font-bold text-slate-400">Razón
                                                            Social</p>
                                                        <p class="text-sm font-bold text-slate-900">{{
                                                            selectedInscrito.facturacion?.razon_social || '-' }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="text-[10px] uppercase font-bold text-slate-400">Doc.
                                                            Facturación</p>
                                                        <p class="text-sm font-mono text-slate-700"><span
                                                                class="font-bold mr-1">{{
                                                                    selectedInscrito.facturacion?.tipo_documento || '-'
                                                                }}:</span>{{ selectedInscrito.facturacion?.ruc || '-' }}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="p-4 space-y-3">
                                                    <div>
                                                        <p class="text-[10px] uppercase font-bold text-slate-400">
                                                            Dirección</p>
                                                        <p class="text-sm font-medium text-slate-700">{{
                                                            selectedInscrito.facturacion?.direccion || '-' }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="text-[10px] uppercase font-bold text-slate-400">Correo
                                                            Facturación</p>
                                                        <p class="text-sm font-medium text-slate-700">{{
                                                            selectedInscrito.facturacion?.correo_facturador || '-' }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <h4
                                            class="text-xs font-black tracking-widest text-slate-400 uppercase mb-4 pl-1">
                                            Registro Niubiz (Método de Pago)</h4>
                                        <div
                                            class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                                            <div
                                                class="p-5 bg-slate-50/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                                <div class="flex items-center gap-4">
                                                    <span
                                                        :class="[getCardBadgeStyle(selectedInscrito.marca_tarjeta), 'px-3 py-1.5 rounded-lg text-xs font-black border uppercase tracking-widest shadow-sm']">
                                                        {{ (!selectedInscrito.marca_tarjeta ||
                                                            selectedInscrito.marca_tarjeta === 'Otra/Desconocida') ?
                                                            'TARJETA BANCARIA' : selectedInscrito.marca_tarjeta }}
                                                    </span>
                                                    <div class="flex flex-col">
                                                        <span
                                                            class="text-xs font-mono text-slate-500 mt-0.5 font-bold tracking-widest">{{
                                                                selectedInscrito.numero_tarjeta || 'No registrado' }}</span>
                                                    </div>
                                                </div>
                                                <div class="flex items-center">
                                                    <span
                                                        :class="[statusStyle(selectedInscrito.estado_pago), 'px-3 py-1.5 rounded-md text-xs font-bold border shadow-sm flex items-center gap-2']">
                                                        <span
                                                            :class="['w-1.5 h-1.5 rounded-full animate-pulse', selectedInscrito.estado_pago === 'PAGADO' ? 'bg-emerald-500' : 'bg-amber-500']"></span>
                                                        {{ selectedInscrito.estado_pago }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <h4
                                            class="text-xs font-black tracking-widest text-slate-400 uppercase mb-4 pl-1">
                                            Descuentos</h4>
                                        <div
                                            class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                                            <div v-if="selectedInscrito.cupon"
                                                class="p-4 bg-indigo-50/50 flex items-center justify-between">
                                                <div class="flex items-center gap-3">
                                                    <div
                                                        class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 shadow-sm border border-indigo-200 shrink-0">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <p
                                                            class="text-[10px] uppercase font-bold text-indigo-400 tracking-widest">
                                                            Cupón de: {{ selectedInscrito.cupon.razon_social }}</p>
                                                        <p
                                                            class="text-sm font-black text-indigo-700 uppercase tracking-tight mt-0.5">
                                                            {{ selectedInscrito.cupon.codigo }}</p>
                                                    </div>
                                                </div>
                                                <span
                                                    class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-white text-indigo-600 border border-indigo-200 shadow-sm uppercase tracking-wider">Aplicado</span>
                                            </div>
                                            <div v-else
                                                class="p-6 text-center bg-slate-50/50 border-dashed border-slate-200">
                                                <svg class="mx-auto h-6 w-6 text-slate-300 mb-2" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="1.5"
                                                        d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z" />
                                                </svg>
                                                <p class="text-sm font-bold text-slate-500">Sin cupón de descuento</p>
                                                <p class="text-xs text-slate-400 mt-0.5">El participante no aplicó
                                                    ningún código promocional.</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <h4
                                            class="text-xs font-black tracking-widest text-slate-400 uppercase mb-4 pl-1">
                                            Accesos y Viaje</h4>
                                        <div
                                            class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                                            <div
                                                class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
                                                <div class="p-6 flex flex-col items-center justify-center text-center">
                                                    <p
                                                        class="text-[10px] uppercase font-bold text-slate-400 mb-4 w-full text-left">
                                                        Código QR de Acceso</p>
                                                    <div v-if="selectedInscrito.qr" class="flex flex-col items-center">
                                                        <img :src="`https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${selectedInscrito.qr}`"
                                                            alt="Código QR"
                                                            class="w-32 h-32 p-2 border border-slate-200 rounded-xl shadow-sm bg-white mb-2 transition-transform hover:scale-105" />
                                                        <p
                                                            class="text-[11px] font-mono text-slate-500 font-medium tracking-widest">
                                                            ID: {{ selectedInscrito.qr }}</p>
                                                    </div>
                                                    <div v-else
                                                        class="flex flex-col items-center py-6 w-full bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
                                                        <p class="text-sm font-medium text-slate-400 italic">QR no
                                                            asignado</p>
                                                    </div>
                                                </div>
                                                <div class="p-6 flex flex-col items-center justify-center text-center">
                                                    <p
                                                        class="text-[10px] uppercase font-bold text-slate-400 mb-4 w-full text-left">
                                                        Cupón de Viaje</p>
                                                    <div v-if="selectedInscrito.cupon_viaje" class="w-full">
                                                        <div
                                                            class="px-4 py-6 bg-cyan-50/80 border border-cyan-200 rounded-xl border-dashed flex flex-col items-center justify-center h-full">
                                                            <p
                                                                class="text-2xl font-black text-cyan-700 uppercase tracking-widest">
                                                                {{ selectedInscrito.cupon_viaje }}</p>
                                                            <p
                                                                class="text-[10px] font-bold text-cyan-500 mt-2 uppercase tracking-wider">
                                                                Código de Viaje</p>
                                                        </div>
                                                    </div>
                                                    <div v-else
                                                        class="flex flex-col items-center py-6 w-full bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
                                                        <p class="text-sm font-medium text-slate-400 italic">Sin cupón
                                                            de viaje</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="shrink-0 bg-white/90 backdrop-blur-md px-6 py-4 border-t border-slate-100 flex justify-end gap-3 z-10">
                                    <button type="button" @click="closeModal"
                                        class="px-6 py-2.5 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors uppercase">Cerrar
                                        Detalles</button>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </Transition>
    </Teleport>

    <Teleport to="body">
        <Transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100"
            leave-active-class="ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="showJsonModal" class="fixed inset-0 z-[160] overflow-y-auto">
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeJsonPreview"></div>
                <div class="flex min-h-full items-center justify-center p-4">
                    <div
                        class="relative w-full max-w-3xl bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col">
                        <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
                            <div>
                                <h3 class="text-lg font-black text-slate-900">Previsualización del Payload #{{
                                    selectedJsonData?.id }}</h3>
                                <p class="text-[11px] text-slate-500">Este es el objeto que se evaluará en el
                                    WebService.</p>
                            </div>
                            <button type="button" @click="closeJsonPreview"
                                class="text-slate-400 hover:text-slate-700 bg-slate-200 hover:bg-slate-300 rounded-full p-1 transition-colors">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="p-0 bg-slate-900 overflow-y-auto custom-scrollbar" style="max-height: 65vh;">
                            <pre
                                class="text-[12px] text-green-400 p-6 font-mono whitespace-pre-wrap">{{ JSON.stringify(selectedJsonData, null, 4) }}</pre>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <Teleport to="body">
        <Transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100"
            leave-active-class="ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="showConfirmModal" class="fixed inset-0 z-[250] overflow-y-auto">
                <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="closeConfirm">
                </div>
                <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                    <Transition enter-active-class="ease-out duration-300"
                        enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                        leave-active-class="ease-in duration-200"
                        leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                        leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <div
                            class="relative transform overflow-hidden rounded-2xl bg-white text-center shadow-2xl transition-all sm:my-8 w-full max-w-sm border border-slate-200 p-6">
                            <div
                                class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50 mb-4">
                                <svg class="h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77-1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-black text-slate-900 mb-2">Confirmar Envío a SIE</h3>
                            <p class="text-sm text-slate-500 px-2">{{ confirmMessage }}</p>
                            <div class="mt-6 flex flex-col-reverse sm:flex-row justify-center gap-3">
                                <button @click="closeConfirm"
                                    class="w-full sm:w-auto px-5 py-2.5 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">Cancelar</button>
                                <button @click="executeConfirm"
                                    class="w-full sm:w-auto px-5 py-2.5 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-lg shadow-indigo-600/30 transition-all">Sí,
                                    ENVIAR A SIE</button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </Transition>
    </Teleport>

    <Teleport to="body">
        <Transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100"
            leave-active-class="ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="isSending" class="fixed inset-0 z-[300] flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-md"></div>
                <div
                    class="relative transform overflow-hidden rounded-3xl bg-white shadow-2xl transition-all w-full max-w-md p-8 text-center border border-slate-200">
                    <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-indigo-50 mb-6">
                        <svg class="h-10 w-10 text-indigo-600 animate-bounce" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-black text-slate-900">Enviando datos al SIE</h3>
                    <p class="text-sm text-slate-500 mt-2">Estamos procesando los registros seleccionados...</p>

                    <div class="mt-8">
                        <div class="flex justify-between items-end mb-2">
                            <span class="text-xs font-black text-indigo-600 uppercase tracking-widest">PROGRESO</span>
                            <span class="text-2xl font-black text-slate-900">{{ enviadoActual }}<span
                                    class="text-slate-300 text-lg">/{{ totalAEnviar }}</span></span>
                        </div>
                        <div
                            class="w-full bg-slate-100 rounded-full h-4 overflow-hidden border border-slate-200 p-0.5 shadow-inner">
                            <div class="bg-indigo-500 h-full rounded-full transition-all duration-500 ease-out shadow-[0_0_10px_rgba(99,102,241,0.4)]"
                                :style="{ width: `${totalAEnviar > 0 ? (enviadoActual / totalAEnviar) * 100 : 0}%` }">
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 p-4 bg-red-50 rounded-2xl border border-red-100 flex items-center gap-3">
                        <svg class="w-6 h-6 text-red-600 flex-shrink-0" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <p
                            class="text-[11px] text-red-700 font-bold leading-tight text-left uppercase tracking-tighter">
                            No cierre ni actualice la ventana hasta que finalice el proceso para evitar duplicados.</p>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <Transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-2">

        <div v-if="$page.props.flash?.success"
            class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-xl shadow-sm flex items-center gap-3">
            <div
                class="flex-shrink-0 w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-black text-emerald-800">¡Operación Exitosa!</h3>
                <p class="text-xs font-medium text-emerald-600 mt-0.5">{{ $page.props.flash.success }}</p>
            </div>
        </div>
    </Transition>

    <Transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-2">

        <div v-if="$page.props.flash?.error"
            class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl shadow-sm flex items-center gap-3">
            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-red-100 flex items-center justify-center text-red-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-black text-red-800">Atención requerida</h3>
                <p class="text-xs font-medium text-red-600 mt-0.5">{{ $page.props.flash.error }}</p>
            </div>
        </div>
    </Transition>

</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
    height: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}

.bg-slate-900.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #475569;
}

/* Ocultar el ícono del calendario en inputs date para mantener el diseño limpio */
input[type="date"]::-webkit-calendar-picker-indicator {
    background: transparent;
    bottom: 0;
    color: transparent;
    cursor: pointer;
    height: auto;
    left: 0;
    position: absolute;
    right: 0;
    top: 0;
    width: auto;
}
</style>
