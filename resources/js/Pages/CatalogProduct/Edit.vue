<template>
    <AppLayout title="Editar Producto">
        <div class="flex justify-between items-center">
            <!-- El botón de regreso ahora apunta a la vista del producto -->
            <Back :href="route('catalog-products.show', catalog_product.id)" />
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                Editar producto: {{ form.name }}
            </h2>
        </div>

        <div class="max-w-4xl mx-auto p-4 sm:p-6 lg:p-8">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-6">
                <!-- El formulario ahora llama al método 'update' -->
                <form @submit.prevent="update">
                    <div ref="formContainer" class="space-y-3">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white text-right border-b border-gray-200 dark:border-slate-700 pb-2 mb-4">
                            Información del producto
                        </h3>

                        <!-- El resto del formulario es casi idéntico al de 'Create.vue' -->
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
                                    <el-option v-for="item in product_families" :key="item.id" :label="item.name" :value="item.id" />
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

                        <!-- ... (resto de los campos del formulario como en Create.vue) ... -->
                        <!-- Ejemplo de un campo -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <TextInput v-model="form.name" label="Nombre del producto*" :error="form.errors.name" placeholder="Ej. Llavero metálico Ford" />
                            <!-- El código se muestra pero no se puede editar para evitar inconsistencias -->
                            <TextInput v-model="form.code" label="Código" :error="form.errors.code" :disabled="true" />
                            <TextInput v-model="form.caracteristics" placeholder="Diferenciador de otros productos similares" label="Características (opcional)" :error="form.errors.caracteristics" class="col-span-full" :isTextarea="true" />
                             <TextInput v-model="form.location" label="Ubicación en almacén" :error="form.errors.location" type="text" placeholder="Ej. Rack A estante 2" />
                        </div>
                        
                        <!-- ... (Secciones de componentes, procesos, etc. van aquí) ... -->

                        <!-- Sección para mostrar imágenes existentes y subir nuevas -->
                        <div>
                            <InputLabel value="Imágenes del producto" />
                            <!-- Mostrar imágenes actuales -->
                            <div v-if="existingMedia.length" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-3 mb-4">
                                <div v-for="image in existingMedia" :key="image.id" class="relative group">
                                    <img :src="image.original_url" class="w-full h-24 object-cover rounded-lg shadow-md">
                                    <!-- Opcional: Botón para eliminar imagen -->
                                    <button @click="deleteImage(image.id)" type="button" class="absolute top-1 right-1 size-6 bg-red-600 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </div>
                            </div>
                            <!-- Uploader para nuevas imágenes -->
                            <FileUploader @files-selected="form.media = $event" acceptedFormat="image/*" :multiple="true" :maxFiles="3" class="mt-1" />
                            <InputError :message="form.errors.media" class="mt-2" />
                        </div>


                        <div class="border-t border-gray-200 dark:border-slate-700 pt-6 flex justify-end">
                            <SecondaryButton :loading="form.processing">
                                Guardar Cambios
                            </SecondaryButton>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- ... (Tus modales para crear familia y marca van aquí) ... -->
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
import { ElMessage, ElMessageBox } from 'element-plus';
import { useForm } from "@inertiajs/vue3";
import axios from 'axios';

export default {
    components: { AppLayout, SecondaryButton, InputError, InputLabel, TextInput, Back, FileUploader, DialogModal, CancelButton, Checkbox },
    props: {
        catalog_product: Object, // Prop con los datos del producto
        brands: Array,
        product_families: Array,
        production_processes: Array,
        raw_materials: Array,
    },
    data() {
        // Mapeo inverso para los selectores
        const materialKey = Object.keys(this.materialOptions).find(key => this.materialOptions[key] === this.catalog_product.material);

        return {
            form: useForm({
                _method: 'PUT', // Clave para que Laravel trate el POST como PUT
                name: this.catalog_product.name,
                code: this.catalog_product.code,
                caracteristics: this.catalog_product.caracteristics,
                cost: this.catalog_product.cost,
                base_price: this.catalog_product.base_price,
                brand_id: this.catalog_product.brand_id,
                product_type_key: this.catalog_product.product_type_key,
                product_family_id: this.catalog_product.product_family_id,
                material: materialKey,
                measure_unit: this.catalog_product.measure_unit,
                min_quantity: this.catalog_product.min_quantity,
                max_quantity: this.catalog_product.max_quantity,
                is_circular: this.catalog_product.is_circular,
                width: this.catalog_product.width,
                large: this.catalog_product.large,
                height: this.catalog_product.height,
                diameter: this.catalog_product.diameter,
                location: this.catalog_product.storages[0]?.location,
                media: [], // Para archivos nuevos
                // Mapea los componentes y procesos existentes al formato del formulario
                components: this.catalog_product.components.map(c => ({ product_id: c.id, quantity: c.pivot.quantity })),
                production_processes: this.catalog_product.production_costs.map(p => ({ process_id: p.id, time: 'N/A', cost: p.cost })),
            }),
            existingMedia: this.catalog_product.media, // Para mostrar imágenes actuales
            // ... (el resto de tu 'data' como en Create.vue: familyForm, brandForm, currentComponent, etc.)
        };
    },
    methods: {
        update() {
            // El método post de Inertia se encarga de enviar el _method: 'PUT'
            this.form.post(route("catalog-products.update", this.catalog_product.id), {
                onSuccess: () => {
                    ElMessage.success('Producto actualizado con éxito');
                },
                onError: (errors) => {
                    console.log(errors);
                    ElMessage.error('Hubo un problema al actualizar el producto. Revisa los campos.');
                    this.$refs.formContainer.scrollIntoView({ behavior: 'smooth' });
                }
            });
        },
        deleteImage(imageId) {
            ElMessageBox.confirm('¿Estás seguro de que deseas eliminar esta imagen? Esta acción no se puede deshacer.', 'Confirmar eliminación', {
                confirmButtonText: 'Eliminar',
                cancelButtonText: 'Cancelar',
                type: 'warning',
            }).then(() => {
                this.$inertia.delete(route('media.destroy', imageId), {
                    preserveScroll: true,
                    onSuccess: () => {
                        // Actualiza la lista de imágenes existentes sin recargar la página
                        this.existingMedia = this.existingMedia.filter(img => img.id !== imageId);
                        ElMessage.success('Imagen eliminada');
                    },
                    onError: () => {
                        ElMessage.error('No se pudo eliminar la imagen');
                    }
                });
            }).catch(() => {});
        },
        // ... (el resto de tus métodos como en Create.vue: storeFamily, addComponent, etc.)
    },
};
</script>
