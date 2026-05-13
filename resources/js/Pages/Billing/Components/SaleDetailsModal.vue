<template>
    <el-dialog 
        :model-value="show" 
        @update:model-value="$emit('update:show', $event)"
        :title="'Detalles para Facturación - OV-' + (sale ? sale.id : '')" 
        width="700px"
        destroy-on-close
    >
        <div v-if="sale">
            <!-- Información Requerida para Facturación -->
            <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg text-sm border border-gray-200 dark:border-gray-700 mb-6 space-y-3">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase font-bold">Razón Social</span>
                        <span class="text-gray-800 dark:text-gray-100 font-medium uppercase">
                            {{ sale.branch?.parent?.name || sale.branch?.name || 'N/A' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase font-bold">Costo de logística</span>
                        <span class="text-gray-800 dark:text-gray-100">${{ formatCurrency(sale.freight_cost) }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase font-bold">OC (Orden de Compra Cliente)</span>
                        <span class="text-indigo-600 dark:text-indigo-400 font-medium uppercase">{{ sale.oce_name || 'No proporcionada' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase font-bold">Monto Total de Venta</span>
                        <span class="text-emerald-600 dark:text-emerald-400 font-bold">${{ formatCurrency(sale.total_amount) }}</span>
                    </div>
                    <div v-if="sale.user.name">
                        <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase font-bold">Vendedor</span>
                        <span class="text-gray-800 dark:text-gray-100">{{ sale.user.name }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase font-bold">Sucursal/Alias</span>
                        <span class="text-gray-800 dark:text-gray-100">{{ sale.branch?.name }}</span>
                    </div>
                </div>

                <!-- Notas y Pie de factura -->
                <div class="mt-4 border-t border-gray-200 dark:border-gray-700 pt-3">
                    <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase font-bold">Datos para Pie de Factura</span>
                    <div class="bg-gray-100 dark:bg-gray-900 p-3 rounded mt-2 border border-gray-200 dark:border-gray-700">
                        <div class="text-gray-800 dark:text-gray-200 uppercase font-mono text-xs whitespace-pre-line">
                            <p>{{ sale.notes ?? 'No hay notas disponibles' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lista de Productos -->
            <div class="mb-6">
                <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-2 border-b pb-1">Productos a Facturar</h4>
                <div class="max-h-60 overflow-y-auto pr-2">
                    <table class="w-full text-xs text-left">
                        <thead class="text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-slate-800 sticky top-0 z-10">
                            <tr>
                                <th class="p-2 font-medium rounded-l-md w-16">Imagen</th>
                                <th class="p-2 font-medium">Descripción</th>
                                <th class="p-2 font-medium text-center">Cantidad</th>
                                <th class="p-2 font-medium rounded-r-md text-right">Precio U.</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="item in sale.sale_products" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-slate-800">
                                <td class="p-2">
                                    <el-image 
                                        v-if="item.product?.media?.length"
                                        style="width: 40px; height: 40px; border-radius: 4px;"
                                        :src="item.product.media[0].original_url" 
                                        :zoom-rate="1.2"
                                        :max-scale="7"
                                        :min-scale="0.2"
                                        :preview-src-list="item.product.media[0].original_url ? [item.product.media[0].original_url] : []"
                                      :initial-index="0"
                                        fit="cover"
                                        hide-on-click-modal
                                    />
                                    <div v-else class="w-10 h-10 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center text-gray-400">
                                        <i class="fa-solid fa-image"></i>
                                    </div>
                                </td>
                                <td class="p-2 text-gray-800 dark:text-gray-200 uppercase">{{ item.product?.name || 'Producto Desconocido' }}</td>
                                <td class="p-2 text-gray-600 dark:text-gray-400 text-center">{{ item.quantity }} pzas</td>
                                <td class="p-2 text-gray-600 dark:text-gray-400 text-right">${{ formatCurrency(item.price) }}</td>
                            </tr>
                            <tr v-if="!sale.sale_products?.length">
                                <td colspan="4" class="p-4 text-center text-gray-400 italic">No hay productos en esta OV.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Formulario de Folios -->
            <form @submit.prevent="submitUpdate" class="bg-indigo-50 dark:bg-indigo-900/10 p-4 rounded-lg border border-indigo-100 dark:border-indigo-900">
                <h4 class="text-sm font-bold text-indigo-800 dark:text-indigo-300 mb-2">Captura de Folios</h4>
                <p class="text-xs text-indigo-600 dark:text-indigo-400 mb-4">
                    Escribe el folio y presiona 'Enter' para agregar múltiples registros. El estatus se actualizará automáticamente.
                </p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1 block">Folios Pre-factura (Externa)</label>
                        <el-select
                            v-model="folios.pre_invoice"
                            multiple
                            filterable
                            allow-create
                            default-first-option
                            :reserve-keyword="false"
                            placeholder="Escribe y presiona Enter..."
                            class="!w-full"
                            no-data-text="Escribe un folio y presiona Enter"
                        >
                        </el-select>
                        <span v-if="form.errors.pre_invoice_folio" class="text-red-500 text-xs mt-1">{{ form.errors.pre_invoice_folio }}</span>
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1 block">Folios Factura Timbrada</label>
                        <el-select
                            v-model="folios.stamped_invoice"
                            multiple
                            filterable
                            allow-create
                            default-first-option
                            :reserve-keyword="false"
                            placeholder="Escribe y presiona Enter..."
                            class="!w-full"
                            no-data-text="Escribe un folio y presiona Enter"
                        >
                        </el-select>
                        <span v-if="form.errors.stamped_invoice_folio" class="text-red-500 text-xs mt-1">{{ form.errors.stamped_invoice_folio }}</span>
                    </div>
                </div>
            </form>
        </div>
        
        <template #footer>
            <span class="dialog-footer">
                <el-button @click="$emit('update:show', false)">Cancelar</el-button>
                <el-button type="primary" @click="submitUpdate" :loading="form.processing">
                    Guardar Folios
                </el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script>
import { useForm } from "@inertiajs/vue3";
import { ElMessage } from 'element-plus';

export default {
    name: 'SaleDetailsModal',
    props: {
        show: Boolean,
        sale: Object,
    },
    emits: ['update:show', 'saved'],
    data() {
        return {
            // Arrays temporales para el manejo visual múltiple en Element Plus
            folios: {
                pre_invoice: [],
                stamped_invoice: []
            },
            form: useForm({
                pre_invoice_folio: '',
                stamped_invoice_folio: '',
            })
        };
    },
    watch: {
        sale: {
            immediate: true,
            handler(newVal) {
                if (newVal) {
                    // Convertimos la cadena separada por comas de la BD en un array para el input múltiple
                    this.folios.pre_invoice = newVal.pre_invoice_folio 
                        ? newVal.pre_invoice_folio.split(',').map(s => s.trim()).filter(s => s) 
                        : [];
                        
                    this.folios.stamped_invoice = newVal.stamped_invoice_folio 
                        ? newVal.stamped_invoice_folio.split(',').map(s => s.trim()).filter(s => s) 
                        : [];
                }
            }
        }
    },
    methods: {
        formatCurrency(value) {
            return parseFloat(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        submitUpdate() {
            // Unimos el array de nuevo en una sola cadena separada por comas para enviarla a tu controlador (ej. "PF-1, PF-2")
            this.form.pre_invoice_folio = this.folios.pre_invoice.join(', ');
            this.form.stamped_invoice_folio = this.folios.stamped_invoice.join(', ');

            this.form.put(route('billing.update-folios', this.sale.id), {
                preserveScroll: true,
                onSuccess: () => {
                    this.$emit('update:show', false);
                    this.$emit('saved');
                    ElMessage.success('Folios guardados y estatus actualizado');
                },
            });
        }
    }
}
</script>