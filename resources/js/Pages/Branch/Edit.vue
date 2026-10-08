<template>
    <AppLayout title="Editar Cliente">
        <!-- Panel Flotante de Notas -->
        <BranchNotes :branch-id="branch.id" />

        <div class="px-4 sm:px-0">
            <div class="flex items-center space-x-2">
                <!-- Botón dinámico que regresa a ventas o al index dependiendo de cómo llegó aquí -->
                <Back :href="backRoute" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Editar cliente: {{ form.name }}
                </h2>
            </div>
        </div>

        <div ref="formContainer" class="py-7">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-xl sm:rounded-lg p-6 md:p-8">
                    
                    <form @submit.prevent="update">
                        <div class="mb-5 p-4 bg-blue-50 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded-md text-sm">
                            <i class="fa-solid fa-circle-info mr-2"></i>
                            <strong>Nota:</strong> El <strong>Nombre Comercial (Alias)</strong>, la <strong>Razón Social</strong>, el <strong>RFC</strong>, el <strong>Método de Pago</strong>, el <strong>Uso de CFDI</strong> y la <strong>CSF</strong> son obligatorios al crear una nueva sucursal matriz; cada contacto requiere <strong>Nombre, Teléfono y Email</strong>. Si se asigna una "Sucursal Matriz" (cliente hijo), los datos fiscales no son obligatorios.
                        </div>

                        <!-- ================= INDICADOR DE PASOS ================= -->
                        <el-steps :active="currentStep" align-center finish-status="success" class="mb-4">
                            <el-step
                                v-for="(step, idx) in steps"
                                :key="idx"
                                :title="step.title"
                                :status="stepHasError(idx) ? 'error' : undefined"
                            />
                        </el-steps>

                        <!-- ================= RESUMEN DE ERRORES ================= -->
                        <div v-if="hasErrors" class="mb-6 p-4 rounded-md border border-red-300 bg-red-50 dark:bg-red-900/20 dark:border-red-700">
                            <p class="font-semibold text-red-700 dark:text-red-300">
                                <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                                Hay errores de validación. Revisa estos pasos:
                            </p>
                            <ul class="mt-2 space-y-1">
                                <li v-for="step in stepsWithErrors" :key="step.index">
                                    <button type="button" @click="goToStep(step.index)" class="text-sm text-red-700 dark:text-red-300 hover:underline font-medium">
                                        <i class="fa-solid fa-circle-exclamation mr-1"></i>
                                        Paso {{ step.index + 1 }}: {{ step.title }} ({{ step.count }} {{ step.count === 1 ? 'error' : 'errores' }})
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <!-- ================= PASO 1: SUCURSAL MATRIZ ================= -->
                        <div v-show="currentStep === 0">
                            <h3 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-blue-800 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/30 px-4 py-2 rounded-md mb-4">
                                <i class="fa-solid fa-sitemap"></i> Sucursal Matriz
                            </h3>
                            <div class="p-4 bg-blue-50 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded-md text-sm mb-4">
                                <i class="fa-solid fa-circle-info mr-2"></i>
                                <strong>¿Para qué seleccionar una Sucursal Matriz?</strong>
                                Si este registro es una sucursal de un cliente existente, selecciónala aquí. Al hacerlo se cargarán automáticamente la
                                <strong>Razón Social</strong>, el <strong>RFC</strong>, el <strong>Grupo</strong> y los <strong>Productos</strong> de la matriz, ya que se comparten.
                                Si es un cliente independiente, deja este campo vacío.
                            </div>
                            <div>
                                <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Sucursal Matriz (Opcional)</label>
                                <el-select v-model="form.parent_branch_id" placeholder="Selecciona una matriz" class="!w-full" filterable clearable :loading="loadingMatrix">
                                    <el-option v-for="item in branches" :key="item.id" :label="item.name" :value="item.id" />
                                </el-select>
                                <InputError :message="form.errors.parent_branch_id" />
                            </div>
                            <p v-if="loadingMatrix" class="text-xs text-blue-700 dark:text-blue-300 mt-2">
                                <i class="fa-solid fa-spinner fa-spin mr-1"></i> Cargando datos de la matriz...
                            </p>
                        </div>

                        <!-- ================= PASO 2: DATOS GENERALES ================= -->
                        <div v-show="currentStep === 1">
                        <h3 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-blue-800 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/30 px-4 py-2 rounded-md mb-4">
                            <i class="fa-solid fa-id-card"></i> Datos Generales
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4">
                            <!-- Nuevos campos incorporados -->
                            <TextInput label="Número de Cliente" v-model="form.client_number" type="text" :error="form.errors.client_number" placeholder="Ej. 001" />
                            <TextInput label="Nombre Comercial (Alias)*" v-model="form.name" type="text" :error="form.errors.name" placeholder="Nombre de empresa/sucursal" />
                            
                            <TextInput label="Grupo" v-model="form.group_name" type="text" :error="form.errors.group_name" placeholder="Nombre del grupo (Opcional)" />

                            <div>
                                <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Estatus*</label>
                                <el-select v-model="form.status" placeholder="Selecciona un estatus" class="!w-full">
                                    <el-option label="Prospecto" value="Prospecto" />
                                    <el-option label="Cliente" value="Cliente" />
                                </el-select>
                                <InputError :message="form.errors.status" />
                            </div>
                            <div>
                                <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Vendedor Asignado</label>
                                <el-select v-model="form.account_manager_id" placeholder="Selecciona un vendedor" class="!w-full" filterable>
                                    <el-option v-for="user in users" :key="user.id" :label="user.name" :value="user.id" />
                                </el-select>
                                <InputError :message="form.errors.account_manager_id" />
                            </div>
                            <div>
                                <InputLabel value="Cómo nos conoció el cliente*" />
                                <el-select v-model="form.meet_way" placeholder="Selecciona">
                                    <el-option v-for="item in meetWays" :key="item" :value="item" :label="item" />
                                </el-select>
                            </div>
                        </div>

                        </div>

                        <!-- ================= PASO 3: DATOS DE FACTURACIÓN ================= -->
                        <div v-show="currentStep === 2">
                        <!-- ================= DATOS PARA FACTURAR ================= -->
                        <div class="mt-8">
                            <h3 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-blue-800 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/30 px-4 py-2 rounded-md mb-4">
                                <i class="fa-solid fa-file-invoice"></i> Datos para Facturar
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4">
                                <TextInput :label="form.parent_branch_id ? 'Razón Social' : 'Razón Social*'" v-model="form.business_name" type="text" :error="form.errors.business_name" :placeholder="parentBranchData?.business_name || 'Razón social fiscal'" />
                                <TextInput :label="form.parent_branch_id ? 'RFC' : 'RFC*'" v-model="form.rfc" type="text" :error="form.errors.rfc" :placeholder="parentBranchData?.rfc || 'Registro Federal de Contribuyentes'" />
                                <TextInput label="Cuenta Bancaria" v-model="form.bank_account" type="text" :error="form.errors.bank_account" placeholder="Ej. 1234 o Cuenta Completa" />
                                <TextInput label="Código Postal" v-model="form.post_code" type="text" :error="form.errors.post_code" />
                                <div class="md:col-span-2">
                                    <TextInput label="Dirección" v-model="form.address" type="text" :error="form.errors.address" placeholder="Calle, número, colonia" />
                                </div>
                            </div>

                            <!-- Documento CSF -->
                            <div class="mt-4 p-4 border border-dashed border-gray-300 dark:border-slate-600 rounded-lg">
                                <label class="flex items-center gap-2 text-gray-700 dark:text-gray-100 text-sm font-semibold mb-1">
                                    <i class="fa-solid fa-paperclip text-blue-700 dark:text-blue-300"></i>
                                    Constancia de Situación Fiscal (CSF) actualizada
                                </label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Formatos permitidos: PDF o imagen (máx. 10 MB). Subir un archivo reemplaza el actual.</p>

                                <div v-if="existingCsf" class="flex items-center gap-2 mb-3 text-sm">
                                    <i class="fa-regular fa-file-pdf text-red-600"></i>
                                    <a :href="existingCsf.original_url" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">Ver CSF actual</a>
                                </div>

                                <FileUploader
                                    @files-selected="form.csf = $event[0] ?? null"
                                    :multiple="false"
                                    format="Documento"
                                    :max-files="1"
                                    :max-file-size="10"
                                />
                                <InputError :message="form.errors.csf" class="mt-2" />
                            </div>
                        </div>

                        <!-- ================= MÉTODO DE PAGO ================= -->
                        <div class="mt-8">
                            <h3 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-blue-800 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/30 px-4 py-2 rounded-md mb-4">
                                <i class="fa-solid fa-credit-card"></i> Método de Pago
                            </h3>
                            <el-radio-group v-model="form.payment_method" @change="handleMetodoPagoChange" class="flex flex-col items-start gap-2 !w-full">
                                <el-radio value="PPD" border class="!w-full !mr-0">
                                    <span class="font-semibold">PPD</span>
                                    <span class="text-xs font-normal text-gray-500 ml-1">(Parcialidades o Diferido)</span>
                                </el-radio>
                                <div v-show="form.payment_method === 'PPD'" class="pl-8">
                                    <el-radio-group v-model="form.payment_submethod" class="flex flex-col items-start gap-1">
                                        <el-radio value="99 X DEFINIR">** 99 X DEFINIR</el-radio>
                                    </el-radio-group>
                                </div>
                                <el-radio value="PUE" border class="!w-full !mr-0">
                                    <span class="font-semibold">PUE</span>
                                    <span class="text-xs font-normal text-gray-500 ml-1">(Una sola exhibición)</span>
                                </el-radio>
                                <div v-show="form.payment_method === 'PUE'" class="pl-8">
                                    <el-radio-group v-model="form.payment_submethod" class="flex flex-col items-start gap-1">
                                        <el-radio value="TRANSFERENCIA">** TRANSFERENCIA</el-radio>
                                        <el-radio value="CHEQUES">** CHEQUES</el-radio>
                                    </el-radio-group>
                                </div>
                            </el-radio-group>
                            <InputError :message="form.errors.payment_method || form.errors.payment_submethod" class="mt-2" />
                        </div>

                        <!-- ================= USO CFDI ================= -->
                        <div class="mt-8">
                            <h3 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-blue-800 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/30 px-4 py-2 rounded-md mb-4">
                                <i class="fa-solid fa-file-shield"></i> Uso de CFDI
                            </h3>
                            <el-radio-group v-model="form.cfdi_use" class="flex flex-col items-start gap-1 !w-full">
                                <el-radio value="GASTOS EN GENERAL" class="!mr-0">** GASTOS EN GENERAL</el-radio>
                                <el-radio value="ADQUISICION DE MERCANCIAS" class="!mr-0">** ADQUISICIÓN DE MERCANCÍAS</el-radio>
                            </el-radio-group>
                            <InputError :message="form.errors.cfdi_use" class="mt-2" />
                        </div>
                        </div>

                        <!-- ================= PASO 4: CONTACTOS ================= -->
                        <div v-show="currentStep === 3">
                        
                        <div class="flex justify-between items-center mt-8 mb-2 border-b dark:border-gray-600 pb-2">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Contactos por Área</h3>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                            Haz clic en un área para desplegar sus contactos. Los contactos de <strong>Comercial</strong> y <strong>Pagos</strong> son obligatorios; el de <strong>Finanzas</strong> es opcional. Cada contacto requiere Nombre, Teléfono y Email, y no se pueden repetir entre contactos.
                        </p>

                        <el-collapse v-model="activeContactGroups" class="contacts-accordion">
                            <el-collapse-item
                                v-for="group in groupedContacts"
                                :key="group.key"
                                :name="group.key"
                            >
                                <template #title>
                                    <div class="flex items-center justify-between w-full pr-4">
                                        <span class="flex items-center gap-2 font-semibold text-gray-800 dark:text-gray-100">
                                            <i class="fa-solid" :class="groupIcon(group.area)"></i> {{ group.area }}
                                            <el-tag v-if="isRequiredArea(group.area)" type="danger" size="small" effect="plain">Obligatorio</el-tag>
                                            <el-tag v-else type="info" size="small" effect="plain">Opcional</el-tag>
                                        </span>
                                        <el-tag v-if="group.items.length" type="success" size="small" effect="light" class="mr-3">
                                            {{ group.items.length }} {{ group.items.length === 1 ? 'contacto' : 'contactos' }}
                                        </el-tag>
                                        <el-tag v-else type="info" size="small" effect="plain" class="mr-3">Sin contactos</el-tag>
                                    </div>
                                </template>

                                <div class="space-y-4">
                                    <div v-for="item in group.items" :key="item.index" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-5 gap-y-4 border dark:border-gray-600 rounded-lg p-4 relative">
                                        <!-- Selector de Prefijo -->
                                        <div>
                                            <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Prefijo</label>
                                            <el-select v-model="item.contact.prefix" placeholder="Prefijo" class="!w-full" clearable>
                                                <el-option v-for="p in prefixes" :key="p" :label="p" :value="p" />
                                            </el-select>
                                            <InputError :message="form.errors[`contacts.${item.index}.prefix`]" />
                                        </div>
                                        <!-- Asignar área (solo para contactos sin área) -->
                                        <div v-if="group.isUndefined">
                                            <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Asignar área</label>
                                            <el-select v-model="item.contact.area" placeholder="Por definir" class="!w-full" clearable>
                                                <el-option v-for="area in contactAreas" :key="area" :label="area" :value="area" />
                                            </el-select>
                                            <InputError :message="form.errors[`contacts.${item.index}.area`]" />
                                        </div>
                            
                                        <TextInput class="lg:col-span-2" label="Nombre del contacto*" v-model="item.contact.name" type="text" :error="form.errors[`contacts.${item.index}.name`]" />
                                        <TextInput label="Cargo" v-model="item.contact.charge" type="text" :error="form.errors[`contacts.${item.index}.charge`]" />
                                        <TextInput label="Teléfono" v-model="item.contact.phone" type="text" :error="form.errors[`contacts.${item.index}.phone`]" helpContent="Teléfono y correo obligatorios" />
                                        <TextInput label="Email" v-model="item.contact.email" type="email" :error="form.errors[`contacts.${item.index}.email`]" />

                                        <div class="md:col-span-2 lg:col-span-3 grid grid-cols-2 gap-x-5">
                                            <div>
                                                <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Mes de Cumpleaños</label>
                                                <el-select v-model="item.contact.birth_month" placeholder="Mes" class="!w-full" clearable>
                                                    <el-option v-for="month in months" :key="month.value" :label="month.label" :value="month.value" />
                                                </el-select>
                                                <InputError :message="form.errors[`contacts.${item.index}.birth_month`]" />
                                            </div>
                                            <div>
                                                <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Día de Cumpleaños</label>
                                                <el-select v-model="item.contact.birth_day" placeholder="Día" class="!w-full" clearable :disabled="!item.contact.birth_month">
                                                    <el-option v-for="day in daysInMonth(item.contact.birth_month)" :key="day" :label="day" :value="day" />
                                                </el-select>
                                                <InputError :message="form.errors[`contacts.${item.index}.birth_day`]" />
                                            </div>
                                        </div>
                            
                                        <button @click="removeContact(item.index)" type="button" class="absolute top-2 right-2 text-gray-400 hover:text-red-500 transition-colors">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>

                                    <div v-if="!group.items.length" class="text-sm text-gray-500 dark:text-gray-400">
                                        No hay contactos registrados en esta área.
                                    </div>

                                    <p v-if="areaError(group.area)" class="text-xs font-semibold text-red-600 dark:text-red-400">
                                        <i class="fa-solid fa-circle-exclamation mr-1"></i>{{ areaError(group.area) }}
                                    </p>

                                    <div class="flex justify-end">
                                        <button @click.stop="addContactToGroup(group.isUndefined ? null : group.area)" type="button" class="text-primary hover:underline text-sm font-semibold">
                                            <i class="fa-solid fa-plus mr-1"></i> Agregar contacto{{ group.isUndefined ? '' : ' en ' + group.area }}
                                        </button>
                                    </div>
                                </div>
                            </el-collapse-item>
                        </el-collapse>
                        <InputError :message="form.errors.contacts" />
                        </div>

                        <!-- ================= PASO 5: PRODUCTOS ================= -->
                        <div v-show="currentStep === 4">

                        <div class="flex justify-between items-center mt-8 mb-4">
                            <h3 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-blue-800 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/30 px-4 py-2 rounded-md w-full">
                                <i class="fa-solid fa-tags"></i> Productos Asignados
                            </h3>
                        </div>
                        
                        <div class="p-4 border border-gray-200 dark:border-slate-700 rounded-lg">
                             <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
                                <div>
                                    <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Buscar producto*</label>
                                    <el-select @change="getProductMedia" v-model="currentProduct.product_id" placeholder="Selecciona un producto" class="!w-full" filterable>
                                        <el-option class="!w-96" v-for="item in availableProducts" 
                                            :key="item.id" 
                                            :label="item.name" 
                                            :value="item.id"
                                            :disabled="isProductInForm(item.id)"
                                        />
                                    </el-select>
                                </div>
                                <TextInput label="Precio Especial (Opcional)" v-model="currentProduct.price"
                                    :helpContent="'Si no agregas precio especial se tomará en cuenta el precio base del producto'" type="number" :step="0.01" placeholder="Dejar vacío para usar precio base" />

                                <div>
                                    <label>Moneda*</label>
                                    <el-select v-model="currentProduct.currency" placeholder="Moneda" :teleported="false" class="!w-full mt-1">
                                        <el-option label="MXN" value="MXN" />
                                        <el-option label="USD" value="USD" />
                                    </el-select>
                                </div>
                            </div>

                            <div v-if="loadingProductMedia" class="flex items-center justify-center h-32">
                                <p class="text-gray-500">Cargando imagen...</p>
                            </div>
                             <div v-else-if="currentProduct.media" class="flex items-center space-x-4 p-2 bg-gray-100 dark:bg-slate-900/50 rounded-md col-span-full mt-4">
                                <figure class="relative flex items-center justify-center size-32 rounded-2xl border border-gray-200 dark:border-slate-900 overflow-hidden shadow-lg">
                                    <img v-if="currentProduct.image_url" :src="currentProduct.image_url" alt="Imagen del producto" class="rounded-2xl w-full h-auto object-cover">
                                    <div v-else class="flex flex-col items-center justify-center text-gray-400 dark:text-slate-500 p-2 text-center">
                                        <i class="fa-solid fa-image text-3xl"></i>
                                        <p class="text-xs mt-1">Sin imagen</p>
                                    </div>
                                </figure>
                                <div>
                                     <p class="font-semibold text-gray-800 dark:text-gray-100">
                                        {{ getProductName(currentProduct.product_id) }}
                                        <span v-if="currentProduct.code" class="text-xs text-gray-500">({{ currentProduct.code }})</span>
                                     </p>
                                     <p class="text-gray-500 dark:text-gray-300">
                                        Precio Base: <strong>${{ currentProduct.base_price?.toFixed(2) ?? '0.00' }}</strong>
                                    </p>
                                    <p class="text-gray-500 dark:text-gray-300">
                                        Stock: <strong>{{ currentProduct.current_stock ?? '0' }}</strong> unidades
                                    </p>
                                    <p class="text-gray-500 dark:text-gray-300">
                                        Ubicación: <strong>{{ currentProduct.location ?? 'No asignado' }}</strong>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="flex justify-end mt-4">
                                <PrimaryButton @click="addProduct" type="button" plain :disabled="!currentProduct.product_id">
                                    <i class="fa-solid fa-plus mr-2"></i> Agregar Producto
                                </PrimaryButton>
                            </div>
                        </div>

                        <div v-if="form.products.length" class="mt-4 space-y-3 max-h-72 overflow-y-auto">
                            <InputError :message="form.errors.products" />
                            <div v-for="(product, index) in form.products" :key="index"
                                class="flex items-center gap-4 p-3 rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900">
                                <el-image
                                    v-if="product.image_url"
                                    :src="product.image_url"
                                    :preview-src-list="[product.image_url]"
                                    :preview-teleported="true"
                                    fit="cover"
                                    class="size-16 rounded-md border border-gray-200 dark:border-slate-700 shrink-0"
                                >
                                    <template #error>
                                        <div class="flex items-center justify-center size-16 text-gray-400">
                                            <i class="fa-solid fa-image text-xl"></i>
                                        </div>
                                    </template>
                                </el-image>
                                <div v-else class="flex items-center justify-center size-16 rounded-md border border-gray-200 dark:border-slate-700 text-gray-400 shrink-0">
                                    <i class="fa-solid fa-image text-xl"></i>
                                </div>

                                <div class="flex-1 min-w-0 text-sm">
                                    <p class="font-semibold text-gray-800 dark:text-gray-100 truncate">
                                        {{ product.name || getProductName(product.product_id) }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Código: {{ product.code ?? 'N/A' }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                        Costo base:
                                        <strong>{{ product.base_price != null ? '$' + Number(product.base_price).toFixed(2) : 'N/A' }}</strong>
                                        <span class="mx-2">|</span>
                                        Precio especial:
                                        <strong>{{ product.price != null ? '$' + Number(product.price).toFixed(2) : 'N/A' }}</strong>
                                        <span class="ml-2">{{ product.currency ?? 'MXN' }}</span>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        <i class="fa-solid fa-boxes-stacked mr-1"></i> Stock: {{ product.current_stock ?? 0 }}
                                        <span class="mx-2">|</span>
                                        <i class="fa-solid fa-location-dot mr-1"></i> {{ product.location || 'Sin ubicación' }}
                                    </p>
                                </div>

                                <div class="flex items-center space-x-2 shrink-0">
                                    <button @click="editProduct(index)" type="button" class="text-gray-500 hover:text-blue-500 transition-colors" title="Editar">
                                        <i class="fa-solid fa-pencil"></i>
                                    </button>
                                    <button @click="removeProduct(index)" type="button" class="text-gray-500 hover:text-red-500 transition-colors" title="Eliminar">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- ================================================== -->
                        <!-- ============== PRODUCTOS SUGERIDOS =============== -->
                        <!-- ================================================== -->
                        <div class="flex justify-between items-center mt-8 mb-4">
                            <h3 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-blue-800 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/30 px-4 py-2 rounded-md w-full">
                                <i class="fa-solid fa-lightbulb"></i> Productos Sugeridos (Opcional)
                            </h3>
                        </div>

                        <div>
                            <el-tooltip
                                content="Selecciona productos que podrían interesarle a este cliente en el futuro, aunque no se les asigne un precio especial ahora."
                                placement="top-start"
                            >
                                <label class="text-gray-700 dark:text-gray-100 text-sm ml-3 flex items-center space-x-2">
                                    <span>Sugerencias de productos</span>
                                    <i class="fa-solid fa-circle-info text-gray-400"></i>
                                </label>
                            </el-tooltip>
                            <el-select
                                v-model="form.suggested_products"
                                placeholder="Buscar y seleccionar productos"
                                class="!w-full mt-1"
                                filterable
                                multiple
                                clearable
                            >
                                <el-option
                                    v-for="item in catalog_products"
                                    :key="item.id"
                                    :label="item.name"
                                    :value="item.id"
                                    :disabled="isProductInForm(item.id)"
                                />
                            </el-select>
                            <InputError :message="form.errors.suggested_products" />
                        </div>

                        </div>

                        <!-- ================= NAVEGACIÓN POR PASOS ================= -->
                        <div class="flex justify-between items-center mt-8 gap-3">
                            <SecondaryButton v-if="currentStep > 0" type="button" @click="prevStep">
                                <i class="fa-solid fa-arrow-left mr-2"></i> Anterior
                            </SecondaryButton>
                            <span v-else></span>

                            <SecondaryButton v-if="currentStep < steps.length - 1" type="button" @click="nextStep">
                                Siguiente <i class="fa-solid fa-arrow-right ml-2"></i>
                            </SecondaryButton>
                            <SecondaryButton v-else :loading="form.processing">
                                Guardar Cambios
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
import BranchNotes from "@/Components/MyComponents/BranchNotes.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import TextInput from "@/Components/TextInput.vue";
import Back from "@/Components/MyComponents/Back.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import { ElMessage } from 'element-plus';
import { useForm } from "@inertiajs/vue3";
import axios from 'axios';

export default {
    data() {
        return {
            prefixes: ['Ing.', 'Lic.', 'Dr.', 'Arq.', 'Mtro.', 'Sr.', 'Sra.', 'Srita.'], // Arreglo de prefijos (igual que en cotizaciones)
            form: useForm({
                name: this.branch.name,
                rfc: this.branch.rfc || this.branches.find(b => b.id === this.branch.parent_branch_id)?.rfc || null,
                // --- NUEVAS PROPIEDADES PRE-CARGADAS ---
                group_name: this.branch.group_name,
                business_name: this.branch.business_name || this.branches.find(b => b.id === this.branch.parent_branch_id)?.business_name || null,
                bank_account: this.branch.bank_account,
                client_number: this.branch.client_number,

                address: this.branch.address,
                post_code: this.branch.post_code,
                status: this.branch.status,
                parent_branch_id: this.branch.parent_branch_id,
                account_manager_id: this.branch.account_manager_id,
                meet_way: this.branch.meet_way,
                // Datos fiscales (CFDI)
                payment_method: this.branch.payment_method,
                payment_submethod: this.branch.payment_submethod,
                cfdi_use: this.branch.cfdi_use,
                csf: null,
                contacts: this.formattedContacts,
                products: this.formattedProducts,
                suggested_products: this.suggestedProductIds ?? [],
                // Necesario para que Laravel reciba el PUT cuando se envian archivos (multipart)
                _method: 'put',
            }),
            currentProduct: {
                product_id: null,
                price: null,
                media: null,
                code: null,
                image_url: null,
                base_price: null,
                currency: 'MXN',
                current_stock: null,
                location: null
            },
            loadingProductMedia: false,
            loadingMatrix: false,
            contactAreas: ['Comercial', 'Finanzas', 'Pagos'],
            activeContactGroups: [],
            currentStep: 0,
            steps: [
                { title: 'Sucursal Matriz' },
                { title: 'Datos Generales' },
                { title: 'Facturación' },
                { title: 'Contactos' },
                { title: 'Productos' },
            ],
            stepErrorKeys: {
                0: ['parent_branch_id'],
                1: ['name', 'client_number', 'group_name', 'status', 'account_manager_id', 'meet_way'],
                2: ['business_name', 'rfc', 'bank_account', 'post_code', 'address', 'payment_method', 'payment_submethod', 'cfdi_use', 'csf'],
                3: ['contacts'],
                4: ['products', 'suggested_products'],
            },
            months: [
                { label: 'Enero', value: 1 }, { label: 'Febrero', value: 2 },
                { label: 'Marzo', value: 3 }, { label: 'Abril', value: 4 },
                { label: 'Mayo', value: 5 }, { label: 'Junio', value: 6 },
                { label: 'Julio', value: 7 }, { label: 'Agosto', value: 8 },
                { label: 'Septiembre', value: 9 }, { label: 'Octubre', value: 10 },
                { label: 'Noviembre', value: 11 }, { label: 'Diciembre', value: 12 },
            ],
            meetWays: [
                'Recomendación',
                'Búsqueda en línea',
                'Publicidad ',
                'Evento o feria comercial',
                'Correo electrónico',
                'Llamada telefónica ',
                'Sitio web de la empresa',
                'Tocamos puerta',
                'Otro',
            ],
        };
    },
    components: {
        Back,
        AppLayout,
        TextInput,
        InputError,
        InputLabel,
        BranchNotes,
        PrimaryButton,
        SecondaryButton,
        FileUploader,
    },
    props: {
        branch: Object,
        formattedContacts: Array,
        formattedProducts: Array,
        users: Array,
        branches: Array,
        catalog_products: Array,
        suggestedProductIds: Array,
    },
    mounted() {
        // Si venimos de crear una orden de venta, marcamos los datos faltantes del cliente
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('redirect_to') === 'sales.create') {
            this.highlightMissingClientData();
        }
    },
    computed: {
        availableProducts() {
            const assignedProductIds = this.formattedProducts.map(p => p.product_id);
            return this.catalog_products.filter(p => !assignedProductIds.includes(p.id));
        },
        // NUEVO: Computado para saber a dónde debe llevar el botón "Atrás"
        backRoute() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('redirect_to') === 'sales.create') {
                return route('sales.create');
            }
            return route('branches.index');
        },
        parentBranchData() {
            if (!this.form.parent_branch_id) return null;
            return this.branches.find(b => b.id === this.form.parent_branch_id);
        },
        existingCsf() {
            return (this.branch.media || []).find(m => m.collection_name === 'csf') || null;
        },
        groupedContacts() {
            const groups = this.contactAreas.map(area => ({
                key: area,
                area: area,
                isUndefined: false,
                items: [],
            }));

            this.form.contacts.forEach((contact, index) => {
                const area = contact.area || null;
                let group = area ? groups.find(g => g.area === area) : groups.find(g => g.isUndefined);

                if (!group && !area) {
                    group = { key: 'Por definir', area: 'Por definir', isUndefined: true, items: [] };
                    groups.push(group);
                }

                if (!group) {
                    group = { key: area, area: area, isUndefined: false, items: [] };
                    groups.push(group);
                }

                group.items.push({ contact, index });
            });

            return groups;
        },
        hasErrors() {
            return this.stepsWithErrors.length > 0;
        },
        stepsWithErrors() {
            return this.steps
                .map((step, index) => ({ index, title: step.title, count: this.stepErrorCount(index) }))
                .filter(step => step.count > 0);
        }
    },
    watch: {
        'form.parent_branch_id'(branchId, oldBranchId) {
            if (branchId && branchId !== oldBranchId) {
                this.loadMatrixData(branchId);
            }
        },
        'form.errors': {
            deep: true,
            handler(errors) {
                // Despliega automáticamente las áreas que tengan errores de contacto
                Object.keys(errors || {}).forEach(key => {
                    // Errores de área obligatoria (Comercial / Pagos)
                    if (key === 'contacts.comercial') this.openContactGroup('Comercial');
                    if (key === 'contacts.pagos') this.openContactGroup('Pagos');

                    const match = key.match(/^contacts\.(\d+)\./);
                    if (match) {
                        const contact = this.form.contacts[parseInt(match[1], 10)];
                        if (contact) {
                            this.openContactGroup(contact.area || 'Por definir');
                        }
                    }
                });
            }
        }
    },
    methods: {
        update() {
            // Se descartan los contactos completamente vacíos antes de enviar
            this.pruneEmptyContacts();

            // Buscamos si existe el parámetro redirect_to en la URL
            const urlParams = new URLSearchParams(window.location.search);
            const redirectTo = urlParams.get('redirect_to');
            const redirectQuoteId = urlParams.get('redirect_quote_id');

            // Preparamos los parámetros para el router de Laravel
            const routeParams = { branch: this.branch.id };
            
            // Si existe la variable en la url, la adjuntamos a la petición
            if (redirectTo) {
                routeParams.redirect_to = redirectTo; 
            }

            // Preservamos la cotización seleccionada para regresar a la OV con ella
            if (redirectQuoteId) {
                routeParams.redirect_quote_id = redirectQuoteId;
            }

            // Enviamos POST con spoofing de PUT: PHP no parsea el cuerpo multipart en un PUT real,
            // por lo que al subir la CSF el request llegaba vacío y fallaban todas las validaciones.
            this.form.post(route("branches.update", routeParams), {
                onSuccess: () => {
                    ElMessage.success('Cliente actualizado correctamente');
                },
                onError: () => {
                    // Nos posicionamos en el primer paso con errores
                    const firstErrorStep = this.steps.findIndex((step, index) => this.stepHasError(index));
                    if (firstErrorStep >= 0) {
                        this.currentStep = firstErrorStep;
                    }
                    this.$nextTick(() => {
                        this.$refs.formContainer.scrollIntoView({ behavior: 'smooth' });
                    });
                    ElMessage.error('Por favor, revisa los errores marcados en los pasos.');
                }
            });
        },
        handleMetodoPagoChange(value) {
            // Ajusta el sub-método según el método seleccionado
            if (value === 'PPD') {
                this.form.payment_submethod = '99 X DEFINIR';
            } else if (this.form.payment_submethod === '99 X DEFINIR') {
                this.form.payment_submethod = null;
            }
        },
        async highlightMissingClientData() {
            try {
                const { data } = await axios.get(route('branches.check-validity', this.branch.id));
                if (data.valid) return;

                const missing = (data.message || '').split(',').map(item => item.trim());

                if (missing.includes('RFC')) {
                    this.form.setError('rfc', 'El RFC es obligatorio para completar la información del cliente.');
                }
                if (missing.includes('Razón Social')) {
                    this.form.setError('business_name', 'La Razón Social es obligatoria para completar la información del cliente.');
                }

                // Nos posicionamos en el primer paso con errores
                const firstErrorStep = this.steps.findIndex((step, index) => this.stepHasError(index));
                if (firstErrorStep >= 0) {
                    this.currentStep = firstErrorStep;
                }

                ElMessage.warning(`El cliente está incompleto (falta: ${data.message}). Corrige los campos marcados y guarda para continuar con la orden de venta.`);
            } catch (error) {
                console.error('No se pudo validar la información del cliente:', error);
            }
        },
        pruneEmptyContacts() {
            // Conserva solo los contactos que tengan al menos un dato capturado
            this.form.contacts = this.form.contacts.filter(contact =>
                (contact.name && String(contact.name).trim() !== '') ||
                (contact.phone && String(contact.phone).trim() !== '') ||
                (contact.email && String(contact.email).trim() !== '')
            );
        },
        groupIcon(area) {
            const icons = {
                Comercial: 'fa-briefcase',
                Finanzas: 'fa-coins',
                Pagos: 'fa-money-check-dollar',
                'Por definir': 'fa-circle-question',
            };
            return icons[area] || 'fa-user';
        },
        addContactToGroup(area) {
            this.form.contacts.push({
                prefix: 'Ing.', // Valor por defecto
                area: area || null,
                name: null,
                charge: null,
                phone: null,
                email: null,
                birth_month: null,
                birth_day: null,
            });
            // Asegura que el grupo quede desplegado
            const key = area || 'Por definir';
            if (!this.activeContactGroups.includes(key)) {
                this.activeContactGroups.push(key);
            }
        },
        isRequiredArea(area) {
            return area === 'Comercial' || area === 'Pagos';
        },
        areaError(area) {
            const keys = { Comercial: 'contacts.comercial', Pagos: 'contacts.pagos' };
            return keys[area] ? this.form.errors[keys[area]] : null;
        },
        openContactGroup(key) {
            if (key && !this.activeContactGroups.includes(key)) {
                this.activeContactGroups.push(key);
            }
        },
        stepErrorCount(stepIndex) {
            const keys = this.stepErrorKeys[stepIndex] || [];
            const errors = this.form.errors || {};
            return Object.keys(errors).filter(errorKey =>
                keys.some(key => errorKey === key || errorKey.startsWith(key + '.'))
            ).length;
        },
        stepHasError(stepIndex) {
            return this.stepErrorCount(stepIndex) > 0;
        },
        goToStep(stepIndex) {
            this.currentStep = stepIndex;
            this.$refs.formContainer?.scrollIntoView({ behavior: 'smooth' });
        },
        nextStep() {
            if (!this.validateStep(this.currentStep)) return;
            if (this.currentStep < this.steps.length - 1) {
                this.currentStep++;
            }
        },
        prevStep() {
            if (this.currentStep > 0) {
                this.currentStep--;
            }
        },
        validateStep(stepIndex) {
            // Validación ligera para guiar al usuario entre pasos
            if (stepIndex === 1) {
                if (!this.form.name) {
                    ElMessage.warning('El Nombre Comercial (Alias) es obligatorio.');
                    return false;
                }
                if (!this.form.status) {
                    ElMessage.warning('Selecciona un estatus.');
                    return false;
                }
            }

            if (stepIndex === 2 && !this.form.parent_branch_id) {
                // Datos obligatorios para sucursales matriz
                const required = [
                    ['business_name', 'La Razón Social es obligatoria.'],
                    ['rfc', 'El RFC es obligatorio.'],
                    ['payment_method', 'Selecciona un Método de Pago.'],
                    ['payment_submethod', 'Selecciona el sub-método de pago.'],
                    ['cfdi_use', 'Selecciona el Uso de CFDI.'],
                    ['csf', 'Sube la CSF actualizada.'],
                ];
                for (const [field, message] of required) {
                    if (!this.form[field]) {
                        ElMessage.warning(message);
                        return false;
                    }
                }
            }

            if (stepIndex === 3) {
                const captured = this.form.contacts.filter(contact =>
                    (contact.name && String(contact.name).trim() !== '') ||
                    (contact.phone && String(contact.phone).trim() !== '') ||
                    (contact.email && String(contact.email).trim() !== '')
                );
                for (const contact of captured) {
                    if (!contact.name) {
                        ElMessage.warning('Cada contacto debe tener Nombre.');
                        return false;
                    }
                    if (!contact.phone) {
                        ElMessage.warning(`Falta el Teléfono de "${contact.name}".`);
                        return false;
                    }
                    if (!contact.email) {
                        ElMessage.warning(`Falta el Email de "${contact.name}".`);
                        return false;
                    }
                }

                // El contacto Comercial y el de Pagos son obligatorios
                const areas = captured.map(contact => contact.area || null);
                if (!areas.includes('Comercial')) {
                    this.openContactGroup('Comercial');
                    ElMessage.warning('Debes agregar al menos un contacto del área Comercial.');
                    return false;
                }
                if (!areas.includes('Pagos')) {
                    this.openContactGroup('Pagos');
                    ElMessage.warning('Debes agregar al menos un contacto del área Pagos.');
                    return false;
                }
            }

            return true;
        },
        async loadMatrixData(branchId) {
            this.loadingMatrix = true;
            try {
                const { data } = await axios.get(route('branches.matrix-data', branchId));

                if (data.branch) {
                    if (data.branch.business_name) this.form.business_name = data.branch.business_name;
                    if (data.branch.rfc) this.form.rfc = data.branch.rfc;
                    if (data.branch.group_name) this.form.group_name = data.branch.group_name;
                }

                if (Array.isArray(data.products)) {
                    this.form.products = data.products.map(product => ({ ...product }));
                }

                ElMessage.success('Se cargaron la Razón Social, RFC, Grupo y Productos de la matriz.');
            } catch (error) {
                console.error('Error al cargar los datos de la matriz:', error);
                ElMessage.error('No se pudieron cargar los datos de la matriz.');
            } finally {
                this.loadingMatrix = false;
            }
        },
        removeContact(index) {
            this.form.contacts.splice(index, 1);
        },
        daysInMonth(month) {
            if (!month) return 31;
            return new Date(2000, month, 0).getDate();
        },
        async getProductMedia() {
            if (!this.currentProduct.product_id) return;
            
            this.loadingProductMedia = true;
            try {
                const response = await axios.get(route('products.get-media', this.currentProduct.product_id));

                if (response.status === 200) {
                    const product = response.data.product;
                    const storages = product.storages ?? [];
                    this.currentProduct.media = product.media;
                    this.currentProduct.base_price = product.base_price;
                    this.currentProduct.code = product.code;
                    this.currentProduct.image_url = product.media?.[0]?.original_url ?? null;
                    this.currentProduct.current_stock = storages.reduce((sum, s) => sum + (parseFloat(s.quantity) || 0), 0);
                    this.currentProduct.location = storages[0]?.location ?? null;
                }
            } catch (error) {
                console.error("Error al cargar detalles del producto:", error);
                ElMessage.error('No se pudo cargar la información del producto.');
                this.resetCurrentProduct();
            } finally {
                this.loadingProductMedia = false;
            }
        },
        addProduct() {
            if (!this.currentProduct.product_id) {
                ElMessage.warning('Debes seleccionar un producto.');
                return;
            }
            
            if (this.isProductInForm(this.currentProduct.product_id)) {
                ElMessage.warning('Este producto ya ha sido agregado a la lista.');
                return;
            }

            this.form.products.push({ ...this.currentProduct });
            this.resetCurrentProduct();
        },
        removeProduct(index) {
            this.form.products.splice(index, 1);
        },
        editProduct(index) {
            const productToEdit = this.form.products[index];
            this.currentProduct = { ...productToEdit };
            this.getProductMedia();
            this.removeProduct(index);
        },
        resetCurrentProduct() {
            this.currentProduct = {
                product_id: null,
                price: null,
                media: null,
                code: null,
                image_url: null,
                currency: 'MXN',
                base_price: null,
                current_stock: null,
                location: null
            };
        },
        getProductName(productId) {
            const product = this.catalog_products.find(p => p.id === productId);
            return product ? product.name : `ID: ${productId}`;
        },
        isProductInForm(productId) {
            return this.form.products.some(p => p.product_id === productId);
        }
    }
};
</script>