<template>
    <div>
        <!-- Botón para asignar nuevo producto -->
        <div class="flex justify-end mb-4">
            <PrimaryButton @click="showAssignProductModal = true">
                <i class="fa-solid fa-plus mr-2"></i>
                Asignar Producto
            </PrimaryButton>
        </div>

        <!-- Grid de productos asignados -->
        <div v-if="products.length" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            <div v-for="product in products" :key="product.id"
                 class="relative group bg-gray-100 dark:bg-slate-900 rounded-lg hover:border-primary dark:hover:border-primary shadow-lg overflow-hidden border border-transparent dark:border-slate-800 transition duration-300">

                <!-- Imagen del producto -->
                <div class="h-40 bg-gray-200 dark:bg-slate-800 flex items-center justify-center">
                    <img :src="product.media[0]?.original_url ?? `https://placehold.co/400x300/e2e8f0/64748b?text=${product.name}`"
                         :alt="product.name" class="h-full w-full object-cover">
                </div>

                <!-- Contenido de la tarjeta -->
                <div class="p-4">
                    <h3 class="font-bold text-lg text-gray-800 dark:text-white truncate">{{ product.name }}</h3>
                    <div class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                        <p>
                            <span class="font-semibold">Último precio:</span>
                            <span class="text-green-600 dark:text-green-400 font-bold ml-1">${{ product.pivot?.last_price?.replace(/\B(?=(\d{3})+(?!\d))/g, ",") }}</span>
                        </p>
                        <p>
                            <span class="font-semibold">Cant. Mínima:</span>
                            <span class="ml-1">{{ product.pivot.min_quantity ?? 'N/A' }} {{ product.measure_unit }}</span>
                        </p>
                    </div>
                </div>

                 <!-- Botones de acción (aparecen al hacer hover) -->
                <div class="absolute top-2 right-2 flex flex-col space-y-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    <el-tooltip content="Editar" placement="left">
                        <button @click="openEditModal(product)" class="size-8 rounded-full bg-white/80 dark:bg-slate-800/80 backdrop-blur-sm shadow-md hover:bg-blue-500 hover:text-white transition-colors">
                            <i class="fa-solid fa-pencil text-xs"></i>
                        </button>
                    </el-tooltip>
                    <el-tooltip content="Desvincular" placement="left">
                        <button @click="confirmDelete(product)" class="size-8 rounded-full bg-white/80 dark:bg-slate-800/80 backdrop-blur-sm shadow-md hover:bg-red-500 hover:text-white transition-colors">
                            <i class="fa-regular fa-trash-can text-xs"></i>
                        </button>
                    </el-tooltip>
                </div>
            </div>
        </div>

        <!-- Mensaje si no hay productos -->
        <div v-else class="text-center text-gray-500 dark:text-gray-400 py-10 border-2 border-dashed dark:border-gray-700 rounded-lg">
             <i class="fa-solid fa-box-open text-4xl mb-3"></i>
            <p class="font-semibold">No hay productos asignados</p>
            <p class="text-sm">Asigna el primer producto a este proveedor.</p>
        </div>
    </div>

    <!-- Modals -->
    <AssignProductModal :show="showAssignProductModal"
                       :supplierId="supplierId"
                       :catalogProducts="catalog"
                       :productToEdit="productToEdit"
                       @close="closeAssignModal" />

    <ConfirmationModal :show="showConfirmDeleteModal" @close="showConfirmDeleteModal = false">
         <template #title>
            Desvincular Producto
        </template>
        <template #content>
            ¿Estás seguro de que deseas quitar <strong>{{ productToDelete?.name }}</strong> del proveedor?
        </template>
        <template #footer>
            <div class="flex space-x-2">
                <CancelButton @click="showConfirmDeleteModal = false">Cancelar</CancelButton>
                <PrimaryButton @click="deleteProduct" :loading="isDeleting" class="!bg-red-600 hover:!bg-red-700">Desvincular</PrimaryButton>
            </div>
        </template>
    </ConfirmationModal>
</template>

<script>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import CancelButton from '@/Components/MyComponents/CancelButton.vue';
import AssignProductModal from '../Modals/AssignProductModal.vue';
import { router } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

export default {
    data() {
        return {
            showAssignProductModal: false,
            showConfirmDeleteModal: false,
            isDeleting: false,
            productToDelete: null,
            productToEdit: null,
        };
    },
    components: {
        PrimaryButton,
        ConfirmationModal,
        CancelButton,
        AssignProductModal,
    },
    props: {
        products: Array,
        catalog: Array,
        supplierId: Number,
    },
    methods: {
        openEditModal(product) {
            this.productToEdit = product;
            this.showAssignProductModal = true;
        },
        confirmDelete(product) {
            this.productToDelete = product;
            this.showConfirmDeleteModal = true;
        },
        deleteProduct() {
            this.isDeleting = true;
            const url = route('suppliers.products.destroy', { supplier: this.supplierId, product: this.productToDelete.id });
            router.delete(url, {
                preserveScroll: true,
                onSuccess: () => {
                    ElMessage.success('Producto desvinculado correctamente.');
                    this.showConfirmDeleteModal = false;
                    this.productToDelete = null;
                },
                onError: () => {
                     ElMessage.error('Ocurrió un error al desvincular el producto.');
                },
                onFinish: () => {
                    this.isDeleting = false;
                }
            });
        },
        closeAssignModal() {
            this.showAssignProductModal = false;
            this.productToEdit = null;
        }
    }
}
</script>
