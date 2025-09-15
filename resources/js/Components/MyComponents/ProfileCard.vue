<template>
    <!-- Contenedor principal con estilos para light/dark mode y un efecto de desenfoque en modo oscuro -->
    <div class="z-50 w-80 absolute rounded-2xl border bg-white shadow-2xl dark:border-white/10 dark:bg-zinc-900">
        
        <!-- Encabezado con gradiente futurista -->
        <div class="h-24 bg-gradient-to-br from-sky-600 to-indigo-700">
            <!-- Botón de cerrar -->
            <button @click="$emit('close')" 
                    class="absolute top-3 right-3 flex h-7 w-7 items-center justify-center rounded-full text-white/70 transition-colors hover:bg-black/20 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M11.9997 10.5865L16.9495 5.63672L18.3637 7.05093L13.4139 12.0007L18.3637 16.9504L16.9495 18.3646L11.9997 13.4148L7.04996 18.3646L5.63574 16.9504L10.5855 12.0007L5.63574 7.05093L7.04996 5.63672L11.9997 10.5865Z"></path></svg>
            </button>
            
            <!-- Botón para editar perfil -->
            <Link :href="route('profile.show')">
                <button class="absolute top-3 left-3 flex h-7 w-7 items-center justify-center rounded-full text-white/70 transition-colors hover:bg-black/20 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M16.707 2.293a1 1 0 0 1 1.414 0l4 4a1 1 0 0 1 0 1.414l-13 13a1 1 0 0 1-.707.293H4a1 1 0 0 1-1-1v-4.414a1 1 0 0 1 .293-.707l13-13zm3 4.414L18.414 5.414 15 8.828l1.293 1.293L20.121 6.293 19.707 6.707zM14.293 9.536l-10-10L4 0v4.414l-1.707 1.707.293.293H3v1h1v1h1v1h.586l9.707-9.707zM13 10.828l-8.879 8.879A1 1 0 0 1 3 19.414V21h1.586a1 1 0 0 1 .707-.293l8.879-8.879L13 10.828z"></path></svg>
                </button>
            </Link>
        </div>

        <!-- Contenedor para la foto de perfil y la información del usuario -->
        <div class="p-6 pt-0 text-center">
            <!-- Foto de perfil, se superpone sobre el header -->
            <figure class="absolute left-[calc(50%-3.5rem)] top-10 h-28 w-28 rounded-full ring-4 ring-white dark:ring-zinc-900">
                <img :src="$page.props.auth.user.profile_photo_url" 
                     class="h-28 w-28 rounded-full object-cover"
                     alt="Foto de perfil">
            </figure>

            <!-- Nombre y Puesto -->
            <div class="mt-20">
                <h1 class="text-xl font-bold text-zinc-800 dark:text-white">{{ $page.props.auth.user.name }}</h1>
                <!-- NOTA: Asegúrate de que `employee_detail.job_position` esté disponible en las props de tu página -->
                <p class="text-sm text-zinc-500 dark:text-gray-400">{{ $page.props.auth.user.employee_detail?.job_position ?? 'Puesto no asignado' }}</p>
                <p class="text-sm text-zinc-500 dark:text-gray-400">Rol: {{ $page.props.auth.user.role }}</p>
            </div>

            <!-- Separador -->
            <hr class="my-5 border-dashed border-zinc-200 dark:border-zinc-700">

            <!-- Detalles del empleado -->
            <div class="space-y-4 text-left">
                <!-- Salario y Horas -->
                <div class="grid grid-cols-2 gap-4 text-center">
                    <div class="rounded-lg bg-zinc-100 p-3 dark:bg-zinc-800">
                        <p class="text-xs text-zinc-500 dark:text-gray-400">Salario Semanal</p>
                        <p class="font-semibold text-zinc-800 dark:text-white blur-sm">${{ $page.props.auth.user.employee_detail?.week_salary?.toLocaleString() ?? 'N/A' }}</p>
                    </div>
                    <div class="rounded-lg bg-zinc-100 p-3 dark:bg-zinc-800">
                        <p class="text-xs text-zinc-500 dark:text-gray-400">Horas/Semana</p>
                        <p class="font-semibold text-zinc-800 dark:text-white">{{ $page.props.auth.user.employee_detail?.hours_per_week ?? 'N/A' }}</p>
                    </div>
                </div>

                <!-- Departamento -->
                <div class="flex items-center space-x-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M21 20h-2v-3h-2v3h-2v-2h-2v2h-2v-3h-2v3H7v-2H5v2H3v-3H1V6h2V3h2v2h2V3h2v2h2V3h2v2h2V3h2v3h2ZM7 8H5v2h2Zm4 0H9v2h2Zm4 0h-2v2h2Zm4 0h-2v2h2Zm-8 4h-2v2h2Zm4 0h-2v2h2Z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs text-zinc-500 dark:text-gray-400">Departamento</p>
                        <p class="text-sm font-medium text-zinc-800 dark:text-gray-300">{{ $page.props.auth.user.employee_detail?.department ?? '-' }}</p>
                    </div>
                </div>

                <!-- Email -->
                <div class="flex items-center space-x-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M1.75 3h20.5c.966 0 1.75.784 1.75 1.75v14.5A1.75 1.75 0 0 1 22.25 21H1.75A1.75 1.75 0 0 1 0 19.25V4.75C0 3.784.784 3 1.75 3ZM22 5.613l-9.594 6.236a1.75 1.75 0 0 1-2.008-.002L2 5.612V19h20V5.613Z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs text-zinc-500 dark:text-gray-400">Email</p>
                        <p class="text-sm font-medium text-zinc-800 dark:text-gray-300">{{ $page.props.auth.user.email ?? '-' }}</p>
                    </div>
                </div>
            </div>

            <!-- Separador -->
            <hr class="my-5 border-dashed border-zinc-200 dark:border-zinc-700">

            <!-- Botón de Cerrar Sesión -->
            <form method="POST" @submit.prevent="logout">
                <button class="w-full rounded-lg py-2.5 text-sm font-semibold text-red-500 transition-colors bg-red-500/10 hover:bg-red-500/20 dark:text-red-400 dark:bg-red-500/10 dark:hover:bg-red-500/20">
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</template>

<script>
import { Link } from "@inertiajs/vue3"

export default {
    emits: ['close'],
    components: {
        Link,
    },
    methods: {
        logout() {
            this.$inertia.post(route('logout'));
        },
    },
}
</script>
