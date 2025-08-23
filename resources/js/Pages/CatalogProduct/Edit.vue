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
                             <TextInput v-model="form.current_stock" label="Estock inicial" :error="form.errors.current_stock" type="number" placeholder="Ej. 3,000" />
                             <TextInput v-model="form.location" label="Ubicación en almacén" :error="form.errors.location" type="text" placeholder="Ej. Rack A estante 2" />
                             <TextInput 
                                v-if="form.product_type_key !== 'C'"
                                v-model="form.cost" 
                                :error="form.errors.cost"
                                label="Cuánto le cuesta a E3D"
                                :formatAsNumber="true">
                                <template #icon-left>
                                    <i class="fa-solid fa-dollar-sign"></i>
                                </template>
                             </TextInput>
                             <TextInput 
                                v-if="form.product_type_key === 'C'"
                                v-model="form.base_price" 
                                :error="form.errors.base_price"
                                :helpContent="'Si no hay precio especial para el cliente se toma el precio base'"
                                label="Precio base para cliente*"
                                :formatAsNumber="true">
                                <template #icon-left>
                                    <i class="fa-solid fa-dollar-sign"></i>
                                </template>
                            </TextInput>
                            <div v-if="form.product_type_key === 'C'">
                                <InputLabel value="Moneda*" />
                                <el-select v-model="form.currency" clearable placeholder="Selecciona la moneda"
                                    no-data-text="No hay información"
                                    no-match-text="No se encontraron coincidencias">
                                    <el-option v-for="item in currencies" :key="item" :label="item" :value="item" />
                                </el-select>
                                <InputError :message="form.errors.currency" class="mt-1" />
                            </div>
                        </div>

                        <div class="space-y-4 p-4 border border-gray-200 dark:border-slate-700 rounded-lg">
                            <label class="flex items-center">
                                <Checkbox v-model:checked="form.is_circular" name="is_circular" class="bg-transparent text-indigo-500 border-gray-500" />
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Es circular</span>
                            </label>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 animate-fade-in">
                                <TextInput v-model="form.width" label="Ancho/Grosor (mm)" :error="form.errors.width" type="number" placeholder="Ej. 5.5" />
                                
                                <TextInput v-if="!form.is_circular" v-model="form.large" label="Largo (mm)" :error="form.errors.large" type="number" placeholder="Ej. 50" />
                                <TextInput v-if="!form.is_circular" v-model="form.height" label="Alto (mm)" :error="form.errors.height" type="number" placeholder="Ej. 25" />
                                
                                <TextInput v-if="form.is_circular" v-model="form.diameter" label="Diámetro (mm)" :error="form.errors.diameter" type="number" placeholder="Ej. 30" />
                            </div>
                        </div>

                        <!-- ================================================================== -->
                        <!-- ================== SECCIÓN DE COMPONENTES (MODIFICADA) ============= -->
                        <!-- ================================================================== -->
                        <div v-if="form.product_type_key === 'C'" class="space-y-4 p-4 border border-gray-200 dark:border-slate-700 rounded-lg mt-4 animate-fade-in">
                            <div class="flex items-center justify-between border-b border-gray-200 dark:border-slate-700 pb-2 mb-4">
                                <label class="flex items-center">
                                    <Checkbox v-model:checked="form.hasComponents" name="is_circular" />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Tiene componentes</span>
                                </label>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white text-right">
                                    Componentes (Materia Prima)
                                </h3>
                            </div>

                            <section v-if="form.hasComponents">
                                <!-- Buscador de Componentes -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <div>
                                        <InputLabel value="Buscar componente*" />
                                        <el-select
                                            @change="getComponentMedia"
                                            v-model="currentComponent.product_id"
                                            filterable
                                            placeholder="Selecciona los componentes"
                                            class="w-full"
                                            no-data-text="No hay materias primas registradas"
                                            no-match-text="No se encontraron coincidencias">
                                            <el-option v-for="item in raw_materials" :key="item.id" :label="item.name" :value="item.id" />
                                        </el-select>
                                    </div>
                                    <TextInput v-model="currentComponent.quantity" label="Cantidad necesaria*" type="number" placeholder="Ej. 1" />
                                    <LoadingIsoLogo v-if="loadingComponentMedia" />
                                    
                                    <!-- Tarjeta de materia prima seleccionada -->
                                    <div class="flex items-center space-x-4 p-2 bg-gray-100 dark:bg-slate-900/50 rounded-md col-span-full mb-2" v-else-if="currentComponent.media">
                                        <figure 
                                            v-if="currentComponent.media" 
                                            class="relative flex items-center justify-center size-32 rounded-2xl border border-gray-200 dark:border-slate-900 overflow-hidden shadow-lg transition transform hover:shadow-xl">
                                            <img v-if="currentComponent.media?.length"
                                                :src="currentComponent.media[0]?.original_url" 
                                                alt="" 
                                                class="rounded-2xl w-full h-auto object-cover transition duration-300 ease-in-out hover:opacity-95"
                                            >
                                            <div v-else class="flex flex-col items-center justify-center text-gray-400 dark:text-slate-500">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                                </svg>
                                            <p>Sin imagen</p>
                                            </div>
                                            <!-- Overlay degradado sutil -->
                                            <div class="absolute inset-0 bg-gradient-to-t from-black/10 via-transparent to-black/5"></div>
                                        </figure>

                                        <!-- informacion de almacén -->
                                        <div>
                                            <p class="text-gray-500 dark:text-gray-300">
                                                Stock: <strong>{{ currentComponent.storages[0]?.quantity }}</strong> unidades
                                            </p>
                                            <p class="text-gray-500 dark:text-gray-300">
                                                Ubicación: <strong>{{ currentComponent.storages[0]?.location ?? 'No asignado' }}</strong>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Botón para Agregar Componente -->
                                <div class="flex justify-end">
                                    <button @click="addComponent" type="button" class="text-sm bg-primary hover:bg-primary-dark text-white font-bold py-2 px-4 rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                        :disabled="!currentComponent.product_id || !currentComponent.quantity">
                                        <i class="fa-solid" :class="editComponentIndex !== null ? 'fa-edit' : 'fa-plus-circle'"></i>
                                        {{ editComponentIndex !== null ? ' Actualizar componente' : ' Agregar componente' }}
                                    </button>
                                </div>

                                <!-- Lista de Componentes Agregados -->
                                <div v-if="form.components.length" class="mt-4">
                                    <ul class="rounded-lg bg-gray-100 dark:bg-slate-900 p-3 space-y-2">
                                        <li v-for="(component, index) in form.components" :key="index" class="flex justify-between items-center p-2 rounded-md transition-colors"
                                            :class="{ 'bg-blue-100 dark:bg-blue-900': editComponentIndex === index }">
                                            <span class="text-sm text-gray-800 dark:text-gray-200">
                                                <span class="font-bold text-primary">{{ getComponentName(component.product_id) }}</span>
                                                (x{{ component.quantity }})
                                            </span>
                                            <div class="flex items-center space-x-3">
                                                <button @click="editComponentIndex = null" v-if="editComponentIndex === index" type="button" class="flex items-center justify-center text-gray-500 hover:text-red-500 transition-colors">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                                <button @click="editComponent(index)" type="button" class="text-gray-500 hover:text-blue-500 transition-colors">
                                                    <i class="fa-solid fa-pencil"></i>
                                                </button>
                                                <button @click="removeComponent(index)" type="button" class="text-gray-500 hover:text-red-500 transition-colors">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                                <InputError :message="form.errors.components" class="col-span-full mb-2" />
                            </section>

                        </div>

                        <!-- ================================================================== -->
                        <!-- ================== NUEVA SECCIÓN DE PROCESOS ===================== -->
                        <!-- ================================================================== -->
                        <div v-if="form.product_type_key === 'C'" class="space-y-4 p-4 border border-gray-200 dark:border-slate-700 rounded-lg mt-4 animate-fade-in">
                            <div class="flex items-center justify-between border-b border-gray-200 dark:border-slate-700 pb-2 mb-4">
                                <label class="flex items-center">
                                    <Checkbox v-model:checked="hasProductionProcesses" name="is_circular" />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Lleva procesos de producción</span>
                                </label>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white text-right">
                                    Procesos de producción
                                </h3>
                            </div>

                            <section v-if="hasProductionProcesses">
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div class="sm:col-span-1">
                                        <InputLabel value="Proceso*" />
                                        <el-select @change="getSelectedProcessData" v-model="currentProcess.process_id" filterable placeholder="Selecciona los procesos en orden" class="w-full">
                                            <el-option v-for="item in production_processes" :key="item.id" :label="item.name" :value="item.id" />
                                        </el-select>
                                    </div>
                                    <TextInput v-model="currentProcess.time" label="Tiempo estimado*" type="text" placeholder="Ej. 5 min 15 seg" :disabled="true" />
                                    <TextInput v-model="currentProcess.cost" label="Costo del proceso*" :formatAsNumber="true" :disabled="true">
                                        <template #icon-left><i class="fa-solid fa-dollar-sign"></i></template>
                                    </TextInput>
                                </div>

                                <!-- Botón para Agregar Proceso -->
                                <div class="flex justify-end space-x-3">
                                    <button @click="addProcess" type="button" class="text-sm bg-primary hover:bg-primary-dark text-white font-bold py-2 px-4 rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                        :disabled="!currentProcess.process_id || !currentProcess.time || !currentProcess.cost">
                                        <i class="fa-solid" :class="editProcessIndex !== null ? 'fa-edit' : 'fa-plus-circle'"></i>
                                        {{ editProcessIndex !== null ? ' Actualizar proceso' : ' Agregar proceso' }}
                                    </button>
                                    <SecondaryButton type="button" v-if="$page.props.auth.user.permissions.includes('Ver costos de produccion')" @click="$inertia.visit(route('production-costs.index'))">Administrar Procesos</SecondaryButton>
                                </div>

                                <!-- Lista de Procesos Agregados -->
                                <div v-if="form.production_processes.length" class="mt-4">
                                    <InputError :message="form.errors.production_processes" class="col-span-full mb-2" />
                                    <ul class="rounded-lg bg-gray-100 dark:bg-slate-900 p-3 space-y-2">
                                        <li v-for="(process, index) in form.production_processes" :key="index" class="flex justify-between items-center p-2 rounded-md transition-colors"
                                            :class="{ 'bg-blue-100 dark:bg-blue-900': editProcessIndex === index }">
                                            <div class="text-sm text-gray-800 dark:text-gray-200">
                                                <span class="font-bold text-primary">{{ getProcessName(process.process_id) }}</span>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    <span>Tiempo: {{ process.time }}</span> | 
                                                    <span>Costo: ${{ process.cost }}</span>
                                                </div>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <button @click="editProcessIndex = null" v-if="editProcessIndex === index" type="button" class="flex items-center justify-center text-gray-500 hover:text-red-500 transition-colors">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                                <button @click="editProcess(index)" type="button" class="text-gray-500 hover:text-blue-500 transition-colors">
                                                    <i class="fa-solid fa-pencil"></i>
                                                </button>
                                                <button @click="removeProcess(index)" type="button" class="text-gray-500 hover:text-red-500 transition-colors">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </section>
                        </div>

                        <!-- Archivos adjuntos existentes -->
                        <div v-if="catalog_product.media?.length" class="col-span-full">
                            <InputLabel value="Archivos adjuntos" />
                            <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mt-2">
                                <FileView v-for="file in catalog_product.media" :key="file.id" :file="file" :deletable="true"
                                    @delete-file="deleteFile($event)" />
                            </div>
                        </div>

                        <div v-if="catalog_product.media?.length < 3">
                            <InputLabel value="Imágenes del producto (máx. 3)" />
                            <FileUploader @files-selected="form.media = $event" acceptedFormat="image/*" :multiple="true" class="mt-1" />
                            <InputError :message="form.errors.media" :maxFiles="3 - catalog_product.media.length" class="mt-2" />
                        </div>

                        <p class="text-amber-600 text-sm col-span-full" v-else>*Has alcanzado el límite de imágenes. Elimina alguna para poder agregar más.</p>

                        <div class="border-t border-gray-200 dark:border-slate-700 pt-6 flex justify-end">
                            <SecondaryButton :loading="form.processing">
                                Guardar cambios
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
import LoadingIsoLogo from "@/Components/MyComponents/LoadingIsoLogo.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import Back from "@/Components/MyComponents/Back.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import DialogModal from "@/Components/DialogModal.vue";
import FileView from "@/Components/MyComponents/FileView.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import { ElMessage } from 'element-plus';
import { useForm } from "@inertiajs/vue3";
import axios from 'axios';

