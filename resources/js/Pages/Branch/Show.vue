<template>
    <AppLayout :title="`Cliente: ${branch.name}`">
        <!-- === ENCABEZADO === -->
        <h1 class="dark:text-white font-bold text-2xl mb-4">{{ branch.name }}</h1>
        <header class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0 pb-4 border-b dark:border-gray-500">
            <div class="w-full lg:w-1/3">
                <el-select @change="$inertia.get(route('branches.show', selectedBranch))"
                    v-model="selectedBranch" filterable placeholder="Buscar otro cliente..."
                    class="!w-full"
                    no-data-text="No hay clientes registrados" no-match-text="No se encontraron coincidencias">
                    <el-option v-for="item in branches" :key="item.id"
                        :label="item.name" :value="item.id" />
                </el-select>
            </div>
            <div class="flex items-center space-x-2 dark:text-white">
                <el-tooltip content="Editar Cliente" placement="top">
                    <Link :href="route('branches.edit', branch.id)">
                        <button class="size-9 flex items-center justify-center rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors">
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
                        <DropdownLink :href="route('branches.create')">
                            <i class="fa-solid fa-plus w-4 mr-2"></i> Nuevo Cliente
                        </DropdownLink>
                        <div class="border-t border-gray-200 dark:border-slate-700" />
                        <DropdownLink @click="showConfirmModal = true" as="button" class="text-red-500 hover:!bg-red-50 dark:hover:!bg-red-900/50">
                            <i class="fa-regular fa-trash-can w-4 mr-2"></i> Eliminar
                        </DropdownLink>
                    </template>
                </Dropdown>

                <Link :href="route('branches.index')"
                    class="flex-shrink-0 size-9 focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 flex items-center justify-center rounded-full bg-white dark:bg-slate-800/80 border border-gray-200 dark:border-slate-700 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-primary transition-all duration-200">
                    <i class="fa-solid fa-xmark"></i>
                </Link>
            </div>
        </header>

        <!-- === CONTENIDO PRINCIPAL === -->
        <main class="grid grid-cols-1 lg:grid-cols-3 gap-7 mt-5 dark:text-white">
            <!-- COLUMNA IZQUIERDA: INFORMACIÓN CLAVE Y CONTACTOS -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Card de Información Clave -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-6">
                    <h3 class="text-lg font-semibold border-b dark:border-slate-700 pb-3 mb-4">Información Clave</h3>
                    <ul class="space-y-3 text-sm">
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Estatus:</span>
                            <el-tag :type="branch.status === 'Cliente' ? 'success' : 'info'" size="small">{{ branch.status }}</el-tag>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Vendedor:</span>
                            <span>{{ branch.account_manager?.name ?? 'No asignado' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Matriz:</span>
                            <span>{{ branch.parent?.name ?? 'N/A' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">RFC:</span>
                            <span>{{ branch.rfc ?? 'No especificado' }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Card de Contactos -->
                <!-- Card de Contactos -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-6">
                    <h3 class="text-lg font-semibold border-b dark:border-slate-700 pb-3 mb-4">Contactos</h3>
                    <div v-if="branch.contacts.length" class="space-y-4">
                        <div v-for="contact in branch.contacts" :key="contact.id">
                            <p class="font-semibold">{{ contact.name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ contact.charge }}</p>
                            <div class="text-sm mt-1 space-y-1">
                                <p><i class="fa-solid fa-envelope mr-2 text-gray-400"></i> {{ getPrimaryDetail(contact, 'Correo') }}</p>
                                <p><i class="fa-solid fa-phone mr-2 text-gray-400"></i> {{ getPrimaryDetail(contact, 'Teléfono') }}</p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">No hay contactos registrados.</p>
                </div>
            </div>

            <!-- COLUMNA DERECHA: PESTAÑAS DE INFORMACIÓN -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg">
                        <el-tabs v-model="activeTab" class="p-6">
                        <el-tab-pane label="Información General" name="general">
                            <ul class="space-y-4 text-sm mt-2">
                                <li><strong class="font-semibold w-32 inline-block">Dirección:</strong> {{ branch.address ?? 'No especificada' }}</li>
                                <li><strong class="font-semibold w-32 inline-block">Código Postal:</strong> {{ branch.post_code ?? 'N/A' }}</li>
                                <li><strong class="font-semibold w-32 inline-block">Nos conoció por:</strong> {{ branch.meet_way ?? 'No especificado' }}</li>
                                <li><strong class="font-semibold w-32 inline-block">Notas:</strong> {{ branch.important_notes ?? 'No hay notas.' }}</li>
                            </ul>
                        </el-tab-pane>
                        <!-- ============ PESTAÑA DE PRODUCTOS ================== -->
                        <el-tab-pane name="products">
                             <template #label>
                                <div class="flex items-center">
                                    <i class="fa-solid fa-tags mr-2"></i>
                                    <span>Productos Asignados</span>
                                </div>
                            </template>
                            <div v-if="branch.products.length" class="space-y-4 mt-2 max-h-[60vh] overflow-y-auto pr-2">
                                <div v-for="product in branch.products" :key="product.id" class="bg-gray-100 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-4 transition-all hover:shadow-md">
                                    <div class="flex items-start space-x-4">
                                        <!-- Imagen del Producto -->
                                        <figure class="w-24 h-24 rounded-lg overflow-hidden flex-shrink-0 border dark:border-slate-600">
                                            <img v-if="product.media?.length" :src="product.media[0]?.original_url" :alt="product.name" class="w-full h-full object-cover">
                                            <div v-else class="w-full h-full bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-400 dark:text-slate-500">
                                                <i class="fa-solid fa-image text-3xl"></i>
                                            </div>
                                        </figure>
                                        <!-- Información del Producto -->
                                        <div class="flex-grow">
                                            <h4 class="font-bold text-lg text-gray-800 dark:text-gray-100">{{ product.name }}</h4>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ product.code }}</p>
                                            <div class="text-sm mt-2 space-y-1">
                                                <p><strong class="font-semibold text-gray-600 dark:text-gray-300">Precio Base:</strong> ${{ product.base_price?.toFixed(2) }}</p>
                                                <p><strong class="font-semibold text-gray-600 dark:text-gray-300">Material:</strong> {{ product.material }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Historial de Precios Especiales -->
                                    <div v-if="product.price_history.length" class="mt-4">
                                        <el-collapse>
                                            <el-collapse-item>
                                                <template #title>
                                                    <span class="font-semibold text-sm text-primary">
                                                        <i class="fa-solid fa-clock-rotate-left mr-2"></i> Ver Historial de Precios Especiales ({{ product.price_history.length }})
                                                    </span>
                                                </template>
                                                <div class="p-2">
                                                    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-slate-700 dark:text-gray-300">
                                                            <tr>
                                                                <th scope="col" class="px-4 py-2">Precio Especial</th>
                                                                <th scope="col" class="px-4 py-2">Vigente Desde</th>
                                                                <th scope="col" class="px-4 py-2">Vigente Hasta</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr v-for="history in product.price_history" :key="history.id" class="bg-white dark:bg-slate-800 border-b dark:border-slate-700">
                                                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">${{ history.price }}</td>
                                                                <td class="px-4 py-2">{{ formatDate(history.valid_from) }}</td>
                                                                <td class="px-4 py-2">{{ history.valid_to ? formatDate(history.valid_to) : 'Indefinido' }}</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </el-collapse-item>
                                        </el-collapse>
                                    </div>
                                     <p v-else class="text-xs text-center text-gray-400 dark:text-gray-500 mt-4 border-t dark:border-slate-700 pt-2">
                                        No hay precios especiales registrados para este producto.
                                    </p>
                                </div>
                            </div>
                            <div v-else class="text-center py-8">
                                <p class="text-gray-500 dark:text-gray-400">
                                    <i class="fa-solid fa-box-open text-3xl mb-2"></i>
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Este cliente no tiene productos asignados.</p>
                            </div>
                        </el-tab-pane>
                        <el-tab-pane label="Cotizaciones" name="quotes">
                            <p class="text-sm text-gray-500 dark:text-gray-400 p-4 text-center">Próximamente: Historial de cotizaciones.</p>
                        </el-tab-pane>
                        <el-tab-pane label="Ventas" name="sales">
                            <p class="text-sm text-gray-500 dark:text-gray-400 p-4 text-center">Próximamente: Historial de ventas.</p>
                        </el-tab-pane>
                        <el-tab-pane label="Proyectos" name="projects">
                            <p class="text-sm text-gray-500 dark:text-gray-400 p-4 text-center">Próximamente: Proyectos relacionados.</p>
                        </el-tab-pane>
                    </el-tabs>
                </div>
            </div>
        </main>

        <!-- Modal de Confirmación para Eliminar -->
        <ConfirmationModal :show="showConfirmModal" @close="showConfirmModal = false">
            <template #title>
                Eliminar Cliente
            </template>
            <template #content>
                ¿Estás seguro de que deseas eliminar permanentemente este cliente? Todos los datos relacionados (contactos, precios, etc.) se perderán. Esta acción no se puede deshacer.
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="showConfirmModal = false">Cancelar</CancelButton>
                    <PrimaryButton @click="deleteItem" class="!bg-red-600 hover:!bg-red-700">Eliminar</PrimaryButton>
                </div>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import { Link } from "@inertiajs/vue3";
import { ElMessage } from 'element-plus';
import axios from 'axios';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    data() {
        return {
            activeTab: 'general',
            selectedBranch: this.branch.id,
            showConfirmModal: false,
        };
    },
    components: {
        Link,
        Dropdown,
        AppLayout,
        CancelButton,
        DropdownLink,
        PrimaryButton,
        SecondaryButton,
        ConfirmationModal,
    },
    props: {
        branch: Object,
        branches: Array, // Necesario para el buscador
    },
    methods: {
        getPrimaryDetail(contact, type) {
            const detail = contact.details.find(d => d.type === type);
            return detail ? detail.value : 'No disponible';
        },
        async deleteItem() {
            try {
                const response = await axios.delete(route('branches.destroy', this.branch.id));
                if (response.status === 200) {
                    ElMessage.success(response.data.message || 'Cliente eliminado con éxito.');
                    this.$inertia.visit(route('branches.index'));
                }
            } catch (err) {
                ElMessage.error('Ocurrió un error al eliminar el cliente.');
                console.error(err);
            } finally {
                this.showConfirmModal = false;
            }
        },
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        }
    },
    watch: {
        'branch.id'(newId) {
            this.selectedBranch = newId;
            // Cambia a la pestaña general cuando se selecciona un nuevo cliente
            this.activeTab = 'general';
        }
    }
};
</script>

<style>
/* Personalización para que las pestañas se vean más limpias */
.el-tabs__header {
    margin-bottom: 24px !important;
}
</style>
