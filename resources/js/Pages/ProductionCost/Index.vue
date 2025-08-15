<template>
    <AppLayout title="Costos de Producción">
        <!-- Encabezado de la página -->
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Gestión de Costos de Producción
        </h2>

        <!-- Contenido principal -->
        <div class="py-7">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Tarjeta contenedora -->
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <!-- Botón para abrir el modal de creación -->
                        <SecondaryButton @click="openCreateModal">
                            <i class="fa-solid fa-plus mr-2"></i>
                            Nuevo Costo
                        </SecondaryButton>
                        
                        <div class="flex items-center space-x-4">
                            <!-- Botón de eliminación masiva -->
                            <el-popconfirm confirm-button-text="Sí, eliminar" cancel-button-text="No" icon-color="#EF4444"
                                title="¿Estás seguro de eliminar los costos seleccionados?" @confirm="deleteSelections">
                                <template #reference>
                                    <el-button type="danger" plain :disabled="!selectedItems.length">
                                        Eliminar seleccionados
                                    </el-button>
                                </template>
                            </el-popconfirm>
                        </div>
                    </div>

                    <!-- Tabla de Costos de Producción -->
                    <el-table
                        max-height="550"
                        :data="production_costs.data" 
                        style="width: 100%" 
                        stripe
                        @selection-change="handleSelectionChange"
                        @row-click="handleRowClick"
                        class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300"
                    >
                        <el-table-column type="selection" width="45" />
                        <el-table-column prop="id" label="ID" width="80" sortable />
                        <el-table-column prop="name" label="Nombre" sortable />
                        <el-table-column prop="cost_type" label="Tipo de costo" width="150">
                             <template #default="scope">
                                <span class="capitalize">{{ scope.row.cost_type.replace('_', ' ') }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="cost" label="Costo" align="right" width="120">
                            <template #default="scope">${{ formatCurrency(scope.row.cost) }}</template>
                        </el-table-column>
                        <el-table-column label="Tiempo estimado" align="center" width="160">
                            <template #default="scope">
                                <span>{{ formatTime(scope.row.estimated_time_seconds) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="Estatus" width="120" align="center">
                            <template #default="scope">
                                <el-tag :type="scope.row.is_active ? 'success' : 'danger'" disable-transitions>
                                    {{ scope.row.is_active ? 'Activo' : 'Inactivo' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                    </el-table>

                    <!-- Paginación -->
                    <div v-if="production_costs.total > 0" class="flex justify-center mt-6">
                        <el-pagination
                            v-model:current-page="production_costs.current_page"
                            :page-size="production_costs.per_page"
                            :total="production_costs.total"
                            layout="prev, pager, next"
                            background
                            @current-change="handlePageChange"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para Crear y Editar -->
        <DialogModal :show="showModal" @close="closeModal">
            <template #title>{{ modalTitle }}</template>
            <template #content>
                <form @submit.prevent="submit" class="grid grid-cols-2 gap-4">
                    <!-- Campo Nombre -->
                    <div class="col-span-2">
                        <TextInput v-model="form.name" label="Nombre del proceso*" :error="form.errors.name" class="w-full" />
                    </div>

                    <!-- Campo Descripción -->
                    <div class="col-span-2">
                        <TextInput v-model="form.description"
                            label="Descripción (opcional)" :error="form.errors.description"
                            class="w-full" :isTextarea="true" />
                    </div>

                    <!-- Campo Tipo de Costo -->
                    <div>
                        <InputLabel value="Tipo de costo*" />
                        <el-select v-model="form.cost_type" placeholder="Selecciona" class="w-full" :teleported="false">
                            <el-option label="Por hora" value="Hora" />
                            <el-option label="Por unidad" value="Pieza" />
                        </el-select>
                        <InputError :message="form.errors.cost_type" />
                    </div>

                    <!-- Campo Costo -->
                    <div>
                        <TextInput type="number" label="Costo*" :error="form.errors.cost"
                            v-model="form.cost" required class="w-full" placeholder="$0.00" />
                    </div>

                    <!-- Campo Tiempo Estimado -->
                    <div>
                         <TextInput type="number" label="Tiempo estimado (segundos)" :error="form.errors.estimated_time_seconds"
                            v-model="form.estimated_time_seconds" class="w-full" placeholder="Ej. 60" />
                    </div>

                    <!-- Switch de Estatus -->
                    <div class="col-span-2 flex items-center space-x-4 pt-3">
                        <el-switch v-model="form.is_active" active-text="Activo" inactive-text="Inactivo" />
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="flex space-x-3">
                    <CancelButton @click="closeModal" :disabled="form.processing">Cancelar</CancelButton>
                    <SecondaryButton @click="submit" :loading="form.processing">
                        {{ isEditing ? 'Actualizar' : 'Crear Costo' }}
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
import { useForm, router } from "@inertiajs/vue3";
import { ElMessage } from 'element-plus';

export default {
    name: 'ProductionCostIndex',
    components: {
        AppLayout,
        SecondaryButton,
        CancelButton,
        DialogModal,
        TextInput,
        InputLabel,
        InputError,
    },
    props: {
        production_costs: Object,
    },
    data() {
        return {
            form: useForm({
                id: null,
                name: null,
                description: null,
                cost_type: 'Pieza',
                cost: null,
                estimated_time_seconds: null,
                is_active: true,
            }),
            showModal: false,
            isEditing: false,
            selectedItems: [],
        };
    },
    computed: {
        modalTitle() {
            return this.isEditing ? 'Editar Costo de Producción' : 'Crear Nuevo Costo de Producción';
        }
    },
    methods: {
        openCreateModal() {
            this.isEditing = false;
            this.form.reset();
            this.form.is_active = true;
            this.showModal = true;
        },
        handleRowClick(row) {
            this.isEditing = true;
            this.form.id = row.id;
            this.form.name = row.name;
            this.form.description = row.description;
            this.form.cost_type = row.cost_type;
            this.form.cost = row.cost;
            this.form.estimated_time_seconds = row.estimated_time_seconds;
            this.form.is_active = !! row.is_active;
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
            this.form.reset();
        },
        submit() {
            if (this.isEditing) {
                this.update();
            } else {
                this.store();
            }
        },
        store() {
            this.form.post(route('production-costs.store'), {
                onSuccess: () => {
                    ElMessage.success('Costo creado correctamente');
                    this.closeModal();
                },
            });
        },
        update() {
            this.form.put(route('production-costs.update', this.form.id), {
                onSuccess: () => {
                    ElMessage.success('Costo actualizado correctamente');
                    this.closeModal();
                },
            });
        },
        handleSelectionChange(selection) {
            this.selectedItems = selection;
        },
        deleteSelections() {
            const ids = this.selectedItems.map(item => item.id);
            router.post(route('production-costs.massive-delete'), { ids }, {
                onSuccess: () => {
                    ElMessage.success('Costos seleccionados eliminados');
                },
            });
        },
        formatCurrency(value) {
            if (value == null) return '0.00';
            return Number(value).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        },
        formatTime(totalSeconds) {
            if (totalSeconds === null || totalSeconds === undefined) return 'N/A';
            if (totalSeconds < 60) {
                return `${totalSeconds} seg`;
            }
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;
            if (seconds === 0) {
                return `${minutes} min`;
            }
            return `${minutes} min ${seconds} seg`;
        },
    }
}
</script>
