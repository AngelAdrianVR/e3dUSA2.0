<template>
    <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6 border border-gray-100 dark:border-gray-800">
        <!-- Encabezado de la sección -->
        <div class="flex items-center justify-between mb-5 flex-wrap gap-2">
            <h3 class="font-semibold text-lg text-gray-800 dark:text-gray-200">
                <i class="fa-solid fa-file-invoice-dollar mr-2 text-indigo-500"></i>
                Lector de Facturas (CFDI XML)
            </h3>
            <div class="flex items-center space-x-2 flex-wrap gap-y-2">
                <el-tag v-if="xmlData" type="success" effect="light" size="small">XML procesado</el-tag>
                <el-button v-if="xmlData" size="small" type="primary" @click="openTemplate">
                    <i class="fa-solid fa-file-lines mr-1"></i> Ver plantilla
                </el-button>
                <el-button v-if="xmlData" size="small" @click="resetData">
                    <i class="fa-solid fa-file-arrow-up mr-1"></i> Cargar otra factura
                </el-button>
            </div>
        </div>

        <!-- ===== ZONA DE CARGA (drag & drop) ===== -->
        <div v-if="!xmlData"
             @click="$refs.fileInput.click()"
             @dragover.prevent="dragging = true"
             @dragleave.prevent="dragging = false"
             @drop.prevent="handleDrop"
             class="border-2 border-dashed rounded-xl p-12 text-center cursor-pointer transition-all"
             :class="dragging
                ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 scale-[1.01]'
                : 'border-gray-300 dark:border-gray-600 hover:border-indigo-400 hover:bg-gray-50 dark:hover:bg-slate-800/50'">
            <i class="fa-solid fa-cloud-arrow-up text-5xl text-gray-400 mb-4"></i>
            <p class="text-gray-600 dark:text-gray-300 font-medium text-lg">Arrastra y suelta tu archivo XML de factura aquí</p>
            <p class="text-sm text-gray-400 mt-1">o haz clic para seleccionarlo (formato CFDI 3.3 / 4.0)</p>
            <input ref="fileInput" type="file" accept=".xml,text/xml" class="hidden" @change="handleFileChange" />
        </div>

        <!-- ===== MENSAJE DE ERROR ===== -->
        <div v-if="error" class="mt-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-sm text-red-600 dark:text-red-400 flex items-start">
            <i class="fa-solid fa-circle-exclamation mt-0.5 mr-2"></i>
            <span>{{ error }}</span>
        </div>

        <!-- ===== INFORMACIÓN EXTRAÍDA DEL XML ===== -->
        <div v-if="xmlData" class="space-y-5">
            <!-- Encabezado del comprobante -->
            <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
                    <p class="font-bold text-gray-800 dark:text-gray-100">
                        <span class="px-2 py-0.5 rounded-md bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 text-xs mr-2">
                            {{ getTipoComprobante(xmlData.tipo) }}
                        </span>
                        {{ xmlData.serie && xmlData.serie !== 'N/A' ? `Serie ${xmlData.serie} · ` : '' }}Folio {{ xmlData.folio }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">CFDI versión {{ xmlData.version }}</p>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Fecha de emisión</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200">{{ formatDate(xmlData.fecha) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Moneda</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200">
                            {{ xmlData.moneda }}
                            <span v-if="xmlData.tipoCambio && xmlData.tipoCambio !== '1'" class="text-xs text-gray-400">(TC {{ xmlData.tipoCambio }})</span>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Forma de pago</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200">{{ getFormaPago(xmlData.formaPago) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Método de pago</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200">{{ getMetodoPago(xmlData.metodoPago) }}</p>
                    </div>
                    <div v-if="xmlData.lugarExpedicion" class="col-span-2">
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Lugar de expedición (C.P.)</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200">{{ xmlData.lugarExpedicion }}</p>
                    </div>
                </div>
            </div>

            <!-- Emisor / Receptor -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                    <p class="text-xs font-bold text-indigo-500 uppercase tracking-wide mb-2">
                        <i class="fa-solid fa-building mr-1"></i> Emisor
                    </p>
                    <p class="font-semibold text-gray-800 dark:text-gray-100">{{ xmlData.emisor.nombre }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">RFC: {{ xmlData.emisor.rfc }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400" v-if="xmlData.emisor.regimenFiscal">
                        Régimen fiscal: {{ getRegimenFiscal(xmlData.emisor.regimenFiscal) }}
                    </p>
                </div>
                <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                    <p class="text-xs font-bold text-emerald-500 uppercase tracking-wide mb-2">
                        <i class="fa-solid fa-user mr-1"></i> Receptor
                    </p>
                    <p class="font-semibold text-gray-800 dark:text-gray-100">{{ xmlData.receptor.nombre }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">RFC: {{ xmlData.receptor.rfc }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400" v-if="xmlData.receptor.regimenFiscal">
                        Régimen fiscal: {{ getRegimenFiscal(xmlData.receptor.regimenFiscal) }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400" v-if="xmlData.receptor.usoCFDI">
                        Uso CFDI: {{ getUsoCFDI(xmlData.receptor.usoCFDI) }}
                    </p>
                </div>
            </div>

            <!-- Conceptos -->
            <div class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                <p class="px-4 py-2 bg-gray-50 dark:bg-slate-800 text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wide border-b border-gray-200 dark:border-gray-700">
                    Conceptos ({{ xmlData.conceptos.length }})
                </p>
                <div class="overflow-x-auto">
                    <table class="w-full whitespace-nowrap text-xs">
                        <thead class="bg-gray-50 dark:bg-slate-800/60">
                            <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                <th class="py-2 px-3">Clave</th>
                                <th class="py-2 px-3">Descripción</th>
                                <th class="py-2 px-3 text-center">Cant.</th>
                                <th class="py-2 px-3">Unidad</th>
                                <th class="py-2 px-3 text-right">P. Unitario</th>
                                <th class="py-2 px-3 text-right">Importe</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="(c, idx) in xmlData.conceptos" :key="idx">
                                <td class="py-2 px-3 text-gray-500">{{ c.claveProdServ }}</td>
                                <td class="py-2 px-3 font-medium text-gray-800 dark:text-gray-200 max-w-xs truncate" :title="c.descripcion">{{ c.descripcion }}</td>
                                <td class="py-2 px-3 text-center text-gray-600 dark:text-gray-300">{{ formatQuantity(c.cantidad) }}</td>
                                <td class="py-2 px-3 text-gray-500">{{ c.unidad || c.claveUnidad || '—' }}</td>
                                <td class="py-2 px-3 text-right text-gray-700 dark:text-gray-300">{{ formatMoney(c.valorUnitario, xmlData.moneda) }}</td>
                                <td class="py-2 px-3 text-right font-semibold text-gray-800 dark:text-gray-100">{{ formatMoney(c.importe, xmlData.moneda) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Impuestos y Totales -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                    <p class="text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-3">
                        <i class="fa-solid fa-calculator mr-1"></i> Impuestos
                    </p>
                    <template v-if="xmlData.impuestos.traslados.length">
                        <p class="text-xs text-gray-400 uppercase mb-1">Traslados</p>
                        <div v-for="(t, i) in xmlData.impuestos.traslados" :key="'t' + i"
                             class="flex justify-between items-center text-sm py-1 border-b border-gray-100 dark:border-gray-800 last:border-0">
                            <span class="text-gray-600 dark:text-gray-300">
                                {{ getImpuesto(t.impuesto) }}
                                <span class="text-xs text-gray-400">({{ t.tipoFactor }} {{ formatRate(t.tasaOCuota) }})</span>
                            </span>
                            <span class="font-medium text-gray-800 dark:text-gray-100">{{ formatMoney(t.importe, xmlData.moneda) }}</span>
                        </div>
                    </template>
                    <template v-if="xmlData.impuestos.retenciones.length">
                        <p class="text-xs text-gray-400 uppercase mb-1 mt-3">Retenciones</p>
                        <div v-for="(r, i) in xmlData.impuestos.retenciones" :key="'r' + i"
                             class="flex justify-between items-center text-sm py-1 border-b border-gray-100 dark:border-gray-800 last:border-0">
                            <span class="text-gray-600 dark:text-gray-300">{{ getImpuesto(r.impuesto) }}</span>
                            <span class="font-medium text-gray-800 dark:text-gray-100">-{{ formatMoney(r.importe, xmlData.moneda) }}</span>
                        </div>
                    </template>
                    <p v-if="!xmlData.impuestos.traslados.length && !xmlData.impuestos.retenciones.length" class="text-sm text-gray-400">
                        Sin impuestos.
                    </p>
                </div>

                <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                    <p class="text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-3">
                        <i class="fa-solid fa-sack-dollar mr-1"></i> Totales
                    </p>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-gray-400">Subtotal</span>
                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ formatMoney(xmlData.subtotal, xmlData.moneda) }}</span>
                        </div>
                        <div v-if="xmlData.descuento" class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-gray-400">Descuento</span>
                            <span class="font-medium text-red-500">-{{ formatMoney(xmlData.descuento, xmlData.moneda) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-gray-400">Impuestos trasladados</span>
                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ formatMoney(xmlData.impuestos.totalTrasladados, xmlData.moneda) }}</span>
                        </div>
                        <div v-if="xmlData.impuestos.totalRetenidos" class="flex justify-between items-center">
                            <span class="text-gray-500 dark:text-gray-400">Impuestos retenidos</span>
                            <span class="font-medium text-red-500">-{{ formatMoney(xmlData.impuestos.totalRetenidos, xmlData.moneda) }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-2 border-t border-gray-200 dark:border-gray-700">
                            <span class="font-bold text-gray-700 dark:text-gray-200">Total</span>
                            <span class="font-bold text-xl text-indigo-600 dark:text-indigo-400">{{ formatMoney(xmlData.total, xmlData.moneda) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timbre fiscal -->
            <div v-if="xmlData.timbre" class="border border-gray-200 dark:border-gray-700 rounded-xl p-4 bg-gray-50 dark:bg-slate-800/50">
                <p class="text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-2">
                    <i class="fa-solid fa-certificate mr-1"></i> Timbre Fiscal Digital
                </p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">UUID</p>
                        <p class="font-mono font-medium text-gray-800 dark:text-gray-200 break-all">{{ xmlData.timbre.uuid }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Fecha de timbrado</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200">{{ formatDate(xmlData.timbre.fechaTimbrado) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Proveedor de certificación</p>
                        <p class="font-medium text-gray-800 dark:text-gray-200">{{ xmlData.timbre.rfcProvCertif }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import * as cfdi from '../cfdi';

const STORAGE_KEY = 'billing_invoice_xml';

export default {
    name: 'XmlInvoiceReader',
    data() {
        return {
            dragging: false,
            error: null,
            xmlData: null,
        };
    },
    methods: {
        handleFileChange(event) {
            const file = event.target.files[0];
            if (file) this.processFile(file);
            event.target.value = ''; // Permite volver a seleccionar el mismo archivo
        },
        handleDrop(event) {
            this.dragging = false;
            const file = event.dataTransfer?.files?.[0];
            if (file) this.processFile(file);
        },
        processFile(file) {
            this.error = null;
            this.xmlData = null;

            if (!file.name.toLowerCase().endsWith('.xml') && file.type !== 'text/xml') {
                this.error = 'El archivo debe ser un XML de factura (.xml).';
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                try {
                    const rawXml = e.target.result;
                    // Se guarda el XML para que la plantilla (pestaña nueva) pueda leerlo
                    localStorage.setItem(STORAGE_KEY, rawXml);
                    this.xmlData = this.parseXml(rawXml);
                } catch (err) {
                    this.error = err.message || 'No se pudo procesar el archivo XML.';
                }
            };
            reader.onerror = () => {
                this.error = 'Ocurrió un error al leer el archivo.';
            };
            reader.readAsText(file);
        },

        // ====== PARSEO DEL CFDI (delegado al módulo compartido) ======
        parseXml(text) {
            return cfdi.parseCfdiXml(text);
        },

        resetData() {
            this.xmlData = null;
            this.error = null;
        },
        openTemplate() {
            // Abre la plantilla en una pestaña nueva; los datos se leen de localStorage
            window.open(route('billing.invoice-template'), '_blank');
        },

        // ====== FORMATEO (delegado al módulo compartido) ======
        formatMoney(value, currency) { return cfdi.formatMoney(value, currency); },
        formatQuantity(value) { return cfdi.formatQuantity(value); },
        formatRate(value) { return cfdi.formatRate(value); },
        formatDate(value) { return cfdi.formatDate(value, 'es-MX'); },

        getTipoComprobante(code) {
            const map = { I: 'Ingreso', E: 'Egreso', T: 'Traslado', P: 'Pago', N: 'Nómina' };
            return map[code] || code || '—';
        },
        getFormaPago(code) {
            const map = {
                '01': 'Efectivo', '02': 'Cheque nominativo', '03': 'Transferencia electrónica de fondos',
                '04': 'Tarjeta de crédito', '05': 'Monedero electrónico', '06': 'Dinero electrónico',
                '08': 'Vales de despensa', '12': 'Dación en pago', '13': 'Pago por subrogación',
                '14': 'Pago por consignación', '15': 'Condonación', '17': 'Compensación',
                '23': 'Novación', '24': 'Confusión', '25': 'Remisión de deuda',
                '26': 'Prescripción o caducidad', '27': 'Ajuste de facturación previa',
                '28': 'Pago en especie', '29': 'Pago por tarjeta de débito', '30': 'Pago por tarjeta de servicios',
                '31': 'Pago por tarjeta de crédito', '99': 'Por definir',
            };
            return map[code] || code || '—';
        },
        getMetodoPago(code) {
            const map = {
                PUE: 'Pago en una sola exhibición',
                PPD: 'Pago en parcialidades o diferido',
            };
            return map[code] || code || '—';
        },
        getRegimenFiscal(code) {
            const map = {
                '601': 'General de Ley Personas Morales',
                '603': 'Personas Morales con Fines no Lucrativos',
                '605': 'Sueldos y Salarios e Ingresos Asimilados a Salarios',
                '606': 'Arrendamiento',
                '607': 'Régimen de Enajenación o Adquisición de Bienes',
                '608': 'Demás ingresos',
                '609': 'Consolidación',
                '610': 'Residentes en el Extranjero sin Establecimiento Permanente en México',
                '611': 'Ingresos por Dividendos (socios y accionistas)',
                '612': 'Personas Físicas con Actividades Empresariales y Profesionales',
                '613': 'Ingresos por intereses',
                '614': 'Ingresos por arrendamiento',
                '615': 'Demás ingresos',
                '616': 'Obligación de Contribuir por Expensas Mayores a la Diferencia entre los Ingresos y las Deducciones del Ejercicio',
                '621': 'Incorporación Fiscal',
                '622': 'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras',
                '623': 'Opcional para Grupos de Sociedades',
                '624': 'Coordinados',
                '625': 'Régimen de las Actividades Empresariales con Ingresos a través de Plataformas Tecnológicas',
                '626': 'Régimen Simplificado de Confianza',
            };
            return map[code] || code || '—';
        },
        getUsoCFDI(code) {
            const map = {
                G01: 'Adquisición de mercancías', G02: 'Devoluciones, descuentos o bonificaciones',
                G03: 'Gastos en general', I01: 'Construcciones', I02: 'Mobiliario y equipo de oficina por inversiones',
                I03: 'Equipo de transporte', I04: 'Equipo de cómputo y accesorios', I05: 'Dados, troqueles, moldes, matrices y herramental',
                I06: 'Comunicaciones telefónicas', I07: 'Comunicaciones satelitales', I08: 'Otra maquinaria y equipo',
                D01: 'Honorarios médicos, dentales y gastos hospitalarios', D02: 'Gastos médicos por incapacidad o discapacidad',
                D03: 'Gastos funerales', D04: 'Donativos', D05: 'Intereses reales efectivamente pagados por créditos hipotecarios',
                D06: 'Aportaciones voluntarias al SAR', D07: 'Primas por seguros de gastos médicos',
                D08: 'Gastos de transportación escolar obligatoria', D09: 'Depósitos en cuentas para el ahorro, primas de seguros de vida',
                D10: 'Pagos por servicios educativos (colegiaturas)', P01: 'Por definir',
            };
            return map[code] || code || '—';
        },
        getImpuesto(code) {
            const map = {
                '001': 'ISR', '002': 'IVA', '003': 'IEPS',
                '004': 'Impuesto al Comercio Exterior', '005': 'Impuesto Sobre Automóviles Nuevos',
                '006': 'Impuesto sobre la Renta no Causado', '007': 'Impuesto por la Actividad de Exploración y Extracción de Hidrocarburos',
                '008': 'Impuesto Sobre la Renta Retenido', '009': 'Impuesto al Valor Agregado Retenido',
            };
            return map[code] || code || '—';
        },
    },
};
</script>
