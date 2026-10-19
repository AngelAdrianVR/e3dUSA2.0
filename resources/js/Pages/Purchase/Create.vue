<template>
    <AppLayout title="Crear Orden de Compra">
        <!-- Productos a favor -->
        <SupplierFavoredProducts v-if="form.supplier_id" :supplier-id="form.supplier_id" />

        <!-- Encabezado de la página -->
        <div class="px-4 sm:px-0">
            <div class="flex items-center space-x-2">
                <Back :href="route('purchases.index')" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Crear nueva órden de compra
                </h2>
            </div>
        </div>

        <!-- Contenedor del formulario -->
        <div ref="formContainer" class="py-7">
            <div class="max-w-[72rem] mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6 md:p-8">
                    
                    <form @submit.prevent="store">
                        <!-- SECCIÓN DE DATOS GENERALES -->
                        <section class="mb-8">
                            <h3 class="font-bold text-lg text-gray-800 dark:text-gray-200 mb-4 border-b pb-2">Datos Generales</h3>
                             <!-- Opciones de Plantilla y Tipo -->
                            <div class="col-span-1 md:col-span-3 grid grid-cols-2 gap-x-5 mb-5">
                                <div>
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-1">Idioma de la plantilla</label>
                                    <el-radio-group v-model="form.is_spanish_template" class="!w-full">
                                        <el-radio-button :label="true">Español</el-radio-button>
                                        <el-radio-button :label="false">Inglés</el-radio-button>
                                    </el-radio-group>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-x-5 gap-y-4">
                                <!-- Requisición que origina la orden (opcional) -->
                                <div>
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-1" for="requisition">Requisición vinculada</label>
                                    <el-select v-model="form.requisition_id" filterable clearable placeholder="Sin requisición"
                                        class="!w-full" @change="onRequisitionChange">
                                        <el-option v-for="requisition in requisitions" :key="requisition.id" :value="requisition.id"
                                            :label="`${requisition.folio} · ${requisition.origin} · ${requisition.branch ?? requisition.business_name ?? 'Sin cliente'}`" />
                                    </el-select>
                                    <InputError :message="form.errors.requisition_id" />
                                </div>
                                <!-- Selector de Proveedor -->
                                <div>
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-1" for="supplier">Proveedor*</label>
                                    <el-select @change="fetchSupplierDetails" v-model="form.supplier_id" filterable placeholder="Selecciona un proveedor" class="!w-full">
                                        <el-option v-for="item in suppliers" :key="item.id" :label="item.name" :value="item.id" />
                                    </el-select>
                                    <InputError :message="form.errors.supplier_id" />
                                </div>
                                <!-- Selector de Contacto -->
                                <div>
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-1" for="contact">Contacto</label>
                                    <el-select v-model="form.supplier_contact_id" filterable placeholder="Selecciona un contacto" class="!w-full" :disabled="!form.supplier_id">
                                        <el-option v-for="item in supplierContacts" :key="item.id" :label="item.name" :value="item.id" />
                                    </el-select>
                                    <InputError :message="form.errors.supplier_contact_id" />
                                </div>
                                <!-- Selector de Cuenta Bancaria -->
                                <div>
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-1" for="bank_account">Información bancaria</label>
                                    <el-select v-model="form.supplier_bank_account_id" filterable placeholder="Selecciona una cuenta" class="!w-full" :disabled="!form.supplier_id">
                                        <el-option v-for="item in supplierBankAccounts" :key="item.id" :label="`${item.bank_name} - ${item.account_number}`" :value="item.id" />
                                    </el-select>
                                    <InputError :message="form.errors.supplier_bank_account_id" />
                                </div>
                                <!-- Selector de Moneda -->
                                <div>
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-1" for="currency">Moneda*</label>
                                    <el-select v-model="form.currency" placeholder="Selecciona" class="!w-full">
                                        <el-option label="MXN" value="MXN" />
                                        <el-option label="USD" value="USD" />
                                    </el-select>
                                    <InputError :message="form.errors.currency" />
                                </div>
                                <!-- Selector de Fecha de Entrega (opcional) -->
                                <div>
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-1" for="delivery_date">Fecha de entrega esperada (opcional)</label>
                                    <el-date-picker v-model="form.expected_delivery_date" type="date" placeholder="Selecciona una fecha" format="YYYY/MM/DD" value-format="YYYY-MM-DD" :disabled-date="disabledDate" class="!w-full" />
                                    <InputError :message="form.errors.expected_delivery_date" />
                                </div>

                                <div class="col-span-2 mt-5">
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-1 mb-1" for="currency">Archivos extra (max. 3)</label>
                                    <FileUploader @files-selected="form.media = $event" :multiple="true" acceptedFormat="todo" :max-files="3" />
                                    <InputError :message="form.errors.media" class="mt-2" />
                                </div>
                                
                                <!-- Campo de Notas -->
                                <div class="col-span-1 md:col-span-3">
                                    <TextInput label="Notas" :withMaxLength="true"
                                        :maxLength="255" 
                                        v-model="form.notes" 
                                        type="text" 
                                        :error="form.errors.notes" 
                                        placeholder="Notas internas o para el proveedor" />
                                </div>
                                
                            </div>

                            <!-- Detalle de la requisición vinculada -->
                            <div v-if="selectedRequisition"
                                class="mt-5 rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-4 space-y-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-bold text-gray-800 dark:text-gray-100 font-mono">{{
                                        selectedRequisition.folio }}</p>
                                    <el-tag size="small" :type="selectedRequisition.type === 'Importación' ? 'primary' : 'success'">
                                        {{ selectedRequisition.type }}
                                    </el-tag>
                                    <el-tag size="small" :type="selectedRequisition.origin === 'Vendedor' ? 'primary' : 'warning'">
                                        {{ selectedRequisition.origin }}
                                    </el-tag>
                                    <el-tag size="small" type="info">{{ selectedRequisition.status }}</el-tag>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ selectedRequisition.branch ?? selectedRequisition.business_name ?? 'Sin cliente' }}
                                    </span>
                                </div>

                                <!-- Resurtido: productos de catálogo que ya se precargaron -->
                                <div v-if="selectedRequisition.origin === 'Almacén'" class="space-y-2">
                                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-300">
                                        <i class="fa-solid fa-boxes-stacked mr-1"></i>
                                        Productos solicitados (se precargaron en la orden)
                                    </p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <div v-for="item in selectedRequisition.items" :key="item.id"
                                            class="flex items-center gap-3 rounded-lg border border-blue-200/70 dark:border-slate-700 bg-white/80 dark:bg-slate-900/40 p-2">
                                            <img v-if="item.image_url" :src="item.image_url" :alt="item.name"
                                                class="size-12 rounded object-cover border border-gray-200 dark:border-slate-700" />
                                            <div v-else class="size-12 rounded border border-dashed border-gray-300 dark:border-slate-700 flex items-center justify-center text-gray-300">
                                                <i class="fa-regular fa-image"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">
                                                    {{ item.name ?? 'Producto de catálogo' }}
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                    {{ item.code ?? 'Sin código' }}
                                                </p>
                                                <p class="text-xs font-semibold text-blue-700 dark:text-blue-300">
                                                    Requerido: {{ formatRequisitionQuantity(item.quantity) }} {{ item.measure_unit ?? '' }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Indicación: la distribución es obligatoria antes de crear la orden -->
                                    <div class="rounded-lg border border-amber-300 dark:border-amber-700/60 bg-amber-50 dark:bg-amber-900/20 px-3 py-2">
                                        <p class="text-xs font-semibold text-amber-800 dark:text-amber-300">
                                            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                                            Define la vía de envío de cada producto precargado
                                        </p>
                                        <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">
                                            Edita cada producto de la lista con el ícono del lápiz y captura cuántas piezas
                                            viajan por <strong>avión</strong>, cuántas por <strong>barco</strong> y cuántas se
                                            <strong>quedan en fábrica</strong>. La suma debe ser igual a la cantidad solicitada;
                                            sin ese dato no se puede crear la orden de compra.
                                        </p>
                                    </div>
                                </div>

                                <!-- Producto nuevo: información capturada en la requisición -->
                                <div v-else class="space-y-2">
                                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-300">
                                        <i class="fa-solid fa-circle-info mr-1"></i>
                                        Información del producto nuevo
                                    </p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Cantidad requerida</p>
                                            <p class="font-semibold text-gray-800 dark:text-gray-100">
                                                {{ formatRequisitionQuantity(selectedRequisition.quantity) }} pieza(s)
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Características / especificación</p>
                                            <p class="font-semibold text-gray-800 dark:text-gray-100">
                                                {{ selectedRequisition.sale_specification || 'Sin especificación registrada' }}
                                            </p>
                                        </div>
                                        <div v-if="newProductDescriptions.length" class="sm:col-span-2">
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Descripción de la partida</p>
                                            <p class="text-gray-800 dark:text-gray-100">{{ newProductDescriptions.join(' · ') }}</p>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        <i class="fa-solid fa-circle-info mr-1"></i>
                                        La requisición es de producto nuevo: agrega el producto de catálogo cuando exista para poder comprarlo.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <!-- SECCIÓN DE PRODUCTOS -->
                        <section class="mb-5">
                            <h3 class="font-bold text-lg text-gray-800 dark:text-gray-200 mb-4 border-b pb-2">Productos</h3>
                            <div v-if="!form.supplier_id" class="text-center text-sm text-gray-500 bg-gray-100 dark:bg-slate-800 rounded-lg p-4">
                                <p>Por favor, selecciona un proveedor para poder agregar productos.</p>
                            </div>
                            <div ref="formProduct" v-else>
                                <!-- Formulario para agregar/editar producto -->
                                <div class="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg grid grid-cols-1 md:grid-cols-4 gap-x-3 gap-y-4 items-end relative">
                                    <div class="md:col-span-full">
                                        <div class="w-1/2">
                                            <label class="text-gray-700 dark:text-gray-100 text-sm ml-1">Producto*</label>
                                            <el-select-v2 v-model="productForm.product_id" filterable placeholder="Busca un producto" :options="availableProducts" @change="onProductSelect" class="!w-full" />
                                        </div>
                                        <LoadingIsoLogo v-if="loadingProductMedia" />

                                        <!-- Tarjeta de materia prima seleccionada -->
                                        <div class="md:absolute top-3 right-3 mt-2 flex items-center space-x-4 p-2 bg-gray-100 dark:bg-slate-900/50 rounded-md col-span-full mb-2" v-else-if="productForm.product_id">
                                            <figure 
                                                v-if="productForm.media" 
                                                class="relative flex items-center justify-center size-32 rounded-2xl border border-gray-200 dark:border-slate-900 overflow-hidden shadow-lg transition transform hover:shadow-xl">
                                                <img v-if="productForm.media?.length"
                                                    :src="productForm.media[0]?.original_url" 
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
                                            <div class="text-sm">
                                                <p class="text-gray-500 dark:text-gray-300">
                                                    Stock: <strong>{{ productForm.storages?.[0]?.quantity?.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",") || 0 }} {{ productForm.measure_unit }}</strong>
                                                </p>
                                                <p class="text-gray-500 dark:text-gray-300 mt-1">
                                                    Mín: <strong>{{ productForm.min_quantity?.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",") || 0 }}</strong> | 
                                                    Máx: <strong>{{ productForm.max_quantity?.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",") || 'N/A' }}</strong>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <TextInput label="Cantidad*" v-model.number="productForm.quantity" type="numeric-stepper" placeholder="0" />
                                    </div>
                                    <div>
                                        <TextInput label="Precio Unitario*" v-model="productForm.unit_price" type="text" placeholder="0.00" :formatAsNumber="true">
                                            <template #icon-left><i class="fa-solid fa-dollar-sign"></i></template>
                                        </TextInput>
                                    </div>
                                    <div class="md:col-span-2"></div> <!-- Spacer -->

                                    <!-- Distribución de stock -->
                                    <div v-if="form.type === 'Venta'" class="md:col-span-full">
                                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Vía de envío y cantidad que viaja</p>
                                        <div class="grid grid-cols-3 gap-x-2 border p-3 rounded-md" :class="{
                                                'border-red-500': stockRemaining < 0,
                                                'border-green-500': stockRemaining === 0,
                                                'border-amber-500': stockRemaining > 0
                                        }">
                                            <TextInput label="Por avión" v-model.number="productForm.plane_stock" type="numeric-stepper" placeholder="0" />
                                            <TextInput label="Por barco" v-model.number="productForm.ship_stock" type="numeric-stepper" placeholder="0" />
                                            <TextInput label="Se queda en fábrica" v-model.number="productForm.additional_stock" type="numeric-stepper" placeholder="0" />
                                        </div>
                                        <p v-if="stockRemaining !== 0" :class="stockRemaining < 0 ? 'text-red-500' : 'text-amber-500'" class="text-xs mt-1">
                                            <i class="fa-solid fa-circle-info mr-1"></i> Cantidad por asignar: {{ stockRemaining }}
                                        </p>
                                    </div>

                                    <!-- Tipo de compra -->
                                    <div class="col-span-full grid grid-cols-1 md:grid-cols-3">
                                        <div>
                                            <label class="text-gray-700 dark:text-gray-100 text-sm ml-1">Tipo de compra</label>
                                            <el-radio-group v-model="productForm.type" class="!w-full">
                                                <el-radio-button label="Venta">Para Venta</el-radio-button>
                                                <el-radio-button label="Muestra">Para Muestras</el-radio-button>
                                            </el-radio-group>
                                        </div>
                                        <div class="md:col-span-2 flex items-center space-x-3">
                                            <el-checkbox @change="!productForm.needs_mold ? productForm.mold_price = 0 : ''" v-model="productForm.needs_mold">¿Requiere molde?</el-checkbox>
                                            <TextInput 
                                                v-if="productForm.needs_mold" 
                                                v-model.number="productForm.mold_price" 
                                                label="Precio del molde" 
                                                helpContent="En caso de no saber el costo, dejarlo vacío o en 0."
                                                type="text" 
                                                placeholder="0.00" 
                                                :formatAsNumber="true"
                                            >
                                                <template #icon-left><i class="fa-solid fa-dollar-sign"></i></template>
                                            </TextInput>
                                        </div>
                                    </div>

                                    <!-- Notas del producto -->
                                    <div class="md:col-span-full">
                                        <TextInput
                                            v-model="productForm.notes"
                                            label="Notas del producto"
                                            :withMaxLength="true"
                                            :maxLength="255"
                                        />
                                    </div>
                                    
                                    <div class="md:col-span-full">
                                        <PrimaryButton @click="addProduct" type="button" :disabled="!productForm.product_id || !productForm.quantity > 0">
                                            <i :class="isEditing ? 'fa-solid fa-check' : 'fa-solid fa-plus'" class="mr-2"></i> {{ isEditing ? 'Actualizar' : 'Agregar' }}
                                        </PrimaryButton>
                                        <button v-if="isEditing" @click="cancelEdit" type="button" class="ml-2 text-sm text-gray-500 hover:text-gray-800 dark:hover:text-red-500">Cancelar</button>
                                    </div>
                                </div>

                                <!-- Distribución pendiente: bloquea la creación de la orden -->
                                <div v-if="missingDistribution.length"
                                    class="mt-4 rounded-lg border border-amber-300 dark:border-amber-700/60 bg-amber-50 dark:bg-amber-900/20 px-4 py-3">
                                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                                        <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                                        Falta la distribución de {{ missingDistribution.length }} producto(s)
                                    </p>
                                    <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">
                                        Edita cada producto de la lista con el ícono del lápiz y captura cuántas piezas viajan
                                        por avión, cuántas por barco y cuántas se quedan en fábrica. La suma debe ser igual a
                                        la cantidad solicitada para poder crear la orden.
                                    </p>
                                </div>

                                <!-- Tabla de productos agregados -->
                                <div class="mt-4 overflow-x-auto">
                                    <el-table :data="form.items" stripe class="!w-full">
                                        <el-table-column prop="product_name" label="Producto" min-width="150" />
                                        <el-table-column prop="notes" label="Notas" min-width="120">
                                            <template #default="scope">
                                                <el-tooltip v-if="scope.row.notes" :content="scope.row.notes" placement="top">
                                                    <span class="truncate">{{ scope.row.notes }}</span>
                                                </el-tooltip>
                                                <span v-else class="text-gray-400">N/A</span>
                                            </template>
                                        </el-table-column>
                                        <el-table-column prop="quantity" label="Cantidad" width="100" />
                                        <el-table-column label="Vía de envío" min-width="170">
                                            <template #default="scope">
                                                <div class="flex flex-wrap gap-1 text-[11px]">
                                                    <span v-if="scope.row.plane_stock"
                                                        class="rounded bg-sky-50 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300 px-1.5 py-0.5">
                                                        Avión: {{ formatRequisitionQuantity(scope.row.plane_stock) }}
                                                    </span>
                                                    <span v-if="scope.row.ship_stock"
                                                        class="rounded bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 px-1.5 py-0.5">
                                                        Barco: {{ formatRequisitionQuantity(scope.row.ship_stock) }}
                                                    </span>
                                                    <span v-if="scope.row.additional_stock"
                                                        class="rounded bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 px-1.5 py-0.5">
                                                        Fábrica: {{ formatRequisitionQuantity(scope.row.additional_stock) }}
                                                    </span>
                                                    <span v-if="!hasDistribution(scope.row)"
                                                        class="font-semibold text-red-600 dark:text-red-400">
                                                        <i class="fa-solid fa-triangle-exclamation mr-1"></i> Falta distribuir
                                                    </span>
                                                </div>
                                            </template>
                                        </el-table-column>
                                        <el-table-column prop="unit_price" label="Precio Unitario" width="120">
                                            <template #default="scope">
                                                <span v-if="scope.row.unit_price && scope.row.unit_price > 0">{{ formatCurrency(scope.row.unit_price) }}</span>
                                                <span v-else>{{ form.is_spanish_template ? 'Por definir' : 'To be defined' }}</span>
                                            </template>
                                        </el-table-column>
                                        <el-table-column prop="mold_price" label="Molde" width="100">
                                            <template #default="scope">
                                                <p v-if="scope.row.needs_mold">{{  scope.row.mold_price ? formatCurrency(scope.row.mold_price) : (form.is_spanish_template ? 'Por definir' : 'To be defined') }}</p>
                                            </template>
                                        </el-table-column>
                                        <el-table-column label="Total" width="150">
                                            <template #default="scope">
                                                <span v-if="scope.row.unit_price && scope.row.unit_price > 0">{{ formatCurrency((scope.row.quantity * scope.row.unit_price) + (scope.row.mold_price || 0)) }}</span>
                                                <span v-else>{{ form.is_spanish_template ? 'Por definir' : 'To be defined' }}</span>
                                            </template>
                                        </el-table-column>
                                        <el-table-column label="Acciones" width="100" align="right">
                                             <template #default="scope">
                                                <div class="flex items-center justify-end space-x-3">
                                                    <button @click="editProduct(scope.$index)" type="button" class="text-blue-500 hover:text-blue-700">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </button>
                                                    <el-popconfirm confirm-button-text="Sí" cancel-button-text="No" icon-color="#D32F2F" title="¿Remover producto?" @confirm="removeProduct(scope.$index)">
                                                        <template #reference>
                                                            <button type="button" class="text-red-500 hover:text-red-700">
                                                                <i class="fa-solid fa-trash-can"></i>
                                                            </button>
                                                        </template>
                                                    </el-popconfirm>
                                                </div>
                                            </template>
                                        </el-table-column>
                                    </el-table>
                                    <InputError :message="form.errors.items" class="mt-2" />
                                </div>

                                <!-- Totales -->
                                <div class="mt-6 flex justify-end">
                                    <div class="w-full max-w-sm space-y-2 text-gray-700 dark:text-gray-300">
                                        <div class="flex justify-between">
                                            <span>Subtotal:</span>
                                            <span v-if="totals.hasUndefinedPrice">{{ form.is_spanish_template ? 'Por definir' : 'To be defined' }}</span>
                                            <span v-else>{{ formatCurrency(totals.subtotal) }}</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <label class="text-sm">IVA ({{ tax_percent }}%):</label>
                                            <div class="w-28">
                                                <TextInput v-model.number="tax_percent" type="number" placeholder="16">
                                                    <template #icon-right><i class="fa-solid fa-percentage"></i></template>
                                                </TextInput>
                                            </div>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Impuestos:</span>
                                            <span v-if="totals.hasUndefinedPrice">{{ form.is_spanish_template ? 'Por definir' : 'To be defined' }}</span>
                                            <span v-else>{{ formatCurrency(totals.tax) }}</span>
                                        </div>
                                        <div class="flex justify-between"><span>Moldes:</span> <span>{{ formatCurrency(totals.molds) }}</span></div>
                                        <div class="flex justify-between font-bold text-lg border-t pt-2">
                                            <span>Total:</span>
                                            <span v-if="totals.hasUndefinedPrice">{{ form.is_spanish_template ? 'Por definir' : 'To be defined' }}</span>
                                            <span v-else>{{ formatCurrency(totals.total) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                        
                        <!-- Botón de envío -->
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2 mt-8 col-span-full">
                            <p v-if="missingDistribution.length" class="text-xs font-semibold text-red-600 dark:text-red-400 text-right">
                                <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                                Completa la vía de envío de {{ missingDistribution.length }} producto(s) para poder crear la orden.
                            </p>
                            <SecondaryButton :loading="form.processing" :disabled="!canSubmitPurchase">
                                Crear Órden de Compra
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
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import SupplierFavoredProducts from "@/Components/MyComponents/SupplierFavoredProducts.vue";
import LoadingIsoLogo from "@/Components/MyComponents/LoadingIsoLogo.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import InputError from "@/Components/InputError.vue";
import TextInput from "@/Components/TextInput.vue";
import Back from "@/Components/MyComponents/Back.vue";
import { ElMessage, ElMessageBox } from 'element-plus';
import { useForm } from "@inertiajs/vue3";
import axios from 'axios';

export default {
    data() {
        // Objeto principal del formulario
        const form = useForm({
            requisition_id: null,
            supplier_id: null,
            supplier_contact_id: null,
            supplier_bank_account_id: null,
            expected_delivery_date: null,
            currency: 'MXN',
            notes: '',
            is_spanish_template: true, // true para español, false para inglés
            type: 'Venta', // 'Venta' o 'Muestra'
            items: [],
            subtotal: 0,
            tax: 0,
            total: 0,
            media: [],
        });

        // Objeto para el producto que se está agregando/editando
        const productForm = {
            product_id: null,
            product_name: '',
            quantity: 1,
            unit_price: 0,
            additional_stock: 0,
            plane_stock: 0,
            needs_mold: false,
            mold_price: null,
            ship_stock: 0,
            type: 'Venta',
            notes: '',
            min_quantity: null, // NUEVO
            max_quantity: null, // NUEVO
        };

        return {
            form,
            productForm,
            supplierContacts: [],
            supplierBankAccounts: [],
            availableProducts: [],
            editProductIndex: null, // Índice del producto en edición
            loadingProductMedia: false,
            tax_percent: 16,
        };
    },
    components: {
        Back,
        AppLayout,
        TextInput,
        InputError,
        FileUploader,
        PrimaryButton,
        LoadingIsoLogo,
        SecondaryButton,
        SupplierFavoredProducts,
    },
    props: {
        suppliers: Array,
        products: Array,
        // Requisiciones abiertas que aún no tienen orden de compra.
        requisitions: {
            type: Array,
            default: () => [],
        },
        prefill: Object, // Nuevo prop para datos precargados
        preselectedProduct: Object, // Nuevo prop para detalles del producto precargado
    },
    async mounted() {
        // Requisición de origen (botón «Crear orden de compra» desde su detalle).
        if (this.prefill?.requisition_id) {
            this.form.requisition_id = parseInt(this.prefill.requisition_id);
        }

        // Verificar si hay datos precargados para llenar el formulario
        if (this.prefill?.supplier_id) {
            this.form.supplier_id = parseInt(this.prefill.supplier_id);
            
            // Cargar datos del proveedor (esto limpiará los items, así que lo hacemos antes de agregar el preseleccionado)
            await this.fetchSupplierDetails();

            // Si hay producto preseleccionado, agregarlo
            if (this.prefill.product_id && this.preselectedProduct) {
                // Intentar encontrar el producto en la lista cargada del proveedor para obtener precio específico
                const productInList = this.availableProducts.find(p => p.value == this.prefill.product_id);
                
                let price = 0;
                // Usar nombre y código del prop por defecto
                let name = `${this.preselectedProduct.name} (${this.preselectedProduct.code})`;

                if (productInList) {
                    name = productInList.label; // Usar etiqueta del select si está disponible
                    if (productInList.pivot && productInList.pivot.last_price) {
                        price = parseFloat(productInList.pivot.last_price);
                    } else {
                        price = productInList.cost ?? 0;
                    }
                }

                // Agregar el item al formulario
                this.form.items.push({
                    product_id: parseInt(this.prefill.product_id),
                    product_name: name,
                    quantity: parseFloat(this.prefill.quantity) || 1,
                    unit_price: price,
                    additional_stock: 0,
                    plane_stock: 0,
                    ship_stock: 0,
                    type: 'Venta',
                    needs_mold: false,
                    mold_price: 0,
                    notes: '',
                });
                
                ElMessage.info('Producto precargado desde alerta de stock.');
            }
        }

        // Requisición seleccionada sin proveedor: se precargan sus partidas.
        this.applyRequisitionItems();
    },
    computed: {
        // Requisición vinculada a la orden.
        selectedRequisition() {
            if (!this.form.requisition_id) return null;

            return this.requisitions.find(requisition => requisition.id === this.form.requisition_id) ?? null;
        },
        // Descripciones capturadas para las partidas de producto nuevo.
        newProductDescriptions() {
            return (this.selectedRequisition?.items ?? [])
                .map(item => item.description)
                .filter(Boolean);
        },
        // Calcula los totales de la orden
        totals() {
            const hasUndefinedPrice = this.form.items.some(item => !item.unit_price || item.unit_price <= 0);
            const subtotal = this.form.items.reduce((acc, item) => acc + (item.quantity * (item.unit_price || 0)), 0);
            const tax = subtotal * (this.tax_percent / 100);
            const molds = this.form.items.reduce((acc, item) => acc + (item.mold_price || 0), 0);
            const total = subtotal + tax + molds;

            return { subtotal, tax, molds, total, hasUndefinedPrice };
        },
        // Devuelve true si se está editando un producto
        isEditing() {
            return this.editProductIndex !== null;
        },
        // Calcula la cantidad de stock restante por asignar
        stockRemaining() {
            const totalStock = (this.productForm.plane_stock || 0) +
                               (this.productForm.ship_stock || 0) +
                               (this.productForm.additional_stock || 0);
            return (this.productForm.quantity || 0) - totalStock;
        },
        /*
         * Partidas cuya distribución (avión + barco + fábrica) no coincide con
         * la cantidad. Las precargadas desde la requisición entran aquí hasta
         * que se editan y se captura la vía de envío.
         */
        missingDistribution() {
            return this.form.items.filter(item => !this.hasDistribution(item));
        },
        canSubmitPurchase() {
            return this.form.items.length > 0 && this.missingDistribution.length === 0;
        },
    },
    methods: {
        /**
         * Indica si la partida ya tiene repartida su cantidad entre avión,
         * barco y lo que se queda en fábrica.
         */
        hasDistribution(item) {
            const assigned = (Number(item.plane_stock) || 0)
                + (Number(item.ship_stock) || 0)
                + (Number(item.additional_stock) || 0);

            return Math.abs(assigned - (Number(item.quantity) || 0)) < 0.0001;
        },
        // Enviar el formulario para crear la orden
        store() {
            if (this.missingDistribution.length) {
                ElMessage.error('Define la vía de envío (avión, barco y fábrica) de cada producto antes de crear la orden.');
                return;
            }

            this.form.subtotal = this.totals.subtotal;
            this.form.tax = this.totals.tax;
            this.form.total = this.totals.total;

            this.form.post(route("purchases.store"), {
                onSuccess: () => {
                    ElMessage.success('Órden de compra creada');
                },
                onError: () => {
                    this.$refs.formContainer.scrollIntoView({ behavior: 'smooth' });
                }
            });
        },
        // Obtener detalles del proveedor (contactos, cuentas, productos)
        async fetchSupplierDetails() {
            if (!this.form.supplier_id) return;
            
            this.resetFormOnSupplierChange();
            
            try {
                const response = await axios.get(route('suppliers.get-details', { supplier: this.form.supplier_id }));
                const data = response.data;
                this.supplierContacts = data.contacts;
                this.supplierBankAccounts = data.bankAccounts;
                // NOTA: Laravel envía los datos de la relación (last_price) dentro de un objeto llamado 'pivot'
                this.availableProducts = data.products.map(product => ({
                    value: product.id,
                    label: `${product.name} (${product.code})`,
                    ...product // Al usar spread, la propiedad 'pivot' queda disponible en availableProducts
                }));

                // El cambio de proveedor limpia las partidas: se vuelven a cargar las de la requisición.
                this.applyRequisitionItems();
            } catch (error) {
                console.error("Error al obtener detalles del proveedor:", error);
                ElMessage.error("No se pudieron cargar los datos del proveedor.");
            }
        },
        // Al cambiar la requisición se recargan sus partidas.
        onRequisitionChange() {
            this.form.items = [];
            this.cancelEdit();
            this.applyRequisitionItems();
        },
        /**
         * Precarga en la orden las partidas de la requisición cuando es de
         * resurtido (productos de catálogo). El producto nuevo solo se muestra
         * como referencia porque todavía no existe en el catálogo.
         */
        applyRequisitionItems() {
            const requisition = this.selectedRequisition;

            if (!requisition || requisition.origin !== 'Almacén') return;

            requisition.items.forEach(item => {
                if (!item.product_id) return;

                const productInList = this.availableProducts.find(product => product.value === item.product_id);
                const price = productInList?.pivot?.last_price
                    ? parseFloat(productInList.pivot.last_price)
                    : (productInList?.cost ?? 0);

                const existingIndex = this.form.items.findIndex(purchaseItem => purchaseItem.product_id === item.product_id);

                if (existingIndex !== -1) {
                    this.form.items[existingIndex] = {
                        ...this.form.items[existingIndex],
                        quantity: item.quantity,
                    };
                    return;
                }

                this.form.items.push({
                    product_id: item.product_id,
                    product_name: item.name
                        ? `${item.name}${item.code ? ` (${item.code})` : ''}`
                        : (item.description ?? 'Producto de catálogo'),
                    quantity: item.quantity,
                    unit_price: price,
                    additional_stock: 0,
                    plane_stock: 0,
                    ship_stock: 0,
                    type: 'Venta',
                    needs_mold: false,
                    mold_price: 0,
                    notes: '',
                });
            });
        },
        formatRequisitionQuantity(value) {
            return (Number(value) || 0).toLocaleString('es-MX', { maximumFractionDigits: 2 });
        },
        // Seleccionar un producto del listado
        onProductSelect(productId) {
            const product = this.availableProducts.find(p => p.value === productId);
            if (product) {
                this.productForm.product_name = product.label;
                
                // CORRECCIÓN AQUI:
                // Verificamos si existe el pivot (dato de la relación) y si tiene un last_price
                if (product.pivot && product.pivot.last_price) {
                    this.productForm.unit_price = parseFloat(product.pivot.last_price);
                } else {
                    // Si no tiene precio específico con este proveedor, usamos el costo global
                    this.productForm.unit_price = product.cost ?? 0;
                }
            }
            this.getProductMedia();
        },
        async getProductMedia() {
            if (!this.productForm.product_id) return;
            this.loadingProductMedia = true;
            try {
                const response = await axios.get(route('products.get-media', this.productForm.product_id));

                if ( response.status === 200 ) {
                    this.productForm.media = response.data.product.media;
                    this.productForm.storages = response.data.product.storages;
                    // Ya no sobreescribimos el costo aquí porque onProductSelect ya tiene la lógica correcta
                    // this.productForm.cost = response.data.product.cost; 
                    this.productForm.measure_unit = response.data.product.measure_unit;
                    this.productForm.currency = response.data.product.currency;
                    
                    // ASIGNACIÓN DEL STOCK MÁXIMO Y MÍNIMO
                    this.productForm.min_quantity = response.data.product.min_quantity;
                    this.productForm.max_quantity = response.data.product.max_quantity;

                    // Verificar si el producto requiere aprobación de dirección
                    if (response.data.product.requires_director_approval) {
                        ElMessageBox.alert(
                            'Consultar con Sherman antes de hacer la compra de este producto.',
                            'Advertencia',
                            { confirmButtonText: 'Entendido', type: 'warning' }
                        );
                    }
                }
            } catch (error) {
                console.log(error);
                ElMessage.error('No se pudo cargar la información del producto');
            } finally {
                this.loadingProductMedia = false;
            }
        },
        // Agregar o actualizar un producto en la lista
        addProduct() {
            if (!this.productForm.product_id || !this.productForm.quantity || this.productForm.unit_price === null) {
                ElMessage.warning('Completa todos los campos del producto');
                return;
            }

            // Validación de la distribución de stock
            if (this.form.type === 'Venta' && this.stockRemaining !== 0) {
                ElMessage.error('La suma del stock (Avión, Barco, Favor) debe ser igual a la Cantidad total.');
                return;
            }

            const existingProductIndex = this.form.items.findIndex(item => item.product_id === this.productForm.product_id);
            if (existingProductIndex !== -1 && existingProductIndex !== this.editProductIndex) {
                 ElMessage.warning('Este producto ya ha sido agregado a la órden');
                return;
            }

            if (this.isEditing) {
                // Actualizar producto existente
                this.form.items[this.editProductIndex] = { ...this.productForm };
            } else {
                // Agregar nuevo producto
                this.form.items.push({ ...this.productForm });
            }

            this.resetProductForm();
        },
        // Quitar un producto de la lista
        removeProduct(index) {
            if (this.editProductIndex === index) {
                this.cancelEdit();
            }
            this.form.items.splice(index, 1);
        },
        // Preparar formulario para editar un producto
        editProduct(index) {
            this.editProductIndex = index;
            this.productForm = { ...this.form.items[index] };
            
            // Si falta info detallada (como en items precargados que no tienen storages), buscarla
            if (!this.productForm.storages) {
                this.getProductMedia();
            }

            this.$refs.formProduct.scrollIntoView({ behavior: 'smooth' });
        },
        // Cancelar la edición de un producto
        cancelEdit() {
            this.editProductIndex = null;
            this.resetProductForm();
        },
        // Limpiar el formulario de producto
        resetProductForm() {
            this.productForm = {
                product_id: null,
                product_name: '',
                quantity: 1,
                unit_price: 0,
                additional_stock: 0,
                plane_stock: 0,
                ship_stock: 0,
                type: 'Venta',
                needs_mold: false,
                mold_price: null,
                notes: '',
                min_quantity: null, // RESET MÍNIMO
                max_quantity: null, // RESET MÁXIMO
            };
            this.editProductIndex = null;
        },
        // Limpiar el formulario cuando cambia el proveedor
        resetFormOnSupplierChange() {
            this.form.items = [];
            this.form.supplier_contact_id = null;
            this.form.supplier_bank_account_id = null;
            this.resetProductForm();
        },
        // Deshabilitar fechas anteriores al día de hoy en el calendario
        disabledDate(time) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return time.getTime() < today.getTime();
        },
        // Formatear un número como moneda
        formatCurrency(value) {
            if (value === null || value === undefined) return '$0.000';

            return Number(value).toLocaleString('es-MX', { 
                style: 'currency', 
                currency: this.form.currency,
                minimumFractionDigits: 3,
                maximumFractionDigits: 3
            });
        }
    }
};
</script>