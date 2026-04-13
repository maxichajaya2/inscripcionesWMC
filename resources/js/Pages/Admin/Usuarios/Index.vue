<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick } from 'vue';
import * as yup from 'yup';

const props = defineProps({
    usuarios: {
        type: [Array, Object],
        default: () => []
    },
    roles: {
        type: Array,
        default: () => []
    }
});

// ==========================================
// 0. NORMALIZACIÓN DE DATOS
// ==========================================
const safeUsuarios = computed(() => {
    return Array.isArray(props.usuarios) ? props.usuarios : Object.values(props.usuarios);
});

// ==========================================
// 1. ESTADOS BÁSICOS
// ==========================================
const searchQuery = ref('');
const isModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const editingUser = ref(null);
const userToDelete = ref(null);
const showToast = ref(false);
const toastMessage = ref('');
const currentPage = ref(1);
const itemsPerPage = 8;

// Estados para los ojitos de las contraseñas
const showPassword = ref(false);
const showConfirmPassword = ref(false);

// ==========================================
// 2. FORMULARIO (Inertia) - UN SOLO ROL
// ==========================================
const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: '' // Volvemos a String para un solo rol
});

// ==========================================
// 3. LÓGICA DE VALIDACIÓN (Yup) EN TIEMPO REAL
// ==========================================
const validateForm = async () => {
    const schema = yup.object({
        name: yup.string().trim().required('El nombre completo es obligatorio'),
        email: yup.string().trim()
            .email('Debe ingresar un correo electrónico válido')
            .required('El correo es obligatorio'),
        role: yup.string().required('Debe asignar un rol obligatoriamente'),

        password: yup.string().test('is-required', 'La contraseña es obligatoria', function(value) {
            // Si creamos uno nuevo, la contraseña es obligatoria sí o sí
            if (!editingUser.value && !value) return false;
            return true;
        }).test('min-length', 'Debe tener al menos 8 caracteres', function(value) {
            if (value && value.length < 8) return false;
            return true;
        }),

        password_confirmation: yup.string().test('passwords-match', 'Las contraseñas no coinciden', function(value) {
            return this.parent.password === value;
        })
    });

    try {
        await schema.validate(form.data(), { abortEarly: false });
        form.clearErrors(); // Si todo está bien, limpiamos
        return true;
    } catch (err) {
        const validationErrors = {};
        err.inner?.forEach(e => {
            if (!validationErrors[e.path]) {
                validationErrors[e.path] = e.message;
            }
        });

        form.clearErrors();
        // ASIGNACIÓN UNO POR UNO: Esto fuerza a Inertia a pintar los <span> rojos
        Object.keys(validationErrors).forEach(field => {
            form.setError(field, validationErrors[field]);
        });

        return false;
    }
};

// Validar en tiempo real mientras el usuario escribe
watch(
    () => form.data(),
    () => {
        if (isModalOpen.value) validateForm();
    },
    { deep: true }
);

// ==========================================
// 4. COMPUTADOS (Búsqueda y Paginación)
// ==========================================
const filteredUsers = computed(() => {
    const query = searchQuery.value.toLowerCase();
    return safeUsuarios.value.filter(user =>
        (user.name && user.name.toLowerCase().includes(query)) ||
        (user.email && user.email.toLowerCase().includes(query))
    );
});

watch(searchQuery, () => { currentPage.value = 1; });

const totalPages = computed(() => Math.max(1, Math.ceil(filteredUsers.value.length / itemsPerPage)));

const paginatedUsers = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredUsers.value.slice(start, start + itemsPerPage);
});

// NUEVAS Funciones de Navegación Completas
const goToFirstPage = () => { currentPage.value = 1; };
const prevPage = () => { if (currentPage.value > 1) currentPage.value--; };
const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++; };
const goToLastPage = () => { currentPage.value = totalPages.value; };

// ==========================================
// 5. MÉTODOS Y FUNCIONES
// ==========================================
const displayToast = (message) => {
    toastMessage.value = message;
    showToast.value = true;
    setTimeout(() => showToast.value = false, 3000);
};

const openModal = async (user = null) => {
    editingUser.value = user;
    form.clearErrors();

    // Reseteamos los ojitos para que siempre empiecen ocultos
    showPassword.value = false;
    showConfirmPassword.value = false;

    if (user) {
        form.name = user.name;
        form.email = user.email;
        form.password = '';
        form.password_confirmation = '';
        form.role = user.roles && user.roles.length > 0 ? user.roles[0].name : '';
    } else {
        form.reset();
        form.role = ''; // Nos aseguramos de que empiece vacío
    }

    isModalOpen.value = true;

    // Timeout mínimo para evitar que el reset() de Inertia borre nuestra validación inicial
    setTimeout(() => {
        validateForm();
    }, 50);
};

