<template>
    <AppLayout title="Crear Producto">
        <div class="flex justify-between items-center">
            <Back :href="route('catalog-products.index')" />
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                Agregar nuevo producto
            </h2>
        </div>

        <div class="max-w-3xl mx-auto p-4 sm:p-6 lg:p-8">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-6">
                <form @submit.prevent="store">
                    <div ref="formContainer" class="space-y-3">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white text-right border-b border-gray-200 dark:border-slate-700 pb-2 mb-4">
                            Información del producto
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <InputLabel value="Tipo de producto*" />
                                <el-select v-model="form.product_type_key" placeholder="Selecciona" class="w-full">
                                    <el-option v-for="item in productTypeOptions" :key="item.key" :label="item.label" :value="item.key" />
                                </el-select>
                                <InputError :message="form.errors.product_type_key" class="mt-1" />
                            </div>

                            <div>
                                <InputLabel>
                                    <div class="flex items-center justify-between">
                                        <span>Familia de producto *</span>
                                        <button @click="showCreateFamilyModal = true" type="button" class="text-primary hover:scale-125 transition-transform">
                                            <i class="fa-solid fa-circle-plus"></i>
                                        </button>
                                    </div>
                                </InputLabel>
                                <el-select v-model="form.product_family_id" filterable placeholder="Selecciona" class="w-full">
                                    <el-option v-for="item in product_families" :key="item.id" :label="item.name" :value="item.id">
                                        <span style="float: left">{{ item.name }}</span>
                                        <span style="float: right; color: #cccccc; font-size: 13px;">
                                            {{ item.key }}
                                        </span>
                                    </el-option>
                                </el-select>
                                <InputError :message="form.errors.product_family_id" class="mt-1" />
                            </div>

                            <div>
                                <InputLabel>
                                    <div class="flex items-center justify-between">
                                        <span>Marca del producto *</span>
                                         <button @click="showCreateBrandModal = true" type="button" class="text-primary hover:scale-125 transition-transform">
                                            <i class="fa-solid fa-circle-plus"></i>
                                        </button>
                                    </div>
                                </InputLabel>
                                <el-select v-model="form.brand_id" filterable placeholder="Selecciona" class="w-full">
                                    <el-option v-for="item in brands" :key="item.id" :label="item.name" :value="item.id" />
                                </el-select>
                                <InputError :message="form.errors.brand_id" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <TextInput v-model="form.name" label="Nombre del producto*" :error="form.errors.name" placeholder="Ej. Llavero metálico Ford" />
                            <TextInput v-model="form.code" label="Código (se genera automáticamente)" :error="form.errors.code" :disabled="true" />
                            <TextInput v-model="form.caracteristics" placeholder="Diferenciador de otros productos similares: color de letras, color de fondo, forma, tamaño, etc."
                                label="Características (opcional)" :error="form.errors.caracteristics"
                                class="col-span-full" :isTextarea="true" :withMaxLength="true" :maxLength="255" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div>
                                <InputLabel value="Material*" />
                                <el-select v-model="form.material" placeholder="Selecciona" class="w-full">
                                    <el-option v-for="item in materialOptions" :key="item.key" :label="item.label" :value="item.key" />
                                </el-select>
                                <InputError :message="form.errors.material" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Unidad de medida" />
                                <el-select v-model="form.measure_unit" clearable placeholder="Selecciona la unidad de medida"
                                    no-data-text="No hay unidades de medida registradas"
                                    no-match-text="No se encontraron coincidencias">
                                    <el-option v-for="(item, index) in mesureUnits" :key="index" :label="item" :value="item" />
                                </el-select>
                                <InputError :message="form.errors.measure_unit" class="mt-1" />
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                             <TextInput v-model="form.min_quantity" label="Cantidad mínima" :error="form.errors.min_quantity" type="number" placeholder="Ej. 100" />
                             <TextInput v-model="form.max_quantity" label="Cantidad máxima" :error="form.errors.max_quantity" type="number" placeholder="Ej. 1000" />
                             <TextInput 
                                v-model="form.cost" 
                                :error="form.errors.cost"
                                label="Cuánto le cuesta a E3D"
                                :formatAsNumber="true">
                                <template #icon-left>
                                    <i class="fa-solid fa-dollar-sign"></i>
                                </template>
                            </TextInput>
                        </div>

                        <div class="space-y-4 p-4 border border-gray-200 dark:border-slate-700 rounded-lg">
                            <label class="flex items-center">
                                <Checkbox v-model:checked="form.is_circular" name="is_circular" class="bg-transparent text-indigo-500 border-gray-500" />
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Es circular</span>
                            </label>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 animate-fade-in">
                                <TextInput v-model="form.width" label="Ancho/Grosor (mm)*" :error="form.errors.width" type="number" placeholder="Ej. 5.5" />
                                
                                <TextInput v-if="!form.is_circular" v-model="form.large" label="Largo (mm)*" :error="form.errors.large" type="number" placeholder="Ej. 50" />
                                <TextInput v-if="!form.is_circular" v-model="form.height" label="Alto (mm)*" :error="form.errors.height" type="number" placeholder="Ej. 25" />
                                
                                <TextInput v-if="form.is_circular" v-model="form.diameter" label="Diámetro (mm)*" :error="form.errors.diameter" type="number" placeholder="Ej. 30" />
                            </div>
                        </div>

                        <div>
                            <InputLabel value="Imágenes del producto (máx. 5)" />
                            <FileUploader @files-selected="form.media = $event" acceptedFormat="image/*" :multiple="true" :maxFiles="5" class="mt-1" />
                            <InputError :message="form.errors.media" class="mt-2" />
                        </div>

                        <div class="border-t border-gray-200 dark:border-slate-700 pt-6 flex justify-end">
                            <SecondaryButton :loading="form.processing">
                                Crear producto
                            </SecondaryButton>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <DialogModal :show="showCreateFamilyModal" @close="showCreateFamilyModal = false">
             <template #title>Crear nueva familia de producto</template>
             <template #content>
                <div class="space-y-4">
                    <TextInput v-model="familyForm.name" label="Nombre de la familia*" :error="familyForm.errors.name" placeholder="Ej. Porta-placas" />
                    <TextInput @keyup.enter="storeFamily" v-model="familyForm.key" label="Clave (abreviación)*" :error="familyForm.errors.key" placeholder="Ej. PP (máx. 3 letras)" :maxLength="3" />
                </div>
             </template>
             <template #footer>
                <div class="flex items-center space-x-3">
                    <CancelButton @click="showCreateFamilyModal = false" :disabled="familyForm.processing">Cancelar</CancelButton>
                    <SecondaryButton @click="storeFamily" :loading="familyForm.processing">Crear</SecondaryButton>
                </div>
             </template>
        </DialogModal>

         <DialogModal :show="showCreateBrandModal" @close="showCreateBrandModal = false">
             <template #title>Crear nueva marca</template>
             <template #content>
                <TextInput @keyup.enter="storeBrand" v-model="brandForm.name" label="Nombre de la marca*" :error="brandForm.errors.name" placeholder="Ej. Ford" />
             </template>
             <template #footer>
                <div class="flex items-center space-x-3">
                 <CancelButton @click="showCreateBrandModal = false" :disabled="brandForm.processing">Cancelar</CancelButton>
                 <SecondaryButton @click="storeBrand" :loading="brandForm.processing">Crear</SecondaryButton>
                </div>
             </template>
        </DialogModal>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import Checkbox from "@/Components/Checkbox.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import Back from "@/Components/MyComponents/Back.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import DialogModal from "@/Components/DialogModal.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import { ElMessage } from 'element-plus';
