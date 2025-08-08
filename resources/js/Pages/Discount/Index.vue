<template>
    <AppLayout title="Bonos">
        <!-- Encabezado de la página -->
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Gestión de Descuentos
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
                            Nuevo Descuento
                        </SecondaryButton>
                        
                        <!-- Botón de eliminación masiva -->
                        <el-popconfirm confirm-button-text="Sí, eliminar" cancel-button-text="No" icon-color="#EF4444"
                            title="¿Estás seguro de eliminar los bonos seleccionados?" @confirm="deleteSelections">
                            <template #reference>
                                <el-button type="danger" plain :disabled="!selectedItems.length">
                                    Eliminar seleccionados
                                </el-button>
                            </template>
                        </el-popconfirm>
                    </div>

                    <!-- Tabla de Bonos -->
                    <el-table
                        max-height="550"
                        :data="discounts.data" 
                        style="width: 100%" 
                        v-loading="loading" 
                        stripe
                        @selection-change="handleSelectionChange"
                        @row-click="handleRowClick"
                        class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300"
                    >
                        <el-table-column type="selection" width="45" />
                        <el-table-column prop="id" label="ID" width="80" sortable />
                        <el-table-column prop="name" label="Nombre" sortable />
                        <el-table-column prop="description" label="Descripción" />
                        <el-table-column prop="amount" label="Tiempo completo" align="right">
                            <template #default="scope">${{ formatCurrency(scope.row.amount) }}</template>
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
                    <div v-if="discounts.total > 0" class="flex justify-center mt-6">
                        <el-pagination
                            v-model:current-page="discounts.current_page"
                            :page-size="discounts.per_page"
                            :total="discounts.total"
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
                <form @submit.prevent="submit" class="grid grid-cols-2 gap-2">
                    <!-- Campo Nombre -->
                    <div class="col-span-2">
                        <TextInput v-model="form.name" label="Nombre del descuento*" :error="form.errors.name" class="w-full" />
                    </div>

                    <!-- Campo Descripción -->
                    <div class="col-span-2">
                        <TextInput v-model="form.description"
                            label="Descripción (opcional)" :error="form.errors.description"
                            class="w-full" :isTextarea="true" :withMaxLength="true" :maxLength="255" />
                    </div>

                    <!-- Campo monto -->
                    <div>
                        <TextInput type="number" step="0.01" label="Monto de descuento*" :error="form.errors.amount"
                            v-model="form.amount" required class="w-full" placeholder="$0.00" />
                    </div>

                    <!-- Switch de Estatus -->
                    <div class="col-span-2 flex items-center space-x-4">
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
import PrimaryButton from '@/Components/PrimaryButton.vue';
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
    components: {
        AppLayout,
        PrimaryButton,
        SecondaryButton,
        CancelButton,
        DialogModal,
        TextInput,
        InputLabel,
        InputError,
    },
    props: {
        discounts: Object,
    },
    data() {
        return {
            // Formulario de Inertia
            form: useForm({
                name: null,
                description: null,
                amount: null,
                is_active: true,
            }),

            // Estado del componente
            showModal: false,
            isEditing: false,
            loading: false,
            selectedItems: [],
        };
    },
    computed: {
        // Título dinámico para el modal
        modalTitle() {
            return this.isEditing ? 'Editar descuento' : 'Crear Nuevo descuento';
        }
    },
    methods: {
        // Abre el modal para crear un nuevo descuento
        openCreateModal() {
            this.isEditing = false;
            this.form.reset();
            this.form.is_active = true;
            this.showModal = true;
        },
        // Maneja el clic en una fila para editar
        handleRowClick(row) {
            this.isEditing = true;
            // Llena el formulario con los datos de la fila seleccionada
            this.form.id = row.id;
            this.form.name = row.name;
            this.form.description = row.description;
            this.form.amount = row.amount;
            this.form.is_active = !! row.is_active; // Convierte 1/0 a true/false
            this.showModal = true;
        },
        // Cierra el modal y resetea el formulario
        closeModal() {
            this.showModal = false;
            this.form.reset();
        },
        // Método único para enviar el formulario
        submit() {
            if (this.isEditing) {
                this.update();
            } else {
                this.store();
            }
        },
        // Envía la petición para crear un Descuento
        store() {
            this.form.post(route('discounts.store'), {
                onSuccess: () => {
                    ElMessage.success('Descuento creado correctamente');
                    this.closeModal();
                },
            });
        },
        // Envía la petición para actualizar un Descuento
        update() {
            this.form.put(route('discounts.update', this.form.id), {
                onSuccess: () => {
                    ElMessage.success('Descuento actualizado correctamente');
                    this.closeModal();
                },
            });
        },
        // Maneja la selección de filas en la tabla
        handleSelectionChange(selection) {
            this.selectedItems = selection;
        },
        // Elimina los Descuentos seleccionados
        deleteSelections() {
            const ids = this.selectedItems.map(item => item.id);
            this.$inertia.post(route('discounts.massive-delete'), { ids }, {
                onSuccess: () => {
                    ElMessage.success('Descuentos seleccionados eliminados');
                },
            });
        },
        // Maneja el cambio de página
        handlePageChange(page) {
            this.$inertia.get(route('discounts.index', { page }));
        },
        // Formatea un número a formato de moneda
        formatCurrency(value) {
            if (value == null) return '0.00';
            return Number(value).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        },
    }
}
</script>
