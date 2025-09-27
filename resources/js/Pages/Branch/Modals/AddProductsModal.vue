<template>
    <DialogModal :show="show" @close="closeModal">
        <template #title>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                <i class="fa-solid fa-tags mr-2"></i> Asignar Productos a {{ branch.name }}
            </h2>
        </template>
        <template #content>
            <form @submit.prevent="saveProducts">
                <div class="p-4 border border-gray-200 dark:border-slate-700 rounded-lg">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-1">
                        <div>
                            <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Buscar producto*</label>
                            <el-select @change="getProductMedia" :teleported="false" v-model="currentProduct.product_id" placeholder="Selecciona un producto" class="!w-full" filterable>
                                <el-option class="w-96" v-for="item in availableProducts" 
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
                            <img v-if="currentProduct.media?.length" :src="currentProduct.media[0]?.original_url" alt="Imagen del producto" class="rounded-2xl w-full h-auto object-cover">
                            <div v-else class="flex flex-col items-center justify-center text-gray-400 dark:text-slate-500 p-2 text-center">
                                <i class="fa-solid fa-image text-3xl"></i>
                                <p class="text-xs mt-1">Sin imagen</p>
                            </div>
                        </figure>
                        <div>
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
                        <SecondaryButton @click="addProduct" type="button" :disabled="!currentProduct.product_id">
                            <i class="fa-solid fa-plus mr-2"></i> Agregar a la lista
                        </SecondaryButton>
                    </div>
                </div>

                <div v-if="form.products.length" class="mt-4">
                    <InputError :message="form.errors.products" />
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Productos a asignar:</p>
                    <ul class="rounded-lg bg-gray-100 dark:bg-slate-900 p-3 space-y-2 max-h-48 overflow-y-auto">
                        <li v-for="(product, index) in form.products" :key="index" class="flex justify-between items-center p-2 rounded-md">
                            <span class="text-sm text-gray-800 dark:text-gray-200">
                                <span class="font-bold text-primary">{{ getProductName(product.product_id) }}</span>
                            </span>
                            <div class="flex items-center space-x-3 text-sm">
                                <span class="text-gray-600 dark:text-gray-400">
                                    Precio Especial: <strong>{{ product.price ? '$' + product.price : 'Precio base' }}</strong>
                                </span>
                                <button @click="removeProduct(index)" type="button" class="text-gray-500 hover:text-red-500 transition-colors">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>
                 <p v-else class="text-sm text-gray-500 dark:text-gray-400 p-4 text-center">Aún no has agregado productos a la lista.</p>
            </form>
        </template>
        <template #footer>
            <div class="flex space-x-2">
                <CancelButton @click="closeModal">Cancelar</CancelButton>
                <PrimaryButton @click="saveProducts" :disabled="!form.products.length || form.processing">
                    <span v-if="form.processing">Guardando...</span>
                    <span v-else>Guardar Productos</span>
                </PrimaryButton>
            </div>
        </template>
    </DialogModal>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import CancelButton from '@/Components/MyComponents/CancelButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { ElMessage } from 'element-plus';
import axios from 'axios';

export default {
    components: {
        DialogModal,
        PrimaryButton,
        SecondaryButton,
        CancelButton,
        TextInput,
        InputError,
    },
    props: {
        show: Boolean,
        branch: Object,
        catalog_products: Array,
    },
    data() {
        return {
            form: useForm({
                products: [],
            }),
            currentProduct: {
                product_id: null,
                price: null,
                media: null,
                base_price: null,
                current_stock: null,
                currency: 'MXN',
                location: null
            },
            loadingProductMedia: false,
        };
    },
    computed: {
        availableProducts() {
            const assignedProductIds = this.branch.products.map(p => p.id);
            return this.catalog_products.filter(p => !assignedProductIds.includes(p.id));
        }
    },
    methods: {
        saveProducts() {
            this.form.post(route('branches.add-products', this.branch.id), {
                preserveScroll: true,
                onSuccess: () => {
                    ElMessage.success('Productos asignados correctamente.');
                    this.closeModal();
                },
                onError: () => {
                     ElMessage.error('Ocurrió un error al asignar los productos.');
                }
            });
        },
        async getProductMedia() {
            if (!this.currentProduct.product_id) return;
            
            this.loadingProductMedia = true;
            try {
                const response = await axios.get(route('products.get-media', this.currentProduct.product_id));
                const product = response.data.product;
                this.currentProduct.media = product.media;
                this.currentProduct.base_price = product.base_price;
                this.currentProduct.current_stock = product.current_stock;
                this.currentProduct.location = product.location;
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
                ElMessage.warning('Este producto ya está en la lista.');
                return;
            }
            this.form.products.push({ ...this.currentProduct });
            this.resetCurrentProduct();
        },
        removeProduct(index) {
            this.form.products.splice(index, 1);
        },
        resetCurrentProduct() {
            this.currentProduct = {
                product_id: null,
                price: null,
                media: null,
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
        },
        closeModal() {
            this.form.reset();
            this.resetCurrentProduct();
            this.$emit('close');
        }
    },
    watch: {
        show(value) {
            if (!value) {
                this.closeModal();
            }
        }
    }
}
</script>
