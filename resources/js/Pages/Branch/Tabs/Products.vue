<template>
  <div v-for="product in products" :key="product.id" class="bg-gray-100 dark:bg-slate-900/50 border border-gray-200 dark:border-gray-700 rounded-lg p-4 transition-all hover:shadow-md">
    <!-- ... Información del producto ... -->
    <div class="flex items-start space-x-4">
        <figure class="w-24 h-24 rounded-lg overflow-hidden flex-shrink-0 border dark:border-slate-600">
            <img v-if="product.media?.length" :src="product.media[0]?.original_url" :alt="product.name" class="w-full h-full object-cover">
            <div v-else class="w-full h-full bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-400 dark:text-slate-500">
                <i class="fa-solid fa-image text-3xl"></i>
            </div>
        </figure>
        <div class="flex-grow">
            <div class="flex justify-between items-start">
                <div>
                    <h4 @click="$inertia.visit(route('catalog-products.show', product.id))" class="font-bold text-lg text-gray-800 dark:text-gray-100 cursor-pointer hover:!text-blue-400">{{ product.name }}</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ product.code }}</p>
                </div>
                <div class="flex items-center space-x-2">
                    <el-tooltip content="Actualizar precio especial" placement="top">
                        <button @click="openPriceModal(product)" class="size-8 flex-shrink-0 flex items-center justify-center rounded-md bg-blue-100 text-blue-600 hover:bg-blue-200 dark:bg-blue-900/50 dark:text-blue-400 dark:hover:bg-blue-900 transition-colors">
                            <i class="fa-solid fa-dollar-sign text-sm"></i>
                        </button>
                    </el-tooltip>
                    <el-tooltip content="Remover producto del cliente" placement="top">
                        <button @click="openRemoveModal(product)" class="size-8 flex-shrink-0 flex items-center justify-center rounded-md bg-red-100 text-red-600 hover:bg-red-200 dark:bg-red-900/50 dark:text-red-400 dark:hover:bg-red-900 transition-colors">
                            <i class="fa-solid fa-trash-can text-sm"></i>
                        </button>
                    </el-tooltip>
                </div>
            </div>
            <div class="text-sm mt-2 space-y-1">
                <p><strong class="font-semibold text-gray-600 dark:text-gray-300">Precio actual:</strong> ${{ currentPrice(product) }}</p>
                <p><strong class="font-semibold text-gray-600 dark:text-gray-300">Material:</strong> {{ product.material }}</p>
            </div>
        </div>
    </div>
    <!-- Historial de Precios Especiales -->
    <div v-if="product.price_history.length" class="mt-4">
        <el-collapse>
            <el-collapse-item>
                <template #title>
                    <span class="font-semibold text-sm text-blue-500">
                        <i class="fa-solid fa-clock-rotate-left mr-2"></i> Ver Historial de Precios Especiales ({{ product.price_history.length }})
                    </span>
                </template>
                <div class="p-2">
                    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-slate-700 dark:text-gray-300">
                            <tr>
                                <th scope="col" class="px-4 py-2">Precio Especial</th>
                                <th scope="col" class="px-4 py-2">Vigente Desde</th>
                                <th scope="col" class="px-4 py-2">Vigente Hasta</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="history in product.price_history" :key="history.id" class="bg-white dark:bg-slate-800 border-b dark:border-gray-600">
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">${{ history.price }}</td>
                                <td class="px-4 py-2">{{ formatDate(history.valid_from) }}</td>
                                <td class="px-4 py-2 flex items-center justify-between">
                                    <span>{{ history.valid_to ? formatDate(history.valid_to) : 'Indefinido' }}</span>
                                    <!-- BOTÓN PARA FINALIZAR PRECIO ACTIVO -->
                                    <el-tooltip v-if="!history.valid_to" content="Finalizar vigencia de este precio" placement="top">
                                        <button @click="confirmCloseSpecialPrice(history.id)" class="size-7 flex items-center justify-center rounded-md text-red-500 bg-red-100 hover:bg-red-200 dark:bg-red-900/50 dark:hover:bg-red-900 transition-colors">
                                            <i class="fa-solid fa-calendar-xmark text-sm"></i>
                                        </button>
                                    </el-tooltip>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </el-collapse-item>
        </el-collapse>
    </div>
        <p v-else class="text-xs text-center text-gray-400 dark:text-gray-500 mt-4 border-t dark:border-gray-700 pt-2">
        No hay precios especiales registrados para este producto.
    </p>
</div>

