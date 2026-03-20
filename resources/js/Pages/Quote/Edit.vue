<template>
    <AppLayout title="Editar Cotización">
        <!-- Panel Flotante de Notas -->
        <BranchNotes v-if="form.branch_id" :branch-id="form.branch_id" />

        <!-- Encabezado -->
        <div class="px-4 sm:px-0 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <Back :href="route('quotes.index')" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Editar cotización COT-{{ quote.id }}
                </h2>
            </div>
        </div>

        <!-- Formulario principal -->
        <div ref="formContainer" class="py-7">
            <div class="max-w-[60rem] mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-3 md:p-9 relative">
                    
                    <form @submit.prevent="update">
                        <!-- SECCIÓN 1: INFORMACIÓN GENERAL -->
                        <div class="flex justify-between items-center">
                            <el-divider content-position="left" class="flex-grow">
                                <span>Información General</span>
                            </el-divider>
                             <!-- Botón para ver productos del cliente -->
                            <div v-if="form.branch_id" class="ml-4">
                                <SecondaryButton type="button" @click="showClientProductsDrawer = true">
                                    <i class="fa-solid fa-box-open mr-2"></i>
                                    Ver productos
                                </SecondaryButton>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-3">
                            <div class="col-span-full mb-5">
                                <el-radio-group v-model="form.is_spanish_template" size="small">
                                    <el-radio-button :label="true">Plantilla en Español</el-radio-button>
                                    <el-radio-button :label="false">Plantilla en Inglés</el-radio-button>
                                </el-radio-group>
                            </div>
                            <!-- Selector de Cliente con creación rápida -->
                            <div>
                                <InputLabel value="Cliente*" />
                                <div class="flex items-center space-x-2">
                                    <el-select v-model="form.branch_id" filterable placeholder="Selecciona un cliente" class="!w-full">
                                        <el-option v-for="branch in localBranches" :key="branch.id" :label="branch.name" :value="branch.id" />
                                    </el-select>
                                    <el-button @click="branchModalVisible = true" type="primary" circle plain>
                                        <i class="fa-solid fa-plus"></i>
                                    </el-button>
                                </div>
                                <InputError :message="form.errors.branch_id" />
                            </div>

                            <!-- Campos que se llenan con el contacto -->
                            <TextInput label="Persona que recibe*" v-model="form.receiver" :error="form.errors.receiver" placeholder="Selecciona un contacto" />
                            <TextInput :label="form.is_spanish_template ? 'Departamento / Puesto*' : 'Departamento / Puesto* (En inglés)'" v-model="form.department" :error="form.errors.department" placeholder="Selecciona un contacto" />
                            
                            <div>
                                <InputLabel value="Dias para primera producción*" />
                                <el-select v-model="form.first_production_days" placeholder="Selecciona">
                                    <el-option
                                        v-for="item in form.is_spanish_template ? firstProductionDaysList : firstProductionDaysListEnglish"
                                        :key="item" :label="item" :value="item" />
                                </el-select>
                                <InputError :message="form.errors.first_production_days" />
                            </div>

                            <!-- NUEVO CAMPO: Vigencia (Validity) -->
                            <TextInput 
                                :label="form.is_spanish_template ? 'Vigencia de la cotización (Opcional)' : 'Quote validity (Optional)'" 
                                v-model="form.validity" 
                                :error="form.errors.validity" 
                                :placeholder="form.is_spanish_template ? 'Ej. Cotización válida por 21 días' : 'Ej. Quote valid for 21 days'" 
                            />

                            <div>
                                <InputLabel value="Moneda general*" />
                                <el-select v-model="form.currency" placeholder="Selecciona la moneda" class="!w-full">
                                    <el-option label="MXN (Peso Mexicano)" value="MXN" />
                                    <el-option label="USD (Dólar Americano)" value="USD" />
                                </el-select>
                                <InputError :message="form.errors.currency" />
                            </div>

                            <div class="col-span-1 md:col-span-full">
                                <InputLabel :value="form.is_spanish_template ? 'Notas generales (opcional)' : 'Notas generales (opcional)(En inglés)'" />
                            
                                <editor
                                    api-key="6wv6th13eisrze7klszq4wnlmgjcgaodezi469shqsn3v1zc" 
                                    v-model="form.notes"
                                    id="quote-notes-editor"
                                    :init="tinymceInit"
                                />
                            </div>
                        </div>

                        <!-- SECCIÓN 2: COSTOS DE HERRAMENTAL Y FLETE -->
                        <el-divider content-position="left" class="!mt-8">
                            <span>Costos Adicionales</span>
                        </el-divider>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-5 gap-y-4 items-start">
                             <TextInput :label="isToolingCostRequired ? 'Costo de Herramental*' : 'Costo de Herramental'" 
                                v-model="form.tooling_cost" 
                                :error="form.errors.tooling_cost" 
                                :placeholder="'Ej. 500.00'" :helpContent="'(Agregar Moneda manualmente $MXN/$USD)'">
                             </TextInput>
                             <div class="flex items-center space-x-2 mt-8">
                                <label class="flex items-center">
                                    <Checkbox v-model:checked="form.is_tooling_cost_stroked" class="bg-transparent border-gray-500" />
                                    <span class="ml-2 text-gray-400">Tachar:</span>
                                </label>
                                <span class="text-gray-500" :class="{ 'line-through': form.is_tooling_cost_stroked }">
                                    {{ form.tooling_cost }}
                                </span>
                             </div>
                             <div></div> <!-- Espaciador -->
                            <div>
                                <InputLabel value="Opción de flete*" />

                                <el-select
                                    v-model="form.freight_option"
                                    :placeholder="form.is_spanish_template ? 'Selecciona el flete' : 'Select freight option (Selecciona el flete)'"
                                    class="!w-full"
                                >
                                    <el-option
                                        :label="form.is_spanish_template ? 'Por cuenta del cliente' : 'Paid by the client'"
                                        :value="form.is_spanish_template ? 'Por cuenta del cliente' : 'Paid by the client'" />
                                    <el-option
                                        :label="form.is_spanish_template ? 'Cargo prorrateado en productos' : 'Freight cost prorated across products'"
                                        :value="form.is_spanish_template ? 'Cargo de flete prorrateado en productos' : 'Freight cost prorated across products'" />
                                    <el-option
                                        :label="form.is_spanish_template ? 'La empresa absorbe el costo' : 'Company absorbs the cost'"
                                        :value="form.is_spanish_template ? 'La empresa absorbe el costo de flete' : 'Company absorbs the freight cost'" />
                                    <el-option
                                        :label="form.is_spanish_template ? 'El cliente manda la guia' : 'Client sends the shipping label'"
                                        :value="form.is_spanish_template ? 'El cliente manda la guía' : 'Client sends the shipping label'" />
                                </el-select>

                                <InputError :message="form.errors.freight_option" />
                            </div>
                            <TextInput v-if="form.freight_option !== 'El cliente manda la guia' && form.freight_option !== 'Client sends the shipping label'" 
                                label="Costo de Flete" v-model="form.freight_cost" :helpContent="'Si no tiene costo, escribe 0 (Cero)'" 
                                :formatAsNumber="true" type="number" :placeholder="'Ej. 500.00'" :error="form.errors.freight_cost">
                               <template #icon-left><i class="fa-solid fa-dollar-sign"></i></template>
                            </TextInput>
                            <div v-if="form.freight_option !== 'El cliente manda la guia' && form.freight_option !== 'Client sends the shipping label'" class="flex items-center space-x-2 mt-8">
                                <label class="flex items-center">
                                    <Checkbox v-model:checked="form.is_freight_cost_stroked" class="bg-transparent border-gray-500" />
                                    <span class="ml-2 text-gray-400">Tachar:</span>
                                </label>
                                <span class="text-gray-500" :class="{ 'line-through': form.is_freight_cost_stroked }">
                                    ${{ formatNumber(form.freight_cost) }} {{ form.currency }}
                                </span>
                            </div>

                            <label class="flex items-center col-span-full">
                                <Checkbox v-model:checked="form.show_breakdown" class="bg-transparent border-gray-500" />
                                <span class="ml-2 text-gray-400">Mostrar totales</span>
                            </label>
                        </div>

                        <!-- SECCIÓN 3: PRODUCTOS -->
                        <el-divider content-position="left" class="!mt-8">
                            <span>Productos a Cotizar</span>
                        </el-divider>

                        <div ref="formProducts" class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg">
                            <div class="flex justify-between items-center space-x-2 mb-3">
                                <el-tooltip content="Refrescar lista de productos" placement="top">
                                    <button @click="fetchCatalogProducts" type="button" class="text-primary">
                                        <i class="fa-solid fa-arrows-rotate"></i>
                                    </button>
                                </el-tooltip>
                                <a :href="route('catalog-products.create')" target="_blank" class="text-primary hover:underline text-sm ml-2">+ Agregar nuevo producto al sistema</a>
                            </div>

                            <!-- Formulario de producto individual -->
                            <div class="grid grid-cols-1 lg:grid-cols-4 gap-3 items-start">
                                
                                <!-- Toggle Nuevo/Catalogo -->
                                <div class="col-span-full mb-1">
                                    <label class="flex items-center">
                                        <Checkbox v-model:checked="currentProduct.is_custom" class="bg-transparent border-blue-500 text-blue-600 focus:ring-blue-500" />
                                        <span class="ml-2 text-blue-700 font-bold dark:text-blue-400">Es producto nuevo (Fuera de catálogo)</span>
                                    </label>
                                </div>

                                <!-- PRODUCTO DE CATÁLOGO -->
                                <div v-if="!currentProduct.is_custom" class="lg:col-span-2">
                                    <InputLabel value="Producto de catálogo*" />
                                    <el-select @change="getProductData" v-model="currentProduct.id" filterable placeholder="Buscar producto" class="w-full">
                                        <el-option class="!w-96" v-for="product in localCatalogProducts" 
                                            :key="product.id" 
                                            :label="`${product.name} (${product.code})`" 
                                            :value="product.id"
                                             />
                                    </el-select>
                                </div>
                                
                                <!-- PRODUCTO NUEVO (CUSTOM) -->
                                <div v-else class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-md border border-blue-200 dark:border-blue-800">
                                    <div class="col-span-full">
                                        <InputLabel value="Nombre del producto*" />
                                        <TextInput v-model="currentProduct.custom_name" placeholder="Ej. Troquel especial" />
                                    </div>
                                    <div class="w-full">
                                        <InputLabel value="Unidad de medida" />
                                        <el-select class="!w-full" v-model="currentProduct.custom_measure_unit" filterable clearable placeholder="Selecciona la unidad de medida"
                                            no-data-text="No hay unidades de medida registradas"
                                            no-match-text="No se encontraron coincidencias">
                                            <el-option v-for="(item, index) in mesureUnits" :key="index" :label="item" :value="item" />
                                        </el-select>
                                        <InputError :message="form.errors.measure_unit" class="mt-1" />
                                    </div>
                                    <div class="col-span-full">
                                        <InputLabel value="Imagen (Opcional)" />
                                        <input type="file" accept="image/*" @change="handleImageUpload" class="mt-1 block w-full text-xs text-slate-500 file:mr-4 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200 dark:file:bg-slate-700 dark:file:text-slate-300 dark:hover:file:bg-slate-600" />
                                    </div>
                                </div>

                                <!-- Siempre visibles: Cantidad y Precio -->
                                <TextInput label="Cantidad*" v-model="currentProduct.quantity" type="number" />
                                <TextInput label="Precio Unitario (Venta)*" v-model="currentProduct.unit_price" type="number" :formatAsNumber="true" :error="unitPriceError">
                                        <template #icon-left><i class="fa-solid fa-dollar-sign"></i></template>
                                </TextInput>

                                <!-- Estado de carga -->
                                <LoadingIsoLogo class="col-span-full" v-if="loadingProductData" />

                                <!-- Tarjeta de producto seleccionado (Previsualización) -->
                                <div class="flex items-start space-x-4 p-2 bg-gray-100 dark:bg-slate-900/50 rounded-md col-span-full mb-2" v-else-if="currentProduct.id || currentProduct.is_custom">
                                    <figure class="relative flex items-center justify-center w-32 h-32 min-w-32 rounded-2xl border border-gray-200 dark:border-slate-900 overflow-hidden shadow-lg bg-white dark:bg-slate-800 transition transform hover:shadow-xl">
                                        <!-- Imagen de producto nuevo -->
                                        <img v-if="currentProduct.is_custom && currentProduct.image_preview"
                                            :src="currentProduct.image_preview" 
                                            class="rounded-2xl w-full h-full object-cover transition duration-300 ease-in-out hover:opacity-95">
                                        <!-- Imagen de producto de catalogo -->
                                        <img v-else-if="!currentProduct.is_custom && currentProduct.media?.length"
                                            :src="currentProduct.media[0]?.original_url" 
                                            class="rounded-2xl w-full h-full object-cover transition duration-300 ease-in-out hover:opacity-95">
                                        <!-- Placeholder -->
                                        <div v-else class="flex flex-col items-center justify-center text-gray-400 dark:text-slate-500">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                            </svg>
                                            <p class="text-xs">Sin imagen</p>
                                        </div>
                                    </figure>

                                    <!-- informacion  -->
                                    <div class="text-sm">
                                        <template v-if="currentProduct.is_custom">
                                            <span class="px-2 py-1 text-xs font-semibold text-blue-800 bg-blue-200 rounded-full mb-2 inline-block">Producto Nuevo</span>
                                            <p class="text-gray-700 dark:text-gray-300">
                                                Nombre: <strong>{{ currentProduct.custom_name || 'Sin especificar' }}</strong>
                                            </p>
                                            <p class="text-gray-500 dark:text-gray-400">
                                                Precio: <strong>${{ formatNumber(currentProduct.custom_cost) }}</strong>
                                            </p>
                                        </template>
                                        <template v-else>
                                            <!-- Etiqueta de producto de cliente -->
                                            <span v-if="currentProduct.isClientProduct" class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-200 rounded-full mb-2 inline-block">
                                                Producto de cliente
                                            </span>
                                            <p class="text-gray-500 dark:text-gray-300">
                                                Stock: <strong>{{ currentProduct.storages?.[0]?.quantity ?? 0 }}</strong> unidades
                                            </p>
                                            <p class="text-gray-500 dark:text-gray-300">
                                                Ubicación: <strong>{{ currentProduct.storages?.[0]?.location ?? 'No asignado' }}</strong>
                                            </p>
                                            <p class="text-gray-500 dark:text-gray-300">
                                                Precio base: <strong>${{ formatNumber(currentProduct.base_price) ?? '0.00' }}</strong>
                                            </p>
                                            <!-- Precio actual del cliente -->
                                            <p v-if="currentProduct.isClientProduct" class="text-green-600 dark:text-green-400 font-semibold mt-1">
                                                Precio actual: <strong>${{ formatNumber(currentProduct.current_price) ?? '0.00' }}</strong>
                                            </p>
                                        </template>
                                    </div>
                                </div>

                                <div v-if="currentProduct.id || currentProduct.is_custom" class="col-span-full flex space-x-2 items-center">
                                    <InputLabel value="¿Mostrar imagen en cotización?" />
                                    <el-switch v-model="currentProduct.show_image" inline-prompt size="large"
                                        style="--el-switch-on-color: #0355B5; --el-switch-off-color: #CCCCCC" active-text="Si"
                                        inactive-text="No" />
                                </div>
                                <div class="lg:col-span-full">
                                     <TextInput label="Notas del producto (opcional)" v-model="currentProduct.notes" type="textarea" :isTextarea="true" :withMaxLength="true" :maxLength="500" />
                                </div>

                                <label class="flex items-center col-span-full">
                                    <Checkbox v-model:checked="form.has_customization" class="bg-transparent border-gray-500" />
                                    <span class="ml-2 text-gray-400">Agregar personalización al producto</span>
                                </label>
                                
                                <!-- SECCIÓN DE PERSONALIZACIÓN DINÁMICA -->
                                <div v-if="form.has_customization" class="lg:col-span-full mt-3 p-4 border border-dashed dark:border-slate-700 rounded-lg">
                                    <h4 class="font-semibold mb-3 text-gray-700 dark:text-gray-300">Detalles de Personalización</h4>
                                    
                                    <!-- Inputs para agregar nuevo detalle -->
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-start">
                                        <div>
                                            <InputLabel value="Tipo*" />
                                            <el-select v-model="newCustomization.type" placeholder="Selecciona" class="!w-full">
                                                <el-option v-for="item in customizationTypes" :key="item" :label="item" :value="item" />
                                            </el-select>
                                        </div>
                                        <TextInput label="Concepto*" v-model="newCustomization.key" placeholder="Ej. Teléfono" />
                                        <TextInput label="Valor" v-model="newCustomization.value" placeholder="Ej. 3312158856" />
                                    </div>
                                    <div class="flex justify-end mt-2">
                                        <SecondaryButton @click="addCustomizationDetail" type="button" :disabled="!newCustomization.type || !newCustomization.key">
                                            <i class="fa-solid fa-plus mr-2"></i>
                                            Agregar Detalle
                                        </SecondaryButton>
                                    </div>

                                    <!-- Lista de detalles agregados al producto actual -->
                                    <div v-if="currentProduct.customization_details && currentProduct.customization_details.length" class="mt-4">
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Detalles agregados:</p>
                                        <div class="flex flex-wrap gap-2">
                                            <el-tag
                                                v-for="(detail, index) in currentProduct.customization_details"
                                                :key="index"
                                                closable
                                                @close="removeCustomizationDetail(index)"
                                                type="info"
                                                size="large"
                                                class="!h-auto"
                                            >
                                                <span class="whitespace-normal">
                                                    <strong>{{ detail.type }}</strong> | {{ detail.key }}: {{ detail.value }}
                                                </span>
                                            </el-tag>
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-2 col-span-full">
                                    <SecondaryButton @click="addProduct" type="button" :disabled="isAddProductDisabled">
                                        {{ editIndex !== null ? 'Actualizar producto' : 'Agregar producto' }}
                                    </SecondaryButton>
                                    <button @click="resetCurrentProduct" v-if="editIndex !== null" type="button" class="text-sm text-gray-500 hover:text-red-500 ml-3">
                                        Cancelar edición
                                    </button>
                                </div>
                            </div>
                        </div>
                        <InputError :message="form.errors.products" class="mt-2" />

                        <!-- Lista de productos agregados con nuevo estilo -->
                        <div v-if="form.products.length" class="mt-5">
                            <h3 class="font-bold mb-2 text-gray-800 dark:text-gray-200">Lista de productos agregados</h3>
                            <ul class="rounded-lg bg-gray-100 dark:bg-slate-800 p-3 space-y-2">
                                <li v-for="(product, index) in form.products" :key="index" class="flex justify-between items-center p-3 rounded-md transition-colors"
                                    :class="{ 'bg-blue-100 dark:bg-blue-900/50': editIndex === index }">
                                    <div class="flex items-center space-x-4">
                                        <!-- Imagen miniatura en lista -->
                                        <div class="w-12 h-12 rounded-md overflow-hidden bg-gray-200 shrink-0">
                                            <img v-if="product.is_custom && product.image_preview" :src="product.image_preview" class="w-full h-full object-cover" />
                                            <img v-else-if="!product.is_custom && product.media?.length" :src="product.media[0]?.original_url" class="w-full h-full object-cover" />
                                            <div v-else class="w-full h-full flex items-center justify-center text-gray-400"><i class="fa-solid fa-image"></i></div>
                                        </div>
                                        
                                        <span class="text-sm text-gray-800 dark:text-gray-200">
                                            <p class="font-bold text-primary flex items-center space-x-2">
                                                <span>{{ product.is_custom ? product.custom_name : getProductName(product.id) }}</span>
                                                <el-tag v-if="product.is_custom" type="primary" size="small" effect="dark">Nuevo / Personalizado</el-tag>
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                Cantidad: {{ product.quantity }} | P.U: ${{ formatNumber(product.unit_price) }} | Subtotal: ${{ formatNumber(product.quantity * product.unit_price) }}
                                            </p>
                                            <p v-if="product.notes" class="text-xs italic text-gray-500 mt-1">Nota: {{ product.notes }}</p>
                                            
                                            <!-- Mostrar detalles de personalización en la lista -->
                                            <div v-if="product.customization_details && product.customization_details.length" class="mt-2">
                                                <p class="text-xs font-semibold text-gray-600 dark:text-gray-300">Personalización:</p>
                                                <ul class="list-disc list-inside pl-1">
                                                    <li v-for="(detail, i) in product.customization_details" :key="i" class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ detail.type }} - {{ detail.key }}: {{ detail.value }}
                                                    </li>
                                                </ul>
                                            </div>
                                        </span>
                                    </div>
                                    <div class="flex items-center space-x-3 shrink-0">
                                        <el-tooltip content="Cancelar edición" placement="top">
                                            <button @click="resetCurrentProduct" v-if="editIndex === index" type="button" class="flex items-center justify-center text-gray-500 hover:text-red-500 transition-colors">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </el-tooltip>
                                        <el-tooltip content="Editar" placement="top">
                                            <button @click="editProduct(index)" type="button" class="text-gray-500 hover:text-blue-500 transition-colors">
                                                <i class="fa-solid fa-pencil"></i>
                                            </button>
                                        </el-tooltip>
                                        <el-tooltip content="Eliminar" placement="top">
                                            <button @click="deleteProduct(index)" type="button" class="text-gray-500 hover:text-red-500 transition-colors">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </el-tooltip>
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <!-- Sección de promociones y descuentos -->
                        <el-divider v-if="$page.props.auth.user.permissions.includes('Descuentos cotizaciones')" content-position="left" class="col-span-full">Promociones</el-divider>
                        <div class="grid grid-cols-2 gap-3" v-if="$page.props.auth.user.permissions.includes('Descuentos cotizaciones')">
                            <label class="flex items-center col-span-full">
                                <Checkbox v-model:checked="form.has_early_payment_discount" class="bg-transparent border-gray-500" />
                                <span class="ml-2 text-gray-400">Descuento pago por adelantado</span>
                                <el-tooltip placement="top">
                                    <template #content>
                                        <p>
                                            Al activar esta opción, el cliente verá <br>
                                            el beneficio de descuento por pago <br>
                                            por adelantado en el portal.
                                        </p>
                                    </template>
                                    <div
                                        class="rounded-full border border-primary size-3 flex items-center justify-center ml-2">
                                        <i class="fa-solid fa-info text-primary text-[7px]"></i>
                                    </div>
                                </el-tooltip>
                            </label>
                            
                            <div v-if="form.has_early_payment_discount">
                                <TextInput label="Porcentaje de descuento*" :error="form.errors.early_payment_discount_amount" v-model="form.early_payment_discount_amount" type="number">
                                        <template #icon-left>%</template>
                                </TextInput>
                            </div>

                        </div>

                        <!-- Botón de envío -->
                        <div class="flex justify-end mt-8 col-span-full">
                            <SecondaryButton :loading="form.processing">
                                Actualizar Cotización
                            </SecondaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Drawer para productos del cliente -->
        <el-drawer v-model="showClientProductsDrawer" title="Productos del Cliente" direction="rtl" :size="drawerSize">
            <div class="md:p-3">
                <p v-if="!clientProducts.length && !loadingClientProducts">Este cliente no tiene productos registrados.</p>
                <LoadingIsoLogo class="col-span-full" v-if="loadingClientProducts" />
                <div v-else class="space-y-4">
                    <div v-for="product in clientProducts" :key="product.id" class="relative bg-gray-100 dark:bg-slate-900 shadow-md rounded-2xl p-4 transition hover:shadow-xl duration-300">
                        <div class="absolute top-2 right-2 flex items-center space-x-1">
                            <el-tooltip content="Actualizar precio especial" placement="top"><button @click="openPriceModal(product)" class="flex items-center justify-center hover:bg-gray-200 dark:hover:bg-slate-800 rounded-full size-8 transition-colors"><i class="fa-solid fa-dollar-sign text-sm text-gray-500 dark:text-gray-600"></i></button></el-tooltip>
                            <el-tooltip content="Ver producto" placement="top"><button @click="openProduct(product.id)" class="flex items-center justify-center hover:bg-gray-200 dark:hover:bg-slate-800 rounded-full size-8 transition-colors"><i class="fa-solid fa-eye text-gray-500 dark:text-gray-600"></i></button></el-tooltip>
                        </div>
                        <div class="flex items-center gap-4">
                            <img v-if="product.media?.length" :src="product.media[0].original_url" class="w-20 h-20 object-cover rounded-xl border dark:border-gray-700"/>
                            <div class="flex-1">
                                <p class="text-lg font-semibold text-gray-800 dark:text-gray-200">{{ product.name }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Código: {{ product.code }}</p>
                                <el-tag v-if="product.archived_at" type="warning">Obsoleto</el-tag>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center justify-between">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Precio base</p>
                            <p class="font-medium text-blue-400">${{ product.base_price }} {{product.currency}}</p>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Precio actual</p>
                            <p class="font-semibold text-green-600 dark:text-green-400">${{ !product.price_history?.[0]?.valid_to && product.price_history?.[0]?.price ? product.price_history[0].price + ' ' + product.price_history[0].currency : product.base_price + ' ' + product.currency }}</p>
                        </div>
                        <div v-if="product.price_history?.length" class="mt-2 text-sm rounded-sm py-1 px-2" :class="getPriceChangeClass(product.price_history[0].valid_from)">
                            <span class="text-gray-700">Último cambio de precio: {{ timeSince(product.price_history[0].valid_from) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </el-drawer>

    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import BranchNotes from "@/Components/MyComponents/BranchNotes.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import LoadingIsoLogo from "@/Components/MyComponents/LoadingIsoLogo.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import Checkbox from "@/Components/Checkbox.vue";
import Back from "@/Components/MyComponents/Back.vue";
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElMessage } from 'element-plus';
import { useForm } from "@inertiajs/vue3";
import axios from 'axios';
import Editor from '@tinymce/tinymce-vue';

export default {
    data() {
        return {
            form: useForm({
                branch_id: null,
                receiver: '',
                department: '',
                currency: 'MXN',
                tooling_cost: null,
                is_tooling_cost_stroked: false,
                freight_cost: null,
                is_freight_cost_stroked: false,
                freight_option: 'Por cuenta del cliente',
                first_production_days: null,
                validity: this.quote.validity || 'Cotización válida por 21 días',
                notes: '',
                is_spanish_template: true,
                show_breakdown: true,
                has_early_payment_discount: false,
                early_payment_discount_amount: null,
                has_customization: false,
                products: [], 
            }),

            localBranches: [],
            availableContacts: [],
            selected_contact_id: null,
            branchModalVisible: false,
            contactModalVisible: false,
            quickBranchForm: { name: '', rfc: '', processing: false, errors: {} },
            quickContactForm: { name: '', charge: '', processing: false, errors: {} },

            branchNotes: [],
            showClosePriceConfirmModal: false,
            priceHistoryToClose: null,

            showPriceModal: false,
            productForUpdate: null,
            priceForm: { amount: null, percentage: null, currency: 'MXN', valid_from: new Date(), current_base_price: 0, min_allowed_price: 0 },

            currentProduct: {
                quote_product_id: null,
                id: null,
                is_custom: false,
                custom_name: '',
                custom_cost: 0,
                custom_measure_unit: '',
                image: null,
                image_preview: null,
                quantity: 1,
                unit_price: null,
                min_price: 0,
                notes: '',
                customization_details: [],
                isClientProduct: false,
                current_price: null,
                show_image: true,
                media: null,
                storages: [],
                base_price: null,
            },

            mesureUnits: [
                'Pieza(s)', 'Litro(s)', 'Par(es)', 'kilogramo(s)', 'Metro(s)', 'Centímetros(cm)', 'Rollo(s)', 'Galon(es)', 'Cubeta(s)', 'Bote(s)',
            ],

            newCustomization: { type: null, key: '', value: '' },
            customizationTypes: ['Grabado de medallón', 'Estampado', 'Bordado', 'Impresión digital', 'Otro'],
            editIndex: null,
            localCatalogProducts: this.catalogProducts,
            showClientProductsDrawer: false,
            clientProducts: [],
            loadingClientProducts: false,
            loadingProductData: false,
            drawerSize: "35%",
            firstProductionDaysList: ['Inmediata', '1 a 2 días', '2 a 3 días', '3 a 4 días', '4 a 5 días', '5 a 6 días', '1 semana', '1 a 2 semanas', '3 a 4 semanas', '5 a 6 semanas', '7 a 8 semanas', '9 a 10 semanas', '11 a 12 semanas', '13 a 14 semanas', '15 a 16 semanas', '17 a 18 semanas'],
            firstProductionDaysListEnglish: ['Immediate', '1 to 2 days', '2 to 3 days', '3 to 4 days', '4 to 5 days', '5 to 6 days', '1 to 2 weeks', '3 to 4 weeks', '5 to 6 weeks', '7 to 8 weeks', '9 to 10 weeks', '11 to 12 weeks', '13 to 14 weeks', '15 to 16 weeks', '17 to 18 weeks'],
            tinyApiKey: '6wv6th13eisrze7klszq4wnlmgjcgaodezi469shqsn3v1zc',
        };
    },
    components: {
        Back, Editor, Checkbox, TextInput, AppLayout, InputError, InputLabel, BranchNotes,
        CancelButton, PrimaryButton, LoadingIsoLogo, SecondaryButton, ConfirmationModal,
    },
    props: {
        catalogProducts: Array,
        branches: Array,
        quote: Object, // Trae todo el modelo cargado con $quote->load('quoteProducts.media', 'quoteProducts.product.media')
    },
    computed: {
        unitPriceError() {
            const userRole = this.$page.props.auth.user.role;
            const isSuperAdmin = Array.isArray(userRole) ? userRole.includes('Super Administrador') : userRole === 'Super Administrador';
            if (isSuperAdmin) return null;

            const price = parseFloat(this.currentProduct.unit_price);
            const min = parseFloat(this.currentProduct.min_price);
            if (min > 0 && price < min) return `El precio mínimo es $${this.formatNumber(min)}`;
            return null;
        },
        isPriceInvalid() {
            if (!this.priceForm.amount || this.priceForm.amount <= 0) return true;
            return this.priceForm.amount < this.priceForm.min_allowed_price;
        },
        isToolingCostRequired() {
            return this.form.products.some(p => p.is_custom);
        },
        isAddProductDisabled() {
            if (this.currentProduct.is_custom) {
                if (!this.currentProduct.custom_name) return true;
            } else {
                if (!this.currentProduct.id) return true;
            }

            return !this.currentProduct.quantity || !this.currentProduct.unit_price || this.unitPriceError;
        },
        tinymceInit() {
            return {
                height: 250, menubar: false,
                plugins: ['advlist', 'autolink', 'lists', 'link', 'charmap', 'preview', 'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen', 'insertdatetime', 'media', 'table', 'help', 'wordcount'],
                toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
                skin: 'oxide', content_css: 'default',
                content_style: `body { font-family:Helvetica,Arial,sans-serif; font-size:14px; } body.dark { background-color: #2d3748; color: #e2e8f0; } body.dark p { color: #e2e8f0; } body.dark strong { color: #fff; }`,
                language_url: `https://cdn.tiny.cloud/1/${this.tinyApiKey}/tinymce/7/langs/es_MX.js`,
                language: 'es_MX',
                setup: (editor) => { editor.on('init', () => { this.syncEditorTheme(); }); }
            };
        }
    },
    methods: {
        syncEditorTheme() {
            const isDark = document.documentElement.classList.contains('dark');
            const editorContainer = document.querySelector('.tox-tinymce'); 
            if (editorContainer) {
                isDark ? editorContainer.classList.add('tinymce-dark') : editorContainer.classList.remove('tinymce-dark');
            }
            const editor = tinymce.get('quote-notes-editor'); 
            if (editor) {
                const editorBody = editor.getBody();
                if (editorBody) {
                    isDark ? editorBody.classList.add('dark') : editorBody.classList.remove('dark');
                }
            }
        },
        
        handleImageUpload(event) {
            const file = event.target.files[0];
            if (file) {
                this.currentProduct.image = file;
                this.currentProduct.image_preview = URL.createObjectURL(file);
            } else {
                this.currentProduct.image = null;
                this.currentProduct.image_preview = null;
            }
        },

        // Nuevo método: descarga la imagen desde la URL actual para que viaje de nuevo al Backend
        async recoverCustomProductImages() {
            const qProducts = this.quote.quote_products || this.quote.quoteProducts || [];
            for (let i = 0; i < this.form.products.length; i++) {
                const product = this.form.products[i];
                // Si es personalizado, tiene previsualización pero el File real (image) está vacío:
                if (product.is_custom && product.image_preview && !product.image) {
                    try {
                        const response = await fetch(product.image_preview);
                        const blob = await response.blob();
                        const mediaObj = qProducts[i]?.media?.[0];
                        const filename = mediaObj?.file_name || `imagen_recuperada_${i}.jpg`;
                        const mimeType = mediaObj?.mime_type || blob.type || 'image/jpeg';
                        
                        // Re-asignamos el blob como si el usuario lo hubiera seleccionado desde su PC
                        product.image = new File([blob], filename, { type: mimeType });
                    } catch (error) {
                        console.warn("No se pudo rehidratar la imagen. Verifica configuraciones CORS si los archivos están en otro dominio.", error);
                    }
                }
            }
        },

        update() {
            if (this.isToolingCostRequired && !this.form.tooling_cost) {
                ElMessage.error('El costo de herramental es obligatorio debido a que hay productos nuevos.');
                return;
            }

            // Transformamos el método del formulario para asegurar que envíe los archivos
            this.form.transform((data) => ({
                ...data,
                _method: 'put', 
            })).post(route("quotes.update", this.quote.id), {
                onSuccess: () => {
                    ElMessage.success('Cotización actualizada correctamente');
                },
                onError: () => {
                    this.$refs.formContainer.scrollIntoView({ behavior: 'smooth' });
                    ElMessage.error('Por favor, revisa los errores en el formulario.');
                }
            });
        },
        addProduct() {
            const productToAdd = { ...this.currentProduct };
            
            if (this.editIndex !== null) {
                this.form.products[this.editIndex] = productToAdd;
            } else {
                this.form.products.push(productToAdd);
            }
            this.resetCurrentProduct();
        },
        editProduct(index) {
            this.currentProduct = JSON.parse(JSON.stringify(this.form.products[index]));
            
            if (this.form.products[index].image) {
                this.currentProduct.image = this.form.products[index].image;
                this.currentProduct.image_preview = this.form.products[index].image_preview;
            }

            this.editIndex = index;
            this.$refs.formProducts.scrollIntoView({ behavior: 'smooth' });
        },
        deleteProduct(index) {
            this.form.products.splice(index, 1);
            ElMessage.info('Producto eliminado de la lista');
        },
        resetCurrentProduct() {
            this.currentProduct = { 
                quote_product_id: null,
                id: null, 
                is_custom: false,
                custom_name: '',
                custom_cost: null, 
                custom_measure_unit: '', 
                image: null, 
                image_preview: null, 
                quantity: 1, 
                unit_price: null,
                min_price: 0,
                notes: '', 
                customization_details: [],
                isClientProduct: false,
                current_price: null,
                media: null,
                storages: [],
                has_customization: false,
                base_price: null,
                show_image: true,
            };
            this.editIndex = null;
        },
        addCustomizationDetail() {
            if (!this.newCustomization.type || !this.newCustomization.key || !this.newCustomization.value) {
                ElMessage.warning('Completa todos los campos de personalización.');
                return;
            }
            this.currentProduct.customization_details.push({ ...this.newCustomization });
            this.newCustomization = { type: null, key: '', value: '' };
        },
        removeCustomizationDetail(index) {
            this.currentProduct.customization_details.splice(index, 1);
            ElMessage.info('Detalle de personalización eliminado.');
        },
        getProductName(productId) {
            const product = this.localCatalogProducts.find(p => p.id === productId);
            return product ? product.name : 'Producto no encontrado';
        },
        formatDate(dateString) {
            if (!dateString) return '';
            return format(new Date(dateString), "d 'de' MMMM, yyyy", { locale: es });
        },
        formatNumber(value) {
            if (value === null || value === undefined) return '0.00';
            const num = Number(value);
            if (isNaN(num)) return '0.00';
            return new Intl.NumberFormat('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 }).format(num);
        },
        openProduct(id) { window.open(`/catalog-products/${id}`, "_blank"); },
        timeSince(dateString) { return "calculado"; }, 
        getPriceChangeClass(dateString) { return "bg-green-200"; },
        updateDrawerSize() {
            const width = window.innerWidth;
            if (width < 640) this.drawerSize = "90%";
            else if (width < 1024) this.drawerSize = "60%";
            else this.drawerSize = "35%";
        },
        confirmCloseSpecialPrice(historyId) {
            this.priceHistoryToClose = historyId;
            this.showClosePriceConfirmModal = true;
        },
        async closeSpecialPrice() {
            if (!this.priceHistoryToClose) return;
            try {
                const response = await axios.patch(route('branch-price-history.close', this.priceHistoryToClose));
                if (response.status === 200) {
                    ElMessage.success('El precio especial ha sido finalizado.');
                    this.fetchClientProducts(this.form.branch_id);
                }
            } catch (error) {
                ElMessage.error(error.response?.data?.message || 'No se pudo finalizar el precio.');
            } finally {
                this.showClosePriceConfirmModal = false;
                this.priceHistoryToClose = null;
            }
        },
        async getProductData() {
            if (!this.currentProduct.id) return;
            this.loadingProductData = true;
            try {
                const response = await axios.get(route('products.get-media', this.currentProduct.id));
                if (response.status === 200) {
                    const productData = response.data.product;
                    this.currentProduct.media = productData.media;
                    this.currentProduct.storages = productData.storages;
                    this.currentProduct.base_price = productData.base_price;
                    this.currentProduct.unit_price = productData.base_price;

                    const clientProduct = this.clientProducts.find(p => p.id === this.currentProduct.id);
                    if (clientProduct) {
                        this.currentProduct.isClientProduct = true;
                        this.currentProduct.current_price = (!clientProduct.price_history?.[0]?.valid_to && clientProduct.price_history?.[0]?.price) 
                            ? clientProduct.price_history[0].price : clientProduct.base_price;
                        this.currentProduct.unit_price = this.currentProduct.current_price;
                    } else {
                        this.currentProduct.isClientProduct = false;
                        this.currentProduct.current_price = null;
                    }
                    this.currentProduct.min_price = this.currentProduct.unit_price || 0;
                }
            } catch (error) {
                ElMessage.error('No se pudo cargar la información del producto')
            } finally {
                this.loadingProductData = false;
            }
        },
        async fetchCatalogProducts() {
            try {
                const response = await axios.post(route('products.fetch-products'), { params: { product_type: 'Catálogo' }});
                this.localCatalogProducts = response.data;
                ElMessage.success('Lista actualizada');
            } catch (error) {
                ElMessage.error('Error al actualizar');
            }
        },
        async fetchClientProducts(branchId) {
            if (!branchId) return;
            this.loadingClientProducts = true;
            try {
                const response = await axios.get(route('branches.fetch-products', branchId));
                this.clientProducts = response.data;
            } catch (error) {
                ElMessage.error('Error al cargar productos del cliente');
            } finally {
                this.loadingClientProducts = false;
            }
        },
        openPriceModal(product) { /* Lógica conservada de create */ },
        updatePriceFromAmount() { /* Lógica conservada de create */ },
        updatePriceFromPercentage() { /* Lógica conservada de create */ },
        async submitNewPrice() { /* Lógica conservada de create */ },
        handleBranchChange(branchId) { /* Lógica conservada de create */ },
        handleContactChange(contactId) { /* Lógica conservada de create */ },
        async storeQuickBranch() { /* Lógica conservada de create */ },
        async storeQuickContact() { /* Lógica conservada de create */ },
    },
    created() { 
        this.localBranches = [...this.branches]; 

        // Precarga de información general en el formulario
        this.form.branch_id = this.quote.branch_id;
        this.form.receiver = this.quote.receiver;
        this.form.department = this.quote.department;
        this.form.currency = this.quote.currency;
        this.form.tooling_cost = this.quote.tooling_cost;
        this.form.is_tooling_cost_stroked = Boolean(this.quote.is_tooling_cost_stroked);
        this.form.freight_cost = this.quote.freight_cost;
        this.form.is_freight_cost_stroked = Boolean(this.quote.is_freight_cost_stroked);
        this.form.freight_option = this.quote.freight_option;
        this.form.first_production_days = this.quote.first_production_days;
        this.form.notes = this.quote.notes || '';
        this.form.is_spanish_template = Boolean(this.quote.is_spanish_template);
        this.form.show_breakdown = Boolean(this.quote.show_breakdown);
        this.form.has_early_payment_discount = Boolean(this.quote.has_early_payment_discount);
        this.form.early_payment_discount_amount = this.quote.early_payment_discount_amount;
        
        // Precarga de los productos agregados iterando sobre las relaciones retornadas de backend
        const qProducts = this.quote.quote_products || this.quote.quoteProducts || [];
        
        this.form.products = qProducts.map(qp => {
            const isCustom = qp.product_id === null;
            return {
                quote_product_id: qp.id, // <-- Importante: Añadimos el ID original para poder identificarlo
                id: qp.product_id,
                is_custom: isCustom,
                custom_name: qp.custom_name,
                custom_cost: qp.custom_cost,
                custom_measure_unit: qp.custom_measure_unit,
                image: null, 
                image_preview: isCustom && qp.media?.length ? qp.media[0].original_url : null,
                quantity: qp.quantity,
                unit_price: qp.unit_price,
                min_price: qp.product ? (qp.product.base_price || 0) : 0,
                notes: qp.notes || '',
                customization_details: qp.customization_details || [],
                isClientProduct: qp.product_id ? true : false, 
                current_price: qp.unit_price,
                media: !isCustom && qp.product?.media ? qp.product.media : null,
                storages: qp.product?.storages || [],
                has_customization: (qp.customization_details && qp.customization_details.length > 0),
                base_price: qp.product?.base_price || null,
                show_image: Boolean(qp.show_image),
            };
        });

        // Disparamos la recuperación automática de archivos de imagen
        this.recoverCustomProductImages();
    },
    watch: {
        'form.branch_id'(newVal) {
            this.clientProducts = [];
            this.handleBranchChange(newVal);
            if (newVal) this.fetchClientProducts(newVal);
        },
        branches(newVal) { this.localBranches = [...newVal]; },
    },
    mounted() {
        this.updateDrawerSize();
        window.addEventListener("resize", this.updateDrawerSize);
    },
    beforeUnmount() { window.removeEventListener("resize", this.updateDrawerSize); },
};
</script>