<template>
    <AppLayout :title="`Detalles de la Factura ${invoice.folio}`">
        <!-- Panel Flotante de Notas -->
        <BranchNotes v-if="invoice.sale.branch?.id" :branch-id="invoice.sale.branch?.id" />
        <!-- === ENCABEZADO === -->
        <header class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0 pb-4 mb-1">
            <div>
                <div class="flex space-x-2 items-center">
                    <Back :href="route('invoices.index')" />
                    <h1 class="dark:text-white font-bold text-2xl my-2">
                        <span class="text-gray-500 dark:text-gray-400">Factura:</span> {{ invoice.folio }}
                    </h1>
                </div>
            </div>

            <div class="flex items-center space-x-2 dark:text-white">
                <!-- Botón para registrar pago (si es PDD) -->
                <el-button
                    v-if="!['Pagada', 'Cancelada'].includes(invoice.status)"
                    @click="openPaymentModal" type="success">
                    <i class="fa-solid fa-hand-holding-dollar mr-2"></i> Registrar Pago
                </el-button>

                <!-- <el-tooltip content="Imprimir Factura (Próximamente)" placement="top">
                    <button
                        class="size-9 flex items-center justify-center rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled>
                        <i class="fa-solid fa-print"></i>
                    </button>
                </el-tooltip> -->

                <el-tooltip v-if="$page.props.auth.user.permissions.includes('Editar facturas') && invoice.status !== 'Cancelada'"
                    :content="(paidAmount > 0) ? 'No puedes editarla teniendo pagos' : 'Editar factura'" placement="top">
                    <Link :href="(paidAmount > 0) ? '' : route('invoices.edit', invoice.id)">
                    <button :disabled="(paidAmount > 0)"
                        class="size-9 flex items-center justify-center rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors disabled:cursor-not-allowed disabled:opacity-50">
                        <i class="fa-solid fa-pencil text-sm"></i>
                    </button>
                    </Link>
                </el-tooltip>

                <Dropdown v-if="!['Pagada', 'Cancelada'].includes(invoice.status)" align="right" width="48">
                    <template #trigger>
                        <button
                            class="h-9 px-3 rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 flex items-center justify-center text-sm transition-colors">
                            Más Acciones <i class="fa-solid fa-chevron-down text-[10px] ml-2"></i>
                        </button>
                    </template>
                    <template #content>
                        <DropdownLink v-if="!['Pagada', 'Cancelada'].includes(invoice.status)" @click="openAttachFileModal" as="button">
                            <i class="fa-solid fa-paperclip w-4 mr-2"></i> Adjuntar archivo
                        </DropdownLink>
                        <DropdownLink v-if="$page.props.auth.user.permissions.includes('Cancelar facturas') && !['Pagada', 'Cancelada', 'Parcialmente pagada'].includes(invoice.status)"
                            @click="confirmCancelInvoice(invoice)" as="button">
                            <i class="fa-solid fa-ban w-4 mr-2"></i> Cancelar factura
                        </DropdownLink>
                        <div class="border-t border-gray-200 dark:border-gray-600" />
                        <DropdownLink v-if="$page.props.auth.user.permissions.includes('Eliminar facturas')"
                            @click="confirmDeleteInvoice(invoice)" as="button"
                            class="text-red-500 hover:!bg-red-50 dark:hover:!bg-red-900/50">
                            <i class="fa-regular fa-trash-can w-4 mr-2"></i> Eliminar factura
                        </DropdownLink>
                    </template>
                </Dropdown>
            </div>
        </header>

        <!-- === CONTENIDO PRINCIPAL === -->
        <main class="grid grid-cols-1 lg:grid-cols-3 gap-5 mt-3 dark:text-white">
            <!-- COLUMNA IZQUIERDA -->
            <div class="lg:col-span-1 space-y-3">
                <!-- Card de Información de la Factura -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Detalles de la Factura</h3>
                    <ul class="space-y-3 text-sm">
                        <li class="flex justify-between items-center">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Estatus:</span>
                            <el-tag :type="getStatusTag(invoice.status)" disable-transitions>
                                {{ invoice.status }}
                            </el-tag>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">OV Relacionada:</span>
                             <Link :href="route('sales.show', invoice.sale.id)" class="text-blue-500 hover:underline cursor-pointer">
                                OV-{{ invoice.sale.id.toString().padStart(4, '0') }}
                            </Link>
                        </li>
                        <li class="flex justify-between items-center">
                                <span class="font-semibold text-gray-600 dark:text-gray-400">Cliente:</span>

                                <!-- Tooltip de cliente -->
                                <el-tooltip placement="right" effect="light" raw-content>
                                    <template #content>
                                        <div class="w-72 bg-white/90 dark:bg-slate-800/90 backdrop-blur-md rounded-xl shadow-xl p-4 text-sm">
                                        <!-- Header -->
                                        <div class="flex justify-between items-center border-b pb-2 mb-3">
                                            <h4 class="font-bold text-lg text-primary dark:text-sky-400">
                                            {{ invoice.sale.branch?.name }}
                                            </h4>
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-600 dark:bg-sky-900 dark:text-sky-300">
                                            {{ invoice.sale.branch?.status ?? 'N/A' }}
                                            </span>
                                        </div>

                                        <!-- Datos principales -->
                                        <div class="space-y-1 text-gray-700 dark:text-gray-300">
                                            <p><strong class="font-semibold">RFC:</strong> {{ invoice.sale.branch?.rfc ?? 'N/A' }}</p>
                                            <p><strong class="font-semibold">Dirección:</strong> {{ invoice.sale.branch?.address ?? 'N/A' }}</p>
                                            <p><strong class="font-semibold">C.P.:</strong> {{ invoice.sale.branch?.post_code ?? 'N/A' }}</p>
                                            <p><strong class="font-semibold">Medio de contacto:</strong> {{ invoice.sale.branch?.meet_way ?? 'N/A' }}</p>
                                            <p><strong class="font-semibold">Última compra:</strong> {{ formatRelative(invoice.sale.branch?.last_purchase_date) }}</p>
                                        </div>

                                        <!-- Footer -->
                                        <div class="mt-4 pt-2 border-t flex justify-between items-center">
                                            <Link :href="route('branches.show', invoice.sale.branch?.id)">
                                            <SecondaryButton class="!py-1.5 !px-3 !text-xs flex items-center gap-1">
                                                <i class="fa-solid fa-eye"></i> Ver Cliente
                                            </SecondaryButton>
                                            </Link>
                                            <span class="text-[10px] italic text-gray-400">Creado: {{ invoice.sale.branch?.created_at?.split('T')[0] }}</span>
                                        </div>
                                        </div>
                                    </template>

                                    <!-- Nombre clickable -->
                                    <span class="text-blue-500 hover:underline cursor-pointer">
                                        {{ invoice.sale.branch?.name ?? 'N/A' }}
                                    </span>
                                </el-tooltip>
                            </li>
                        <li v-if="invoice.sale.contact" class="flex justify-between">
                           <span class="font-semibold text-gray-600 dark:text-gray-400">Contacto:</span>
                           <span>{{ invoice.sale.contact.name ?? 'N/A' }}</span>
                       </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">No. de Factura:</span>
                            <span>{{ invoice.installment_number }} de {{ invoice.total_installments }}</span>
                        </li>
                         <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Creado por:</span>
                            <span>{{ invoice.user?.name ?? 'N/A' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Fecha de Emisión:</span>
                            <span>{{ formatDate(invoice.issue_date) }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Fecha de Vencimiento:</span>
                            <span>{{ formatDate(invoice.due_date) }}</span>
                        </li>
                         <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Forma de pago:</span>
                            <span>{{ invoice.payment_option }}</span>
                        </li>
                         <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Método de pago:</span>
                            <span>{{ invoice.payment_method }}</span>
                        </li>
                        <li class="flex justify-between text-base font-bold pt-3 border-t dark:border-gray-600">
                            <span class="text-gray-700 dark:text-gray-300">Monto Total:</span>
                            <span>{{ formatCurrency(invoice.amount) }} {{ invoice.currency }}</span>
                        </li>
                         <li class="flex justify-between text-base font-bold text-green-600 dark:text-green-400">
                            <span >Monto Pagado:</span>
                            <span>{{ formatCurrency(paidAmount) }} {{ invoice.currency }}</span>
                        </li>
                         <li class="flex justify-between text-base font-bold text-amber-600 dark:text-amber-400">
                            <span >Monto Pendiente:</span>
                            <span>{{ formatCurrency(pendingAmount) }} {{ invoice.currency }}</span>
                        </li>
                    </ul>
                </div>

                 <!-- Card de Facturas Relacionadas -->
                <div v-if="invoice.sale.invoices?.length > 1" class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Otras Facturas de la OV</h3>
                    <ul class="space-y-2">
                        <li v-for="relatedInvoice in invoice.sale.invoices" :key="relatedInvoice.id">
                            <Link :href="route('invoices.show', relatedInvoice.id)"
                                class="flex justify-between items-center p-2 rounded-md transition-colors duration-200"
                                :class="{
                                    'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 font-bold': relatedInvoice.id === invoice.id,
                                    'hover:bg-gray-100 dark:hover:bg-slate-700': relatedInvoice.id !== invoice.id
                                }">
                                <div class="text-sm">
                                    <p>{{ relatedInvoice.folio }}</p>
                                    <p class="text-xs text-gray-500">{{ relatedInvoice.installment_number }} de {{ relatedInvoice.total_installments }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold">{{ formatCurrency(relatedInvoice.amount) }}</p>
                                    <el-tag :type="getStatusTag(relatedInvoice.status)" size="small" disable-transitions>
                                        {{ relatedInvoice.status }}
                                    </el-tag>
                                </div>
                            </Link>
                        </li>
                    </ul>
                </div>

                <!-- Card de Archivos adjuntos -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <div class="flex justify-between items-center border-b dark:border-gray-600 pb-3 mb-4">
                        <h3 class="text-lg font-semibold">Archivos Adjuntos</h3>
                        <el-button @click="openAttachFileModal" type="primary" plain circle>
                            <i class="fa-solid fa-plus"></i>
                        </el-button>
                    </div>
                    <div v-if="invoice.media?.length" class="grid grid-cols-2 gap-3 col-span-full mb-3">
                        <FileView v-for="file in invoice.media" :key="file.id" :file="file" :deletable="true" @delete-file="deleteFile($event)" />
                    </div>
                     <p v-else class="text-sm text-gray-500 text-center py-4">No hay archivos adjuntos.</p>
                </div>
            </div>

            <!-- COLUMNA DERECHA: PAGOS -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-4 min-h-[300px]">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Historial de Pagos</h3>
                    <div v-if="invoice.payments?.length" class="space-y-3 max-h-[65vh] overflow-auto">
                       <el-table :data="invoice.payments" style="width: 100%" stripe class="dark:!bg-slate-800/50 dark:!text-gray-300">
                           <el-table-column prop="payment_date" label="Fecha de Pago" width="180">
                               <template #default="scope">{{ formatDate(scope.row.payment_date) }}</template>
                           </el-table-column>
                           <el-table-column prop="amount" label="Monto" width="120">
                               <template #default="scope">{{ formatCurrency(scope.row.amount) }}</template>
                           </el-table-column>
                            <el-table-column prop="payment_method" label="Método de Pago" />
                           <el-table-column prop="fiels" label="Archivos">
                               <template #default="scope">
                                    <div v-if="scope.row.media?.length" class="">
                                        <FileView v-for="file in scope.row.media" :key="file.id" :file="file" />
                                    </div>
                               </template>
                           </el-table-column>
                           <el-table-column prop="notes" label="Notas" />
                       </el-table>
                    </div>
                    <div v-else class="text-center text-gray-500 dark:text-gray-400 py-10">
                        <i class="fa-solid fa-file-invoice-dollar text-3xl mb-3"></i>
                        <p>No se han registrado pagos para esta factura.</p>
                    </div>
                </div>
            </div>
        </main>

        <!-- Modal para Adjuntar Archivos -->
        <el-dialog v-model="attachFileModalVisible" title="Adjuntar Archivos a la Factura" width="500px">
            <form @submit.prevent="submitAttachFile">
                <el-upload
                    ref="mediaUploaderRef"
                    class="upload-demo"
                    drag
                    multiple
                    :auto-upload="false"
                    @change="handleFileChange"
                    :on-remove="handleFileChange"
                >
                    <i class="fa-solid fa-cloud-arrow-up text-5xl text-gray-400"></i>
                    <div class="el-upload__text">
                        Arrastra archivos aquí o <em>haz clic para subir</em>
                    </div>
                    <template #tip>
                        <div class="el-upload__tip">
                            Archivos JPG, PNG, PDF, XML con un tamaño menor de 4MB
                        </div>
                    </template>
                </el-upload>
            </form>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="attachFileModalVisible = false">Cancelar</el-button>
                    <el-button type="primary" @click="submitAttachFile" :loading="mediaForm.processing">
                        Confirmar
                    </el-button>
                </span>
            </template>
        </el-dialog>

        <!-- Modal para Registrar Pago -->
        <el-dialog v-model="paymentModalVisible" title="Registrar Pago de Factura" width="500px">
            <div>
                <p class="mb-2"><strong>Folio:</strong> {{ invoice.folio }}</p>
                <p class="mb-2"><strong>Cliente:</strong> {{ invoice.sale.branch.name }}</p>
                <p class="mb-4"><strong>Saldo Pendiente:</strong> <span class="font-bold text-lg text-amber-600">{{ formatCurrency(pendingAmount) }}</span></p>

                <form @submit.prevent="submitPayment">
                    <div class="space-y-4">
                        <!-- Monto del pago -->
                        <div>
                             <label class="text-sm ml-3">Monto a pagar*</label>
                             <el-input-number v-model="paymentForm.amount" :precision="2" :step="100" :min="0.01" :max="pendingAmount" class="!w-full" size="large" />
                             <InputError :message="paymentForm.errors.amount" />
                        </div>
                        <!-- Fecha de pago -->
                        <div>
                            <label class="text-sm ml-3">Fecha de pago*</label>
                            <el-date-picker v-model="paymentForm.payment_date" type="date" placeholder="Selecciona una fecha" format="YYYY/MM/DD" value-format="YYYY-MM-DD" class="!w-full" size="large" />
                            <InputError :message="paymentForm.errors.payment_date" />
                        </div>
                        <!-- Método de pago -->
                        <div>
                           <label class="text-sm ml-3">Método de pago*</label>
                            <el-select v-model="paymentForm.payment_method" placeholder="Seleccionar" class="!w-full" size="large">
                                <el-option v-for="item in paymentMethods" :key="item" :label="item" :value="item" />
                            </el-select>
                            <InputError :message="paymentForm.errors.payment_method" />
                        </div>
                        <!-- Notas -->
                        <div>
                             <label class="text-sm ml-3">Notas</label>
                             <el-input v-model="paymentForm.notes" :rows="2" type="textarea" placeholder="Añade notas o comentarios adicionales..." />
                             <InputError :message="paymentForm.errors.notes" />
                        </div>
                        <!-- Adjuntar comprobante -->
                        <div>
                            <label class="text-sm ml-3">Adjuntar comprobante(s)</label>
                            <el-upload
                                ref="paymentUploaderRef"
                                v-model:file-list="paymentForm.media"
                                multiple
                                :auto-upload="false"
                            >
                                <el-button type="primary" plain><i class="fa-solid fa-upload mr-2"></i>Seleccionar archivos</el-button>
                            </el-upload>
                            <InputError :message="paymentForm.errors.media" />
                        </div>
                    </div>
                </form>
            </div>
             <template #footer>
                <span class="dialog-footer">
                    <el-button @click="paymentModalVisible = false">Cancelar</el-button>
                    <el-button type="primary" @click="submitPayment" :loading="paymentForm.processing">
                        Confirmar Pago
                    </el-button>
                </span>
            </template>
        </el-dialog>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import BranchNotes from "@/Components/MyComponents/BranchNotes.vue";
import Back from "@/Components/MyComponents/Back.vue";
import FileView from "@/Components/MyComponents/FileView.vue";
import InputError from "@/Components/InputError.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import { Link, useForm, router } from "@inertiajs/vue3";
import { ref } from 'vue';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { ElMessage, ElMessageBox } from 'element-plus';

export default {
    name: 'InvoiceShow',
    components: {
        Link,
        Back,
        FileView,
        Dropdown,
        AppLayout,
        InputError,
        BranchNotes,
        DropdownLink,
        SecondaryButton,
    },
    props: {
        invoice: Object,
    },
    setup(props) {
        // Formulario para adjuntar archivos a la factura
        const mediaForm = useForm({
            media: [],
        });

        // Formulario para registrar pagos
        const paymentForm = useForm({
            amount: null,
            payment_date: new Date().toISOString().split('T')[0],
            payment_method: 'Transferencia electrónica de fondos',
            notes: '',
            media: [], // Para los comprobantes de pago
        });

        // Referencias a los componentes de subida de archivos
        const mediaUploaderRef = ref(null);
        const paymentUploaderRef = ref(null);

        return { mediaForm, paymentForm, mediaUploaderRef, paymentUploaderRef };
    },
    data() {
        return {
            attachFileModalVisible: false,
            paymentModalVisible: false,
            paymentMethods: ['Efectivo', 'Transferencia electrónica de fondos', 'Tarjeta de crédito', 'Tarjeta de débito', 'Cheque nominativo', 'Por definir'],
        };
    },
    computed: {
        paidAmount() {
            if (!this.invoice.payments || this.invoice.payments.length === 0) {
                return 0;
            }
            return this.invoice.payments.reduce((total, payment) => total + parseFloat(payment.amount), 0);
        },
        pendingAmount() {
            const pending = parseFloat(this.invoice.amount) - this.paidAmount;
            return Math.max(0, pending); // Asegura que no sea negativo
        }
    },
    methods: {
        formatRelative(dateString) {
            if (!dateString) return "Sin registro";

            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date; // Diferencia en milisegundos

            if (diffMs < 0) {
                return "En el futuro"; // por si la fecha viene futura
            }

            const seconds = Math.floor(diffMs / 1000);
            const minutes = Math.floor(seconds / 60);
            const hours = Math.floor(minutes / 60);
            const days = Math.floor(hours / 24);
            const months = Math.floor(days / 30);
            const years = Math.floor(months / 12);

            if (seconds < 60) return `Hace ${seconds} segundos`;
            if (minutes < 60) return `Hace ${minutes} minutos`;
            if (hours < 24) return `Hace ${hours} horas`;
            if (days < 30) return `Hace ${days} días`;
            if (months < 12) return `Hace ${months} mes${months > 1 ? "es" : ""}`;
            return `Hace ${years} año${years > 1 ? "s" : ""}`;
        },
        deleteFile(fileId) {
            this.invoice.media = this.invoice.media.filter(m => m.id !== fileId);
        },
        // --- MÉTODOS PARA ACCIONES DE FACTURA (CANCELAR, ELIMINAR, ADJUNTAR) ---
        confirmCancelInvoice(invoice) {
            if (invoice.status === 'Cancelada' || invoice.status === 'Pagada') {
                ElMessage.info(`Esta factura no se puede cancelar porque su estatus es "${invoice.status}".`);
                return;
            }

            ElMessageBox.confirm(
                `Se cancelará la factura con folio <strong class="text-indigo-500">${invoice.folio}</strong>. Esta acción no se puede deshacer. ¿Deseas continuar?`,
                'Confirmar Cancelación',
                {
                    confirmButtonText: 'Sí, Cancelar',
                    cancelButtonText: 'Salir',
                    type: 'warning',
                    dangerouslyUseHTMLString: true,
                }
            ).then(() => {
                this.cancelInvoice(invoice);
            }).catch(() => {
                ElMessage.info('Cancelación abortada');
            });
        },
        cancelInvoice(invoice) {
            router.put(route('invoices.cancel', invoice.id), {}, {
                preserveScroll: true,
                onSuccess: () => ElMessage.success('Factura cancelada correctamente'),
                onError: (errors) => ElMessage.error(errors.error || 'No se pudo cancelar la factura.'),
            });
        },
        confirmDeleteInvoice(invoice) {
            ElMessageBox.confirm(
                `Se eliminará permanentemente la factura con folio <strong class="text-red-500">${invoice.folio}</strong>. Esta acción no se puede deshacer. ¿Deseas continuar?`,
                'Confirmar Eliminación',
                {
                    confirmButtonText: 'Sí, Eliminar',
                    cancelButtonText: 'Salir',
                    type: 'error',
                    dangerouslyUseHTMLString: true,
                }
            ).then(() => {
                this.deleteInvoice(invoice);
            }).catch(() => {
                ElMessage.info('Eliminación abortada');
            });
        },
        deleteInvoice(invoice) {
            router.delete(route('invoices.destroy', invoice.id), {
                preserveScroll: true,
                onSuccess: () => {
                    ElMessage.success('Factura eliminada. Serás redirigido al listado.');
                    router.visit(route('invoices.index'));
                },
                onError: (errors) => ElMessage.error(errors.error || 'No se pudo eliminar la factura.'),
            });
        },
        openAttachFileModal() {
            this.mediaForm.reset();
            this.mediaUploaderRef?.clearFiles();
            this.attachFileModalVisible = true;
        },
        handleFileChange(file, fileList) {
            this.mediaForm.media = fileList.map(f => f.raw);
        },
        submitAttachFile() {
            this.mediaForm.post(route('invoices.media.store', this.invoice.id), {
                preserveScroll: true,
                onSuccess: () => {
                    this.attachFileModalVisible = false;
                    ElMessage.success('Archivos adjuntados correctamente');
                },
                onError: () => ElMessage.error('Ocurrió un error al subir los archivos.'),
            });
        },

        // --- MÉTODOS PARA REGISTRO DE PAGO ---
        openPaymentModal() {
            this.paymentForm.reset();
            this.paymentForm.amount = this.pendingAmount; // Sugerir el monto pendiente
            this.paymentUploaderRef?.clearFiles();
            this.paymentModalVisible = true;
        },
        submitPayment() {
            // Importante: Usar .transform() para que Inertia maneje el array de archivos
            this.paymentForm.transform(data => ({
                ...data,
                media: data.media.map(file => file.raw),
            })).post(route('invoices.payments.store', this.invoice.id), {
                preserveScroll: true,
                onSuccess: () => {
                    this.paymentModalVisible = false;
                    ElMessage.success('Pago registrado correctamente');
                },
                onError: (errors) => {
                    console.log(errors);
                    ElMessage.error('Hubo un error al registrar el pago. Revisa los datos.');
                }
            });
        },

        // --- HELPERS DE FORMATO ---
        formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        },
        formatCurrency(value) {
            if (value === null || value === undefined) return '$0.00';
            return '$' + parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        getStatusTag(status) {
            switch (status) {
                case 'Pagada': return 'success';
                case 'Pendiente': return 'warning';
                case 'Parcialmente pagada': return 'warning';
                case 'Vencida': return 'danger';
                case 'Cancelada': return 'danger';
                default: return 'primary';
            }
        },
    },
};
</script>
