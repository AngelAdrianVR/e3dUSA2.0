<template>
    <AppLayout title="Tarifas de envío">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Tarifas de envío
        </h2>

        <div class="py-7">
            <div class="max-w-[100rem] mx-auto sm:px-6 lg:px-8">

                <!-- Barra de herramientas -->
                <div class="bg-white dark:bg-slate-900 shadow-xl sm:rounded-lg p-6 mb-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <button v-if="canManage" @click="openCreateModal"
                                class="text-sm bg-sky-600 hover:bg-sky-700 text-white font-semibold px-4 py-2 rounded-md transition shadow-sm">
                                <i class="fa-solid fa-plus mr-1"></i>Agregar tarifa
                            </button>
                            <span class="text-xs text-gray-400 dark:text-gray-500">
                                {{ familiesWithRates.length }} {{ familiesWithRates.length === 1 ? 'familia con tarifas' : 'familias con tarifas' }}
                            </span>
                        </div>
                        <el-input v-model="search" clearable placeholder="Filtrar familia..." class="w-full md:!w-72">
                            <template #prefix>
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </template>
                        </el-input>
                    </div>
                </div>

                <!-- Una ficha (tabla) por familia de producto -->
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <div v-for="family in filteredFamilies" :key="family.id"
                        class="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg shadow-md overflow-hidden">

                        <!-- Cabecera de la ficha -->
                        <div class="bg-slate-800 dark:bg-slate-950 px-4 py-3 flex items-center justify-between gap-3">
                            <h3 class="text-white text-[13px] font-bold uppercase tracking-wide">
                                {{ family.name.toUpperCase() }}
                            </h3>
                            <span class="shrink-0 text-[11px] font-semibold bg-sky-600 text-white rounded-full px-2 py-0.5">
                                {{ family.shipping_rates.length }} {{ family.shipping_rates.length === 1 ? 'tarifa' : 'tarifas' }}
                            </span>
                        </div>

                        <!-- Código del SAT (único por familia) -->
                        <div class="flex flex-wrap items-center gap-2 px-4 py-3 bg-slate-50 dark:bg-slate-800/60 border-b border-gray-200 dark:border-slate-700">
                            <label class="text-[11px] font-bold uppercase text-gray-600 dark:text-gray-300">
                                Código del SAT:
                            </label>
                            <el-input v-model="satDrafts[family.id]" size="small" class="!w-40" placeholder="Ej. 39264001"
                                :disabled="!canManage" @keyup.enter="saveSatCode(family)" />
                            <button v-if="canManage && satCodeChanged(family)" @click="saveSatCode(family)"
                                class="text-xs bg-green-600 hover:bg-green-700 text-white font-semibold px-2.5 py-1 rounded-md transition"
                                title="Guardar código del SAT">
                                <i class="fa-solid fa-floppy-disk mr-1"></i>Guardar
                            </button>
                            <span v-else-if="family.sat_code" class="text-xs text-green-600 dark:text-green-400">
                                <i class="fa-solid fa-circle-check mr-1"></i>Guardado
                            </span>
                            <span v-else class="text-xs text-gray-400">Sin código</span>
                        </div>

                        <!-- Tabla de tarifas -->
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[720px] text-sm">
                                <thead class="bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-gray-300">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase min-w-[110px]">Cantidad</th>
                                        <th class="px-3 py-2 text-center text-[11px] font-semibold uppercase min-w-[110px]">Largo (cm)</th>
                                        <th class="px-3 py-2 text-center text-[11px] font-semibold uppercase min-w-[110px]">Ancho (cm)</th>
                                        <th class="px-3 py-2 text-center text-[11px] font-semibold uppercase min-w-[110px]">Alto (cm)</th>
                                        <th class="px-3 py-2 text-center text-[11px] font-semibold uppercase min-w-[110px]">Peso (kg)</th>
                                        <th class="px-3 py-2 text-center text-[11px] font-semibold uppercase w-24 sticky right-0 z-10 bg-gray-100 dark:bg-slate-800 border-l border-gray-200 dark:border-slate-700">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">

                                    <!-- Fila para agregar una nueva tarifa -->
                                    <tr v-if="addingFamilyId === family.id" class="bg-sky-50 dark:bg-sky-950">
                                        <td class="px-2 py-1.5">
                                            <el-input-number v-model="form.quantity" :min="1" :precision="0" :controls="false"
                                                size="small" class="w-full" placeholder="Cantidad" />
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <el-input-number v-model="form.length_cm" :min="0" :precision="2" :controls="false"
                                                size="small" class="w-full" />
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <el-input-number v-model="form.width_cm" :min="0" :precision="2" :controls="false"
                                                size="small" class="w-full" />
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <el-input-number v-model="form.height_cm" :min="0" :precision="2" :controls="false"
                                                size="small" class="w-full" />
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <el-input-number v-model="form.weight_kg" :min="0" :precision="2" :controls="false"
                                                size="small" class="w-full" />
                                        </td>
                                        <td class="px-2 py-1.5 sticky right-0 z-10 bg-sky-50 dark:bg-sky-950 border-l border-gray-200 dark:border-slate-700">
                                            <div class="flex items-center justify-center gap-3">
                                                <button @click="submitForm" :disabled="form.processing"
                                                    class="text-green-600 hover:text-green-700 disabled:opacity-50" title="Guardar">
                                                    <i class="fa-solid" :class="form.processing ? 'fa-spinner fa-spin' : 'fa-check'"></i>
                                                </button>
                                                <button @click="cancelForm" type="button"
                                                    class="text-gray-500 hover:text-red-500" title="Cancelar">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                <!-- Filas de tarifas existentes -->
                                <template v-for="rate in family.shipping_rates" :key="rate.id">
                                    <!-- Fila en edición -->
                                    <tr v-if="editingRateId === rate.id" class="bg-amber-50 dark:bg-amber-950">
                                        <td class="px-2 py-1.5">
                                            <el-input-number v-model="form.quantity" :min="1" :precision="0" :controls="false"
                                                size="small" class="w-full" />
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <el-input-number v-model="form.length_cm" :min="0" :precision="2" :controls="false"
                                                size="small" class="w-full" />
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <el-input-number v-model="form.width_cm" :min="0" :precision="2" :controls="false"
                                                size="small" class="w-full" />
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <el-input-number v-model="form.height_cm" :min="0" :precision="2" :controls="false"
                                                size="small" class="w-full" />
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <el-input-number v-model="form.weight_kg" :min="0" :precision="2" :controls="false"
                                                size="small" class="w-full" />
                                        </td>
                                        <td class="px-2 py-1.5 sticky right-0 z-10 bg-amber-50 dark:bg-amber-950 border-l border-gray-200 dark:border-slate-700">
                                            <div class="flex items-center justify-center gap-3">
                                                <button @click="submitForm" :disabled="form.processing"
                                                    class="text-green-600 hover:text-green-700 disabled:opacity-50" title="Guardar">
                                                    <i class="fa-solid" :class="form.processing ? 'fa-spinner fa-spin' : 'fa-check'"></i>
                                                </button>
                                                <button @click="cancelForm" type="button"
                                                    class="text-gray-500 hover:text-red-500" title="Cancelar">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Fila en modo lectura -->
                                    <tr v-else class="group hover:bg-gray-50 dark:hover:bg-slate-800">
                                        <td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">
                                            {{ formatNumber(rate.quantity) }}
                                        </td>
                                        <td class="px-3 py-2 text-center text-gray-600 dark:text-gray-300">{{ formatDecimal(rate.length_cm) }}</td>
                                        <td class="px-3 py-2 text-center text-gray-600 dark:text-gray-300">{{ formatDecimal(rate.width_cm) }}</td>
                                        <td class="px-3 py-2 text-center text-gray-600 dark:text-gray-300">{{ formatDecimal(rate.height_cm) }}</td>
                                        <td class="px-3 py-2 text-center text-gray-600 dark:text-gray-300">{{ formatDecimal(rate.weight_kg) }}</td>
                                        <td class="px-3 py-2 sticky right-0 z-10 bg-white dark:bg-slate-900 group-hover:bg-gray-50 dark:group-hover:bg-slate-800 border-l border-gray-200 dark:border-slate-700">
                                            <div class="flex items-center justify-center gap-3">
                                                <button v-if="canManage" @click="startEditing(family, rate)"
                                                    class="text-gray-500 hover:text-blue-500 transition-colors" title="Editar">
                                                    <i class="fa-solid fa-pencil"></i>
                                                </button>
                                                <el-popconfirm v-if="canManage" confirm-button-text="Sí" cancel-button-text="No"
                                                    icon-color="#EF4444"
                                                    :title="`¿Eliminar la tarifa de ${formatNumber(rate.quantity)} piezas?`"
                                                    @confirm="destroyRate(rate)">
                                                    <template #reference>
                                                        <button class="text-gray-500 hover:text-red-500 transition-colors" title="Eliminar">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </template>
                                                </el-popconfirm>
                                                <span v-if="!canManage" class="text-xs text-gray-400">—</span>
                                            </div>
                                        </td>
                                    </tr>
                                </template>

                                <!-- Ficha sin tarifas -->
                                <tr v-if="!family.shipping_rates.length && addingFamilyId !== family.id">
                                    <td colspan="6" class="px-3 py-6 text-center text-xs text-gray-400">
                                        Sin tarifas registradas para esta familia.
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Errores de validación de la ficha en edición -->
                        <div v-if="editingFamilyId === family.id && hasErrors"
                            class="px-4 py-2 bg-red-50 dark:bg-red-950/30 text-xs text-red-600 dark:text-red-400 space-y-0.5">
                            <p v-for="(message, field) in form.errors" :key="field">
                                <i class="fa-solid fa-circle-exclamation mr-1"></i>{{ message }}
                            </p>
                        </div>

                        <!-- Pie de la ficha -->
                        <div class="px-4 py-3 flex justify-end border-t border-gray-200 dark:border-slate-700">
                            <button v-if="canManage" @click="startAdding(family)"
                                class="text-xs bg-sky-600 hover:bg-sky-700 text-white font-semibold px-3 py-1.5 rounded-md transition">
                                <i class="fa-solid fa-plus mr-1"></i>Agregar tarifa
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Sin resultados / sin tarifas -->
                <div v-if="!filteredFamilies.length"
                    class="bg-white dark:bg-slate-900 shadow-xl sm:rounded-lg p-10 text-center text-sm text-gray-400">
                    <template v-if="!familiesWithRates.length">
                        <i class="fa-solid fa-box-open text-3xl mb-3 block opacity-60"></i>
                        Aún no hay tarifas registradas.
                        <button v-if="canManage" @click="openCreateModal" class="text-sky-600 hover:text-sky-700 font-semibold underline">
                            Agrega la primera tarifa
                        </button>
                        para comenzar.
                    </template>
                    <template v-else>
                        No se encontraron familias que coincidan con la búsqueda.
                    </template>
                </div>
            </div>
        </div>

        <!-- Modal: agregar tarifa a cualquier familia (incluidas las que aún no tienen ficha) -->
        <DialogModal :show="showCreateModal" @close="closeCreateModal">
            <template #title>Agregar tarifa de envío</template>
            <template #content>
                <form @submit.prevent="submitCreate" class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <InputLabel value="Familia del producto*" />
                        <el-select v-model="createForm.product_family_id" filterable clearable
                            placeholder="Selecciona la familia" class="w-full" :teleported="false">
                            <el-option v-for="family in families" :key="family.id" :label="family.name" :value="family.id" />
                        </el-select>
                        <InputError :message="createForm.errors.product_family_id" />
                    </div>

                    <div>
                        <InputLabel value="Cantidad por caja*" />
                        <el-input-number v-model="createForm.quantity" :min="1" :precision="0" :controls="false"
                            class="w-full" placeholder="Ej. 100" />
                        <InputError :message="createForm.errors.quantity" />
                    </div>

                    <div>
                        <InputLabel value="Peso (kg)*" />
                        <el-input-number v-model="createForm.weight_kg" :min="0" :precision="2" :controls="false"
                            class="w-full" placeholder="Ej. 12.50" />
                        <InputError :message="createForm.errors.weight_kg" />
                    </div>

                    <div>
                        <InputLabel value="Largo (cm)*" />
                        <el-input-number v-model="createForm.length_cm" :min="0" :precision="2" :controls="false"
                            class="w-full" placeholder="Ej. 40" />
                        <InputError :message="createForm.errors.length_cm" />
                    </div>

                    <div>
                        <InputLabel value="Ancho (cm)*" />
                        <el-input-number v-model="createForm.width_cm" :min="0" :precision="2" :controls="false"
                            class="w-full" placeholder="Ej. 30" />
                        <InputError :message="createForm.errors.width_cm" />
                    </div>

                    <div>
                        <InputLabel value="Alto (cm)*" />
                        <el-input-number v-model="createForm.height_cm" :min="0" :precision="2" :controls="false"
                            class="w-full" placeholder="Ej. 20" />
                        <InputError :message="createForm.errors.height_cm" />
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="flex space-x-3">
                    <CancelButton @click="closeCreateModal" :disabled="createForm.processing">Cancelar</CancelButton>
                    <SecondaryButton @click="submitCreate" :loading="createForm.processing">
                        Guardar tarifa
                    </SecondaryButton>
                </div>
            </template>
        </DialogModal>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import CancelButton from '@/Components/MyComponents/CancelButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

