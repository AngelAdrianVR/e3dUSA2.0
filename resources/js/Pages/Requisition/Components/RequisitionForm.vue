<template>
    <div class="space-y-6">
        <!-- Tipo de requisición -->
        <div v-if="showTypeSelector">
            <InputLabel value="Tipo de compra" />
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
                <button type="button" @click="form.type = 'Local'"
                    :class="form.type === 'Local'
                        ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20 ring-2 ring-emerald-500/40'
                        : 'border-gray-200 dark:border-slate-700 hover:border-emerald-400'"
                    class="rounded-xl border p-4 text-left transition-all">
                    <p class="font-bold text-gray-800 dark:text-gray-100">
                        <i class="fa-solid fa-store mr-2 text-emerald-500"></i> Local
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Proveedor nacional: cotización, factura y pago directo.
                    </p>
                </button>
                <button type="button" @click="form.type = 'Importación'"
                    :class="form.type === 'Importación'
                        ? 'border-sky-500 bg-sky-50 dark:bg-sky-900/20 ring-2 ring-sky-500/40'
                        : 'border-gray-200 dark:border-slate-700 hover:border-sky-400'"
                    class="rounded-xl border p-4 text-left transition-all">
                    <p class="font-bold text-gray-800 dark:text-gray-100">
                        <i class="fa-solid fa-ship mr-2 text-sky-500"></i> Importación
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Fábrica / China: negociación, pedido y seguimiento de paquetería.
                    </p>
                </button>
            </div>
        </div>
        <div v-else class="flex items-center space-x-3">
            <InputLabel value="Tipo de compra" />
            <el-tag :type="form.type === 'Importación' ? 'primary' : 'success'">{{ form.type }}</el-tag>
        </div>

        <!-- Vía de entrada -->
        <div v-if="showOriginSelector">
            <InputLabel value="Vía de entrada" />
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
                <button type="button" @click="form.origin = 'Vendedor'"
                    :class="form.origin === 'Vendedor'
                        ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 ring-2 ring-blue-500/40'
                        : 'border-gray-200 dark:border-slate-700 hover:border-blue-400'"
                    class="rounded-xl border p-4 text-left transition-all">
                    <p class="font-bold text-gray-800 dark:text-gray-100">
                        <i class="fa-solid fa-user-tie mr-2 text-blue-500"></i> Vendedor
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Requisición de venta a un cliente con planos y formatos de autorización.
                    </p>
                </button>
                <button type="button" @click="form.origin = 'Almacén'"
                    :class="form.origin === 'Almacén'
                        ? 'border-amber-500 bg-amber-50 dark:bg-amber-900/20 ring-2 ring-amber-500/40'
                        : 'border-gray-200 dark:border-slate-700 hover:border-amber-400'"
                    class="rounded-xl border p-4 text-left transition-all">
                    <p class="font-bold text-gray-800 dark:text-gray-100">
                        <i class="fa-solid fa-boxes-stacked mr-2 text-amber-500"></i> Almacén / Producción
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Reposición de stock por alerta de bajo inventario.
                    </p>
                </button>
            </div>
        </div>
        <div v-else class="flex items-center space-x-3">
            <InputLabel value="Vía de entrada" />
            <el-tag :type="form.origin === 'Vendedor' ? 'primary' : 'warning'">{{ form.origin }}</el-tag>
        </div>

        <!-- Datos de la vía vendedor -->
        <div v-if="form.origin === 'Vendedor'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <InputLabel value="Cliente *" />
                <el-select v-model="form.branch_id" filterable class="w-full mt-1" placeholder="Selecciona el cliente">
                    <el-option v-for="branch in branches" :key="branch.id" :value="branch.id"
                        :label="branch.name + (branch.business_name ? ' · ' + branch.business_name : '')" />
                </el-select>
                <InputError :message="collectError('branch_id')" class="mt-1" />
            </div>
            <div>
                <InputLabel value="Razón social" />
                <div class="w-full mt-1 rounded-md border border-gray-300 dark:border-slate-700 bg-gray-100 dark:bg-slate-800 px-3 py-2 text-sm text-gray-700 dark:text-gray-200 min-h-[38px]">
                    {{ razonSocial || 'Sin razón social registrada' }}
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Se toma automáticamente del cliente seleccionado.
                </p>
            </div>
            <div>
                <InputLabel value="Cantidad de piezas *" />
                <TextInput v-model="form.quantity" type="number" :min="0.01" :step="0.01" class="w-full mt-1" />
                <InputError :message="collectError('quantity')" class="mt-1" />
            </div>
            <div>
                <InputLabel value="Especificación de venta" />
                <TextInput v-model="form.sale_specification" class="w-full mt-1"
                    placeholder="Ej. Zinc, baño dorado, 4 tonos de color..." />
                <InputError :message="collectError('sale_specification')" class="mt-1" />
            </div>
        </div>

        <!-- Datos de la vía almacén / producción -->
        <div v-else class="space-y-4">
            <div class="flex justify-between items-center">
                <div>
                    <InputLabel value="Productos solicitados *" />
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Agrega los productos que se necesitan; cada uno podrá asignarse a su propia guía de paquetería.
                    </p>
                </div>
                <SecondaryButton type="button" @click="addItem">
                    <i class="fa-solid fa-plus mr-2"></i> Agregar producto
                </SecondaryButton>
            </div>

            <InputError :message="collectError('items')" />

            <div v-for="(item, index) in form.items" :key="item.key"
                class="border border-gray-200 dark:border-slate-700 rounded-xl p-4 space-y-3 bg-gray-50/50 dark:bg-slate-800/30">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                    <div class="md:col-span-7">
                        <InputLabel :value="`Producto ${index + 1} *`" />
                        <el-select v-model="item.product_id" filterable class="w-full mt-1"
                            placeholder="Busca por nombre o código">
                            <el-option v-for="product in products" :key="product.id" :value="product.id"
                                :label="product.name + ' (' + product.code + ')'" />
                        </el-select>
                        <InputError :message="collectError(`items.${index}.product_id`)" class="mt-1" />
                    </div>
                    <div class="md:col-span-3">
                        <InputLabel value="Cantidad *" />
                        <TextInput v-model="item.quantity" type="number" :min="0.01" :step="0.01" class="w-full mt-1" />
                        <InputError :message="collectError(`items.${index}.quantity`)" class="mt-1" />
                    </div>
                    <div class="md:col-span-2 flex md:justify-end">
                        <el-tooltip :content="itemIsShipped(item)
                            ? 'Este producto ya viaja en una guía; no puede eliminarse desde la edición'
                            : 'Quitar producto'" placement="top">
                            <span>
                                <SecondaryButton type="button" :disabled="itemIsShipped(item) || form.items.length === 1"
                                    @click="removeItem(index)">
                                    <i class="fa-solid fa-trash-can"></i>
                                </SecondaryButton>
                            </span>
                        </el-tooltip>
                    </div>
                </div>

                <!-- Tarjeta del producto seleccionado -->
                <div v-if="productFor(item)" class="flex flex-col sm:flex-row gap-4 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg p-3">
                    <el-image :src="productFor(item).image_url" fit="cover"
                        :preview-src-list="[productFor(item).image_url]" :initial-index="0"
                        preview-teleported hide-on-click-modal
                        class="w-full sm:w-28 h-28 rounded-lg border border-gray-200 dark:border-slate-700 cursor-pointer"
                        :alt="productFor(item).name">
                        <template #error>
                            <div class="flex items-center justify-center w-full h-full text-gray-300">
                                <i class="fa-regular fa-image text-3xl"></i>
                            </div>
                        </template>
                    </el-image>

                    <div class="flex-1 grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
                        <div class="col-span-2 sm:col-span-3">
                            <p class="font-semibold text-gray-800 dark:text-gray-100">{{ productFor(item).name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Código: {{ productFor(item).code }} · Clic en la imagen para verla en grande
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Stock actual</p>
                            <p class="font-bold" :class="isLowStock(productFor(item))
                                ? 'text-red-600 dark:text-red-400'
                                : 'text-emerald-600 dark:text-emerald-400'">
                                {{ formatStock(productFor(item).current_stock) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Stock mínimo</p>
                            <p class="font-bold text-gray-700 dark:text-gray-200">{{ formatStock(productFor(item).min_quantity) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Faltante</p>
                            <p class="font-bold text-amber-600 dark:text-amber-400">
                                {{ formatStock(Math.max(productFor(item).faltante, 0)) }}
                            </p>
                        </div>
                        <div v-if="isLowStock(productFor(item))" class="col-span-2 sm:col-span-3">
                            <p class="text-xs text-red-600 dark:text-red-400">
                                <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                                Stock por debajo del mínimo: se sugiere reponer al menos
                                {{ formatStock(Math.max(productFor(item).min_quantity - productFor(item).current_stock, 0)) }} piezas.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <p v-if="form.items.length > 0" class="text-xs text-gray-500 dark:text-gray-400">
                Total solicitado: <strong>{{ formatStock(totalItemsQuantity) }}</strong> piezas en {{ form.items.length }} partida(s).
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <InputLabel value="Prioridad *" />
                    <el-select v-model="form.priority" class="w-full mt-1" placeholder="Selecciona la prioridad">
                        <el-option v-for="priority in priorities" :key="priority" :label="priority" :value="priority" />
                    </el-select>
                    <InputError :message="collectError('priority')" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Motivo *" />
                    <TextInput v-model="form.reason" class="w-full mt-1"
                        placeholder="Ej. Alerta de stock bajo, faltante para producción..." />
                    <InputError :message="collectError('reason')" class="mt-1" />
                </div>
            </div>
        </div>


        <!-- Frecuencia de venta / consumo -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <InputLabel value="Frecuencia de venta / consumo" />
                <el-select v-model="form.sale_frequency" clearable class="w-full mt-1"
                    placeholder="Selecciona la frecuencia">
                    <el-option v-for="frequency in salesFrequencies" :key="frequency" :label="frequency"
                        :value="frequency" />
                </el-select>
                <InputError :message="collectError('sale_frequency')" class="mt-1" />
            </div>
        </div>

        <!-- Notas -->
        <div>
            <InputLabel value="Notas adicionales" />
            <textarea v-model="form.notes" rows="2"
                class="w-full mt-1 rounded-md border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-gray-200 focus:border-primary focus:ring-primary"
                placeholder="Información extra para compras..."></textarea>
            <InputError :message="collectError('notes')" class="mt-1" />
        </div>

        <!-- Documentos de la vía vendedor -->
        <div v-if="form.origin === 'Vendedor'" class="space-y-4 border-t border-gray-200 dark:border-slate-700 pt-4">
            <h3 class="font-semibold text-gray-800 dark:text-gray-200">
                <i class="fa-solid fa-paperclip mr-2"></i> Documentos requeridos
            </h3>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div>
                    <InputLabel value="Formato de autorización *" />
                    <div v-if="existing.authorization_formats?.length" class="flex flex-wrap gap-2 my-2">
                        <FileView v-for="file in existing.authorization_formats" :key="file.id" :file="file"
                            :deletable="Boolean(requisition)"
                            @delete-file="$emit('delete-existing-media', 'authorization_formats', $event)" />
                    </div>
                    <FileUploader format="Todo" :maxFiles="3" :maxFileSize="10"
                        @files-selected="files.authorization_formats = $event" />
                    <InputError :message="collectError('authorization_formats')" class="mt-1" />
                </div>

                <div>
                    <InputLabel :value="form.type === 'Importación' ? 'Plano / render *' : 'Plano para proveedor *'" />
                    <div v-if="existing.blueprints?.length" class="flex flex-wrap gap-2 my-2">
                        <FileView v-for="file in existing.blueprints" :key="file.id" :file="file"
                            :deletable="Boolean(requisition)"
                            @delete-file="$emit('delete-existing-media', 'blueprints', $event)" />
                    </div>
                    <FileUploader format="Todo" :maxFiles="3" :maxFileSize="10"
                        @files-selected="files.blueprints = $event" />
                    <InputError :message="collectError('blueprints')" class="mt-1" />
                </div>

                <div v-if="form.type === 'Importación'">
                    <InputLabel value="Carta de uso de marca *" />
                    <div v-if="existing.brand_use_letters?.length" class="flex flex-wrap gap-2 my-2">
                        <FileView v-for="file in existing.brand_use_letters" :key="file.id" :file="file"
                            :deletable="Boolean(requisition)"
                            @delete-file="$emit('delete-existing-media', 'brand_use_letters', $event)" />
                    </div>
                    <FileUploader format="Todo" :maxFiles="3" :maxFileSize="10"
                        @files-selected="files.brand_use_letters = $event" />
                    <InputError :message="collectError('brand_use_letters')" class="mt-1" />
                    <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                        <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                        Obligatoria en requisiciones de importación levantadas por vendedores.
                    </p>
                </div>
            </div>
        </div>

        <!-- Acciones -->
        <div class="flex justify-end space-x-3 border-t border-gray-200 dark:border-slate-700 pt-4">
            <Link :href="route('requisitions.index')">
                <CancelButton>Cancelar</CancelButton>
            </Link>
            <PrimaryButton @click="submit" :disabled="processing">
                <i class="fa-solid fa-floppy-disk mr-2"></i>
                {{ submitLabel }}
            </PrimaryButton>
        </div>
    </div>
</template>

<script>
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import TextInput from "@/Components/TextInput.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import FileView from "@/Components/MyComponents/FileView.vue";
import { Link } from "@inertiajs/vue3";

let itemKey = 0;

export default {
    components: {
        InputLabel,
        InputError,
        TextInput,
        PrimaryButton,
        SecondaryButton,
        CancelButton,
        FileUploader,
        FileView,
        Link,
    },
    props: {
        initialType: {
            type: String,
            default: 'Local',
        },
        initialOrigin: {
            type: String,
            default: 'Vendedor',
        },
        branches: {
            type: Array,
            default: () => [],
        },
        products: {
            type: Array,
            default: () => [],
        },
        priorities: {
            type: Array,
            default: () => [],
        },
        salesFrequencies: {
            type: Array,
            default: () => [],
        },
        requisition: {
            type: Object,
            default: null,
        },
        showTypeSelector: {
            type: Boolean,
            default: true,
        },
        showOriginSelector: {
            type: Boolean,
            default: true,
        },
        submitLabel: {
            type: String,
            default: 'Guardar requisición',
        },
        processing: {
            type: Boolean,
            default: false,
        },
        serverErrors: {
            type: Object,
            default: () => ({}),
        },
    },
    emits: ['submit', 'delete-existing-media'],
    data() {
        return {
            form: {
                type: this.requisition?.type ?? this.initialType,
                origin: this.requisition?.origin ?? this.initialOrigin,
                branch_id: this.requisition?.branch_id ?? null,
                quantity: this.requisition?.quantity ?? '',
                sale_specification: this.requisition?.sale_specification ?? '',
                reason: this.requisition?.reason ?? '',
                priority: this.requisition?.priority ?? '',
                sale_frequency: this.requisition?.sale_frequency ?? '',
                notes: this.requisition?.notes ?? '',
                items: this.initialItems(),
            },
            files: {
                authorization_formats: [],
                blueprints: [],
                brand_use_letters: [],
            },
        };
    },
    computed: {
        selectedBranch() {
            return this.branches.find(branch => branch.id === this.form.branch_id) ?? null;
        },
        razonSocial() {
            if (!this.selectedBranch) return '';
            return this.selectedBranch.business_name ?? this.selectedBranch.parent?.business_name ?? '';
        },
        totalItemsQuantity() {
            return this.form.items.reduce((total, item) => total + (Number(item.quantity) || 0), 0);
        },
        existing() {
            const media = this.requisition?.media ?? [];

            return media.reduce((grouped, file) => {
                grouped[file.collection_name] = [...(grouped[file.collection_name] ?? []), file];
                return grouped;
            }, {});
        },
    },
    methods: {
        /**
         * Partidas iniciales: las existentes al editar o una vacía al crear.
         */
        initialItems() {
            const items = this.requisition?.items ?? [];

            if (items.length) {
                return items.map(item => ({
                    key: `item-${itemKey++}`,
                    id: item.id,
                    product_id: item.product_id,
                    quantity: item.quantity,
                    shipments: item.shipments ?? [],
                }));
            }

            return [{ key: `item-${itemKey++}`, id: null, product_id: null, quantity: '', shipments: [] }];
        },
        addItem() {
            this.form.items.push({ key: `item-${itemKey++}`, id: null, product_id: null, quantity: '', shipments: [] });
        },
        removeItem(index) {
            if (this.form.items.length === 1) return;
            this.form.items.splice(index, 1);
        },
        itemIsShipped(item) {
            return Boolean(item.shipments?.length);
        },
        productFor(item) {
            const product = this.products.find(candidate => candidate.id === item.product_id);

            if (!product) return null;

            return {
                ...product,
                faltante: Math.max((Number(product.min_quantity) || 0) - (Number(product.current_stock) || 0), 0),
            };
        },
        isLowStock(product) {
            return Number(product.current_stock) < Number(product.min_quantity);
        },
        formatStock(value) {
            const number = Number(value) || 0;
            return number.toLocaleString('es-MX', { maximumFractionDigits: 2 });
        },
        collectError(field) {
            const errors = this.serverErrors ?? {};
            const error = errors[field];

            if (!error) return '';

            return Array.isArray(error) ? error[0] : error;
        },
        submit() {
            const isWarehouse = this.form.origin === 'Almacén';

            this.$emit('submit', {
                ...this.form,
                items: isWarehouse
                    ? this.form.items.map(item => ({
                        id: item.id,
                        product_id: item.product_id,
                        quantity: item.quantity,
                    }))
                    : undefined,
                files: { ...this.files },
            });
        },
    },
};
</script>
