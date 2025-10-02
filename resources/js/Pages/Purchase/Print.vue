<template>
    <div class="bg-gray-50 min-h-screen font-sans text-gray-800">
        <Head :title="'Orden de Compra ' + String(purchase.id).padStart(4, '0')">
            <!-- Google Fonts: Inter -->
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
            <!-- Font Awesome -->
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" xintegrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        </Head>

        <!-- Botones Flotantes -->
        <div class="fixed bottom-7 right-7 print:hidden z-50 flex flex-col items-center gap-3">
            <!-- Botón de Enviar Correo -->
            <button v-if="purchase.authorized_at && $page.props.auth.user.permissions.includes('Autorizar ordenes de compra')" 
                @click="showEmailModal = true" 
                title="Enviar por Correo"
                class="bg-blue-600 text-white rounded-full size-14 shadow-lg hover:bg-blue-700 transition-all flex items-center justify-center print:hidden">
                <i class="fa-solid fa-envelope text-xl"></i>
            </button>
             <!-- Botón de Autorizar -->
            <button v-if="!purchase.authorized_at && $page.props.auth.user.permissions.includes('Autorizar ordenes de compra')" 
                @click="authorizePurchase" 
                title="Autorizar Compra"
                class="bg-green-600 text-white rounded-full size-14 shadow-lg hover:bg-green-700 transition-all flex items-center justify-center print:hidden">
                <i class="fa-solid fa-check text-2xl"></i>
            </button>
            <!-- Botón de Imprimir -->
            <button @click="printPage" title="Imprimir Orden"
                class="bg-sky-600 text-white rounded-full size-14 shadow-lg hover:bg-sky-700 transition-all flex items-center justify-center">
                <i class="fa-solid fa-print text-2xl"></i>
            </button>
        </div>

        <main class="max-w-5xl mx-auto p-8 md:p-12 bg-white shadow-2xl rounded-lg my-10 relative">
            <!-- Marca de agua para órdenes no autorizadas -->
            <div v-if="!purchase.authorizer"
                class="absolute inset-0 flex items-center justify-center z-0 print:flex pointer-events-none">
                <span class="text-red-600 text-8xl font-extrabold tracking-widest opacity-10 rotate-[-25deg] drop-shadow-lg select-none">
                    {{ purchase.is_spanish_template ? 'NO AUTORIZADO' : 'UNAUTHORIZED' }}
                </span>
            </div>
            
            <!-- Contenido de la orden -->
            <!-- Header -->
            <header class="flex justify-between items-start pb-8 border-b-2 border-gray-100">
                <div class="w-2/5">
                    <ApplicationLogo />
                    <p class="text-xs text-gray-500 mt-4 leading-relaxed">
                        Av. Aurelio Ortega 518, Seattle,<br>
                        45150 Zapopan, Jal.<br>
                        EDU211206DC9 | 33 38338209
                    </p>
                </div>
                <div class="w-3/5 text-right space-y-1">
                    <h1 v-if="purchase.is_spanish_template" class="text-4xl font-bold text-gray-800 uppercase tracking-tight">Orden de Compra</h1>
                    <h1 v-else class="text-4xl font-bold text-gray-800 uppercase tracking-tight">Purchase Order</h1>
                    <p class="text-md">
                        <span class="font-semibold text-gray-500">Folio:</span> OC-{{ String(purchase.id).padStart(4, "0") }}
                    </p>
                    <p class="text-md">
                        <span class="font-semibold text-gray-500">{{ purchase.is_spanish_template ? 'Fecha de Emisión:' : 'Issue Date:' }}</span>
                        {{ formatDate(purchase.emited_at) }}
                    </p>
                     <p class="text-md">
                        <span class="font-semibold text-gray-500">{{ purchase.is_spanish_template ? 'Fecha estimada de Entrera:' : 'Estimated Delivery Date:' }}</span>
                        {{ formatDate(purchase.expected_delivery_date) }}
                    </p>
                </div>
            </header>

            <!-- Información del Proveedor -->
            <section class="mt-8 grid grid-cols-2 gap-8">
                <div>
                    <h2 class="text-sm font-bold uppercase text-gray-500 tracking-wider">{{ purchase.is_spanish_template ? 'Proveedor' : 'Supplier' }}</h2>
                    <p class="font-bold text-lg text-blue-700 mt-2">{{ purchase.supplier.name }}</p>
                    <p class="text-sm text-gray-600">{{ purchase.is_spanish_template ? 'Dirección: ' : 'Address: ' }} {{ purchase.supplier.address }}</p>
                    <p class="text-sm text-gray-600">{{ purchase.is_spanish_template ? 'Teléfono: ' : 'Phone: ' }} {{ purchase.supplier.phone }}</p>
                </div>
                <div class="text-right">
                     <h2 class="text-sm font-bold uppercase text-gray-500 tracking-wider">{{ purchase.is_spanish_template ? 'Información Bancaria' : 'Bank Information' }}</h2>
                     <p v-if="getBankInfo" class="text-sm text-gray-600 mt-2 whitespace-pre-line">{{ getBankInfo }}</p>
                     <p v-else class="text-sm text-gray-500 italic mt-2">No especificada</p>
                </div>
            </section>
            
            <!-- Tabla de Productos -->
            <section class="mt-10">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-100 text-left text-gray-600 uppercase">
                            <th class="p-4 font-bold rounded-l-lg">Imagen</th>
                            <th class="p-4 font-bold w-[35%]">{{ purchase.is_spanish_template ? 'Producto' : 'Product' }}</th>
                            <th class="p-4 font-bold text-center">{{ purchase.is_spanish_template ? 'Cantidad' : 'Quantity' }}</th>
                            <th class="p-4 font-bold text-right">{{ purchase.is_spanish_template ? 'Precio Unitario' : 'Unit Price' }}</th>
                            <th class="p-4 font-bold text-right">{{ purchase.is_spanish_template ? 'Molde' : 'New Mold fee' }}</th>
                            <th class="p-4 font-bold text-right rounded-r-lg">{{ purchase.is_spanish_template ? 'Importe' : 'Total' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in purchase.items" :key="item.id" class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                            <td class="p-3">
                                <img 
                                    :src="item.product?.media[0]?.original_url || 'https://placehold.co/100x100/e2e8f0/cccccc?text=SIN+IMAGEN'"
                                    :alt="item.description"
                                    class="size-16 object-cover rounded-md shadow-sm"
                                    @error="$event.target.src='https://placehold.co/100x100/e2e8f0/cccccc?text=ERROR'"
                                />
                            </td>
                            <td class="p-3 align-top">
                                <p class="font-semibold text-gray-800">{{ item.description }}</p>
                                <p class="text-xs text-gray-500">{{ item.product?.code }}</p>
                                
                                <!-- MERGED: Distribución de Cantidades (from PrintOld.vue) -->
                                <el-collapse v-if="item.plane_stock > 0 || item.ship_stock > 0 || item.additional_stock > 0" class="mt-2">
                                    <el-collapse-item name="distribution">
                                        <template #title>
                                        <span class="text-xs text-blue-700 font-semibold">
                                            <i class="fa-solid fa-chart-pie mr-1"></i> Distribución de Cantidades
                                        </span>
                                        </template>
                                        <div class="text-xs text-blue-600 space-y-2 pl-2">
                                        <p v-if="item.plane_stock > 0">
                                            <i class="fa-solid fa-plane w-4 text-blue-400"></i>
                                            Avión:
                                            <span class="font-semibold">{{ formatNumber(item.plane_stock) }} {{ item.product.measure_unit }}</span>
                                        </p>
                                        <p v-if="item.ship_stock > 0">
                                            <i class="fa-solid fa-ship w-4 text-blue-400"></i>
                                            Barco:
                                            <span class="font-semibold">{{ formatNumber(item.ship_stock) }} {{ item.product.measure_unit }}</span>
                                        </p>
                                        <p v-if="item.additional_stock > 0">
                                            <i class="fa-solid fa-plus-circle w-4 text-blue-400"></i>
                                            A Favor:
                                            <span class="font-semibold">{{ formatNumber(item.additional_stock) }} {{ item.product.measure_unit }}</span>
                                        </p>
                                        </div>
                                    </el-collapse-item>
                                </el-collapse>
                            </td>
                            <td class="p-3 text-center align-top">{{ formatNumber(item.quantity) }} {{ item.product.measure_unit }}</td>
                            <td class="p-3 text-right align-top">{{ formatCurrency(item.unit_price) }}</td>
                            <td class="p-3 text-right align-top">{{ item.needs_mold ? (item.mold_price ? formatCurrency(item.mold_price) : (purchase.is_spanish_template ? 'Por definir' : 'To be defined')) : 'No' }}</td>
                            <td class="p-3 text-right font-semibold align-top">{{ formatCurrency(item.total_price) }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- Totales -->
            <section class="mt-8 flex justify-end">
                <div class="w-full max-w-sm space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Subtotal</span>
                        <span class="font-semibold text-gray-800">{{ formatCurrency(purchase.subtotal) }}</span>
                    </div>
                    <div v-if="purchase.items.some(item => item.needs_mold)" class="flex justify-between">
                        <span class="text-gray-500">{{ purchase.is_spanish_template ? 'Nuevo molde' : 'New Mold Fee' }}</span>
                        <span class="font-semibold text-gray-800">{{ moldsFee > 0 ? formatCurrency(moldsFee) : (purchase.is_spanish_template ? 'Por definir' : 'To be defined') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">{{ purchase.is_spanish_template ? 'Impuestos' : 'Tax' }}</span>
                        <span class="font-semibold text-gray-800">{{ formatCurrency(purchase.tax) }}</span>
                    </div>
                    <div class="flex justify-between text-lg font-bold text-blue-700 border-t-2 border-gray-200 pt-3 mt-2">
                        <span>Total</span>
                        <span>{{ formatCurrency(purchase.total) }}</span>
                    </div>
                </div>
            </section>

             <!-- Notas y Firmas -->
            <footer class="mt-12 pt-8 border-t-2 border-gray-100">
                <div v-if="purchase.notes">
                    <h3 class="text-sm font-bold uppercase text-gray-500 tracking-wider">{{ purchase.is_spanish_template ? 'Notas' : 'Notes' }}</h3>
                    <p class="text-sm text-gray-600 mt-2">{{ purchase.notes }}</p>
                </div>
                <div class="grid grid-cols-2 gap-16 mt-20 text-center">
                     <div class="border-t-2 border-gray-400 pt-3">
                        <p class="text-md font-semibold">{{ purchase.user.name }}</p>
                        <p class="text-xs text-gray-500 uppercase tracking-wider">{{ purchase.is_spanish_template ? 'Solicitado por' : 'Requested by' }}</p>
                    </div>
                    <div class="border-t-2 border-gray-400 pt-3">
                        <p v-if="purchase.authorizer" class="text-md font-semibold">{{ purchase.authorizer.name }}</p>
                        <p v-else class="text-sm font-semibold pt-1 text-gray-400 italic">Pendiente de Autorización</p>
                        <p class="text-xs text-gray-500 uppercase tracking-wider">{{ purchase.is_spanish_template ? 'Autorizado por' : 'Authorized by' }}</p>
                    </div>
                </div>
            </footer>
        </main>
        
        <!-- Modal para Enviar Correo -->
        <el-dialog v-model="showEmailModal" title="Enviar Orden de Compra por Correo" width="600px" :close-on-click-modal="false">
             <form @submit.prevent="sendEmail" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Proveedor *</label>
                        <el-select v-model="selectedSupplierId" placeholder="Seleccionar proveedor" class="w-full" filterable>
                            <el-option v-for="supplier in allSuppliers" :key="supplier.id" :label="supplier.name" :value="supplier.id" />
                        </el-select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contacto del Proveedor *</label>
                        <el-select v-model="form.contact_id" placeholder="Seleccionar contacto" class="w-full" filterable :disabled="!selectedSupplierId">
                            <el-option v-for="contact in availableContacts" :key="contact.id" :label="contact.name" :value="contact.id">
                                <span class="float-left">{{ contact.name }}</span>
                                <span class="float-right text-sm text-gray-400">{{ contact.email }}</span>
                            </el-option>
                        </el-select>
                        <InputError :message="form.errors.contact_id" />
                    </div>
                </div>
                 <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cuenta Bancaria *</label>
                     <el-select v-model="form.supplier_bank_account_id" placeholder="Seleccionar cuenta" class="w-full" filterable :disabled="!selectedSupplierId">
                        <el-option v-for="account in availableBankAccounts" :key="account.id" :label="account.bank_name" :value="account.id">
                            <span class="float-left">{{ account.bank_name }}</span>
                            <span class="float-right text-sm text-gray-400">{{ account.account_number }}</span>
                        </el-option>
                    </el-select>
                    <InputError :message="form.errors.supplier_bank_account_id" />
                </div>
                <div>
                    <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Asunto *</label>
                    <el-input id="subject" v-model="form.subject" placeholder="Asunto del correo"></el-input>
                    <InputError :message="form.errors.subject" />
                </div>
                <div>
                     <label for="content" class="block text-sm font-medium text-gray-700 mb-1">Mensaje (Opcional)</label>
                     <el-input id="content" v-model="form.content" type="textarea" :rows="5" placeholder="Añade un mensaje personalizado..."></el-input>
                     <InputError :message="form.errors.content" />
                </div>
                <div class="text-sm text-gray-600">
                    <i class="fa-solid fa-paperclip mr-2 text-gray-400"></i>Se adjuntará automáticamente la Orden de Compra OC-{{ String(purchase.id).padStart(4, "0") }}.pdf
                </div>
             </form>
             <template #footer>
                <div class="flex justify-end space-x-2">
                    <button @click="showEmailModal = false" type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none">
                        Cancelar
                    </button>
                    <button @click="sendEmail" :disabled="form.processing" type="button" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none disabled:opacity-50">
                        <span v-if="form.processing"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Enviando...</span>
                        <span v-else><i class="fa-solid fa-paper-plane mr-2"></i>Enviar Correo</span>
                    </button>
                </div>
             </template>
        </el-dialog>
    </div>
</template>

<script>
import { Head, router, useForm } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import InputError from '@/Components/InputError.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    components: {
        Head,
        ApplicationLogo,
        InputError,
    },
    props: {
        purchase: Object,
        allSuppliers: Array, // <-- Nueva prop
    },
    data() {
        return {
            showEmailModal: false,
            selectedSupplierId: this.purchase.supplier_id, // <-- Nuevo estado para el proveedor seleccionado
            form: useForm({
                contact_id: this.purchase.contact_id,
                supplier_bank_account_id: this.purchase.supplier_bank_account_id,
                // Asunto condicional (Español / Inglés)
    subject: this.purchase.is_spanish_template 
        ? `Orden de Compra | OC-${String(this.purchase.id).padStart(4, "0")} | Emblems 3D USA`
        : `Purchase Order | PO-${String(this.purchase.id).padStart(4, "0")} | Emblems 3D USA`,
    
    // Contenido del mensaje condicional (Español / Inglés)
    content: this.purchase.is_spanish_template 
        ? `Estimado proveedor,\n\nAdjunto nuestra orden de compra para su gestión.\n\nQuedamos a la espera de su confirmación.\n\nSaludos cordiales.`
        : `Dear Supplier,\n\nPlease find our purchase order attached for processing.\n\nWe look forward to your confirmation.\n\nBest regards.`,
            }),
        };
    },
    computed: {
        moldsFee() {
            const molds_fee = this.purchase.items.reduce((acc, item) => acc + item.mold_price, 0);
            return molds_fee;
        },
        getBankInfo() {
            if (!this.purchase.bank_account) {
                return null;
            }
            const { bank_name, account_number, clabe } = this.purchase.bank_account;
            return `${bank_name}\nCuenta: ${account_number}\nCLABE: ${clabe}`;
        },
        // Devuelve el objeto del proveedor seleccionado actualmente
        selectedSupplier() {
            if (!this.selectedSupplierId) return null;
            return this.allSuppliers.find(s => s.id === this.selectedSupplierId);
        },
        // Devuelve los contactos disponibles para el proveedor seleccionado
        availableContacts() {
            return this.selectedSupplier ? this.selectedSupplier.contacts : [];
        },
        // Devuelve las cuentas bancarias disponibles para el proveedor seleccionado
        availableBankAccounts() {
            return this.selectedSupplier ? this.selectedSupplier.bank_accounts : [];
        },
    },
    watch: {
        // Reinicia los campos de contacto y cuenta bancaria cuando cambia el proveedor
        selectedSupplierId(newId, oldId) {
            if (newId !== oldId) {
                this.form.contact_id = null;
                this.form.supplier_bank_account_id = null;
            }
        }
    },
    methods: {
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        },
        formatCurrency(value) {
            if (value === null || value === undefined) return '$0.00';
            return Number(value).toLocaleString('es-MX', { style: 'currency', currency: this.purchase.currency });
        },
        formatNumber(value) {
            if (value === null || value === undefined) return '0';
            const num = typeof value === 'string' ? parseFloat(value.replace(/,/g, '')) : value;
            if (isNaN(num)) return '0';
            return num.toLocaleString('es-MX');
        },
        printPage() {
            window.print();
        },
        authorizePurchase() {
            ElMessageBox.confirm(
                '¿Estás seguro de que deseas autorizar esta orden de compra?',
                'Confirmar Autorización',
                {
                    confirmButtonText: 'Sí, Autorizar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning',
                }
            ).then(() => {
                router.put(route('purchases.authorize', this.purchase.id), {}, {
                    preserveScroll: true,
                    onSuccess: () => {
                        ElMessage.success('¡Compra autorizada con éxito!');
                    },
                    onError: () => {
                        ElMessage.error('Ocurrió un error al autorizar la compra.');
                    }
                });
            }).catch(() => {
                // User cancelled the action
            });
        },
        sendEmail() {
            if (!this.form.contact_id || !this.form.supplier_bank_account_id || !this.form.subject) {
                 ElMessage.error('Por favor, completa los campos obligatorios: Contacto, Cuenta Bancaria y Asunto.');
                 return;
            }

            this.form.post(route('purchases.send-email', this.purchase.id), {
                preserveScroll: true,
                onSuccess: () => {
                    this.showEmailModal = false;
                    this.form.reset();
                    ElMessage.success('Correo enviado exitosamente. La página se refrescará.');
                    setTimeout(() => router.reload(), 1500);
                },
                onError: (errors) => {
                    ElMessage.error('No se pudo enviar el correo. Revisa los errores.');
                    console.log(errors);
                }
            });
        }
    }
}
</script>

<style>
@media print {
    body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .print\:hidden {
        display: none !important;
    }
    .print\:flex {
        display: flex !important;
    }
    main {
        border: none !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 0 !important;
        max-width: 100% !important;
    }
    .font-sans {
        font-family: 'Inter', sans-serif;
    }
}
body {
    font-family: 'Inter', sans-serif;
}
</style>

