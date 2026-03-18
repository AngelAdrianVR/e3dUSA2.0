<template>
    <AppLayout title="Editar Orden de Producción">
        <div class="flex justify-between items-center px-4 sm:px-0">
            <Back :href="route('productions.index')" />
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Editar Órdenes de Producción <span class="text-primary ml-2">OV-{{ sale.id.toString().padStart(4, '0') }}</span>
            </h2>
        </div>

        <div class="py-7">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300">
                            <i class="fa-solid fa-boxes-stacked mr-2"></i>
                            Revisa los productos y ajusta las tareas
                        </h3>
                        <p class="text-sm text-gray-500 mt-1">Si hubo cambios en la orden de venta (ej. la cantidad), aquí puedes agregar o quitar tareas correspondientes a la producción.</p>
                    </div>

                    <!-- Lista de Productos a Producir -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 max-h-[60vh] overflow-auto pr-2">
                        <div v-for="product in sale.sale_products" :key="product.id" class="bg-gray-50 dark:bg-slate-800 border dark:border-gray-700 rounded-lg p-4 flex flex-col justify-between">
                            <div class="flex items-start space-x-4">
                                <img draggable="false" :src="getProductImage(product.product)" :alt="product.product.name" class="size-24 rounded object-cover">
                                <div class="flex-1">
                                    <h4 class="font-bold text-lg text-gray-800 dark:text-gray-200">{{ product.product.name }}</h4>
                                    <p class="text-sm text-gray-500">Cantidad Solicitada: <span class="font-semibold">{{ product.quantity }} {{ product.product.measure_unit }}</span></p>
                                    <div class="flex items-center space-x-2">
                                        <p class="text-sm text-amber-500">A Producir (descontando de stock): <span class="font-semibold">{{ product.quantity_to_produce }} {{ product.product.measure_unit }}</span></p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3" v-if="product.product.components && product.product.components.length">
                                <h5 class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400 mb-1">Componentes</h5>
                                <ul class="text-xs space-y-1">
                                    <li v-for="component in product.product.components" :key="component.id" class="flex justify-between items-center">
                                        <div class="flex items-center space-x-2">
                                            <img draggable="false" :src="getProductImage(component)" :alt="component.name" class="size-8 rounded object-cover">
                                            <span class="dark:text-gray-100">{{ component.name }}</span>
                                        </div>
                                        <span class="px-2 py-0.5 rounded-full" :class="getTotalStock(component) > 0 ? 'bg-green-200 text-green-900' : 'bg-red-200 text-red-900'">
                                            Stock después de producirlo: {{ formatNumber(getTotalStock(component)) }} {{ component.measure_unit }}
                                        </span>
                                    </li>
                                </ul>
                            </div>

                            <div class="mt-4 flex justify-between items-center">
                                 <el-tag :type="getTasksForProduct(product.id).length ? 'success' : 'warning'">
                                    <i class="fa-solid fa-list-check mr-2"></i>
                                    {{ getTasksForProduct(product.id).length }} Tareas Asignadas
                                </el-tag>
                                <PrimaryButton @click="openTaskModal(product)">
                                    <i class="fa-solid fa-pen-to-square mr-2"></i>
                                    Editar Tareas
                                </PrimaryButton>
                            </div>
                        </div>
                    </div>

                    <!-- Boton Guardar -->
                    <div class="flex justify-end mt-7 border-t dark:border-gray-700 pt-5">
                        <SecondaryButton @click="update" :loading="form.processing">
                            Guardar Cambios <i class="fa-solid fa-floppy-disk ml-2"></i>
                        </SecondaryButton>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de Asignación de Tareas (Reutilizado de Create) -->
        <DialogModal :show="isTaskModalOpen" @close="isTaskModalOpen = false">
            <template #title>Tareas para: <span class="text-primary">{{ currentProduct?.product.name }}</span></template>
            <template #content>
                <div class="p-4 border rounded-lg dark:border-gray-700 space-y-4 bg-gray-50 dark:bg-slate-800/50">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Agregar Nueva Tarea</h4>
                    
                    <div class="flex items-center space-x-3">
                        <el-switch v-model="newTask.is_free_task" active-text="Tarea Libre" inactive-text="Proceso Estándar" />
                    </div>

                    <div v-if="!newTask.is_free_task">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Proceso*</label>
                        <el-select v-model="newTask.production_cost_id" filterable :teleported="false" placeholder="Selecciona un proceso" class="w-full" @change="handleProcessSelection">
                            <el-option v-for="process in productionCosts" :key="process.id" :label="process.name" :value="process.id" />
                        </el-select>
                    </div>

                    <div v-if="newTask.is_free_task">
                        <TextInput
                            v-model="newTask.name"
                            label="Nombre de la Tarea*"
                            placeholder="Ej. Empaque, Corte Especial"
                        />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Operador*</label>
                        <el-select v-model="newTask.operator_id" :teleported="false" placeholder="Selecciona un operador" class="w-full" filterable>
                            <el-option v-for="operator in operators" :key="operator.id" :label="operator.name" :value="operator.id" />
                        </el-select>
                    </div>

                     <div>
                        <TextInput
                            v-model="newTask.estimated_time_minutes"
                            label="Tiempo Estimado (minutos)*"
                            :disabled="!newTask.is_free_task && newTask.production_cost_id !== null"
                            type="number"
                        />
                    </div>

                     <div class="text-right mt-2">
                        <SecondaryButton @click="addTask" :disabled="!isNewTaskValid()">Agregar Tarea</SecondaryButton>
                    </div>
                </div>

                <h4 class="text-md font-semibold text-gray-600 dark:text-gray-400 mt-6 mb-2">Tareas Actuales:</h4>
                <p v-if="!tasksForCurrentProduct.length" class="text-sm text-center text-gray-500 py-4">Aún no hay tareas asignadas para este producto.</p>
                <ul v-else class="space-y-2 max-h-[30vh] overflow-y-auto pr-2">
                    <li v-for="(task, index) in tasksForCurrentProduct" :key="index" class="flex justify-between items-center bg-gray-100 dark:bg-slate-800 p-3 rounded-lg border dark:border-gray-700">
                        <div class="flex-1">
                           <p class="text-sm dark:text-gray-300 font-bold mb-1">{{ task.name }}</p>
                           <p class="text-xs text-gray-500"><i class="fa-solid fa-user-gear mr-1 text-gray-400"></i> {{ getOperatorName(task.operator_id) }}</p>
                           <p class="text-xs text-gray-500 mt-1">Tiempo Estimado: <span class="text-blue-500 font-semibold">{{ task.estimated_time_minutes }} min</span></p>
                           <!-- Etiqueta si la tarea ya empezó o finalizó -->
                           <span v-if="task.status && task.status !== 'Pendiente'" class="inline-block mt-1 px-2 py-0.5 bg-blue-100 text-blue-800 text-[10px] rounded-full">{{ task.status }}</span>
                        </div>
                        <div class="ml-4">
                            <button v-if="!task.status || task.status === 'Pendiente'" @click="removeTask(index)" class="text-red-500 hover:text-red-700 transition" title="Eliminar tarea">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                            <span v-else class="text-xs text-gray-400" title="No se puede eliminar porque ya está en proceso o terminada">
                                <i class="fa-solid fa-lock"></i>
                            </span>
                        </div>
                    </li>
                </ul>
            </template>
            <template #footer>
                <PrimaryButton @click="isTaskModalOpen = false">Cerrar</PrimaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import Back from "@/Components/MyComponents/Back.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import DialogModal from "@/Components/DialogModal.vue";