<!-- MODAL PARA ACTUALIZAR PRECIO -->
<ConfirmationModal :show="showPriceModal" @close="showPriceModal = false">
    <template #title>
        Actualizar precio de <span class="text-blue-500">{{ productForUpdate?.name }}</span>
    </template>
    <template #content>
        <div class="space-y-4 text-sm dark:text-gray-300">
            <p>El precio de referencia actual es <strong class="font-semibold">${{ priceForm.current_base_price }}</strong>. El nuevo precio no puede ser inferior al actual y el aumento debe ser de al menos 4%.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                <div>
                    <label class="font-semibold">Aumento en porcentaje*</label>
                        <el-input v-model="priceForm.percentage" @input="updatePriceFromPercentage" placeholder="Ej. 5" class="mt-1">
                        <template #append>%</template>
                    </el-input>
                </div>
                    <div>
                    <label class="font-semibold">Precio nuevo en moneda*</label>
                        <el-input v-model="priceForm.amount" @input="updatePriceFromAmount" placeholder="Ej. 44.10" class="mt-1">
                        <template #prepend>$</template>
                    </el-input>
                </div>
            </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="font-semibold">Moneda*</label>
                    <el-select v-model="priceForm.currency" placeholder="Moneda" :teleported="false" class="!w-full mt-1">
                        <el-option label="MXN" value="MXN" />
                        <el-option label="USD" value="USD" />
                    </el-select>
                </div>
                <div>
                    <label class="font-semibold">Fecha de cambio (Vigente desde)*</label>
                    <el-date-picker v-model="priceForm.valid_from" :teleported="false" type="date" placeholder="Selecciona una fecha" class="!w-full mt-1" />
                </div>
            </div>
            <!-- MENSAJE DE ERROR -->
            <div v-if="priceForm.amount && isPriceInvalid" class="text-red-500 text-xs mt-1 p-2 bg-red-50 dark:bg-red-900/40 rounded-md">
                <i class="fa-solid fa-circle-exclamation mr-1"></i>
                El precio debe ser mayor o igual a ${{ priceForm.min_allowed_price.toFixed(2) }} (aumento mínimo del 4%).
            </div>
        </div>
    </template>
    <template #footer>
        <div class="flex space-x-2">
            <CancelButton @click="showPriceModal = false">Cancelar</CancelButton>
            <PrimaryButton @click="submitNewPrice" :disabled="isPriceInvalid" class="!bg-blue-600 hover:!bg-blue-700 disabled:!bg-blue-300 dark:disabled:!bg-slate-600">Actualizar precio</PrimaryButton>
        </div>
    </template>
</ConfirmationModal>

<!-- Confirmación para Finalizar Precio -->
<ConfirmationModal :show="showClosePriceConfirmModal" @close="showClosePriceConfirmModal = false">
    <template #title>
        Finalizar Precio Especial
    </template>
    <template #content>
        ¿Estás seguro de que deseas finalizar la vigencia de este precio especial? El producto volverá a su precio base para este cliente.
    </template>
    <template #footer>
        <div class="flex space-x-2">
            <CancelButton @click="showClosePriceConfirmModal = false">Cancelar</CancelButton>
            <PrimaryButton @click="closeSpecialPrice" class="!bg-red-600 hover:!bg-red-700">Sí, finalizar</PrimaryButton>
        </div>
    </template>
</ConfirmationModal>

<!-- ===== NUEVO MODAL PARA REMOVER PRODUCTO ===== -->
<ConfirmationModal :show="showRemoveModal" @close="showRemoveModal = false">
    <template #title>
        Remover Producto del Cliente
    </template>
    <template #content>
        ¿Estás seguro de remover <span class="font-bold text-red-500">{{ productToRemove?.name }}</span> de este cliente?
        <br><br>
        Esta acción eliminará permanentemente la relación y todo el <strong>historial de precios especiales</strong> asociados. Esta acción no se puede deshacer.
    </template>
    <template #footer>
        <div class="flex space-x-2">
            <CancelButton @click="showRemoveModal = false">Cancelar</CancelButton>
            <PrimaryButton @click="removeProductFromBranch" class="!bg-red-600 hover:!bg-red-700">Sí, remover</PrimaryButton>
        </div>
    </template>
</ConfirmationModal>

</template>

