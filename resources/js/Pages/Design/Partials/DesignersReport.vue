<template>
    <el-dialog v-model="showModal" title="Generar Reporte de Actividades" width="500px" @close="close">
        <div class="space-y-6">
            <div>
                <label for="report-month" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Selecciona el Mes
                </label>
                <el-date-picker
                    v-model="form.month"
                    id="report-month"
                    type="month"
                    placeholder="Selecciona un mes"
                    format="MMMM, YYYY"
                    value-format="YYYY-MM"
                    class="!w-full"
                    :disabled-date="disabledDate"
                />
                <p v-if="form.errors.month" class="text-red-500 text-xs mt-1">{{ form.errors.month }}</p>
            </div>
            <div>
                <label for="designers" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Selecciona Diseñador(es)
                </label>
                <el-select
                    v-model="form.designers"
                    id="designers"
                    multiple
                    filterable
                    placeholder="Selecciona uno o más diseñadores"
                    class="!w-full"
                    :teleported="false"
                >
                    <el-option v-for="designer in designers" :key="designer.id" :label="designer.name" :value="designer.id" />
                </el-select>
                 <p v-if="form.errors.designers" class="text-red-500 text-xs mt-1">{{ form.errors.designers }}</p>
            </div>
        </div>
        <template #footer>
            <span class="dialog-footer">
                <CancelButton @click="close">Cancelar</CancelButton>
                <SecondaryButton @click="generateReport" :loading="form.processing" class="ml-3">
                    <span v-if="form.processing">Generando...</span>
                    <span v-else>Generar Reporte</span>
                </SecondaryButton>
            </span>
        </template>
    </el-dialog>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import SecondaryButton from "@/Components/SecondaryButton.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import axios from 'axios';

export default {
    name: 'DesignersReportModal',
    components: {
        SecondaryButton,
        CancelButton,
    },
    props: {
        show: Boolean,
    },
    emits: ['close'],
    data() {
        return {
            form: useForm({
                month: null,
                designers: [],
            }),
            designers: [],
            showModal: this.show,
        };
    },
    methods: {
        close() {
            this.form.reset();
            this.$emit('close');
        },
        async fetchDesigners() {
            try {
                const response = await axios.get(route('design-orders.get-designers'));
                this.designers = response.data;
            } catch (error) {
                console.error("Error fetching designers:", error);
                ElMessage.error('No se pudo cargar la lista de diseñadores.');
            }
        },
        generateReport() {
            // Validaciones manuales
            let errors = {};
            if (!this.form.month) {
                errors.month = 'Debes seleccionar un mes.';
            }
            if (this.form.designers.length === 0) {
                errors.designers = 'Debes seleccionar al menos un diseñador.';
            }

            if (Object.keys(errors).length > 0) {
                this.form.setError(errors);
                return;
            }
            
            this.form.clearErrors();

            const reportUrl = route('design-orders.reports.designers-activity', {
                month: this.form.month,
                designers: this.form.designers,
            });

            window.open(reportUrl, '_blank');
            this.close();
        },
        disabledDate(time) {
            return time.getTime() > Date.now();
        }
    },
    watch: {
        show(newVal) {
            this.showModal = newVal;
            if (newVal) {
                this.fetchDesigners();
            }
        }
    }
}
</script>
