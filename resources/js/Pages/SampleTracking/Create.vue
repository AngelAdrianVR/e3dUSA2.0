<template>
    <AppLayout title="Nueva Solicitud de Muestra">
        <div class="px-4 sm:px-0">
            <div class="flex items-center space-x-2">
                <Back />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Nueva Solicitud de Muestra
                </h2>
            </div>
        </div>

        <div ref="formContainer" class="py-7">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6 md:p-8">
                    
                    <form @submit.prevent="store">
                        <!-- SECCIÓN DE DATOS GENERALES -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                            <TextInput label="Nombre*" v-model="form.name" type="text" :placeholder="'Ej. Llavero KIA nuevo diseño'" :error="form.errors.name" class="col-span-full" />

                            <!-- Selector de Cliente -->
                            <div>
                                <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Cliente/Prospecto*</label>
                                <div class="flex items-center space-x-2">
                                    <el-select v-model="form.branch_id" filterable placeholder="Selecciona un cliente" class="!w-full" @change="handleBranchChange">
                                        <el-option v-for="branch in localBranches" :key="branch.id" :label="branch.name" :value="branch.id" />
                                    </el-select>
                                    <el-button @click="branchModalVisible = true" type="primary" circle plain>
                                        <i class="fa-solid fa-plus"></i>
                                    </el-button>
                                </div>
                                <InputError :message="form.errors.branch_id" />
                            </div>

                            <!-- Selector de Contacto -->
                            <div>
                                <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Contacto*</label>
                                 <div class="flex items-center space-x-2">
                                    <el-select v-model="form.contact_id" filterable placeholder="Selecciona un contacto" class="!w-full" :disabled="!form.branch_id">
                                        <el-option v-for="contact in availableContacts" :key="contact.id" :label="contact.name" :value="contact.id" />
                                    </el-select>
                                    <el-button @click="contactModalVisible = true" type="primary" circle plain :disabled="!form.branch_id">
                                        <i class="fa-solid fa-plus"></i>
                                    </el-button>
                                </div>
                                <InputError :message="form.errors.contact_id" />
                            </div>

                             <!-- Switch para devolución -->
                            <div class="flex items-center space-x-3 mt-2">
                                <el-switch v-model="form.will_be_returned" />
                                <span class="text-gray-700 dark:text-gray-100 text-sm">¿La muestra será devuelta?</span>
                                <InputError :message="form.errors.will_be_returned" />
                            </div>

                             <!-- Fecha de devolución esperada -->
                            <div v-if="form.will_be_returned">
                                <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Fecha de devolución esperada*</label>
                                <el-date-picker
                                    v-model="form.expected_devolution_date"
                                    type="date"
                                    placeholder="Selecciona una fecha"
                                    format="YYYY/MM/DD"
                                    value-format="YYYY-MM-DD"
                                    :disabled-date="disabledDate"
                                    class="!w-full"
                                />
                                <InputError :message="form.errors.expected_devolution_date" />
                            </div>

                             <!-- Comentarios -->
                            <div class="col-span-full">
                                <TextInput
                                    label="Comentarios generales"
                                    :isTextarea="true"
                                    v-model="form.comments"
                                    type="text"
                                    :error="form.errors.comments"
                                    placeholder="Agrega comentarios o notas adicionales. Tamaño x, color, modificaciones, etc."
                                />
                                <InputError :message="form.errors.comments" />
                            </div>
                        </div>

                        <!-- SECCIÓN DE ITEMS/PRODUCTOS -->
                        <div class="border-t dark:border-gray-700 my-8">
                           <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 leading-tight mt-6 mb-3">Productos de Muestra</h3>
                           
                           <div v-if="!form.items.length" class="text-sm text-gray-500 text-center py-4">
                               <span>Aún no has agregado productos a la solicitud.</span>
                           </div>

                           <!-- Items Dinámicos -->
                           <div v-for="(item, index) in form.items" :key="index" class="bg-gray-100 dark:bg-slate-800 p-4 rounded-lg mb-4">
                                <!-- Encabezado del item -->
                                <div class="flex justify-between items-center mb-3">
                                    <p class="font-semibold text-sm text-gray-700 dark:text-gray-300">
                                        {{ item.type === 'catalog' ? 'Producto de Catálogo' : 'Propuesta de Nuevo Producto' }}
                                    </p>
                                    <el-button @click="removeItem(index)" type="danger" circle plain size="small">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </el-button>
                                </div>
                               
                                <div class="grid grid-cols-2 gap-x-4 gap-y-2">
                                    <!-- Campos para Producto de Catálogo -->
                                    <template v-if="item.type === 'catalog'">
                                        <div class="col-span-full">
                                            <el-select @change="getProductData(item)" v-model="item.itemable_id" filterable placeholder="Selecciona un producto" class="!w-full">
                                                <el-option class="!w-96" v-for="product in products" :key="product.id" :label="product.name" :value="product.id" />
                                            </el-select>
                                            <InputError :message="form.errors[`items.${index}.itemable_id`]" />
                                        </div>

                                        <div v-if="item.loading" class="col-span-full flex items-center justify-center p-6">
                                            <svg class="animate-spin h-8 w-8 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </div>

                                        <div v-else-if="item.product_data" class="col-span-full mt-3 bg-white dark:bg-slate-900/50 rounded-xl shadow-md p-4 flex items-start space-x-4">
                                            <div class="flex-shrink-0">
                                                <img v-if="item.product_data.media?.length" :src="item.product_data.media[0].original_url" alt="Imagen del producto" class="h-20 w-20 rounded-lg object-cover">
                                                <div v-else class="h-20 w-20 rounded-lg bg-gray-200 dark:bg-slate-800 flex flex-col items-center justify-center text-gray-400 dark:text-slate-500 text-xs text-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mb-1">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                                    </svg>
                                                    <span>Sin imagen</span>
                                                </div>
                                            </div>
                                            <div class="flex-1">
                                                <p class="font-bold text-gray-800 dark:text-gray-100">{{ item.product_data.name }}</p>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ item.product_data.code }}</p>
                                                <div class="mt-2 text-xs">
                                                    <span class="font-semibold text-gray-700 dark:text-gray-300">Stock total:</span>
                                                    <span class="ml-1 text-green-600 dark:text-green-400 font-medium">{{ calculateTotalStock(item.product_data.storages) }} unidades</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-span-full">
                                            <el-input v-model="item.notes" :rows="2" type="textarea" placeholder="Notas o comentarios del producto" />
                                            <InputError :message="form.errors[`items.${index}.notes`]" />
                                        </div>
                                    </template>

                                    <!-- Campos para Nuevo Producto -->
                                    <template v-else>
                                        <div>
                                            <el-input v-model="item.name" placeholder="Nombre del nuevo producto*" />
                                            <InputError :message="form.errors[`items.${index}.name`]" />
                                        </div>

                                        <div>
                                            <el-input v-model="item.description" :rows="2" type="textarea" placeholder="Descripción o detalles del producto" />
                                            <InputError :message="form.errors[`items.${index}.description`]" />
                                        </div>
                                        <div class="col-span-full">
                                            <InputLabel value="Imágenes del nuevo producto (opcional, máx. 2)" />
                                            <FileUploader @files-selected="item.media = $event" format="Imagen" :multiple="true" :maxFiles="2" class="mt-1" />
                                            <InputError :message="form.errors[`items.${index}.media`]" class="mt-2" />
                                        </div>
                                    </template>

                                    <!-- Cantidad (común para ambos) -->
                                    <div>
                                        <el-input-number v-model="item.quantity" :min="1" placeholder="Cant." class="!w-full" />
                                        <InputError :message="form.errors[`items.${index}.quantity`]" />
                                    </div>
                                </div>
                           </div>
                           
                           <div class="flex items-center space-x-2 mt-3">
                                <el-button @click="addItem('catalog')" type="primary" plain>
                                    <i class="fa-solid fa-plus mr-2"></i>
                                    Producto de Catálogo
                                </el-button>
                                <el-button @click="addItem('new')" type="success" plain>
                                     <i class="fa-solid fa-plus mr-2"></i>
                                    Nuevo Producto
                                </el-button>
                           </div>
                           <InputError :message="form.errors.items" class="mt-2" />
                        </div>

                        <div class="flex justify-end mt-8 col-span-full">
                            <SecondaryButton :disabled="!form.items.length || form.processing" :loading="form.processing" @click="store">
                                Guardar Solicitud
                            </SecondaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODALES DE CREACIÓN RÁPIDA -->
        <el-dialog v-model="branchModalVisible" title="Crear Cliente/Prospecto Rápido" width="30%">
            <form @submit.prevent="storeQuickBranch">
                <div class="space-y-4">
                    <TextInput label="Nombre*" v-model="quickBranchForm.name" type="text" :error="quickBranchForm.errors.name" />
                    <TextInput label="RFC" v-model="quickBranchForm.rfc" type="text" :error="quickBranchForm.errors.rfc" />
                </div>
            </form>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="branchModalVisible = false">Cancelar</el-button>
                    <el-button type="primary" @click="storeQuickBranch" :loading="quickBranchForm.processing">
                        Guardar
                    </el-button>
                </span>
            </template>
        </el-dialog>

        <el-dialog v-model="contactModalVisible" title="Crear Contacto Rápido" width="30%">
            <form @submit.prevent="storeQuickContact">
                <div class="space-y-4">
                    <TextInput label="Nombre*" v-model="quickContactForm.name" type="text" :error="quickContactForm.errors.name" />
                    <TextInput label="Cargo" v-model="quickContactForm.charge" type="text" :error="quickContactForm.errors.charge" />
                </div>
            </form>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="contactModalVisible = false">Cancelar</el-button>
                    <el-button type="primary" @click="storeQuickContact" :loading="quickContactForm.processing">
                        Guardar
                    </el-button>
                </span>
            </template>
        </el-dialog>

    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import InputLabel from "@/Components/InputLabel.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import TextInput from "@/Components/TextInput.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import InputError from "@/Components/InputError.vue";
