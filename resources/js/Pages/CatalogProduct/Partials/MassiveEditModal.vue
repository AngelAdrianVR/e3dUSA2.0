<template>
    <DialogModal :show="show" @close="close" max-width="4xl">
        <template #title>
            Edición Masiva de Productos
        </template>

        <template #content>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Modifica los campos para cada uno de los <b>{{ form.products.length }}</b> productos seleccionados. El código se regenerará automáticamente.
            </p>
            
            <div class="hidden md:grid grid-cols-12 gap-x-4 font-bold text-sm text-gray-700 dark:text-gray-300 border-b pb-2 mb-2">
                <div class="col-span-4">Producto</div>
                <div class="col-span-3">Familia</div>
                <div class="col-span-3">Material</div>
                <div class="col-span-2 text-center">¿Componente?</div>
            </div>

            <div class="space-y-3 min-h-56 max-h-96 overflow-y-auto pr-2">
                 <div v-for="(product, index) in form.products" :key="product.id"
                     class="grid grid-cols-1 md:grid-cols-12 gap-x-4 gap-y-2 items-center border-b dark:border-slate-700 py-2">
                    
                    <div class="col-span-full md:col-span-4">
                         <p class="font-semibold text-gray-800 dark:text-gray-200">{{ product.name }}</p>
                         <p class="text-xs text-gray-500">{{ product.code }}</p>
                    </div>

                    <div class="col-span-full md:col-span-3">
                         <InputLabel :for="'family-' + product.id" value="Familia" class="md:hidden mb-1" />
                         <el-select v-model="product.product_family_id" :teleported="false" filterable clearable placeholder="Familia" class="w-full" :id="'family-' + product.id">
                            <el-option v-for="item in product_families" :key="item.id" :label="item.name" :value="item.id" />
                         </el-select>
                         <InputError :message="form.errors[`products.${index}.product_family_id`]" class="mt-1" />
                    </div>

                    <div class="col-span-full md:col-span-3">
                        <InputLabel :for="'material-' + product.id" value="Material" class="md:hidden mb-1" />
                        <el-select v-model="product.material" :teleported="false" clearable placeholder="Material" class="w-full" :id="'material-' + product.id">
                            <el-option v-for="item in materialOptions" :key="item.key" :label="item.label" :value="item.key" />
                        </el-select>
                        <InputError :message="form.errors[`products.${index}.material`]" class="mt-1" />
                    </div>

                    <div class="col-span-full md:col-span-2 flex justify-center items-center pt-2 md:pt-0">
                         <Checkbox v-model:checked="product.is_used_as_component" :name="'component-' + product.id" />
                         <InputError :message="form.errors[`products.${index}.is_used_as_component`]" class="mt-1" />
                    </div>
                 </div>
            </div>

             <InputError :message="form.errors.massive_update" class="mt-2" />
        </template>

        <template #footer>
            <CancelButton @click="close" :disabled="form.processing">
                Cancelar
            </CancelButton>
            <SecondaryButton @click="submit" :loading="form.processing" :disabled="form.processing" class="ml-2">
                Actualizar {{ form.products.length }} Productos
            </SecondaryButton>
        </template>
    </DialogModal>
</template>

<script>
import DialogModal from '@/Components/DialogModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import CancelButton from '@/Components/MyComponents/CancelButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import { watch } from 'vue';

export default {
    name: 'MassiveEditModal',
    components: {
        DialogModal,
        SecondaryButton,
        CancelButton,
        InputLabel,
        InputError,
        Checkbox,
    },
    props: {
        show: {
            type: Boolean,
            default: false,
        },
        selected_products: {
            type: Array,
            required: true,
        },
        product_families: {
            type: Array,
            required: true,
        },
    },
    emits: ['close'],
    setup(props, { emit }) {
        const form = useForm({
            products: [],
        });

        const materialOptions = [
            { label: 'METAL', key: 'M' }, { label: 'PLASTICO', key: 'PLS' }, { label: 'PIEL DE LUJO', key: 'PL' },
            { label: 'ORIGINAL', key: 'O' }, { label: 'LUJO', key: 'L' }, { label: 'PIEL', key: 'P' }, { label: 'ZAMAK', key: 'ZK' },
            { label: 'SOLIDCHROME', key: 'SCH' }, { label: 'MICROMETAL', key: 'MM' }, { label: 'FLEXCHROME', key: 'FCH' }, { label: 'ALUMINIO', key: 'AL' },
            { label: 'ESTIRENO', key: 'ES' }, { label: 'ABS', key: 'ABS' }, { label: 'PVC', key: 'PVC' }, { label: 'TELA', key: 'T' }, { label: 'CAUCHO', key: 'CAU' },
            { label: 'VINILPIEL', key: 'VPL' }
        ];
        
        const materialReverseMap = Object.fromEntries(materialOptions.map(opt => [opt.label, opt.key]));

        const initializeForm = () => {
            form.products = props.selected_products.map(p => ({
                id: p.id,
                name: p.name,
                code: p.code,
                product_family_id: p.product_family_id,
                material: p.material ? materialReverseMap[p.material] : null,
                is_used_as_component: p.is_used_as_component,
            }));
             form.clearErrors();
        };

        const submit = () => {
            form.post(route('products.massive-update'), {
                onSuccess: () => {
                    ElMessage.success('Productos actualizados correctamente');
                    close();
                },
                onError: () => {
                     ElMessage.error('Ocurrió un error al actualizar. Revisa los datos.');
                }
            });
        };

        const close = () => {
            emit('close');
        };

        watch(() => props.show, (newVal) => {
            if (newVal) {
                initializeForm();
            }
        });

        return {
            form,
            materialOptions,
            submit,
            close,
        };
    },
};
</script>

