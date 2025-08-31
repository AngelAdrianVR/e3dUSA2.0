<template>
    <AppLayout title="Nueva Orden de Producción">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Nueva Orden de Producción
                </h2>
                <div class="flex items-center space-x-4">
                    <span v-for="s in 3" :key="s" class="transition-all duration-300"
                          :class="{'text-primary font-bold scale-110': step === s, 'text-gray-400': step !== s}">
                        Paso {{ s }}
                    </span>
                </div>
            </div>
        </template>

        <div class="py-7">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div v-if="step === 1">
                        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-4">
                            <i class="fa-solid fa-file-invoice-dollar mr-2"></i>
                            Paso 1: Selecciona las órdenes de venta a producir
                        </h3>
                        <div class="border rounded-lg p-2 dark:border-gray-700">
                             <el-table v-loading="loading" max-height="500" :data="salesData" style="width: 100%" stripe @selection-change="handleSaleSelectionChange" class="dark:!bg-slate-900 dark:!text-gray-300">
                                <el-table-column type="selection" width="55" />
                                <el-table-column label="Orden Venta" prop="id" width="120" #default="scope">OV-{{ scope.row.id }}</el-table-column>
                                <el-table-column prop="branch.name" label="Cliente" />
                                <el-table-column prop="authorized_at" label="Fecha Autorización" />
                             </el-table>
                             <!-- Elemento disparador para IntersectionObserver -->
                             <div v-if="sales.next_page_url" ref="loadMoreTrigger" class="h-10 flex justify-center items-center">
                                 <i v-if="loading" class="fa-solid fa-spinner fa-spin text-gray-400"></i>
                             </div>
                        </div>
                        <div class="flex justify-end mt-8">
                            <PrimaryButton @click="step = 2" :disabled="!selectedSales.length">
                                Siguiente <i class="fa-solid fa-arrow-right ml-2"></i>
                            </PrimaryButton>
                        </div>
                    </div>

                    <div v-if="step === 2">
                         <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-4">
                            <i class="fa-solid fa-boxes-stacked mr-2"></i>
                            Paso 2: Revisa productos y asigna tareas
                        </h3>
                         <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <div v-for="product in productsToProduce" :key="product.id" class="bg-gray-50 dark:bg-slate-800 border dark:border-gray-700 rounded-lg p-4 flex flex-col justify-between">
                                <div class="flex items-start space-x-4">
                                    <img :src="getProductImage(product.product)" :alt="product.product.name" class="w-24 h-24 rounded object-cover">
                                    <div class="flex-1">
                                        <p class="text-xs text-primary">OV-{{ product.sale_id }}</p>
                                        <h4 class="font-bold text-lg text-gray-800 dark:text-gray-200">{{ product.product.name }}</h4>
                                        <p class="text-sm text-gray-500">Cantidad Total: <span class="font-semibold">{{ product.quantity }}</span></p>
                                        <p class="text-sm text-amber-500">A Producir (descontando stock): <span class="font-semibold">{{ product.quantity_to_produce }}</span></p>
                                    </div>
                                </div>
                                <div class="mt-3" v-if="product.product.components.length">
                                    <h5 class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400 mb-1">Componentes</h5>
                                    <ul class="text-xs space-y-1">
                                        <li v-for="component in product.product.components" :key="component.id" class="flex justify-between items-center">
                                            <span>- {{ component.name }}</span>
                                            <span class="px-2 py-0.5 rounded-full" :class="getTotalStock(component) > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                                Stock: {{ getTotalStock(component) }}
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
                                        <i class="fa-solid fa-plus mr-2"></i>
                                        Asignar Tareas
                                    </PrimaryButton>
                                </div>
                            </div>
                        </div>
                         <div class="flex justify-between mt-8">
                            <SecondaryButton @click="step = 1"><i class="fa-solid fa-arrow-left mr-2"></i> Volver</SecondaryButton>
                            <PrimaryButton @click="step = 3" :disabled="!isReadyToCreate()">
                                Revisar y Crear Órdenes <i class="fa-solid fa-arrow-right ml-2"></i>
                            </PrimaryButton>
                        </div>
                    </div>

                    <div v-if="step === 3">
                        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-4">
                            <i class="fa-solid fa-clipboard-check mr-2"></i>
                            Paso 3: Confirma la creación de las órdenes
                        </h3>
                        <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                            <p class="text-gray-600 dark:text-gray-300">
                                Estás a punto de crear <span class="font-bold text-primary">{{ productsToProduce.length }}</span> órdenes de producción para los productos seleccionados.
                                Cada producto tiene sus tareas asignadas con operadores y tiempos estimados.
                            </p>
                            <p class="mt-2 text-sm text-gray-500">
                                Una vez creadas, las órdenes aparecerán en el módulo de producción en estado "Pendiente".
                                ¿Deseas continuar?
                            </p>
                        </div>
                        <div class="flex justify-between mt-8">
                            <SecondaryButton @click="step = 2"><i class="fa-solid fa-arrow-left mr-2"></i> Volver a Asignar</SecondaryButton>
                            <el-popconfirm confirm-button-text="Sí, crear ahora" cancel-button-text="No" title="¿Confirmar creación de órdenes?" @confirm="store">
                                <template #reference>
                                    <PrimaryButton :disabled="form.processing">
                                        <i v-if="form.processing" class="fa-solid fa-spinner fa-spin mr-2"></i>
                                        Crear Órdenes de Producción
                                    </PrimaryButton>
                                </template>
                            </el-popconfirm>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DialogModal :show="isTaskModalOpen" @close="isTaskModalOpen = false">
            <template #title>Asignar Tareas para: <span class="text-primary">{{ currentProduct?.product.name }}</span></template>
            <template #content>
                <div class="p-4 border rounded-lg dark:border-gray-700 space-y-4">
                    <div class="flex items-center space-x-3">
                        <el-switch v-model="newTask.is_free_task" active-text="Tarea Libre" inactive-text="Proceso Estándar" />
                    </div>

                    <div v-if="!newTask.is_free_task">
                         <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Proceso*</label>
                        <el-select v-model="newTask.production_cost_id" :teleported="false" placeholder="Selecciona un proceso" class="w-full" @change="handleProcessSelection">
                            <el-option v-for="process in productionCosts" :key="process.id" :label="process.name" :value="process.id" />
                        </el-select>
                    </div>

                    <div v-if="newTask.is_free_task">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre de la Tarea*</label>
                        <input v-model="newTask.name" type="text" placeholder="Ej. Empaque, Corte Especial" class="w-full" />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Operador*</label>
                        <el-select v-model="newTask.operator_id" :teleported="false" placeholder="Selecciona un operador" class="w-full">
                            <el-option v-for="operator in operators" :key="operator.id" :label="operator.name" :value="operator.id" />
                        </el-select>
                    </div>

                     <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tiempo Estimado (minutos)*</label>
                        <input v-model="newTask.estimated_time_minutes" type="number" min="1" class="w-full" :disabled="!newTask.is_free_task && newTask.production_cost_id !== null" />
                    </div>

                     <div class="col-span-3 text-right">
                        <SecondaryButton @click="addTask" :disabled="!isNewTaskValid()">Agregar Tarea</SecondaryButton>
                    </div>
                </div>

                <h4 class="text-md font-semibold text-gray-600 dark:text-gray-400 mt-6 mb-2">Tareas para esta orden:</h4>
                <p v-if="!tasksForCurrentProduct.length" class="text-sm text-center text-gray-500 py-4">Aún no hay tareas asignadas.</p>
                <ul v-else class="space-y-2">
                    <li v-for="(task, index) in tasksForCurrentProduct" :key="index" class="flex justify-between items-center bg-gray-100 dark:bg-slate-800 p-2 rounded-lg">
                        <p class="text-sm dark:text-gray-300">
                           <i class="fa-solid fa-user-gear mr-2 text-gray-400"></i><span class="font-bold">{{ getOperatorName(task.operator_id) }}:</span> {{ task.name }} - <span class="text-blue-500 font-semibold">{{ task.estimated_time_minutes }} min</span>
                        </p>
                        <button @click="removeTask(index)" class="text-red-500 hover:text-red-700 transition">
                            <i class="fa-solid fa-trash"></i>
                        </button>
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
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import DialogModal from "@/Components/DialogModal.vue";
import { Link, useForm } from "@inertiajs/vue3";
import { ElMessage } from 'element-plus';

