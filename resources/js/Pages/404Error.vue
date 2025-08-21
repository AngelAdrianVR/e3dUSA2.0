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
        <div class="car">
            <img class="w-3/4" src="@/../../public/images/teslaX_cartoon.png" alt="">
        </div>
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
