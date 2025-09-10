<template>
    <AppLayout :title="`Solicitud: ${designOrder.order_title}`">
        <!-- componente de carga de trabajo de diseñadores -->
        <DesignersWorkload v-if="$page.props.auth.user.permissions.includes('Crear ordenes de diseño')" />

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
            <div class="flex items-center space-x-2 dark:text-white mr-4">
                <!-- ACCIONES PARA EL DISEÑADOR -->
                <div v-if="isAssignedDesigner">
                    <PrimaryButton v-if="canStartWork" @click="startWork">
                        <i class="fa-solid fa-play mr-2"></i>
                        Iniciar Trabajo
                    </PrimaryButton>
                    <PrimaryButton v-if="canFinishWork" @click="promptFinishWork" class="!bg-green-600 hover!bg-green-700"> <i class="fa-solid fa-check-double mr-2"></i>
                        Terminar Diseño
                    </PrimaryButton>
                </div>

                <!-- Botones de acciones -->
                <el-tooltip v-if="!designOrder.designer_id && $page.props.auth.user.permissions.includes('Asignar diseños')" content="Asignar diseñador" placement="top">
                    <button @click="openAssignModal" class="size-9 flex items-center justify-center rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                        </svg>
                    </button>
                </el-tooltip>
                
                <el-tooltip v-if="designOrder.authorized_at === null && designOrder.designer_id && $page.props.auth.user.permissions.includes('Autorizar ordenes de diseño')" content="Autorizar Órden" placement="top">
                    <button @click="authorize" class="size-9 flex items-center justify-center rounded-lg bg-green-300 hover:bg-green-400 dark:bg-green-800 dark:hover:bg-green-700 transition-colors">
                        <i class="fa-solid fa-check-double"></i>
                    </button>
                </el-tooltip>

                <el-tooltip v-if="designOrder.status === 'Terminada' && $page.props.auth.user.permissions.includes('Crear formatos de autorizacion de diseño') && !designOrder.design_authorization" content="Crear formato de autorizacion de diseño" placement="top">
                    <button @click="$inertia.visit(route('design-authorizations.create', { design_order_id: designOrder.id }))" class="size-9 flex items-center justify-center rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 0 0 5.304 0l6.401-6.402M6.75 21A3.75 3.75 0 0 1 3 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 0 0 3.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008Z" />
                        </svg>
                    </button>
                </el-tooltip>

                <el-tooltip v-if="$page.props.auth.user.permissions.includes('Editar ordenes de diseño')" :content="(designOrder.status === 'En proceso' || designOrder.status === 'Terminada') ? 'No puedes editarla una vez iniciado el trabajo o ya esté terminada' : 'Editar Órden'" placement="top">
                    <button @click="$inertia.visit(route('design-orders.edit', designOrder.id))"
                        :disabled="designOrder.status === 'En proceso' || designOrder.status === 'Terminada'" 
                        class="size-9 flex items-center justify-center rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors disabled:cursor-not-allowed disabled:opacity-50">
                        <i class="fa-solid fa-pencil text-sm"></i>
                    </button>
                </el-tooltip>

                <el-tooltip content="Solicitar una modificación sobre este diseño" placement="top">
                    <Link :href="route('design-orders.create', { modifies_design: designOrder.design_id })"
                        v-if="designOrder.status === 'Terminada' && designOrder.design_id"
                        class="size-9 flex items-center justify-center rounded-lg bg-blue-200 hover:bg-blue-300 dark:bg-blue-800 dark:hover:bg-blue-700 transition-colors">
                        <i class="fa-solid fa-wand-magic-sparkles text-sm"></i>
                    </Link>
                </el-tooltip>

                <!-- Opciones para Admin/Vendedor -->
                <Dropdown align="right" width="48">
                    <template #trigger>
                        <button class="h-9 px-3 rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 flex items-center justify-center text-sm transition-colors">
                            Acciones <i class="fa-solid fa-chevron-down text-[10px] ml-2"></i>
                        </button>
                    </template>
                    <template #content>
                        <DropdownLink :href="route('design-orders.create')">
                            <i class="fa-solid fa-plus w-4 mr-2"></i> Nueva Orden
                        </DropdownLink>
                        <div class="border-t border-gray-200 dark:border-gray-600" />
                        <DropdownLink @click="showConfirmModal = true" as="button" class="text-red-500 hover:!bg-red-50 dark:hover:!bg-red-900/50">
                            <i class="fa-regular fa-trash-can w-4 mr-2"></i> Eliminar orden
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
            <div class="lg:col-span-1 space-y-4">
                <!-- === STEPPER DE ESTADO === -->
                <Stepper :currentStatus="designOrder.status" :steps="designOrderSteps" />
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
                        <li v-if="designOrder.design_authorization" class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Formato de autorización:</span>
                            <span @click="$inertia.visit(route('design-authorizations.show', designOrder.design_authorization.id))" class="text-blue-500 hover:underline cursor-pointer">
                                FA-{{ designOrder.design_authorization.id.toString().padStart(4, '0') }}
                            </span>
                        </li>
                        <li v-if="designOrder.branch" class="flex justify-between items-center">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Cliente:</span>

                            <!-- Tooltip Moderno -->
                            <el-tooltip placement="top-start" effect="light" raw-content>
                                <template #content>
                                    <div class="w-72 bg-white/90 dark:bg-slate-800/90 backdrop-blur-md rounded-xl shadow-xl p-4 text-sm">
                                    <!-- Header -->
                                    <div class="flex justify-between items-center border-b pb-2 mb-3">
                                        <h4 class="font-bold text-lg text-primary dark:text-sky-400">
                                        {{ designOrder.branch?.name }}
                                        </h4>
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-600 dark:bg-sky-900 dark:text-sky-300">
                                        {{ designOrder.branch?.status ?? 'N/A' }}
                                        </span>
                                    </div>

                                    <!-- Datos principales -->
                                    <div class="space-y-1 text-gray-700 dark:text-gray-300">
                                        <p><strong class="font-semibold">RFC:</strong> {{ designOrder.branch?.rfc ?? 'N/A' }}</p>
                                        <p><strong class="font-semibold">Dirección:</strong> {{ designOrder.branch?.address ?? 'N/A' }}</p>
                                        <p><strong class="font-semibold">C.P.:</strong> {{ designOrder.branch?.post_code ?? 'N/A' }}</p>
                                        <p><strong class="font-semibold">Medio de contacto:</strong> {{ designOrder.branch?.meet_way ?? 'N/A' }}</p>
                                        <p><strong class="font-semibold">Última compra:</strong> {{ formatRelative(designOrder.branch?.last_purchase_date) }}</p>
                                    </div>

                                    <!-- Footer -->
                                    <div class="mt-4 pt-2 border-t flex justify-between items-center">
                                        <Link :href="route('branches.show', designOrder.branch?.id)">
                                        <SecondaryButton class="!py-1.5 !px-3 !text-xs flex items-center gap-1">
                                            <i class="fa-solid fa-eye"></i> Ver Cliente
                                        </SecondaryButton>
                                        </Link>
                                        <span class="text-[10px] italic text-gray-400">Creado: {{ designOrder.branch?.created_at?.split('T')[0] }}</span>
                                    </div>
                                    </div>
                                </template>

                            <!-- Nombre clickable -->
                            <span class="text-blue-500 hover:underline cursor-pointer">
                                {{ designOrder.branch?.name ?? 'N/A' }}
                            </span>
                            </el-tooltip>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Solicitante:</span>
                            <span>{{ designOrder.requester?.name ?? 'N/A' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Diseñador:</span>
                            <span v-if="designOrder.designer_id">{{ designOrder.designer?.name }}</span>
                            <span class="text-amber-500" v-else>No asignado</span>
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
                            <span class="font-semibold text-amber-600 dark:text-amber-400">Iniciado:</span>
                            <span>{{ formatDateTime(designOrder.started_at) }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-amber-600 dark:text-amber-400">Finalizado:</span>
                            <span>{{ formatDateTime(designOrder.finished_at) }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- COLUMNA DERECHA: PESTAÑAS DE INFORMACIÓN -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg min-h-[70vh]">
                    <el-tabs v-model="activeTab" class="p-5">
                        <el-tab-pane label="Recursos" name="resources">
                            <p>Especificaciones:</p>
                            <p class="text-sm mt-2 whitespace-pre-wrap">{{ designOrder.specifications }}</p>
                            
                            <div class="col-span-2 mt-5">
                                <InputLabel value="Archivos Finales (obligatorio para terminar)" />
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Sube aquí el o los archivos resultantes de tu trabajo. Este paso es requerido para marcar la orden como "Terminada".</p>
                                <FileUploader @files-selected="finishForm.final_files = $event" :multiple="true" acceptedFormat="cualquier" />
                                <div v-if="finishForm.errors.final_files" class="text-red-500 text-xs mt-1">
                                    {{ finishForm.errors.final_files }}
                                </div>
                            </div>
                                                        
                            <div v-if="designOrder.media?.length" class="col-span-full mt-7">
                                <InputLabel value="Archivos de referencia adjuntos" />
                                <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mt-2">
                                    <FileView v-for="file in designOrder.media" :key="file.id" :file="file" :deletable="false"
                                        @delete-file="deleteFile($event)" />
                                </div>
                            </div>
                        </el-tab-pane>
                        <el-tab-pane label="Terminados" name="finished">
                            <!-- === NEW: Display all design versions === -->
                            <div class="h-[60vh] overflow-y-auto" v-if="designVersions.length > 0">
                                <div v-for="(version, index) in designVersions" :key="version.id" class="mb-6 pb-6 border-b dark:border-gray-700 last:border-b-0">
                                    <div class="flex justify-between items-center mb-3">
                                        <h3 class="font-semibold text-lg" :class="{'text-green-600 dark:text-green-400': designOrder.design_id === version.id}">
                                            <i class="fa-solid fa-paint-brush mr-2"></i>
                                            {{ version.name }}
                                            <span class="text-xs font-normal text-gray-500 ml-2">(Versión {{ index + 1 }})</span>
                                        </h3>
                                        <el-tag v-if="index === 0" size="small" type="info">Original</el-tag>
                                        <el-tag v-else size="small">Modificación</el-tag>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ version.description }}</p>

                                    <div v-if="version.media?.length > 0">
                                        <InputLabel value="Archivos Finales" />
                                        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mt-2">
                                            <FileView v-for="file in version.media" :key="file.id" :file="file" :deletable="false" />
                                        </div>
                                    </div>
                                    <p v-else class="text-xs text-gray-500 italic">No hay archivos adjuntos para esta versión.</p>
                                </div>
                            </div>
                            <p v-else class="text-gray-500 text-center italic mt-5">La orden aún no está terminada. No hay archivos finales.</p>
                             <!-- === END NEW === -->
                        </el-tab-pane>
                        <el-tab-pane label="Asignaciones" name="assigments">
                            <div v-if="designOrder.assignment_logs?.length" class="mt-5 space-y-4">
                                <div 
                                v-for="log in designOrder.assignment_logs" 
                                :key="log.id" 
                                class="p-4 rounded-2xl shadow-md border border-gray-200 bg-gray-100 hover:shadow-lg transition dark:bg-gray-800 dark:border-gray-700"
                                >
                                <div class="flex justify-between items-center">
                                    <h3 class="font-semibold text-gray-800 dark:text-gray-200">
                                    Asignación de diseñador
                                    </h3>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ new Date(log.changed_at).toLocaleString() }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300 italic">
                                    {{ log.reason }}
                                </p>
                                <div class="mt-3 text-sm">
                                    <p v-if="log.previous_designer" class="text-gray-700 dark:text-gray-300">
                                    <span class="font-semibold">Diseñador previo:</span> 
                                    {{ log.previous_designer?.name ?? 'N/A' }}
                                    </p>
                                    <p class="text-gray-700 dark:text-gray-300">
                                    <span class="font-semibold">Nuevo diseñador:</span> 
                                    {{ log.new_designer?.name }}
                                    </p>
                                    <p class="text-gray-700 dark:text-gray-300">
                                    <span class="font-semibold">Cambiado por usuario:</span> 
                                    {{ log.changed_by_user?.name }}
                                    </p>
                                </div>
                                </div>
                            </div>

                            <p v-else class="text-gray-500 text-center italic mt-5 dark:text-gray-400">
                                Historial de asignaciones vacío
                            </p>
                            </el-tab-pane>

                    </el-tabs>
                </div>
            </div>
        </main>

        <!-- Modal para Asignar Diseñador -->
        <DialogModal :show="showAssignModal" @close="closeAssignModal">
            <template #title>
                Asignar Diseñador a Orden
            </template>

            <template #content>
                <div>
                    <p class="text-amber-500">Revisa la carga de trabajo para equilibrar el trabajo de tus diseñadores</p>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                        {{ 'OD-' + designOrder.id.toString().padStart(4, '0') }}: {{ designOrder.order_title }}
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Solicitado por: <span class="font-medium">{{ designOrder.requester?.name }}</span>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Fecha de Solicitud: <span class="font-medium">{{ formatDate(designOrder.created_at) }}</span>
                    </p>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Seleccionar diseñador
                        </label>
                        <el-select v-model="assignmentForm.designer_id" :teleported="false"
                                placeholder="Selecciona un diseñador"
                                class="!w-1/2 mt-1"
                                filterable>
                            <el-option v-for="designer in designers"
                                    :key="designer.id"
                                    :label="designer.name"
                                    :value="designer.id" />
                        </el-select>
                        <div v-if="assignmentForm.errors.designer_id" class="text-red-500 text-xs mt-1">
                            {{ assignmentForm.errors.designer_id }}
                        </div>
                    </div>
                </div>
            </template>

            <template #footer>
                <CancelButton @click="closeAssignModal">
                    Cancelar
                </CancelButton>

                <SecondaryButton @click="submitAssignment" :loading="assignmentForm.processing" class="ml-3">
                    <span v-if="assignmentForm.processing">Asignando...</span>
                    <span v-else>Asignar</span>
                </SecondaryButton>
            </template>
        </DialogModal>

        <!-- Modal de Confirmación para Terminar Diseño -->
        <ConfirmationModal :show="showFinishModal" @close="showFinishModal = false">
            <template #title>
                Terminar Diseño
            </template>
            <template #content>
                ¿Estás seguro de que deseas marcar este diseño como terminado? Se creará un nuevo activo de diseño en el sistema con los archivos que adjuntaste.
            </template>
            <template #footer>
                <div class="flex items-center space-x-3">
                    <CancelButton @click="showFinishModal = false">Cancelar</CancelButton>
                    <PrimaryButton @click="finishWork" :loading="finishForm.processing" :disabled="finishForm.processing" class="!bg-green-600 hover:!bg-green-700">Confirmar y Terminar</PrimaryButton>
                </div>
            </template>
        </ConfirmationModal>

        <!-- Modal de Confirmación para Eliminar Orden -->
        <ConfirmationModal :show="showConfirmModal" @close="showConfirmModal = false">
            <template #title>
                Eliminar Orden de diseño
            </template>
            <template #content>
                ¿Estás seguro de que deseas eliminar permanentemente esta orden de diseño? Todos los datos relacionados se perderán. Esta acción no se puede deshacer.
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="showConfirmModal = false">Cancelar</CancelButton>
                    <PrimaryButton @click="deleteItem" class="!bg-red-600 hover:!bg-red-700">Eliminar</PrimaryButton>
                </div>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import DesignersWorkload from "@/Components/MyComponents/DesignersWorkload.vue";
import Stepper from "@/Components/MyComponents/Stepper.vue";
import InputLabel from "@/Components/InputLabel.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import FileView from "@/Components/MyComponents/FileView.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import DialogModal from '@/Components/DialogModal.vue'; // <-- Importar DialogModal
import { Link, useForm, router } from "@inertiajs/vue3";
import { ElMessage, ElMessageBox } from 'element-plus';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    data() {
        return {
            activeTab: 'resources',
            selectedOrder: this.designOrder.id,
            showFinishModal: false,
            showConfirmModal: false,
            designOrderSteps: ['Autorizada', 'En proceso', 'Terminada'],

            finishForm: useForm({
                final_files: [],
            }),

            // asignación de diseñador
            showAssignModal: false,
            designers: [],
            assignmentForm: this.$inertia.form({
                designer_id: null,
            }),
        };
    },
    components: {
        Link,
        Stepper,
        Dropdown,
        FileView,
        AppLayout,
        InputLabel,
        DialogModal,
        CancelButton,
        DropdownLink,
        FileUploader,
        PrimaryButton,
        SecondaryButton,
        DesignersWorkload,
        ConfirmationModal,
    },
    props: {
        designOrder: Object,
        designOrders: Array,
        auth: Object,
        designVersions: Array, //versiones de diseño (modificaciones)
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
        promptFinishWork() {
            if (this.finishForm.final_files.length === 0) {
                ElMessage.warning('Debes subir al menos un archivo final para poder terminar el diseño.');
                return;
            }
            this.showFinishModal = true;
        },
        finishWork() {
            this.finishForm.post(route('design-orders.finish-work', this.designOrder.id), {
                preserveScroll: true,
                onSuccess: () => {
                    this.showFinishModal = false;
                    this.finishForm.reset(); // Limpiar el formulario
                    ElMessage.success('¡Excelente! Diseño terminado y archivado.');
                },
                onError: (errors) => {
                    this.showFinishModal = false; // Ocultar modal si hay error
                    let message = 'No se pudo terminar el diseño.';
                    if (errors.final_files) {
                        message = errors.final_files; // Mostrar el error específico de los archivos
                    }
                    ElMessage.error(message);
                }
            });
        },
        openAssignModal() {
            this.fetchDesigners();
            this.showAssignModal = true;
        },
        closeAssignModal() {
            this.showAssignModal = false;
            this.assignmentForm.reset();
        },
        async deleteItem() {
            try {
                const response = await axios.delete(route('design-orders.destroy', this.designOrder.id));
                if (response.status === 200) {
                    ElMessage.success(response.data.message || 'Orden eliminada con éxito.');
                    this.$inertia.visit(route('design-orders.index'));
                }
            } catch (err) {
                ElMessage.error('Ocurrió un error al eliminar el cliente.');
                console.error(err);
            } finally {
                this.showConfirmModal = false;
            }
        },
        async fetchDesigners() {
            try {
                const response = await axios.get(route('design-orders.get-designers'));
                this.designers = response.data;
            } catch (error) {
                console.error("Error fetching designers:", error);
                ElMessage.error('No se pudo cargar la lista de diseñadores.');
            }
        },
        submitAssignment() {
            if (!this.assignmentForm.designer_id) {
                ElMessage.warning('Debes seleccionar un diseñador.');
                return;
            }

            this.assignmentForm.put(route('design-orders.assign-designer', this.designOrder.id), {
                preserveScroll: true,
                onSuccess: () => {
                    this.closeAssignModal();
                    ElMessage.success('Diseñador asignado correctamente.');
                    router.reload({ preserveScroll: true }); // Recargar datos de la tabla
                },
                onError: (errors) => {
                     let message = 'Ocurrió un error al asignar el diseñador.';
                     if (errors.designer_id) {
                         message = errors.designer_id[0];
                     }
                     ElMessage.error(message);
                }
            });
        },
        async authorize() {
            try {
                const response = await axios.get(route('design-orders.authorize', this.designOrder.id));
                if (response.status === 200) {
                    router.reload({ 
                        preserveScroll: true,
                    })                    
                    ElMessage.success(response.data.message);
                }
            } catch (err) {
                ElMessage.error('Ocurrió un error al autorizar la orden');
                console.error(err);
            }
        },
        handleUploadSuccess(response, file, fileList) {
            ElMessage.success('Archivos subidos correctamente.');
            this.$inertia.reload({ only: ['designOrder'] });
        },
        handleUploadError(error, file, fileList) {
            ElMessage.error('Error al subir el archivo. Inténtalo de nuevo.');
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
        },
        formatRelative(dateString) {
            if (!dateString) return "Sin registro";

            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date; // Diferencia en milisegundos

            if (diffMs < 0) {
                return "En el futuro"; // por si la fecha viene futura
            }

            const seconds = Math.floor(diffMs / 1000);
            const minutes = Math.floor(seconds / 60);
            const hours = Math.floor(minutes / 60);
            const days = Math.floor(hours / 24);
            const months = Math.floor(days / 30);
            const years = Math.floor(months / 12);

            if (seconds < 60) return `Hace ${seconds} segundos`;
            if (minutes < 60) return `Hace ${minutes} minutos`;
            if (hours < 24) return `Hace ${hours} horas`;
            if (days < 30) return `Hace ${days} días`;
            if (months < 12) return `Hace ${months} mes${months > 1 ? "es" : ""}`;
            return `Hace ${years} año${years > 1 ? "s" : ""}`;
        },
    }
};
</script>
