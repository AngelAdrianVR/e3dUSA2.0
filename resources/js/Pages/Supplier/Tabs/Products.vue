<template>
    <div>
        <div class="flex justify-end mb-4">
            <PrimaryButton @click="showAssignProductModal = true">
                <i class="fa-solid fa-plus mr-2"></i>
                Asignar Producto
            </PrimaryButton>
        </div>
        <ul v-if="products.length" class="space-y-2">
            <li v-for="product in products" :key="product.id" class="flex justify-between items-center p-3 rounded-lg bg-gray-100 dark:bg-slate-900">
                <div>
                    <p class="font-bold text-primary">{{ product.name }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Último precio: ${{ product.pivot.last_price }}
                    </p>
                    <p class="text-xs text-gray-500">
                        Cant. Mínima: {{ product.pivot.min_quantity ?? 'N/A' }} {{ product.measure_unit }}
                    </p>
                </div>
                <div class="flex space-x-2">
                     <el-tooltip content="Editar" placement="top">
                        <button @click="openEditModal(product)" class="size-7 rounded-md bg-gray-200 dark:bg-slate-700 hover:bg-blue-200 dark:hover:bg-blue-900 transition-colors">
                            <i class="fa-solid fa-pencil text-xs"></i>
                        </button>
                    </el-tooltip>
                    <el-tooltip content="Eliminar" placement="top">
                        <button @click="confirmDelete(product.id)" class="size-7 rounded-md bg-gray-200 dark:bg-slate-700 hover:bg-red-200 dark:hover:bg-red-900 transition-colors">
                            <i class="fa-regular fa-trash-can text-xs"></i>
                        </button>
                    </el-tooltip>
                </div>
            </li>
        </ul>
        <p v-else class="text-sm text-gray-500 dark:text-gray-400 p-4 text-center">
            Este proveedor aún no tiene productos asignados.
        </p>
    </div>

    <!-- Modals -->
    <ConfirmationModal :show="showConfirmDelete" @close="showConfirmDelete = false">
         <template #title>
            Desvincular Producto
        </template>
        <template #content>
            ¿Estás seguro de que deseas quitar este producto del proveedor?
        </template>
        <template #footer>
            <div class="flex space-x-2">
                <CancelButton @click="showConfirmDelete = false">Cancelar</CancelButton>
                <PrimaryButton @click="deleteProduct" class="!bg-red-600 hover:!bg-red-700">Eliminar</PrimaryButton>
            </div>
        </template>
    </ConfirmationModal>

</template>

<script>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import CancelButton from '@/Components/MyComponents/CancelButton.vue';
import { router } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

export default {
    data() {
        return {
            showAssignProductModal: false,
            showConfirmDelete: false,
            productToDeleteId: null,
            productToEdit: null,
        };
    },
    components: {
        PrimaryButton,
        ConfirmationModal,
        CancelButton,
    },
    props: {
        products: Array,
        catalog: Array,
        supplierId: Number,
    },
    methods: {
        openEditModal(product) {
            // Lógica para abrir modal de edición (próximamente)
            ElMessage.info('La función de editar producto asignado estará disponible pronto.');
        },
        confirmDelete(productId) {
            this.productToDeleteId = productId;
            this.showConfirmDelete = true;
        },
        deleteProduct() {
            // Lógica para desvincular producto (requiere una nueva ruta y método en el controlador)
            ElMessage.info('La función de desvincular producto estará disponible pronto.');
            this.showConfirmDelete = false;
        }
    }
}
</script>
