<template>
    <Head title="Finiquito" />
    <div class="settlement-page">
        <!-- Botón de impresión (oculto al imprimir) -->
        <div class="no-print print-toolbar">
            <button @click="print" class="print-btn">
                <i class="fa-solid fa-print"></i> Imprimir / Guardar como PDF
            </button>
        </div>

        <!-- Contenido del Finiquito -->
        <div class="settlement-document">
            <!-- Encabezado -->
            <div class="doc-header">
                <h1>Finiquito</h1>
                <p>Documento de Liquidación Laboral</p>
            </div>

            <!-- Datos del empleado y empresa -->
            <div class="doc-section">
                <table class="info-table">
                    <tr>
                        <td class="label">Empleado:</td>
                        <td>{{ user.name }}</td>
                        <td class="label">Puesto:</td>
                        <td>{{ user.employee_detail?.job_position ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Fecha de Ingreso:</td>
                        <td>{{ formatDate(settlement.join_date) }}</td>
                        <td class="label">Fecha de Terminación:</td>
                        <td>{{ formatDate(settlement.current_date) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Años de Servicio:</td>
                        <td>{{ settlement.years_of_service }} años</td>
                        <td class="label">Salario Diario:</td>
                        <td>{{ formatCurrency(settlement.daily_salary) }}</td>
                    </tr>
                </table>
            </div>

            <!-- Desglose de conceptos -->
            <div class="doc-section">
                <h2>Desglose de Conceptos</h2>
                <table class="breakdown-table">
                    <thead>
                        <tr>
                            <th>Concepto</th>
                            <th>Detalle</th>
                            <th>Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Vacaciones Pendientes</td>
                            <td>
                                {{ settlement.available_vacation_days > 0
                                    ? settlement.available_vacation_days.toFixed(2) + ' días'
                                    : 'Sin días disponibles' }}
                            </td>
                            <td class="amount">{{ formatCurrency(settlement.vacation_pay) }}</td>
                        </tr>
                        <tr>
                            <td>Prima Vacacional (25%)</td>
                            <td>
                                {{ settlement.available_vacation_days > 0 ? '25% sobre vacaciones' : 'N/A' }}
                            </td>
                            <td class="amount">{{ formatCurrency(settlement.vacation_premium) }}</td>
                        </tr>
                        <tr>
                            <td>Parte Proporcional de Aguinaldo</td>
                            <td>{{ settlement.days_since_anniversary }} días desde última anualidad</td>
                            <td class="amount">{{ formatCurrency(settlement.aguinaldo_proportional) }}</td>
                        </tr>
                        <tr>
                            <td>Salarios Devengados (Nómina Pendiente)</td>
                            <td>
                                <template v-if="settlement.payroll_week">
                                    Semana {{ formatDate(settlement.payroll_week.start_date) }} —
                                    {{ formatDate(settlement.payroll_week.end_date) }}
                                </template>
                                <template v-else>Sin periodo abierto</template>
                            </td>
                            <td class="amount">{{ formatCurrency(settlement.pending_payroll) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="2">Total Finiquito</td>
                            <td class="amount">{{ formatCurrency(settlement.total_settlement) }}</td>
                        </tr>
                    </tfoot>
                </table>
                <p class="total-in-words">
                    <strong>Total:</strong> {{ numberToWords(settlement.total_settlement) }}
                </p>
            </div>

            <!-- Texto legal -->
            <div class="doc-section legal-text">
                <p>
                    Por medio del presente, el empleado <strong>{{ user.name }}</strong> declara haber recibido
                    de la empresa <strong>Emblems 3D USA, S.A. de C.V.</strong> la cantidad total de
                    <strong>{{ formatCurrency(settlement.total_settlement) }}</strong>
                    ({{ numberToWords(settlement.total_settlement) }}),
                    correspondiente al pago de su finiquito por terminación de la relación laboral, que incluye
                    vacaciones pendientes, prima vacacional, parte proporcional de aguinaldo y salarios devengados
                    hasta la fecha de terminación arriba indicada.
                </p>
                <p>
                    Asimismo, el empleado manifiesta que no existe cantidad alguna pendiente de pago por parte
                    de la empresa, derivada de la relación laboral que por este acto se da por terminada, otorgando
                    el más amplio finiquito y liberando a la empresa de cualquier responsabilidad, reclamación o
                    acción legal presente o futura relacionada con su empleo o la terminación del mismo.
                </p>
                <p>
                    El empleado declara estar plenamente conforme con la cantidad recibida y con los términos
                    de esta liquidación, reconociendo que cubre la totalidad de sus prestaciones laborales
                    devengadas.
                </p>
            </div>

            <!-- Firmas -->
            <div class="doc-section signatures">
                <div class="signature-block">
                    <div class="signature-line"></div>
                    <p class="signature-name">{{ user.name }}</p>
                    <p class="signature-label">Empleado</p>
                    <p class="signature-subtext">Recibí conforme</p>
                </div>

                <div class="signature-block">
                    <div class="signature-line"></div>
                    <p class="signature-name">E3D USA, S.A. de C.V.</p>
                    <p class="signature-label">Representante de la Empresa</p>
                </div>
            </div>

            <!-- Fecha del documento -->
            <div class="doc-section doc-footer">
                <p>Fecha de elaboración: {{ formatDate(settlement.current_date) }}</p>
                <p>Montos expresados en pesos mexicanos (MXN).</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    user: Object,
    settlement: Object,
});

const formatCurrency = (value) =>
    new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);

const formatDate = (dateString) =>
    new Date(dateString.split('T')[0] + 'T00:00:00').toLocaleDateString('es-MX', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });

const numberToWords = (amount) => {
    const entero = Math.floor(amount);
    const centavos = Math.round((amount - entero) * 100);

    const unidades = ['', 'un', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
    const decenas = ['', 'diez', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
    const especiales = ['diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve'];
    const veintis = ['veinte', 'veintiún', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'];
    const centenas = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

    const convertirGrupo = (n) => {
        if (n === 0) return '';
        if (n === 100) return 'cien';
        let texto = '';
        const c = Math.floor(n / 100);
        const d = Math.floor((n % 100) / 10);
        const u = n % 10;

        if (c > 0) texto += centenas[c] + ' ';
        if (d === 1 && u > 0) {
            texto += especiales[u] + ' ';
        } else if (d === 2 && u > 0) {
            texto += veintis[u] + ' ';
        } else {
            if (d > 0) texto += decenas[d] + (u > 0 ? ' y ' : ' ');
            if (u > 0) texto += unidades[u] + ' ';
        }
        return texto.trim();
    };

    if (entero === 0 && centavos === 0) return 'cero pesos 00/100 M.N.';

    let resultado = '';

    if (entero >= 1000000) {
        const millones = Math.floor(entero / 1000000);
        resultado += (millones === 1 ? 'un millón ' : convertirGrupo(millones) + ' millones ');
    }

    const miles = Math.floor((entero % 1000000) / 1000);
    if (miles > 0) {
        resultado += (miles === 1 ? 'mil ' : convertirGrupo(miles) + ' mil ');
    }

    const cientos = entero % 1000;
    if (cientos > 0 || entero === 0) {
        resultado += convertirGrupo(cientos) + ' ';
    }

    resultado += 'pesos ' + String(centavos).padStart(2, '0') + '/100 M.N.';
    return resultado.charAt(0).toUpperCase() + resultado.slice(1);
};

const print = () => {
    window.print();
};
</script>

<style>
/* ==========================================
   RESET GLOBAL — siempre blanco, textos negros
   ========================================== */
html, body {
    background: #ffffff !important;
    color: #000000 !important;
    margin: 0;
    padding: 0;
    font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
    font-size: 13px;
    line-height: 1.6;
}

/* ==========================================
   BARRA DE IMPRESIÓN
   ========================================== */
.print-toolbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 100;
    background: #f3f4f6;
    padding: 10px 20px;
    text-align: right;
    border-bottom: 1px solid #d1d5db;
}
.print-btn {
    background: #2563eb;
    color: #ffffff;
    border: none;
    padding: 10px 24px;
    font-size: 15px;
    font-weight: 600;
    border-radius: 6px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.print-btn:hover {
    background: #1d4ed8;
}

/* ==========================================
   DOCUMENTO PRINCIPAL
   ========================================== */
.settlement-page {
    background: #ffffff;
    color: #000000;
    min-height: 100vh;
    padding-top: 60px;
}
.settlement-document {
    max-width: 800px;
    margin: 0 auto;
    padding: 40px 42px 56px;
    background: #ffffff;
}

/* Encabezado */
.doc-header {
    text-align: center;
    border-bottom: 2px solid #111827;
    padding-bottom: 12px;
    margin-bottom: 18px;
}
.doc-header h1 {
    font-size: 20px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 3px;
    margin: 0 0 3px 0;
    color: #111827;
}
.doc-header p {
    font-size: 12px;
    color: #4b5563;
    margin: 0;
}

/* Secciones */
.doc-section {
    margin-bottom: 20px;
}
.doc-section h2 {
    font-size: 15px;
    font-weight: 700;
    border-bottom: 1px solid #d1d5db;
    padding-bottom: 4px;
    margin: 0 0 13px 0;
    color: #111827;
}

/* Tabla de info */
.info-table {
    width: 100%;
    border-collapse: collapse;
}
.info-table td {
    padding: 2px 4px;
    vertical-align: top;
    font-size: 12px;
}
.info-table .label {
    font-weight: 700;
    color: #374151;
    white-space: nowrap;
    width: 1%;
}

/* Tabla de desglose */
.breakdown-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.breakdown-table th {
    background: #f3f4f6;
    font-weight: 700;
    text-align: left;
    padding: 6px 8px;
    border-bottom: 2px solid #111827;
    color: #0e47c3;
}
.breakdown-table th:last-child,
.breakdown-table td.amount {
    text-align: right;
}
.breakdown-table td {
    padding: 6px 8px;
    border-bottom: 1px solid #e5e7eb;
    color: #1f2937;
}
.breakdown-table .total-row td {
    font-weight: 800;
    font-size: 14px;
    background: #f9fafb;
    border-top: 2px solid #111827;
    color: #111827;
    padding: 8px 8px;
}
.total-in-words {
    font-size: 12px;
    color: #4b5563;
    margin-top: 6px;
    text-align: right;
}

/* Texto legal */
.legal-text {
    font-size: 12px;
    color: #1f2937;
    line-height: 1.7;
    text-align: justify;
}
.legal-text p {
    margin: 0 0 10px 0;
}
.legal-text strong {
    color: #111827;
}

/* Firmas */
.signatures {
    display: flex;
    justify-content: space-between;
    margin-top: 42px;
    gap: 60px;
}
.signature-block {
    flex: 1;
    text-align: center;
}
.signature-line {
    border-bottom: 1px solid #111827;
    margin-bottom: 5px;
    height: 1px;
}
.signature-name {
    font-weight: 700;
    font-size: 13px;
    margin: 0 0 2px 0;
    color: #111827;
}
.signature-label {
    font-size: 12px;
    color: #374151;
    margin: 0;
}
.signature-subtext {
    font-size: 11px;
    color: #6b7280;
    margin: 3px 0 0 0;
    font-style: italic;
}

/* Footer */
.doc-footer {
    margin-top: 24px;
    text-align: center;
    font-size: 12px;
    color: #6b7280;
    border-top: 1px solid #d1d5db;
    padding-top: 10px;
}
.doc-footer p {
    margin: 0 0 2px 0;
}

/* ==========================================
   ESTILOS DE IMPRESIÓN
   ========================================== */
@media print {
    html, body {
        background: #ffffff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .no-print {
        display: none !important;
    }
    .settlement-page {
        padding-top: 0;
    }
    .settlement-document {
        max-width: 100%;
        padding: 20px 24px;
    }
    @page {
        margin: 1.5cm;
        size: letter;
    }
}
</style>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }

    body {
        background: white !important;
    }

    .print-container {
        box-shadow: none !important;
        border: none !important;
        page-break-inside: avoid;
    }
}
</style>
