<template>
    <AppLayout title="Días Festivos">
        <!-- Encabezado de la página -->
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Gestión de Días Festivos
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
                            Nuevo Día Festivo
                        </SecondaryButton>
                        
                        <!-- Botón de eliminación masiva -->
                        <el-popconfirm confirm-button-text="Sí, eliminar" cancel-button-text="No" icon-color="#EF4444"
                            title="¿Estás seguro de eliminar los días festivos seleccionados?" @confirm="deleteSelections">
                            <template #reference>
                                <el-button type="danger" plain :disabled="!selectedItems.length">
                                    Eliminar seleccionados
                                </el-button>
                            </template>
                        </el-popconfirm>
                    </div>

                    <!-- Tabla de Días Festivos -->
                    <el-table
                        max-height="550"
                        :data="holidays.data" 
                        style="width: 100%" 
                        stripe
                        @selection-change="handleSelectionChange"
                        @row-click="handleRowClick"
                        class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300"
                    >
                        <el-table-column type="selection" width="45" />
                        <el-table-column prop="id" label="ID" width="80" sortable />
                        <el-table-column prop="name" label="Nombre" sortable />
                        <el-table-column prop="date" label="Fecha" sortable>
                        <template #default="scope">
                                <span class="text-gray-600 dark:text-gray-400">{{ formatDate(scope.row.date) }}</span>
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
                    <div v-if="holidays.total > 0" class="flex justify-center mt-6">
                        <el-pagination
                            v-model:current-page="holidays.current_page"
                            :page-size="holidays.per_page"
                            :total="holidays.total"
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
                        <TextInput v-model="form.name" label="Nombre de la festividad*" :error="form.errors.name" class="w-full" />
                    </div>

                    <!-- Selector de Día -->
                    <div>
                         <InputLabel value="Día*" />
                         <el-select v-model="form.day" clearable placeholder="Día" class="w-full" :teleported="false">
                            <el-option v-for="item in 31" :key="item" :label="item" :value="item" />
                        </el-select>
                        <InputError :message="form.errors.day" />
                    </div>

                    <!-- Selector de Mes -->
                    <div>
                        <InputLabel value="Mes*" />
                        <el-select v-model="form.month" clearable placeholder="Mes" class="w-full" :teleported="false">
                            <el-option v-for="(item, index) in months" :key="index" :label="item.label" :value="item.value" />
                        </el-select>
                        <InputError :message="form.errors.month" />
                    </div>

                    <!-- Switch de Estatus -->
                    <div class="col-span-2 flex items-center space-x-4 mt-3">
                        <el-switch v-model="form.is_active" active-text="Activo" inactive-text="Inactivo" />
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="flex space-x-3">
                    <CancelButton @click="closeModal" :disabled="form.processing">Cancelar</CancelButton>
                    <SecondaryButton @click="submit" :loading="form.processing">
                        {{ isEditing ? 'Actualizar' : 'Crear Día Festivo' }}
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
    name: 'HolidayIndex',
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
        holidays: Object,
    },
    data() {
        return {
            // Formulario de Inertia
            form: useForm({
                id: null,
                name: null,
                day: null,
                month: null,
                is_active: true,
            }),
            // Estado del componente
            showModal: false,
            isEditing: false,
            selectedItems: [],
            months: [
                { label: "Enero", value: "01" },
                { label: "Febrero", value: "02" },
                { label: "Marzo", value: "03" },
                { label: "Abril", value: "04" },
                { label: "Mayo", value: "05" },
                { label: "Junio", value: "06" },
                { label: "Julio", value: "07" },
                { label: "Agosto", value: "08" },
                { label: "Septiembre", value: "09" },
                { label: "Octubre", value: "10" },
                { label: "Noviembre", value: "11" },
                { label: "Diciembre", value: "12" },
            ],
        };
    },
    computed: {
        // Título dinámico para el modal
        modalTitle() {
            return this.isEditing ? 'Editar Día Festivo' : 'Crear Nuevo Día Festivo';
        }
    },
    methods: {
        // Abre el modal para crear un nuevo día festivo
        openCreateModal() {
            this.isEditing = false;
            this.form.reset();
            this.form.is_active = true;
            this.showModal = true;
        },
        // Formatea una fecha en un formato legible.
        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('es-MX', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });
        },
        // Maneja el clic en una fila para editar
        handleRowClick(row) {
            console.log(row) 
            // return;
            this.isEditing = true;
            // Llena el formulario con los datos de la fila seleccionada
            this.form.id = row.id;
            this.form.name = row.name;
            this.form.day = row.date.split('-')[2].split('T')[0];
            this.form.month = row.date.split('-')[1];
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
        // Envía la petición para crear un día festivo
        store() {
            this.form.post(route('holidays.store'), {
                onSuccess: () => {
                    ElMessage.success('Día festivo creado correctamente');
                    this.closeModal();
                },
            });
        },
        // Envía la petición para actualizar un día festivo
        update() {
            this.form.put(route('holidays.update', this.form.id), {
                onSuccess: () => {
                    ElMessage.success('Día festivo actualizado correctamente');
                    this.closeModal();
                },
            });
        },
        // Maneja la selección de filas en la tabla
        handleSelectionChange(selection) {
            this.selectedItems = selection;
        },
        // Elimina los días festivos seleccionados
        deleteSelections() {
            const ids = this.selectedItems.map(item => item.id);
            this.$inertia.post(route('holidays.massive-delete'), { ids }, {
                onSuccess: () => {
                    ElMessage.success('Días festivos seleccionados eliminados');
                },
            });
        },
        // Maneja el cambio de página
        handlePageChange(page) {
            this.$inertia.get(route('holidays.index', { page }));
        },
    }
}
</script>
