<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import colorbar from '@/Components/colorbar.vue';
import GreenArrowRight from '@/Components/GreenArrowRight.vue';
import { router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, nextTick } from 'vue';

// Estilos globales
import "../../../css/inscripciones.css";

const props = defineProps({
    categorias: {
        type: Array,
        default: () => []
    }
});
const showSupport = ref(false);

const grupoSeleccionado = ref(null);
const macroSeccion = ref(null); // 'inscripciones' o 'viajes'
const copiado = ref(false);

const seleccionarGrupo = (grupo) => {
    grupoSeleccionado.value = grupo;
    scrollToCategories();
};

const copiarCorreo = () => {
    const email = 'wmc.itsupport@iimp.org.pe';
    navigator.clipboard.writeText(email).then(() => {
        copiado.value = true;
        // El mensaje desaparece después de 2 segundos
        setTimeout(() => {
            copiado.value = false;
        }, 3000);
    });
};
// const categoriasVisibles = computed(() => {
//     if (!grupoSeleccionado.value) return [];
//     return props.categorias.filter(cat => cat.grupo === grupoSeleccionado.value);
// });

const categoriasVisibles = computed(() => {
    if (!grupoSeleccionado.value) return [];

    // 1. Filtramos primero por el grupo (autor/participante)
    let filtradas = props.categorias.filter(cat => cat.grupo === grupoSeleccionado.value);

    // 2. Si estamos en la sección de VIAJES, ocultamos Student y One Day
    if (macroSeccion.value === 'viajes') {
        filtradas = filtradas.filter(cat => {
            const nombre = cat.nombre_en.toUpperCase();
            // Retorna falso para los que NO queremos mostrar
            return !nombre.includes('STUDENT') && !nombre.includes('ONE DAY');
        });
    }
    // Filtramos por el grupo (autor/participante)
    // y podrías añadir un filtro extra si tu base de datos tiene algo para "viajes"
    return filtradas;
})

const volverAMacro = () => {
    macroSeccion.value = null;
    grupoSeleccionado.value = null;
};

// const irAlFormulario = (id) => {
//     // Buscamos la categoría para saber su grupo
//     const categoria = props.categorias.find(c => c.id === id);

//     if (categoria.grupo === 'autor') {
//         router.get(route('inscripcion.autor'), { category: id });
//     } else {
//         router.get(route('inscripcion.participante'), { category: id });
//     }
// };

const irAlFormulario = (id) => {
    const categoria = props.categorias.find(c => c.id === id);

    // Preparamos los parámetros
    const params = {
        category: id,
        section: macroSeccion.value,// Esto enviará 'inscripciones' o 'viajes'
        profile: categoria.id_perfil,
        course: 0
    };

    if (categoria.grupo === 'autor') {
        router.get(route('inscripcion.autor'), params);
    } else {
        router.get(route('inscripcion.participante'), params);
    }
};

const scrollToCategories = () => {
    nextTick(() => {
        // Buscamos el ID que pusimos en el HTML
        const element = document.getElementById('section-categories');
        if (element) {
            // Ajuste: offset para que no quede pegado al borde superior
            const yOffset = -20;
            const y = element.getBoundingClientRect().top + window.pageYOffset + yOffset;

            window.scrollTo({ top: y, behavior: 'smooth' });
        }
    });
};
</script>

