<template>
  <div class="space-y-4">
    <!-- ===== Chart ===== -->
    <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-lg">
      <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div class="flex items-center gap-3 min-w-0">
          <img
            :src="product.image_url || 'https://placehold.co/56x56/1f2937/9ca3af?text=N/A'"
            @error="handleImageError"
            class="w-14 h-14 rounded-lg object-cover bg-gray-700 flex-shrink-0"
            alt=""
          />
          <div class="min-w-0">
            <h2 class="text-lg font-semibold dark:text-white truncate">{{ product.name }}</h2>
            <p class="text-xs font-mono text-gray-400">{{ product.code }}</p>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-4">
          <div class="text-right">
            <p class="text-xs text-gray-500">Piezas vendidas</p>
            <p class="text-lg font-bold font-mono dark:text-white">{{ formatNumber(totals.quantity) }}</p>
            <p v-if="mixedCurrency" class="text-[10px] text-gray-400">MXN + USD</p>
          </div>
          <div v-if="!mixedCurrency" class="text-right">
            <p class="text-xs text-gray-500">Dinero vendido</p>
            <p class="text-lg font-bold font-mono text-green-600 dark:text-green-400">{{ formatMoney(totals.amount) }}</p>
          </div>
          <div class="text-right">
            <p class="text-xs text-gray-500">Órdenes</p>
            <p class="text-lg font-bold font-mono dark:text-white">{{ formatNumber(totals.orders) }}</p>
          </div>
          <button
            @click="$emit('close')"
            class="text-gray-400 hover:text-red-500 text-2xl leading-none transition"
            title="Cerrar"
          >
            &times;
          </button>
        </div>
      </div>

      <LoadingIsoLogo v-if="loadingTimeline" class="my-3" />
      <div v-else-if="timeline.length">
        <apexchart type="line" height="340" :options="chartOptions" :series="chartSeries"></apexchart>
      </div>
      <div v-else class="text-center py-10">
        <p class="text-gray-400">No hay ventas de este producto en el periodo seleccionado.</p>
      </div>
    </div>

    <!-- ===== Orders table ===== -->
    <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-lg">
      <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <h2 class="text-lg font-semibold dark:text-white">
          Órdenes de venta <span class="text-sm font-normal text-gray-400">({{ mixedCurrency ? 'MXN + USD' : currency }})</span>
        </h2>
        <span v-if="orders" class="text-xs text-gray-400">
          Mostrando {{ pageStart }}–{{ pageEnd }} de {{ formatNumber(orders.total) }} órdenes
        </span>
      </div>

      <LoadingIsoLogo v-if="loadingOrders" class="my-3" />

      <template v-else-if="orders && orders.data.length">
        <div class="overflow-auto max-h-[45vh] rounded-md">
          <table class="w-full text-sm">
            <thead class="text-xs text-gray-600 dark:text-gray-400 uppercase bg-gray-300/70 dark:bg-gray-700/80 sticky top-0 z-10 backdrop-blur-sm">
              <tr>
                <th class="px-4 py-3 text-left">Orden</th>
                <th class="px-4 py-3 text-left">Fecha</th>
                <th class="px-4 py-3 text-left">Cliente</th>
                <th class="px-4 py-3 text-right">Piezas</th>
                <th class="px-4 py-3 text-right">Precio prom.</th>
                <th class="px-4 py-3 text-right">Total</th>
                <th class="px-4 py-3 text-left">Estado</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="order in orders.data"
                :key="order.sale_id"
                class="border-b dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50"
              >
                <td class="px-4 py-3">
                  <a
                    :href="route('sales.show', order.sale_id)"
                    target="_blank"
                    class="font-mono text-indigo-600 dark:text-indigo-400 hover:underline"
                  >
                    #{{ order.sale_id }}
                  </a>
                </td>
                <td class="px-4 py-3 dark:text-white whitespace-nowrap">{{ formatDate(order.created_at) }}</td>
                <td class="px-4 py-3 dark:text-gray-300">{{ order.branch_name || '—' }}</td>
                <td class="px-4 py-3 text-right font-mono dark:text-white">{{ formatNumber(order.total_quantity) }}</td>
                <td class="px-4 py-3 text-right font-mono text-gray-500 dark:text-gray-400">
                  {{ formatMoney(order.avg_price, order.currency) }}
                </td>
                <td class="px-4 py-3 text-right font-mono font-semibold text-green-600 dark:text-green-400">
                  {{ formatMoney(order.total_amount, order.currency) }}
                </td>
                <td class="px-4 py-3">
                  <span
                    :class="statusClass(order.status)"
                    class="text-[11px] px-2 py-1 rounded-full font-medium whitespace-nowrap"
                  >
                    {{ order.status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="orders.last_page > 1" class="flex justify-center mt-4">
          <el-pagination
            :current-page="orders.current_page"
            :page-size="orders.per_page"
            :total="orders.total"
            layout="prev, pager, next"
            background
            @current-change="fetchOrders"
          />
        </div>
      </template>

      <div v-else class="text-center py-10">
        <p class="text-gray-400">No se encontraron órdenes para este producto.</p>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import VueApexCharts from 'vue3-apexcharts';
import { ElPagination } from 'element-plus';
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';

export default {
  name: 'ProductSalesPanel',
  components: {
    LoadingIsoLogo,
    apexchart: VueApexCharts,
    ElPagination,
  },
  props: {
    product: { type: Object, required: true },
    filters: { type: Object, required: true },
    currency: { type: String, default: 'MXN' },
    formatMoney: { type: Function, required: true },
    formatNumber: { type: Function, required: true },
  },
  emits: ['close'],
  data() {
    return {
      timeline: [],
      orders: null,
      loadingTimeline: false,
      loadingOrders: false,
    };
  },
  computed: {
    /** Solo con permiso se muestran importes. */
    canSeeMoney() {
      const permissions = this.$page?.props?.auth?.user?.permissions ?? [];
      return Array.isArray(permissions) && permissions.includes('Ver cantidades de dinero');
    },
    /** true cuando el análisis muestra piezas de todas las monedas (sin filtro de moneda). */
    mixedCurrency() {
      return this.currency === 'ALL' || !this.canSeeMoney;
    },
    chartRows() {
      return this.timeline.map((row) => ({
        date: row.date,
        quantity: Number(row.total_quantity || 0),
        amount: Number(row.total_amount || 0),
        orders: Number(row.sales_count || 0),
      }));
    },
    totals() {
      return this.chartRows.reduce(
        (acc, row) => {
          acc.quantity += row.quantity;
          acc.amount += row.amount;
          acc.orders += row.orders;
          return acc;
        },
        { quantity: 0, amount: 0, orders: 0 }
      );
    },
    chartSeries() {
      if (this.mixedCurrency) {
        return [{ name: 'Piezas', type: 'line', data: this.chartRows.map((r) => r.quantity) }];
      }

      return [
        { name: `Dinero (${this.currency})`, type: 'area', data: this.chartRows.map((r) => r.amount) },
        { name: 'Piezas', type: 'line', data: this.chartRows.map((r) => r.quantity) },
      ];
    },
    chartOptions() {
      const yaxis = this.mixedCurrency
        ? [
            {
              seriesName: 'Piezas',
              labels: {
                formatter: (val) => this.formatNumber(Math.round(val)),
                style: { colors: ['#4F46E5'] },
              },
            },
          ]
        : [
            {
              seriesName: `Dinero (${this.currency})`,
              labels: {
                formatter: (val) => this.compactMoney(val),
                style: { colors: ['#10B981'] },
              },
            },
            {
              seriesName: 'Piezas',
              opposite: true,
              labels: {
                formatter: (val) => this.formatNumber(Math.round(val)),
                style: { colors: ['#4F46E5'] },
              },
            },
          ];

      return {
        chart: {
          type: 'line',
          height: 340,
          toolbar: { show: false },
          zoom: { enabled: false },
          foreColor: '#9CA3AF',
        },
        colors: this.mixedCurrency ? ['#4F46E5'] : ['#10B981', '#4F46E5'],
        stroke: { curve: 'smooth', width: this.mixedCurrency ? [3] : [0, 3] },
        fill: {
          type: this.mixedCurrency ? ['solid'] : ['gradient', 'solid'],
          gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0.08, stops: [0, 90, 100] },
        },
        dataLabels: { enabled: false },
        markers: { size: 0, hover: { size: 5 } },
        grid: { borderColor: '#9CA3AF', strokeDashArray: 4, opacity: 0.25 },
        xaxis: {
          categories: this.chartRows.map((r) => this.formatShortDate(r.date)),
          tickAmount: this.chartRows.length > 1 ? Math.min(this.chartRows.length, 12) : undefined,
          labels: { rotate: -45, rotateAlways: false, hideOverlappingLabels: true },
        },
        yaxis,
        legend: { position: 'top', horizontalAlign: 'right', labels: { colors: ['#9CA3AF'] } },
        tooltip: {
          shared: true,
          // El tooltip se muestra con fondo blanco: forzamos texto oscuro para que sea legible en modo dark.
          theme: 'light',
          custom: ({ dataPointIndex }) => {
            const row = this.chartRows[dataPointIndex];
            if (!row) return '';
            const moneyLine = this.mixedCurrency
              ? ''
              : `<div style="color:#111827">Dinero: <b>${this.formatMoney(row.amount)}</b></div>`;
            return `
              <div style="padding:8px 12px;font-size:12px;line-height:1.6;color:#111827;background:#ffffff;">
                <div style="font-weight:600;margin-bottom:4px;color:#111827">${this.formatDate(row.date)}</div>
                <div style="color:#111827">Piezas: <b>${this.formatNumber(row.quantity)}</b></div>
                ${moneyLine}
                <div style="color:#111827">Órdenes: <b>${this.formatNumber(row.orders)}</b></div>
              </div>
            `;
          },
        },
      };
    },
    pageStart() {
      if (!this.orders || !this.orders.data.length) return 0;
      return (this.orders.current_page - 1) * this.orders.per_page + 1;
    },
    pageEnd() {
      if (!this.orders) return 0;
      return (this.orders.current_page - 1) * this.orders.per_page + this.orders.data.length;
    },
  },
  watch: {
    'product.id': {
      handler() {
        this.fetchAll();
      },
    },
    filters: {
      handler() {
        this.fetchAll();
      },
      deep: true,
    },
  },
  methods: {
    fetchAll() {
      this.fetchTimeline();
      this.fetchOrders(1);
    },
    async fetchTimeline() {
      this.loadingTimeline = true;
      try {
        const response = await axios.get(
          route('api.sales-analysis.product-sales-timeline', { product: this.product.id }),
          { params: this.filters }
        );
        this.timeline = response.data;
      } catch (e) {
        console.error('Error fetching product sales timeline:', e);
        this.timeline = [];
      } finally {
        this.loadingTimeline = false;
      }
    },
    async fetchOrders(page = 1) {
      this.loadingOrders = true;
      try {
        const response = await axios.get(
          route('api.sales-analysis.product-sale-orders', { product: this.product.id, page }),
          { params: this.filters }
        );
        this.orders = response.data;
      } catch (e) {
        console.error('Error fetching product sale orders:', e);
        this.orders = null;
      } finally {
        this.loadingOrders = false;
      }
    },
    formatDate(dateString) {
      if (!dateString) return '';
      const raw = `${dateString}`;
      // Las fechas del timeline vienen como 'YYYY-MM-DD'; las de las órdenes como ISO.
      const date = raw.includes('T') ? new Date(raw) : new Date(`${raw.slice(0, 10)}T00:00:00`);
      return date.toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
    },
    formatShortDate(dateString) {
      if (!dateString) return '';
      const raw = `${dateString}`;
      const date = raw.includes('T') ? new Date(raw) : new Date(`${raw.slice(0, 10)}T00:00:00`);
      return date.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
    },
    compactMoney(value) {
      const abs = Math.abs(value || 0);
      if (abs >= 1000000) return `$${(value / 1000000).toFixed(1)}M`;
      if (abs >= 1000) return `$${(value / 1000).toFixed(1)}k`;
      return `$${Math.round(value || 0)}`;
    },
    statusClass(status) {
      switch (status) {
        case 'En Producción':
          return 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300';
        case 'Preparando Envío':
          return 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300';
        case 'Enviada':
          return 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300';
        default:
          return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300';
      }
    },
    handleImageError(event) {
      const img = event.target;
      const currentSrc = img.src;
      const prodDomain = 'https://www.intranetemblems3d.dtw.com.mx';

      if (img.dataset.fallbackAttempted || currentSrc.includes(prodDomain)) return;
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
    this.fetchAll();
  },
};
</script>
