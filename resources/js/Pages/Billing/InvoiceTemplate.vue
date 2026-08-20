<template>
    <Head title="Invoice Template" />

    <div class="min-h-screen bg-gray-100 print:bg-white print:py-0 text-slate-800 text-sm">
        <!-- Barra de acciones (oculta al imprimir) -->
        <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between print:hidden">
            <a :href="route('billing.dashboard')" class="text-sm text-gray-600 hover:text-gray-900 hover:underline flex items-center gap-1">
                <i class="fa-solid fa-arrow-left"></i> Volver al panel
            </a>
            <div class="flex items-center gap-2">
                <button v-if="xmlData" @click="toggleEditing"
                    :class="editing ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-slate-800 hover:bg-slate-700'"
                    class="flex items-center gap-2 text-white px-4 py-2 rounded shadow transition">
                    <i class="fa-solid" :class="editing ? 'fa-floppy-disk' : 'fa-pen'"></i>
                    <span class="font-medium text-sm">{{ editing ? 'Guardar cambios' : 'Editar datos' }}</span>
                </button>
                <button @click="printInvoice"
                    class="flex items-center gap-2 bg-slate-800 text-white px-4 py-2 rounded shadow hover:bg-slate-700 transition">
                    <i class="fa-solid fa-print"></i>
                    <span class="font-medium text-sm">Print / Save as PDF</span>
                </button>
            </div>
        </div>

        <!-- Mensaje si no hay datos -->
        <div v-if="!xmlData && !loading" class="max-w-5xl mx-auto px-4 py-20 text-center print:hidden">
            <i class="fa-solid fa-file-circle-xmark text-5xl text-gray-300 mb-4"></i>
            <p class="text-gray-600 font-medium">No se encontró información de factura.</p>
            <a :href="route('billing.dashboard')"
               class="inline-block mt-4 bg-slate-800 text-white px-4 py-2 rounded shadow hover:bg-slate-700">
                Ir al panel de facturación
            </a>
        </div>

        <!-- PLANTILLA IMPRIMIBLE CON ESTILO SOLUCIÓN FACTIBLE -->
        <div v-if="xmlData" id="printable-invoice" class="max-w-5xl mx-auto print:max-w-full">
            <!-- Marco del documento: mitad izquierda roja, mitad derecha azul -->
            <div class="print-frame">
                <div class="bg-white p-6 print:p-3 font-sans text-sm">
                    <div class="grid grid-cols-12 gap-4">

                        <!-- COLUMNA IZQUIERDA (4 de 12) -->
                        <div class="col-span-4 flex flex-col justify-between">
                            <div>
                                <!-- Teléfonos de contacto -->
                                <div class="space-y-1 mb-3 text-sm font-semibold text-slate-700">
                                    <div class="flex items-center gap-2"><i class="fa-solid fa-phone text-blue-600"></i> 333624 0054</div>
                                    <div class="flex items-center gap-2"><i class="fa-solid fa-phone text-blue-600"></i> 333833 8209</div>
                                    <div class="flex items-center gap-2"><i class="fa-brands fa-whatsapp text-emerald-600"></i> 33 2183 5678</div>
                                </div>

                                <!-- Bloque de Metadatos Fiscales -->
                                <div class="border border-gray-300 rounded overflow-hidden mb-3">
                                    <div class="bg-gray-200 text-center py-1 font-bold text-xs tracking-wide uppercase border-b border-gray-300">
                                        Digital Tax Receipt Via Internet
                                    </div>
                                    <table class="w-full text-xs border-collapse">
                                        <tbody>
                                            <!-- <tr class="border-b border-gray-200"><td class="bg-gray-100 p-1.5 font-medium text-gray-600 w-2/5">Fiscal Folio</td><td class="p-1.5 font-mono break-all leading-none">{{ xmlData.timbre?.uuid || '—' }}</td></tr>
                                            <tr class="border-b border-gray-200"><td class="bg-gray-100 p-1.5 font-medium text-gray-600">SAT certificate</td><td class="p-1.5 font-mono">{{ xmlData.timbre?.noCertificadoSAT || '—' }}</td></tr>
                                            <tr class="border-b border-gray-200"><td class="bg-gray-100 p-1.5 font-medium text-gray-600">Issuer's cert.</td><td class="p-1.5 font-mono">{{ xmlData.noCertificado || '—' }}</td></tr> -->
                                            <!-- <tr class="border-b border-gray-200"><td class="bg-gray-100 p-1.5 font-medium text-gray-600">Certification date</td><td class="p-1.5">{{ formatDate(xmlData.timbre?.fechaTimbrado) }}</td></tr> -->
                                            <template v-if="!editing">
                                                <tr class="border-b border-gray-200"><td class="bg-gray-100 p-1.5 font-medium text-gray-600">Series / Folio</td><td class="p-1.5 font-bold">{{ editable.metadata.serieFolio }}</td></tr>
                                                <tr class="border-b border-gray-200"><td class="bg-gray-100 p-1.5 font-medium text-gray-600">Issuance date</td><td class="p-1.5">{{ editable.metadata.issuanceDate }}</td></tr>
                                                <tr class="border-b border-gray-200"><td class="bg-gray-100 p-1.5 font-medium text-gray-600">Document type</td><td class="p-1.5">{{ editable.metadata.documentType }}</td></tr>
                                                <tr class="border-b border-gray-200"><td class="bg-gray-100 p-1.5 font-medium text-gray-600">Payment terms</td><td class="p-1.5 font-medium">{{ editable.metadata.paymentTerms }}</td></tr>
                                                <tr class="border-b border-gray-200"><td class="bg-gray-100 p-1.5 font-medium text-gray-600">Currency</td><td class="p-1.5 font-bold">{{ editable.metadata.currency }}</td></tr>
                                                <tr class="border-b border-gray-200"><td class="bg-gray-100 p-1.5 font-medium text-gray-600">Exchange rate</td><td class="p-1.5 font-mono">{{ editable.metadata.exchangeRate }}</td></tr>
                                                <tr class="border-b border-gray-200"><td class="bg-gray-100 p-1.5 font-medium text-gray-600">Payment method</td><td class="p-1.5 font-medium">{{ editable.metadata.paymentMethod }}</td></tr>
                                            </template>
                                            <template v-else>
                                                <tr v-for="(label, key) in metadataLabels" :key="key" class="border-b border-gray-200">
                                                    <td class="bg-gray-100 p-1 font-medium text-gray-600 w-2/5">{{ label }}</td>
                                                    <td class="p-1"><input v-model="editable.metadata[key]" class="w-full border border-gray-300 rounded px-1 py-0.5 text-xs" /></td>
                                                </tr>
                                            </template>
                                            <!-- <tr><td class="bg-gray-100 p-1.5 font-medium text-gray-600">Place of issuance</td><td class="p-1.5">{{ xmlData.lugarExpedicion }}</td></tr> -->
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Bloque Emisor -->
                                <div class="border border-gray-300 rounded p-2 mb-3 text-xs space-y-0.5">
                                    <div class="font-bold text-slate-700 uppercase mb-1 border-b border-gray-200 pb-0.5">Issuing Company:</div>
                                    <template v-if="!editing">
                                        <p class="font-bold text-slate-900 text-sm">{{ editable.issuer.name }}</p>
                                        <p class="font-bold text-sky-700">{{ editable.issuer.rfc }}</p>
                                        <p class="text-gray-600">{{ editable.issuer.address1 }}</p>
                                        <p class="text-gray-600">{{ editable.issuer.address2 }}</p>
                                    </template>
                                    <template v-else>
                                        <label class="block">
                                            <span class="text-[10px] text-gray-500 uppercase">Name</span>
                                            <input v-model="editable.issuer.name" class="w-full border border-gray-300 rounded px-1 py-0.5 text-xs mt-0.5" />
                                        </label>
                                        <label class="block">
                                            <span class="text-[10px] text-gray-500 uppercase">RFC</span>
                                            <input v-model="editable.issuer.rfc" class="w-full border border-gray-300 rounded px-1 py-0.5 text-xs mt-0.5" />
                                        </label>
                                        <label class="block">
                                            <span class="text-[10px] text-gray-500 uppercase">Address 1</span>
                                            <input v-model="editable.issuer.address1" class="w-full border border-gray-300 rounded px-1 py-0.5 text-xs mt-0.5" />
                                        </label>
                                        <label class="block">
                                            <span class="text-[10px] text-gray-500 uppercase">Address 2</span>
                                            <input v-model="editable.issuer.address2" class="w-full border border-gray-300 rounded px-1 py-0.5 text-xs mt-0.5" />
                                        </label>
                                    </template>
                                </div>

                                <!-- Bloque Receptor -->
                                <div class="border border-gray-300 rounded p-2 mb-3 text-xs space-y-0.5">
                                    <div class="font-bold text-slate-700 uppercase mb-1 border-b border-gray-200 pb-0.5">Receiving Company:</div>
                                    <template v-if="!editing">
                                        <p class="font-bold text-slate-900 text-sm">{{ editable.receiver.name }}</p>
                                        <p class="font-bold text-sky-700">{{ editable.receiver.rfc }}</p>
                                        <p class="text-gray-600">{{ editable.receiver.address1 }}</p>
                                        <p class="text-gray-600">{{ editable.receiver.address2 }}</p>
                                        <p class="text-gray-600" v-if="editable.receiver.customerNumber"><span class="font-semibold">Customer Number:</span> {{ editable.receiver.customerNumber }}</p>
                                        <p class="text-gray-600" v-if="editable.receiver.residence"><span class="font-semibold">Residence:</span> {{ editable.receiver.residence }}</p>
                                    </template>
                                    <template v-else>
                                        <label class="block">
                                            <span class="text-[10px] text-gray-500 uppercase">Name</span>
                                            <input v-model="editable.receiver.name" class="w-full border border-gray-300 rounded px-1 py-0.5 text-xs mt-0.5" />
                                        </label>
                                        <label class="block">
                                            <span class="text-[10px] text-gray-500 uppercase">RFC</span>
                                            <input v-model="editable.receiver.rfc" class="w-full border border-gray-300 rounded px-1 py-0.5 text-xs mt-0.5" />
                                        </label>
                                        <label class="block">
                                            <span class="text-[10px] text-gray-500 uppercase">Address 1</span>
                                            <input v-model="editable.receiver.address1" class="w-full border border-gray-300 rounded px-1 py-0.5 text-xs mt-0.5" />
                                        </label>
                                        <label class="block">
                                            <span class="text-[10px] text-gray-500 uppercase">Address 2</span>
                                            <input v-model="editable.receiver.address2" class="w-full border border-gray-300 rounded px-1 py-0.5 text-xs mt-0.5" />
                                        </label>
                                        <label class="block">
                                            <span class="text-[10px] text-gray-500 uppercase">Customer Number</span>
                                            <input v-model="editable.receiver.customerNumber" class="w-full border border-gray-300 rounded px-1 py-0.5 text-xs mt-0.5" />
                                        </label>
                                        <label class="block">
                                            <span class="text-[10px] text-gray-500 uppercase">Residence</span>
                                            <input v-model="editable.receiver.residence" class="w-full border border-gray-300 rounded px-1 py-0.5 text-xs mt-0.5" />
                                        </label>
                                    </template>
                                </div>
                            </div>

                            <!-- Cadena y Sellos Digitales (Pie de Columna Izquierda) -->
                            <div v-if="xmlData.timbre" class="text-[9px] text-gray-500 font-mono space-y-2 leading-tight break-all border-t border-gray-200 pt-2">
                                <div>
                                    <span class="font-bold block text-[10px] text-gray-700 font-sans">Original String of the SAT Digital Certification Addendum</span>
                                    ||1.1|{{ xmlData.timbre.uuid }}|{{ xmlData.timbre.fechaTimbrado }}|{{ xmlData.timbre.selloCFD }}||
                                </div>
                                <div>
                                    <span class="font-bold block text-[10px] text-gray-700 font-sans">CFDI Digital seal</span>
                                    {{ xmlData.timbre.selloCFD }}
                                </div>
                                <div>
                                    <span class="font-bold block text-[10px] text-gray-700 font-sans">SAT Digital seal</span>
                                    {{ xmlData.timbre.selloSAT }}
                                </div>
                            </div>
                        </div>

                        <!-- COLUMNA DERECHA (8 de 12) -->
                        <div class="col-span-8 flex flex-col justify-between">
                            <div>
                                <!-- Header Logo y Leyenda -->
                                <div class="text-center mb-3">
                                    <img src="/images/logo.png" alt="Company logo" class="h-14 w-auto object-contain" />
                                    <p class="text-[13px] font-bold text-gray-700 mt-1 leading-snug">
                                        <strong class="text-red-600"> High-quality emblems </strong> — we are leading manufacturers serving the automotive, home appliance, electronics, furniture, and toy industries.
                                    </p>
                                    <p class="text-[12px] text-gray-500 leading-snug">
                                        Within the <strong class="text-blue-600"> Automotive Divisio </strong>n, we specialize in manufacturing chrome emblems, license plate frames, keychains, document holders, styrene plates, and rubber floor mats.
                                    </p>
                                    <div class="mt-2 inline-flex items-center gap-1 bg-slate-900 text-white px-3 py-1 rounded-full text-xs font-semibold">
                                        <i class="fa-solid fa-hand-pointer text-amber-400"></i> emblems3dusa.com
                                    </div>
                                </div>

                                <!-- Tabla de Conceptos -->
                                <table class="w-full text-xs border-collapse mb-4 border border-gray-300">
                                    <thead>
                                        <tr class="bg-gray-100 border-b border-gray-300 text-slate-700 font-bold">
                                            <th class="p-1.5 text-left border-r border-gray-300 w-16">Code</th>
                                            <th class="p-1.5 text-left border-r border-gray-300">Product description</th>
                                            <th class="p-1.5 text-center border-r border-gray-300 w-12">Quantity</th>
                                            <th class="p-1.5 text-center border-r border-gray-300 w-12">Unit</th>
                                            <th class="p-1.5 text-right border-r border-gray-300 w-16">PRICE</th>
                                            <th class="p-1.5 text-right w-20">TOTAL AMOUNT</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(c, idx) in xmlData.conceptos" :key="idx" class="border-b border-gray-200">
                                            <td class="p-1.5 font-mono border-r border-gray-200">{{ c.noIdentificacion || '—' }}</td>
                                            <td class="p-1.5 border-r border-gray-200">{{ c.descripcion }}</td>
                                            <td class="p-1.5 text-center border-r border-gray-200">{{ formatQuantity(c.cantidad) }}</td>
                                            <td class="p-1.5 text-center border-r border-gray-200">{{ c.unidad || 'Pcs' }}</td>
                                            <td class="p-1.5 text-right border-r border-gray-200">{{ formatMoney(c.valorUnitario, xmlData.moneda) }}</td>
                                            <td class="p-1.5 text-right font-medium">{{ formatMoney(c.importe, xmlData.moneda) }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <!-- Importe con Letra -->
                                <div class="bg-gray-100 border border-gray-300 rounded p-2 mb-4 text-center">
                                    <span class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide">Amount in words</span>
                                    <span class="text-xs font-bold text-slate-800">*** {{ amountInWords || '—' }} ***</span>
                                </div>

                                <!-- Totales y Notas -->
                                <div class="grid grid-cols-12 gap-4 mb-4">
                                    <div class="col-span-6 border border-gray-300 rounded p-2 text-xs">
                                        <span class="font-bold text-slate-700 block mb-1">NOTES:</span>
                                        <p class="text-gray-600">Sherman Corporation</p>
                                    </div>
                                    <div class="col-span-6">
                                        <table class="w-full text-xs border-collapse">
                                            <tbody>
                                                <tr class="border-b border-gray-200">
                                                    <td class="py-1 font-bold text-gray-600 text-right pr-2">Subtotal</td>
                                                    <td class="py-1 text-right font-mono">{{ formatMoney(xmlData.subtotal, xmlData.moneda) }}</td>
                                                </tr>
                                                <tr class="border-b border-gray-200">
                                                    <td class="py-1 font-bold text-gray-600 text-right pr-2">Discounts</td>
                                                    <td class="py-1 text-right font-mono">{{ formatMoney(xmlData.descuento || 0, xmlData.moneda) }}</td>
                                                </tr>
                                                <tr class="border-b border-gray-200">
                                                    <td class="py-1 font-bold text-gray-600 text-right pr-2">Taxes passed on</td>
                                                    <td class="py-1 text-right font-mono">{{ formatMoney(xmlData.impuestos?.totalTrasladados || 0, xmlData.moneda) }}</td>
                                                </tr>
                                                <tr class="border-b border-gray-200">
                                                    <td class="py-1 font-bold text-gray-600 text-right pr-2">Taxes withheld</td>
                                                    <td class="py-1 text-right font-mono">{{ formatMoney(xmlData.impuestos?.totalRetenidos || 0, xmlData.moneda) }}</td>
                                                </tr>
                                                <tr class="border-b-2 border-gray-800">
                                                    <td class="py-1 font-bold text-slate-900 text-right pr-2 text-sm">TOTAL</td>
                                                    <td class="py-1 text-right font-bold text-slate-900 text-sm font-mono">{{ formatMoney(xmlData.total, xmlData.moneda) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer PAC / QR / Descargas -->
                            <div class="flex items-end justify-between border-t border-gray-200 pt-3">
                                <img src="/images/escudoSherman.png" alt="Company logo" class="h-20 w-auto object-contain" />
                                <img src="/images/qrPagina.png" alt="Company logo" class="h-20 w-auto object-contain" />
                                <div class="w-20 h-20 bg-white border border-gray-300 flex items-center justify-center overflow-hidden">
                                    <img v-if="qrDataUrl" :src="qrDataUrl" alt="SAT QR" class="w-full h-full object-contain" />
                                    <!-- <span v-else class="text-[9px] text-gray-500">[QR SAT]</span> -->
                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { Head } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import { parseCfdiXml, formatMoney, formatQuantity, formatRate, formatDate, buildSatQrUrl, amountToWords } from './cfdi';

const STORAGE_KEY = 'billing_invoice_xml';
const EDIT_KEY = 'billing_invoice_template_data';

export default {
    name: 'InvoiceTemplate',
    components: { Head },
    data() {
        return {
            loading: true,
            error: null,
            xmlData: null,
            qrDataUrl: null,
            editing: false,
            metadataLabels: {
                serieFolio: 'Series / Folio',
                issuanceDate: 'Issuance date',
                documentType: 'Document type',
                paymentTerms: 'Payment terms',
                currency: 'Currency',
                exchangeRate: 'Exchange rate',
                paymentMethod: 'Payment method',
            },
            editable: {
                metadata: {
                    serieFolio: '', issuanceDate: '', documentType: '', paymentTerms: '',
                    currency: '', exchangeRate: '', paymentMethod: '',
                },
                issuer: { name: '', rfc: '', address1: '', address2: '' },
                receiver: { name: '', rfc: '', address1: '', address2: '', customerNumber: '', residence: '' },
            },
        };
    },
    computed: {
        amountInWords() {
            if (!this.xmlData) return '';
            return amountToWords(this.xmlData.total, this.xmlData.moneda);
        },
    },
    mounted() {
        const rawXml = localStorage.getItem(STORAGE_KEY);
        if (!rawXml) {
            this.loading = false;
            return;
        }
        try {
            this.xmlData = parseCfdiXml(rawXml);
            this.loadEditable();
            this.generateSatQr();
        } catch (err) {
            this.error = err.message || 'No se pudo procesar el archivo XML.';
        } finally {
            this.loading = false;
        }
    },
    methods: {
        printInvoice() {
            // Si está en modo edición, guarda antes de imprimir
            if (this.editing) this.saveEditable();
            window.print();
        },
        // Carga los datos editables guardados, o los inicializa desde el XML
        loadEditable() {
            const saved = localStorage.getItem(EDIT_KEY);
            if (saved) {
                try {
                    const parsed = JSON.parse(saved);
                    this.editable = {
                        metadata: { ...this.editable.metadata, ...(parsed.metadata || {}) },
                        issuer: { ...this.editable.issuer, ...(parsed.issuer || {}) },
                        receiver: { ...this.editable.receiver, ...(parsed.receiver || {}) },
                    };
                    return;
                } catch (e) { /* si falla, se inicializa desde el XML */ }
            }
            if (!this.xmlData) return;
            this.editable = {
                metadata: {
                    serieFolio: `${this.xmlData.serie || ''}${this.xmlData.folio || ''}`,
                    issuanceDate: this.formatDate(this.xmlData.fecha),
                    documentType: this.getTipoComprobante(this.xmlData.tipo),
                    paymentTerms: this.xmlData.condicionesDePago || '30 days',
                    currency: this.xmlData.moneda || '',
                    exchangeRate: this.xmlData.tipoCambio || '1.00',
                    paymentMethod: this.getMetodoPago(this.xmlData.metodoPago),
                },
                issuer: {
                    name: this.xmlData.emisor?.nombre || '',
                    rfc: this.xmlData.emisor?.rfc || '',
                    address1: 'CALLE 1 no. 19, Col. SEATTLE',
                    address2: 'ZAPOPAN JALISCO, CP 45150',
                },
                receiver: {
                    name: this.xmlData.receptor?.nombre || '',
                    rfc: this.xmlData.receptor?.rfc || '',
                    address1: '400 NAVE ROAD S.E., STARK OHIO',
                    address2: `Estados Unidos de América, CP ${this.xmlData.receptor?.domicilioFiscal || '44646'}`,
                    customerNumber: this.xmlData.receptor?.numRegIdTrib || '',
                    residence: this.xmlData.receptor?.residenciaFiscal || '',
                },
            };
        },
        saveEditable() {
            localStorage.setItem(EDIT_KEY, JSON.stringify(this.editable));
            this.editing = false;
        },
        toggleEditing() {
            if (this.editing) {
                this.saveEditable();
            } else {
                this.editing = true;
            }
        },
        // Genera el QR del SAT a partir de los datos del comprobante
        async generateSatQr() {
            const qrUrl = buildSatQrUrl(this.xmlData);
            if (!qrUrl) return;
            try {
                this.qrDataUrl = await QRCode.toDataURL(qrUrl, { width: 300, margin: 1 });
            } catch (err) {
                console.error('No se pudo generar el QR del SAT:', err);
            }
        },
        formatMoney(v, c) { return formatMoney(v, c); },
        formatQuantity(v) { return formatQuantity(v); },
        formatRate(v) { return formatRate(v); },
        formatDate(v) { return formatDate(v, 'en-US'); },

        // ====== CATÁLOGOS SAT (ENGLISH) ======
        getTipoComprobante(code) {
            const map = { I: 'Invoice', E: 'Expense', T: 'Transfer', P: 'Payment', N: 'Payroll' };
            return map[code] || code || '—';
        },
        getMetodoPago(code) {
            const map = { PUE: 'One payment', PPD: 'Deferred / partial payment' };
            return map[code] || code || '—';
        },
    },
};
</script>

<style>
    /* Marco del documento: mitad izquierda roja, mitad derecha azul */
    .print-frame {
        background: linear-gradient(90deg, #dc2626 0%, #dc2626 50%, #2563eb 50%, #2563eb 100%);
        padding: 4px;
    }

    @media print {
        body * {
            visibility: hidden;
        }
        #printable-invoice,
        #printable-invoice * {
            visibility: visible;
        }
        #printable-invoice {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
            box-shadow: none !important;
        }
        /* Margen pequeño y estético; la factura abarca la hoja tamaño carta */
        @page {
            size: letter portrait;
            margin: 1cm 1.2cm;
        }
    }
</style>