<template>
    <AppLayout class="bg-gradient-wmc">
        <div class="px-6 py-12 mx-auto max-w-6xl min-h-[80vh] flex flex-col justify-center font-sans">
            <!-- <div
                class="bg-slate-900 text-white p-6 rounded-xl my-6 text-xs border-l-4 border-purple-500 shadow-2xl overflow-auto max-h-96">
                <h2 class="text-lg font-bold mb-4 text-purple-400">🔍 Inspección de Props (Frontend)</h2>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="bg-black/40 p-3 rounded border border-white/10">
                        <p class="text-gray-400 uppercase font-bold">Total Categorías:</p>
                        <p class="text-xl font-mono">{{ props.categorias.length }}</p>
                    </div>
                    <div class="bg-black/40 p-3 rounded border border-white/10">
                        <p class="text-gray-400 uppercase font-bold">Grupo Seleccionado:</p>
                        <p class="text-xl font-mono text-cyan-400">{{ grupoSeleccionado || 'NINGUNO' }}</p>
                    </div>
                </div>

                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-purple-300 border-b border-white/20">
                            <th class="p-2">ID</th>
                            <th class="p-2">Nombre</th>
                            <th class="p-2">Grupo (DB)</th>
                            <th class="p-2">Perfil ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="cat in props.categorias" :key="cat.id"
                            class="border-b border-white/5 hover:bg-white/5">
                            <td class="p-2 font-mono">{{ cat.id }}</td>
                            <td class="p-2 font-bold">{{ cat.nombre_en }}</td>
                            <td class="p-2">
                                <span :class="cat.grupo === 'participante' ? 'text-green-400' : 'text-yellow-400'">
                                    {{ cat.grupo || 'NULL' }}
                                </span>
                            </td>
                            <td class="p-2 font-mono">{{ cat.id_perfil }}</td>
                        </tr>
                    </tbody>
                </table>
            </div> -->
            <!-- <div class="preventa-banner-home animate-fade-in-down mb-8">
                <div class="banner-home-content p-3 md:p-6 flex items-center gap-3">
                    <div class="banner-home-icon shrink-0">
                        <span class="text-xl md:text-2xl">⏳</span>
                    </div>
                    <div class="banner-home-text text-left">
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="tag-early-bird text-[10px] md:text-xs px-2 py-0.5">EARLY BIRD RATES</span>
                        </div>
                        <h2 class="text-base md:text-2xl font-black text-white leading-tight">
                            Take advantage of Early Bird rates!
                        </h2>
                        <p class="text-white/80 text-xs md:text-base leading-snug">
                            Register by <strong>March 31st</strong> and secure your spot.
                        </p>
                    </div>
                </div>
            </div> -->
            <div id="titulo_inicial" class="mb-12 text-left animate-fade-in-down">
                <h1 class="text-4xl md:text-5xl font-black text-yellow-price tracking-tight mb-2">
                    World Mining Congress <span class="text-white">2026</span>
                </h1>
                <h3 class="text-xl md:text-2xl text-cyan-50 font-medium opacity-90 mb-4">
                    Select registration type
                </h3>
                <colorbar class="block w-48 h-1.5 rounded-full" />
            </div>

            <div class="flex flex-col lg:flex-row gap-8 items-start">


                <div class="w-full lg:w-5/12 space-y-6">

                    <div v-if="!macroSeccion" class="space-y-6 animate-fade-in">

                        <button @click="macroSeccion = 'inscripciones'"
                            class="w-full group relative flex items-center justify-between p-8 bg-blue-600/10 border-2 border-blue-500/50 rounded-3xl backdrop-blur-xl transition-all duration-500 hover:border-blue-400 hover:bg-blue-600/20 text-left overflow-hidden border-loading-effect glow-intense">

                            <div class="absolute inset-0 border-anim-line pointer-events-none opacity-100"></div>

                            <div
                                class="absolute inset-0 bg-blue-400/20 animate-soft-glow pointer-events-none filter blur-xl">
                            </div>

                            <div
                                class="absolute inset-0 bg-gradient-to-r from-transparent via-white/40 to-transparent -translate-x-full animate-shimmer-effect pointer-events-none">
                            </div>

                            <div class="flex flex-col z-10">
                                <div class="flex flex-col z-10">
                                    <span class="text-yellow-400 text-xs uppercase tracking-widest font-bold mb-1">
                                        Register as a participant, select your category, and complete payment
                                    </span>
                                    <!-- <span
                                        class="text-blue-300 text-xs uppercase tracking-widest font-black mb-1 drop-shadow-[0_0_5px_rgba(59,130,246,0.8)]">
                                        Register now and get access to shorts courses and technical visits
                                    </span> -->
                                    <h5
                                        class="text-2xl font-black text-white group-hover:text-blue-200 transition-colors flex items-center gap-3 drop-shadow-[0_0_10px_rgba(255,255,255,0.3)]">
                                        REGISTRATION TO EVENT
                                        <span class="relative flex h-3 w-3">
                                            <span
                                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-300 opacity-90"></span>
                                            <span
                                                class="relative inline-flex rounded-full h-3 w-3 bg-blue-400 shadow-[0_0_15px_#3b82f6]"></span>
                                        </span>
                                    </h5>

                                </div>
                            </div>

                            <div
                                class="p-4 rounded-2xl bg-blue-500/20 border-2 border-blue-400/50 group-hover:bg-blue-600 group-hover:shadow-[0_0_30px_rgba(59,130,246,0.8)] transition-all duration-300 z-10">
                                <GreenArrowRight class="w-6 h-6 invert brightness-200" />
                            </div>
                        </button>
                        <button @click="macroSeccion = 'viajes'"
                            class="w-full group relative flex items-center justify-between p-8 bg-white/5 border border-white/10 rounded-3xl backdrop-blur-md transition-all duration-300 hover:border-green-500 hover:bg-white/10 hover:shadow-[0_0_20px_rgba(34,197,94,0.3)] text-left">
                            <div class="flex flex-col z-10">

                                <span class="text-yellow-price text-xs uppercase tracking-widest font-bold mb-1">
                                    Sign up for optional site visits and training programs
                                </span>
                                <h5 class="text-2xl font-black text-white group-hover:text-green-200 transition-colors">
                                    TECHNICAL VISITS AND SHORT COURSES
                                </h5>

                            </div>
                            <div
                                class="p-4 rounded-2xl bg-white/10 group-hover:bg-green-600 transition-all duration-300">
                                <GreenArrowRight class="w-6 h-6 invert brightness-200" />
                            </div>
                        </button>
                    </div>
                    <div v-else class="space-y-6 animate-fade-in-right">
                        <button @click="volverAMacro"
                            class="group flex items-center gap-3 px-4 py-2 bg-white/10 hover:bg-white/20 border border-white/20 hover:border-white/40 rounded-xl transition-all duration-300 mb-8 w-fit shadow-lg backdrop-blur-sm">
                            <div class="rotate-180 transition-transform group-hover:-translate-x-1">
                                <GreenArrowRight class="w-4 h-4 invert brightness-200" />
                            </div>
                            <span class="text-white text-xs font-black uppercase tracking-[0.15em]">
                                Back to Start
                            </span>
                        </button>

                        <!-- <h4 class="text-white/60 font-bold text-[15px] uppercase tracking-[0.2em] mb-4">
                            Select your profile for <span class="text-yellow-price">{{ macroSeccion === 'inscripciones'
                                ? 'REGISTRATION' : 'TOURS & COURSES' }}</span>:
                        </h4> -->
                        <button @click="seleccionarGrupo('participante')"
                            :class="grupoSeleccionado === 'participante'
                                ? 'bg-blue-900/40 border-blue-400 shadow-[0_0_35px_rgba(59,130,246,0.6)] scale-105 ring-1 ring-blue-300'
                                : 'bg-white/5 border-white/10 hover:bg-white/10 hover:border-blue-500 hover:shadow-[0_0_20px_rgba(59,130,246,0.3)]'"
                            class="w-full group relative flex items-center justify-between p-6 backdrop-blur-md border rounded-3xl transition-all duration-300 ease-out text-left">

                            <div class="flex flex-col z-10">
                                <span :class="grupoSeleccionado === 'participante' ? 'text-blue-200' : 'text-blue-400'"
                                    class="text-xs uppercase tracking-widest font-bold mb-1 transition-colors">
                                    Registration
                                </span>
                                <h5 class="text-2xl font-black text-white group-hover:text-blue-200 transition-colors">
                                    PARTICIPANT
                                </h5>
                                <p class="text-xs text-gray-400 mt-1  group-hover:text-yellow-100/70 transition-colors">
                                    General access for professionals, students, and attendees interested in
                                    participating in the event, conferences, and scheduled activities.
                                </p>
                            </div>

                            <div :class="grupoSeleccionado === 'participante'
                                ? 'bg-blue-600 shadow-lg scale-110 rotate-0'
                                : 'bg-white/10 group-hover:bg-blue-600 group-hover:rotate-[-45deg]'"
                                class="p-4 rounded-2xl transition-all duration-300">
                                <GreenArrowRight class="w-6 h-6 invert brightness-200" />
                            </div>
                        </button>

                        <button @click="seleccionarGrupo('autor')" v-if="macroSeccion === 'inscripciones'"
                            :class="grupoSeleccionado === 'autor'
                                ? 'bg-cyan-900/40 border-cyan-400 shadow-[0_0_35px_rgba(34,211,238,0.6)] scale-105 ring-1 ring-cyan-300'
                                : 'bg-white/5 border-white/10 hover:bg-white/10 hover:border-cyan-500 hover:shadow-[0_0_20px_rgba(34,211,238,0.3)]'"
                            class="w-full group relative flex items-center justify-between p-6 backdrop-blur-md border rounded-3xl transition-all duration-300 ease-out text-left">

                            <div class="flex flex-col z-10">
                                <span :class="grupoSeleccionado === 'autor' ? 'text-cyan-200' : 'text-cyan-400'"
                                    class="text-xs uppercase tracking-widest font-bold mb-1 transition-colors">
                                    Registration
                                </span>
                                <h5 class="text-2xl font-black text-white group-hover:text-cyan-200 transition-colors">
                                    SELECTED AUTHOR - COAUTHOR
                                </h5>
                                <p class="text-xs text-gray-400 mt-1 group-hover:text-yellow-100/70 transition-colors">
                                    Exclusive registration for authors whose papers have been accepted and selected as
                                    winners for presentation at the event.
                                </p>
                            </div>

                            <div :class="grupoSeleccionado === 'autor'
                                ? 'bg-cyan-600 shadow-lg scale-110 rotate-0'
                                : 'bg-white/10 group-hover:bg-cyan-600 group-hover:rotate-[-45deg]'"
                                class="p-4 rounded-2xl transition-all duration-300">
                                <GreenArrowRight class="w-6 h-6 invert brightness-200" />
                            </div>
                        </button>
                    </div>
                </div>

                <div class="w-full lg:w-7/12 mt-8 lg:mt-0 relative min-h-[300px]">
                    <div v-if="grupoSeleccionado" id="section-categories"
                        class="flex flex-col gap-5 animate-fade-in-right">

                        <div class="flex items-center justify-between px-2 mb-1">
                            <h6 class="text-white/40 text-[10px] font-bold uppercase tracking-[0.2em]">
                                SELECT CATEGORY
                            </h6>
                        </div>

                        <div v-for="cat in categoriasVisibles" :key="cat.id" @click="irAlFormulario(cat.id)"
                            class="w-full cursor-pointer group relative flex flex-row items-center justify-between p-6 rounded-3xl
                                    bg-white/5 backdrop-blur-xl border border-white/10
                                    transition-all duration-300 ease-out overflow-hidden
                                    hover:bg-gradient-to-r hover:from-yellow-900/40 hover:to-transparent
                                    hover:border-yellow-400/80 hover:shadow-[0_0_35px_rgba(234,179,8,0.4)] hover:scale-[1.02] hover:-translate-y-1">

                            <div
                                class="absolute inset-0 bg-yellow-500/10 opacity-0 group-hover:opacity-100 transition-opacity duration-500 blur-xl">
                            </div>

                            <div class="relative z-10 flex-1 pr-6">
                                <div class="flex items-center gap-2 mb-2">
                                    <span
                                        class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md bg-white/10 text-white/60 group-hover:bg-yellow-500/20 group-hover:text-yellow-200 transition-colors">
                                        {{ cat.grupo === 'autor' ? 'Author Rate' : 'Attendee Rate' }}
                                    </span>
                                </div>
                                <h4
                                    class="text-lg md:text-xl font-bold text-white leading-tight group-hover:text-yellow-100 transition-colors">
                                    {{ cat.nombre_en }}
                                </h4>
                                <p class="text-xs text-gray-400 mt-1  group-hover:text-yellow-100/70 transition-colors">
                                    {{ cat.categoria_description }}
                                </p>
                            </div>

                            <div
                                class="relative z-10 text-right flex flex-col items-end border-l border-white/10 pl-6 group-hover:border-yellow-500/30 transition-colors">
                                <!-- <span
                                    class="text-2xl md:text-4xl font-black text-yellow-price drop-shadow-[0_2px_10px_rgba(234,179,8,0.3)] group-hover:scale-110 transition-transform duration-300 origin-right">
                                    {{ cat.precio_disponible?.moneda?.simbolo || '$' }}{{ cat.precio_disponible?.valor
                                        || '0' }}
                                </span> -->
                                <span v-if="macroSeccion === 'inscripciones'" translate="no"
                                    class="text-2xl md:text-4xl font-black text-yellow-price drop-shadow-[0_2px_10px_rgba(234,179,8,0.3)] group-hover:scale-110 transition-transform duration-300 origin-right">
                                    {{ cat.precio_disponible?.moneda?.simbolo || 'USD ' }}{{
                                        cat.precio_disponible?.valor
                                        || '0' }}
                                </span>
                                <!--    <span class="text-[9px] uppercase text-gray-400 font-bold mb-3 block tracking-wider">
                                    + TAX / IVA
                                </span>
                            -->
                                <div
                                    class="px-4 py-2 rounded-full flex items-center gap-2 text-xs font-bold transition-all duration-300
                                            border border-yellow-500/30 text-yellow-400 bg-yellow-500/5
                                            group-hover:bg-yellow-500 group-hover:text-black group-hover:shadow-[0_0_15px_rgba(234,179,8,0.6)]">
                                    {{ macroSeccion === 'inscripciones' ? 'Select' : 'View Details' }}
                                    <GreenArrowRight
                                        class="w-3 h-3 transition-all duration-300 group-hover:invert group-hover:brightness-0" />
                                </div>
                            </div>
                        </div>

                    </div>


                    <div v-else class="w-full mt-8 md:mt-0">
                        <div class="p-8 bg-black/20 backdrop-blur-sm border-l-4 border-yellow-price rounded-r-2xl">
                            <h6 class="text-yellow-price font-bold uppercase tracking-tighter mb-2">
                                Important Note
                            </h6>
                            <div class="text-white/80 text-sm leading-relaxed">
                                <p>
                                    The World Mining Congress 2026 registration system is designed to manage participant
                                    registrations and payment processing.
                                </p>

                                <p class="mt-4">
                                    For any questions or technical issues related to the system or paper submissions,
                                    please contact us via:
                                </p>

                                <div class="flex flex-col gap-3 mt-4">
                                    <a href="mailto:wmc.itsupport@iimp.org.pe" @click.prevent="copiarCorreo"
                                        class="flex items-center gap-2 text-cyan-400 font-bold underline hover:text-cyan-300 transition-colors">
                                        <i class="pi pi-envelope"></i>
                                        wmc.itsupport@iimp.org.pe
                                    </a>

                                    <transition name="bounce">
                                        <span v-if="copiado"
                                            class="absolute -top-10 left-0 bg-green-500 text-white text-xs font-black py-1.5 px-3 rounded-full shadow-[0_0_15px_rgba(34,197,94,0.6)] flex items-center whitespace-nowrap z-50">
                                            <i class="pi pi-check-circle mr-2"></i>
                                            Email copied. Please paste it into your email client.
                                        </span>
                                    </transition>

                                    <a href="https://api.whatsapp.com/send/?phone=51987196080&text=Hello, I need assistance with WMC 2026 registration&type=phone_number&app_absent=0"
                                        target="_blank"
                                        class="flex items-center gap-2 text-green-400 font-bold underline hover:text-green-300 transition-colors">
                                        <i class="pi pi-whatsapp"></i>
                                        WhatsApp Support
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.animate-fade-in-right {
    animation: fadeInRight 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes fadeInRight {
    from {
        opacity: 0;
        transform: translateX(20px);
    }

    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.animate-fade-in {
    animation: fadeIn 0.8s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }
}

.preventa-banner-home {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(245, 158, 11, 0.05) 100%);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-left: 6px solid #f59e0b;
    border-radius: 24px;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    /* Para soporte en Safari/iPhone */
    position: relative;
    overflow: hidden;
}

