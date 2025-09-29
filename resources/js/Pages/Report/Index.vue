<template>
    <AppLayout title="Reporte de Errores y Sugerencias">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Reporte de Errores y Sugerencias
        </h2>

        <div class="py-7">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <!-- <div class="flex justify-between items-center mb-6"> -->
                        <!-- <span></span> -->
                        <!-- Input de búsqueda -->
                        <!-- <SearchInput @keyup.enter="handleSearch" v-model="search" @cleanSearch="handleSearch" :searchProps="SearchProps" /> -->
                    <!-- </div> -->

                    <!-- Overlay de carga -->
                    <div class="relative">
                        <div v-if="loading"
                            class="absolute inset-0 bg-white/75 dark:bg-slate-900/75 flex items-center justify-center z-20 rounded-lg">
                            <LoadingIsoLogo />
                        </div>
                        
                        <!-- Tabla de Reportes -->
                        <el-table 
                            max-height="550" 
                            :data="tableData"
                            style="width: 100%" 
                            stripe
                            @row-click="showReportDetails"
                            class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300">

                            <el-table-column prop="id" label="ID" width="60" />
                            <el-table-column prop="title" label="Título" width="250" />
                            <el-table-column prop="type" label="Tipo" width="120">
                                 <template #default="scope">
                                    <el-tag :type="scope.row.type === 'Error' ? 'danger' : 'warning'" disable-transitions>
                                        {{ scope.row.type }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column prop="status" label="Estatus" width="120">
                                <template #default="scope">
                                    <el-tag :type="scope.row.status === 'Pendiente' ? 'info' : 'success'" disable-transitions>
                                        {{ scope.row.status }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="Usuario" width="200">
                                <template #default="scope">
                                    {{ scope.row.user?.name ?? 'N/A' }}
                                </template>
                            </el-table-column>
                             <el-table-column label="Fecha de creación">
                                <template #default="scope">
                                    {{ new Date(scope.row.created_at).toLocaleString() }}
                                </template>
                            </el-table-column>

                            <!-- Menú de acciones por fila -->
                            <el-table-column align="right">
                                <template #default="scope">
                                    <el-dropdown trigger="click" @command="handleCommand">
                                        <button @click.stop
                                            class="el-dropdown-link mr-3 justify-center items-center size-8 rounded-full text-secondary hover:bg-[#F2F2F2] dark:hover:bg-slate-500 transition-all duration-200 ease-in-out">
                                            <i class="fa-solid fa-ellipsis-vertical"></i>
                                        </button>
                                        <template #dropdown>
                                            <el-dropdown-menu>
                                                <el-dropdown-item :command="'details-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                    </svg>Ver detalles
                                                </el-dropdown-item>
                                                <el-dropdown-item
                                                    v-if="scope.row.status === 'Pendiente'"
                                                    :command="'update-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    </svg>Marcar como Atendido
                                                </el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>

                    <!-- Paginación -->
                    <div v-if="reports.total > 0 && !search" class="flex justify-center mt-6">
                        <el-pagination v-model:current-page="reports.current_page"
                            :page-size="reports.per_page" :total="reports.total"
                            layout="prev, pager, next" background @current-change="handlePageChange" />
                    </div>
                </div>
            </div>
        </div>
         <!-- Modal para ver detalles -->
        <DialogModal :show="showDetailsModal" @close="showDetailsModal = false">
            <template #title>
                Detalles del Reporte #{{ selectedReport?.id }}
            </template>
            <template #content>
                <div v-if="selectedReport" class="space-y-4">
                    <div>
                        <h3 class="font-bold">Título:</h3>
                        <p>{{ selectedReport.title }}</p>
                    </div>
                    <div>
                        <h3 class="font-bold">Descripción:</h3>
                        <p class="whitespace-pre-wrap">{{ selectedReport.description }}</p>
                    </div>
                    <!-- Sección para mostrar imágenes adjuntas -->
                    <div v-if="selectedReport.media && selectedReport.media.length > 0">
                        <h3 class="font-bold">Archivos adjuntos:</h3>
                        <div class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-4">
                            <div v-for="mediaItem in selectedReport.media" :key="mediaItem.id">
                                <a :href="mediaItem.original_url" target="_blank" rel="noopener noreferrer" class="block">
                                    <img :src="mediaItem.original_url" :alt="mediaItem.name" class="rounded-lg object-cover h-32 w-full hover:opacity-80 transition-opacity">
                                </a>
                            </div>
                        </div>
                    </div>
                    <!-- Fin de la sección de imágenes -->
                    <div>
                        <h3 class="font-bold">Reportado por:</h3>
                        <p>{{ selectedReport.user?.name }}</p>
                    </div>
                     <div>
                        <h3 class="font-bold">Fecha:</h3>
                        <p>{{ new Date(selectedReport.created_at).toLocaleString() }}</p>
                    </div>
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="showDetailsModal = false">Cerrar</SecondaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import SearchInput from '@/Components/MyComponents/SearchInput.vue';
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import DialogModal from '@/Components/DialogModal.vue';
import { ElMessage, ElMessageBox } from 'element-plus';

export default {
    data() {
        return {
            loading: false,
            search: '',
            tableData: this.reports.data,
            SearchProps: ['Título', 'Tipo', 'Estatus', 'Usuario'],
            showDetailsModal: false,
            selectedReport: null,
        };
    },
    components: {
        AppLayout,
        SearchInput,
        LoadingIsoLogo,
        SecondaryButton,
        DialogModal,
    },
    props: {
        reports: Object,
    },
    methods: {
        handleSearch() {
            // Lógica de búsqueda (puedes implementarla más adelante si lo necesitas)
        },
        showReportDetails(row) {
            this.selectedReport = row;
            this.showDetailsModal = true;
        },
        handleCommand(command) {
            const [action, id] = command.split('-');
            const report = this.reports.data.find(item => item.id == id);
            
            if (action === 'details') {
                this.showReportDetails(report);
            } else if (action === 'update') {
                this.updateStatus(report);
            }
        },
        updateStatus(report) {
             ElMessageBox.confirm(
                `¿Estás seguro de que quieres marcar el reporte "${report.title}" como "Atendido"?`,
                'Confirmar Acción',
                {
                    confirmButtonText: 'Sí, marcar como atendido',
                    cancelButtonText: 'Cancelar',
                    type: 'warning',
                }
            ).then(() => {
                this.$inertia.put(route('reports.update', report.id), { status: 'Atendido' }, {
                    onSuccess: () => {
                        ElMessage.success('El estatus del reporte ha sido actualizado.');
                    },
                    onError: () => {
                        ElMessage.error('Ocurrió un error al actualizar el reporte.');
                    }
                });
            }).catch(() => {
                // El usuario canceló la acción
            });
        },
        handlePageChange(page) {
            this.$inertia.get(route('reports.index', { page: page }), {
                preserveState: true,
                replace: true,
            });
        },
    },
    watch: {
        'reports.data': {
            handler(newData) {
                if (!this.search) {
                    this.tableData = newData;
                }
            },
            deep: true,
            immediate: true,
        }
    }
};
</script>

<style>
/* Estilos para la paginación en modo oscuro */
.dark .el-pagination button,
.dark .el-pager li {
    background-color: #1f2937 !important;
    color: #d1d5db !important;
}
.dark .el-pager li.is-active {
    color: #ffffff !important;
    background-color: #3b82f6 !important;
}
</style>
