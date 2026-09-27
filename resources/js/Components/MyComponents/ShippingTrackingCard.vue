<template>
    <!-- Card de Información de Envío (paquetería, guía, fecha promesa y tarifas sugeridas) -->
    <div v-if="sale?.shipments?.length || suggestedRates.length" class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
        <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4 flex items-center">
            <i class="fa-solid fa-truck-fast mr-2"></i> Información de Envío
        </h3>

        <div v-for="(shipment, index) in sale.shipments" :key="shipment.id"
            class="mb-4 last:mb-0 border-b dark:border-gray-700 last:border-0 pb-3 last:pb-0">
            <p class="text-xs font-bold text-gray-500 uppercase mb-2">Envío #{{ index + 1 }} - {{ shipment.status }}</p>
            <ul class="space-y-3 text-sm">
                <li class="flex justify-between items-center">
                    <span class="font-semibold text-gray-600 dark:text-gray-400">Paquetería:</span>
                    <span>{{ shipment.shipping_company ?? '-' }}</span>
                </li>
                <li class="flex justify-between items-center">
                    <span class="font-semibold text-gray-600 dark:text-gray-400">No. Guía:</span>
                    <div class="flex items-center space-x-2">
                        <span :class="shipment.tracking_guide ? 'font-mono bg-gray-100 dark:bg-gray-700 px-1 rounded' : ''">
                            {{ shipment.tracking_guide ?? '-' }}
                        </span>
                        <el-tooltip v-if="canEditTracking" content="Editar paquetería y guía" placement="top">
                            <button @click="openGuideModal(shipment)"
                                class="text-blue-500 hover:text-blue-700 text-xs p-1 rounded hover:bg-blue-100 dark:hover:bg-blue-900">
                                <i class="fa-solid fa-pencil"></i>
                            </button>
                        </el-tooltip>
                    </div>
                </li>
                <li class="flex justify-between">
                    <span class="font-semibold text-gray-600 dark:text-gray-400">Fecha promesa de embarque:</span>
                    <span>{{ formatDateOnly(shipment.promise_date) }}</span>
                </li>
            </ul>
        </div>

        <!-- Tarifas de envío sugeridas (mínimo número de cajas por familia) -->
        <SuggestedShippingRates :suggestions="suggestedRates" />

        <!-- Botón de Seguimiento -->
        <div v-if="showTrackingButton && sale?.shipments?.length" class="mt-4 pt-2 border-t dark:border-gray-600">
            <PrimaryButton @click="goToShipments"
                :disabled="!['Preparando Envío', 'Enviada'].includes(sale.status)"
                class="w-full justify-center !text-xs">
                Seguimiento de envío <i class="fa-solid fa-arrow-right ml-2"></i>
            </PrimaryButton>
        </div>

        <!-- Modal para registrar/editar la información de rastreo -->
        <DialogModal :show="showGuideModal" @close="showGuideModal = false">
            <template #title>
                Información de Rastreo
            </template>
            <template #content>
                <form @submit.prevent="submitGuide" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Paquetería</label>
                        <input v-model="guideForm.shipping_company" type="text"
                            class="w-full rounded-md border-gray-300 dark:bg-slate-800 text-sm"
                            placeholder="Ej. DHL, FedEx, PaqueteExpress..." />
                        <p v-if="guideForm.errors.shipping_company" class="text-red-500 text-[11px] mt-1">{{ guideForm.errors.shipping_company }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Número de Guía</label>
                        <input v-model="guideForm.tracking_guide" type="text"
                            class="w-full rounded-md border-gray-300 dark:bg-slate-800 text-sm"
                            placeholder="Ingresa el número de rastreo..." />
                        <p v-if="guideForm.errors.tracking_guide" class="text-red-500 text-[11px] mt-1">{{ guideForm.errors.tracking_guide }}</p>
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="showGuideModal = false">Cancelar</CancelButton>
                    <PrimaryButton @click="submitGuide" :disabled="guideForm.processing">
                        Guardar Información
                    </PrimaryButton>
                </div>
            </template>
        </DialogModal>
    </div>
</template>

<script>
import DialogModal from '@/Components/DialogModal.vue';
import CancelButton from '@/Components/MyComponents/CancelButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SuggestedShippingRates from '@/Components/MyComponents/SuggestedShippingRates.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

export default {
    name: 'ShippingTrackingCard',
    components: {
        DialogModal,
        CancelButton,
        PrimaryButton,
        SuggestedShippingRates,
    },
    props: {
        sale: {
            type: Object,
            required: true,
        },
        // Sugerencias de cajas calculadas para la orden (por familia)
        suggestedRates: {
            type: Array,
            default: () => [],
        },
        // Muestra el botón para ir al módulo de envíos
        showTrackingButton: {
            type: Boolean,
            default: false,
        },
        // Requiere el permiso "Editar información de envío" para editar paquetería/guía
        canEditTracking: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            showGuideModal: false,
            guideForm: useForm({
                shipment_id: null,
                shipping_company: '',
                tracking_guide: '',
            }),
        };
    },
    methods: {
        // Formatea una fecha (YYYY-MM-DD o ISO) sin desfase de zona horaria
        formatDateOnly(dateString) {
            if (!dateString) return '-';
            const [datePart] = String(dateString).split('T');
            const [y, m, d] = datePart.split('-').map(Number);
            if (!y || !m || !d) return dateString;
            return new Date(y, m - 1, d).toLocaleDateString('es-MX', {
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });
        },
        goToShipments() {
            router.visit(route('shipments.show', this.sale.id));
        },
        openGuideModal(shipment) {
            if (!this.canEditTracking) return;

            this.guideForm.clearErrors();
            this.guideForm.shipment_id = shipment.id;
            this.guideForm.shipping_company = shipment.shipping_company ?? '';
            this.guideForm.tracking_guide = shipment.tracking_guide ?? '';
            this.showGuideModal = true;
        },
        submitGuide() {
            if (!this.canEditTracking) return;

            this.guideForm.put(route('shipments.update-tracking', this.guideForm.shipment_id), {
                preserveScroll: true,
                onSuccess: () => {
                    this.showGuideModal = false;
                    ElMessage.success('Guía actualizada correctamente');
                },
                onError: () => {
                    ElMessage.error('Error al actualizar la guía.');
                },
            });
        },
    },
};
</script>