.banner-home-content {
    display: flex;
    align-items: center;
    padding: 1.5rem;
    gap: 1.25rem;
}

.banner-home-icon {
    background: rgba(245, 158, 11, 0.2);
    width: 56px;
    height: 56px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.tag-early-bird {
    background: #f59e0b;
    color: #000;
    font-size: 0.65rem;
    font-weight: 900;
    padding: 2px 8px;
    border-radius: 6px;
    letter-spacing: 0.05em;
}

.banner-home-text h2 {
    line-height: 1.2;
    margin-bottom: 4px;
}

/* Adaptación para Mobile */
@media (max-width: 640px) {
    .banner-home-content {
        padding: 1.25rem;
        flex-direction: column;
        text-align: center;
    }

    .banner-home-icon {
        width: 48px;
        height: 48px;
    }

    .banner-home-text h2 {
        font-size: 1.25rem;
    }

    .banner-home-text p {
        font-size: 0.85rem;
    }
}

/* Animación de entrada igual a la de tus títulos */
.animate-fade-in-down {
    animation: fadeInDown 0.6s ease-out forwards;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Animación de parpadeo suave del fondo */
@keyframes soft-glow {

    0%,
    100% {
        opacity: 0.3;
    }

    50% {
        opacity: 0.8;
    }
}

.animate-soft-glow {
    animation: soft-glow 3s ease-in-out infinite;
}

/* Animación de destello que cruza el botón */
@keyframes shimmer-effect {
    0% {
        transform: translateX(-100%);
    }

    30% {
        transform: translateX(100%);
    }

    100% {
        transform: translateX(100%);
    }
}

.animate-shimmer-effect {
    animation: shimmer-effect 4s infinite cubic-bezier(0.4, 0, 0.2, 1);
}

/* Mejora la escala al cargar para que se sienta dinámico */
button {
    backface-visibility: hidden;
    transform: translateZ(0);
}

/* --- EFECTO DE BORDE CARGANDO / PARPADEANTE --- */

.border-loading-effect {
    position: relative;
    /* Asegura que el contenido esté sobre el efecto */
}

/* El resplandor parpadeante general del borde */
@keyframes border-pulse {

    0%,
    100% {
        border-color: rgba(59, 130, 246, 0.2);
        box-shadow: 0 0 10px rgba(59, 130, 246, 0.1);
    }

    50% {
        border-color: rgba(59, 130, 246, 0.8);
        box-shadow: 0 0 25px rgba(59, 130, 246, 0.4);
    }
}

.border-loading-effect {
    animation: border-pulse 4s infinite ease-in-out;
}

/* La línea que recorre el borde (opcional, muy profesional) */
.border-anim-line::before {
    content: "";
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: conic-gradient(transparent,
            transparent,
            transparent,
            #3b82f6
            /* Azul principal */
        );
    animation: rotate-border 4s linear infinite;
    z-index: 0;
}

/* Máscara para que el efecto solo se vea en el borde de 2px */
.border-loading-effect::after {
    content: "";
    position: absolute;
    inset: 4px;
    /* Grosor del borde */

    /* Debe coincidir con el fondo oscuro de tu app */
    border-radius: 22px;
    /* Ajustado al rounded-3xl menos el inset */
    z-index: 0;
}

/* Asegurar que el contenido del botón esté arriba de todo */
.border-loading-effect>div {
    position: relative;
    z-index: 10;
}

@keyframes rotate-border {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

/* Estilo para los círculos de los pasos */
.stepper-circle {
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

/* Animación para que la barra de progreso se sienta suave */
.progress-bar-glow {
    box-shadow: 0 0 10px rgba(59, 130, 246, 0.5);
}

/* --- EFECTO DE RESPLANDOR EXTREMO --- */

.glow-intense {
    /* Crea un halo de luz alrededor del botón */
    box-shadow: 0 0 20px rgba(59, 130, 246, 0.2), inset 0 0 15px rgba(59, 130, 246, 0.2);
}

@keyframes border-pulse {

    0%,
    100% {
        border-color: rgba(59, 130, 246, 0.4);
        box-shadow: 0 0 15px rgba(59, 130, 246, 0.3), inset 0 0 10px rgba(59, 130, 246, 0.2);
    }

    50% {
        border-color: rgba(147, 197, 253, 1);
        /* Azul muy claro */
        box-shadow: 0 0 40px rgba(59, 130, 246, 0.8), inset 0 0 20px rgba(59, 130, 246, 0.4);
    }
}

.border-loading-effect {
    animation: border-pulse 2.5s infinite ease-in-out;
    /* Más rápido para dar energía */
}

/* La línea que recorre el borde ahora es un "Láser" */
.border-anim-line::before {
    content: "";
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: conic-gradient(transparent,
            transparent,
            rgba(59, 130, 246, 0.1),
            #3b82f6,
            /* Azul neón */
            #ffffff,
            /* Núcleo blanco del láser */
            #3b82f6,
            transparent);
    animation: rotate-border 3s linear infinite;
    z-index: 0;
    filter: blur(2px);
    /* Efecto neón */
}

.border-loading-effect::after {
    content: "";
    position: absolute;
    inset: 3px;
    /* Borde un poco más delgado para que se vea más la luz */

    /* Color oscuro de tu fondo */
    border-radius: 22px;
    z-index: 0;
}

/* Shimmer más rápido y brillante */
@keyframes shimmer-effect {
    0% {
        transform: translateX(-100%) skewX(-15deg);
    }

    25% {
        transform: translateX(100%) skewX(-15deg);
    }

    100% {
        transform: translateX(100%) skewX(-15deg);
    }
}

.animate-shimmer-effect {
    animation: shimmer-effect 3s infinite cubic-bezier(0.4, 0, 0.2, 1);
}

/* Resplandor interno que "respira" */
@keyframes soft-glow {

    0%,
    100% {
        opacity: 0.4;
        transform: scale(1);
    }

    50% {
        opacity: 0.9;
        transform: scale(1.05);
    }
}

.bounce-enter-active {
    animation: bounce-in 0.5s;
}

.bounce-leave-active {
    animation: bounce-in 0.5s reverse;
}

@keyframes bounce-in {
    0% {
        transform: scale(0) translateY(10px);
        opacity: 0;
    }

    50% {
        transform: scale(1.1) translateY(-5px);
    }

    100% {
        transform: scale(1) translateY(0);
        opacity: 1;
    }
}
</style>
