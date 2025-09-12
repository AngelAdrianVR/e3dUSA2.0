<template>
    <AppLayout title="Detalles del Seguimiento de Muestra">
        <!-- === ENCABEZADO === -->
        <div class="px-4 sm:px-0">
            <div class="flex items-center space-x-2">
                <h1 class="dark:text-white font-bold text-2xl">
                    Seguimiento de Muestra MUE-{{ sampleTracking.id.toString().padStart(4, '0') }}
                </h1>
            </div>
        </div>

        <header class="flex space-x-2 space-y-3 justify-end sm:space-y-0 py-3 mt-1 dark:text-white">
            <!-- Botones de Acción -->
            <div>
                <el-button-group>
                    <el-button v-if="sampleTracking.status === 'Autorizado'" @click="updateStatus('Enviado')" type="primary">Marcar como Enviado</el-button>
                    <el-button v-if="sampleTracking.status === 'Enviado' && sampleTracking.will_be_returned" @click="updateStatus('Devuelto')" type="info">Marcar como Devuelto</el-button>
                    <el-button v-if="sampleTracking.status === 'Enviado' || sampleTracking.status === 'Devuelto'" @click="updateStatus('Aprobado')" type="success">Aprobado</el-button>
                    <el-button v-if="(sampleTracking.status === 'Enviado' || sampleTracking.status === 'Aprobado') || sampleTracking.status === 'Devuelto'" @click="updateStatus('Completado')" type="success">Completar</el-button>
                    <el-button v-if="sampleTracking.status === 'Enviado' || sampleTracking.status === 'Devuelto'" @click="updateStatus('Rechazado')" type="danger">Rechazado</el-button>
                </el-button-group>
            </div>

            <el-tooltip v-if="sampleTracking.authorized_at === null" content="Autorizar Seguimiento" placement="top">
                <button @click="authorize" class="size-9 flex items-center justify-center rounded-lg bg-green-300 hover:bg-green-400 dark:bg-green-800 dark:hover:bg-green-700 transition-colors">
                    <i class="fa-solid fa-check-double"></i>
                </button>
            </el-tooltip>

            <el-tooltip :content="sampleTracking.authorized_at ? 'No puedes editarla una vez autorizada' : 'Editar Seguimiento'" placement="top">
                <Link :href="sampleTracking.authorized_at ? '' : route('sample-trackings.edit', sampleTracking.id)">
                    <button :disabled="sampleTracking.authorized_at" 
                        class="size-9 flex items-center justify-center rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors disabled:cursor-not-allowed disabled:opacity-50">
                        <i class="fa-solid fa-pencil text-sm"></i>
                    </button>
                </Link>
            </el-tooltip>

            <Dropdown align="right" width="48">
                <template #trigger>
                    <button class="h-9 px-3 rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 flex items-center justify-center text-sm transition-colors">
                        Más Acciones <i class="fa-solid fa-chevron-down text-[10px] ml-2"></i>
                    </button>
                </template>
                <template #content>
                    <DropdownLink v-if="$page.props.auth.user.permissions.includes('Crear muestras')" @click="$inertia.visit(route('sample-trackings.create'))" as="button">
                        <i class="fa-solid fa-plus w-4 mr-2"></i> Crear nuevo seguimiento
                    </DropdownLink>
                    <div class="border-t border-gray-200 dark:border-gray-600" />
                    <DropdownLink v-if="$page.props.auth.user.permissions.includes('Eliminar muestras')" @click="showConfirmModal = true" as="button" class="text-red-500 hover:!bg-red-50 dark:hover:!bg-red-900/50">
                        <i class="fa-regular fa-trash-can w-4 mr-2"></i> Eliminar Seguimiento
                    </DropdownLink>
                </template>
            </Dropdown>

            <Link :href="route('sample-trackings.index')"
                class="flex-shrink-0 size-9 focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 flex items-center justify-center rounded-full bg-white dark:bg-slate-800/80 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-primary transition-all duration-200">
                <i class="fa-solid fa-xmark"></i>
            </Link>
        </header>
        
        <div v-if="sampleTracking.status === 'Rechazado'" 
            class="flex items-center gap-3 p-4 rounded-xl border border-red-300 bg-red-50 text-red-700 shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" 
                fill="none" 
                viewBox="0 0 24 24" 
                stroke-width="1.5" 
                stroke="currentColor" 
                class="w-6 h-6 text-red-600">
                <path stroke-linecap="round" 
                    stroke-linejoin="round" 
                    d="M12 9v3.75m0 3.75h.007M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="font-semibold text-sm md:text-base">
                La muestra ha sido <span>rechazada</span>.
            </p>
        </div>

        <!-- === STEPPER DE ESTADO === -->
        <Stepper v-else :currentStatus="sampleTracking.status" :steps="sampleTrackingSteps" :treatCurrentAsCompleted="true" />


        <!-- === CONTENIDO PRINCIPAL === -->
        <main class="grid grid-cols-1 lg:grid-cols-3 gap-7 mt-5 dark:text-white">
            <!-- COLUMNA IZQUIERDA -->
            <div class="lg:col-span-1 space-y-5">
                <!-- Card de Información del Seguimiento -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Detalles del Seguimiento</h3>
                    <ul class="space-y-3 text-sm">
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Estatus:</span>
                            <el-tag :type="statusTagType" size="small">{{ sampleTracking.status }}</el-tag>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Solicitante:</span>
                            <span>{{ sampleTracking.requester?.name ?? 'N/A' }}</span>
                        </li>
                         <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">¿Será devuelta?:</span>
                            <span :class="sampleTracking.will_be_returned ? 'text-green-500' : 'text-red-500'">
                                {{ sampleTracking.will_be_returned ? 'Sí' : 'No' }}
                            </span>
                        </li>
                        <li v-if="sampleTracking.will_be_returned" class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Fecha esperada de devolución:</span>
                            <span>{{ formatDate(sampleTracking.expected_devolution_date) }}</span>
                        </li>
                        
                        <!-- AGREGADO: Visualización de las fechas de estatus -->
                        <li v-if="sampleTracking.approved_at" class="flex justify-between text-xs">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Aprobado el:</span>
                            <span class="text-gray-500 dark:text-gray-300">{{ formatDate(sampleTracking.approved_at) }}</span>
                        </li>
                        <li v-if="sampleTracking.sent_at" class="flex justify-between text-xs">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Enviado el:</span>
                            <span class="text-gray-500 dark:text-gray-300">{{ formatDate(sampleTracking.sent_at) }}</span>
                        </li>
                        <li v-if="sampleTracking.returned_at" class="flex justify-between text-xs">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Devuelto el:</span>
                            <span class="text-gray-500 dark:text-gray-300">{{ formatDate(sampleTracking.returned_at) }}</span>
                        </li>
                        <li v-if="sampleTracking.completed_at" class="flex justify-between text-xs">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Completado el:</span>
                            <span class="text-gray-500 dark:text-gray-300">{{ formatDate(sampleTracking.completed_at) }}</span>
                        </li>
                        <li v-if="sampleTracking.denied_at" class="flex justify-between text-xs">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Rechazado el:</span>
                            <span class="text-gray-500 dark:text-gray-300">{{ formatDate(sampleTracking.denied_at) }}</span>
                        </li>

                        <li class="col-span-full pt-3 border-t dark:border-gray-600">
                            <p class="font-semibold text-gray-600 dark:text-gray-400">Comentarios / Notas:</p>
                            <p class="mt-1 text-gray-800 dark:text-gray-300 whitespace-pre-wrap">{{ sampleTracking.comments ?? 'No hay comentarios.' }}</p>
                        </li>
                    </ul>
                </div>

                <!-- Card de Cliente y Contacto -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Cliente y Contacto</h3>
                    <ul class="space-y-3 text-sm">
                         <li class="flex justify-between items-center">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Cliente:</span>

                            <!-- Tooltip de cliente -->
                            <el-tooltip placement="top-start" effect="light" raw-content>
                                <template #content>
                                    <div class="w-72 bg-white/90 dark:bg-slate-800/90 backdrop-blur-md rounded-xl shadow-xl p-4 text-sm">
                                    <!-- Header -->
                                    <div class="flex justify-between items-center border-b pb-2 mb-3">
                                        <h4 class="font-bold text-lg text-primary dark:text-sky-400">
                                        {{ sampleTracking.branch?.name }}
                                        </h4>
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-600 dark:bg-sky-900 dark:text-sky-300">
                                        {{ sampleTracking.branch?.status ?? 'N/A' }}
                                        </span>
                                    </div>

                                    <!-- Datos principales -->
                                    <div class="space-y-1 text-gray-700 dark:text-gray-300">
                                        <p><strong class="font-semibold">RFC:</strong> {{ sampleTracking.branch?.rfc ?? 'N/A' }}</p>
                                        <p><strong class="font-semibold">Dirección:</strong> {{ sampleTracking.branch?.address ?? 'N/A' }}</p>
                                        <p><strong class="font-semibold">C.P.:</strong> {{ sampleTracking.branch?.post_code ?? 'N/A' }}</p>
                                        <p><strong class="font-semibold">Medio de contacto:</strong> {{ sampleTracking.branch?.meet_way ?? 'N/A' }}</p>
                                        <p><strong class="font-semibold">Última compra:</strong> {{ formatRelative(sampleTracking.branch?.last_purchase_date) }}</p>
                                    </div>

                                    <!-- Footer -->
                                    <div class="mt-4 pt-2 border-t flex justify-between items-center">
                                        <Link :href="route('branches.show', sampleTracking.branch?.id)">
                                        <SecondaryButton class="!py-1.5 !px-3 !text-xs flex items-center gap-1">
                                            <i class="fa-solid fa-eye"></i> Ver Cliente
                                        </SecondaryButton>
                                        </Link>
                                        <span class="text-[10px] italic text-gray-400">Creado: {{ sampleTracking.branch?.created_at?.split('T')[0] }}</span>
                                    </div>
                                    </div>
                                </template>

                                <!-- Nombre clickable -->
                                <span class="text-blue-500 hover:underline cursor-pointer">
                                    {{ sampleTracking.branch?.name ?? 'N/A' }}
                                </span>
                            </el-tooltip>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Contacto:</span>
                            <span>{{ sampleTracking.contact?.name ?? 'N/A' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Teléfono:</span>
                            <span>
                                {{ sampleTracking.contact.details.find(d => d.type === 'Teléfono')?.value ?? 'No especificado' }}
                            </span>
                        </li>

                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Correo:</span>
                            <span>
                                {{ sampleTracking.contact.details.find(d => d.type === 'Correo')?.value ?? 'No especificado' }}
                            </span>
                        </li>

                    </ul>
                </div>
            </div>

            <!-- COLUMNA DERECHA: PRODUCTOS -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Productos en la Muestra</h3>
                    <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-2">
                        <!-- Tarjeta de Producto -->
                        <div v-for="item in sampleTracking.items" :key="item.id" class="flex items-start space-x-4 bg-gray-100 dark:bg-slate-900 p-4 rounded-lg">
                            <img @click="item.itemable_type.includes('NewProductProposal') ? '' : $inertia.visit(route('catalog-products.show', item.itemable.id))" 
                            :src="item.image_url" @error="$event.target.src='https://placehold.co/100x100/e2e8f0/e2e8f0/png?text=O'" alt="Imagen del producto" 
                            class="size-24 rounded-md object-cover flex-shrink-0 cursor-pointer">
                            <div class="flex-grow">
                                <p class="font-bold text-lg text-gray-800 dark:text-gray-100">{{ item.itemable.name }}</p>
                                <div class="flex items-center space-x-2 text-sm mt-1">
                                    <span class="font-semibold">Cantidad: {{ item.quantity }}</span>
                                    <el-tag size="small" :type="item.itemable_type.includes('NewProductProposal') ? 'warning' : 'info'">
                                        {{ item.itemable_type.includes('NewProductProposal') ? 'Nuevo Producto' : 'De Catálogo' }}
                                    </el-tag>
                                </div>
                                <p v-if="item.itemable.description" class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                                    <span class="font-semibold">Descripción:</span> {{ item.itemable.description }}
                                </p>
                                <p v-if="item.notes" class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                                    <span class="font-semibold">Notas:</span> {{ item.notes }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Modal de Confirmación para Eliminar (Sin cambios) -->
        <ConfirmationModal :show="showConfirmModal" @close="showConfirmModal = false">
            <template #title>
                Eliminar Seguimiento de muestra MUE-{{ sampleTracking.id.toString().padStart(4, '0') }}
            </template>
            <template #content>
                ¿Estás seguro de que deseas eliminar permanentemente este seguimiento? Todos los datos relacionados se perderán. Esta acción no se puede deshacer.
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="showConfirmModal = false">Cancelar</CancelButton>
                    <SecondaryButton @click="deleteItem" class="!bg-red-600 hover:!bg-red-700">Eliminar</SecondaryButton>
                </div>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import Stepper from "@/Components/MyComponents/Stepper.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import { Link, useForm } from "@inertiajs/vue3";
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElMessage, ElMessageBox } from 'element-plus';

export default {
    data() {
        // --- MODIFICADO: Se agrega 'comments' al formulario ---
        const statusForm = useForm({
            status: null,
            comments: '',
        });

        return {
            statusForm,
            showConfirmModal: false,
            sampleTrackingSteps: ['Autorizado', 'Enviado', 'Devuelto', 'Aprobado', 'Completado'],
        };
    },
    components: {
        Link,
        Stepper,
        Dropdown,
        AppLayout,
        DropdownLink,
        CancelButton,
        SecondaryButton,
        ConfirmationModal,
    },
    props: {
        sampleTracking: Object,
    },
    computed: {
        activeStep() {
            const statusMap = {
                'Pendiente': 0,
                'Aprobado': 1,
                'Enviado': 2,
                'Devuelto': 3,
                'Completado': this.sampleTracking.will_be_returned ? 4 : 3
            };
            return statusMap[this.sampleTracking.status] ?? 0;
        },
        statusTagType() {
             const statusStyles = {
                'Pendiente': 'warning',
                'Autorizado': 'primary',
                'Aprobado': 'primary',
                'Enviado': 'info',
                'Devuelto': 'info',
                'Completado': 'success',
                'Rechazado': 'danger',
                'Modificación': 'warning',
            };
            return statusStyles[this.sampleTracking.status] ?? 'default';
        }
    },
    methods: {
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return format(date, "d MMM, yyyy", { locale: es });
        },
        async deleteItem() {
            try {
                const response = await axios.post(route('sample-trackings.destroy', this.sale.id), {
                _method: 'DELETE'
                });

                if (response.status === 200) {
                    ElMessage.success(response.data.message || 'Seguimiento eliminado con éxito.');
                    this.$inertia.visit(route('sample-trackings.index'));
                }
            } catch (err) {
                ElMessage.error('Ocurrió un error al eliminar el seguimiento.');
                console.error(err);
            } finally {
                this.showConfirmModal = false;
            }
        },
        // --- MÉTODO MODIFICADO PARA INCLUIR NOTAS ---
        updateStatus(newStatus) {
            ElMessageBox.prompt(
                'Opcional: Agrega una nota para este cambio de estatus. Esta nota sobreescribirá los comentarios actuales.',
                `Cambiar estatus a "${newStatus}"`,
                {
                    confirmButtonText: 'Sí, cambiar',
                    cancelButtonText: 'Cancelar',
                    inputType: 'textarea',
                    inputPlaceholder: 'Escribe tus notas aquí...',
                    // Pre-llenar con los comentarios existentes si los hay
                    inputValue: this.sampleTracking.comments, 
                }
            ).then(({ value }) => { // 'value' contiene el texto del textarea
                this.statusForm.status = newStatus;
                this.statusForm.comments = value; // Asignar las notas al formulario

                this.statusForm.put(route('sample-trackings.update-status', { sampleTracking: this.sampleTracking.id }), {
                    preserveScroll: true, // Para evitar que la página salte al inicio
                    onSuccess: () => {
                        // El controlador se encarga del mensaje flash
                    },
                    onError: (errors) => {
                        console.error(errors);
                        ElMessage.error('No se pudo actualizar el estatus. Revisa la consola para más detalles.');
                    }
                });
            }).catch(() => {
                ElMessage.info('Acción cancelada.');
            });
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
        async authorize() {
            try {
                const response = await axios.put(route('sample-trackings.authorize', this.sampleTracking.id));
                if (response.status === 200) {
                    this.sampleTracking.authorized_at = response.data.item.authorized_at;
                    this.sampleTracking.status = response.data.item.status;
                    ElMessage.success(response.data.message);
                }
            } catch (err) {
                ElMessage.error('Ocurrió un error al autorizar el seguimiento de muestra');
                console.error(err);
            }
        },
    }
};
</script>

