<template>
    <AppLayout :title="`Cliente: ${branch.name}`">
        <!-- === ENCABEZADO === -->
        <div class="p-4 md:p-6 lg:p-8 bg-white dark:bg-slate-900 border-b dark:border-slate-700">
            <div class="max-w-7xl mx-auto">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="flex items-center space-x-3">
                            <Back :href="route('branches.index')" />
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Cliente</p>
                                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                                    {{ branch.name }}
                                </h1>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <Link :href="route('branches.edit', branch.id)">
                            <SecondaryButton>
                                <i class="fa-solid fa-pen-to-square mr-2"></i>
                                Editar
                            </SecondaryButton>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- === CONTENIDO PRINCIPAL === -->
        <div class="py-7">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                    <!-- COLUMNA IZQUIERDA: INFORMACIÓN CLAVE Y CONTACTOS -->
                    <div class="lg:col-span-1 space-y-6">
                        <!-- Card de Información Clave -->
                        <div class="bg-white dark:bg-slate-900 shadow-lg rounded-lg p-6">
                            <h3 class="text-lg font-semibold border-b dark:border-slate-700 pb-3 mb-4">Información Clave</h3>
                            <ul class="space-y-3 text-sm">
                                <li class="flex justify-between">
                                    <span class="font-semibold text-gray-600 dark:text-gray-400">Estatus:</span>
                                    <el-tag :type="branch.status === 'Cliente' ? 'success' : 'info'" size="small">{{ branch.status }}</el-tag>
                                </li>
                                <li class="flex justify-between">
                                    <span class="font-semibold text-gray-600 dark:text-gray-400">Vendedor:</span>
                                    <span>{{ branch.account_manager?.name ?? 'No asignado' }}</span>
                                </li>
                                <li class="flex justify-between">
                                    <span class="font-semibold text-gray-600 dark:text-gray-400">Matriz:</span>
                                    <span>{{ branch.parent?.name ?? 'N/A' }}</span>
                                </li>
                                <li class="flex justify-between">
                                    <span class="font-semibold text-gray-600 dark:text-gray-400">RFC:</span>
                                    <span>{{ branch.rfc ?? 'No especificado' }}</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Card de Contactos -->
                        <div class="bg-white dark:bg-slate-900 shadow-lg rounded-lg p-6">
                            <h3 class="text-lg font-semibold border-b dark:border-slate-700 pb-3 mb-4">Contactos</h3>
                            <div v-if="branch.contacts.length" class="space-y-4">
                                <div v-for="contact in branch.contacts" :key="contact.id">
                                    <p class="font-semibold">{{ contact.name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ contact.charge }}</p>
                                    <div class="text-sm mt-1 space-y-1">
                                        <p><i class="fa-solid fa-envelope mr-2 text-gray-400"></i> {{ getPrimaryDetail(contact, 'email') }}</p>
                                        <p><i class="fa-solid fa-phone mr-2 text-gray-400"></i> {{ getPrimaryDetail(contact, 'phone') }}</p>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-sm text-gray-500 dark:text-gray-400">No hay contactos registrados.</p>
                        </div>
                    </div>

                    <!-- COLUMNA DERECHA: PESTAÑAS DE INFORMACIÓN -->
                    <div class="lg:col-span-2">
                        <div class="bg-white dark:bg-slate-900 shadow-lg rounded-lg">
                             <el-tabs v-model="activeTab" class="p-6">
                                <el-tab-pane label="Información General" name="general">
                                    <ul class="space-y-4 text-sm mt-2">
                                        <li><strong class="font-semibold w-32 inline-block">Dirección:</strong> {{ branch.address ?? 'No especificada' }}</li>
                                        <li><strong class="font-semibold w-32 inline-block">Código Postal:</strong> {{ branch.post_code ?? 'N/A' }}</li>
                                        <li><strong class="font-semibold w-32 inline-block">Nos conoció por:</strong> {{ branch.meet_way ?? 'No especificado' }}</li>
                                        <li><strong class="font-semibold w-32 inline-block">Notas:</strong> {{ branch.important_notes ?? 'No hay notas.' }}</li>
                                    </ul>
                                </el-tab-pane>
                                <el-tab-pane label="Cotizaciones" name="quotes">
                                    <p class="text-sm text-gray-500 dark:text-gray-400 p-4 text-center">Próximamente: Historial de cotizaciones.</p>
                                </el-tab-pane>
                                <el-tab-pane label="Ventas" name="sales">
                                    <p class="text-sm text-gray-500 dark:text-gray-400 p-4 text-center">Próximamente: Historial de ventas.</p>
                                </el-tab-pane>
                                <el-tab-pane label="Proyectos" name="projects">
                                    <p class="text-sm text-gray-500 dark:text-gray-400 p-4 text-center">Próximamente: Proyectos relacionados.</p>
                                </el-tab-pane>
                            </el-tabs>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import Back from "@/Components/MyComponents/Back.vue";
import { Link } from "@inertiajs/vue3";

export default {
    // Usando Options API
    data() {
        return {
            activeTab: 'general',
        };
    },
    components: {
        AppLayout,
        SecondaryButton,
        Back,
        Link,
    },
    props: {
        branch: Object,
    },
    methods: {
        getPrimaryDetail(contact, type) {
            const detail = contact.details.find(d => d.type === type && d.is_primary);
            return detail ? detail.value : 'No disponible';
        }
    }
};
</script>

<style>
/* Personalización para que las pestañas se vean más limpias */
.el-tabs__header {
    margin-bottom: 24px !important;
}
</style>
