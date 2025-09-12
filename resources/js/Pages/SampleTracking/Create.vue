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
                            <!-- Selector de Cliente -->
                            <div>
                                <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Cliente/Prospecto*</label>
                                <el-select v-model="form.branch_id" filterable placeholder="Selecciona un cliente" class="!w-full" @change="handleBranchChange">
                                    <el-option v-for="branch in branches" :key="branch.id" :label="branch.name" :value="branch.id" />
                                </el-select>
                                <InputError :message="form.errors.branch_id" />
                            </div>

                            <!-- Selector de Contacto -->
                            <div>
                                <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Contacto*</label>
                                <el-select v-model="form.contact_id" filterable placeholder="Selecciona un contacto" class="!w-full" :disabled="!form.branch_id">
                                    <el-option v-for="contact in availableContacts" :key="contact.id" :label="contact.name" :value="contact.id" />
                                </el-select>
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
                                        <div>
                                            <el-select v-model="item.itemable_id" filterable placeholder="Selecciona un producto" class="!w-full">
                                                <el-option v-for="product in products" :key="product.id" :label="product.name" :value="product.id" />
                                            </el-select>
                                            <InputError :message="form.errors[`items.${index}.itemable_id`]" />
                                        </div>

                                        <div>
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
                                            <!-- Corregido: @files-selected ahora actualiza item.media -->
                                            <FileUploader @files-selected="item.media = $event" format="Imagen" :multiple="true" :maxFiles="2" class="mt-1" />
                                            <!-- Corregido: Mensaje de error apunta al item correcto -->
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
                           
                           <!-- Botones para agregar items -->
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
                            <SecondaryButton :disabled="!form.items.length || form.processing" :loading="form.processing">
                                Guardar Solicitud
                            </SecondaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
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

export default {
    data() {
        const form = useForm({
            branch_id: null,
            contact_id: null,
            will_be_returned: false,
            expected_devolution_date: null,
            comments: null,
            items: [],
        });

        return {
            form,
            availableContacts: [],
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
        contacts: Array,
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
            const selectedBranch = this.branches.find(b => b.id === branchId);
            this.availableContacts = selectedBranch ? selectedBranch.contacts : [];
        },
        addItem(type) {
            if (type === 'catalog') {
                this.form.items.push({
                    type: 'catalog',
                    itemable_id: null,
                    quantity: 1,
                    notes: '', // Agregado: inicializar notas
                });
            } else {
                this.form.items.push({
                    type: 'new',
                    name: '',
                    description: '',
                    quantity: 1,
                    media: [], // Corregido: inicializar como array para las imágenes
                });
            }
        },
        removeItem(index) {
            this.form.items.splice(index, 1);
        },
    },
    watch: {
        // si se desactiva la devolucion, limpiar la fecha
        'form.will_be_returned'(newValue) {
            if (!newValue) {
                this.form.expected_devolution_date = null;
            }
        }
    }
};
</script>
