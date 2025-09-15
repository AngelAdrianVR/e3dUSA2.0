<template>
    <AppLayout title="Crear Producto">
        <div class="flex justify-between items-center">
            <Back :href="route('catalog-products.index')" />
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                Agregar nuevo producto
            </h2>
        </div>

        <div class="max-w-4xl mx-auto p-4 sm:p-6 lg:p-8">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-6">
                <form @submit.prevent="confirmAndStore">
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
                                        <span>Marca del producto/Agencia*</span>
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

                        <p v-if="form.material == 'T'" class="text-xs text-amber-600 bg-amber-100 px-3 py-1 rounded-md">
                        Si el producto se vende por medidas personalizadas, indicar stock y costo tomando como unidad el metro lineal.  
                        (Ejemplo: un rollo de tela de 1.52m x 30m equivale a 30 en stock y la unidad es Metro(s)).
                        </p>

                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                             <TextInput v-model="form.min_quantity" label="Cantidad mínima" :error="form.errors.min_quantity" type="number" placeholder="Ej. 100" />
                             <TextInput v-model="form.max_quantity" label="Cantidad máxima" :error="form.errors.max_quantity" type="number" placeholder="Ej. 1000" />
                             <TextInput v-model="form.current_stock" label="Estock inicial" :error="form.errors.current_stock" type="number" placeholder="Ej. 3,000" />
                             <TextInput v-model="form.location" label="Ubicación en almacén" :error="form.errors.location" type="text" placeholder="Ej. Rack A estante 2" />
                             <TextInput 
                                v-model="form.cost" 
                                :error="form.errors.cost"
                                label="Cuánto le cuesta a E3D*"
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

                            <div v-if="form.product_type_key === 'C'" class="mt-5">
                                <label class="flex items-center">
                                    <Checkbox v-model:checked="form.is_used_as_component" name="is_used_as_component" class="bg-transparent text-indigo-500 border-gray-500" />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">¿Se usa como componente?</span>
                                </label>
                                <InputError :message="form.errors.is_used_as_component" class="mt-1" />
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
                        <!-- ================== SECCIÓN DE COMPONENTES ============= -->
                        <!-- ================================================================== -->
                        <div v-if="form.product_type_key === 'C'" :class="form.errors.components ? 'border-red-600' : 'border-gray-200 dark:border-slate-700'"
                            class="space-y-4 p-4 border rounded-lg mt-4 animate-fade-in">
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
                                            <el-option v-for="item in components" :key="item.id" :label="item.name" :value="item.id" :disabled="isComponentSelected(item.id)" />
                                        </el-select>
                                    </div>
                                    <TextInput v-model="currentComponent.quantity" label="Cantidad necesaria*" type="number" placeholder="Ej. 1" />
                                    <LoadingIsoLogo v-if="loadingComponentMedia" />

                                    <!-- Tarjeta de materia prima seleccionada -->
                                    <div class="flex items-center space-x-4 p-2 bg-gray-100 dark:bg-slate-900/50 rounded-md col-span-full mb-2" v-else-if="currentComponent.product_id">
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
                                                Stock: <strong>{{ currentComponent.storages[0]?.quantity.replace(/\B(?=(\d{3})+(?!\d))/g, ",") }} {{ currentComponent.measure_unit }}</strong>
                                            </p>
                                            <p class="text-gray-500 dark:text-gray-300">
                                                Ubicación: <strong>{{ currentComponent.storages[0]?.location ?? 'No asignado' }}</strong>
                                            </p>
                                            <p class="text-gray-500 dark:text-gray-300">
                                                Costo: <strong>${{ currentComponent.cost ?? '0.00' }} {{ currentComponent.currency }}</strong>
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
                                                <button @click="editComponentIndex = null; currentComponent = []" v-if="editComponentIndex === index" type="button" class="flex items-center justify-center text-gray-500 hover:text-red-500 transition-colors">
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

                        <!-- ================== NUEVA SECCIÓN DE PROCESOS ===================== -->
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
                                            <el-option v-for="item in production_processes" :key="item.id" :label="item.name" :value="item.id" :disabled="isProcessSelected(item.id)" />
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
                                    <SecondaryButton type="button" v-if="$page.props.auth.user.permissions.includes('Ver costos de produccion')"
                                        @click="openProcessessCreate">
                                        Crear nuevo Proceso
                                    </SecondaryButton>
                                    <el-tooltip content="Refrescar procesos" placement="top">
                                        <button
                                            @click="refreshProcesses"
                                            type="button"
                                            class="size-10 flex items-center justify-center rounded-full bg-gray-200 hover:bg-gray-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-200 transition-all shadow-sm hover:shadow-md"
                                        >
                                            <!-- Ícono de refrescar (Heroicons) -->
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                            </svg>
                                        </button>
                                    </el-tooltip>

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

                        <div>
                            <InputLabel value="Imágenes del producto (máx. 3)" />
                            <FileUploader @files-selected="form.media = $event" acceptedFormat="image/*" :multiple="true" :maxFiles="3" class="mt-1" />
                            <InputError :message="form.errors.media" class="mt-2" />
                        </div>

                        <div class="border-t border-gray-200 dark:border-slate-700 pt-6 flex justify-end">
                            <SecondaryButton :loading="form.processing || searchingSimilarProducts">
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

        <!-- Modal para verificar productos similares -->
        <DialogModal :show="showSimilarProductsModal" @close="showSimilarProductsModal = false">
             <template #title>
                <h2 class="text-xl font-bold text-yellow-600 dark:text-yellow-400">
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i> ¡Atención! Productos similares encontrados
                </h2>
             </template>
             <template #content>
                <p class="text-sm text-gray-700 dark:text-gray-300 mb-4">
                    Se encontraron los siguientes productos con un código base idéntico. Por favor, revisa cuidadosamente para evitar duplicar un producto que ya existe.
                </p>
                <div class="max-h-96 overflow-y-auto space-y-3 pr-2">
                    <div v-for="product in similarProducts" :key="product.id" class="flex items-start p-3 bg-gray-100 dark:bg-slate-900 rounded-lg shadow-sm">
                        <img v-if="product.media?.length" :src="product.media[0]?.original_url" alt="Imagen del producto" class="size-20 rounded-md object-cover mr-4">
                        <div v-else class="size-20 rounded-md bg-gray-200 dark:bg-slate-800 flex items-center justify-center mr-4 text-gray-400">
                            <i class="fa-solid fa-image text-2xl"></i>
                        </div>
                        <div>
                            <p class="font-bold text-gray-900 dark:text-gray-100">{{ product.name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">ID: {{ product.id }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">Código: {{ product.code }}</p>
                            <p v-if="product.caracteristics" class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ product.caracteristics }}</p>
                        </div>
                    </div>
                </div>
             </template>
             <template #footer>
                <div class="flex items-center space-x-3">
                    <CancelButton @click="showSimilarProductsModal = false" :disabled="form.processing">Cancelar</CancelButton>
                    <SecondaryButton @click="proceedToStore" :loading="form.processing" :disabled="form.processing">
                        Confirmar Creación
                    </SecondaryButton>
                </div>
             </template>
        </DialogModal>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import Checkbox from "@/Components/Checkbox.vue";
