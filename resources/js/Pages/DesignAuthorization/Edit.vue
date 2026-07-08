<template>
    <AppLayout title="Editar Autorización de Diseño">
        <div class="px-4 sm:px-0">
            <div class="flex items-center space-x-2">
                <Back />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Editar autorización de diseño #{{ authorization.id }}
                </h2>
            </div>
        </div>

        <div ref="formContainer" class="py-7">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6 md:p-8">

                    <form @submit.prevent="update" class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">

                        <div class="md:col-span-2">
                            <label class="text-sm ml-3 text-gray-700 dark:text-gray-100">Orden de Diseño*</label>
                            <el-select v-model="form.design_order_id" placeholder="Selecciona una orden de diseño" class="w-full" filterable disabled>
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
                         <div v-else class="md:col-span-2">
                             <label class="text-sm ml-3 text-gray-700 dark:text-gray-100">Imagen de portada actual</label>
                             <img :src="cover_image_url" class="w-32 h-32 object-cover rounded-lg border dark:border-gray-700">
                             <p class="text-xs text-gray-500 ml-3 mt-2">No hay más imágenes en la orden de diseño para seleccionar.</p>
                         </div>

                        <div>
                            <TextInput label="Versión del Formato*" v-model="form.version" type="text" :error="form.errors.version" placeholder="Ej. 1.0" />
                        </div>

                        <div>
                            <label class="text-sm ml-3 text-gray-700 dark:text-gray-100">Tipo de producto*</label>
                            <el-select v-model="form.product_type" placeholder="Selecciona un tipo" class="w-full mt-1" filterable>
                                <el-option v-for="item in productTypeOptions" :key="item" :label="item" :value="item" />
                            </el-select>
                            <InputError :message="form.errors.product_type" />
                        </div>

                        <div>
                            <TextInput label="Nombre del producto*" v-model="form.product_name" type="text" :error="form.errors.product_name" placeholder="Ej. Llavero MG con grabado" />
                        </div>

                        <div>
                            <label class="text-sm ml-3 text-gray-700 dark:text-gray-100">Material</label>
                            <el-select v-model="form.material" placeholder="Selecciona el material" class="w-full mt-1" filterable allow-create>
                                <el-option v-for="item in materialOptions" :key="item" :label="item" :value="item" />
                            </el-select>
                            <InputError :message="form.errors.material" />
                        </div>

                        <!-- Lista de Pantones Dinámica para Edición -->
                        <div class="col-span-1 md:col-span-2">
                            <label class="text-sm ml-3 text-gray-700 dark:text-gray-100">Pantones</label>
                            <div class="flex gap-2 items-end mt-1">
                                <div class="flex-1">
                                    <input 
                                        type="text" 
                                        v-model="currentPantoneName" 
                                        @keyup.enter.prevent="addPantone"
                                        class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full h-10" 
                                        placeholder="Ej. 186 C (Presiona Enter para agregar)" 
                                    />
                                </div>
                                <div class="w-16 flex flex-col justify-end">
                                    <input type="color" v-model="currentPantoneColor" class="h-10 w-full rounded cursor-pointer border border-gray-300 bg-white p-0 shadow-sm" title="Elige un color representativo" />
                                </div>
                                <button type="button" @click="addPantone" class="bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-800 px-4 py-2 rounded-md hover:bg-gray-700 dark:hover:bg-gray-300 transition h-10 font-semibold text-sm">
                                    Agregar
                                </button>
                            </div>
                            <InputError :message="form.errors.pantone" />

                            <!-- Chips de Pantones Agregados -->
                            <div v-if="form.pantone.length > 0" class="mt-3 flex flex-wrap gap-2">
                                <div v-for="(item, index) in form.pantone" :key="index" class="flex items-center gap-2 bg-gray-100 dark:bg-gray-800 py-1.5 px-3 rounded-full border border-gray-200 dark:border-gray-700">
                                    <div class="w-5 h-5 rounded-full shadow-sm border border-gray-300" :style="{ backgroundColor: item.color }"></div>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ item.name }}</span>
                                    <button type="button" @click="removePantone(index)" class="text-red-500 hover:text-red-700 ml-1 rounded-full p-0.5 hover:bg-red-100 dark:hover:bg-red-900 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Mostrar Captura Actual de Pantones si existe -->
                            <div v-if="pantone_capture_url" class="mt-4">
                                <label class="text-sm text-gray-700 dark:text-gray-100 font-medium">Captura de pantones actual</label>
                                <div class="mt-2">
                                    <img :src="pantone_capture_url" class="w-32 h-32 object-contain rounded-lg border dark:border-gray-700">
                                </div>
                            </div>

                            <!-- NUEVO CAMPO: Captura de Pantones -->
                            <div class="mt-4">
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-2 font-medium">Si no conoces los pantones o deseas actualizar la imagen, puedes subir una captura de ellos.</p>
                                <FileUploader @files-selected="form.pantone_media = $event" :multiple="false" format="Imagen" :max-files="1" />
                                <InputError :message="form.errors.pantone_media" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <TextInput label="Medidas" v-model="form.dimensions" type="text" :error="form.errors.dimensions" placeholder="Ej. 80mm x 35mm" />
                        </div>

                         <div class="md:col-span-2">
                            <label class="text-sm ml-3 text-gray-700 dark:text-gray-100">Técnica / Proceso de Impresión</label>
                            <el-select
                                v-model="form.production_methods"
                                multiple
                                filterable
                                allow-create
                                default-first-option
                                :reserve-keyword="false"
                                placeholder="Ej. Serigrafía, Grabado Láser"
                                class="w-full mt-1"
                            >
                                <el-option v-for="item in productionMethodsOptions" :key="item" :label="item" :value="item" />
                            </el-select>
                             <InputError :message="form.errors.production_methods" />
                        </div>

                        <div class="md:col-span-2">
                            <TextInput v-model="form.specifications" :label="'Especificaciones'" :isTextarea="true" rows="3" class="textarea" placeholder="Detalles adicionales del producto..." />
                            <InputError :message="form.errors.specifications" />
                        </div>

                        <div class="md:col-span-2 mt-4 border-t pt-6 dark:border-gray-700">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Datos Comerciales y Logísticos</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <div>
                                    <TextInput label="Tiempo de entrega" v-model="form.delivery_time" type="text" :error="form.errors.delivery_time" placeholder="Ej. 5-6 días" />
                                </div>
                                <div>
                                    <TextInput label="Volumen mínimo" v-model="form.minimum_volume" type="number" :error="form.errors.minimum_volume" placeholder="Ej. 100" />
                                </div>
                                <div>
                                    <TextInput label="Precio por unidad ($)" v-model="form.unit_price" type="number" step="0.01" :error="form.errors.unit_price" placeholder="Ej. 69.55" />
                                </div>
                                <div>
                                    <TextInput label="Herramental de impresión ($)" v-model="form.printing_tooling_cost" type="number" step="0.01" :error="form.errors.printing_tooling_cost" placeholder="Ej. 600.00" />
                                </div>
                                <div>
                                    <TextInput label="Herramental de inyección ($)" v-model="form.injection_tooling_cost" type="number" step="0.01" :error="form.errors.injection_tooling_cost" placeholder="Ej. 24000.00" />
                                </div>
                                <div>
                                    <TextInput label="Costo de flete ($)" v-model="form.freight_cost" type="number" step="0.01" :error="form.errors.freight_cost" placeholder="Ej. 750.00" />
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-2 mt-4">
                            <label class="text-sm ml-3 text-gray-700 dark:text-gray-100">Cliente (Sucursal y Contacto)*</label>
                             <div class="flex items-center space-x-2 mt-1">
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
                            <el-select v-model="form.seller_id" placeholder="Selecciona un vendedor" class="w-full mt-1" filterable>
                                <el-option v-for="item in sellers" :key="item.id" :label="item.name" :value="item.id" />
                            </el-select>
                            <InputError :message="form.errors.seller_id" />
                        </div>

                        <div class="flex justify-end mt-5 col-span-full">
                            <SecondaryButton :loading="form.processing">
                                Actualizar Autorización
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
import TextInput from "@/Components/TextInput.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue"; // AGREGADO
import Back from "@/Components/MyComponents/Back.vue";
import { ElMessage } from 'element-plus';
import { useForm } from "@inertiajs/vue3";
import axios from 'axios';