<script>
import PrimaryButton from "@/Components/PrimaryButton.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import { ElMessage } from 'element-plus';
import axios from 'axios';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
data() {
    return {
        // --- DATOS PARA EL MODAL DE PRECIO ---
        showPriceModal: false,
        productForUpdate: null,
        priceForm: {
            amount: null,
            percentage: null,
            currency: 'MXN',
            valid_from: new Date(),
            current_base_price: 0,
            min_allowed_price: 0,
        },

        showClosePriceConfirmModal: false,
        priceHistoryToClose: null,

        // --- NUEVOS DATOS PARA EL MODAL DE REMOVER ---
        showRemoveModal: false,
        productToRemove: null,
    }
},
components:{
    CancelButton,
    PrimaryButton,
    ConfirmationModal,
},
props:{
    products: Array,
    branchId: Number
},
computed: {
    isPriceInvalid() {
        if (!this.priceForm.amount || this.priceForm.amount <= 0) return true;
        return this.priceForm.amount < this.priceForm.min_allowed_price;
    }
},
methods:{
    openPriceModal(product) {
        const basePrice = product.price_history?.[0]?.price ?? product.base_price;
        
        this.productForUpdate = product;
        this.priceForm = {
            amount: null,
            percentage: null,
            currency: 'MXN',
            valid_from: new Date(),
            current_base_price: basePrice,
            min_allowed_price: basePrice * 1.04, // Regla de aumento del 4%
        };
        this.showPriceModal = true;
    },
    currentPrice(product) {
        // si tiene precio especial y esta vigente, lo toma como precio actual, si no, toma el precio base
        const specialPrice = product.price_history?.[0];
        
        if (specialPrice && (!specialPrice.valid_to || specialPrice.valid_to === null)) {
            return specialPrice.price;
        }
        return product.base_price;
    },
    formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return format(date, "d 'de' MMMM, yyyy", { locale: es });
    },
    async submitNewPrice() {
        if (this.isPriceInvalid) {
            ElMessage.error('El precio ingresado no es válido o es menor al permitido.');
            return;
        }

        try {
            // Definimos la ruta y los parámetros para la petición
            const routeName = 'branches.products.price.store';
            const routeParams = { branch: this.branchId, product: this.productForUpdate.id };
            
            const response = await axios.post(route(routeName, routeParams), this.priceForm);

            if (response.status === 200) {
                ElMessage.success('Precio actualizado correctamente.');
                this.showPriceModal = false;
                // Recarga los datos de la página para reflejar el cambio
                this.$inertia.reload({ preserveScroll: true });
            }
        } catch (error) {
            console.error("Error al actualizar el precio:", error);
            ElMessage.error(error.response?.data?.message || 'Ocurrió un error al guardar el precio.');
        }
    },

    // --- MÉTODOS PARA FINALIZAR PRECIO ---
    confirmCloseSpecialPrice(historyId) {
        this.priceHistoryToClose = historyId;
        this.showClosePriceConfirmModal = true;
    },
    updatePriceFromAmount() {
        if (this.priceForm.amount && this.priceForm.current_base_price > 0) {
            const percentage = ((this.priceForm.amount / this.priceForm.current_base_price) - 1) * 100;
            this.priceForm.percentage = percentage.toFixed(2);
        } else {
            this.priceForm.percentage = null;
        }
    },
    updatePriceFromPercentage() {
        if (this.priceForm.percentage !== null && this.priceForm.percentage !== '') {
            const newAmount = this.priceForm.current_base_price * (1 + (this.priceForm.percentage / 100));
            this.priceForm.amount = newAmount.toFixed(2);
        } else {
            this.priceForm.amount = null;
        }
    },
    async closeSpecialPrice() {
        if (!this.priceHistoryToClose) return;
        try {
            // Usamos PATCH para indicar una actualización parcial del recurso
            const response = await axios.patch(route('branch-price-history.close', this.priceHistoryToClose));
            if (response.status === 200) {
                ElMessage.success('El precio especial ha sido finalizado.');
                this.$inertia.reload({ preserveScroll: true });
            }
        } catch (error) {
            console.error("Error al finalizar el precio:", error);
            ElMessage.error(error.response?.data?.message || 'No se pudo finalizar el precio.');
        } finally {
            this.showClosePriceConfirmModal = false;
            this.priceHistoryToClose = null;
        }
    },
    
    // --- MÉTODOS PARA REMOVER PRODUCTO ---
    openRemoveModal(product) {
        this.productToRemove = product;
        this.showRemoveModal = true;
    },
    async removeProductFromBranch() {
        if (!this.productToRemove) return;

        try {
            const response = await axios.delete(route('branches.products.remove', { branch: this.branchId, product: this.productToRemove.id }));

            if (response.status === 200) {
                ElMessage.success('Producto removido correctamente.');
                this.showRemoveModal = false;
                this.$inertia.reload({ preserveScroll: true }); // Recarga los props de la página padre
            }
        } catch (error) {
            console.error("Error al remover el producto:", error);
            ElMessage.error(error.response?.data?.message || 'Ocurrió un error al remover el producto.');
        } finally {
            this.productToRemove = null;
        }
    },
},
}
</script>