export default {
    components: { AppLayout, SecondaryButton,FileView, LoadingIsoLogo, InputError, InputLabel, TextInput, Back, FileUploader, DialogModal, CancelButton, Checkbox },
    props: {
        catalog_product: Object, // Prop con los datos del producto
        brands: Array,
        product_families: Array,
        production_processes: Array,
        raw_materials: Array,
    },
    data() {
        const typeMap = {
            "Catálogo": "C",
            "Materia prima": "MP"
        };

        const materialMap = {
            "Metal": "M",
            "Plástico": "PLS",
            "Piel de lujo": "PL",
            "Original": "O",
            "Lujo": "L",
            "Piel": "P",
            "Zamak": "ZK",
            "Solid chrome": "SCH",
            "Micrometal": "MM",
        };

        return {
            form: useForm({
                _method: 'PUT', // Clave para que Laravel trate el POST como PUT
                name: this.catalog_product.name,
                code: this.catalog_product.code,
                caracteristics: this.catalog_product.caracteristics,
                cost: this.catalog_product.cost,
                currency: this.catalog_product.currency,
                base_price: this.catalog_product.base_price,
                brand_id: this.catalog_product.brand_id,
                product_type_key: typeMap[this.catalog_product.product_type],
                product_family_id: this.catalog_product.product_family_id,
                material: materialMap[this.catalog_product.material],
                base_price: this.catalog_product.base_price,
                measure_unit: this.catalog_product.measure_unit,
                min_quantity: this.catalog_product.min_quantity,
                max_quantity: this.catalog_product.max_quantity,
                is_circular: !! this.catalog_product.is_circular,
                width: this.catalog_product.width,
                large: this.catalog_product.large,
                height: this.catalog_product.height,
                diameter: this.catalog_product.diameter,
                location: this.catalog_product.storages[0]?.location,
                media: [], // Para archivos nuevos
                hasComponents: this.catalog_product.components.length ? true : false,
                // Mapea los componentes y procesos existentes al formato del formulario
                components: this.catalog_product.components.map(c => ({ product_id: c.id, quantity: c.pivot.quantity })),
                production_processes: this.catalog_product.production_costs.map(p => ({ process_id: p.id, time: p.estimated_time_seconds + ' seg', cost: p.cost })),
            }),
            familyForm: useForm({ name: null, key: null }),
            brandForm: useForm({ name: null }),

            // --- Estado para la gestión de componentes ---
            currentComponent: { product_id: null, quantity: 1 },
            loadingComponentMedia: false,
            editComponentIndex: null,

            // --- Estado para la gestión de procesos ---
            hasProductionProcesses: this.catalog_product.production_costs.length ? true : false,
            currentProcess: { process_id: null, time: null, cost: null },
            editProcessIndex: null,

            // --- Modales ---
            showCreateFamilyModal: false,
            showCreateBrandModal: false,

            // --- opciones para selects ---
            currencies: ['MXN', 'USD'],
            productTypeOptions: [
                { label: 'Catálogo', key: 'C' },
                { label: 'Materia Prima', key: 'MP' },
                // { label: 'Insumo', key: 'I' },
            ],
            materialOptions: [
                { label: 'Metal', key: 'M' }, { label: 'Plástico', key: 'PLS' }, { label: 'Piel de lujo', key: 'PL' },
                { label: 'Original', key: 'O' }, { label: 'Lujo', key: 'L' }, { label: 'Piel', key: 'P' }, { label: 'Zamak', key: 'ZK' },
                { label: 'Solid Chrome', key: 'SCH' }, { label: 'Micrometal', key: 'MM' }
            ],
            mesureUnits: [
                'Pieza(s)', 'Litro(s)', 'Par(es)', 'kilogramo(s)', 'Metro(s)', 'Rollo(s)', 'Galon(es)', 'Cubeta(s)', 'Bote(s)',
            ],
        };
    },
    watch: {
        'form.product_type_key': 'generatePartNumber',
        'form.product_family_id': 'generatePartNumber',
        'form.material': 'generatePartNumber',
        'form.brand_id': 'generatePartNumber',
        // 'form.is_circular'(isCircular) {
        //     this.form.large = isCircular ? null : this.form.large;
        //     this.form.height = isCircular ? null : this.form.height;
        //     this.form.diameter = !isCircular ? null : this.form.diameter;
        // }
    },
    computed: {
        /**
         * Calcula el costo total de todos los componentes en la lista.
         * Se actualiza automáticamente si se agrega, elimina o modifica un componente.
         */
        totalComponentsCost() {
            return this.form.components.reduce((total, component) => {
                const quantity = parseFloat(component.quantity) || 0;
                const cost = parseFloat(component.cost) || 0;
                return total + (quantity * cost);
            }, 0);
        },

        /**
         * Calcula el costo final del producto sumando el costo base
         * y el costo total de los componentes.
         */
        finalProductCost() {
            const baseCost = parseFloat(this.form.cost) || 0;
            // Usamos 'this.totalComponentsCost' para acceder a la otra propiedad computada
            return baseCost + this.totalComponentsCost;
        }
    },
    methods: {
        update() {
            // suma al costo los componentes
            if ( this.form.product_type_key === 'C' ) {
                this.form.cost = this.totalComponentsCost;
            }
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
        storeFamily() {
            this.familyForm.post(route('product-families.store'), {
                onSuccess: () => {
                    ElMessage.success('Familia creada');
                    this.showCreateFamilyModal = false;
                    this.familyForm.reset();
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
            const brandObj = this.brands.find(b => b.id == this.form.brand_id);
            const brand = brandObj ? brandObj.name.substring(0, 3).toUpperCase() : '';
            const id = this.catalog_product.id || 'XXX';

            if (type && family && material) {
                this.form.code = `${type}-${family}-${material}-${brand}-${id}`;
            } else {
                this.form.code = '';
            }
        },
        // --- Métodos para Componentes ---
        async getComponentMedia() {
            // 1. Inicia el estado de carga
            this.loadingComponentMedia = true;
            try {
                const response = await axios.get(route('products.get-media', this.currentComponent.product_id));

                if ( response.status === 200 ) {
                    this.currentComponent.media = response.data.product.media;
                    this.currentComponent.storages = response.data.product.storages;
                    this.currentComponent.cost = response.data.product.cost;
                }
            } catch (error) {
                console.log(error);
                ElMessage.error('No se pudo cargar la imagen del componente')
            } finally {
                this.loadingComponentMedia = false;
            }
        },
        addComponent() {
            const componentToAdd = { ...this.currentComponent };
            if (this.editComponentIndex !== null) {
                this.form.components[this.editComponentIndex] = componentToAdd;
            } else {
                this.form.components.push(componentToAdd);
            }
            this.resetCurrentComponent();
        },
        editComponent(index) {
            this.currentComponent = { ...this.form.components[index] };
            this.editComponentIndex = index;
        },
        removeComponent(index) {
            this.form.components.splice(index, 1);
            if (this.editComponentIndex === index) {
                this.resetCurrentComponent();
            }
        },
        resetCurrentComponent() {
            this.currentComponent = { product_id: null, quantity: 1 };
            this.editComponentIndex = null;
        },
        getComponentName(productId) {
            const product = this.raw_materials.find(p => p.id === productId);
            return product ? product.name : `ID: ${productId}`;
        },
        // --- Métodos para Procesos de Producción ---
        getSelectedProcessData() {
            const selectedProcess = this.production_processes.find(
                item => item.id == this.currentProcess.process_id
            );

            if (selectedProcess) {
                let totalSeconds = selectedProcess.estimated_time_seconds;

                if (totalSeconds > 60) {
                    const minutes = Math.floor(totalSeconds / 60);
                    const seconds = totalSeconds % 60;
                    this.currentProcess.time = `${minutes} min ${seconds} seg`;
                } else {
                    this.currentProcess.time = `${totalSeconds} seg`;
                }

                this.currentProcess.cost = selectedProcess.cost;
            }
        },
        addProcess() {
            const processToAdd = { ...this.currentProcess };
            if (this.editProcessIndex !== null) {
                this.form.production_processes[this.editProcessIndex] = processToAdd;
            } else {
                this.form.production_processes.push(processToAdd);
            }
            this.resetCurrentProcess();
        },
        editProcess(index) {
            this.currentProcess = { ...this.form.production_processes[index] };
            this.editProcessIndex = index;
        },
        removeProcess(index) {
            this.form.production_processes.splice(index, 1);
            if (this.editProcessIndex === index) {
                this.resetCurrentProcess();
            }
        },
        resetCurrentProcess() {
            this.currentProcess = { process_id: null, time: null, cost: null };
            this.editProcessIndex = null;
        },
        getProcessName(processId) {
            const process = this.production_processes.find(p => p.id === processId);
            return process ? process.name : `ID: ${processId}`;
        },
        mounted() {
            this.generatePartNumber();
        },
        deleteFile(fileId) {
            this.catalog_product.media = this.catalog_product.media.filter(m => m.id !== fileId);
        },
    },
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
