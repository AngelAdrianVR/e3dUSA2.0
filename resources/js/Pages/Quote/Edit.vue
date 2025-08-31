<template>
    <AppLayout title="Editar Cotización">
        <!-- Panel Flotante de Notas -->
        <BranchNotes v-if="form.branch_id" :branch-id="form.branch_id" />

        <!-- Encabezado -->
        <div class="px-4 sm:px-0 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <Back :href="route('quotes.index')" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Editar cotización <span class="text-primary">{{ quote.folio }}</span>
                </h2>
            </div>
        </div>

        <!-- Formulario principal -->
        <div ref="formContainer" class="py-7">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
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
                            <div>
                                <InputLabel value="Cliente*" />
                                <el-select v-model="form.branch_id" filterable placeholder="Selecciona un cliente" class="!w-full">
                                    <el-option v-for="branch in branches" :key="branch.id" :label="branch.name" :value="branch.id" />
                                </el-select>
                                <InputError :message="form.errors.branch_id" />
                            </div>
                            <TextInput label="Persona que recibe*" v-model="form.receiver" :error="form.errors.receiver" placeholder="Ej. Juan Pérez" />
                            <TextInput :label="form.is_spanish_template ? 'Departamento / Puesto*' : 'Departamento / Puesto* (En inglés)'" v-model="form.department" :error="form.errors.department" placeholder="Ej. Gerente de Compras" />
                            <div>
                                <InputLabel value="Dias para primera producción*" />
                                <el-select v-model="form.first_production_days" placeholder="Selecciona">
                                    <el-option
                                        v-for="item in form.is_spanish_template ? firstProductionDaysList : firstProductionDaysListEnglish"
                                        :key="item" :label="item" :value="item" />
                                </el-select>
                                <InputError :message="form.errors.first_production_days" />
                            </div>
                            <div>
                                <InputLabel value="Moneda general*" />
                                <el-select v-model="form.currency" placeholder="Selecciona la moneda" class="!w-full">
                                    <el-option label="MXN (Peso Mexicano)" value="MXN" />
                                    <el-option label="USD (Dólar Americano)" value="USD" />
                                </el-select>
                                <InputError :message="form.errors.currency" />
                            </div>
                            <div class="col-span-1 md:col-span-full">
                                <TextInput :label="form.is_spanish_template ? 'Notas generales (opcional)' : 'Notas generales (opcional)(En inglés)'"  :isTextarea="true" :withMaxLength="true" :maxLength="500" v-model="form.notes" type="textarea" :error="form.errors.notes" />
                            </div>
                        </div>

                        <!-- SECCIÓN 2: COSTOS DE HERRAMENTAL Y FLETE -->
                        <el-divider content-position="left" class="!mt-8">
                            <span>Costos Adicionales</span>
                        </el-divider>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-5 gap-y-4 items-start">
                             <TextInput label="Costo de Herramental*" 
                                v-model="form.tooling_cost" type="number" 
                                :formatAsNumber="true" 
                                :error="form.errors.tooling_cost" 
                                :placeholder="'Ej. 500.00'" :helpContent="'Si no tiene costo, escribe 0 (Cero)'">
                                <template #icon-left><i class="fa-solid fa-dollar-sign"></i></template>
                             </TextInput>
                             <div class="flex items-center space-x-2 mt-8">
                                <label class="flex items-center">
                                    <Checkbox v-model:checked="form.is_tooling_cost_stroked" class="bg-transparent border-gray-500" />
                                    <span class="ml-2 text-gray-400">Tachar:</span>
                                </label>
                                <span class="text-gray-500" :class="{ 'line-through': form.is_tooling_cost_stroked }">
                                    ${{ formatNumber(form.tooling_cost) }} {{ form.currency }}
                                </span>
                             </div>
                             <div></div> <!-- Espaciador -->
                            <div>
                                 <InputLabel value="Opción de flete*" />
                                <el-select v-model="form.freight_option" placeholder="Selecciona el flete" class="!w-full">
                                    <el-option label="Por cuenta del cliente" value="Por cuenta del cliente" />
                                    <el-option label="Cargo prorrateado en productos" value="Cargo de flete prorrateado en productos" />
                                    <el-option label="La empresa absorbe el costo" value="La empresa absorbe el costo de flete" />
                                    <el-option label="El cliente manda la guia" value="El cliente manda la guia" />
                                </el-select>
                                <InputError :message="form.errors.freight_option" />
                            </div>
                            <TextInput v-if="form.freight_option !== 'El cliente manda la guia'" 
                                label="Costo de Flete*" v-model="form.freight_cost" :helpContent="'Si no tiene costo, escribe 0 (Cero)'" 
                                :formatAsNumber="true" type="number" :placeholder="'Ej. 500.00'" :error="form.errors.freight_cost">
                               <template #icon-left><i class="fa-solid fa-dollar-sign"></i></template>
                            </TextInput>
                            <div v-if="form.freight_option !== 'El cliente manda la guia'" class="flex items-center space-x-2 mt-8">
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
                                <span class="ml-2 text-gray-400">Mostrar total sumando productos, flete y herramental</span>
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
                                <a :href="route('catalog-products.create')" target="_blank" class="text-primary hover:underline text-sm ml-2">+ Agregar nuevo producto</a>
                            </div>
                            <div class="grid grid-cols-1 lg:grid-cols-4 gap-3 items-start">
                                <div class="lg:col-span-2">
                                    <InputLabel value="Producto" />
                                    <el-select @change="getProductData" v-model="currentProduct.id" filterable placeholder="Buscar producto" class="w-full">
                                        <el-option v-for="product in localCatalogProducts" 
                                            :key="product.id" 
                                            :label="`${product.name} (${product.code})`" 
                                            :value="product.id"
                                            :disabled="form.products.some(p => p.id === product.id) && product.id !== this.form.products[editIndex]?.id" />
                                    </el-select>
                                </div>
                                <TextInput label="Cantidad*" v-model="currentProduct.quantity" type="number" />
                                <TextInput label="Precio Unitario*" v-model="currentProduct.unit_price" type="number" :formatAsNumber="true">
                                        <template #icon-left><i class="fa-solid fa-dollar-sign"></i></template>
                                </TextInput>

                                <LoadingIsoLogo class="col-span-full" v-if="loadingProductData" />

                                <div class="flex items-start space-x-4 p-2 bg-gray-100 dark:bg-slate-900/50 rounded-md col-span-full mb-2" v-else-if="currentProduct.id">
                                    <figure 
                                        v-if="currentProduct.media" 
                                        class="relative flex items-center justify-center w-32 h-32 min-w-32 rounded-2xl border border-gray-200 dark:border-slate-900 overflow-hidden shadow-lg transition transform hover:shadow-xl">
                                        <img v-if="currentProduct.media?.length"
                                            :src="currentProduct.media[0]?.original_url" 
                                            alt="" 
                                            class="rounded-2xl w-full h-auto object-cover transition duration-300 ease-in-out hover:opacity-95"
                                        >
                                        <div v-else class="flex flex-col items-center justify-center text-gray-400 dark:text-slate-500">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                            </svg>
                                        <p>Sin imagen</p>
                                        </div>
                                    </figure>

                                    <div>
                                        <span v-if="currentProduct.isClientProduct" class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-200 rounded-full mb-2 inline-block">
                                            Producto de cliente
                                        </span>
                                        <p class="text-gray-500 dark:text-gray-300">
                                            Stock: <strong>{{ currentProduct.storages[0]?.quantity ?? 0 }}</strong> unidades
                                        </p>
                                        <p class="text-gray-500 dark:text-gray-300">
                                            Ubicación: <strong>{{ currentProduct.storages[0]?.location ?? 'No asignado' }}</strong>
                                        </p>
                                        <p class="text-gray-500 dark:text-gray-300">
                                            Precio base: <strong>${{ formatNumber(currentProduct.base_price) ?? '0.00' }}</strong>
                                        </p>
                                        <p v-if="currentProduct.isClientProduct" class="text-green-600 dark:text-green-400 font-semibold mt-1">
                                            Precio actual: <strong>${{ formatNumber(currentProduct.current_price) ?? '0.00' }}</strong>
                                        </p>
                                    </div>
                                </div>
                                <div v-if="currentProduct.id" class="col-span-full flex space-x-2 items-center">
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
                                
                                <div v-if="form.has_customization" class="lg:col-span-full mt-3 p-4 border border-dashed dark:border-slate-700 rounded-lg">
                                    <h4 class="font-semibold mb-3 text-gray-700 dark:text-gray-300">Detalles de Personalización</h4>
                                    
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

                                    <div v-if="currentProduct.customization_details.length" class="mt-4">
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
                                    <SecondaryButton @click="addProduct" type="button" :disabled="!currentProduct.id || !currentProduct.quantity || !currentProduct.unit_price">
                                        {{ editIndex !== null ? 'Actualizar producto' : 'Agregar producto' }}
                                    </SecondaryButton>
                                    <button @click="resetCurrentProduct" v-if="editIndex !== null" type="button" class="text-sm text-gray-500 hover:text-red-500 ml-3">
                                        Cancelar edición
                                    </button>
                                </div>
                            </div>
                        </div>
                        <InputError :message="form.errors.products" class="mt-2" />

                        <div v-if="form.products.length" class="mt-5">
                            <h3 class="font-bold mb-2 text-gray-800 dark:text-gray-200">Lista de productos agregados</h3>
                            <ul class="rounded-lg bg-gray-100 dark:bg-slate-800 p-3 space-y-2">
                                <li v-for="(product, index) in form.products" :key="index" class="flex justify-between items-center p-3 rounded-md transition-colors"
                                    :class="{ 'bg-blue-100 dark:bg-blue-900/50': editIndex === index }">
                                    <div class="flex items-center space-x-4">
                                        <span class="text-sm text-gray-800 dark:text-gray-200">
                                            <p class="font-bold text-primary">{{ getProductName(product.id) }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                Cantidad: {{ product.quantity }} | P.U: ${{ formatNumber(product.unit_price) }} | Subtotal: ${{ formatNumber(product.quantity * product.unit_price) }}
                                            </p>
                                            <p v-if="product.notes" class="text-xs italic text-gray-500 mt-1">Nota: {{ product.notes }}</p>
                                            
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
                                    <div class="flex items-center space-x-3">
                                        <el-tooltip content="Cancelar edición" placement="top">
                                            <button @click="resetCurrentProduct" v-if="editIndex === index" type="button" class="flex items-center justify-center text-gray-500 hover:text-red-500 transition-colors">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </el-tooltip>
                                        <!-- <el-tooltip content="Editar" placement="top">
                                            <button @click="editProduct(index)" type="button" class="text-gray-500 hover:text-blue-500 transition-colors">
                                                <i class="fa-solid fa-pencil"></i>
                                            </button>
                                        </el-tooltip> -->
                                        <el-tooltip content="Eliminar" placement="top">
                                            <button @click="deleteProduct(index)" type="button" class="text-gray-500 hover:text-red-500 transition-colors">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </el-tooltip>
                                    </div>
                                </li>
                            </ul>
                        </div>

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

                        <div class="flex justify-end mt-8 col-span-full">
                            <SecondaryButton :loading="form.processing">
                                Guardar cambios
                            </SecondaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Drawer para productos del cliente -->
        <el-drawer 
            v-model="showClientProductsDrawer" 
            title="Productos del Cliente" 
            direction="rtl" 
            :size="drawerSize"
            >
            <div class="md:p-3">
                <p v-if="!clientProducts.length && !loadingClientProducts">
                Este cliente no tiene productos registrados.
                </p>

                <!-- Estado de carga -->
                <LoadingIsoLogo class="col-span-full" v-if="loadingClientProducts" />

                <!-- Lista de productos -->
                <div v-else class="space-y-4">
                    <div 
                        v-for="product in clientProducts" 
                        :key="product.id" 
                        class="relative bg-gray-100 dark:bg-slate-900 shadow-md rounded-2xl p-4 transition hover:shadow-xl duration-300"
                    >
                        <!-- BOTONES DE ACCIÓN -->
                        <div class="absolute top-2 right-2 flex items-center space-x-1">
                            <el-tooltip content="Actualizar precio especial" placement="top">
                                <button @click="openPriceModal(product)" class="flex items-center justify-center hover:bg-gray-200 dark:hover:bg-slate-800 rounded-full size-8 transition-colors">
                                    <i class="fa-solid fa-dollar-sign text-sm text-gray-500 dark:text-gray-600"></i>
                                </button>
                            </el-tooltip>
                            <el-tooltip content="Ver producto" placement="top">
                                <button @click="openProduct(product.id)" class="flex items-center justify-center hover:bg-gray-200 dark:hover:bg-slate-800 rounded-full size-8 transition-colors">
                                    <i class="fa-solid fa-eye text-gray-500 dark:text-gray-600"></i>
                                </button>
                            </el-tooltip>
                        </div>

                        <!-- Imagen -->
                        <div class="flex items-center gap-4">
                        <img 
                            v-if="product.media?.length" 
                            :src="product.media[0].original_url" 
                            alt="Imagen del producto" 
                            class="w-20 h-20 object-cover rounded-xl border dark:border-gray-700"
                        />
                        <div class="flex-1">
                            <p class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                            {{ product.name }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                            Código: {{ product.code }}
                            </p>
                        </div>
                        </div>

                        <!-- Precios -->
                        <div class="mt-4 flex items-center justify-between">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Precio base</p>
                        <p class="font-medium text-secondary">${{ product.base_price }}</p>
                        </div>
                        <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Precio actual</p>
                        <p class="font-semibold text-green-600 dark:text-green-400">
                        ${{ !product.price_history?.[0]?.valid_to && product.price_history?.[0]?.price 
                                ? product.price_history[0].price 
                                : product.base_price }}
                        </p>
                        </div>

                        <!-- Último cambio de precio -->
                        <div 
                            v-if="product.price_history?.length" 
                            class="mt-2 text-sm rounded-sm py-1 px-2"
                            :class="getPriceChangeClass(product.price_history[0].valid_from)"
                            >
                            Último cambio de precio: {{ timeSince(product.price_history[0].valid_from) }}
                        </div>

                        <!-- Historial de precios (MODIFICADO CON ETIQUETAS) -->
                        <el-collapse v-if="product.price_history?.length" class="mt-4">
                            <el-collapse-item :title="'Historial de precios'" name="history">
                                <ul class="space-y-2 max-h-40 overflow-y-auto pr-2 text-sm">
                                <li 
                                    v-for="(history, idx) in product.price_history" 
                                    :key="idx" 
                                    class="flex justify-between items-center text-gray-600 dark:text-gray-400"
                                >
                                    <div class="flex items-center space-x-2">
                                        <span>{{ formatDate(history.valid_from) }}</span>
                                        <!-- ETIQUETAS DE ESTADO -->
                                        <span v-if="!history.valid_to" class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">Actual</span>
                                        <span v-else class="px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">Cerrado</span>
                                    </div>
                                    <div class="flex items-center space-x-1">
                                        <span class="font-medium">${{ history.price }} {{ history.currency }}</span>
                                        <!-- BOTÓN PARA FINALIZAR PRECIO ACTIVO -->
                                        <el-tooltip v-if="!history.valid_to" content="Finalizar vigencia de este precio" placement="top">
                                            <button @click="confirmCloseSpecialPrice(history.id)" class="size-7 flex items-center justify-center rounded-md text-red-500 bg-red-100 hover:bg-red-200 dark:bg-red-900/50 dark:hover:bg-red-900 transition-colors">
                                                <i class="fa-solid fa-calendar-xmark text-sm"></i>
                                            </button>
                                        </el-tooltip>
                                    </div>
                                </li>
                                </ul>
                            </el-collapse-item>
                        </el-collapse>

                        <p class="text-sm text-gray-600 dark:text-gray-500 italic mt-3" v-else>No cuenta con precio especial, así que se toma el precio base del producto</p>
                    </div>
                </div>
            </div>
        </el-drawer>

        <ConfirmationModal :show="showPriceModal" @close="showPriceModal = false">
            <template #title>
                Actualizar precio de <span class="text-blue-500">{{ productForUpdate?.name }}</span>
            </template>
            <template #content>
                <div class="space-y-4 text-sm dark:text-gray-300">
                    <p>El precio de referencia actual es <strong class="font-semibold">${{ priceForm.current_base_price }}</strong>. El nuevo precio no puede ser inferior al actual y el aumento debe ser de al menos 4%.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                        <div>
                            <label class="font-semibold">Aumento en porcentaje*</label>
                             <el-input v-model="priceForm.percentage" @input="updatePriceFromPercentage" placeholder="Ej. 5" class="mt-1">
                                <template #append>%</template>
                            </el-input>
                        </div>
                         <div>
                            <label class="font-semibold">Precio nuevo en moneda*</label>
                             <el-input v-model="priceForm.amount" @input="updatePriceFromAmount" placeholder="Ej. 44.10" class="mt-1">
                                <template #prepend>$</template>
                            </el-input>
                        </div>
                    </div>
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="font-semibold">Moneda*</label>
                            <el-select v-model="priceForm.currency" placeholder="Moneda" :teleported="false" class="!w-full mt-1">
                                <el-option label="MXN" value="MXN" />
                                <el-option label="USD" value="USD" />
                            </el-select>
                        </div>
                        <div>
                            <label class="font-semibold">Fecha de cambio (Vigente desde)*</label>
                            <el-date-picker v-model="priceForm.valid_from" type="date" :teleported="false" placeholder="Selecciona una fecha" class="!w-full mt-1" />
                        </div>
                    </div>
                    <div v-if="priceForm.amount && isPriceInvalid" class="text-red-500 text-xs mt-1 p-2 bg-red-50 dark:bg-red-900/40 rounded-md">
                        <i class="fa-solid fa-circle-exclamation mr-1"></i>
                        El precio debe ser mayor o igual a ${{ priceForm.min_allowed_price.toFixed(2) }} (aumento mínimo del 4%).
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="showPriceModal = false">Cancelar</CancelButton>
                    <PrimaryButton @click="submitNewPrice" :disabled="isPriceInvalid" class="!bg-blue-600 hover:!bg-blue-700 disabled:!bg-blue-300 dark:disabled:!bg-slate-600">Actualizar precio</PrimaryButton>
                </div>
            </template>
        </ConfirmationModal>

        <!-- Confirmación para Finalizar Precio -->
        <ConfirmationModal :show="showClosePriceConfirmModal" @close="showClosePriceConfirmModal = false">
            <template #title>
                Finalizar Precio Especial
            </template>
            <template #content>
                ¿Estás seguro de que deseas finalizar la vigencia de este precio especial? El producto volverá a su precio base para este cliente.
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="showClosePriceConfirmModal = false">Cancelar</CancelButton>
                    <PrimaryButton @click="closeSpecialPrice" class="!bg-red-600 hover:!bg-red-700">Sí, finalizar</PrimaryButton>
                </div>
            </template>
        </ConfirmationModal>

    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import BranchNotes from "@/Components/MyComponents/BranchNotes.vue";
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

export default {
    // Usando Options API
    data() {
        return {
            form: useForm({
                branch_id: this.quote.branch_id,
                receiver: this.quote.receiver,
                department: this.quote.department,
                currency: this.quote.currency,
                tooling_cost: this.quote.tooling_cost,
                is_tooling_cost_stroked: this.quote.is_tooling_cost_stroked,
                freight_cost: this.quote.freight_cost,
                is_freight_cost_stroked: this.quote.is_freight_cost_stroked,
                freight_option: this.quote.freight_option,
                first_production_days: this.quote.first_production_days,
                notes: this.quote.notes,
                is_spanish_template: this.quote.is_spanish_template,
                show_breakdown: this.quote.show_breakdown,
                has_early_payment_discount: this.quote.has_early_payment_discount,
                early_payment_discount_amount: this.quote.early_payment_discount_amount || null,
                has_customization: false, // Se determina dinámicamente
                products: [], // Se llena en mounted
            }),

            // --- para cerrar precio especial ---
            showClosePriceConfirmModal: false,
            priceHistoryToClose: null,

            // --- DATOS PARA EL MODAL DE PRECIO ---
            showPriceModal: false,
            productForUpdate: null,
            priceForm: {
                amount: null,
                percentage: null,
                currency: 'MXN',
                valid_from: new Date(),
                current_base_price: 0,
                min_allowed_price: 0,
            },
            // Se agregan más propiedades para manejar el estado del producto actual
            currentProduct: {
                id: null,
                quantity: 1,
                unit_price: null,
                notes: '',
                customization_details: [], // <--- MODIFICADO: Ahora es un array
                isClientProduct: false,
                current_price: null,
                show_image: true,
                media: null,
                storages: [],
                base_price: null,
            },
            // --- NUEVO: Estado para el formulario de personalización ---
            newCustomization: {
                type: null,
                key: '',
                value: ''
            },
            customizationTypes: [
                'Grabado de medallón',
                'Estampado',
                'Bordado',
                'Impresión digital',
                'Otro'
            ],
            // --- FIN NUEVO ---
            editIndex: null,
            localCatalogProducts: this.catalogProducts,
            showClientProductsDrawer: false,
            clientProducts: [],
            loadingClientProducts: false,
            loadingProductData: false,
            drawerSize: "35%", // valor inicial
            firstProductionDaysList: [
                'Inmediata',
                '1 a 2 días',
                '2 a 3 días',
                '3 a 4 días',
                '4 a 5 días',
                '5 a 6 días',
                '1 a 2 semanas',
                '3 a 4 semanas',
                '5 a 6 semanas',
                '7 a 8 semanas',
                '9 a 10 semanas',
                '11 a 12 semanas',
                '13 a 14 semanas',
                '15 a 16 semanas',
                '17 a 18 semanas',
            ],
            firstProductionDaysListEnglish: [
                'Immediate',
                '1 to 2 days',
                '2 to 3 days',
                '3 to 4 days',
                '4 to 5 days',
                '5 to 6 days',
                '1 to 2 weeks',
                '3 to 4 weeks',
                '5 to 6 weeks',
                '7 to 8 weeks',
                '9 to 10 weeks',
                '11 to 12 weeks',
                '13 to 14 weeks',
                '15 to 16 weeks',
                '17 to 18 weeks',
            ],
        };
    },
    components: {
        Back,
        Checkbox,
        TextInput,
        AppLayout,
        InputError,
        InputLabel,
        BranchNotes,
        CancelButton,
        PrimaryButton,
        LoadingIsoLogo,
        SecondaryButton,
        ConfirmationModal,
    },
    props: {
        quote: Object,
        catalogProducts: Array,
        branches: Array
    },
    computed: {
        isPriceInvalid() {
            if (!this.priceForm.amount || this.priceForm.amount <= 0) return true;
            // if (this.priceForm.amount < this.priceForm.current_base_price) return true;
            return this.priceForm.amount < this.priceForm.min_allowed_price;
        }
    },
    methods: {
        update() {
            this.form.put(route("quotes.update", this.quote), {
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
            // Clonar el objeto para evitar reactividad no deseada
            const productToAdd = { ...this.currentProduct };
            
            if (this.editIndex !== null) {
                this.form.products[this.editIndex] = productToAdd;
            } else {
                this.form.products.push(productToAdd);
            }
            this.resetCurrentProduct();
        },
        editProduct(index) {
            // Clonado profundo para evitar reactividad no deseada al editar
            this.currentProduct = JSON.parse(JSON.stringify(this.form.products[index]));
            this.editIndex = index;
            // Hacer scroll a la sección de productos para una mejor UX
            this.$refs.formProducts.scrollIntoView({ behavior: 'smooth' });
        },
        deleteProduct(index) {
            this.form.products.splice(index, 1);
            ElMessage.info('Producto eliminado de la lista');
        },
        resetCurrentProduct() {
            this.currentProduct = { 
                id: null, 
                quantity: 1, 
                unit_price: null, 
                notes: '', 
                customization_details: [], // <--- MODIFICADO
                isClientProduct: false,
                current_price: null,
                media: null,
                storages: [],
                base_price: null,
                show_image: true,
            };
            this.editIndex = null;
        },
        // --- INICIO: Nuevos métodos para personalización ---
        addCustomizationDetail() {
            if (!this.newCustomization.type || !this.newCustomization.key || !this.newCustomization.value) {
                ElMessage.warning('Completa todos los campos de personalización.');
                return;
            }
            this.currentProduct.customization_details.push({ ...this.newCustomization });
            // Resetear el formulario de personalización
            this.newCustomization = { type: null, key: '', value: '' };
        },
        removeCustomizationDetail(index) {
            this.currentProduct.customization_details.splice(index, 1);
            ElMessage.info('Detalle de personalización eliminado.');
        },
        // --- FIN: Nuevos métodos para personalización ---
        getProductName(productId) {
            const product = this.localCatalogProducts.find(p => p.id === productId);
            return product ? product.name : 'Producto no encontrado';
        },
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        },
        formatNumber(value) {
            if (value === null || value === undefined) return '0.00';
            // Asegurarse de que el valor es un número antes de formatear
            const num = Number(value);
            if (isNaN(num)) return '0.00';
            return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
        },

        // ------ Metodos para el drawer ( productos del cliente ) -------
        openProduct(id) {
            window.open(`/catalog-products/${id}`, "_blank");
        },
        timeSince(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffMonths = diffMs / (1000 * 60 * 60 * 24 * 30);

            if (diffMonths < 1) return "menos de un mes";
            if (diffMonths < 12) {
                const months = Math.floor(diffMonths);
                return `hace ${months} mes${months > 1 ? "es" : ""}`;
            }
            const years = Math.floor(diffMonths / 12);
            return `hace ${years} año${years > 1 ? "s" : ""}`;
            },
            getPriceChangeClass(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffMonths = diffMs / (1000 * 60 * 60 * 24 * 30);

            if (diffMonths <= 6) return "bg-green-200";
            if (diffMonths > 6 && diffMonths < 12) return "bg-amber-300";
            return "bg-red-400";
        },
        updateDrawerSize() {
            const width = window.innerWidth;
            if (width < 640) {
                this.drawerSize = "90%"; // móvil
            } else if (width < 1024) {
                this.drawerSize = "60%"; // tablet
            } else {
                this.drawerSize = "35%"; // desktop
            }
        },

        // --- Metodos para cerrar precio especial ---
        confirmCloseSpecialPrice(historyId) {
            this.priceHistoryToClose = historyId;
            this.showClosePriceConfirmModal = true;
        },

        async closeSpecialPrice() {
            if (!this.priceHistoryToClose) return;
            try {
                // Usamos PATCH para indicar una actualización parcial del recurso
                const response = await axios.patch(route('branch-price-history.close', this.priceHistoryToClose));
                if (response.status === 200) {
                    ElMessage.success('El precio especial ha sido finalizado.');
                    this.fetchClientProducts(this.form.branch_id);s
                }
            } catch (error) {
                console.error("Error al finalizar el precio:", error);
                ElMessage.error(error.response?.data?.message || 'No se pudo finalizar el precio.');
            } finally {
                this.showClosePriceConfirmModal = false;
                this.priceHistoryToClose = null;
            }
        },

        // ------- Metodos asíncronos -----------
        async getProductData() {
            if (!this.currentProduct.id) return;
            // 1. Inicia el estado de carga
            this.loadingProductData = true;
            try {
                const response = await axios.get(route('products.get-media', this.currentProduct.id));

                if ( response.status === 200 ) {
                    const productData = response.data.product;
                    this.currentProduct.media = productData.media;
                    this.currentProduct.storages = productData.storages;
                    this.currentProduct.base_price = productData.base_price;
                    this.currentProduct.unit_price = productData.base_price;

                    // --- NUEVA LÓGICA ---
                    // Revisa si es un producto registrado por el cliente para obtener su precio especial
                    const clientProduct = this.clientProducts.find(p => p.id === this.currentProduct.id);
                    if (clientProduct) {
                        this.currentProduct.isClientProduct = true;
                        // Obtiene el precio actual del historial o usa el precio base como fallback
                        this.currentProduct.current_price = 
                        (!clientProduct.price_history?.[0]?.valid_to && clientProduct.price_history?.[0]?.price) 
                            ? clientProduct.price_history[0].price 
                            : clientProduct.base_price;

                        // Asigna el precio del cliente como precio unitario por defecto
                        this.currentProduct.unit_price = this.currentProduct.current_price;
                    } else {
                        this.currentProduct.isClientProduct = false;
                        this.currentProduct.current_price = null;
                    }
                }
            } catch (error) {
                console.log(error);
                ElMessage.error('No se pudo cargar la información del producto')
            } finally {
                this.loadingProductData = false;
            }
        },
        // Actualiza los productos de catálogo
        async fetchCatalogProducts() {
            try {
                const response = await axios.post(route('products.fetch-products'), {
                    params: { product_type: 'Catálogo' }
                });
                this.localCatalogProducts = response.data;
                ElMessage.success('Lista de productos actualizada');
            } catch (error) {
                ElMessage.error('No se pudo actualizar la lista de productos.');
            }
        },
        async fetchClientProducts(branchId) {
            if (!branchId) return;
            this.loadingClientProducts = true;
            try {
                const response = await axios.get(route('branches.fetch-products', branchId));
                this.clientProducts = response.data;
            } catch (error) {
                console.error("Error fetching client products:", error);
                ElMessage.error('No se pudieron cargar los productos del cliente.');
            } finally {
                this.loadingClientProducts = false;
            }
        },

        // --- MÉTODOS PARA GESTIÓN DE PRECIOS ---
        openPriceModal(product) {
            const basePrice = product.price_history?.[0]?.price ?? product.base_price;
            
            this.productForUpdate = product;
            this.priceForm = {
                amount: null,
                percentage: null,
                currency: 'MXN',
                valid_from: new Date(),
                current_base_price: basePrice,
                min_allowed_price: basePrice * 1.04, // Regla de aumento del 4%
            };
            this.showPriceModal = true;
        },

        updatePriceFromAmount() {
            if (this.priceForm.amount && this.priceForm.current_base_price > 0) {
                const percentage = ((this.priceForm.amount / this.priceForm.current_base_price) - 1) * 100;
                this.priceForm.percentage = percentage.toFixed(2);
            } else {
                this.priceForm.percentage = null;
            }
        },

        updatePriceFromPercentage() {
            if (this.priceForm.percentage !== null && this.priceForm.percentage !== '') {
                const newAmount = this.priceForm.current_base_price * (1 + (this.priceForm.percentage / 100));
                this.priceForm.amount = newAmount.toFixed(2);
            } else {
                this.priceForm.amount = null;
            }
        },

        async submitNewPrice() {
            if (this.isPriceInvalid) {
                ElMessage.error('El precio ingresado no es válido o es menor al permitido.');
                return;
            }

            try {
                const routeName = 'branches.products.price.store';
                // Usamos el ID del cliente del formulario principal
                const routeParams = { branch: this.form.branch_id, product: this.productForUpdate.id };
                
                const response = await axios.post(route(routeName, routeParams), this.priceForm);

                if (response.status === 200) {
                    ElMessage.success('Precio actualizado correctamente.');
                    this.showPriceModal = false;
                    // Recargamos solo los productos del cliente para no afectar el formulario
                    this.fetchClientProducts(this.form.branch_id);
                }
            } catch (error) {
                console.error("Error al actualizar el precio:", error);
                ElMessage.error(error.response?.data?.message || 'Ocurrió un error al guardar el precio.');
            }
        },
    },
    watch: {
        // Observador para cargar productos del cliente cuando se selecciona uno
        'form.branch_id'(newVal) {
            this.clientProducts = []; // Limpiar la lista anterior
            if (newVal) {
                this.fetchClientProducts(newVal);
            }
        }
    },
    mounted() {
        // Mapear los productos de la cotización al formato que espera el formulario
        this.form.products = this.quote.products.map(product => ({
            id: product.id,
            quantity: product.pivot.quantity,
            unit_price: product.pivot.unit_price,
            notes: product.pivot.notes,
            show_image: product.pivot.show_image,
            // Asegurarse de que customization_details sea un array
            customization_details: product.pivot.customization_details ? JSON.parse(JSON.stringify(product.pivot.customization_details)) : [],
            // Puedes agregar más campos si es necesario, aunque no se guarden en el pivote
            name: product.name, 
            code: product.code,
        }));

        // Determinar si algún producto tiene personalización para activar el checkbox principal
        this.form.has_customization = this.form.products.some(p => p.customization_details && p.customization_details.length > 0);

        // Cargar productos del cliente inicial
        if (this.form.branch_id) {
            this.fetchClientProducts(this.form.branch_id);
        }
        
        this.updateDrawerSize();
        window.addEventListener("resize", this.updateDrawerSize);
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.updateDrawerSize);
    },
};
</script>