export default {
    name: 'ShippingRateIndex',
    components: {
        AppLayout,
        DialogModal,
        InputLabel,
        InputError,
        CancelButton,
        SecondaryButton,
    },
    props: {
        families: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            search: '',
            addingFamilyId: null,
            editingRateId: null,
            editingFamilyId: null,
            showCreateModal: false,
            // Borradores del código del SAT por familia (se guardan al confirmar)
            satDrafts: {},
            form: useForm({
                product_family_id: null,
                quantity: null,
                length_cm: null,
                width_cm: null,
                height_cm: null,
                weight_kg: null,
            }),
            // Formulario del modal para dar de alta la primera tarifa de una familia
            createForm: useForm({
                product_family_id: null,
                quantity: null,
                length_cm: null,
                width_cm: null,
                height_cm: null,
                weight_kg: null,
            }),
        };
    },
    computed: {
        canManage() {
            return this.$page.props.auth.user?.permissions?.includes('Gestionar tarifas') ?? false;
        },
        hasErrors() {
            return Object.keys(this.form.errors).length > 0;
        },
        // Solo se muestran las familias que ya tienen al menos una tarifa registrada
        familiesWithRates() {
            return this.families.filter(family => family.shipping_rates.length > 0);
        },
        filteredFamilies() {
            const term = this.search.trim().toLowerCase();
            if (!term) return this.familiesWithRates;

            return this.familiesWithRates.filter(family =>
                family.name.toLowerCase().includes(term) ||
                (family.key || '').toLowerCase().includes(term)
            );
        },
    },
    created() {
        this.syncSatDrafts();
    },
    watch: {
        // Al recargar la página (guardar/eliminar), refrescamos los borradores del SAT
        families() {
            this.syncSatDrafts();
        },
    },
    methods: {
        syncSatDrafts() {
            const drafts = {};
            this.families.forEach(family => {
                drafts[family.id] = family.sat_code ?? '';
            });
            this.satDrafts = drafts;
        },
        satCodeChanged(family) {
            return (this.satDrafts[family.id] ?? '') !== (family.sat_code ?? '');
        },
        saveSatCode(family) {
            if (!this.satCodeChanged(family)) return;

            router.put(route('product-families.sat-code', family.id), {
                sat_code: this.satDrafts[family.id] || null,
            }, {
                preserveScroll: true,
                onSuccess: () => ElMessage.success(`Código del SAT de ${family.name} actualizado`),
            });
        },
        openCreateModal() {
            this.createForm.reset();
            this.createForm.clearErrors();
            this.showCreateModal = true;
        },
        closeCreateModal() {
            this.showCreateModal = false;
            this.createForm.reset();
            this.createForm.clearErrors();
        },
        submitCreate() {
            this.createForm.post(route('shipping-rates.store'), {
                preserveScroll: true,
                onSuccess: () => {
                    ElMessage.success('Tarifa agregada');
                    this.closeCreateModal();
                },
            });
        },
        startAdding(family) {
            this.cancelForm();
            this.addingFamilyId = family.id;
            this.editingFamilyId = family.id;
            this.form.product_family_id = family.id;
        },
        startEditing(family, rate) {
            this.cancelForm();
            this.editingFamilyId = family.id;
            this.editingRateId = rate.id;
            this.form.product_family_id = family.id;
            this.form.quantity = Number(rate.quantity);
            this.form.length_cm = Number(rate.length_cm);
            this.form.width_cm = Number(rate.width_cm);
            this.form.height_cm = Number(rate.height_cm);
            this.form.weight_kg = Number(rate.weight_kg);
        },
        cancelForm() {
            this.form.reset();
            this.form.clearErrors();
            this.addingFamilyId = null;
            this.editingRateId = null;
            this.editingFamilyId = null;
        },
        submitForm() {
            const options = {
                preserveScroll: true,
                onSuccess: () => {
                    ElMessage.success(this.editingRateId ? 'Tarifa actualizada' : 'Tarifa agregada');
                    this.cancelForm();
                },
            };

            if (this.editingRateId) {
                this.form.put(route('shipping-rates.update', this.editingRateId), options);
            } else {
                this.form.post(route('shipping-rates.store'), options);
            }
        },
        destroyRate(rate) {
            router.delete(route('shipping-rates.destroy', rate.id), {
                preserveScroll: true,
                onSuccess: () => ElMessage.success('Tarifa eliminada'),
            });
        },
        formatDecimal(value) {
            const num = Number(value);
            return isNaN(num) ? value : num.toFixed(2);
        },
        formatNumber(value) {
            if (value === null || value === undefined || value === '') return '-';
            const num = Number(value);
            return isNaN(num) ? value : new Intl.NumberFormat('es-MX').format(num);
        },
    },
};
</script>
