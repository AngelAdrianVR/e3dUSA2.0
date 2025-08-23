<template>
    <AppLayout :title="`Cliente: ${branch.name}`">
        <!-- === ENCABEZADO (Sin cambios) === -->
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
                        <div class="border-t border-gray-200 dark:border-gray-600" />
                        <DropdownLink @click="showConfirmModal = true" as="button" class="text-red-500 hover:!bg-red-50 dark:hover:!bg-red-900/50">
                            <i class="fa-regular fa-trash-can w-4 mr-2"></i> Eliminar
                        </DropdownLink>
                    </template>
                </Dropdown>

                <Link :href="route('branches.index')"
                    class="flex-shrink-0 size-9 focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 flex items-center justify-center rounded-full bg-white dark:bg-slate-800/80 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-primary transition-all duration-200">
                    <i class="fa-solid fa-xmark"></i>
                </Link>
            </div>
        </header>

        <!-- === CONTENIDO PRINCIPAL === -->
        <main class="grid grid-cols-1 lg:grid-cols-3 gap-7 mt-5 dark:text-white">
            <!-- COLUMNA IZQUIERDA: INFORMACIÓN CLAVE Y CONTACTOS (Sin cambios) -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Card de Información Clave -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Información Clave</h3>
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
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Contactos</h3>
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
                        <!-- ============ PESTAÑA DE PRODUCTOS (MODIFICADA) ================== -->
                        <el-tab-pane name="products">
                             <template #label>
                                <div class="flex items-center">
                                    <i class="fa-solid fa-tags mr-2"></i>
                                    <span>Productos Asignados</span>
                                </div>
                            </template>
                            <div v-if="branch.products.length" class="space-y-4 mt-2 max-h-[60vh] overflow-y-auto pr-2">
                                <div v-for="product in branch.products" :key="product.id" class="bg-gray-100 dark:bg-slate-900/50 border border-gray-200 dark:border-gray-700 rounded-lg p-4 transition-all hover:shadow-md">
                                    <!-- ... Información del producto ... -->
                                    <div class="flex items-start space-x-4">
                                        <figure class="w-24 h-24 rounded-lg overflow-hidden flex-shrink-0 border dark:border-slate-600">
                                            <img v-if="product.media?.length" :src="product.media[0]?.original_url" :alt="product.name" class="w-full h-full object-cover">
                                            <div v-else class="w-full h-full bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-400 dark:text-slate-500">
                                                <i class="fa-solid fa-image text-3xl"></i>
                                            </div>
                                        </figure>
                                        <div class="flex-grow">
                                            <div class="flex justify-between items-start">
                                                <div>
                                                    <h4 @click="$inertia.visit(route('catalog-products.show', product.id))" class="font-bold text-lg text-gray-800 dark:text-gray-100 cursor-pointer hover:!text-blue-400">{{ product.name }}</h4>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ product.code }}</p>
                                                </div>
                                                <el-tooltip content="Actualizar precio especial" placement="top">
                                                    <button @click="openPriceModal(product)" class="size-8 flex-shrink-0 flex items-center justify-center rounded-md bg-blue-100 text-blue-600 hover:bg-blue-200 dark:bg-blue-900/50 dark:text-blue-400 dark:hover:bg-blue-900 transition-colors">
                                                        <i class="fa-solid fa-dollar-sign text-sm"></i>
                                                    </button>
                                                </el-tooltip>
                                            </div>
                                            <div class="text-sm mt-2 space-y-1">
                                                <p><strong class="font-semibold text-gray-600 dark:text-gray-300">Precio actual:</strong> ${{ currentPrice(product) }}</p>
                                                <p><strong class="font-semibold text-gray-600 dark:text-gray-300">Material:</strong> {{ product.material }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Historial de Precios Especiales -->
                                    <div v-if="product.price_history.length" class="mt-4">
                                        <el-collapse>
                                            <el-collapse-item>
                                                <template #title>
                                                    <span class="font-semibold text-sm text-blue-500">
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
                                                            <tr v-for="history in product.price_history" :key="history.id" class="bg-white dark:bg-slate-800 border-b dark:border-gray-600">
                                                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">${{ history.price }}</td>
                                                                <td class="px-4 py-2">{{ formatDate(history.valid_from) }}</td>
                                                                <td class="px-4 py-2 flex items-center justify-between">
                                                                    <span>{{ history.valid_to ? formatDate(history.valid_to) : 'Indefinido' }}</span>
                                                                    <!-- BOTÓN PARA FINALIZAR PRECIO ACTIVO -->
                                                                    <el-tooltip v-if="!history.valid_to" content="Finalizar vigencia de este precio" placement="top">
                                                                        <button @click="confirmCloseSpecialPrice(history.id)" class="size-7 flex items-center justify-center rounded-md text-red-500 bg-red-100 hover:bg-red-200 dark:bg-red-900/50 dark:hover:bg-red-900 transition-colors">
                                                                            <i class="fa-solid fa-calendar-xmark text-sm"></i>
                                                                        </button>
                                                                    </el-tooltip>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </el-collapse-item>
                                        </el-collapse>
                                    </div>
                                     <p v-else class="text-xs text-center text-gray-400 dark:text-gray-500 mt-4 border-t dark:border-gray-700 pt-2">
                                        No hay precios especiales registrados para este producto.
                                    </p>
                                </div>
                            </div>
                        </el-tab-pane>
                        <!-- ============ PESTAÑA DE COTIZACIONES ================== -->
                        <el-tab-pane label="Cotizaciones" name="quotes">
                            <LoadingIsoLogo v-if="loadingQuotes" />
                            <div v-else class="rounded-lg overflow-hidden border dark:border-gray-700">
                                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-slate-900 dark:text-gray-300">
                                        <tr>
                                            <th scope="col" class="px-2 py-3">Folio</th>
                                            <th scope="col" class="px-2 py-3">Creado por</th>
                                            <th scope="col" class="px-2 py-3">Total</th>
                                            <th scope="col" class="px-2 py-3">Autorizado</th>
                                            <th scope="col" class="px-2 py-3">Estatus</th>
                                            <th scope="col" class="px-2 py-3">Creado el</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Iterar sobre las cotizaciones cuando las tengas -->
                                        <tr v-for="quote in quotes" :key="quote.id" @click="openQuote(quote.id)"
                                            class="bg-white dark:bg-slate-800/80 border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer">
                                            <td class="px-5 py-4 font-medium text-gray-900 dark:text-white">COT-{{ String(quote.id).padStart(3, '0') }}</td>
                                            <td class="px-5 py-4">{{ quote.user?.name ?? 'Sin usuario asignado' }}</td>
                                            <td class="px-5 py-4">${{ quote.total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") }} {{ quote.currency }}</td>
                                            <td class="px-5 py-4 text-center">
                                                <i v-if="quote.authorized_at" class="fa-solid fa-check-double text-green-500" title="Autorizado"></i>
                                                <p v-else>No autorizado</p>
                                            </td>
                                            <td class="px-5 py-4">
                                                <el-tooltip placement="top">
                                                    <template #content>
                                                        <p v-html="getStatusTooltip(quote)"></p>
                                                    </template>
                                                    <span v-html="getStatusIcon(quote.status)" class="text-sm mr-2"></span>
                                                </el-tooltip>
                                            </td>
                                            <td class="px-5 py-4 text-gray-500 dark:text-gray-400">{{ formatDate(quote.created_at) }}</td>
                                        </tr>
                                         <!-- Mensaje si no hay cotizaciones -->
                                        <tr v-if="!quotes.length">
                                            <td colspan="6" class="text-center py-10 text-gray-500 dark:text-gray-400">
                                                <div class="flex flex-col items-center">
                                                    <i class="fa-regular fa-file-lines text-4xl mb-3"></i>
                                                    <span>No hay cotizaciones para mostrar.</span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
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

        <!-- MODAL PARA ACTUALIZAR PRECIO -->
        <ConfirmationModal :show="showPriceModal" @close="showPriceModal = false">
            <template #title>
                Actualizar precio de <span class="text-blue-500">{{ productForUpdate?.name }}</span>
            </template>
            <template #content>
                <div class="space-y-4 text-sm dark:text-gray-300">
                    <p>El precio de referencia actual es <strong class="font-semibold">${{ priceForm.current_base_price }}</strong>. El nuevo precio no puede ser inferior al actual y el aumento debe ser de al menos 4%.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                        <div>
                            <label class="font-semibold">Aumento en porcentaje*</label>
                             <el-input v-model="priceForm.percentage" @input="updatePriceFromPercentage" placeholder="Ej. 5" class="mt-1">
                                <template #append>%</template>
                            </el-input>
                        </div>
                         <div>
                            <label class="font-semibold">Precio nuevo en moneda*</label>
                             <el-input v-model="priceForm.amount" @input="updatePriceFromAmount" placeholder="Ej. 44.10" class="mt-1">
                                <template #prepend>$</template>
                            </el-input>
                        </div>
                    </div>
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="font-semibold">Moneda*</label>
                            <el-select v-model="priceForm.currency" placeholder="Moneda" :teleported="false" class="!w-full mt-1">
                                <el-option label="MXN" value="MXN" />
                                <el-option label="USD" value="USD" />
                            </el-select>
                        </div>
                        <div>
                            <label class="font-semibold">Fecha de cambio (Vigente desde)*</label>
                            <el-date-picker v-model="priceForm.valid_from" :teleported="false" type="date" placeholder="Selecciona una fecha" class="!w-full mt-1" />
                        </div>
                    </div>
                    <!-- MENSAJE DE ERROR -->
                    <div v-if="priceForm.amount && isPriceInvalid" class="text-red-500 text-xs mt-1 p-2 bg-red-50 dark:bg-red-900/40 rounded-md">
                        <i class="fa-solid fa-circle-exclamation mr-1"></i>
                        El precio debe ser mayor o igual a ${{ priceForm.min_allowed_price.toFixed(2) }} (aumento mínimo del 4%).
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="showPriceModal = false">Cancelar</CancelButton>
                    <PrimaryButton @click="submitNewPrice" :disabled="isPriceInvalid" class="!bg-blue-600 hover:!bg-blue-700 disabled:!bg-blue-300 dark:disabled:!bg-slate-600">Actualizar precio</PrimaryButton>
                </div>
            </template>
        </ConfirmationModal>

        <!-- Confirmación para Finalizar Precio -->
        <ConfirmationModal :show="showClosePriceConfirmModal" @close="showClosePriceConfirmModal = false">
            <template #title>
                Finalizar Precio Especial
            </template>
            <template #content>
                ¿Estás seguro de que deseas finalizar la vigencia de este precio especial? El producto volverá a su precio base para este cliente.
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="showClosePriceConfirmModal = false">Cancelar</CancelButton>
                    <PrimaryButton @click="closeSpecialPrice" class="!bg-red-600 hover:!bg-red-700">Sí, finalizar</PrimaryButton>
                </div>
            </template>
        </ConfirmationModal>

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
import LoadingIsoLogo from "@/Components/MyComponents/LoadingIsoLogo.vue";
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
            // --- DATOS PARA EL MODAL DE PRECIO ---
            showPriceModal: false,
            productForUpdate: null,
            priceForm: {
                amount: null,
                percentage: null,
                currency: 'MXN',
                valid_from: new Date(),
                current_base_price: 0,
                min_allowed_price: 0,
            },
            showClosePriceConfirmModal: false,
            priceHistoryToClose: null,

            // Cotizaciones
            loadingQuotes: false,
            quotes: [],
            statusMap: {
                'Aceptada': { icon: '<i class="fa-solid fa-circle-check text-green-500"></i>', text: 'Aceptada por el cliente' },
                'Rechazada': { icon: '<i class="fa-solid fa-circle-xmark text-red-500"></i>', text: 'Rechazada por el cliente' },
                'Esperando respuesta': { icon: '<i class="fa-solid fa-hourglass-half text-amber-500"></i>', text: 'Esperando respuesta del cliente' },
            }
        };
    },
    components: {
        Link,
        Dropdown,
        AppLayout,
        DropdownLink,
        CancelButton,
        PrimaryButton,
        LoadingIsoLogo,
        SecondaryButton,
        ConfirmationModal,
    },
    props: {
        branch: Object,
        branches: Array, // Necesario para el buscador
    },
    computed: {
        isPriceInvalid() {
            if (!this.priceForm.amount || this.priceForm.amount <= 0) return true;
            return this.priceForm.amount < this.priceForm.min_allowed_price;
        }
    },
    methods: {
        // --- metodos de cotizaciones ---
        openQuote(id) {
            const url = route('quotes.show', id);
            window.open(url, '_blank'); // abre en una nueva pestaña
        },
        getStatusIcon(status) {
            return this.statusMap[status]?.icon || '<i class="fa-solid fa-circle-question text-gray-400"></i>';
        },
        getStatusTooltip(row) {
            let baseText = this.statusMap[row.status]?.text || 'Estatus desconocido';
            if (row.customer_responded_at) {
                baseText += `<br>Fecha: ${this.formatDate(row.customer_responded_at)}`;
            }
            if (row.status === 'Rechazada' && row.rejection_reason) {
                baseText += `<br>Motivo: <b>${row.rejection_reason}</b>`;
            }
            return baseText;
        },

        getPrimaryDetail(contact, type) {
            const detail = contact.details.find(d => d.type === type);
            return detail ? detail.value : 'No disponible';
        },

        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        },
        
        currentPrice(product) {
            // si tiene precio especial y esta vigente, lo toma como precio actual, si no, toma el precio base
            const specialPrice = product.price_history?.[0];
            
            if (specialPrice && (!specialPrice.valid_to || specialPrice.valid_to === null)) {
                return specialPrice.price;
            }
            return product.base_price;
        },

        openPriceModal(product) {
            const basePrice = product.price_history?.[0]?.price ?? product.base_price;
            
            this.productForUpdate = product;
            this.priceForm = {
                amount: null,
                percentage: null,
                currency: 'MXN',
                valid_from: new Date(),
                current_base_price: basePrice,
                min_allowed_price: basePrice * 1.04, // Regla de aumento del 4%
            };
            this.showPriceModal = true;
        },

        updatePriceFromAmount() {
            if (this.priceForm.amount && this.priceForm.current_base_price > 0) {
                const percentage = ((this.priceForm.amount / this.priceForm.current_base_price) - 1) * 100;
                this.priceForm.percentage = percentage.toFixed(2);
            } else {
                this.priceForm.percentage = null;
            }
        },

        updatePriceFromPercentage() {
            if (this.priceForm.percentage !== null && this.priceForm.percentage !== '') {
                const newAmount = this.priceForm.current_base_price * (1 + (this.priceForm.percentage / 100));
                this.priceForm.amount = newAmount.toFixed(2);
            } else {
                this.priceForm.amount = null;
            }
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

        // --- MÉTODOS PARA FINALIZAR PRECIO ---
        confirmCloseSpecialPrice(historyId) {
            this.priceHistoryToClose = historyId;
            this.showClosePriceConfirmModal = true;
        },

        async closeSpecialPrice() {
            if (!this.priceHistoryToClose) return;
            try {
                // Usamos PATCH para indicar una actualización parcial del recurso
                const response = await axios.patch(route('branch-price-history.close', this.priceHistoryToClose));
                if (response.status === 200) {
                    ElMessage.success('El precio especial ha sido finalizado.');
                    this.$inertia.reload({ preserveScroll: true });
                }
            } catch (error) {
                console.error("Error al finalizar el precio:", error);
                ElMessage.error(error.response?.data?.message || 'No se pudo finalizar el precio.');
            } finally {
                this.showClosePriceConfirmModal = false;
                this.priceHistoryToClose = null;
            }
        },

        async submitNewPrice() {
            if (this.isPriceInvalid) {
                ElMessage.error('El precio ingresado no es válido o es menor al permitido.');
                return;
            }

            try {
                // Definimos la ruta y los parámetros para la petición
                const routeName = 'branches.products.price.store';
                const routeParams = { branch: this.branch.id, product: this.productForUpdate.id };
                
                const response = await axios.post(route(routeName, routeParams), this.priceForm);

                if (response.status === 200) {
                    ElMessage.success('Precio actualizado correctamente.');
                    this.showPriceModal = false;
                    // Recarga los datos de la página para reflejar el cambio
                    this.$inertia.reload({ preserveScroll: true });
                }
            } catch (error) {
                console.error("Error al actualizar el precio:", error);
                ElMessage.error(error.response?.data?.message || 'Ocurrió un error al guardar el precio.');
            }
        },

        // recupera todas las cotiszaciones del cliente. peticion as+incrona para no relentizar la  renderización de la vista
        async fetchQuotes() {
            try {
                const response = await axios.get(route('quotes.branch-quotes', this.branch.id));
                
                if ( response.status === 200 ) {
                    this.quotes = response.data
                }
            } catch (error) {
                console.error("Error al cargar cotizaciones:", error);
                ElMessage.error('Ocurrió un error al cargar cotizaciones.');
            } finally {
                this.loadingQuotes = false;
            }
        },
    },
    watch: {
        'branch.id'(newId) {
            this.selectedBranch = newId;
            // Cambia a la pestaña general cuando se selecciona un nuevo cliente
            this.activeTab = 'general';
        }
    },
    mounted() {
        this.fetchQuotes();
    }
};
</script>

<style>
/* Personalización para que las pestañas se vean más limpias */
.el-tabs__header {
    margin-bottom: 24px !important;
}
</style>
