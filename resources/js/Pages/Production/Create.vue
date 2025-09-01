<template>
    <AppLayout title="Nueva Orden de Producción">
        <div class="flex justify-between items-center">
            <Back :href="route('catalog-products.index')" />
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Nueva Orden de Producción
            </h2>

            <div class="flex items-center space-x-6">
                <span 
                    v-for="s in 3" 
                    :key="s" 
                    class="relative flex flex-col items-center text-sm font-medium transition-colors duration-300"
                    :class="step === s 
                        ? 'text-gray-900 dark:text-white' 
                        : step > s 
                            ? 'text-green-600 dark:text-green-400' 
                            : 'text-gray-400 dark:text-gray-500'">
                    
                    <!-- Círculo del paso -->
                    <span 
                        class="size-8 flex items-center justify-center rounded-full border transition-all duration-300"
                        :class="step > s 
                            ? 'bg-gradient-to-r from-green-400 to-green-600 text-white border-transparent' 
                            : step === s 
                                ? 'border-gray-900 dark:border-white font-semibold' 
                                : 'border-gray-400 dark:border-gray-600'">
                        <!-- Palomita si ya se completó -->
                        <template v-if="step > s">✔</template>
                        <template v-else>{{ s }}</template>
                    </span>
                    
                    <!-- Etiqueta -->
                    <span class="mt-1">Paso {{ s }}</span>
                </span>
            </div>
        </div>

        <div class="py-7">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div v-if="step === 1">
                        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-4">
                            <i class="fa-solid fa-file-invoice-dollar mr-2"></i>
                            Paso 1: Selecciona las órdenes de venta a producir
                        </h3>
                        <div class="border rounded-lg p-2 dark:border-gray-700 dark:bg-slate-800 max-h-[60vh] overflow-auto">
                             <el-table v-loading="loading" max-height="500" :data="salesData" style="width: 100%" stripe @selection-change="handleSaleSelectionChange" class="dark:!bg-slate-900 dark:!text-gray-300">
                                <el-table-column type="selection" width="55" />
                                <el-table-column label="Orden Venta" prop="id" width="150" #default="scope">
                                    <div class="flex items-center space-x-2">
                                        <svg v-if="scope.row.type === 'venta'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-purple-500">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                                        </svg>
                                        <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-red-500">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                        </svg>

                                        <p v-if="scope.row.type === 'venta'">OV-{{ scope.row.id.toString().padStart(4, '0') }}</p>
                                        <p v-else>OS-{{ scope.row.id.toString().padStart(4, '0') }}</p>
                                    </div>
                                </el-table-column>
                                <el-table-column #default="scope" prop="branch.name" label="Cliente" width="150">
                                    <p >{{ scope.row.branch?.name ?? 'N/A' }}</p>
                                </el-table-column>
                                <el-table-column label="Productos" width="150">
                                <template #default="scope">
                                    <el-tooltip v-if="scope.row.sale_products?.length" placement="right">
                                        <template #content>
                                            <ul class="list-disc list-inside text-xs">
                                                <li v-for="item in scope.row.sale_products" :key="item.id" class="flex items-center space-x-2 mb-2">
                                                    <img draggable="false" :src="getProductImage(item.product)" :alt="item.product.name" class="size-12 rounded-md object-cover mr-2">
                                                    ({{ item.quantity }}) {{ item.product.name }}
                                                </li>
                                            </ul>
                                        </template>
                                        <span class="cursor-pointer bg-blue-100 text-blue-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300">
                                            {{ scope.row.sale_products.length }} producto(s)
                                        </span>
                                    </el-tooltip>
                                    <span v-else class="text-xs text-gray-400">N/A</span>
                                </template>
                            </el-table-column>
                                <el-table-column prop="authorized_at" label="Fecha Autorización" #default="scope">
                                    <p >{{ formatDate(scope.row.authorized_at) }}</p>
                                </el-table-column>
                             </el-table>
                             <!-- Elemento disparador para IntersectionObserver -->
                             <div v-if="sales.next_page_url" ref="loadMoreTrigger" class="h-10 flex justify-center items-center">
                                 <i v-if="loading" class="fa-solid fa-spinner fa-spin text-gray-400"></i>
                             </div>
                        </div>
                        <div class="flex justify-end mt-8">
                            <SecondaryButton @click="step = 2" :disabled="!selectedSales.length">
                                Siguiente <i class="fa-solid fa-arrow-right ml-2"></i>
                            </SecondaryButton>
                        </div>
                    </div>

                    <div v-if="step === 2">
                         <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-4">
                            <i class="fa-solid fa-boxes-stacked mr-2"></i>
                            Paso 2: Revisa productos y asigna tareas
                        </h3>
                         <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 max-h-[60vh] overflow-auto">
                            <div v-for="product in productsToProduce" :key="product.id" class="bg-gray-50 dark:bg-slate-800 border dark:border-gray-700 rounded-lg p-4 flex flex-col justify-between">
                                <div class="flex items-start space-x-4">
                                    <img draggable="false" :src="getProductImage(product.product)" :alt="product.product.name" class="size-24 rounded object-cover">
                                    <div class="flex-1">
                                        <p class="text-xs text-primary font-bold">OV-{{ product.sale_id.toString().padStart(4, '0') }}</p>
                                        <h4 class="font-bold text-lg text-gray-800 dark:text-gray-200">{{ product.product.name }}</h4>
                                        <p class="text-sm text-gray-500">Cantidad Solicitada: <span class="font-semibold">{{ product.quantity }} {{ product.product.measure_unit }}</span></p>
                                        <div class="flex items-center space-x-2">
                                            <p class="text-sm text-amber-500">A Producir (descontando de stock): <span class="font-semibold">{{ product.quantity_to_produce }} {{ product.product.measure_unit }}</span></p>
                                            <el-tooltip v-if="product.quantity_to_produce === 0" placement="top" content="Debes asignar al menos una tarea a cada producto para continuar.">
                                                <template #content>
                                                    <p class="text-amber-500">
                                                        Debes asignar al menos una tarea a cada producto para continuar. <br>
                                                        <strong class="text-white">Si un producto no requiere producción, puedes asignarle una tarea de empaque, o una libre con 0 minutos.</strong>
                                                    </p>
                                                </template>
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 text-blue-400">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                                                </svg>
                                            </el-tooltip>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3" v-if="product.product.components.length">
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
                                        <i class="fa-solid fa-plus mr-2"></i>
                                        Asignar Tareas
                                    </PrimaryButton>
                                </div>
                            </div>
                        </div>
                         <div class="flex justify-between mt-7">
                            <PrimaryButton @click="step = 1"><i class="fa-solid fa-arrow-left mr-2"></i> Volver</PrimaryButton>
                            <SecondaryButton @click="step = 3" :disabled="!isReadyToCreate()">
                                Revisar y Crear Órdenes <i class="fa-solid fa-arrow-right ml-2"></i>
                            </SecondaryButton>
                        </div>
                    </div>

                    <div v-if="step === 3">
                        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-4">
                            <i class="fa-solid fa-clipboard-check mr-2"></i>
                            Paso 3: Confirma la creación de las órdenes
                        </h3>

                        <!-- Confirmation text -->
                        <div class="bg-gray-50 dark:bg-slate-800/50 backdrop-blur-sm border border-primary/20 p-4 rounded-xl mt-6 text-center">
                            <p class="dark:text-gray-300">
                                Estás a punto de crear <span class="font-bold text-primary text-lg">{{ productsToProduce.length }}</span> órdenes de producción para los productos seleccionados.
                            </p>
                            <p class="mt-2 text-sm text-gray-400">
                                Una vez creadas, las órdenes aparecerán en el módulo de producción en estado "Pendiente".
                                ¿Deseas continuar?
                            </p>
                        </div>
                        
                        <!-- Summary of products and tasks -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 max-h-[52vh] overflow-y-auto p-4 mt-2">
                            <div v-for="product in productsToProduce" :key="product.id"
                                class="bg-gray-50 dark:bg-slate-800/50 backdrop-blur-sm border border-primary/20 rounded-xl p-4 shadow-lg hover:border-primary/60 transition-all duration-300 ease-in-out">
                                
                                <div class="flex items-center space-x-4 mb-4">
                                    <img :src="getProductImage(product.product)" :alt="product.product.name" class="size-16 rounded-lg object-cover border-2 border-slate-700 shadow-md">
                                    <div>
                                        <p class="text-xs text-primary font-bold">OV-{{ product.sale_id.toString().padStart(4, '0') }}</p>
                                        <h4 class="font-bold text-lg dark:text-gray-100">{{ product.product.name }}</h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Cantidad a producir: {{ product.quantity_to_produce }} {{ product.product.measure_unit }}</p>
                                    </div>
                                </div>

                                <div>
                                    <h5 class="text-sm font-semibold dark:text-gray-300 mb-2 border-b border-slate-700 pb-1">Resumen de Tareas</h5>
                                    <ul v-if="getTasksForProduct(product.id).length" class="space-y-2">
                                        <li v-for="(task, index) in getTasksForProduct(product.id)" :key="index"
                                            class="flex justify-between items-center bg-gray-200 dark:bg-slate-900/70 p-2.5 rounded-lg text-sm">
                                            <div class="flex flex-col">
                                            <span class="font-semibold text-cyan-400">{{ task.name }}</span>
                                            <span class="text-gray-400 text-xs mt-1"><i class="fa-solid fa-user-gear mr-2"></i>{{ getOperatorName(task.operator_id) }}</span>
                                            </div>
                                            <div class="text-right">
                                                <span class="font-mono font-bold text-lg text-amber-400">{{ task.estimated_time_minutes }}</span>
                                                <span class="text-xs text-gray-500 block">min</span>
                                            </div>
                                        </li>
                                    </ul>
                                    <p v-else class="text-center text-sm text-gray-500 py-2">No hay tareas asignadas.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-between mt-8">
                            <PrimaryButton @click="step = 2"><i class="fa-solid fa-arrow-left mr-2"></i> Volver a Asignar</PrimaryButton>
                            <el-popconfirm confirm-button-text="Sí, crear ahora" cancel-button-text="No" title="¿Confirmar creación de órdenes?" @confirm="store">
                                <template #reference>
                                    <SecondaryButton :loading="form.processing">
                                        Crear Órdenes de Producción
                                    </SecondaryButton>
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
import Back from "@/Components/MyComponents/Back.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import DialogModal from "@/Components/DialogModal.vue";
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
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
    components: { AppLayout, SecondaryButton, PrimaryButton, Link, DialogModal, TextInput, Back },
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
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        },
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
        formatNumber(value) {
            return Number(value).toLocaleString('en-US'); 
            // o 'es-MX' si prefieres puntos y comas al estilo español
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
