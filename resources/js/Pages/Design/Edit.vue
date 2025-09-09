<template>
    <AppLayout :title="'Editar Orden OD-' + designOrder.id.toString().padStart(4, '0')">
        <!-- componente de carga de trabajo de diseñadores -->
        <DesignersWorkload v-if="$page.props.auth.user.permissions.includes('Crear ordenes de diseño')" />

        <div class="px-4 sm:px-0">
            <div class="flex items-center space-x-2">
                <Back />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Editar Orden de Diseño <span class="text-indigo-500">OD-{{ designOrder.id.toString().padStart(4, '0') }}</span>
                </h2>
            </div>
        </div>

        <div ref="formContainer" class="py-7">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6 md:p-8">
                    
                    <form @submit.prevent="updateDesignOrder" class="grid grid-cols-1 md:grid-cols-2 gap-5">
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

                        <div v-if="designOrder.media?.length" label="Archivos adjuntos" class="grid grid-cols-2 lg:grid-cols-3 gap-3 col-span-full mb-3">
                            <label class="col-span-full text-gray-700 dark:text-white text-sm" for="">Archivos adjuntos</label>
                            <FileView v-for="file in designOrder.media" :key="file" :file="file" :deletable="true"
                                @delete-file="deleteFile($event)" />
                        </div>

                        <div v-if="designOrder.media?.length < 3" class="col-span-2 mt-5">
                            <InputLabel :value="`Archivos (max: 3). ${designOrder.media?.length ?? 0} archivos actuales`" />
                            <FileUploader @files-selected="form.media = $event" :multiple="true" acceptedFormat="todo" :max-files="3 - designOrder.media?.length" />
                            <InputError :message="form.errors.media" class="mt-2" />
                        </div>

                        <p class="text-amber-600 text-sm mt-4 col-span-full" v-else>*Has alcanzado el límite de imágenes elimina alguna para poder agregar más</p>
                        
                        <div class="flex items-center justify-start mt-3">
                             <label class="text-gray-700 dark:text-gray-100 text-sm mr-5">¿Es prioridad alta?</label>
                            <el-switch v-model="form.is_hight_priority" style="--el-switch-on-color: #EF4444;"/>
                        </div>

                        <div class="flex justify-end mt-8 col-span-full">
                            <SecondaryButton :loading="form.processing" :disabled="form.processing">
                                Guardar Cambios
                            </SecondaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL PARA CREAR CATEGORÍA (Se mantiene igual) -->
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

    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import FileView from "@/Components/MyComponents/FileView.vue";
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
        // Inicializamos el formulario con los datos de la orden que se pasa como prop
        const form = useForm({
            _method: 'PUT', // Campo para indicar que es una petición PUT
            order_title: this.designOrder.order_title,
            specifications: this.designOrder.specifications,
            design_category_id: this.designOrder.design_category_id,
            is_hight_priority: Boolean(this.designOrder.is_hight_priority),
            branch_id: this.designOrder.branch_id,
            contact_id: this.designOrder.contact_id,
            due_date: this.designOrder.due_date,
            designer_id: this.designOrder.designer_id,
            media: null, // Los archivos se manejan por separado
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
        };
    },
    components: {
        Back,
        FileView,
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
        designOrder: Object, // Prop para recibir la orden de diseño a editar
        designCategories: Array,
        designers: Array,
        branches: Array,
    },
    methods: {
        updateDesignOrder() {
            // Usamos 'post' porque las peticiones PUT no soportan 'multipart/form-data' de forma nativa.
            // Inertia se encarga de convertirlo a una petición PUT real gracias al campo _method.
            this.form.post(route("design-orders.update", this.designOrder.id), {
                onSuccess: () => {
                    ElMessage({
                        type: 'success',
                        message: 'Orden de diseño actualizada',
                    });
                },
                onError: () => {
                    // Desplazarse al contenedor del formulario si hay errores de validación
                    this.$refs.formContainer.scrollIntoView({ behavior: 'smooth' });
                }
            });
        },
        storeCategory() {
            this.categoryForm.post(route('design-categories.store'), {
                preserveScroll: true,
                onSuccess: () => {
                    this.showCategoryModal = false;
                    this.categoryForm.reset();
                    ElMessage({ type: 'success', message: 'Categoría creada' });
                    router.reload({ only: ['designCategories'] });
                },
                onError: () => {
                    this.$refs.categoryNameInput.focus();
                }
            });
        },
        handleBranchChange(branchId) {
            this.form.contact_id = null;
            const selectedBranch = this.branches.find(b => b.id === branchId);
            this.availableContacts = selectedBranch ? selectedBranch.contacts : [];
        },
        deleteFile(fileId) {
            this.designOrder.media = this.designOrder.media.filter(m => m.id !== fileId);
        },
        disabledDate(time) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return time.getTime() < today.getTime();
        },
        // Método para inicializar los contactos si ya hay una sucursal seleccionada
        initializeContacts() {
            if (this.form.branch_id) {
                const selectedBranch = this.branches.find(b => b.id === this.form.branch_id);
                this.availableContacts = selectedBranch ? selectedBranch.contacts : [];
            }
        }
    },
    mounted() {
        this.initializeContacts();
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