export default {
    data() {
        return {
            step: 1,
            selectedSales: [],
            isTaskModalOpen: false,
            currentProduct: null,
            loading: false,
            salesData: this.sales.data,
            observer: null, // Guardará la instancia del IntersectionObserver

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
    components: { AppLayout, SecondaryButton, PrimaryButton, Link, DialogModal },
    props: {
        sales: Object, 
        operators: Array,
        productionCosts: Array,
    },
    computed: {
        productsToProduce() {
            return this.selectedSales.flatMap(sale => sale.sale_products);
        },
        tasksForCurrentProduct() {
            if (!this.currentProduct) return [];
            const product = this.form.products_with_tasks.find(p => p.sale_product_id === this.currentProduct.id);
            return product ? product.tasks : [];
        }
    },
    mounted() {
        // Configuramos el observador cuando el componente se monta
        this.setupObserver();
    },
    beforeUnmount() {
        // Es importante desconectar el observador para evitar fugas de memoria
        if (this.observer) {
            this.observer.disconnect();
        }
    },
    methods: {
        setupObserver() {
            const options = {
                root: null, // viewport
                rootMargin: '0px',
                threshold: 1.0,
            };

            this.observer = new IntersectionObserver(([entry]) => {
                // Si el elemento disparador es visible y hay más páginas por cargar
                if (entry && entry.isIntersecting && this.sales.next_page_url) {
                    this.loadMoreSales();
                }
            }, options);

            // Empezamos a observar el elemento disparador, si existe
            if (this.$refs.loadMoreTrigger) {
                this.observer.observe(this.$refs.loadMoreTrigger);
            }
        },
        // --- Métodos del Asistente y Carga Progresiva ---
        handleSaleSelectionChange(selection) {
            this.selectedSales = selection;
            const selectedProductIds = this.productsToProduce.map(p => p.id);
            this.form.products_with_tasks = this.form.products_with_tasks.filter(p => selectedProductIds.includes(p.sale_product_id));
        },
        loadMoreSales() {
            if (this.loading) return; // Evitar cargas múltiples simultáneas
            
            this.loading = true;
            this.$inertia.get(this.sales.next_page_url, {}, {
                preserveState: true,
                preserveScroll: true,
                only: ['sales'],
                onSuccess: page => {
                    this.salesData = [...this.salesData, ...page.props.sales.data];
                    // Inertia actualiza props.sales automáticamente
                    this.loading = false;

                    // Si ya no hay más páginas, el div disparador desaparecerá y el observador dejará de activarse.
                },
                onError: () => {
                    this.loading = false;
                }
            });
        },
        
        // --- Métodos de Asignación de Tareas ---
        openTaskModal(product) {
            this.currentProduct = product;
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
                this.newTask.estimated_time_minutes = Math.round(process.estimated_time_seconds / 60);
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
                    operator_id: this.newTask.operator_id,
                    name: this.newTask.name,
                    estimated_time_minutes: this.newTask.estimated_time_minutes,
                    production_cost_id: this.newTask.production_cost_id,
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

        // --- Lógica de Creación ---
        isReadyToCreate() {
            if (!this.productsToProduce.length) return false;
            
            return this.productsToProduce.every(product => {
                const found = this.form.products_with_tasks.find(p => p.sale_product_id === product.id);
                return found && found.tasks.length > 0;
            });
        },
        store() {
            const payload = {
                products_with_tasks: this.form.products_with_tasks.filter(p => p.tasks.length > 0)
            };
            this.form.post(route('productions.store'), {
                 onSuccess: () => ElMessage.success('Órdenes de producción creadas exitosamente'),
                 onError: (errors) => {
                    console.error(errors);
                    ElMessage.error('Ocurrió un error. Revisa los datos e intenta de nuevo.');
                 },
            });
        },
    },
};
</script>
