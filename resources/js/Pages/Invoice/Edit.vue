<template>
    <AppLayout title="Editar Factura">
        <div class="px-4 sm:px-0">
            <div class="flex items-center space-x-2">
                <Back :href="route('invoices.show', invoice.id)" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Editar Factura F-{{ invoice.folio }}
                </h2>
            </div>
        </div>

        <div ref="formContainer" class="py-7">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6 md:p-8">
                    
                    <form @submit.prevent="update" class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4">
                        
                        <!-- Selector de Orden de Venta -->
                        <div class="md:col-span-2">
                             <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Orden de Venta (OV)*</label>
                             <el-select-v2 v-model="form.sale_id"
                                filterable
                                :options="sales.map(item => ({ label: `OV-${item.id.toString().padStart(4, '0')}`, value: item.id }))"
                                placeholder="Seleccione una OV"
                                class="w-full"
                                size="large"
                                :disabled="true" /> <!-- Deshabilitado para no cambiar la OV de una factura existente -->
                            <InputError :message="form.errors.sale_id" />
                        </div>
                        
                        <!-- Cliente (Sucursal) -->
                        <div>
                           <TextInput label="Cliente (Sucursal)" v-model="clientName" type="text" :disabled="true" />
                        </div>

                        <!-- Total de Parcialidades (Cantidad de facturas) -->
                        <div>
                           <TextInput label="Total de facturas para esta OV*" v-model="form.total_installments" type="number" :min="1" :error="form.errors.total_installments" placeholder="Ej. 3" />
                           <p v-if="!form.sale_id" class="text-xs text-gray-500 ml-3 mt-1">Primero selecciona una OV</p>
                        </div>

                        <!-- Monto -->
                         <div>
                            <TextInput v-model="form.amount" :error="form.errors.amount" label="Monto de la factura*" placeholder="Se calculará automáticamente" :formatAsNumber="true" :disabled="form.total_installments < 1">
                                <template #icon-left>
                                    <i class="fa-solid fa-dollar-sign"></i>
                                </template>
                            </TextInput>
                        </div>

                        <!-- Folio -->
                        <TextInput label="Folio de la factura*" v-model="form.folio" type="text" :error="form.errors.folio" placeholder="Ej. F-12345" />

                        <!-- Número de Parcialidad -->
                        <TextInput label="Número de parcialidad*" v-model="form.installment_number" type="number" :min="1" :error="form.errors.installment_number" placeholder="Ej. 1" />

                        <!-- Fecha de Emisión -->
                        <div>
                            <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Fecha de emisión*</label>
                            <el-date-picker v-model="form.issue_date" type="date" placeholder="Selecciona una fecha" format="YYYY/MM/DD" value-format="YYYY-MM-DD" class="!w-full" size="large" />
                            <InputError :message="form.errors.issue_date" />
                        </div>

                        <!-- Fecha de Vencimiento -->
                        <div>
                            <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Fecha de vencimiento*</label>
                            <el-date-picker v-model="form.due_date" type="date" placeholder="Selecciona una fecha" format="YYYY/MM/DD" value-format="YYYY-MM-DD" class="!w-full" size="large" />
                            <InputError :message="form.errors.due_date" />
                        </div>

                        <!-- Moneda -->
                        <div>
                            <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Moneda*</label>
                            <el-select v-model="form.currency" placeholder="Seleccionar" class="!w-full" size="large">
                                <el-option v-for="item in currencies" :key="item" :label="item" :value="item" />
                            </el-select>
                            <InputError :message="form.errors.currency" />
                        </div>
                        
                        <!-- Opción de Pago (PUE/PPD) -->
                        <div>
                            <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Opción de pago*</label>
                            <el-select v-model="form.payment_option" placeholder="Seleccionar" class="!w-full" size="large">
                                <el-option v-for="item in paymentOptions" :key="item.label" :label="item.label" :value="item.label">
                                    <span>{{ item.label }}</span>
                                    <span class="text-xs text-gray-400 ml-2">{{ item.description }}</span>
                                </el-option>
                            </el-select>
                            <InputError :message="form.errors.payment_option" />
                        </div>
                        
                        <!-- Método de Pago -->
                        <div class="md:col-span-2">
                            <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Método de pago*</label>
                            <el-select v-model="form.payment_method" placeholder="Seleccionar" class="!w-full" size="large">
                                <el-option v-for="item in paymentMethods" :key="item" :label="item" :value="item" />
                            </el-select>
                            <InputError :message="form.errors.payment_method" />
                        </div>

                        <!-- Notas -->
                        <div class="md:col-span-2">
                            <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Notas</label>
                            <el-input v-model="form.notes" :rows="2" type="textarea" placeholder="Añade notas o comentarios adicionales..." />
                            <InputError :message="form.errors.notes" />
                        </div>

                        <div class="col-span-full mt-5">
                            <label class="text-gray-700 dark:text-gray-100 text-sm ml-3" for="">Adjuntar nuevos archivos (max. 3)</label>
                            <FileUploader @files-selected="form.media = $event" :multiple="true" :max-files="3" />
                            <InputError :message="form.errors.media" class="mt-2" />
                        </div>
                        
                        <!-- Botón de Guardar -->
                        <div class="flex justify-end mt-5 col-span-full">
                            <SecondaryButton :loading="form.processing" :disabled="form.processing">
                                Guardar Cambios
                            </SecondaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import InputError from "@/Components/InputError.vue";
import TextInput from "@/Components/TextInput.vue";
import Back from "@/Components/MyComponents/Back.vue";
import { ElMessage } from 'element-plus';
import { useForm } from "@inertiajs/vue3";
import { watch, computed } from 'vue';

export default {
    components: {
        AppLayout,
        SecondaryButton,
        FileUploader,
        InputError,
        TextInput,
        Back,
    },
    props: {
        invoice: Object,
        sales: Array,
    },
    setup(props) {
        const form = useForm({
            ...props.invoice,
            media: [], // El campo 'media' es para subir nuevos archivos, no para mostrar los existentes.
        });

        const currencies = ['MXN', 'USD'];
        const paymentOptions = [
            { label: 'PUE', description: 'Pago en una sola exhibición' },
            { label: 'PPD', description: 'Pago en parcialidades o diferido' },
        ];
        const paymentMethods = ['Efectivo', 'Transferencia electrónica de fondos', 'Tarjeta de crédito', 'Tarjeta de débito', 'Por definir'];

        const selectedSale = computed(() => {
            if (!form.sale_id) return null;
            return props.sales.find(s => s.id === form.sale_id);
        });

        const clientName = computed(() => {
            return selectedSale.value?.branch?.name ?? 'N/A';
        });

        const handleInstallmentsChange = () => {
            if (selectedSale.value && form.total_installments > 0) {
                const amountPerInstallment = selectedSale.value.total_amount / form.total_installments;
                form.amount = parseFloat(amountPerInstallment.toFixed(2));
            } else {
                form.amount = null;
            }
        };

        watch(() => form.total_installments, handleInstallmentsChange);

        return { form, currencies, paymentOptions, paymentMethods, clientName };
    },
    methods: {
        update() {
            // El helper 'form' de Inertia usa POST con un campo _method para PUT/PATCH/DELETE.
            this.form.post(route("invoices.update", this.invoice.id), {
                _method: 'put',
                onSuccess: () => {
                    ElMessage.success('Factura actualizada correctamente');
                },
                onError: (errors) => {
                    this.$refs.formContainer.scrollIntoView({ behavior: 'smooth' });
                    ElMessage.error('Por favor, revisa los errores en el formulario');
                    console.log(errors);
                }
            });
        },
    }
};
</script>