import { useForm } from "@inertiajs/vue3";

export default {
    // Definición en Options API
    components: { AppLayout, SecondaryButton, InputError, InputLabel, TextInput, Back, FileUploader, DialogModal, CancelButton, Checkbox },
    props: {
        brands: Array,
        product_families: Array,
        consecutive: Number,
    },
    data() {
        return {
            form: useForm({
                name: null,
                code: null,
                cost: null,
                caracteristics: null,
                brand_id: null,
                product_family_id: null,
                product_type_key: 'C', // 'C' para Catálogo por defecto
                material: null,
                measure_unit: 'Pieza(s)',
                min_quantity: 1,
                max_quantity: null,
                is_circular: false,
                width: null,
                large: null,
                height: null,
                diameter: null,
                media: [],
            }),
            familyForm: useForm({
                name: null,
                key: null,
            }),
            brandForm: useForm({
                name: null,
            }),
            showCreateFamilyModal: false,
            showCreateBrandModal: false,
            productTypeOptions: [
                { label: 'Catálogo', key: 'C' },
                { label: 'Materia Prima', key: 'MP' },
                { label: 'Insumo', key: 'I' },
            ],
            materialOptions: [
                { label: 'Metal', key: 'M' },
                { label: 'Plástico', key: 'PLS' },
                { label: 'Piel de lujo', key: 'PL' },
                { label: 'Original', key: 'O' },
                { label: 'Lujo', key: 'L' },
                { label: 'Piel', key: 'P' },
                { label: 'Zamak', key: 'ZK' },
            ],
            mesureUnits: [
                'Pieza(s)',
                'Litro(s)',
                'Par(es)',
                'kilogramo(s)',
                'Metro(s)',
                'Rollo(s)',
                'Galon(es)',
                'Cubeta(s)',
                'Bote(s)',
            ],
        };
    },
    watch: {
        // Observadores para generar el número de parte automáticamente
        'form.product_type_key': 'generatePartNumber',
        'form.product_family_id': 'generatePartNumber',
        'form.material': 'generatePartNumber',
        
        // Limpiar dimensiones si cambia la forma del producto
        'form.is_circular'(isCircular) {
            if (isCircular) {
                this.form.large = null;
                this.form.height = null;
            } else {
                this.form.diameter = null;
            }
        }
    },
    methods: {
        store() {
            this.form.post(route("catalog-products.store"), {
                onSuccess: () => {
                    ElMessage.success('Producto creado con éxito');
                    this.form.reset();
                },
                onError: () => {
                    ElMessage.error('Hubo un problema al crear el producto. Revisa los campos.'),
                    // Agrega esta línea para hacer scroll al inicio
                    this.$refs.formContainer.scrollIntoView({ behavior: 'smooth' });
                }
            });
        },
        storeFamily() {
            this.familyForm.post(route('product-families.store'), {
                onSuccess: () => {
                    ElMessage.success('Familia creada');
                    this.showCreateFamilyModal = false;
                    this.familyForm.reset();
                    // Inertia recargará los props automáticamente
                },
                preserveScroll: true,
            });
        },
        storeBrand() {
            this.brandForm.post(route('brands.store'), {
                onSuccess: () => {
                    ElMessage.success('Marca creada');
                    this.showCreateBrandModal = false;
                    this.brandForm.reset();
                },
                 preserveScroll: true,
            });
        },
        generatePartNumber() {
            const type = this.form.product_type_key || '';
            const familyObj = this.product_families.find(f => f.id === this.form.product_family_id);
            const family = familyObj ? familyObj.key.toUpperCase() : '';
            const material = this.form.material || '';
            const id = this.consecutive || 'XXX';

            if (type && family && material) {
                this.form.code = `${type}-${family}-${material}-${id}`;
            } else {
                this.form.code = '';
            }
        },
    },
    mounted() {
        // Generar número de parte inicial si hay datos
        this.generatePartNumber();
    }
};
</script>

<style>
/* Animación para la aparición suave */
.animate-fade-in {
  animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>