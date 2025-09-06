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
            <span
                class="text-red-600 text-8xl font-extrabold tracking-widest opacity-10 rotate-[-25deg] drop-shadow-lg select-none"
            >
                {{ purchase.is_spanish_template ? 'NO AUTORIZADO' : 'UNAUTHORIZED' }}
            </span>
            </div>


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
                        <span class="font-semibold text-gray-500">{{ purchase.is_spanish_template ? 'Fecha estimada de Entrega:' : 'Estimated Delivery Date:' }}</span>
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
                            <th class="p-4 font-bold">{{ purchase.is_spanish_template ? 'Producto' : 'Product' }}</th>
                            <th class="p-4 font-bold text-center">{{ purchase.is_spanish_template ? 'Cantidad' : 'Quantity' }}</th>
                            <th class="p-4 font-bold text-right">{{ purchase.is_spanish_template ? 'Precio Unitario' : 'Unit Price' }}</th>
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
                                
                               <!-- Distribución de Cantidades -->
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
                                            <span class="font-semibold">{{ item.plane_stock }} {{ item.product.measure_unit }}</span>
                                        </p>

                                        <p v-if="item.ship_stock > 0">
                                            <i class="fa-solid fa-ship w-4 text-blue-400"></i>
                                            Barco:
                                            <span class="font-semibold">{{ item.ship_stock }} {{ item.product.measure_unit }}</span>
                                        </p>

                                        <p v-if="item.additional_stock > 0">
                                            <i class="fa-solid fa-plus-circle w-4 text-blue-400"></i>
                                            A Favor:
                                            <span class="font-semibold">{{ item.additional_stock }} {{ item.product.measure_unit }}</span>
                                        </p>
                                        </div>
                                    </el-collapse-item>
                                </el-collapse>

                            </td>
                            <td class="p-3 text-center align-top">{{ item.quantity }} {{ item.product.measure_unit }}</td>
                            <td class="p-3 text-right align-top">{{ formatCurrency(item.unit_price) }}</td>
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
    </div>
</template>

<script>
import { Head, router } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { ElMessage } from 'element-plus';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    components: {
        Head,
        ApplicationLogo,
    },
    props: {
        purchase: Object,
    },
    computed: {
        getBankInfo() {
            if (!this.purchase.bank_account) {
                return null;
            }
            const { bank_name, account_number, clabe } = this.purchase.bank_account;
            return `${bank_name}\nCuenta: ${account_number}\nCLABE: ${clabe}`;
        },
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
        printPage() {
            window.print();
        },
        authorizePurchase() {
            if (confirm('¿Estás seguro de que deseas autorizar esta orden de compra?')) {
                router.put(route('purchases.authorize', this.purchase.id), {}, {
                    preserveScroll: true,
                    onSuccess: () => {
                        // El componente se refrescará con las nuevas props
                    },
                    onError: (errors) => {
                        console.error('Error al autorizar la compra:', errors);
                        alert('Ocurrió un error al intentar autorizar la compra.');
                    }
                });
            }
        },
        async authorizePurchase() {
            try {
                const response = await axios.put(route('purchases.authorize', this.purchase.id));
                if (response.status === 200) {
                    ElMessage.success(response.data.message);
                    // Refresca los props de la página actual desde el servidor.
                    router.reload({ 
                        preserveScroll: true,
                        preserveState: true 
                    });
                }
            } catch (err) {
                ElMessage.error('Ocurrió un error al autorizar la compra');
                console.error(err);
            }
        },
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
        padding: 3 !important;
        max-width: 100% !important;
    }
    .font-sans {
        font-family: 'Inter', sans-serif;
    }
}

/* Estilo para el cuerpo de la página en vista normal */
body {
    font-family: 'Inter', sans-serif;
}
</style>
z