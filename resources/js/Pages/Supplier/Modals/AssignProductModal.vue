<template>
    <DialogModal :show="show" maxWidth="3xl" @close="$emit('close')">
        <template #title>
            {{ isEditing ? 'Editar Producto Asignado' : 'Asignar Nuevo Producto' }}
        </template>
        <template #content>
            <form @submit.prevent="save">
                <div class="grid grid-cols-2 gap-4 min-h-56">
                    <!-- Selector de producto (solo para crear) -->
                    <div class="col-span-2">
                         <InputLabel value="Producto*" />
                        <el-select v-model="form.product_id"
                                @change="getProductMedia"
                                filterable
                                :teleported="false"
                                placeholder="Selecciona un producto"
                                class="!w-1/2"
                                no-data-text="No hay productos"
                                no-match-text="No se encontraron coincidencias"
                                :disabled="isEditing">
                            <el-option v-for="item in catalogProducts"
                                :key="item.id"
                                :label="item.name"
                                :value="item.id" />
                        </el-select>
                        <InputError :message="form.errors.product_id" />
                    </div>

                    <!-- Campos de precio y cantidad -->
                    <TextInput v-model="form.last_price" label="Último Precio de Compra*" :error="form.errors.last_price" type="number" />
                    <TextInput v-model="form.min_quantity" label="Cantidad Mínima de Compra" :error="form.errors.min_quantity" type="number" />

                    <LoadingIsoLogo v-if="loadingComponentMedia" />

                    <!-- Tarjeta de materia prima seleccionada -->
                    <div class="flex items-center space-x-4 p-2 bg-gray-100 dark:bg-slate-900/50 rounded-md col-span-full mb-2" v-else-if="form.product_id">
                        <figure 
                            v-if="newProduct.media" 
                            class="relative flex items-center justify-center size-32 rounded-2xl border border-gray-200 dark:border-slate-900 overflow-hidden shadow-lg transition transform hover:shadow-xl">
                            <img v-if="newProduct.media?.length"
                                :src="newProduct.media[0]?.original_url" 
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
                                Stock: <strong>{{ newProduct.storages[0]?.quantity.replace(/\B(?=(\d{3})+(?!\d))/g, ",") }} {{ newProduct.measure_unit }}</strong>
                            </p>
                            <p class="text-gray-500 dark:text-gray-300">
                                Ubicación: <strong>{{ newProduct.storages[0]?.location ?? 'No asignado' }}</strong>
                            </p>
                            <p class="text-gray-500 dark:text-gray-300">
                                Costo: <strong>${{ newProduct.cost ?? '0.00' }} {{ newProduct.currency }}</strong>
                            </p>
                        </div>
                    </div>
                </div>
            </form>
        </template>
        <template #footer>
            <CancelButton @click="$emit('close')">Cancelar</CancelButton>
            <PrimaryButton @click="save" :loading="form.processing" class="ml-2">{{ isEditing ? 'Guardar Cambios' : 'Asignar' }}</PrimaryButton>
        </template>
    </DialogModal>
</template>

<script>
import DialogModal from '@/Components/DialogModal.vue';
import LoadingIsoLogo from "@/Components/MyComponents/LoadingIsoLogo.vue";
import PrimaryButton from '@/Components/PrimaryButton.vue';
import CancelButton from '@/Components/MyComponents/CancelButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import { computed, watch } from 'vue';

export default {
    data() {
        const initialNewProductState = {
            product_id: null,
            last_price: null,
            supplier_sku: null,
            min_quantity: 1,
            media: [],
            storages: [],
            cost: null,
            measure_unit: '',
            currency: ''
        };

        return {
            newProduct: { ...initialNewProductState },
            loadingComponentMedia: false,
        }
    },
    props: {
        show: Boolean,
        supplierId: Number,
        catalogProducts: Array,
        productToEdit: Object,
    },
    emits: ['close'],
    components: {
        TextInput,
        InputLabel,
        InputError,
        DialogModal,
        CancelButton,
        PrimaryButton,
        LoadingIsoLogo,
    },
    methods:{
        async getProductMedia() {
            if (!this.form.product_id) return;
            this.loadingComponentMedia = true;
            try {
                const response = await axios.get(route('products.get-media', this.form.product_id));

                if ( response.status === 200 ) {
                    this.newProduct.media = response.data.product.media;
                    this.newProduct.storages = response.data.product.storages;
                    this.newProduct.cost = response.data.product.cost;
                    this.newProduct.measure_unit = response.data.product.measure_unit;
                    this.newProduct.currency = response.data.product.currency;

                    // Si estamos agregando un nuevo producto (no editando), se asigna el costo como precio.
                    this.form.last_price = response.data.product.cost;
                }
            } catch (error) {
                console.log(error);
                ElMessage.error('No se pudo cargar la información del producto');
            } finally {
                this.loadingComponentMedia = false;
            }
        },
    },

    setup(props, { emit }) {
        const isEditing = computed(() => !!props.productToEdit);

        const form = useForm({
            product_id: null,
            last_price: '',
            min_quantity: 1,
            supplier_id: props.supplierId,
        });

        watch(() => props.show, (newVal) => {
            if (newVal) {
                if (isEditing.value) {
                    form.product_id = props.productToEdit.id;
                    form.last_price = props.productToEdit.pivot.last_price;
                    form.min_quantity = props.productToEdit.pivot.min_quantity;
                } else {
                    form.reset();
                    form.supplier_id = props.supplierId;
                }
                 form.clearErrors();
            }
        });

        const save = () => {
            if (isEditing.value) {
                const url = route('suppliers.products.update', { supplier: props.supplierId, product: props.productToEdit.id });
                form.put(url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        ElMessage.success('Producto actualizado.');
                        emit('close');
                    },
                    onError: () => ElMessage.error('Hubo un error al actualizar.'),
                });
            } else {
                const url = route('suppliers.products.store', { supplier: props.supplierId });
                form.post(url, {
                     preserveScroll: true,
                     onSuccess: () => {
                        ElMessage.success('Producto asignado.');
                        emit('close');
                    },
                     onError: () => ElMessage.error('Hubo un error al asignar.'),
                });
            }
        };

        return { form, isEditing, save };
    }
}
</script>
