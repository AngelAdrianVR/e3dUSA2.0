<template>
    <AppLayout title="Solicitar Diseño">
        <!-- componente de carga de trabajo de diseñadores -->
        <DesignersWorkload v-if="$page.props.auth.user.permissions.includes('Crear ordenes de diseño')" />

        <div class="px-4 sm:px-0">
            <div class="flex items-center space-x-2">
                <Back />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Solicitar nuevo diseño
                </h2>
            </div>
        </div>

        <!-- === Modification Notice === -->
        <div v-if="originalDesign" class="p-4 my-4 text-sm text-blue-800 rounded-lg bg-blue-50 dark:bg-gray-800 dark:text-blue-400 max-w-4xl mx-auto sm:px-6 lg:px-8" role="alert">
            Estás solicitando una modificación para el diseño: <span class="font-medium">{{ originalDesign.name }}</span>. Los detalles se han precargado.
        </div>
        <!-- === END === -->

        <div ref="formContainer" class="py-7">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6 md:p-8">
                    
                    <form @submit.prevent="checkSimilarAndStore" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <InputLabel value="Cliente (opcional)" />
                            <el-select v-model="form.branch_id" filterable placeholder="Selecciona un cliente" class="!w-full" @change="handleBranchChange">
                                <el-option v-for="branch in branches" :key="branch.id" :label="branch.name" :value="branch.id" />
                            </el-select>
                            <InputError :message="form.errors.branch_id" />
                        </div>

                        <div v-if="form.branch_id">
                            <InputLabel value="Contacto*" />
                            <el-select v-model="form.contact_id" filterable placeholder="Selecciona un contacto" class="!w-full" no-data-text="Selecciona un cliente primero">
                                <el-option v-for="contact in availableContacts" :key="contact.id" :label="`${contact.name} (${contact.charge})`" :value="contact.id" />
                            </el-select>
                            <InputError :message="form.errors.contact_id" />
                        </div>
                        
                        <div class="md:col-span-2">
                            <TextInput
                                label="Título del diseño*"
                                v-model="form.order_title"
                                type="text"
                                :error="form.errors.order_title"
                                placeholder="Ej. Render 3d Llavero"
                            />
                        </div>
                        
                        <div class="md:col-span-2">
                            <TextInput
                                label="Especificaciones*"
                                v-model="form.specifications"
                                :isTextarea="true"
                                :error="form.errors.specifications"
                                placeholder="Describe detalladamente qué necesitas. Incluye medidas, colores, textos, ideas, etc."
                                rows="3"
                            />
                        </div>

                        <div class="md:col-span-2">
                            <div class="flex items-end space-x-2">
                                <div class="w-full">
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Categoría*</label><br>
                                    <el-select v-model="form.design_category_id" placeholder="Selecciona una categoría" class="!w-2/3" filterable>
                                        <el-option v-for="category in designCategories" :key="category.id" :label="category.name" :value="category.id" />
                                    </el-select>
                                    <InputError :message="form.errors.design_category_id" />
                                </div>
                                <PrimaryButton @click="showCategoryModal = true" type="button">
                                    <i class="fa-solid fa-plus text-xs"></i>
                                </PrimaryButton>
                            </div>
                        </div>

                        <div>
                            <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Asignar a diseñador</label>
                            <el-select v-model="form.designer_id" placeholder="Selecciona un diseñador" class="!w-full" filterable clearable>
                                <el-option v-for="designer in designers" :key="designer.id" :label="designer.name" :value="designer.id" />
                            </el-select>
                            <InputError :message="form.errors.designer_id" />
                        </div>
                        
                        <div>
                            <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Fecha de entrega deseada (Opcional)</label>
                            <el-date-picker
                                v-model="form.due_date"
                                type="date"
                                placeholder="Selecciona una fecha"
                                format="YYYY/MM/DD"
                                value-format="YYYY-MM-DD"
                                :disabled-date="disabledDate"
                                class="!w-full"
                            />
                            <InputError :message="form.errors.due_date" />
                        </div>

                        <div class="col-span-2 mt-5">
                            <InputLabel value="Archivos (max: 3)" />
                            <FileUploader @files-selected="form.media = $event" :multiple="true" acceptedFormat="imagen" :max-files="3" />
                            <InputError :message="form.errors.media" class="mt-2" />
                        </div>
                        
                        <div class="flex items-center justify-start mt-3">
                             <label class="text-gray-700 dark:text-gray-100 text-sm mr-5">¿Es prioridad alta?</label>
                            <el-switch v-model="form.is_hight_priority" style="--el-switch-on-color: #EF4444;"/>
                        </div>

                        <div class="flex justify-end mt-8 col-span-full">
                            <SecondaryButton :loading="form.processing" :disabled="form.processing">
                                Crear Orden
                            </SecondaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL PARA CREAR CATEGORÍA -->
        <DialogModal :show="showCategoryModal" @close="showCategoryModal = false">
            <template #title>
                Crear nueva categoría de diseño
            </template>
            <template #content>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <TextInput
                            label="Nombre de la categoría*"
                            v-model="categoryForm.name"
                            type="text"
                            :error="categoryForm.errors.name"
                            placeholder="Ej. Diseño de logotipo"
                            ref="categoryNameInput"
                        />
                    </div>
                    <div>
                        <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Complejidad*</label>
                         <el-select v-model="categoryForm.complexity" :teleported="false" placeholder="Selecciona la complejidad" class="!w-full">
                            <el-option label="Simple" value="Simple" />
                            <el-option label="Medio" value="Medio" />
                            <el-option label="Complejo" value="Complejo" />
                        </el-select>
                        <InputError :message="categoryForm.errors.complexity" />
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="flex items-center space-x-3">
                    <CancelButton @click="showCategoryModal = false" :disabled="categoryForm.processing">
                        Cancelar
                    </CancelButton>
                    <SecondaryButton @click="storeCategory" :loading="categoryForm.processing" :disabled="categoryForm.processing">
                        Crear Categoría
                    </SecondaryButton>
                </div>
            </template>
        </DialogModal>

        <!-- ========= NUEVO MODAL PARA ÓRDENES SIMILARES ========= -->
        <DialogModal :show="showSimilarOrdersModal" @close="showSimilarOrdersModal = false">
            <template #title>
                Se encontraron órdenes similares
            </template>
            <template #content>
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    Hemos encontrado algunas órdenes que podrían ser similares a la que estás creando.
                    Por favor, revísalas para evitar duplicados. Puedes hacer clic en el folio para ver los detalles en una nueva pestaña.
                </p>
                <div class="border rounded-md dark:border-gray-700 max-h-60 overflow-y-auto">
                    <ul class="divide-y dark:divide-gray-700">
                        <li v-for="order in similarOrders" :key="order.id" class="p-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-200">
                            <p class="font-semibold text-gray-800 dark:text-gray-200">
                                <a :href="route('design-orders.show', order.id)" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                    Folio: OD-{{ order.id.toString().padStart(4, '0') }}
                                </a>
                                - {{ order.order_title }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-300 mt-1">
                                Estado: <span class="font-medium">{{ order.status }}</span> | Solicitado por: {{ order.requester?.name ?? 'N/A' }} el {{ new Date(order.created_at).toLocaleDateString() }}
                            </p>
                        </li>
                    </ul>
                </div>
            </template>
            <template #footer>
                <div class="flex items-center justify-between w-full">
                    <p class="text-sm text-gray-500">
                        {{ similarOrders.length }} coincidencia(s) encontrada(s).
                    </p>
                    <div class="flex items-center space-x-3">
                        <CancelButton @click="showSimilarOrdersModal = false" :disabled="form.processing">
                            Cancelar
                        </CancelButton>
                        <SecondaryButton @click="confirmCreation" :loading="form.processing" :disabled="form.processing">
                            Crear de todos modos
                        </SecondaryButton>
                    </div>
                </div>
            </template>
        </DialogModal>

    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import DesignersWorkload from "@/Components/MyComponents/DesignersWorkload.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import TextInput from "@/Components/TextInput.vue";
import Back from "@/Components/MyComponents/Back.vue";
import DialogModal from "@/Components/DialogModal.vue";
import { ElMessage } from 'element-plus';
import { useForm, router } from "@inertiajs/vue3";
import { nextTick } from 'vue';

export default {
    data() {
        const form = useForm({
            order_title: this.originalDesign ? `Modificación de: ${this.originalDesign.name}` : null,
            specifications: this.originalDesign ? `Basado en el diseño: ${this.originalDesign.name}.\n---NUEVOS CAMBIOS---\n` : null,
            design_category_id: this.originalDesign ? this.originalDesign.design_category_id : null,
            is_hight_priority: false,
            branch_id: null,
            contact_id: null,
            due_date: null,
            designer_id: null,
            media: null,
            modifies_design_id: this.originalDesign ? this.originalDesign.id : null,
        });

        const categoryForm = useForm({
            name: null,
            complexity: null,
        });

        return {
            form,
            categoryForm,
            showCategoryModal: false,
            availableContacts: [],
            similarOrders: [],
            showSimilarOrdersModal: false,
        };
    },
    components: {
        Back,
        TextInput,
        AppLayout,
        InputError,
        InputLabel,
        DialogModal,
        CancelButton,
        FileUploader,
        PrimaryButton,
        SecondaryButton,
        DesignersWorkload,
    },
    props: {
        designCategories: Array,
        designers: Array,
        branches: Array,
        originalDesign: Object,
    },
    methods: {
        // Método que envía el formulario al backend
        proceedWithStore() {
            this.form.post(route("design-orders.store"), {
                onSuccess: () => {
                    ElMessage({
                        type: 'success',
                        message: 'Solicitud de diseño enviada',
                    });
                },
                onError: () => {
                    this.$refs.formContainer.scrollIntoView({ behavior: 'smooth' });
                }
            });
        },
        // Nuevo método principal que se ejecuta al enviar el formulario
        async checkSimilarAndStore() {
            // Validación básica para no hacer una petición innecesaria
            if (!this.form.order_title || !this.form.design_category_id) {
                ElMessage({
                    type: 'warning',
                    message: 'El título y la categoría son necesarios para crear la orden.',
                });
                // Procedemos para que la validación del backend muestre los errores específicos
                this.proceedWithStore();
                return;
            }

            try {
                this.form.processing = true;
                const response = await axios.post(route('design-orders.check-similar'), {
                    order_title: this.form.order_title,
                    design_category_id: this.form.design_category_id,
                    branch_id: this.form.branch_id,
                });

                if (response.data && response.data.length > 0) {
                    // Si se encuentran órdenes, las guardamos y mostramos el modal
                    this.similarOrders = response.data;
                    this.showSimilarOrdersModal = true;
                } else {
                    // Si no hay similitudes, creamos la orden directamente
                    this.proceedWithStore();
                }
            } catch (error) {
                console.error("Error al verificar órdenes similares:", error);
                ElMessage({
                    type: 'error',
                    message: 'No se pudo verificar si existen órdenes similares. Se intentará crear la orden.',
                });
                // En caso de error en la verificación, permitimos la creación
                this.proceedWithStore();
            } finally {
                 this.form.processing = false;
            }
        },
        // Se llama cuando el usuario confirma la creación desde el modal
        confirmCreation() {
            this.showSimilarOrdersModal = false;
            this.proceedWithStore();
        },
        storeCategory() {
            this.categoryForm.post(route('design-categories.store'), {
                preserveScroll: true,
                onSuccess: () => {
                    this.showCategoryModal = false;
                    this.categoryForm.reset();
                    ElMessage({ type: 'success', message: 'Categoría creada' });
                    // Forzar a Inertia a recargar las props para actualizar el select
                    router.reload({ only: ['designCategories'] });
                },
                onError: () => {
                    this.$refs.categoryNameInput.focus();
                }
            });
        },
        async handleBranchChange(branchId) {
            this.form.contact_id = null;
            
            const selectedBranch = this.branches.find(b => b.id === branchId);
            this.availableContacts = selectedBranch ? selectedBranch.contacts : [];
        },
        disabledDate(time) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return time.getTime() < today.getTime();
        },
    },
    watch: {
        showCategoryModal(value) {
            if (value) {
                nextTick(() => this.$refs.categoryNameInput.focus());
            }
        },
    }
};
</script>

