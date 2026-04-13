<script setup>
import { ref, computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const isMobileMenuOpen = ref(false);

const authUser = computed(() => {
    return page.props.auth?.user || {
        name: 'Usuario',
        role_name: 'Invitado',
        roles: []
    };
});

const isAdmin = computed(() => authUser.value.roles.includes('admin'));
const isAsociado = computed(() => authUser.value.roles.includes('asociado') ||
    authUser.value.roles.includes('Asociado'));

console.log('Usuario autenticado:', authUser.value);
</script>

<template>
    <div class="flex h-screen bg-gray-50 overflow-hidden font-sans">

        <div v-if="isMobileMenuOpen" @click="isMobileMenuOpen = false"
            class="fixed inset-0 bg-blue-950/80 backdrop-blur-sm z-40 md:hidden">
        </div>

        <aside :class="[isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full']"
            class="fixed inset-y-0 left-0 w-72 bg-blue-950 shadow-2xl shrink-0 z-50 transition-transform duration-300 md:relative md:translate-x-0 md:flex md:flex-col">

            <div class="flex items-center h-20 bg-blue-950 px-6 border-b border-white/10 shrink-0">
                <Link :href="route('inscripcion.index')" class="flex items-center gap-3">
                    <img src="/images/logo-admin.png" alt="Logo Admin"
                        class="h-10 w-auto object-contain transition-transform hover:scale-105" />
                </Link>

                <button @click="isMobileMenuOpen = false" class="ml-auto text-blue-200 md:hidden">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M6 18L18 6M6 6l12 12" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                </button>
            </div>

            <nav class="flex-1 px-4 py-8 space-y-1 overflow-y-auto custom-scrollbar">
                <p v-if="isAdmin" class="text-[10px] font-bold text-blue-300/70 uppercase tracking-widest px-4 mb-3">General</p>
                <div v-if="isAdmin" class="pt-6">
                    <Link :href="route('admin.index')" v-if="isAdmin"
                        class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all"
                        :class="route().current('admin.index') ? 'bg-blue-600 text-white shadow-lg shadow-blue-900/50' : 'text-blue-200 hover:bg-blue-900/50 hover:text-white'">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Inicio
                    </Link>
                </div>
                <!-- <Link :href="route('asociados.index')" v-if="isAsociado"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all"
                    :class="route().current('asociados.index') ? 'bg-blue-600 text-white shadow-lg shadow-blue-900/50' : 'text-blue-200 hover:bg-blue-900/50 hover:text-white'">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Inicio
                </Link> -->

                <div v-if="isAdmin || isAsociado" class="pt-6">
                    <p class="text-[10px] font-bold text-blue-300/70 uppercase tracking-widest px-4 mb-3">Gestión
                        Comercial</p>
                    <Link :href="route('cupones.index')"
                        class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all"
                        :class="route().current('cupones.index') ? 'bg-blue-600 text-white shadow-lg shadow-blue-900/50' : 'text-blue-200 hover:bg-blue-900/50 hover:text-white'">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4v-3a2 2 0 00-2-2H5z" />
                        </svg>
                        Cupones
                    </Link>
                    <Link :href="route('inscritos.index')"
                        class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all"
                        :class="route().current('inscritos.index') ? 'bg-blue-600 text-white shadow-lg shadow-blue-900/50' : 'text-slate-300 hover:bg-slate-800 hover:text-white'">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                        </svg>
                        Inscritos
                    </Link>
                </div>

                <div v-if="isAdmin" class="pt-6">
                    <p class="text-[10px] font-bold text-blue-300/70 uppercase tracking-widest px-4 mb-3">Administración
                    </p>
                    <!-- <Link :href="route('roles.index')"
                        class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all"
                        :class="route().current('roles.index') ? 'bg-blue-600 text-white shadow-lg shadow-blue-900/50' : 'text-blue-200 hover:bg-blue-900/50 hover:text-white'">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                        </svg>
                        Roles y Permisos
                    </Link> -->
                    <Link :href="route('usuarios.index')"
                        class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all mt-1"
                        :class="route().current('usuarios.index') ? 'bg-blue-600 text-white shadow-lg shadow-blue-900/50' : 'text-blue-200 hover:bg-blue-900/50 hover:text-white'">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        Usuarios
                    </Link>
                </div>
            </nav>

            <div class="p-6 bg-blue-950 border-t border-white/10 shrink-0">
                <div class="flex items-center gap-4 mb-4">
                    <img :src="authUser.avatar" class="w-11 h-11 rounded-xl border-2 border-yellow-500/30 shadow-lg"
                        alt="User">
                    <div class="truncate">
                        <p class="text-sm font-bold text-white truncate drop-shadow-md">{{ authUser.name }}</p>
                        <span
                            class="inline-flex px-2 py-0.5 mt-1 rounded bg-yellow-400/10 text-yellow-400 text-[10px] font-bold border border-yellow-400/20 uppercase tracking-wide">
                            {{ authUser.role_name }}
                        </span>
                    </div>
                </div>
                <Link :href="route('logout')" method="post" as="button"
                    class="group flex items-center justify-between w-full px-4 py-2.5 text-xs font-bold text-blue-200 hover:text-white bg-blue-900/50 hover:bg-blue-600 rounded-xl border border-white/5 transition-all hover:border-blue-400/50">
                    <span>Cerrar Sesión</span>
                    <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </Link>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
            <header class="h-20 bg-white border-b border-gray-200 shrink-0 z-30 flex items-center shadow-sm">
                <div class="w-full flex items-center justify-between px-6 md:px-10">
                    <button @click="isMobileMenuOpen = true" class="p-2 -ml-2 text-gray-600 md:hidden">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M4 6h16M4 12h16M4 18h16" stroke-width="2" stroke-linecap="round" />
                        </svg>
                    </button>

                    <div class="flex-1 px-4">
                        <h2
                            class="font-bold text-gray-900 tracking-tight text-lg md:text-xl truncate uppercase leading-none">
                            <slot name="header" />
                        </h2>
                    </div>

                    <div class="flex items-center gap-2 md:gap-4 shrink-0">
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button class="flex items-center gap-3 group focus:outline-none">
                                    <div class="text-right hidden sm:block leading-tight">
                                        <p class="text-xs font-bold text-gray-900 group-hover:text-blue-600 transition">
                                            {{ authUser.name }}</p>
                                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">{{
                                            authUser.role_name }}</p>
                                    </div>
                                    <img :src="authUser.avatar"
                                        class="w-10 h-10 rounded-xl border border-gray-200 group-hover:border-blue-500 transition shadow-sm"
                                        alt="User">
                                </button>
                            </template>
                            <template #content>
                                <DropdownLink :href="route('profile.edit')"> Mi Perfil </DropdownLink>
                                <DropdownLink :href="route('logout')" method="post" as="button"
                                    class="text-red-600 font-bold"> Salir </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto bg-gray-50/50 p-4 md:p-8 custom-scrollbar relative z-0">
                <div class="max-w-7xl mx-auto pb-10">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

<style>
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

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>
