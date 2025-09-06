<template>
    <AppLayout :title="`Detalles de Compra OC-${purchase.id.toString().padStart(4, '0')}`">
        <!-- === ENCABEZADO === -->
        <header class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0 pb-4 mb-1">
            <div>
                <div class="flex space-x-2 items-center">
                    <Back :href="route('purchases.index')" />
                    <h1 class="dark:text-white font-bold text-2xl my-2">
                        <span class="text-gray-500 dark:text-gray-400">Orden de Compra:</span> OC-{{ purchase.id.toString().padStart(4, '0') }}
                    </h1>
                </div>
            </div>
            
            <div class="flex items-center space-x-2 dark:text-white">
                <el-tooltip v-if="purchase.status === 'Creada' && $page.props.auth.user.permissions.includes('authorize purchases')" content="Autorizar Compra" placement="top">
                    <button @click="updatePurchaseStatus('authorize', 'Autorizada')" class="size-9 flex items-center justify-center rounded-lg bg-green-300 hover:bg-green-400 dark:bg-green-800 dark:hover:bg-green-700 transition-colors">
                        <i class="fa-solid fa-check-double"></i>
                    </button>
                </el-tooltip>
                 <el-tooltip v-if="purchase.status === 'Autorizada'" content="Marcar como Enviada" placement="top">
                    <button @click="updatePurchaseStatus('mark-as-sent', 'Enviada')" class="size-9 flex items-center justify-center rounded-lg bg-blue-300 hover:bg-blue-400 dark:bg-blue-800 dark:hover:bg-blue-700 transition-colors">
                        <i class="fa-solid fa-truck-fast"></i>
                    </button>
                </el-tooltip>
                <el-tooltip v-if="purchase.status === 'Enviada'" content="Marcar como Recibida" placement="top">
                    <button @click="showReceivedModal = true" class="size-9 flex items-center justify-center rounded-lg bg-indigo-300 hover:bg-indigo-400 dark:bg-indigo-800 dark:hover:bg-indigo-700 transition-colors">
                        <i class="fa-solid fa-box-check"></i>
                    </button>
                </el-tooltip>

                <el-tooltip content="Imprimir Orden" placement="top">
                    <a :href="route('purchases.print', purchase.id)" target="_blank" class="size-9 flex items-center justify-center rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors">
                        <i class="fa-solid fa-print"></i>
                    </a>
                </el-tooltip>

                <el-tooltip :content="purchase.authorized_at ? 'No puedes editarla una vez autorizada' : 'Editar Orden'" placement="top">
                    <Link :href="purchase.authorized_at ? '' : route('purchases.edit', purchase.id)">
                        <button :disabled="!!purchase.authorized_at" 
                            class="size-9 flex items-center justify-center rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors disabled:cursor-not-allowed disabled:opacity-50">
                            <i class="fa-solid fa-pencil text-sm"></i>
                        </button>
                    </Link>
                </el-tooltip>
                
                <Dropdown align="right" width="48">
                    <template #trigger>
                        <button class="h-9 px-3 rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 flex items-center justify-center text-sm transition-colors">
                            Más Acciones <i class="fa-solid fa-chevron-down text-[10px] ml-2"></i>
                        </button>
                    </template>
                    <template #content>
                        <DropdownLink :href="route('purchases.create')" as="button">
                            Crear nueva Compra
                        </DropdownLink>
                        <div class="border-t border-gray-200 dark:border-gray-600" />
                        <DropdownLink @click="showConfirmModal = true" as="button" class="text-red-500 hover:!bg-red-50 dark:hover:!bg-red-900/50">
                            <i class="fa-regular fa-trash-can w-4 mr-2"></i> Eliminar Compra
                        </DropdownLink>
                    </template>
                </Dropdown>
            </div>
        </header>

        <!-- === CONTENIDO PRINCIPAL === -->
        <main class="grid grid-cols-1 lg:grid-cols-3 gap-5 mt-3 dark:text-white">
            <!-- COLUMNA IZQUIERDA -->
            <div class="lg:col-span-1 space-y-4">
                <!-- === STEPPER DE ESTADO === -->
                <Stepper :currentStatus="purchase.status" :steps="purchaseSteps" />
                
                <!-- Card de Información de la Compra -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Detalles de la Compra</h3>
                    <ul class="space-y-3 text-sm">
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Creado por:</span>
                            <span>{{ purchase.user?.name ?? 'N/A' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Fecha de Emisión:</span>
                            <span>{{ formatDateTime(purchase.emited_at) }}</span>
                        </li>
                         <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Autorizado por:</span>
                            <span>{{ purchase.authorizer?.name ?? 'N/A' }}</span>
                        </li>
                         <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Fecha de Autorización:</span>
                            <span>{{ formatDateTime(purchase.authorized_at) }}</span>
                        </li>
                         <li class="flex justify-between text-base font-bold">
                            <span class="text-gray-700 dark:text-gray-300">Monto Total:</span>
                            <span>{{ formatCurrency(purchase.total) }} {{ purchase.currency }}</span>
                        </li>
                    </ul>
                </div>
                
                <!-- Card de Información del Proveedor -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Información del Proveedor</h3>
                    <ul class="space-y-3 text-sm">
                        <li class="flex justify-between items-center">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Nombre:</span>
                            <span class="text-blue-500 hover:underline cursor-pointer">{{ purchase.supplier.name }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Contacto:</span>
                            <span>{{ purchase.contact?.name ?? 'N/A' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Dirección:</span>
                            <span>{{ purchase.supplier.address }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Teléfono:</span>
                            <span>{{ purchase.supplier.phone }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- COLUMNA DERECHA: PRODUCTOS -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-4 min-h-[300px]">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Productos de la Orden</h3>
                    <div v-if="purchase.items?.length" class="space-y-3 max-h-[65vh] overflow-auto custom-scroll p-1">
                        <ProductPurchaseCard v-for="item in purchase.items" :key="item.id" :purchase-item="item" />
                    </div>
                    <div v-else class="text-center text-gray-500 dark:text-gray-400 py-10">
                        <i class="fa-solid fa-boxes-stacked text-3xl mb-3"></i>
                        <p>Esta orden no tiene productos registrados.</p>
                    </div>
                </div>
            </div>
        </main>

        <!-- Modal de Confirmación para Eliminar -->
        <ConfirmationModal :show="showConfirmModal" @close="showConfirmModal = false">
            <template #title>
                Eliminar Orden de Compra OC-{{ purchase.id.toString().padStart(4, '0') }}
            </template>
            <template #content>
                ¿Estás seguro de que deseas eliminar permanentemente esta Orden? Esta acción no se puede deshacer.
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="showConfirmModal = false">Cancelar</CancelButton>
                    <SecondaryButton @click="deleteItem" class="!bg-red-600 hover:!bg-red-700">Eliminar</SecondaryButton>
                </div>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import Back from "@/Components/MyComponents/Back.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import ProductPurchaseCard from "@/Components/MyComponents/ProductPurchaseCard.vue";
import Stepper from "@/Components/MyComponents/Stepper.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import { ElMessage } from 'element-plus';
import { Link, router } from "@inertiajs/vue3";
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    name: 'PurchaseShow',
    components: {
        Link,
        Back,
        Stepper,
        Dropdown,
        AppLayout,
        DropdownLink,
        CancelButton,
        SecondaryButton,
        ConfirmationModal,
        ProductPurchaseCard,
    },
    props: {
        purchase: Object,
    },
    data() {
        return {
            showConfirmModal: false,
            purchaseSteps: ['Autorizada', 'Enviada', 'Recibida'],
        };
    },
    methods: {
        formatDateTime(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return format(date, "d 'de' MMMM, yyyy 'a las' HH:mm", { locale: es });
        },
        formatCurrency(value) {
            if (value === null || value === undefined) return '$0.00';
            return Number(value).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
        },
        async updatePurchaseStatus(action, newStatus) {
            let routeName;
            if (action === 'authorize') {
                routeName = 'purchases.authorize';
            } else {
                // You should create these routes and controller methods in your backend
                // For example: purchases.mark-as-sent, purchases.mark-as-received
                ElMessage.warning(`La acción "${action}" no está implementada en el backend.`);
                return;
            }

            try {
                await router.put(route(routeName, this.purchase.id), {}, {
                    onSuccess: () => {
                        this.purchase.status = newStatus;
                        ElMessage.success('Estatus de la compra actualizado');
                    },
                    onError: (errors) => {
                         ElMessage.error('Ocurrió un error al actualizar el estatus.');
                        console.error(errors);
                    }
                });
            } catch (err) {
                 ElMessage.error('Ocurrió un error al actualizar el estatus.');
                console.error(err);
            }
        },
        async deleteItem() {
            try {
                router.delete(route('purchases.destroy', this.purchase.id), {
                    onSuccess: () => {
                        ElMessage.success('Compra eliminada con éxito.');
                    }
                });
            } catch (err) {
                ElMessage.error('Ocurrió un error al eliminar la compra.');
                console.error(err);
            } finally {
                this.showConfirmModal = false;
            }
        },
    }
};
</script>