import { useForm } from "@inertiajs/vue3";
import { ElMessage } from 'element-plus';

export default {
    data() {
        return {
            isTaskModalOpen: false,
            currentProduct: null,
            
            newTask: {
                is_free_task: true,
                production_cost_id: null,
                operator_id: null,
                name: '',
                estimated_time_minutes: null,
            },
            
            form: useForm({
                products_with_tasks: []
            }),
        };
    },
    components: { AppLayout, SecondaryButton, PrimaryButton, DialogModal, TextInput, Back },
    props: {
        sale: Object, 
        operators: Array,
        productionCosts: Array,
    },
    computed: {
        tasksForCurrentProduct() {
            if (!this.currentProduct) return [];
            const product = this.form.products_with_tasks.find(p => p.sale_product_id === this.currentProduct.id);
            return product ? product.tasks : [];
        }
    },
    mounted() {
        // Inicializar el formulario con las producciones y tareas existentes
        this.form.products_with_tasks = this.sale.sale_products.map(sp => {
            return {
                sale_product_id: sp.id,
                tasks: sp.production ? sp.production.tasks.map(t => ({
                    id: t.id,
                    operator_id: t.operator_id,
                    name: t.name,
                    estimated_time_minutes: t.estimated_time_minutes,
                    status: t.status
                })) : []
            };
        });
    },
    methods: {
        // --- Métodos de Asignación de Tareas ---
        openTaskModal(product) {
            this.currentProduct = product;
            
            // Si el producto fue borrado del form (poco probable por la inicialización, pero por si acaso)
            if (!this.form.products_with_tasks.some(p => p.sale_product_id === product.id)) {
                this.form.products_with_tasks.push({
                    sale_product_id: product.id,
                    tasks: []
                });
            }
            this.resetNewTaskForm();
            this.isTaskModalOpen = true;
        },
        getTasksForProduct(saleProductId) {
            const product = this.form.products_with_tasks.find(p => p.sale_product_id === saleProductId);
            return product ? product.tasks : [];
        },
        handleProcessSelection(selectedId) {
            const process = this.productionCosts.find(p => p.id === selectedId);
            if (process) {
                this.newTask.name = process.name;

                let totalSeconds = process.estimated_time_seconds;

                // Multiplicar por cantidad a producir si es tipo "Pieza"
                if (process.cost_type === 'Pieza' && this.currentProduct) {
                    totalSeconds = process.estimated_time_seconds * this.currentProduct.quantity_to_produce;
                }
                
                this.newTask.estimated_time_minutes = Math.round(totalSeconds / 60);
            }
        },
        isNewTaskValid() {
            const isNameValid = this.newTask.name && this.newTask.name.trim() !== '';
            const isTimeValid = this.newTask.estimated_time_minutes > 0;
            return this.newTask.operator_id && isNameValid && isTimeValid;
        },
        addTask() {
            if (!this.isNewTaskValid()) return;
            const product = this.form.products_with_tasks.find(p => p.sale_product_id === this.currentProduct.id);
            if (product) {
                product.tasks.push({ 
                    id: null, // Es nueva, no tiene ID todavía
                    operator_id: this.newTask.operator_id,
                    name: this.newTask.name,
                    estimated_time_minutes: this.newTask.estimated_time_minutes,
                    status: 'Pendiente',
                });
                this.resetNewTaskForm();
            }
        },
        removeTask(index) {
            const product = this.form.products_with_tasks.find(p => p.sale_product_id === this.currentProduct.id);
            if (product) {
                product.tasks.splice(index, 1);
            }
        },
        resetNewTaskForm() {
            this.newTask = { is_free_task: true, production_cost_id: null, operator_id: null, name: '', estimated_time_minutes: null };
        },
        
        // --- Métodos de Ayuda (Helpers) ---
        getOperatorName(operatorId) {
            const operator = this.operators.find(op => op.id === operatorId);
            return operator ? operator.name : 'N/A';
        },
        getProductImage(product) {
            return product.media && product.media.length > 0
                ? product.media[0].original_url
                : 'https://via.placeholder.com/150';
        },
        getTotalStock(product) {
            if (!product.storages || product.storages.length === 0) return 0;
            return product.storages.reduce((sum, storage) => sum + (storage.quantity || 0), 0);
        },
        formatNumber(value) {
            return Number(value).toLocaleString('en-US'); 
        },

        // --- Lógica de Actualización ---
        update() {
            this.form.put(route('productions.update', this.sale.id), {
                 onSuccess: () => ElMessage.success('Órdenes de producción actualizadas exitosamente'),
                 onError: (errors) => {
                    console.error(errors);
                    ElMessage.error('Ocurrió un error. Revisa los datos e intenta de nuevo.');
                 },
            });
        },
    },
};
</script>