<template>
    <div v-if="product"
        class="border dark:border-slate-700 rounded-lg p-3 hover:bg-gray-50 dark:hover:bg-slate-800 transition-all duration-300"
        :class="{ 'opacity-70 saturate-50': shipmentStatus === 'Enviada' }">
        
        <div class="flex items-start gap-4">
            <!-- Imagen del Producto -->
            <div class="flex-shrink-0">
                <img @click="$inertia.visit(route('catalog-products.show', product.id))"
                    class="size-16 rounded-md object-cover cursor-pointer" :src="product.media[0]?.original_url"
                    :alt="product.name"
                    onerror="this.onerror=null; this.src='https://placehold.co/100x100/EBF4FF/3B82F6?text=Producto'">
            </div>

            <!-- Detalles del Producto -->
            <div class="flex-grow">
                <p class="font-semibold dark:text-white">{{ product.name }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">SKU: {{ product.code }}</p>

                <!-- Información de Cantidad y Stock -->
                <div class="flex items-center justify-between mt-2">
                    <div class="flex items-center">
                        <!-- Vista Normal -->
                        <template v-if="!isEditing">
                            <span class="text-sm font-bold text-primary dark:text-sky-400 mr-1">{{ shipmentProduct.quantity?.toLocaleString() }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400"> {{ product.measure_unit }} {{ isShipmentShipped ? 'enviadas' : 'requeridas' }}</span>
                            
                            <!-- Botón para Editar (Solo si no está enviada la parcialidad) -->
                            <!-- <button v-if="!isShipmentShipped && shipmentStatus !== 'Enviada'" 
                                    @click="isEditing = true" 
                                    class="ml-2 text-gray-400 hover:text-blue-500 transition-colors" 
                                    title="Editar cantidad programada">
                                <i class="fa-solid fa-pencil text-xs"></i>
                            </button> -->
                        </template>

                        <!-- Vista de Edición (Input + Botones de Guardar/Cancelar) -->
                        <template v-else>
                            <div class="flex items-center gap-1.5">
                                <el-input-number v-model="form.quantity" :min="1" size="small" class="!w-24"></el-input-number>
                                
                                <button @click="saveQuantity" 
                                        :disabled="form.processing" 
                                        class="text-green-500 hover:text-green-600 bg-green-50 dark:bg-green-900/30 px-2 py-1 rounded transition-colors shadow-sm disabled:opacity-50" 
                                        title="Guardar">
                                    <i class="fa-solid fa-check"></i>
                                </button>
                                
                                <button @click="cancelEdit" 
                                        class="text-red-500 hover:text-red-600 bg-red-50 dark:bg-red-900/30 px-2 py-1 rounded transition-colors shadow-sm" 
                                        title="Cancelar">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </template>
                    </div>
                    
                    <!-- Indicador de Stock Disponible -->
                    <div v-if="!isShipmentShipped"
                        :class="availableStock >= shipmentProduct.quantity ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300'"
                        class="px-2 py-1 rounded-full text-xs font-semibold">
                        Disponible: {{ availableStock?.toLocaleString() }}
                    </div>
                    
                    <!-- Indicador de Enviado -->
                    <div v-else
                        class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-sky-100 text-sky-800 dark:bg-sky-900/50 dark:text-sky-300">
                        <svg class="size-4 -ml-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        <span>Enviado</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- NUEVO: Alerta de Envío Incompleto (Sólo aparece si original_quantity > quantity) -->
        <div v-if="isShipmentShipped && shipmentProduct.original_quantity && shipmentProduct.original_quantity > shipmentProduct.quantity" 
             class="mt-3 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800/60 rounded p-3 text-xs w-full">
            <p class="text-orange-800 dark:text-orange-300 font-semibold mb-1.5">
                <i class="fa-solid fa-triangle-exclamation mr-1"></i> Se envió incompleto
            </p>
            <div class="grid grid-cols-2 gap-2 text-orange-700 dark:text-orange-400">
                <p><span class="font-medium text-orange-800 dark:text-orange-300">Programado inicialmente:</span> {{ shipmentProduct.original_quantity }} {{ product.measure_unit }}</p>
                <p><span class="font-medium text-orange-800 dark:text-orange-300">Enviado realmente:</span> {{ shipmentProduct.quantity }} {{ product.measure_unit }}</p>
                <p class="col-span-2 mt-1"><span class="font-medium text-orange-800 dark:text-orange-300">Razón:</span> {{ shipmentProduct.less_sent_reason }}</p>
            </div>
        </div>

    </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

export default {
    name: 'ShipmentProductCard',
    props: {
        shipmentProduct: {
            type: Object,
            required: true,
        },
        shipmentStatus: String,
        isShipmentShipped: {
            type: Boolean,
            default: false
        }
    },
    data() {
        return {
            isEditing: false,
            form: useForm({
                quantity: this.shipmentProduct.quantity
            })
        }
    },
    watch: {
        // Mantiene sincronizado el formulario en caso de que la cantidad se actualice globalmente
        'shipmentProduct.quantity'(newVal) {
            this.form.quantity = newVal;
            this.form.defaults({ quantity: newVal });
        }
    },
    computed: {
        product() {
            return this.shipmentProduct?.sale_product?.product;
        },
        // Calcula el stock disponible para este producto
        availableStock() {
            // Cantidad tomada de stock desde la creación de venta que ya fue descontada del stock
            const quantityTakenOfStock = this.shipmentProduct.sale_product.quantity - this.shipmentProduct.sale_product.quantity_to_produce
            if (!this.product?.storages || this.product.storages.length === 0) {
                return 0;
            }
            return this.product.storages.reduce((total, storage) => total + parseFloat(storage.quantity) + quantityTakenOfStock, 0);
        }
    },
    methods: {
        saveQuantity() {
            this.form.put(route('shipments.update-product-quantity', this.shipmentProduct.id), {
                preserveScroll: true,
                onSuccess: () => {
                    this.isEditing = false;
                    ElMessage.success('Cantidad actualizada correctamente.');
                },
                onError: (errors) => {
                    // Muestra el mensaje de error si se superó el límite (ej. se excede de la orden)
                    ElMessage.error(errors.message || 'Error al actualizar la cantidad.');
                }
            });
        },
        cancelEdit() {
            this.isEditing = false;
            this.form.reset();
            this.form.clearErrors();
        }
    }
}
</script>