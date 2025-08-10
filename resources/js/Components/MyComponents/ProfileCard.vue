<template>
    <div class="z-50 h-64 w-60 bg-zinc-800 shadow-2xl border border-white/10 absolute rounded-xl">
        
        <div class="h-[35%] bg-zinc-900/50 rounded-t-xl">
            <button @click="$emit('close')" 
                    class="absolute top-2 right-2 flex items-center justify-center size-7 rounded-full text-gray-400 hover:bg-white/10 hover:text-white transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
            
            <Link :href="route('profile.show')">
                <button class="absolute flex items-center justify-center size-7 rounded-full top-2 left-2 text-gray-400 hover:bg-white/10 hover:text-white transition-colors">
                    <i class="fa-solid fa-pen text-xs"></i>
                </button>
            </Link>
        </div>

        <figure class="absolute size-24 top-10 left-[calc(50%-3rem)] ring-4 ring-zinc-800 rounded-full">
            <img :src="$page.props.auth.user.profile_photo_url" 
                 class="size-24 object-cover rounded-full"
                 alt="Foto de perfil">
        </figure>

        <div class="flex flex-col text-center mt-14 px-4">
            <span class="font-bold text-white">{{ $page.props.auth.user.name }}</span>
            <span class="text-xs text-gray-400">{{ $page.props.auth.user.employee_properties?.charge }}</span>
            
            <div class="mt-3 space-y-2 text-left">
                <p class="flex items-center space-x-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-gray-500" viewBox="0 0 24 24" fill="currentColor"><path d="M1.75 3h20.5c.966 0 1.75.784 1.75 1.75v14.5A1.75 1.75 0 0 1 22.25 21H1.75A1.75 1.75 0 0 1 0 19.25V4.75C0 3.784.784 3 1.75 3ZM22 5.613l-9.594 6.236a1.75 1.75 0 0 1-2.008-.002L2 5.612V19h20V5.613Z"/></svg>
                    <span class="text-xs text-gray-300">{{ $page.props.auth.user.email ?? '-' }}</span>
                </p>
                <p v-if="$page.props.auth.user.employee_properties?.phone" class="flex items-center space-x-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-gray-500" viewBox="0 0 24 24" fill="currentColor"><path d="M6.5 2h11A2.5 2.5 0 0 1 20 4.5v15a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2ZM14 20a1 1 0 1 0-4 0h4Z"/></svg>
                    <span class="text-xs text-gray-300">{{ $page.props.auth.user.employee_properties?.phone }}</span>
                </p>
            </div>
        </div>

        <div class="absolute bottom-3 w-full px-4">
             <form method="POST" @submit.prevent="logout">
                <button class="w-full text-center py-2 rounded-lg text-sm
                               text-red-400 bg-red-500/10 
                               hover:bg-red-500/20 hover:text-red-300 transition-colors">
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