<template>
    <AppLayout title="Crear Autorización de Diseño">
        <div class="px-4 sm:px-0">
            <div class="flex items-center space-x-2">
                <Back />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Crear nueva autorización de diseño
                </h2>
            </div>
        </div>

        <div ref="formContainer" class="py-7">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6 md:p-8">
                    
                    <form @submit.prevent="store" class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                        
                        <div class="md:col-span-2">
                            <label class="text-sm ml-3 text-gray-700 dark:text-gray-100">Orden de Diseño*</label>
                            <el-select v-model="form.design_order_id" placeholder="Selecciona una orden de diseño" class="w-full" filterable @change="handleDesignOrderChange">
                                <el-option v-for="item in designOrders" :key="item.id" :label="item.order_title" :value="item.id" />
                            </el-select>
                            <InputError :message="form.errors.design_order_id" />
                        </div>

                        <!-- Sección para seleccionar la imagen de portada -->
                        <div v-if="loadingImages" class="md:col-span-2 text-center text-gray-500">
                            Cargando imágenes...
                        </div>
                        <div v-else-if="availableCoverImages.length" class="md:col-span-2">
                            <label class="text-sm ml-3 text-gray-700 dark:text-gray-100">Imagen de portada*</label>
                            <p class="text-xs text-gray-500 ml-3 mb-2">Selecciona una de las imágenes de la orden de diseño.</p>
                            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2 rounded-lg p-2 border dark:border-gray-700">
                                <div v-for="image in availableCoverImages" :key="image.id" @click="form.cover_media_id = image.id"
                                    class="relative aspect-square rounded-md overflow-hidden cursor-pointer transition-all duration-200"
                                    :class="form.cover_media_id === image.id ? 'ring-2 ring-blue-500 scale-105' : 'hover:scale-105'">
                                    <img :src="image.original_url" :alt="image.name" class="w-full h-full object-cover">
                                    <div v-if="form.cover_media_id === image.id" class="absolute inset-0 bg-black/40 bg-opacity-40 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <InputError :message="form.errors.cover_media_id" />
                        </div>

                        <div>
                            <TextInput label="Nombre del producto*" v-model="form.product_name" type="text" :error="form.errors.product_name" placeholder="Ej. Portaplacas Nissan" />
                        </div>
                        
                        <div>
                            <TextInput label="Material" v-model="form.material" type="text" :error="form.errors.material" placeholder="Ej. Plástico ABS" />
                        </div>

                        <div>
                           <TextInput label="Color" v-model="form.color" type="text" :error="form.errors.color" placeholder="Ej. Negro con letras blancas" />
                        </div>

                         <div class="md:col-span-2">
                            <label class="text-sm ml-3 text-gray-700 dark:text-gray-100">Métodos de Producción</label>
                            <el-select
                                v-model="form.production_methods"
                                multiple
                                filterable
                                allow-create
                                default-first-option
                                :reserve-keyword="false"
                                placeholder="Ej. Serigrafía, Grabado Láser"
                                class="w-full"
                            >
                                <el-option v-for="item in productionMethodsOptions" :key="item" :label="item" :value="item" />
                            </el-select>
                             <InputError :message="form.errors.production_methods" />
                        </div>

                        <div class="md:col-span-2">
                            <TextInput v-model="form.specifications" :label="'Especificaciones'" :isTextarea="true" rows="3" class="textarea" placeholder="Detalles adicionales del producto..." />
                            <InputError :message="form.errors.specifications" />
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-sm ml-3 text-gray-700 dark:text-gray-100">Cliente (Sucursal y Contacto)*</label>
                             <div class="flex items-center space-x-2">
                                <el-select v-model="form.branch_id" placeholder="Sucursal" class="w-1/2" filterable @change="handleBranchChange">
                                    <el-option v-for="item in branches" :key="item.id" :label="item.name" :value="item.id" />
                                </el-select>
                                 <el-select v-model="form.contact_id" placeholder="Contacto" class="w-1/2" filterable :disabled="!form.branch_id" >
                                    <el-option v-for="item in availableContacts" :key="item.id" :label="item.name" :value="item.id" />
                                 </el-select>
                             </div>
                            <InputError :message="form.errors.branch_id" />
                            <InputError :message="form.errors.contact_id" />
                        </div>

                         <div>
                            <label class="text-sm ml-3 text-gray-700 dark:text-gray-100">Vendedor*</label>
                            <el-select v-model="form.seller_id" placeholder="Selecciona un vendedor" class="w-full" filterable>
                                <el-option v-for="item in sellers" :key="item.id" :label="item.name" :value="item.id" />
                            </el-select>
                            <InputError :message="form.errors.seller_id" />
                        </div>

                        <div v-if="!form.cover_media_id" class="md:col-span-2 mt-4">
                            <label class="text-sm ml-3 text-gray-700 dark:text-gray-100">Archivos adjuntos adicionales (imágenes, PDF)</label>
                            <FileUploader @files-selected="form.media = $event" :multiple="true" format="Imagen" :max-files="1" />
                            <InputError :message="form.errors.media" class="mt-2" />
                        </div>
                        
                        <div class="flex justify-end mt-5 col-span-full">
                            <SecondaryButton :loading="form.processing">
                                Guardar Autorización
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
import SecondaryButton from "@/Components/SecondaryButton.vue";
import InputError from "@/Components/InputError.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import TextInput from "@/Components/TextInput.vue";
import Back from "@/Components/MyComponents/Back.vue";
import { ElMessage } from 'element-plus';
import { useForm } from "@inertiajs/vue3";
import axios from 'axios';

