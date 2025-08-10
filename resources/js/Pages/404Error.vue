<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    status: Number,
});
</script>

<template>
    <Head title="Página No Encontrada" />
    <div class="relative min-h-screen w-full flex flex-col items-center justify-center bg-slate-900 text-white overflow-hidden">
        
        <!-- Contenido principal -->
        <div class="relative z-10 text-center space-y-4 px-4 animated-content">
            <h1 class="text-7xl md:text-9xl font-black text-gray-700 drop-shadow-lg">
                404
            </h1>
            <p class="text-2xl md:text-3xl font-bold text-gray-300">
                Parece que tomaste una ruta equivocada.
            </p>
            <p class="text-gray-400">
                La página que buscas no existe o fue movida a otro lugar.
            </p>
            <div class="pt-6">
                <Link 
                    :href="route('dashboard')"
                    class="
                        inline-block px-8 py-3 bg-red-600 text-white font-bold rounded-lg 
                        shadow-lg shadow-red-600/30 transition-all duration-300 
                        hover:bg-red-700 hover:shadow-xl hover:-translate-y-1
                    "
                >
                    Volver al Inicio
                </Link>
            </div>
        </div>

        <!-- Animación de la carretera -->
        <div class="road"></div>

        <!-- Coche SVG -->
        <!-- <div class="car">
            <svg class="w-48 md:w-64" viewBox="0 0 408 158" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M399 98C399 88.6112 391.389 81 382 81H340L333 51H104L86 98H26C16.6112 98 9 105.611 9 115V129C9 138.389 16.6112 146 26 146H43C50.1797 146 56 151.82 56 159H99C99 151.82 104.82 146 112 146H308C315.18 146 321 151.82 321 159H364C364 151.82 369.82 146 377 146H382C391.389 146 399 138.389 399 129V98Z" fill="#EF4444"/>
                <path d="M333 51L340 81H382C386.418 81 390 77.4183 390 73V51H333Z" fill="#DC2626"/>
                <path d="M104 51L118 81H333L327 51H104Z" fill="#F87171"/>
                <path d="M104 51L86 98H26C21.5817 98 18 94.4183 18 90V51H104Z" fill="#DC2626"/>
                <circle cx="77.5" cy="127.5" r="21.5" fill="#1E293B" stroke="#475569" stroke-width="8"/>
                <circle cx="342.5" cy="127.5" r="21.5" fill="#1E293B" stroke="#475569" stroke-width="8"/>
            </svg>
        </div> -->
    </div>
</template>

<style scoped>
.road {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 150px;
    background: #334155; /* Color del asfalto */
    border-top: 8px solid #475569;
    /* Líneas de la carretera */
    background-image: repeating-linear-gradient(
        90deg,
        transparent,
        transparent 40px,
        #f1f5f9 40px,
        #f1f5f9 80px
    );
    background-size: 120px 4px;
    background-repeat: repeat-x;
    background-position: center;
    animation: move-road 8s linear infinite;
}

@keyframes move-road {
    from { background-position-x: 0px; }
    to { background-position-x: -120px; }
}

.car {
    position: absolute;
    bottom: 80px;
    left: 50%;
    transform: translateX(-50%);
    animation: car-bob 1s ease-in-out infinite;
}

@keyframes car-bob {
    0% { transform: translate(-50%, 0); }
    50% { transform: translate(-50%, -5px); }
    100% { transform: translate(-50%, 0); }
}

.animated-content {
    animation: fade-in 1s ease-out forwards;
}

@keyframes fade-in {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
