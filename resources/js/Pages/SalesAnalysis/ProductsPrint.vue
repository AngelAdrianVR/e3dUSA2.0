<template>
  <div class="print-view">
    <!-- Acciones (no se imprimen) -->
    <div class="no-print toolbar">
      <button type="button" class="btn btn-secondary" @click="goBack">
        <i class="fa-solid fa-arrow-left"></i> Regresar
      </button>
      <button type="button" class="btn btn-primary" @click="printPage">
        <i class="fa-solid fa-print"></i> Imprimir
      </button>
    </div>

    <!-- Encabezado -->
    <header class="report-header">
      <div>
        <h1>Top {{ products.length }} productos más vendidos</h1>
        <p class="subtitle">
          {{ showsMoney ? `Ordenado por dinero vendido (${currency || 'MXN'})` : 'Ordenado por piezas vendidas (todas las monedas)' }}
        </p>
      </div>
      <div class="report-meta">
        <p><span>Periodo:</span> {{ periodLabel }}</p>
        <p><span>Familia:</span> {{ familyName }}</p>
        <p><span>Generado:</span> {{ generatedAt }}</p>
      </div>
    </header>

    <!-- Tabla -->
    <table class="report-table">
      <thead>
        <tr>
          <th class="col-index">#</th>
          <th class="col-image">Imagen</th>
          <th class="col-code">Código</th>
          <th>Producto</th>
          <th class="col-family">Familia</th>
          <th class="col-number">Piezas</th>
          <th v-if="showsMoney" class="col-number">Dinero</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(product, index) in products" :key="product.id">
          <td class="col-index">{{ index + 1 }}</td>
          <td class="col-image">
            <img
              :src="product.image_url || placeholder"
              @error="handleImageError"
              alt=""
              class="product-image"
            />
          </td>
          <td class="col-code">{{ product.code }}</td>
          <td>
            <span class="product-name">{{ product.name }}</span>
            <span v-if="product.archived_at" class="badge">Obsoleto</span>
          </td>
          <td class="col-family">{{ product.family_name }}</td>
          <td class="col-number">{{ formatNumber(product.total_quantity) }}</td>
          <td v-if="showsMoney" class="col-number">{{ formatMoney(product.total_amount) }}</td>
        </tr>
      </tbody>
      <tfoot v-if="products.length">
        <tr>
          <td :colspan="showsMoney ? 5 : 4" class="totals-label">Totales</td>
          <td class="col-number totals-value">{{ formatNumber(totals.quantity) }}</td>
          <td v-if="showsMoney" class="col-number totals-value">{{ formatMoney(totals.amount) }}</td>
        </tr>
      </tfoot>
    </table>

    <p v-if="!products.length" class="empty">No se encontraron productos vendidos en el periodo seleccionado.</p>

    <footer class="report-footer">
      Análisis de ventas · {{ appName }}
    </footer>
  </div>
</template>

<script>
export default {
  name: 'ProductsPrint',
  props: {
    products: { type: Array, default: () => [] },
    metric: { type: String, default: 'units' },
    limit: { type: Number, default: 20 },
    currency: { type: String, default: null },
    canSeeMoney: { type: Boolean, default: false },
    familyName: { type: String, default: 'Todas las familias' },
    periodLabel: { type: String, default: '' },
  },
  computed: {
    showsMoney() {
      return this.canSeeMoney && this.metric === 'amount';
    },
    totals() {
      return this.products.reduce(
        (acc, product) => {
          acc.quantity += Number(product.total_quantity || 0);
          acc.amount += Number(product.total_amount || 0);
          return acc;
        },
        { quantity: 0, amount: 0 }
      );
    },
    generatedAt() {
      const now = new Date();
      const date = now.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
      const time = now.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
      return `${date} ${time}`;
    },
    appName() {
      return import.meta.env.VITE_APP_NAME || 'Emblems USA';
    },
    placeholder() {
      return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48"><rect width="48" height="48" fill="#e5e7eb"/><text x="24" y="28" font-size="10" text-anchor="middle" fill="#6b7280">N/A</text></svg>'
      );
    },
  },
  methods: {
    printPage() {
      window.print();
    },
    goBack() {
      window.location.href = route('sales-analysis.index', { tab: 'products' });
    },
    formatNumber(value) {
      return new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 }).format(value ?? 0);
    },
    formatMoney(value) {
      const code = this.currency || 'MXN';
      const locale = code === 'USD' ? 'en-US' : 'es-MX';
      return new Intl.NumberFormat(locale, { style: 'currency', currency: code }).format(value ?? 0);
    },
    handleImageError(event) {
      const img = event.target;
      const currentSrc = img.src;
      const prodDomain = 'https://www.intranetemblems3d.dtw.com.mx';

      if (img.dataset.fallbackAttempted || currentSrc.includes(prodDomain)) {
        img.src = this.placeholder;
        return;
      }
      img.dataset.fallbackAttempted = 'true';

      try {
        const urlObj = new URL(currentSrc);
        img.src = prodDomain + urlObj.pathname;
      } catch (e) {
        img.src = currentSrc.replace(/^https?:\/\/[^\/]+/, prodDomain);
      }
    },
  },
  mounted() {
    document.title = `Top ${this.products.length} productos más vendidos`;
  },
};
</script>