export default {
    data() {
        const form = useForm({
            design_order_id: null,
            product_name: null,
            material: null,
            color: null,
            production_methods: [],
            specifications: null,
            branch_id: null,
            contact_id: null,
            seller_id: null,
            media: null,
            cover_media_id: null,
        });

        return {
            form,
            productionMethodsOptions: ['Serigrafía', 'Grabado Láser', 'Emblema Pegado', 'Inyección', 'Remadchado de emblema', 'Cubierta de vinil'],
            availableContacts: [],
            availableCoverImages: [],
            loadingImages: false, // Indicador de carga para la UI
        };
    },
    components: {
        AppLayout,
        SecondaryButton,
        InputError,
        FileUploader,
        TextInput,
        Back,
    },
    props: {
        designOrders: Array,
        sellers: Array,
        branches: Array,
        design_order: Object,
    },
    methods: {
        store() {
            this.form.post(route("design-authorizations.store"), {
                onSuccess: () => {
                    ElMessage.success('Autorización creada correctamente');
                    this.form.reset(); 
                    this.availableCoverImages = [];
                    this.availableContacts = [];
                },
                onError: () => {
                    this.$refs.formContainer.scrollIntoView({ behavior: 'smooth' });
                }
            });
        },
        handleBranchChange(branchId) {
            this.form.contact_id = null;
            const selectedBranch = this.branches.find(b => b.id === branchId);
            this.availableContacts = selectedBranch ? selectedBranch.contacts : [];
        },
        async handleDesignOrderChange(orderId) {
            this.form.cover_media_id = null;
            this.availableCoverImages = [];

            if (!orderId) return;

            this.loadingImages = true;
            try {
                const response = await axios.get(route('design-authorizations.get-files', orderId));
                // Filtramos para mostrar solo imágenes, en caso de que haya otros tipos de archivo
                this.availableCoverImages = response.data.filter(file => file.mime_type.startsWith('image/'));
            } catch (error) {
                console.error('Error al cargar los archivos de la orden:', error);
                ElMessage.error('No se pudieron cargar las imágenes de la orden.');
            } finally {
                this.loadingImages = false;
            }
        }
    },
    mounted() {
        if ( this.design_order ) {
            this.form.design_order_id = this.design_order.id;
            this.form.branch_id = this.design_order.branch_id;
            this.handleBranchChange(this.design_order.branch_id);
            this.form.contact_id = this.design_order.contact_id;
            this.form.seller_id = this.design_order.requester_id;
            this.handleDesignOrderChange();
        }
    }
};
</script>

