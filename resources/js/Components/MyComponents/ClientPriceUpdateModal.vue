<template>
    <ConfirmationModal :show="show" @close="$emit('close')">
        <template #title>
            Actualizar precio de <span class="text-blue-500">{{ product?.name }}</span>
        </template>
        <template #content>
            <div class="space-y-4 text-sm dark:text-gray-300 text-left">
                <p v-if="canBypassPriceRule" class="text-green-600 dark:text-green-400 text-xs mt-1 font-semibold p-2 bg-green-50 dark:bg-green-900/20 rounded-md">
                    <i class="fa-solid fa-unlock mr-1"></i> Tienes permisos especiales para asignar cualquier precio sin restricción.
                </p>
                <p v-else>
                    El precio de referencia actual es <strong class="font-semibold">${{ formatAmount(priceForm.current_base_price) }}</strong>.
                    El nuevo precio no puede tener un descuento mayor al 4% sobre la referencia.
                    Si solo deseas gestionar los precios por volumen, puedes dejar el precio nuevo vacío.
                </p>

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
                        <el-date-picker v-model="priceForm.valid_from" type="date" :teleported="false" placeholder="Selecciona una fecha" class="!w-full mt-1" />
                    </div>
                </div>

                <div v-if="priceForm.amount && isPriceInvalid && !canBypassPriceRule" class="text-red-500 text-xs mt-1 p-2 bg-red-50 dark:bg-red-900/40 rounded-md">
                    <i class="fa-solid fa-circle-exclamation mr-1"></i>
                    El precio debe ser mayor o igual a ${{ formatAmount(priceForm.min_allowed_price) }} (descuento máximo del 4%).
                </div>

                <VolumePricesEditor :key="priceModalKey" ref="volumePriceEditor" v-model="priceForm.volume_prices"
                    :currency="priceForm.currency" @change="volumePricesDirty = $event.dirty" />
            </div>
        </template>
        <template #footer>
            <div class="flex space-x-2">
                <CancelButton @click="$emit('close')">Cancelar</CancelButton>
                <PrimaryButton @click="submitNewPrice"
                    :disabled="!canSubmitPriceForm || saving"
                    class="!bg-blue-600 hover:!bg-blue-700 disabled:!bg-blue-300 dark:disabled:!bg-slate-600">
                    Actualizar precio
                </PrimaryButton>
            </div>
        </template>
    </ConfirmationModal>
</template>

<script>
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import CancelButton from '@/Components/MyComponents/CancelButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import VolumePricesEditor from '@/Components/MyComponents/VolumePricesEditor.vue';
import axios from 'axios';
import { ElMessage } from 'element-plus';

/**
 * Modal para actualizar el precio de referencia (especial) de un producto
 * para un cliente/grupo. Mismo comportamiento que en el drawer de productos
 * del cliente.
 */
export default {
    name: 'ClientPriceUpdateModal',
    components: {
        ConfirmationModal,
        CancelButton,
        PrimaryButton,
        VolumePricesEditor,
    },
    props: {
        show: { type: Boolean, default: false },
        branchId: { type: [Number, String], default: null },
        product: { type: Object, default: null },
    },
    emits: ['close', 'updated'],
    data() {
        return {
            saving: false,
            volumePricesDirty: false,
            priceModalKey: 0,
            priceForm: {
                amount: null,
                percentage: null,
                currency: 'MXN',
                valid_from: new Date(),
                current_base_price: 0,
                min_allowed_price: 0,
                volume_prices: [],
            },
        };
    },
    computed: {
        canBypassPriceRule() {
            // El backend valida este permiso para permitir cualquier precio.
            return this.$page.props.auth?.user?.permissions?.includes('Crear clientes') || false;
        },
        isPriceInvalid() {
            const amount = Number(this.priceForm.amount);

            if (!this.priceForm.amount || amount <= 0) return true;
            if (this.canBypassPriceRule) return false;

            return amount < Number(this.priceForm.min_allowed_price);
        },
        hasNewAmount() {
            return this.priceForm.amount !== null && this.priceForm.amount !== '';
        },
        canSubmitPriceForm() {
            if (this.hasNewAmount) return !this.isPriceInvalid;

            return this.volumePricesDirty;
        },
    },
    watch: {
        show: {
            immediate: true,
            handler(value) {
                if (value) this.initializeForm();
            },
        },
    },
    methods: {
        initializeForm() {
            const product = this.product;

            // Precio de referencia: el registro vigente (sin valid_to).
            const activePrice = product?.price_history?.find(h => !h.valid_to)?.price
                ?? product?.price_history?.[0]?.price;
            const basePrice = Number(activePrice ?? product?.base_price ?? 0);

            this.priceForm = {
                amount: null,
                percentage: null,
                currency: 'MXN',
                valid_from: new Date(),
                current_base_price: basePrice,
                // Mismo criterio que el backend: descuento máximo del 4% sobre la referencia.
                min_allowed_price: Number((basePrice * 0.96).toFixed(2)),
                volume_prices: (product?.volume_prices ?? []).map((tier) => ({
                    min_quantity: tier.min_quantity,
                    max_quantity: tier.max_quantity,
                    price: tier.price,
                    currency: tier.currency,
                })),
            };
            this.volumePricesDirty = false;
            this.priceModalKey++;
        },
        formatAmount(value) {
            const number = Number(value);
            return isNaN(number) ? '0.00' : number.toFixed(2);
        },
        updatePriceFromAmount() {
            const amount = Number(this.priceForm.amount);

            if (amount && this.priceForm.current_base_price > 0) {
                const percentage = ((amount / this.priceForm.current_base_price) - 1) * 100;
                this.priceForm.percentage = percentage.toFixed(2);
            } else {
                this.priceForm.percentage = null;
            }
        },
        updatePriceFromPercentage() {
            const percentage = Number(this.priceForm.percentage);

            if (this.priceForm.percentage !== null && this.priceForm.percentage !== '') {
                const newAmount = this.priceForm.current_base_price * (1 + (percentage / 100));
                this.priceForm.amount = newAmount.toFixed(2);
            } else {
                this.priceForm.amount = null;
            }
        },
        async submitNewPrice() {
            const volumePricesError = this.$refs.volumePriceEditor?.validate();
            if (volumePricesError) {
                ElMessage.error('Revisa los precios por volumen. ' + volumePricesError);
                return;
            }

            if (!this.hasNewAmount && !this.volumePricesDirty) {
                ElMessage.error('Ingresa un precio nuevo o modifica los precios especiales por volumen.');
                return;
            }

            if (this.hasNewAmount && this.isPriceInvalid) {
                ElMessage.error('El precio ingresado no es válido o es menor al permitido.');
                return;
            }

            this.saving = true;

            try {
                const { data } = await axios.post(
                    route('branches.products.price.store', { branch: this.branchId, product: this.product.id }),
                    {
                        ...this.priceForm,
                        amount: this.hasNewAmount ? this.priceForm.amount : null,
                        sync_volume_prices: this.volumePricesDirty,
                        volume_prices: this.volumePricesDirty ? this.priceForm.volume_prices : [],
                    }
                );

                ElMessage.success(data?.message || 'Precio actualizado correctamente.');
                this.$emit('updated');
                this.$emit('close');
            } catch (error) {
                const errors = error.response?.data?.errors;
                const firstError = errors ? Object.values(errors)[0]?.[0] : null;

                ElMessage.error(firstError || error.response?.data?.message || 'Ocurrió un error al guardar el precio.');
            } finally {
                this.saving = false;
            }
        },
    },
};
</script>