const submit = async () => {
    // 1. Validamos formato con Yup (frontend)
    const isValid = await validateForm();
    if (!isValid) return;

    const isEditing = !!editingUser.value;
    const action = isEditing ? route('usuarios.update', editingUser.value.id) : route('usuarios.store');
    const method = isEditing ? 'put' : 'post';

    form[method](action, {
        onSuccess: () => {
            isModalOpen.value = false;

            // Mensaje interactivo y personalizado
            const message = isEditing
                ? `¡Excelente! Los datos de "${form.name}" han sido actualizados correctamente.`
                : `¡Éxito! El usuario "${form.name}" ha sido registrado en el sistema WMC.`;

            displayToast(message);

            form.reset();
        },
        onError: (errors) => {
            // Manejo de errores más amigable
            if (errors.email) {
                displayToast(`Atención: El correo "${form.email}" ya se encuentra en uso o no es válido.`);
            } else {
                displayToast("Vaya, parece que hubo un problema al procesar la solicitud.");
            }
        },
        preserveScroll: true
    });
};

const confirmDelete = (user) => {
    userToDelete.value = user;
    isDeleteModalOpen.value = true;
};

const executeDelete = () => {
    if (userToDelete.value) {
        router.delete(route('usuarios.destroy', userToDelete.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                isDeleteModalOpen.value = false;
                userToDelete.value = null;
                displayToast('Usuario eliminado exitosamente.');
            }
        });
    }
};
</script>