import Back from "@/Components/MyComponents/Back.vue";
import { ElMessage } from 'element-plus';
import { useForm } from "@inertiajs/vue3";
import axios from 'axios';

export default {
    data() {
        const form = useForm({
            name: null,
            branch_id: null,
            contact_id: null,
            will_be_returned: false,
            expected_devolution_date: null,
            comments: null,
            items: [],
        });

        return {
            form,
            localBranches: [],
            availableContacts: [],
            branchModalVisible: false,
            contactModalVisible: false,
            quickBranchForm: {
                name: '',
                rfc: '',
                processing: false,
                errors: {},
            },
            quickContactForm: {
                name: '',
                charge: '',
                processing: false,
                errors: {},
            },
        };
    },
    components: {
        Back,
        AppLayout,
        TextInput,
        InputLabel,
        InputError,
        FileUploader,
        SecondaryButton,
    },
    props: {
        branches: Array,
        products: Array,
    },
    methods: {
        store() {
            this.form.post(route("sample-trackings.store"), {
                onSuccess: () => {
                    ElMessage.success('Solicitud de muestra registrada');
                    this.form.reset(); 
                },
                onError: (errors) => {
                    console.log(errors);
                    this.$refs.formContainer.scrollIntoView({ behavior: 'smooth' });
                    ElMessage.error('Por favor, revisa los errores en el formulario.');
                }
            });
        },
        disabledDate(time) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return time.getTime() < today.getTime();
        },
        handleBranchChange(branchId) {
            this.form.contact_id = null;
            const selectedBranch = this.localBranches.find(b => b.id === branchId);
            this.availableContacts = selectedBranch ? selectedBranch.contacts : [];
        },
        addItem(type) {
            if (type === 'catalog') {
                this.form.items.push({
                    type: 'catalog',
                    itemable_id: null,
                    quantity: 1,
                    notes: '',
                    product_data: null, 
                    loading: false,
                });
            } else {
                this.form.items.push({
                    type: 'new',
                    name: '',
                    description: '',
                    quantity: 1,
                    media: [],
                });
            }
        },
        removeItem(index) {
            this.form.items.splice(index, 1);
        },
        async getProductData(item) {
            if (!item.itemable_id) return;
            item.loading = true;
            item.product_data = null; 

            try {
                const response = await axios.get(route('products.get-media', item.itemable_id));
                if (response.status === 200) {
                    item.product_data = response.data.product;
                }
            } catch (error) {
                console.error(error);
                ElMessage.error('No se pudo cargar la información del producto.');
            } finally {
                item.loading = false;
            }
        },
        calculateTotalStock(storages) {
            if (!storages || !storages.length) return 0;
            return storages.reduce((total, storage) => total + (storage.quantity || 0), 0);
        },
        // --- MÉTODOS NUEVOS PARA CREACIÓN RÁPIDA ---
        async storeQuickBranch() {
            this.quickBranchForm.processing = true;
            this.quickBranchForm.errors = {};
            try {
                const response = await axios.post(route('branches.quick-store'), this.quickBranchForm);
                if (response.status === 200) {
                    const newBranch = response.data;
                    this.localBranches.push(newBranch);
                    this.form.branch_id = newBranch.id;
                    this.handleBranchChange(newBranch.id);
                    this.branchModalVisible = false;
                    this.quickBranchForm.name = '';
                    this.quickBranchForm.rfc = '';
                    ElMessage.success('Cliente/Prospecto creado exitosamente');
                }
            } catch (error) {
                if (error.response && error.response.status === 422) {
                    const validationErrors = error.response.data.errors;
                    Object.keys(validationErrors).forEach(key => {
                        this.quickBranchForm.errors[key] = validationErrors[key][0];
                    });
                } else {
                    console.error(error);
                    ElMessage.error('Ocurrió un error al crear el cliente.');
                }
            } finally {
                this.quickBranchForm.processing = false;
            }
        },
        async storeQuickContact() {
            if (!this.form.branch_id) {
                 ElMessage.warning('Primero debes seleccionar un cliente.');
                 return;
            }
            this.quickContactForm.processing = true;
            this.quickContactForm.errors = {};
            try {
                const response = await axios.post(route('branches.quick-store.contact', { branch: this.form.branch_id }), this.quickContactForm);
                if (response.status === 200) {
                    const newContact = response.data;
                    this.availableContacts.push(newContact);
                    
                    const parentBranch = this.localBranches.find(b => b.id === this.form.branch_id);
                    if (parentBranch) {
                        parentBranch.contacts.push(newContact);
                    }

                    this.form.contact_id = newContact.id;
                    this.contactModalVisible = false;
                    this.quickContactForm.name = '';
                    this.quickContactForm.charge = '';
                    ElMessage.success('Contacto creado exitosamente');
                }
            } catch (error) {
                if (error.response && error.response.status === 422) {
                    const validationErrors = error.response.data.errors;
                    Object.keys(validationErrors).forEach(key => {
                        this.quickContactForm.errors[key] = validationErrors[key][0];
                    });
                } else {
                    console.error(error);
                    ElMessage.error('Ocurrió un error al crear el contacto.');
                }
            } finally {
                this.quickContactForm.processing = false;
            }
        },
    },
    created() {
        this.localBranches = [...this.branches];
    },
    watch: {
        'form.will_be_returned'(newValue) {
            if (!newValue) {
                this.form.expected_devolution_date = null;
            }
        },
        branches(newVal) {
            this.localBranches = [...newVal];
        },
    }
};
</script>
