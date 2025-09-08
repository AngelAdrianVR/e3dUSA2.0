<template>
    <AppLayout title="Solicitar Diseño">
        <div class="px-4 sm:px-0">
            <div class="flex items-center space-x-2">
                <Back />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Solicitar nuevo diseño
                </h2>
            </div>
        </div>

        <div ref="formContainer" class="py-7">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6 md:p-8">
                    
                    <form @submit.prevent="store" class="grid grid-cols-1 md:grid-cols-2 gap-5">
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
                                placeholder="Ej. Lona para espectacular de hamburguesas"
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

    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
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
            order_title: null,
            specifications: null,
            design_category_id: null,
            is_hight_priority: false,
            branch_id: null,
            contact_id: null,
            due_date: null,
            designer_id: null,
            media: null,
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
        AppLayout,
        SecondaryButton,
        PrimaryButton,
        CancelButton,
        InputError,
        TextInput,
        DialogModal,
        FileUploader,
        InputLabel,
        Back,
    },
    props: {
        designCategories: Array,
        designers: Array,
        branches: Array,
    },
    methods: {
        store() {
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
        }
    }
};
</script>

