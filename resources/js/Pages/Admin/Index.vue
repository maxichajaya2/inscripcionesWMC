<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
    ultimosUsuarios: Array,
    ultimosCupones: Array
});
</script>

<template>
    <Head title="Panel de Control" />

    <AuthenticatedLayout>
        <div class="space-y-8">

            <div>
                <h2 class="font-black text-2xl text-slate-800 tracking-tight">Resumen General</h2>
                <p class="text-sm text-slate-500 mt-1">Métricas y actividad reciente de la plataforma WMC 2026.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">

                <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200 flex items-center gap-4 group hover:shadow-md transition-shadow">
                    <div class="w-14 h-14 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition-colors">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Usuarios</p>
                        <p class="text-3xl font-black text-slate-800 tracking-tight">{{ stats.total_usuarios }}</p>
                    </div>
                </div>

                <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200 flex items-center gap-4 group hover:shadow-md transition-shadow">
                    <div class="w-14 h-14 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center group-hover:bg-purple-600 group-hover:text-white transition-colors">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Roles Creados</p>
                        <p class="text-3xl font-black text-slate-800 tracking-tight">{{ stats.total_roles }}</p>
                    </div>
                </div>

                <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200 flex items-center gap-4 group hover:shadow-md transition-shadow">
                    <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4v-3a2 2 0 00-2-2H5z" /></svg>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Cupones Activos</p>
                        <p class="text-3xl font-black text-slate-800 tracking-tight">{{ stats.cupones_activos }}</p>
                    </div>
                </div>

                <!-- <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200 flex items-center gap-4 group hover:shadow-md transition-shadow">
                    <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center group-hover:bg-amber-500 group-hover:text-white transition-colors">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Usos Totales</p>
                        <p class="text-3xl font-black text-slate-800 tracking-tight">{{ stats.usos_totales_cupones }}</p>
                    </div>
                </div> -->

            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                        <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">Últimos Usuarios</h3>
                        <Link :href="route('usuarios.index')" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 transition-colors">Ver todos &rarr;</Link>
                    </div>
                    <div class="overflow-x-auto p-2">
                        <table class="min-w-full divide-y divide-slate-100">
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <tr v-for="user in ultimosUsuarios" :key="user.id" class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold uppercase text-xs">
                                                {{ user.name.charAt(0) }}
                                            </div>
                                            <div>
                                                <p class="font-bold text-xs text-slate-800">{{ user.name }}</p>
                                                <p class="text-[10px] text-slate-500">{{ user.email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span v-if="user.roles.length > 0" class="px-2 py-1 bg-slate-100 text-slate-600 text-[9px] font-bold rounded-md uppercase tracking-tight">
                                            {{ user.roles[0].name }} <span v-if="user.roles.length > 1">+{{ user.roles.length - 1 }}</span>
                                        </span>
                                        <span v-else class="text-[10px] text-slate-400 italic">Sin rol</span>
                                    </td>
                                </tr>
                                <tr v-if="ultimosUsuarios.length === 0">
                                    <td colspan="2" class="px-4 py-8 text-center text-xs text-slate-500">No hay usuarios registrados.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                        <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">Cupones Recientes</h3>
                        <Link :href="route('cupones.index')" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 transition-colors">Ver todos &rarr;</Link>
                    </div>
                    <div class="overflow-x-auto p-2">
                        <table class="min-w-full divide-y divide-slate-100">
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <tr v-for="cupon in ultimosCupones" :key="cupon.id" class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex flex-col gap-0.5">
                                            <span class="font-black text-xs text-slate-800 uppercase">{{ cupon.codigo_cupon }}</span>
                                            <span class="text-[10px] text-slate-500 truncate max-w-[150px]">{{ cupon.razon_social || 'Global' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <span v-if="cupon.is_active" class="w-2 h-2 rounded-full bg-green-500 inline-block shadow-[0_0_5px_rgba(34,197,94,0.5)]" title="Activo"></span>
                                        <span v-else class="w-2 h-2 rounded-full bg-slate-300 inline-block" title="Inactivo"></span>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <span class="font-black text-indigo-600 text-sm">
                                            {{ cupon.tipo_descuento === 'porcentaje' ? cupon.valor + '%' : '$' + cupon.valor }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="ultimosCupones.length === 0">
                                    <td colspan="3" class="px-4 py-8 text-center text-xs text-slate-500">No hay cupones creados.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
