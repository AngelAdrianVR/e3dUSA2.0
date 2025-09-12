<template>
    <AppLayout title="Bonos">
        <!-- Encabezado de la página -->
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Gestión de Bonos
        </h2>

        <!-- Contenido principal -->
        <div class="py-7">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <SecondaryButton v-if="hasPermission('Crear bonos')" @click="openCreateModal">
                            <i class="fa-solid fa-plus mr-2"></i>
                            Nuevo Bono
                        </SecondaryButton>
                        <el-popconfirm v-if="hasPermission('Eliminar bonos')" confirm-button-text="Sí, eliminar" cancel-button-text="No" icon-color="#EF4444"
                            title="¿Estás seguro de eliminar los bonos seleccionados?" @confirm="deleteSelections">
                            <template #reference>
                                <el-button type="danger" plain :disabled="!selectedItems.length">
                                    Eliminar seleccionados
                                </el-button>
                            </template>
                        </el-popconfirm>
                    </div>

                    <el-table max-height="550" :data="bonuses.data" style="width: 100%" stripe
                        @selection-change="handleSelectionChange" @row-click="handleRowClick"
                        class="dark:!bg-slate-900 dark:!text-gray-300" :class="hasPermission('Editar bonos') ? 'cursor-pointer' : null">
                        <el-table-column type="selection" width="45" />
                        <el-table-column prop="id" label="ID" width="80" sortable />
                        <el-table-column prop="name" label="Nombre" sortable />
                        <el-table-column prop="rules" label="Reglas Aplicadas">
                            <template #default="scope">
                                <div v-if="scope.row.rules && scope.row.rules.length" class="flex flex-col space-y-1">
                                    <el-tag v-for="rule in scope.row.rules" :key="rule.id" size="small" effect="plain">
                                        {{ formatRule(rule) }}
                                    </el-tag>
                                </div>
                                <span v-else class="text-gray-400">Bono Directo</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="full_time" label="T. completo" align="right">
                            <template #default="scope">${{ formatCurrency(scope.row.full_time) }}</template>
                        </el-table-column>
                        <el-table-column prop="half_time" label="M. tiempo" align="right">
                            <template #default="scope">${{ formatCurrency(scope.row.half_time) }}</template>
                        </el-table-column>
                    </el-table>
                    <div v-if="bonuses.total > 0" class="flex justify-center mt-6">
                        <el-pagination v-model:current-page="bonuses.current_page" :page-size="bonuses.per_page"
                            :total="bonuses.total" layout="prev, pager, next" background
                            @current-change="handlePageChange" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para Crear y Editar -->
        <DialogModal :show="showModal" @close="closeModal" max-width="3xl">
            <template #title>{{ modalTitle }}</template>
            <template #content>
                <form @submit.prevent="submit" class="grid grid-cols-2 gap-x-6 gap-y-4">
                    <div class="col-span-2">
                        <TextInput v-model="form.name" label="Nombre del bono*" :error="form.errors.name" class="w-full" />
                    </div>
                    <div class="col-span-2">
                        <TextInput v-model="form.description" label="Descripción (opcional)" :error="form.errors.description" class="w-full" :isTextarea="true" />
                    </div>
                    <div>
                        <TextInput type="number" step="0.01" label="Monto para tiempo completo*" :error="form.errors.full_time" v-model="form.full_time" required class="w-full" placeholder="$0.00" />
                    </div>
                    <div>
                        <TextInput type="number" step="0.01" label="Monto para medio tiempo*" :error="form.errors.half_time" v-model="form.half_time" required class="w-full" placeholder="$0.00" />
                    </div>
                    <div class="col-span-2">
                        <el-divider>Motor de Reglas</el-divider>
                        <div>
                            <InputLabel value="Tipo de Cálculo*" />
                            <el-radio-group v-model="form.calculation_type" @change="handleCalcTypeChange">
                                <el-radio-button label="all_or_nothing_weekly">Semanal (Todo o Nada)</el-radio-button>
                                <el-radio-button label="proportional_by_day">Diario (Proporcional)</el-radio-button>
                            </el-radio-group>
                            <p class="text-xs text-gray-500 mt-1">Define si las reglas se evalúan al final de la semana o día por día.</p>
                        </div>
                        <div class="mt-4">
                            <InputLabel value="Condiciones (el bono se otorga si se cumplen TODAS)" />
                            <div v-if="!form.rules.length" class="text-center text-sm text-gray-500 bg-gray-100 dark:bg-slate-800 p-4 rounded-lg mt-2">
                                <p>Sin condiciones, este bono se aplicará directamente.</p>
                            </div>
                            <div class="space-y-2 mt-2">
                                <div v-for="(rule, index) in form.rules" :key="index" class="grid grid-cols-12 gap-2 items-center">
                                    <span class="col-span-1 text-center font-bold dark:text-gray-300">Si</span>
                                    <el-select v-model="rule.metric" placeholder="Métrica" class="col-span-4">
                                        <el-option v-for="metric in filteredMetrics" :key="metric.value" :label="metric.label" :value="metric.value" />
                                    </el-select>
                                    <el-select v-model="rule.operator" placeholder="Operador" class="col-span-3">
                                        <el-option v-for="operator in rule_definitions.operators" :key="operator.value" :label="operator.label" :value="operator.value" />
                                    </el-select>
                                    
                                    <!-- **NUEVA LÓGICA PARA VALOR DINÁMICO** -->
                                    <div class="col-span-3">
                                        <el-input v-model="rule.value" type="text" placeholder="Valor" :disabled="rule.is_dynamic" />
                                        <div v-if="metricSupportsDynamic(rule.metric)" class="mt-1">
                                            <el-checkbox v-model="rule.is_dynamic" @change="toggleDynamicValue(rule)">
                                                <span class="text-xs">Usar horas del empleado</span>
                                            </el-checkbox>
                                        </div>
                                    </div>

                                    <el-button @click="removeRule(index)" type="danger" plain circle class="col-span-1">
                                        <i class="fa-solid fa-xmark"></i>
                                    </el-button>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <el-button @click="addRule" type="primary" plain class="w-full">Añadir Condición</el-button>
                        </div>
                    </div>
                    <div class="col-span-2 flex items-center space-x-4 mt-3">
                        <el-switch v-model="form.is_active" active-text="Activo" inactive-text="Inactivo" />
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="flex space-x-3">
                    <CancelButton @click="closeModal" :disabled="form.processing">Cancelar</CancelButton>
                    <SecondaryButton @click="submit" :loading="form.processing">
                        {{ isEditing ? 'Actualizar' : 'Crear Bono' }}
                    </SecondaryButton>
                </div>
            </template>
        </DialogModal>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import CancelButton from '@/Components/MyComponents/CancelButton.vue';