<template>
    <Head title="Gestión de Usuarios" />

    <AuthenticatedLayout>
        <div class="space-y-6">

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div>
                    <h2 class="font-black text-2xl text-slate-800 tracking-tight">Gestión de Usuarios</h2>
                    <p class="text-sm text-slate-500 mt-1">Administra los accesos y roles del sistema.</p>
                </div>

                <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
                    <div class="relative w-full sm:w-64">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <input v-model="searchQuery" type="text" placeholder="Buscar por nombre o correo..." class="w-full pl-9 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all outline-none shadow-sm text-sm" />
                    </div>
                    <button @click="openModal()" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-sm transition-all active:scale-95 whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                        Nuevo Usuario
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50/80">
                            <tr>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-slate-500 uppercase tracking-widest">Usuario</th>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-slate-500 uppercase tracking-widest">Rol Asignado</th>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-slate-500 uppercase tracking-widest">Fecha de Registro</th>
                                <th class="px-6 py-4 text-right text-[10px] font-black text-slate-500 uppercase tracking-widest">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <tr v-for="user in paginatedUsers" :key="user.id" class="group hover:bg-slate-50/50 transition-colors">

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 font-black uppercase text-sm border border-indigo-100">
                                            {{ user.name.charAt(0) }}
                                        </div>
                                        <div class="flex flex-col gap-0.5">
                                            <span class="font-black text-sm text-slate-800 tracking-tight">{{ user.name }}</span>
                                            <span class="text-[10px] text-slate-500 font-medium">{{ user.email }}</span>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span v-if="user.roles && user.roles.length > 0" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-purple-50 text-purple-700 text-[10px] font-black uppercase tracking-widest border border-purple-200/60">
                                        <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span> {{ user.roles[0].name }}
                                    </span>
                                    <span v-else class="text-[10px] text-slate-400 italic">Sin rol asignado</span>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-xs font-bold text-slate-600">{{ new Date(user.created_at).toLocaleDateString() }}</span>
                                </td>

                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <div class="flex justify-end gap-2">
                                        <button @click="openModal(user)" class="p-2 text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors" title="Editar Usuario">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button @click="confirmDelete(user)" class="p-2 text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-colors" title="Eliminar Usuario">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredUsers.length === 0">
                                <td colspan="4" class="px-6 py-12 text-center text-slate-500 font-medium">
                                    No se encontraron usuarios que coincidan con la búsqueda.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="filteredUsers.length > 0" class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <span class="text-xs text-slate-500 font-medium">
                        Mostrando <span class="font-bold text-slate-700">{{ (currentPage - 1) * itemsPerPage + 1 }}</span> a
                        <span class="font-bold text-slate-700">{{ Math.min(currentPage * itemsPerPage, filteredUsers.length) }}</span>
                        de <span class="font-bold text-slate-700">{{ filteredUsers.length }}</span> usuarios
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
                        <svg class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <div class="ml-3 w-0 flex-1 pt-0.5">
                        <p class="text-sm font-bold text-white">{{ toastMessage }}</p>
                    </div>
                </div>
            </div>
        </Transition>

        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
            <div v-if="isModalOpen" class="fixed inset-0 z-[150] overflow-y-auto bg-slate-900/60 backdrop-blur-sm">
                <div class="flex min-h-full items-center justify-center p-4 py-10">

                    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden relative">
                        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                            <h3 class="text-xl font-black text-slate-800 uppercase tracking-tight">{{ editingUser ? 'Editar Usuario' : 'Registrar Nuevo Usuario' }}</h3>
                            <button @click="isModalOpen = false" class="text-slate-400 hover:text-slate-600 bg-white p-2 rounded-full border border-slate-200 transition-colors">✕</button>
                        </div>

                        <form @submit.prevent="submit" class="p-6 space-y-6">

                            <div class="bg-red-50 border border-red-200 rounded-xl p-3 flex items-start gap-3">
                                <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <div>
                                    <h5 class="text-sm font-bold text-red-800">Atención</h5>
                                    <p class="text-xs text-red-600 mt-0.5">Los campos marcados con un asterisco (<span class="font-black text-red-600">*</span>) son obligatorios.</p>
                                </div>
                            </div>

                            <div>
                                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2 mb-4">Información de la Cuenta</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-bold text-slate-700 mb-1 uppercase">Nombre Completo <span class="text-red-500">*</span></label>
                                        <input v-model="form.name" type="text" :class="{'border-red-500 ring-2 ring-red-100': form.errors.name}" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-4 focus:ring-2 focus:ring-indigo-500 outline-none text-sm text-slate-700 font-medium" />
                                        <span v-if="form.errors.name" class="block text-red-500 text-[10px] mt-1 font-bold">{{ form.errors.name }}</span>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1 uppercase">Correo Electrónico <span class="text-red-500">*</span></label>
                                        <input v-model="form.email" type="email" :class="{'border-red-500': form.errors.email}" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-4 outline-none text-sm font-medium" />
                                        <span v-if="form.errors.email" class="block text-red-500 text-[10px] mt-1 font-bold">{{ form.errors.email }}</span>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1 uppercase">Rol Asignado <span class="text-red-500">*</span></label>
                                        <select v-model="form.role" :class="{'border-red-500': form.errors.role}" translate="no" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-4 outline-none text-sm font-bold text-indigo-600">
                                            <option value="" disabled>Seleccione un rol...</option>
                                            <option v-for="rol in roles" :key="rol.id" :value="rol.name" translate="no">{{ rol.name }}</option>
                                        </select>
                                        <span v-if="form.errors.role" class="block text-red-500 text-[10px] mt-1 font-bold">{{ form.errors.role }}</span>
                                    </div>

                                </div>
                            </div>

                            <div>
                                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2 mb-4">Seguridad</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1 uppercase">Contraseña <span v-if="!editingUser" class="text-red-500">*</span></label>
                                        <div class="relative">
                                            <input v-model="form.password" :type="showPassword ? 'text' : 'password'" :placeholder="editingUser ? 'Dejar en blanco para no cambiar' : 'Mínimo 8 caracteres'" :class="{'border-red-500': form.errors.password}" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 pl-4 pr-10 outline-none text-sm font-medium" />
                                            <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-indigo-600 transition-colors">
                                                <svg v-if="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                            </button>
                                        </div>
                                        <span v-if="form.errors.password" class="block text-red-500 text-[10px] mt-1 font-bold">{{ form.errors.password }}</span>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1 uppercase">Confirmar Contraseña <span v-if="!editingUser" class="text-red-500">*</span></label>
                                        <div class="relative">
                                            <input v-model="form.password_confirmation" :type="showConfirmPassword ? 'text' : 'password'" :class="{'border-red-500': form.errors.password_confirmation}" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2.5 pl-4 pr-10 outline-none text-sm font-medium" />
                                            <button type="button" @click="showConfirmPassword = !showConfirmPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-indigo-600 transition-colors">
                                                <svg v-if="!showConfirmPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                            </button>
                                        </div>
                                        <span v-if="form.errors.password_confirmation" class="block text-red-500 text-[10px] mt-1 font-bold">{{ form.errors.password_confirmation }}</span>
                                    </div>

                                </div>
                            </div>

                            <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                                <button type="button" @click="isModalOpen = false" class="px-6 py-2.5 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors uppercase">Cancelar</button>
                                <button type="submit" :disabled="form.processing" class="px-6 py-2.5 text-sm font-bold text-white bg-indigo-600 rounded-xl shadow-lg hover:bg-indigo-700 transition-colors disabled:opacity-50 uppercase">
                                    {{ editingUser ? 'Guardar Cambios' : 'Confirmar Usuario' }}
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
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <h3 class="text-xl font-black text-slate-800 mb-2 uppercase">¿Eliminar Usuario?</h3>
                        <p class="text-sm text-slate-500 mb-6">
                            Vas a eliminar a <span class="font-bold text-slate-800 tracking-tight">{{ userToDelete?.name }}</span>. Esta acción no se puede deshacer.
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
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
</style>