export default {
    data() {
        // Lógica de compatibilidad hacia atrás: 
        // Convierte el pantone a array si viene como un string (formatos viejos)
        let initialPantone = [];
        if (Array.isArray(this.authorization.pantone)) {
            initialPantone = [...this.authorization.pantone];
        } else if (typeof this.authorization.pantone === 'string' && this.authorization.pantone.trim() !== '') {
            initialPantone = [{ 
                name: this.authorization.pantone, 
                color: this.authorization.pantone_color || '#E3000F' 
            }];
        }

        return {
            form: useForm({
                design_order_id: this.authorization.design_order_id,
                version: this.authorization.version || '1',
                product_type: this.authorization.product_type,
                product_name: this.authorization.product_name,
                material: this.authorization.material,
                color: this.authorization.color,
                pantone: initialPantone, // Usamos la variable normalizada
                pantone_color: this.authorization.pantone_color || '#E3000F', // Por si lo requiere el backend (puedes ignorarlo)
                pantone_media: null, // AGREGADO: Para la captura
                dimensions: this.authorization.dimensions,
                production_methods: this.authorization.production_methods,
                specifications: this.authorization.specifications,
                delivery_time: this.authorization.delivery_time,
                minimum_volume: this.authorization.minimum_volume,
                printing_tooling_cost: this.authorization.printing_tooling_cost,
                injection_tooling_cost: this.authorization.injection_tooling_cost,
                unit_price: this.authorization.unit_price,
                freight_cost: this.authorization.freight_cost,
                branch_id: this.authorization.branch_id,
                contact_id: this.authorization.contact_id,
                seller_id: this.authorization.seller_id,
                cover_media_id: null,
            }),
            currentPantoneName: '',      // Estado local para el input de texto del pantone
            currentPantoneColor: '#E3000F', // Estado local para el color picker
            productTypeOptions: [
                'Portaplaca', 'Emblema', 'Llavero', 'Parasol', 'Tapete', 'Manta', 
                'Carpeta', 'Separador', 'Portadocumentos', 'Termo', 'Placa de estireno', 
                'Etiqueta', 'Overlay', 'Pin', 'Prenda', 'Botella', 'Hielera', 
                'Funda para auto', 'Perfumero', 'Funda para llavero', 'Bocina', 
                'Carcasa', 'Banderín', 'Maletin'
            ],
            materialOptions: [
                'METAL', 'PLASTICO', 'PIEL DE LUJO', 'ORIGINAL', 'PIEL', 'ZAMAK', 
                'SOLIDCHROME', 'MICROMETAL', 'FLEXCHROME', 'ALUMINIO', 'ESTIRENO', 
                'ABS', 'PVC', 'TELA', 'CAUCHO', 'VINILPIEL', 'FIBRA DE CARBONO', 'OVERLAY'
            ],
            productionMethodsOptions: [
                'Serigrafía', 'Grabado Láser', 'Emblema Pegado', 'Inyección', 
                'Remachado de emblema', 'Cubierta de vinil', 'Sublimación', 'Tampografía'
            ],
            availableContacts: [],
            availableCoverImages: [],
            loadingImages: false,
        };
    },
    components: {
        AppLayout,
        SecondaryButton,
        InputError,
        TextInput,
        FileUploader, // AGREGADO
        Back,
    },
    props: {
        authorization: Object,
        designOrders: Array,
        sellers: Array,
        branches: Array,
        cover_image_url: String,
        pantone_capture_url: String, // AGREGADO: Para mostrar la captura existente
        current_cover_id: Number,
    },
    methods: {
        // --- Métodos para gestionar Pantones ---
        addPantone() {
            if (this.currentPantoneName.trim() === '') return;
            
            this.form.pantone.push({
                name: this.currentPantoneName.trim(),
                color: this.currentPantoneColor
            });

            // Limpiamos los inputs para agregar otro
            this.currentPantoneName = '';
            this.currentPantoneColor = '#E3000F';
        },
        removePantone(index) {
            this.form.pantone.splice(index, 1);
        },
        // ----------------------------------------------

        update() {
            // Se usa el método PUT para la actualización.
            // Si no se selecciona una nueva imagen, se debe omitir 'cover_media_id' o enviarlo como null
            // para que el backend no intente cambiarla.
            if (!this.form.cover_media_id) {
                delete this.form.cover_media_id;
            }

            this.form.put(route("design-authorizations.update", this.authorization.id), {
                onSuccess: () => {
                    ElMessage.success('Autorización actualizada correctamente');
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
            if (!orderId) return;

            this.loadingImages = true;
            try {
                const response = await axios.get(route('design-authorizations.get-files', orderId));
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
        // Precargar contactos de la sucursal actual
        this.handleBranchChange(this.form.branch_id);
        
        // Volvemos a colocar el contact_id ya que handleBranchChange lo limpia a null
        this.form.contact_id = this.authorization.contact_id;
        
        // Cargar imágenes de la orden de diseño actual
        this.handleDesignOrderChange(this.form.design_order_id);
        
        // Establecer la imagen de portada actual si existe
        this.form.cover_media_id = this.current_cover_id;
    }
};
</script>