import DialogModal from '@/Components/DialogModal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { useForm } from "@inertiajs/vue3";
import { ElMessage } from 'element-plus';

export default {
    name: 'BonusIndex',
    components: { AppLayout, SecondaryButton, CancelButton, DialogModal, TextInput, InputLabel, InputError },
    props: {
        bonuses: Object,
        rule_definitions: Object,
    },
    data() {
        return {
            form: useForm({
                id: null,
                name: null,
                description: null,
                calculation_type: 'all_or_nothing_weekly',
                rules: [],
                full_time: null,
                half_time: null,
                is_active: true,
            }),
            showModal: false,
            isEditing: false,
            selectedItems: [],
        };
    },
    computed: {
        modalTitle() {
            return this.isEditing ? 'Editar Bono' : 'Crear Nuevo Bono';
        },
        filteredMetrics() {
            return this.rule_definitions.metrics.filter(metric => 
                metric.scope === this.form.calculation_type
            );
        }
    },
    methods: {
        hasPermission(permission) {
            const permissions = this.$page.props.auth.user.permissions;
            return permissions.includes(permission);
        },
        openCreateModal() {
            this.isEditing = false;
            this.form.reset();
            this.showModal = true;
        },
        handleRowClick(row) {
            if (!this.hasPermission('Editar bonos')) return;
            this.isEditing = true;
            // Pre-procesar las reglas para configurar la UI del valor dinámico
            const rules = (row.rules || []).map(rule => ({
                ...rule,
                is_dynamic: rule.value === 'employee_scheduled_hours'
            }));
            const formData = { ...row, rules, is_active: !!row.is_active };
            this.form.defaults(formData).reset();
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
        },
        submit() {
            if (this.isEditing) this.update();
            else this.store();
        },
        store() {
            this.form.post(route('bonuses.store'), {
                onSuccess: () => {
                    ElMessage.success('Bono creado correctamente');
                    this.closeModal();
                },
            });
        },
        update() {
            this.form.put(route('bonuses.update', this.form.id), {
                onSuccess: () => {
                    ElMessage.success('Bono actualizado correctamente');
                    this.closeModal();
                },
            });
        },
        handleSelectionChange(selection) {
            this.selectedItems = selection;
        },
        deleteSelections() {
            const ids = this.selectedItems.map(item => item.id);
            this.$inertia.post(route('bonuses.massive-delete'), { ids }, {
                onSuccess: () => ElMessage.success('Bonos seleccionados eliminados'),
            });
        },
        handlePageChange(page) {
            this.$inertia.get(route('bonuses.index', { page }));
        },
        formatCurrency(value) {
            if (value == null) return '0.00';
            return Number(value).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        },
        addRule() {
            // is_dynamic se añade para el control de la UI
            this.form.rules.push({ metric: '', operator: '', value: '', is_dynamic: false });
        },
        removeRule(index) {
            this.form.rules.splice(index, 1);
        },
        handleCalcTypeChange() {
            this.form.rules = [];
        },
        formatRule(rule) {
            const metric = this.rule_definitions.metrics.find(m => m.value === rule.metric)?.label || rule.metric;
            const value = rule.value === 'employee_scheduled_hours' ? 'sus horas programadas' : rule.value;
            return `Si ${metric} ${rule.operator} ${value}`;
        },
        // **NUEVOS MÉTODOS PARA VALOR DINÁMICO**
        metricSupportsDynamic(metricValue) {
            return this.rule_definitions.metrics.find(m => m.value === metricValue)?.accepts_dynamic ?? false;
        },
        toggleDynamicValue(rule) {
            if (rule.is_dynamic) {
                rule.value = 'employee_scheduled_hours';
            } else {
                rule.value = ''; // Resetear a valor manual
            }
        }
    }
}
</script>