<style>
/* Vista de impresión: solo modo claro, sin depender del tema de la app. */
.print-view {
  min-height: 100vh;
  background: #ffffff;
  color: #111827;
  padding: 24px 32px;
  font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
  font-size: 13px;
}

.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 24px;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid transparent;
  transition: background-color .15s ease, color .15s ease;
}

.btn-primary {
  background: #2563eb;
  color: #ffffff;
}

.btn-primary:hover {
  background: #1d4ed8;
}

.btn-secondary {
  background: #ffffff;
  color: #374151;
  border-color: #d1d5db;
}

.btn-secondary:hover {
  background: #f3f4f6;
}

.report-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 24px;
  border-bottom: 2px solid #111827;
  padding-bottom: 12px;
  margin-bottom: 16px;
}

.report-header h1 {
  font-size: 20px;
  font-weight: 700;
  margin: 0 0 4px;
  color: #111827;
}

.subtitle {
  margin: 0;
  font-size: 12px;
  color: #4b5563;
}

.report-meta p {
  margin: 0 0 2px;
  font-size: 12px;
  color: #374151;
  text-align: right;
  white-space: nowrap;
}

.report-meta span {
  font-weight: 600;
  color: #111827;
}

.report-table {
  width: 100%;
  border-collapse: collapse;
  background: #ffffff;
}

.report-table th,
.report-table td {
  border: 1px solid #d1d5db;
  padding: 6px 8px;
  text-align: left;
  vertical-align: middle;
  color: #111827;
}

.report-table thead th {
  background: #f3f4f6;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: .03em;
  font-weight: 700;
}

.report-table tbody tr:nth-child(even) {
  background: #fafafa;
}

.report-table tfoot td {
  background: #f3f4f6;
  font-weight: 700;
}

.col-index {
  width: 34px;
  text-align: center !important;
}

.col-image {
  width: 56px;
  text-align: center !important;
}

.col-code {
  width: 140px;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 11px;
  white-space: nowrap;
}

.col-family {
  width: 130px;
  font-size: 12px;
}

.col-number {
  width: 110px;
  text-align: right !important;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  white-space: nowrap;
}

.product-image {
  width: 42px;
  height: 42px;
  object-fit: cover;
  border-radius: 4px;
  border: 1px solid #e5e7eb;
  display: inline-block;
}

.product-name {
  font-weight: 600;
}

.badge {
  display: inline-block;
  margin-left: 6px;
  padding: 1px 6px;
  border-radius: 9999px;
  background: #fef3c7;
  color: #92400e;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
}

.totals-label {
  text-align: right !important;
  text-transform: uppercase;
  font-size: 11px;
}

.totals-value {
  font-weight: 700;
}

.empty {
  margin-top: 24px;
  text-align: center;
  color: #6b7280;
}

.report-footer {
  margin-top: 24px;
  padding-top: 8px;
  border-top: 1px solid #e5e7eb;
  font-size: 11px;
  color: #6b7280;
  text-align: right;
}

@media print {
  @page {
    margin: 12mm;
  }

  .no-print {
    display: none !important;
  }

  .print-view {
    padding: 0;
    font-size: 11px;
  }

  .report-table {
    font-size: 10px;
  }

  .report-table tr {
    page-break-inside: avoid;
  }

  body {
    background: #ffffff !important;
  }
}
</style>