import LoadingIsoLogo from "@/Components/MyComponents/LoadingIsoLogo.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import Back from "@/Components/MyComponents/Back.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import DialogModal from "@/Components/DialogModal.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import { ElMessage } from 'element-plus';
import { useForm, Link, router } from "@inertiajs/vue3";
import axios from 'axios';

export default {
    components: { AppLayout, Link, SecondaryButton, InputError, InputLabel, TextInput, Back, FileUploader, DialogModal, CancelButton, Checkbox, LoadingIsoLogo },
    props: {
        brands: Array,
        product_families: Array,
        consecutive: Number,
        production_processes: Array, // Prop para recibir los procesos de producción
        components: Array, // Prop para recibir los componentes (materias primas) de producción
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
                is_used_as_component: false,
                product_type_key: 'C', // 'C' para Catálogo por defecto
                material: null,
                measure_unit: 'Pieza(s)',
                currency: 'MXN',
                min_quantity: 1,
                max_quantity: null,
                is_circular: false,
                base_price: null,
                width: null,
                large: null,
                height: null,
                diameter: null,
                current_stock: null,
                location: null,
                media: [],
                components: [], // Array para guardar los componentes del producto
                production_processes: [], // Array para guardar los procesos del producto
                hasComponents: false,
            }),
            familyForm: useForm({ name: null, key: null }),
            brandForm: useForm({ name: null }),

            // --- Estado para la gestión de componentes ---
            currentComponent: { product_id: null, quantity: 1 },
            loadingComponentMedia: false,
            editComponentIndex: null,

            // --- Estado para la gestión de procesos ---
            hasProductionProcesses: false,
            currentProcess: { process_id: null, time: null, cost: null },
            editProcessIndex: null,

            // --- Estado para la verificación de duplicados ---
            searchingSimilarProducts: false,
            showSimilarProductsModal: false,
            similarProducts: [],
            isCheckingForDuplicates: false,

            // --- Modales ---
            showCreateFamilyModal: false,
            showCreateBrandModal: false,

            // --- opciones de selects ---
            currencies: ['MXN', 'USD'],
            productTypeOptions: [
                { label: 'Catálogo', key: 'C' },
                { label: 'Materia Prima', key: 'MP' },
                { label: 'Insumo', key: 'I' },
            ],
            materialOptions: [
                { label: 'METAL', key: 'M' }, { label: 'PLASTICO', key: 'PLS' }, { label: 'PIEL DE LUJO', key: 'PL' },
                { label: 'ORIGINAL', key: 'O' }, { label: 'LUJO', key: 'L' }, { label: 'PIEL', key: 'P' }, { label: 'ZAMAK', key: 'ZK' },
                { label: 'SOLIDCHROME', key: 'SCH' }, { label: 'MICROMETAL', key: 'MM' }, { label: 'FLEXCHROME', key: 'FCH' }, { label: 'ALUMINIO', key: 'AL' },
                { label: 'ESTIRENO', key: 'ES' }, { label: 'ABS', key: 'ABS' }, { label: 'PVC', key: 'PVC' }, { label: 'TELA', key: 'T' }, { label: 'CAUCHO', key: 'CAU' },
                { label: 'VINILPIEL', key: 'VPL' }
            ],
            mesureUnits: [
                'Pieza(s)', 'Litro(s)', 'Par(es)', 'kilogramo(s)', 'Metro(s)', 'Centímetros(cm)', 'Rollo(s)', 'Galon(es)', 'Cubeta(s)', 'Bote(s)',
            ],
        };
    },
    watch: {
        'form.product_type_key'(newVal) {
            this.generatePartNumber();
            this.form.is_used_as_component = newVal === 'MP';
        },
        'form.hasComponents'(newVal) {
            if (newVal) {
                this.form.cost = 0;
            } else {
                this.form.cost = null;
            }
        },
        'form.product_family_id': 'generatePartNumber',
        'form.material': 'generatePartNumber',
        'form.brand_id': 'generatePartNumber',
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
    },

    methods: {
        openProcessessCreate() {
            const url = route('production-costs.index');
            window.open(url, '_blank');
        },
        refreshProcesses() {
            router.reload({ 
                preserveScroll: true,
                preserveState: true 
            });
        },
        async confirmAndStore() {
            
            // Extrae el código base sin el consecutivo
            const baseCode = this.form.code.substring(0, this.form.code.lastIndexOf('-'));
            
            if (!baseCode) {
                // Si no hay código base, procede a guardar directamente para que la validación del backend actúe
                this.proceedToStore();
                return;
            }

            this.isCheckingForDuplicates = true;

            try {
                this. searchingSimilarProducts = true;
                // Llama al nuevo endpoint del backend para buscar productos similares
                const response = await axios.get(route('catalog-products.find-similar', { base_code: baseCode }));

                if (response.data.length > 0) {
                    // Si se encuentran productos, guarda los resultados y muestra el modal
                    this.similarProducts = response.data;
                    this.showSimilarProductsModal = true;
                } else {
                    // Si no se encuentran, procede a guardar el producto directamente
                    this.proceedToStore();
                }
            } catch (error) {
                console.error("Error al verificar productos similares:", error);
                ElMessage.error('Hubo un problema al verificar duplicados. Por favor, intenta de nuevo.');
            } finally {
                this.isCheckingForDuplicates = false;
                this. searchingSimilarProducts = false;
            }
        },
        isComponentSelected(productId) {
            const isSelected = this.form.components.some(c => c.product_id === productId);
            if (!isSelected) return false;

            if (this.editComponentIndex !== null) {
                const editingProductId = this.form.components[this.editComponentIndex].product_id;
                if (productId === editingProductId) {
                    return false;
                }
            }
            
            return true;
        },
        isProcessSelected(processId) {
            const isSelected = this.form.production_processes.some(p => p.process_id === processId);
            if (!isSelected) return false;

            if (this.editProcessIndex !== null) {
                const editingProcessId = this.form.production_processes[this.editProcessIndex].process_id;
                if (processId === editingProcessId) {
                    return false;
                }
            }
            
            return true;
        },
        proceedToStore () {
            this.form.post(route("catalog-products.store"), {
                onSuccess: () => {
                    ElMessage.success('Producto creado con éxito');
                    this.form.reset();
                },
                onError: () => {
                    ElMessage.error('Hubo un problema al crear el producto. Revisa los campos.');
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
            const id = this.consecutive || 'XXX';

            if (type && family && material) {
                if ( type === 'MP' ) {
                    this.form.code = `${type}-${material}-${family}-${brand}-${id}`; // incluye el tipo de producto
                } else {
                    this.form.code = `${family}-${material}-${brand}-${id}`; // se quito el tipo de producto para dejarlo legible y corto para facturas
                }
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
                    this.currentComponent.measure_unit = response.data.product.measure_unit;
                    this.currentComponent.currency = response.data.product.currency;
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
            const product = this.components.find(p => p.id === productId);
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
    },
    mounted() {
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
