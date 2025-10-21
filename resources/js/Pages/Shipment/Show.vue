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
                <!-- === STEPPER DE ESTADO === -->
                <Stepper :currentStatus="sale.status" :steps="saleSteps" />
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
                            <span>{{ formatDate(sale.created_at) }}</span>
                        </li>
                         <li class="flex justify-between items-center">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Prioridad:</span>
                            <el-tag v-if="sale.is_high_priority" type="danger" size="small">Alta</el-tag>
                            <el-tag v-else type="info" size="small">Normal</el-tag>
                        </li>
                    </ul>
                </div>

                <!-- Card de Resumen de Producción -->
                <div v-if="sale.production_summary && sale.production_summary.total_productions > 0"
                    class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-4">
                    <div class="flex justify-between items-center border-b dark:border-gray-600 pb-2 mb-3">
                        <h3 class="text-lg font-semibold">Resumen de Producción</h3>
                        <!-- Estadísticas Detalladas -->
                        <div class="grid grid-cols-2 text-center text-sm">
                            <div>
                                <p class="font-bold text-lg">{{ sale.production_summary.completed_productions }} / {{ sale.production_summary.total_productions }}</p>
                                <p class="text-gray-500 dark:text-gray-400 text-xs">Productos</p>
                            </div>
                            <div>
                                <p class="font-bold text-lg">{{ sale.production_summary.completed_tasks }} / {{ sale.production_summary.total_tasks }}</p>
                                <p class="text-gray-500 dark:text-gray-400 text-xs">Tareas</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <!-- Estado General -->
                        <div class="flex justify-between items-center text-sm">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Estado:</span>
                            <span class="font-bold px-2 py-1 rounded-md text-xs"
                                :class="{
                                    'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300': sale.production_summary.status === 'Terminada',
                                    'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300': sale.production_summary.status === 'En Proceso',
                                    'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300': sale.production_summary.status === 'Sin material',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300': sale.production_summary.status === 'Pendiente',
                                    'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300': sale.production_summary.status === 'Pausada',
                                }">
                                {{ sale.production_summary.status }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Inicio:</span>
                            <span class="font-bold px-2 py-1 rounded-md text-xs">
                                {{ formatDateTime(sale.production_summary.started_at) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Fin:</span>
                            <span class="font-bold px-2 py-1 rounded-md text-xs">
                                {{ formatDateTime(sale.production_summary.finished_at) }}
                            </span>
                        </div>

                        <!-- Barra de Progreso Futurista -->
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-xs font-medium text-gray-700 dark:text-gray-300">Progreso General</span>
                                <span class="text-xs font-bold text-green-500">{{ sale.production_summary.percentage }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3 dark:bg-slate-700">
                                <div class="bg-gradient-to-r from-emerald-600 to-green-500 h-3 rounded-full transition-all duration-500 ease-out"
                                    :style="{ width: sale.production_summary.percentage + '%' }">
                                </div>
                            </div>
                        </div>

                    </div>
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
                        <div class="flex items-center space-x-2">
                            <!-- ===== BOTÓN PARA CREAR ETIQUETA(S) ===== -->
                             <el-tooltip content="Crear etiqueta(s) de envío" placement="top">
                                <button @click="openLabelModal(shipment)" class="px-3 py-1 bg-blue-500 text-white rounded-md text-sm hover:bg-blue-600 transition-colors duration-200 flex items-center gap-2">
                                    <i class="fa-solid fa-tags"></i>
                                </button>
                            </el-tooltip>

                             <!-- ===== BOTÓN MARCAR COMO ENVIADO ===== -->
                            <button
                                @click="openConfirmationDialog(shipment)"
                                :disabled="!isShipmentReady(shipment)"
                                class="px-3 py-1 bg-green-600 text-white rounded-md text-sm hover:bg-green-700 transition-colors duration-200 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-green-600">
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
                            <span>{{ formatDate(shipment.promise_date) || 'N/A' }}</span>
                        </div>
                         <div>
                            <p class="font-semibold text-gray-600 dark:text-gray-400">Paquetería</p>
                            <span>{{ shipment.shipping_company ?? 'N/A' }}</span>
                        </div>
                         <div>
                            <p class="font-semibold text-gray-600 dark:text-gray-400">Guía de Rastreo</p>
                            <span>{{ shipment.tracking_guide ?? 'N/A' }}</span>
                        </div>
                    </div>
                    
                    <!-- Productos en este envío -->
                     <h4 class="text-md font-semibold mb-2 mt-5">Productos en este envío</h4>
                    <div v-if="shipment.shipment_products?.length" class="space-y-3 max-h-[65vh] overflow-auto pr-2">
                       <ShipmentProductCard v-for="item in shipment.shipment_products" :key="item.id" :shipment-product="item" :shipmentStatus="sale.status" />
                    </div>
                    <div v-else class="text-center text-gray-500 text-sm py-6">
                        <p>No se han registrado productos para este envío.</p>
                    </div>
                </div>
            </div>
        </main>

        <!-- ===== MODAL DE CONFIRMACIÓN ENVIADO ===== -->
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

        <!-- ===== MODAL ETIQUETA DE ENVÍO MEJORADO ===== -->
        <el-dialog v-model="labelModalVisible" title="Crear Etiquetas para el Envío" width="900px">
            <div v-if="labelForm.shipment">
                <!-- SECCIÓN DE PRODUCTOS DISPONIBLES -->
                <div class="bg-gray-100 dark:bg-slate-700 p-4 rounded-lg mb-6">
                    <h3 class="font-semibold text-lg mb-2 text-gray-800 dark:text-gray-200">Productos disponibles para empacar</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div v-for="product in labelForm.shipment.shipment_products" :key="product.id" class="bg-white dark:bg-slate-800 p-3 rounded-md shadow">
                            <p class="font-semibold truncate">{{ product.sale_product.product.name }}</p>
                            <p class="text-gray-600 dark:text-gray-400">
                                <span class="text-lg font-bold" :class="remainingQuantities[product.sale_product.id] < 0 ? 'text-red-500' : 'text-green-500'">
                                    {{ remainingQuantities[product.sale_product.id] }}
                                </span> / {{ product.quantity }} disponibles
                            </p>
                        </div>
                    </div>
                     <el-alert v-if="isOverAllocation" title="¡Atención!" type="error" description="Has asignado más productos de los disponibles en esta parcialidad. Ajusta las cantidades." :closable="false" show-icon class="mt-4" />
                </div>

                <!-- SECCIÓN DE CAJAS -->
                <div class="space-y-4 max-h-[50vh] overflow-y-auto pr-2">
                    <div v-for="(box, index) in labelForm.boxes" :key="box.id" class="border dark:border-gray-600 rounded-lg p-4 bg-white dark:bg-slate-800/50 relative">
                        <div class="flex justify-between items-center mb-3">
                            <h4 class="font-bold text-lg text-primary dark:text-sky-400">Caja #{{ index + 1 }}</h4>
                            <el-button type="danger" plain size="small" @click="removeBox(index)" v-if="labelForm.boxes.length > 1">
                                <i class="fa-solid fa-trash-can mr-1"></i> Quitar Caja
                            </el-button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <!-- Productos en la caja -->
                            <div class="md:col-span-2 space-y-2">
                                <div v-for="product in labelForm.shipment.shipment_products" :key="product.id" class="flex items-center justify-between">
                                    <label class="text-sm text-gray-700 dark:text-gray-300 w-1/2 truncate">{{ product.sale_product.product.name }}</label>
                                    <el-input-number v-model="box.productQuantities[product.sale_product.id]" :min="0" :max="product.quantity" controls-position="right" size="small" class="w-32" />
                                </div>
                            </div>
                            <!-- Opciones de la caja -->
                            <div class="flex items-center justify-between mt-2 pt-2 border-t dark:border-gray-600">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">¿Contenido Frágil?</label>
                                <el-switch v-model="box.isFragile" />
                            </div>
                        </div>
                    </div>
                </div>

                 <div class="mt-4">
                    <el-button @click="addBox" type="primary" plain><i class="fa-solid fa-plus mr-2"></i>Agregar otra caja</el-button>
                </div>
            </div>
            
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="labelModalVisible = false">Cancelar</el-button>
                    <el-button type="primary" @click="generateAndPrintLabels" :disabled="isOverAllocation || !hasProductsInBoxes">
                        <i class="fa-solid fa-print mr-2"></i>
                        Generar {{ labelForm.boxes.length }} Etiqueta(s)
                    </el-button>
                </span>
            </template>
        </el-dialog>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import Stepper from "@/Components/MyComponents/Stepper.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import ShipmentProductCard from "@/Components/MyComponents/ShipmentProductCard.vue";
import { Link, router } from "@inertiajs/vue3";
import { format, parseISO  } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    name: 'ShipmentShow',
    components: {
        Link,
        Stepper,
        AppLayout,
        SecondaryButton,
        ShipmentProductCard,
    },
    props: {
        sale: Object,
    },
    data() {
        return {
            dialogVisible: false,
            form: {
                id: null,
                notes: '',
                sent_by: '',
            },
            // --- State para modal de etiqueta ---
            labelModalVisible: false,
            labelForm: {
                shipment: null,
                boxes: [],
            },
            saleSteps: ['Autorizada', 'En Proceso', 'En Producción', 'Preparando Envío', 'Enviada'],
        }
    },
    computed: {
        assignedQuantities() {
            const totals = {};
            if (!this.labelForm.shipment) return totals;

            this.labelForm.shipment.shipment_products.forEach(p => {
                totals[p.sale_product.id] = 0;
            });

            this.labelForm.boxes.forEach(box => {
                for (const productId in box.productQuantities) {
                    totals[productId] += Number(box.productQuantities[productId] || 0);
                }
            });
            return totals;
        },
        remainingQuantities() {
            const remaining = {};
            if (!this.labelForm.shipment) return remaining;

            this.labelForm.shipment.shipment_products.forEach(p => {
                const totalInShipment = p.quantity;
                const totalAssigned = this.assignedQuantities[p.sale_product.id] || 0;
                remaining[p.sale_product.id] = totalInShipment - totalAssigned;
            });
            return remaining;
        },
        isOverAllocation() {
            return Object.values(this.remainingQuantities).some(rem => rem < 0);
        },
        hasProductsInBoxes() {
            if (this.labelForm.boxes.length === 0) return false;
            // Verifica que al menos una caja tenga al menos un producto.
            return this.labelForm.boxes.some(box => 
                Object.values(box.productQuantities).some(qty => qty > 0)
            );
        }
    },
    methods: {
        formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('es-MX', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true }).replace('.', '');
        },
        // --- MÉTODOS PARA ETIQUETAS ---
        openLabelModal(shipment) {
            this.labelForm.shipment = shipment;
            this.labelForm.boxes = [];
            this.addBox(); // Agrega la primera caja por defecto
            this.labelModalVisible = true;
        },
        addBox() {
            const newBox = {
                id: Date.now(),
                isFragile: false,
                productQuantities: {}
            };
            this.labelForm.shipment.shipment_products.forEach(p => {
                newBox.productQuantities[p.sale_product.id] = 0;
            });
            this.labelForm.boxes.push(newBox);
        },
        removeBox(index) {
            this.labelForm.boxes.splice(index, 1);
        },
        generateAndPrintLabels() {
            let allLabelsHtml = '';
            const totalBoxes = this.labelForm.boxes.length;

            this.labelForm.boxes.forEach((box, index) => {
                const boxNumber = index + 1;
                const packageInfo = `Caja ${boxNumber} de ${totalBoxes}`;
                
                const productsInBox = this.labelForm.shipment.shipment_products
                    .filter(p => box.productQuantities[p.sale_product.id] > 0)
                    .map(p => ({
                        name: p.sale_product.product.name,
                        quantity: box.productQuantities[p.sale_product.id]
                    }));

                if (productsInBox.length === 0) return; // No generar etiqueta para cajas vacías

                const contentDescription = productsInBox.map(p => `${p.quantity} x ${p.name}`).join(', ');
                const totalPieces = productsInBox.reduce((acc, p) => acc + Number(p.quantity), 0);

                allLabelsHtml += this.getSingleLabelHtml(box, packageInfo, contentDescription, totalPieces, boxNumber);
            });

            const printWindow = window.open('', '_blank', 'width=816,height=1056');
            printWindow.document.write(`
                <!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Etiquetas OV-${this.sale.id}</title>
                <script src="https://cdn.tailwindcss.com"><\/script>
                <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"><\/script>
                <style>@media print { body{-webkit-print-color-adjust:exact;print-color-adjust:exact;} @page{margin:0;size:letter;} .page-break{page-break-before:always;} } .label-container{width:8.5in;height:11in;page-break-inside:avoid;}</style>
                </head><body>
                ${allLabelsHtml}
                <script>
                    document.querySelectorAll('.barcode-svg').forEach(svg => {
                        const value = svg.getAttribute('data-value');
                        if(value) try { JsBarcode(svg, value, { format:"CODE128", displayValue:true, fontSize:18, height:60, margin:10 }); } catch(e){}
                    });
                    window.onload = function() { window.print(); window.onafterprint = function() { window.close(); } }
                <\/script></body></html>`);
            printWindow.document.close();
            this.labelModalVisible = false;
        },
        getSingleLabelHtml(box, packageInfo, contentDescription, totalPieces, boxNumber) {
            const shipment = this.labelForm.shipment;
            const pageBreak = boxNumber > 1 ? '<div class="page-break"></div>' : '';

            return `${pageBreak}
            <div class="label-container bg-white p-8">
                <header class="flex justify-between items-center border-b-2 border-gray-200 pb-4">
                    <h1 class="text-2xl font-bold text-gray-800">emblems3dusa.com</h1>
                    <div class="text-right"><p class="text-gray-600 font-semibold">Fecha de Emisión</p><p class="text-lg">${new Date().toLocaleDateString('es-MX',{year:'numeric',month:'long',day:'numeric'})}</p></div>
                </header>
                <main class="grid grid-cols-2 gap-8 mt-6">
                    <div class="space-y-6">
                        <div>
                            <h2 class="text-sm font-bold text-gray-500 uppercase tracking-widest mb-2">REMITENTE</h2>
                            <div class="border-l-4 border-blue-500 pl-4 text-gray-700"><p class="font-bold">Emblems 3D USA</p><p>Calle 1 #19, Seattle</p><p>Zapopan, Jalisco. C.P. 45150</p><p>Tel: 33 3624 0054 / Cel: 33 2183 5678</p></div>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-gray-500 uppercase tracking-widest mb-2">DESTINATARIO</h2>
                            <div class="border-l-4 border-green-500 pl-4 text-gray-800"><p class="font-bold text-lg">${this.sale.branch?.name??'N/A'}</p><p>Attn: ${this.sale.contact?.name??'N/A'}</p><p>${this.sale.branch?.address??'N/A'}</p><p>C.P. ${this.sale.branch?.post_code??'N/A'}</p><p>Tel: ${this.getPrimaryDetail(this.sale.contact,'Teléfono')}</p></div>
                        </div>
                    </div>
                    <div class="flex flex-col justify-between">
                        ${box.isFragile?`<div class="border-4 border-red-500 rounded-lg p-4 text-center bg-red-50"><div class="flex items-center justify-center gap-4"><svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg><div><h3 class="text-3xl font-extrabold text-red-600">CUIDADO</h3><p class="text-xl font-semibold text-red-500">FRÁGIL</p></div></div></div>`:''}
                        <div class="bg-gray-50 dark:bg-slate-700/50 rounded-lg p-4 space-y-2 text-sm">
                            <div class="flex justify-between"><span class="font-semibold text-gray-600">Orden de Venta:</span><span class="font-mono">OV-${this.sale.id.toString().padStart(4,'0')}</span></div>
                            <div class="flex justify-between"><span class="font-semibold text-gray-600">Contenido:</span><span class="text-right">${contentDescription}</span></div>
                            <div class="flex justify-between"><span class="font-semibold text-gray-600">Piezas en Caja:</span><span>${totalPieces}</span></div>
                            <div class="flex justify-between"><span class="font-semibold text-gray-600">Paquete:</span><span>${packageInfo}</span></div>
                            <div class="flex justify-between"><span class="font-semibold text-gray-600">Paquetería:</span><span>${shipment.shipping_company??'N/A'}</span></div>
                        </div>
                    </div>
                </main>
                <footer class="mt-8 border-t-2 border-gray-200 pt-4 text-center">
                     <svg class="barcode-svg mx-auto" data-value="OV-${this.sale.id.toString().padStart(4, '0')}-C${boxNumber}"></svg>
                </footer>
            </div>`;
        },
        // --- MÉTODOS EXISTENTES ---
        getAvailableStock(saleProduct) {
            const quantityTakenOfStock = saleProduct.quantity - saleProduct.quantity_to_produce
            if (!saleProduct.product.storages || saleProduct.product.storages.length === 0) return 0;
            return saleProduct.product.storages.reduce((total, storage) => total + parseFloat(storage.quantity) + quantityTakenOfStock, 0);
        },
        isShipmentReady(shipment) {
            if (!shipment.shipment_products) return false;
            return shipment.shipment_products.every(item => {
                const availableStock = this.getAvailableStock(item.sale_product);
                return availableStock >= item.quantity;
            });
        },
        // metodo sin modificaciones
        // isShipmentReady(shipment) {
        //     if (!shipment.shipment_products) return false;
        //     return shipment.shipment_products.every(item => {
        //         const availableStock = this.getAvailableStock(item.sale_product.product);
        //         return availableStock >= item.quantity;
        //     });
        // },
        openConfirmationDialog(shipment) {
            this.form.id = shipment.id;
            this.form.notes = shipment.notes || ''; 
            this.form.sent_by = this.$page.props.auth.user.name; 
            this.dialogVisible = true;
        },
        confirmShipment() {
            router.put(route('shipments.update', this.form.id), this.form, {
                preserveScroll: true,
                onSuccess: () => { this.dialogVisible = false; },
                onError: (errors) => { console.error('Error al actualizar el envío:', errors); }
            });
        },
        printOrder() {
            window.open(route('sales.print', this.sale.id), '_blank');
        },
        getPrimaryDetail(contact, type) {
            if (!contact?.details) return 'No disponible';
            const detail = contact.details.find(d => d.type === type && d.is_primary);
            return detail ? detail.value : 'No disponible';
        },
        formatRelative(dateString) {
            if (!dateString) return "Sin registro";
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            if (diffMs < 0) return "En el futuro";
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
            if (!dateString) return '';

            let date;

            // Caso 1: formato con hora (ej. "2025-09-17 12:30:00")
            if (dateString.includes(' ')) {
                const [datePart] = dateString.split(' '); // nos quedamos con YYYY-MM-DD
                const [year, month, day] = datePart.split('-');
                date = new Date(year, month - 1, day);
            }
            // Caso 2: formato ISO (ej. "2025-09-17" o "2025-09-17T00:00:00Z")
            else {
                try {
                    date = parseISO(dateString);
                } catch {
                    return ''; // si no es parseable, devolvemos vacío
                }
            }

            if (isNaN(date)) return ''; // seguridad

            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        },
        getStatusTagType(status) {
            const statusMap = { 'Pendiente': 'warning', 'Enviado': 'success', };
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

