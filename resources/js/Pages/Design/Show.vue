<template>
    <AppLayout :title="`Solicitud: ${designOrder.order_title}`">

        <!-- === ENCABEZADO === -->
        <h1 class="dark:text-white font-bold text-2xl mb-4">{{ designOrder.order_title }}</h1>
        <header class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0 pb-4 border-b dark:border-gray-500">
            <div class="w-full lg:w-1/3">
                <el-select @change="$inertia.get(route('design-orders.show', selectedOrder))"
                    v-model="selectedOrder" filterable placeholder="Buscar otra solicitud..."
                    class="!w-full"
                    no-data-text="No hay solicitudes" no-match-text="No se encontraron coincidencias">
                    <el-option v-for="item in designOrders" :key="item.id"
                        :label="item.order_title" :value="item.id" />
                </el-select>
            </div>
            <div class="flex items-center space-x-2 dark:text-white">
                <!-- ACCIONES PARA EL DISEÑADOR -->
                <div v-if="isAssignedDesigner">
                    <PrimaryButton v-if="canStartWork" @click="startWork">
                        <i class="fa-solid fa-play mr-2"></i>
                        Iniciar Trabajo
                    </PrimaryButton>
                    <PrimaryButton v-if="canFinishWork" @click="showFinishModal = true" class="!bg-green-600 hover:!bg-green-700">
                        <i class="fa-solid fa-check-double mr-2"></i>
                        Terminar Diseño
                    </PrimaryButton>
                </div>

                <!-- Botones de acciones -->
                <!-- <el-tooltip v-if="sale.authorized_at === null" content="Autorizar Órden" placement="top">
                    <button @click="authorize" class="size-9 flex items-center justify-center rounded-lg bg-green-300 hover:bg-green-400 dark:bg-green-800 dark:hover:bg-green-700 transition-colors">
                        <i class="fa-solid fa-check-double"></i>
                    </button>
                </el-tooltip> -->

                <!-- Opciones para Admin/Vendedor -->
                <Dropdown v-else align="right" width="48">
                    <template #trigger>
                        <button class="h-9 px-3 rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 flex items-center justify-center text-sm transition-colors">
                            Acciones <i class="fa-solid fa-chevron-down text-[10px] ml-2"></i>
                        </button>
                    </template>
                    <template #content>
                        <DropdownLink :href="route('design-orders.create')">
                            <i class="fa-solid fa-plus w-4 mr-2"></i> Nueva Solicitud
                        </DropdownLink>
                        <div class="border-t border-gray-200 dark:border-gray-600" />
                        <DropdownLink @click="showConfirmModal = true" as="button" class="text-red-500 hover:!bg-red-50 dark:hover:!bg-red-900/50">
                            <i class="fa-regular fa-trash-can w-4 mr-2"></i> Cancelar Solicitud
                        </DropdownLink>
                    </template>
                </Dropdown>

                <Link :href="route('design-orders.index')"
                    class="flex-shrink-0 size-9 focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 flex items-center justify-center rounded-full bg-white dark:bg-slate-800/80 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-primary transition-all duration-200">
                    <i class="fa-solid fa-xmark"></i>
                </Link>
            </div>
        </header>

        <!-- === CONTENIDO PRINCIPAL === -->
        <main class="grid grid-cols-1 lg:grid-cols-3 gap-7 mt-5 dark:text-white">
            <!-- COLUMNA IZQUIERDA -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Card de Información Clave -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Información Clave</h3>
                    <ul class="space-y-3 text-sm">
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Estatus:</span>
                            <el-tag :type="getStatusTagType(designOrder.status)" size="small">{{ designOrder.status }}</el-tag>
                        </li>
                         <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Prioridad:</span>
                            <el-tag :type="designOrder.is_hight_priority ? 'danger' : 'primary'" size="small">
                                {{ designOrder.is_hight_priority ? 'Alta' : 'Normal' }}
                            </el-tag>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Solicitante:</span>
                            <span>{{ designOrder.requester?.name ?? 'N/A' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Diseñador:</span>
                            <span>{{ designOrder.designer?.name ?? 'No asignado' }}</span>
                        </li>
                         <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Categoría:</span>
                            <span>{{ designOrder.design_category?.name ?? 'N/A' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Sucursal:</span>
                            <span>{{ designOrder.branch_name ?? 'No especificada' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Fecha Límite:</span>
                            <span>{{ formatDate(designOrder.due_date) }}</span>
                        </li>
                    </ul>
                </div>

                 <!-- Card de Fechas importantes -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Fechas</h3>
                     <ul class="space-y-3 text-sm">
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Solicitado:</span>
                            <span>{{ formatDateTime(designOrder.created_at) }}</span>
                        </li>
                         <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Asignado:</span>
                            <span>{{ formatDateTime(designOrder.assigned_at) }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Iniciado:</span>
                            <span>{{ formatDateTime(designOrder.started_at) }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Finalizado:</span>
                            <span>{{ formatDateTime(designOrder.finished_at) }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- COLUMNA DERECHA: PESTAÑAS DE INFORMACIÓN -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg min-h-[70vh]">
                    <el-tabs v-model="activeTab" class="p-5">
                        <el-tab-pane label="Especificaciones" name="specifications">
                            <p class="text-sm mt-2 whitespace-pre-wrap">{{ designOrder.specifications }}</p>
                        </el-tab-pane>
                        <el-tab-pane label="Archivos" name="files">
                            <!-- Uploader de archivos -->
                            <div class="col-span-2 mt-5">
                                <InputLabel value="Archivos (max: 3)" />
                                <FileUploader @files-selected="form.media = $event" :multiple="true" acceptedFormat="imagen" :max-files="3" />
                            </div>
                            
                            <!-- upload de muestra -->
                            <!-- <el-upload
                                drag
                                multiple
                                :action="route('design-orders.upload', designOrder.id)"
                                :on-success="handleUploadSuccess"
                                :on-error="handleUploadError"
                                :headers="{ 'X-CSRF-TOKEN': $page.props.csrf_token }"
                                class="!w-full mb-5"
                                >
                                <i class="fa-solid fa-cloud-arrow-up text-5xl text-gray-400"></i>
                                <div class="el-upload__text">
                                    Arrastra archivos aquí o <em>haz clic para subir</em>
                                </div>
                                <template #tip>
                                    <div class="el-upload__tip">
                                    Archivos de hasta 20MB.
                                    </div>
                                </template>
                            </el-upload> -->
                            
                            <!-- Archivos adjuntos existentes -->
                            <div v-if="designOrder.media?.length" class="col-span-full mt-10">
                                <InputLabel value="Archivos adjuntos" />
                                <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mt-2">
                                    <FileView v-for="file in designOrder.media" :key="file.id" :file="file" :deletable="true"
                                        @delete-file="deleteFile($event)" />
                                </div>
                            </div>

                            <!-- Estado vacío -->
                            <div v-else class="text-center py-10">
                                <i class="fa-regular fa-folder-open text-5xl text-gray-400"></i>
                                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No hay archivos adjuntos.</p>
                            </div>

                        </el-tab-pane>
                    </el-tabs>
                </div>
            </div>
        </main>

        <!-- Modal de Confirmación para Terminar Diseño -->
        <ConfirmationModal :show="showFinishModal" @close="showFinishModal = false">
            <template #title>
                Terminar Diseño
            </template>
            <template #content>
                ¿Estás seguro de que deseas marcar este diseño como terminado? Se creará un nuevo activo de diseño en el sistema y se cerrará la orden de trabajo. Los archivos adjuntos se moverán al nuevo diseño.
            </template>
            <template #footer>
                <div class="flex items-center space-x-3">
                    <CancelButton @click="showFinishModal = false">Cancelar</CancelButton>
                    <PrimaryButton @click="finishWork" class="!bg-green-600 hover:!bg-green-700">Confirmar y Terminar</PrimaryButton>
                </div>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import InputLabel from "@/Components/InputLabel.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import FileView from "@/Components/MyComponents/FileView.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import { Link, useForm } from "@inertiajs/vue3";
import { ElMessage, ElMessageBox } from 'element-plus';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    data() {
        return {
            activeTab: 'specifications',
            selectedOrder: this.designOrder.id,
            showFinishModal: false,
        };
    },
    components: {
        Link,
        Dropdown,
        FileView,
        AppLayout,
        InputLabel,
        CancelButton,
        DropdownLink,
        FileUploader,
        PrimaryButton,
        ConfirmationModal,
    },
    props: {
        designOrder: Object,
        designOrders: Array,
        auth: Object,
    },
    computed: {
        isAssignedDesigner() {
            return this.auth.user.id === this.designOrder.designer_id;
        },
        canStartWork() {
            return this.isAssignedDesigner && !this.designOrder.started_at && this.designOrder.status === 'Autorizada';
        },
        canFinishWork() {
            return this.isAssignedDesigner && !!this.designOrder.started_at && !this.designOrder.finished_at;
        }
    },
    methods: {
        startWork() {
            this.$inertia.put(route('design-orders.start-work', this.designOrder.id), null, {
                preserveScroll: true,
                onSuccess: () => ElMessage.success('¡A trabajar! El trabajo ha sido iniciado.'),
                onError: () => ElMessage.error('No se pudo iniciar el trabajo.')
            });
        },
        finishWork() {
            this.$inertia.put(route('design-orders.finish-work', this.designOrder.id), null, {
                 preserveScroll: true,
                onSuccess: () => {
                    this.showFinishModal = false;
                    ElMessage.success('¡Excelente! Diseño terminado y archivado.');
                },
                onError: () => ElMessage.error('No se pudo terminar el diseño.')
            });
        },
        handleUploadSuccess(response, file, fileList) {
            ElMessage.success('Archivos subidos correctamente.');
            this.$inertia.reload({ only: ['designOrder'] });
        },
        handleUploadError(error, file, fileList) {
            ElMessage.error('Error al subir el archivo. Inténtalo de nuevo.');
        },
        confirmDeleteFile(file) {
            ElMessageBox.confirm(
                `¿Estás seguro de que quieres eliminar el archivo "${file.file_name}"? Esta acción no se puede deshacer.`,
                'Confirmar Eliminación',
                {
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning',
                }
            )
            .then(() => {
                this.deleteFile(file.id);
            })
            .catch(() => {
                // Cancelado
            });
        },
        // deleteFile(fileId) {
        //     this.$inertia.delete(route('design-orders.media.delete', { designOrder: this.designOrder.id, media: fileId }), {
        //         preserveScroll: true,
        //         onSuccess: () => ElMessage.success('Archivo eliminado.'),
        //         onError: () => ElMessage.error('No se pudo eliminar el archivo.')
        //     });
        // },
        deleteFile(fileId) {
            this.designOrder.files = this.designOrder.files.filter(m => m.id !== fileId);
        },
        getFileIcon(mimeType) {
            if (mimeType.startsWith('image/')) return 'fa-regular fa-file-image';
            if (mimeType.includes('pdf')) return 'fa-regular fa-file-pdf';
            if (mimeType.includes('zip') || mimeType.includes('rar')) return 'fa-regular fa-file-zipper';
            if (mimeType.includes('word')) return 'fa-regular fa-file-word';
            if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'fa-regular fa-file-excel';
            return 'fa-regular fa-file';
        },
        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        getStatusTagType(status) {
            const statusMap = {
                'Pendiente': 'warning',
                'Autorizada': 'primary',
                'En proceso': 'info',
                'Terminada': 'success',
                'Cancelada': 'danger',
            };
            return statusMap[status] || 'default';
        },
        formatDate(dateString) {
            if (!dateString) return 'No definida';
            const date = new Date(dateString);
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        },
        formatDateTime(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return format(date, "d MMM, yyyy HH:mm 'hrs.'", { locale: es });
        }
    }
};
</script>
