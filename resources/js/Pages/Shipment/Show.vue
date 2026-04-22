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
                <el-tooltip v-if="sale.status != 'Enviada'" content="Agregar Nueva Parcialidad" placement="top">
                    <button @click="openNewShipmentModal" class="size-9 flex items-center justify-center rounded-lg bg-blue-100 text-blue-600 hover:bg-blue-200 dark:bg-blue-900/50 dark:text-blue-400 dark:hover:bg-blue-800 transition-colors mr-2">
                        <i class="fa-solid fa-layer-group"></i>
                    </button>
                </el-tooltip>
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
                
                <!-- Componente Refactorizado: Detalles de la Órden -->
                <OrderDetailsCard :sale="sale" />

                <!-- Componente Refactorizado: Progreso de Envíos por Producto -->
                <ShipmentProgressCard v-if="uniqueSaleProducts.length > 0" :uniqueSaleProducts="uniqueSaleProducts" />

                <!-- Componente Refactorizado: Resumen de Producción -->
                <ProductionSummaryCard :summary="sale.production_summary" />
            </div>

            <!-- COLUMNA DERECHA: ENVÍOS -->
            <div class="lg:col-span-2 space-y-2 max-h-[calc(100vh-200px)] overflow-y-auto pr-2 pb-10 custom-scrollbar">
                <div v-if="!sale.shipments?.length" class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-4 min-h-[300px] flex items-center justify-center">
                     <div class="text-center text-gray-500 dark:text-gray-400 py-10">
                        <i class="fa-solid fa-box-open text-3xl mb-3"></i>
                        <p>Esta órden no tiene envíos registrados.</p>
                    </div>
                </div>
                
                <!-- Acordeón Envoltorio + Componente Tarjeta Original -->
                <template v-else>
                    <ShipmentAccordion 
                        v-for="(shipment, index) in sale.shipments" 
                        :key="shipment.id" 
                        :shipment="shipment"
                        :index="index"
                    >
                        <ShipmentCard 
                            :shipment="shipment"
                            :index="index"
                            :saleStatus="sale.status"
                            :isReady="isShipmentReady(shipment)"
                            @open-label="openLabelModal"
                            @open-confirmation="openConfirmationDialog"
                            @open-guide="openGuideModal"
                            @open-evidence="openEvidenceModal"
                            @delete-media="deleteMedia"
                            @delete-shipment="deleteShipment"
                        />
                    </ShipmentAccordion>
                </template>
            </div>
        </main>

        <!-- ===== MODALES (Se mantienen igual) ===== -->
        <!-- Modal Nueva Parcialidad -->
        <el-dialog v-model="newShipmentModalVisible" title="Agregar Nueva Parcialidad" width="700px">
            <div class="space-y-4">
                <div class="bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 p-3 rounded-lg text-sm flex items-start gap-3">
                    <i class="fa-solid fa-circle-info mt-0.5"></i>
                    <p>Crea un nuevo envío. Si la cantidad que asignes de un producto ya estaba programada en un envío pendiente, <strong>el sistema la restará automáticamente de allá</strong> para moverla a esta nueva parcialidad sin descuadrar la órden.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha Promesa *</label>
                        <el-date-picker v-model="newShipmentForm.promise_date" type="date" placeholder="Selecciona una fecha" format="YYYY/MM/DD" value-format="YYYY-MM-DD" class="!w-full" />
                        <p v-if="newShipmentForm.errors.promise_date" class="text-red-500 text-xs mt-1">{{ newShipmentForm.errors.promise_date }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Paquetería</label>
                        <el-input v-model="newShipmentForm.shipping_company" placeholder="Ej. DHL, FedEx..." />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número de Guía</label>
                        <el-input v-model="newShipmentForm.tracking_guide" placeholder="Ingresa el número de rastreo..." />
                    </div>
                </div>

                <div class="mt-6 border-t dark:border-gray-600 pt-4">
                    <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-3">Cantidades a mover a esta parcialidad</h4>
                    <div class="space-y-3 max-h-[40vh] overflow-y-auto pr-2 custom-scrollbar">
                        <div v-for="(prod, index) in newShipmentForm.products" :key="prod.sale_product_id" class="flex justify-between items-center bg-gray-50 dark:bg-slate-700/50 p-3 rounded-lg border dark:border-gray-600">
                            <div class="w-2/3 pr-4">
                                <p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate" :title="getProductName(prod.sale_product_id)">
                                    {{ getProductName(prod.sale_product_id) }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    Máx. disponible para mover: <span class="font-bold text-gray-800 dark:text-gray-200">{{ getMaxTransferable(prod.sale_product_id) }} pzas</span>
                                </p>
                            </div>
                            <div class="w-1/3 text-right">
                                <el-input-number v-model="prod.quantity" :min="0" :max="getMaxTransferable(prod.sale_product_id)" size="small" class="!w-full" />
                            </div>
                        </div>
                    </div>
                    <p v-if="newShipmentForm.errors.products" class="text-red-500 text-xs mt-1">{{ newShipmentForm.errors.products }}</p>
                    <p v-if="newShipmentForm.errors.message" class="text-red-500 text-sm mt-2 text-center font-bold">{{ newShipmentForm.errors.message }}</p>
                </div>
            </div>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="newShipmentModalVisible = false">Cancelar</el-button>
                    <el-button type="primary" @click="submitNewShipment" :loading="newShipmentForm.processing" :disabled="!hasProductsToShip">Crear Parcialidad</el-button>
                </span>
            </template>
        </el-dialog>

        <!-- Modal Confirmación -->
        <el-dialog v-model="dialogVisible" title="Confirmar Envío" width="600px">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Estás a punto de marcar este envío como "Enviado". Puedes ajustar la fecha y modificar las cantidades si se enviará menos de lo planeado en esta parcialidad.
            </p>
            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha de Envío *</label>
                        <el-date-picker v-model="form.sent_at" type="datetime" placeholder="Selecciona la fecha" format="YYYY/MM/DD hh:mm a" value-format="YYYY-MM-DD HH:mm:ss" class="!w-full" />
                        <p v-if="form.errors.sent_at" class="text-red-500 text-xs mt-1">{{ form.errors.sent_at }}</p>
                    </div>
                    <div>
                        <label for="sent_by" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Enviado por *</label>
                        <el-input v-model="form.sent_by" id="sent_by" placeholder="Nombre de quien envía" />
                        <p v-if="form.errors.sent_by" class="text-red-500 text-xs mt-1">{{ form.errors.sent_by }}</p>
                    </div>
                </div>
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas (Opcional)</label>
                    <el-input v-model="form.notes" id="notes" type="textarea" :rows="2" placeholder="Añade notas sobre el envío aquí..." />
                    <p v-if="form.errors.notes" class="text-red-500 text-xs mt-1">{{ form.errors.notes }}</p>
                </div>
                
                <div class="mt-4 border-t dark:border-gray-600 pt-4">
                    <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-3">Cantidades a enviar en esta caja/parcialidad</h4>
                    <div class="space-y-2 max-h-[30vh] overflow-y-auto pr-2 custom-scrollbar">
                        <div v-for="(prod, index) in form.products" :key="prod.id" class="flex justify-between items-center bg-gray-50 dark:bg-slate-700/50 p-3 rounded-lg border dark:border-gray-600">
                            <div class="w-2/3 pr-4">
                                <p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate" :title="prod.name">{{ prod.name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Programado: <span class="font-bold text-gray-800 dark:text-gray-200">{{ prod.max_quantity }} pzas</span></p>
                            </div>
                            <div class="w-1/3 text-right">
                                <el-input-number v-model="prod.quantity" :min="0" :max="prod.max_quantity" size="small" class="!w-full" />
                            </div>
                        </div>
                    </div>
                    <p v-if="form.errors.products" class="text-red-500 text-xs mt-1">{{ form.errors.products }}</p>
                </div>
            </div>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="dialogVisible = false">Cancelar</el-button>
                    <el-button type="primary" @click="confirmShipment" :loading="form.processing" :disabled="!hasProductsToShipConfirm">Confirmar Envío</el-button>
                </span>
            </template>
        </el-dialog>

        <!-- Modal Etiquetas -->
        <el-dialog v-model="labelModalVisible" title="Crear Etiquetas para el Envío" width="900px">
            <!-- Resto del contenido del modal Etiquetas permanece idéntico -->
             <div v-if="labelForm.shipment">
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
                <div class="space-y-4 max-h-[50vh] overflow-y-auto pr-2 custom-scrollbar">
                    <div v-for="(box, index) in labelForm.boxes" :key="box.id" class="border dark:border-gray-600 rounded-lg p-4 bg-white dark:bg-slate-800/50 relative">
                        <div class="flex justify-between items-center mb-3">
                            <h4 class="font-bold text-lg text-primary dark:text-sky-400">Caja #{{ index + 1 }}</h4>
                            <el-button type="danger" plain size="small" @click="removeBox(index)" v-if="labelForm.boxes.length > 1">
                                <i class="fa-solid fa-trash-can mr-1"></i> Quitar Caja
                            </el-button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <div class="md:col-span-2 space-y-2">
                                <div v-for="product in labelForm.shipment.shipment_products" :key="product.id" class="flex items-center justify-between">
                                    <label class="text-sm text-gray-700 dark:text-gray-300 w-1/2 truncate">{{ product.sale_product.product.name }}</label>
                                    <el-input-number v-model="box.productQuantities[product.sale_product.id]" :min="0" :max="product.quantity" controls-position="right" size="small" class="w-32" />
                                </div>
                            </div>
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
                        <i class="fa-solid fa-print mr-2"></i> Generar {{ labelForm.boxes.length }} Etiqueta(s)
                    </el-button>
                </span>
            </template>
        </el-dialog>

        <!-- Modal Info Rastreo -->
        <el-dialog v-model="showGuideModal" title="Información de Rastreo" width="500px">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Paquetería</label>
                    <el-input v-model="guideForm.shipping_company" placeholder="Ej. DHL, FedEx, PaqueteExpress..." />
                    <p v-if="guideForm.errors.shipping_company" class="text-red-500 text-xs mt-1">{{ guideForm.errors.shipping_company }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número de Guía</label>
                    <el-input v-model="guideForm.tracking_guide" placeholder="Ingresa el número de rastreo..." />
                    <p v-if="guideForm.errors.tracking_guide" class="text-red-500 text-xs mt-1">{{ guideForm.errors.tracking_guide }}</p>
                </div>
            </div>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="showGuideModal = false">Cancelar</el-button>
                    <el-button type="primary" @click="submitGuide" :loading="guideForm.processing">Guardar Información</el-button>
                </span>
            </template>
        </el-dialog>

        <!-- Modal Evidencia -->
        <el-dialog v-model="showEvidenceModal" title="Subir Evidencia de Entrega" width="500px">
            <div class="space-y-4">
                <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer relative">
                    <input type="file" @change="handleEvidenceFileChange" multiple accept="image/*,.pdf" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" />
                    <div class="space-y-2">
                            <i class="fa-solid fa-cloud-arrow-up text-3xl text-gray-400"></i>
                            <p class="text-sm text-gray-500">Haz clic o arrastra archivos aquí</p>
                            <p class="text-xs text-gray-400">(Imágenes o PDF, máx 5MB)</p>
                    </div>
                </div>
                <p v-if="evidenceForm.errors.evidence_files" class="text-red-500 text-xs mt-1">{{ evidenceForm.errors.evidence_files }}</p>
                <div v-if="evidenceForm.evidence_files.length" class="mt-3 space-y-2">
                    <p class="text-xs font-semibold uppercase text-gray-500">Archivos seleccionados:</p>
                    <div v-for="(file, idx) in evidenceForm.evidence_files" :key="idx" class="text-xs flex items-center bg-blue-50 text-blue-700 px-2 py-1 rounded border border-blue-200">
                        <i class="fa-solid fa-file mr-2"></i> {{ file.name }}
                    </div>
                </div>
            </div>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="showEvidenceModal = false">Cancelar</el-button>
                    <el-button type="primary" @click="submitEvidence" :loading="evidenceForm.processing" :disabled="!evidenceForm.evidence_files.length">Subir Archivos</el-button>
                </span>
            </template>
        </el-dialog>

    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import Stepper from "@/Components/MyComponents/Stepper.vue";
import ShipmentCard from "@/Components/MyComponents/ShipmentCard.vue";
import OrderDetailsCard from "@/Components/MyComponents/OrderDetailsCard.vue";
import ShipmentProgressCard from "@/Components/MyComponents/ShipmentProgressCard.vue";
import ProductionSummaryCard from "@/Components/MyComponents/ProductionSummaryCard.vue";
import ShipmentAccordion from "@/Components/MyComponents/ShipmentAccordion.vue";

import { Link, router, useForm } from "@inertiajs/vue3";
import { ElMessage } from 'element-plus';
import axios from 'axios';

export default {
    name: 'ShipmentShow',
    components: {
        Link,
        Stepper,
        AppLayout,
        ShipmentCard,
        OrderDetailsCard,
        ShipmentProgressCard,
        ProductionSummaryCard,
        ShipmentAccordion
    },
    props: {
        sale: Object,
    },
    data() {
        return {
            dialogVisible: false,
            form: useForm({
                id: null,
                sent_at: '',
                notes: '',
                sent_by: '',
                products: []
            }),
            labelModalVisible: false,
            labelForm: {
                shipment: null,
                boxes: [],
            },
            saleSteps: ['Autorizada', 'En Proceso', 'En Producción', 'Preparando Envío', 'Enviada'],
            showGuideModal: false,
            guideForm: useForm({
                shipment_id: null,
                shipping_company: '',
                tracking_guide: '',
            }),
            showEvidenceModal: false,
            evidenceForm: useForm({
                shipment_id: null,
                evidence_files: [],
            }),
            newShipmentModalVisible: false,
            newShipmentForm: useForm({
                sale_id: this.sale.id,
                promise_date: '',
                shipping_company: '',
                tracking_guide: '',
                products: []
            })
        }
    },
    computed: {
        hasProductsToShipConfirm() {
            if (!this.form.products) return false;
            return this.form.products.some(p => p.quantity > 0);
        },
        assignedQuantities() {
            const totals = {};
            if (!this.labelForm.shipment) return totals;
            this.labelForm.shipment.shipment_products.forEach(p => { totals[p.sale_product.id] = 0; });
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
            return this.labelForm.boxes.some(box => Object.values(box.productQuantities).some(qty => qty > 0));
        },
        uniqueSaleProducts() {
            if (!this.sale || !this.sale.shipments) return [];
            const productsMap = new Map();
            this.sale.shipments.forEach(shipment => {
                shipment.shipment_products?.forEach(sp => {
                    if (!productsMap.has(sp.sale_product_id)) {
                        productsMap.set(sp.sale_product_id, {
                            id: sp.sale_product_id,
                            product: sp.sale_product.product,
                            total_quantity: sp.sale_product.quantity,
                            shipped_quantity: 0,
                            percentage: 0
                        });
                    }
                });
            });
            this.sale.shipments.forEach(shipment => {
                if (shipment.status === 'Enviado') {
                    shipment.shipment_products?.forEach(sp => {
                        const prod = productsMap.get(sp.sale_product_id);
                        if (prod) prod.shipped_quantity += sp.quantity;
                    });
                }
            });
            productsMap.forEach(prod => {
                prod.percentage = Math.round((prod.shipped_quantity / prod.total_quantity) * 100);
                if (prod.percentage > 100) prod.percentage = 100;
            });
            return Array.from(productsMap.values());
        },
        hasProductsToShip() {
            return this.newShipmentForm.products.some(p => p.quantity > 0);
        }
    },
    methods: {
        getPrimaryDetail(contact, type) {
            if (!contact?.details) return 'No disponible';
            const detail = contact.details.find(d => d.type === type && d.is_primary);
            return detail ? detail.value : 'No disponible';
        },
        openNewShipmentModal() {
            this.newShipmentForm.reset();
            this.newShipmentForm.clearErrors();
            this.newShipmentForm.products = this.uniqueSaleProducts.map(p => ({
                sale_product_id: p.id,
                quantity: 0
            }));
            this.newShipmentModalVisible = true;
        },
        getMaxTransferable(saleProductId) {
            const saleProductInfo = this.uniqueSaleProducts.find(p => p.id === saleProductId);
            if (!saleProductInfo) return 0;
            return saleProductInfo.total_quantity - saleProductInfo.shipped_quantity;
        },
        getProductName(saleProductId) {
            const found = this.uniqueSaleProducts.find(p => p.id === saleProductId);
            return found ? found.product.name : 'Desconocido';
        },
        submitNewShipment() {
            const productsToSend = this.newShipmentForm.products.filter(p => p.quantity > 0);
            this.newShipmentForm.transform(data => ({
                ...data,
                products: productsToSend
            })).post(route('shipments.store'), {
                onSuccess: () => {
                    this.newShipmentModalVisible = false;
                    ElMessage.success('Nueva parcialidad generada y productos reagrupados con éxito.');
                },
                onError: (errors) => {
                    console.error(errors);
                    if(!errors.message && !errors.products) {
                       ElMessage.error('Error al procesar la solicitud, verifica los datos e intenta de nuevo.');
                    }
                }
            });
        },
        async deleteMedia(mediaId) {
            try {
                await axios.delete(route('media.delete-file', mediaId))
                window.location.reload();
                this.$message.success('Archivo eliminado correctamente')
            } catch (error) {
                console.error(error)
                this.$message.error('Error al eliminar el archivo')
            }
        },
        deleteShipment(shipmentId) {
            router.delete(route('shipments.destroy', shipmentId), {
                preserveScroll: true,
                onSuccess: () => ElMessage.success('Parcialidad eliminada correctamente.'),
                onError: (errors) => ElMessage.error(errors.message || 'Error al eliminar la parcialidad.')
            });
        },
        openLabelModal(shipment) {
            this.labelForm.shipment = shipment;
            this.labelForm.boxes = [];
            this.addBox(); 
            this.labelModalVisible = true;
        },
        addBox() {
            const newBox = { id: Date.now(), isFragile: false, productQuantities: {} };
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

                if (productsInBox.length === 0) return; 

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
        openGuideModal(shipment) {
            this.guideForm.shipment_id = shipment.id;
            this.guideForm.shipping_company = shipment.shipping_company;
            this.guideForm.tracking_guide = shipment.tracking_guide;
            this.showGuideModal = true;
        },
        submitGuide() {
            this.guideForm.put(route('shipments.update-tracking', this.guideForm.shipment_id), {
                onSuccess: () => {
                    this.showGuideModal = false;
                    ElMessage.success('Guía actualizada correctamente');
                },
                onError: () => ElMessage.error('Error al actualizar la guía.')
            });
        },
        openEvidenceModal(shipment) {
            this.evidenceForm.reset();
            this.evidenceForm.shipment_id = shipment.id;
            this.showEvidenceModal = true;
        },
        handleEvidenceFileChange(e) {
            this.evidenceForm.evidence_files = Array.from(e.target.files);
        },
        submitEvidence() {
            this.evidenceForm.post(route('shipments.store-evidence', this.evidenceForm.shipment_id), {
                onSuccess: () => {
                    this.showEvidenceModal = false;
                    this.evidenceForm.reset();
                    ElMessage.success('Evidencia subida correctamente');
                },
                onError: () => ElMessage.error('Error al subir la evidencia.')
            });
        },
        getAvailableStock(saleProduct) {
            const quantityTakenOfStock = saleProduct.quantity - saleProduct.quantity_to_produce;
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
        openConfirmationDialog(shipment) {
            this.form.clearErrors();
            this.form.id = shipment.id;
            this.form.notes = shipment.notes || ''; 
            this.form.sent_by = this.$page.props.auth.user.name; 
            
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            this.form.sent_at = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;

            this.form.products = shipment.shipment_products.map(sp => ({
                id: sp.id,
                name: sp.sale_product?.product?.name || 'Producto Desconocido',
                max_quantity: sp.quantity,
                quantity: sp.quantity 
            }));

            this.dialogVisible = true;
        },
        confirmShipment() {
            this.form.put(route('shipments.update', this.form.id), {
                preserveScroll: true,
                onSuccess: () => { 
                    this.dialogVisible = false; 
                    ElMessage.success('Envío confirmado y stock actualizado exitosamente.');
                },
                onError: (errors) => { 
                    console.error('Error al actualizar el envío:', errors); 
                    if (!this.form.errors.sent_at && !this.form.errors.products) {
                         ElMessage.error('Error al actualizar el envío. Revisa los datos.');
                    }
                }
            });
        },
        printOrder() {
            window.open(route('sales.print', this.sale.id), '_blank');
        }
    },
};
</script>
<style>
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: rgba(156, 163, 175, 0.5); border-radius: 20px; }
.dark .custom-scrollbar::-webkit-scrollbar-thumb { background-color: rgba(75, 85, 99, 0.5); }
</style>