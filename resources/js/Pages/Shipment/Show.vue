<template>
    <AppLayout :title="`Detalles de Envío para la Órden OV-${sale.id.toString().padStart(4, '0')}`">
        <!-- === ENCABEZADO === -->
        <header class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0 pb-4 mb-1">
            <div>
                <div class="flex space-x-2 items-center">
                    <h1 class="dark:text-white font-bold text-2xl my-2">
                        <span class="text-gray-500 dark:text-gray-400">Envíos de la Órden:</span> OV-{{ sale.id.toString().padStart(4, '0') }}
                    </h1>
                </div>
            </div>
            
            <div class="flex items-center space-x-2 dark:text-white">
                <el-tooltip content="Imprimir Órden" placement="top">
                    <button @click="printOrder" class="size-9 flex items-center justify-center rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors">
                        <i class="fa-solid fa-print"></i>
                    </button>
                </el-tooltip>
                
                <Link :href="route('shipments.index')"
                    class="flex-shrink-0 size-9 focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 flex items-center justify-center rounded-full bg-white dark:bg-slate-800/80 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-red-600 transition-all duration-200">
                    <i class="fa-solid fa-xmark"></i>
                </Link>
            </div>
        </header>


        <!-- === CONTENIDO PRINCIPAL === -->
        <main class="grid grid-cols-1 lg:grid-cols-3 gap-5 mt-3 dark:text-white">
            <!-- COLUMNA IZQUIERDA -->
            <div class="lg:col-span-1 space-y-4">
                <!-- Card de Información de la Órden -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Detalles de la Órden</h3>
                    <ul class="space-y-3 text-sm">
                        <!-- Campos para Venta -->
                        <template v-if="sale.type === 'venta'">
                            <li class="flex justify-between items-center">
                                <span class="font-semibold text-gray-600 dark:text-gray-400">Cliente:</span>

                                <!-- Tooltip Moderno -->
                                <el-tooltip placement="right" effect="light" raw-content>
                                    <template #content>
                                        <div class="w-72 bg-white/90 dark:bg-slate-800/90 backdrop-blur-md rounded-xl shadow-xl p-4 text-sm">
                                        <!-- Header -->
                                        <div class="flex justify-between items-center border-b pb-2 mb-3">
                                            <h4 class="font-bold text-lg text-primary dark:text-sky-400">
                                            {{ sale.branch?.name }}
                                            </h4>
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-600 dark:bg-sky-900 dark:text-sky-300">
                                            {{ sale.branch?.status ?? 'N/A' }}
                                            </span>
                                        </div>

                                        <!-- Datos principales -->
                                        <div class="space-y-1 text-gray-700 dark:text-gray-300">
                                            <p><strong class="font-semibold">RFC:</strong> {{ sale.branch?.rfc ?? 'N/A' }}</p>
                                            <p><strong class="font-semibold">Dirección:</strong> {{ sale.branch?.address ?? 'N/A' }}</p>
                                            <p><strong class="font-semibold">C.P.:</strong> {{ sale.branch?.post_code ?? 'N/A' }}</p>
                                            <p><strong class="font-semibold">Medio de contacto:</strong> {{ sale.branch?.meet_way ?? 'N/A' }}</p>
                                            <p><strong class="font-semibold">Última compra:</strong> {{ formatRelative(sale.branch?.last_purchase_date) }}</p>
                                        </div>

                                        <!-- Footer -->
                                        <div class="mt-4 pt-2 border-t flex justify-between items-center">
                                            <Link :href="route('branches.show', sale.branch?.id)">
                                            <SecondaryButton class="!py-1.5 !px-3 !text-xs flex items-center gap-1">
                                                <i class="fa-solid fa-eye"></i> Ver Cliente
                                            </SecondaryButton>
                                            </Link>
                                            <span class="text-[10px] italic text-gray-400">Creado: {{ sale.branch?.created_at?.split('T')[0] }}</span>
                                        </div>
                                        </div>
                                    </template>

                                <!-- Nombre clickable -->
                                <span class="text-blue-500 hover:underline cursor-default">
                                    {{ sale.branch?.name ?? 'N/A' }}
                                </span>
                                </el-tooltip>
                            </li>

                            <!-- Contacto -->
                            <li class="flex justify-between">
                                <span class="font-semibold text-gray-600 dark:text-gray-400">Contacto:</span>
                                
                                <el-tooltip
                                    v-if="sale.contact"
                                    placement="right"
                                    effect="dark"
                                >
                                    <template #content>
                                    <div class="space-y-2 text-sm">
                                        <p v-if="getPrimaryDetail(sale.contact, 'Correo')" class="flex items-center gap-2">
                                        <i class="fa-solid fa-envelope text-blue-400"></i>
                                        <span>{{ getPrimaryDetail(sale.contact, 'Correo') }}</span>
                                        </p>
                                        <p v-if="getPrimaryDetail(sale.contact, 'Teléfono')" class="flex items-center gap-2">
                                        <i class="fa-solid fa-phone text-green-400"></i>
                                        <span>{{ getPrimaryDetail(sale.contact, 'Teléfono') }}</span>
                                        </p>
                                    </div>
                                    </template>

                                    <span
                                    class="text-blue-500 font-medium hover:underline cursor-default transition-colors duration-200"
                                    >
                                    {{ sale.contact?.name ?? 'N/A' }}
                                    </span>
                                </el-tooltip>

                                <span v-else class="text-gray-400 italic">N/A</span>
                            </li>


                            <!-- Nombre clickable -->
                            <li class="flex justify-between">
                                <span class="font-semibold text-gray-600 dark:text-gray-400">OV:</span>
                                <span @click="$inertia.visit(route('sales.show', sale.id))" class="text-blue-500 hover:underline cursor-pointer">
                                    OV-{{ sale.id.toString().padStart(4, '0') ?? 'N/A' }}
                                </span>
                            </li>
                        </template>


                        <!-- Campos Comunes -->
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Tipo:</span>
                            <span>{{ sale.type === 'venta' ? 'Venta' : 'Stock' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">OCE:</span>
                            <span>{{ sale.oce_name ?? 'No especificado' }}</span>
                        </li>
                         <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Creado por:</span>
                            <span>{{ sale.user?.name ?? 'N/A' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Fecha de Creación:</span>
                        </li>
                         <li class="flex justify-between items-center">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Prioridad:</span>
                            <el-tag v-if="sale.is_high_priority" type="danger" size="small">Alta</el-tag>
                            <el-tag v-else type="info" size="small">Normal</el-tag>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- COLUMNA DERECHA: ENVÍOS -->
            <div class="lg:col-span-2 space-y-5">
                <div v-if="!sale.shipments?.length" class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-4 min-h-[300px] flex items-center justify-center">
                     <div class="text-center text-gray-500 dark:text-gray-400 py-10">
                        <i class="fa-solid fa-box-open text-3xl mb-3"></i>
                        <p>Esta órden no tiene envíos registrados.</p>
                    </div>
                </div>
                <!-- Iteración de cada envío (parcialidad) -->
                <div v-else v-for="(shipment, index) in sale.shipments" :key="shipment.id" class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-4">
                    <!-- Encabezado de la Parcialidad -->
                    <div class="flex justify-between items-center border-b dark:border-gray-600 pb-3 mb-4">
                        <h3 class="text-lg font-semibold">
                            Envío Parcial #{{ sale.shipments.length - index }}
                        </h3>
                        <div class="flex items-center space-x-4">
                             <!-- ===== BOTÓN MODIFICADO: Ahora abre el modal ===== -->
                            <button v-if="shipment.status === 'Pendiente'" @click="openConfirmationDialog(shipment)"
                                class="px-3 py-1 bg-green-600 text-white rounded-md text-sm hover:bg-green-700 transition-colors duration-200 flex items-center gap-2">
                                <i class="fa-solid fa-truck-fast"></i>
                                Marcar como Enviado
                            </button>
                            <el-tag :type="getStatusTagType(shipment.status)">{{ shipment.status }}</el-tag>
                             <!-- Indicador de Alerta -->
                            <el-tooltip v-if="isOverdue(shipment.promise_date, shipment.status)" content="La fecha promesa ha pasado" placement="top">
                                <i class="fa-solid fa-triangle-exclamation text-red-500 text-xl animate-pulse"></i>
                            </el-tooltip>
                        </div>
                    </div>
                    
                    <!-- Información del Envío -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mb-4">
                        <div>
                            <p class="font-semibold text-gray-600 dark:text-gray-400">Fecha Promesa</p>
                            <span>{{ formatDate(shipment.promise_date) ?? 'N/A' }}</span>
                        </div>
                         <div>
                            <p class="font-semibold text-gray-600 dark:text-gray-400">Paquetería</p>
                            <span>{{ shipment.shipping_company ?? 'N/A' }}</span>
                        </div>
                         <div>
                            <p class="font-semibold text-gray-600 dark:text-gray-400">Guía de Rastreo</p>
                            <span>{{ shipment.tracking_guide ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-600 dark:text-gray-400"># de Paquetes</p>
                            <span>{{ shipment.number_of_packages ?? 'N/A' }}</span>
                        </div>
                    </div>
                    
                    <!-- Productos en este envío -->
                     <h4 class="text-md font-semibold mb-2 mt-5">Productos en este envío</h4>
                    <div v-if="shipment.shipment_products?.length" class="space-y-3 max-h-[65vh] overflow-auto">
                       <ShipmentProductCard v-for="item in shipment.shipment_products" :key="item.id" :shipment-product="item" />
                    </div>
                    <div v-else class="text-center text-gray-500 text-sm py-6">
                        <p>No se han registrado productos para este envío.</p>
                    </div>
                </div>
            </div>
        </main>

        <!-- ===== MODAL DE CONFIRMACIÓN AGREGADO ===== -->
        <el-dialog v-model="dialogVisible" title="Confirmar Envío" width="500px">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Estás a punto de marcar este envío como "Enviado". Por favor, agrega una nota si es necesario y confirma quién realiza el envío.
            </p>
            <div class="space-y-4">
                <div>
                    <label for="sent_by" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Enviado por</label>
                    <el-input v-model="form.sent_by" id="sent_by" placeholder="Nombre de quien envía" />
                </div>
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notas (Opcional)</label>
                    <el-input v-model="form.notes" id="notes" type="textarea" :rows="3" placeholder="Añade notas sobre el envío aquí..." />
                </div>
            </div>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="dialogVisible = false">Cancelar</el-button>
                    <el-button type="primary" @click="confirmShipment">
                        Confirmar Envío
                    </el-button>
                </span>
            </template>
        </el-dialog>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import ShipmentProductCard from "@/Components/MyComponents/ShipmentProductCard.vue";
import { Link, router } from "@inertiajs/vue3";
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    name: 'ShipmentShow',
    components: {
        Link,
        AppLayout,
        SecondaryButton,
        ShipmentProductCard,
    },
    props: {
        sale: Object,
    },
    // --- ESTADO LOCAL PARA EL MODAL ---
    data() {
        return {
            dialogVisible: false,
            form: {
                id: null,
                notes: '',
                sent_by: '',
            }
        }
    },
    methods: {
        // --- MÉTODO NUEVO: Abre el modal y prepara el formulario ---
        openConfirmationDialog(shipment) {
            this.form.id = shipment.id;
            this.form.notes = shipment.notes || ''; // Carga notas existentes si las hay
            this.form.sent_by = this.$page.props.auth.user.name; // Carga el nombre del usuario logueado por defecto
            this.dialogVisible = true;
        },

        // --- MÉTODO NUEVO: Confirma y envía la petición de actualización ---
        confirmShipment() {
            router.put(route('shipments.update', this.form.id), this.form, {
                preserveScroll: true,
                onSuccess: () => {
                    this.dialogVisible = false; // Cierra el modal si todo sale bien
                },
                onError: (errors) => {
                    console.error('Error al actualizar el envío:', errors);
                    // Aquí podrías mostrar una notificación de error al usuario
                }
            });
        },
        printOrder() {
            window.open(route('sales.print', this.sale.id), '_blank');
        },
        getPrimaryDetail(contact, type) {
            if (!contact.details) return 'No disponible';
            const detail = contact.details.find(d => d.type === type && d.is_primary);
            return detail ? detail.value : 'No disponible';
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
        formatDate(dateString) {
            if (!dateString) return 'No especificada';
            const date = new Date(dateString.replace(/-/g, '/'));
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        },
        formatCurrency(value) {
            if (value === null || value === undefined) return '$0.00';
            const num = Number(value);
            return num.toLocaleString('es-MX', {
                style: 'currency',
                currency: 'MXN',
            });
        },
        getStatusTagType(status) {
            const statusMap = {
                'Pendiente': 'warning',
                'Enviado': 'success',
            };
            return statusMap[status] || 'info';
        },
        isOverdue(promiseDate, status) {
            if (!promiseDate || status === 'Enviado') return false;
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const promise = new Date(promiseDate.replace(/-/g, '/'));
            return promise < today;
        }
    },
};
</script>
