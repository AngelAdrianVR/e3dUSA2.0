<template>
    <AppLayout title="Tutoriales y manuales">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Tutoriales y manuales
        </h2>

        <div class="py-5">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex sm:flex-row justify-between items-center mb-6 space-y-3 sm:space-y-0">
                        <Link v-if="$page.props.auth.user.permissions.includes('Crear tutoriales y manuales')"
                            :href="route('manuals.create')">
                            <SecondaryButton class="rounded-xl w-full sm:w-auto">
                                <i class="fa-solid fa-plus mr-2"></i>
                                Nuevo manual
                            </SecondaryButton>
                        </Link>
                        
                        <SearchInput @keyup.enter="searchManuals" @cleanSearch="searchManuals" v-model="inputSearch" :searchProps="SearchProps" />
                    </div>

                    <div class="relative">
                        <div v-if="loadingSearch" class="absolute inset-0 bg-white/75 dark:bg-slate-900/75 flex items-center justify-center z-20 rounded-lg">
                            <LoadingIsoLogo />
                        </div>
                        <div v-if="allManuals.length > 0" class="max-h-[65vh] overflow-y-auto">
                            <ManualPresentation v-for="item in allManuals" :key="item.id" :manual="item" />
                            <div ref="sentinel" class="h-10"></div>

                            <div v-if="loading" class="text-center py-4 text-gray-500 dark:text-gray-400">
                                <i class="fa-solid fa-spinner fa-spin text-2xl"></i>
                                <p class="mt-2">Cargando más...</p>
                            </div>
                        </div>
                        
                        <div v-else>
                            <Empty />
                            <!-- <p class="text-center text-base dark:text-white">Registrar <button @click="$inertia.visit(route('manuals.create'))" class="hover:underline text-secondary dark:text-blue-300">aquí</button></p> -->
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import SearchInput from '@/Components/MyComponents/SearchInput.vue';
import Empty from '@/Components/MyComponents/Empty.vue';
import SecondaryButton from "@/Components/SecondaryButton.vue";
import ManualPresentation from "@/Components/MyComponents/ManualPresentation.vue";
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import { Link, router } from "@inertiajs/vue3";

export default {
    components: {
        Link,
        AppLayout,
        Empty,
        SearchInput,
        LoadingIsoLogo,
        SecondaryButton,
        ManualPresentation,
    },
    props: {
        manuals: Object, // Ahora es un objeto Paginator de Laravel
        filters: Object, // Para recibir los filtros de búsqueda del controlador
    },
    data() {
        return {
            loadingSearch: false, // Para mostrar el loading al buscar
            inputSearch: this.filters.search || "",
            // 'allManuals' almacenará todos los manuales cargados (los de la página 1, 2, 3, etc.)
            allManuals: this.manuals.data,
            loading: false, // Para saber si se está cargando una nueva página
            observer: null, // Para el IntersectionObserver
            SearchProps: ['Título', 'Tipo', 'Creador'], // indica por cuales propiedades del registro puedes buscar
        };
    },
    watch: {
        // Si la propiedad 'manuals' cambia (ej. por una nueva búsqueda),
        // reseteamos la lista de 'allManuals'.
        manuals: {
            handler(newValue) {
                // Si es la primera página, reemplazamos los datos.
                if (newValue.current_page === 1) {
                    this.allManuals = newValue.data;
                } else {
                    // Si no, los agregamos a la lista existente.
                    this.allManuals = [...this.allManuals, ...newValue.data];
                }
                this.loading = false;
            },
            deep: true,
        },
    },
    methods: {
        searchManuals() {
            // 1. Inicia el estado de carga
            this.loadingSearch = true;

            router.get(route('manuals.index'), { search: this.inputSearch },{
                preserveState: true,
                replace: true,
                // 2. Usa el callback onFinish para detener la carga cuando todo termine
                onFinish: () => {
                    this.loadingSearch = false;
                },
            });
        },
        loadMoreManuals() {
            // Si no hay siguiente página o ya estamos cargando, no hacemos nada.
            if (!this.manuals.next_page_url || this.loading) {
                return;
            }

            this.loading = true;

            // Usamos router.get con opciones para una carga suave sin recargar toda la página
            router.get(this.manuals.next_page_url, {}, {
                preserveState: true,
                preserveScroll: true,
                only: ['manuals'], // Solo pedimos la prop 'manuals' al servidor
                onFinish: () => {
                    this.loading = false;
                },
            });
        },
        // Inicializa el IntersectionObserver
        setupObserver() {
            this.observer = new IntersectionObserver(
                (entries) => {
                    // Si el elemento centinela es visible en la pantalla
                    if (entries[0].isIntersecting) {
                        this.loadMoreManuals();
                    }
                },
                {
                    rootMargin: "0px 0px 200px 0px", // Carga 200px antes de que llegue al final
                }
            );

            // Empezamos a observar el elemento centinela
            this.observer.observe(this.$refs.sentinel);
        },
    },
    mounted() {
        // Configuramos el observador cuando el componente se monta
        if ( this.manuals.data.length > 10 ) {
            this.setupObserver();
        }
    },
    beforeUnmount() {
        // Limpiamos el observador para evitar memory leaks
        if (this.observer) {
            this.observer.disconnect();
        }
    },
};
</script>