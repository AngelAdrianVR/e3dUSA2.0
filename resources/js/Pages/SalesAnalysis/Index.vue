<template>
<AppLayout title="Análisis de ventas">
  <!-- Main container -->
  <div class="min-h-screen p-4 sm:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto">
      <!-- Header -->
      <header class="mb-8">
        <h1 class="text-3xl font-bold dark:text-white tracking-tight">Análisis de Ventas</h1>
        <p class="text-gray-400 mt-1">Dashboard interactivo para visualizar el rendimiento de ventas.</p>
      </header>

      <!-- Filters -->
       <div class="mb-6 flex flex-wrap items-center gap-4">
        <div class="flex items-center space-x-2 bg-white dark:bg-gray-800 p-1.5 rounded-lg shadow-md w-[450px]">
          <button
            v-for="period in periods"
            :key="period.key"
            @click="changePeriod(period.key)"
            :disabled="isLoadingAny"
            :class="[
                'w-full text-center px-4 py-2 rounded-md text-sm font-medium transition-colors duration-200 focus:outline-none',
                activePeriod === period.key 
                ? 'bg-blue-600 text-white shadow-md' 
                : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700',
                isLoadingAny ? 'opacity-50 cursor-not-allowed' : ''
            ]">
            {{ period.label }}
          </button>
        </div>
        <div v-if="activePeriod === 'custom'">
            <el-date-picker
                v-model="customDateRange"
                type="daterange"
                range-separator="a"
                start-placeholder="Fecha de inicio"
                end-placeholder="Fecha de fin"
                @change="handleDateChange"
                :disabled="isLoadingAny"
                format="DD/MM/YYYY"
                value-format="YYYY-MM-DD"
            />
        </div>
      </div>

      <!-- KPIs and Main Charts -->
      <LoadingIsoLogo v-if="isLoading.salesMetrics" class="my-3" />
      <div v-else-if="salesMetrics">
        <!-- KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
          <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-lg">
            <h3 class="text-sm font-medium text-gray-400">Ventas Totales</h3>
            <p class="mt-2 text-3xl font-bold text-slate-700 dark:text-white">{{ formatCurrency(salesMetrics.total_sales) }}</p>
          </div>
          <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-lg">
            <h3 class="text-sm font-medium text-gray-400">Costos Totales</h3>
            <p class="mt-2 text-3xl font-bold text-slate-700 dark:text-white">{{ formatCurrency(salesMetrics.total_costs) }}</p>
          </div>
          <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-lg">
            <h3 class="text-sm font-medium text-gray-400">Utilidad Bruta</h3>
            <p class="mt-2 text-3xl font-bold text-green-400">{{ formatCurrency(salesMetrics.total_profit) }}</p>
          </div>
          <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-lg flex flex-col items-center justify-center">
            <h3 class="text-sm font-medium text-gray-400 mb-2">Margen de Utilidad</h3>
            <apexchart type="radialBar" height="120" :options="marginChartOptions" :series="marginChartSeries"></apexchart>
          </div>
        </div>

        <!-- Sales vs Costs Chart -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg mb-8">
            <h2 class="text-lg font-semibold dark:text-white mb-4">Ventas vs. Costos a lo largo del tiempo</h2>
            <apexchart type="area" height="350" :options="salesCostsChartOptions" :series="salesCostsChartSeries"></apexchart>
        </div>
      </div>


      <!-- Product and Customer Analysis -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left Column: Top Products List -->
        <div class="lg:col-span-1 bg-white dark:bg-gray-800 p-4 rounded-xl shadow-lg h-full">
          <h2 class="text-lg font-semibold dark:text-white mb-4">Top 30 Productos Más Vendidos</h2>
          <LoadingIsoLogo v-if="isLoading.topProducts" class="my-3" />
          <ul v-else-if="topProducts.length" class="space-y-2 max-h-[60vh] overflow-y-auto pr-2">
            <li v-for="(product, index) in topProducts" :key="product.id" @click="selectProduct(product)"
              :class="['flex items-center space-x-4 p-3 rounded-lg cursor-pointer transition-all duration-200',
                selectedProduct?.id === product.id ? 'dark:bg-indigo-900 bg-blue-200 shadow-lg' : 'dark:hover:bg-gray-700 hover:bg-gray-100']">
              <span :class="['font-bold text-lg w-8 text-center flex-shrink-0', selectedProduct?.id === product.id ? 'text-white' : 'text-secondary dark:text-indigo-400']">{{ index + 1 }}</span>
              <img :src="product.image_url || 'https://placehold.co/40x40/1f2937/9ca3af?text=N/A'" class="w-10 h-10 rounded-md object-cover bg-gray-700">
              <div class="flex-1 min-w-0">
                <p class="font-semibold text-sm text-gray-600 dark:text-white truncate">{{ product.name }}</p>
                <p class="text-xs text-gray-400">{{ product.code }}</p>
              </div>
              <div class="text-right flex-shrink-0">
                 <p class="font-bold text-base text-gray-600 dark:text-white">{{ product.total_quantity }}</p>
                 <p class="text-xs text-gray-500">unidades</p>
              </div>
            </li>
          </ul>
           <div v-else class="text-center py-10"><p class="text-gray-400">No se encontraron productos.</p></div>
        </div>

        <!-- Right Column: Details View -->
        <div class="lg:col-span-2 space-y-7">
            <!-- Top Customers List (Default View) -->
            <div v-if="!selectedProduct && !selectedCustomer" class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-lg">
                <h2 class="text-lg font-semibold dark:text-white mb-4">Top 10 Clientes</h2>
                <LoadingIsoLogo v-if="isLoading.topCustomers" class="my-3" />
                <ul v-else-if="topCustomers.length" class="space-y-1">
                    <li v-for="(customer, index) in topCustomers" :key="customer.id" @click="selectCustomer(customer)" class="flex items-center justify-between p-3 rounded-lg cursor-pointer transition-all duration-200 dark:hover:bg-gray-700 hover:bg-gray-100">
                        <div class="flex items-center space-x-4">
                             <span class="font-bold text-lg w-8 text-center text-secondary dark:text-indigo-400">{{ index + 1 }}</span>
                             <p class="font-semibold dark:text-white">{{ customer.name }}</p>
                        </div>
                        <p class="font-semibold text-lg text-green-400 font-mono">{{ formatCurrency(customer.total_purchased) }}</p>
                    </li>
                </ul>
                 <div v-else class="text-center py-10"><p class="text-gray-400">No se encontraron clientes.</p></div>
            </div>
            
            <!-- Customer Details View -->
            <div v-if="selectedCustomer" class="space-y-8">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-lg font-semibold dark:text-white">Detalles del Cliente</h2>
                            <h3 class="text-2xl font-bold dark:text-white">{{ selectedCustomer.name }}</h3>
                        </div>
                        <button @click="selectedCustomer = null" class="text-gray-400 hover:text-red-500 text-2xl transition">&times;</button>
                    </div>
                    <LoadingIsoLogo v-if="isLoading.customerDetails" class="my-3" />
                    <div v-else-if="customerSalesDetails.length">
                        <div class="mb-6">
                            <h4 class="text-md font-semibold dark:text-white mb-2">Ventas por Monto ($) por Familia</h4>
                            <apexchart type="area" height="300" :options="customerChartOptions" :series="customerAmountSeries"></apexchart>
                        </div>
                        <div>
                            <h4 class="text-md font-semibold dark:text-white mb-2">Ventas por Piezas (unidades) por Familia</h4>
                            <apexchart type="area" height="300" :options="customerChartOptions" :series="customerQuantitySeries"></apexchart>
                        </div>
                    </div>
                    <div v-else class="text-center py-10"><p class="text-gray-400">Sin ventas para este cliente en el período.</p></div>
                </div>
            </div>

          <!-- Product Details View -->
          <div v-if="selectedProduct" class="space-y-8">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold dark:text-white">Detalles del Producto</h2>
                    <button @click="selectedProduct = null" class="text-gray-400 hover:text-red-500 text-2xl transition">&times;</button>
                </div>
                <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-4 sm:space-y-0 sm:space-x-6">
                    <img :src="selectedProduct.image_url || 'https://placehold.co/128x128/1f2937/9ca3af?text=N/A'" class="w-32 h-32 rounded-lg object-cover bg-gray-700">
                    <div class="flex-1">
                        <h3 class="text-2xl font-bold dark:text-white">{{ selectedProduct.name }}</h3>
                        <p class="text-sm text-gray-400 font-mono mb-4">{{ selectedProduct.code }}</p>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-gray-500">Costo</p>
                                <p class="text-lg font-semibold text-green-400">{{ formatCurrency(selectedProduct.cost) }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Precio Base</p>
                                <p class="text-lg font-semibold text-green-400">{{ formatCurrency(selectedProduct.base_price) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg">
                <h2 class="text-lg font-semibold dark:text-white mb-4">Historial de Ventas</h2>
                <LoadingIsoLogo v-if="isLoading.productSales" class="my-3" />
                <div v-else-if="productSales.length" class="overflow-x-auto max-h-[50vh] overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs text-gray-600 dark:text-gray-400 uppercase bg-gray-300 dark:bg-gray-700/50 sticky top-0">
                            <tr>
                                <th class="px-6 py-3">Fecha</th>
                                <th class="px-6 py-3">Cliente</th>
                                <th class="px-6 py-3 text-right">Cantidad</th>
                                <th class="px-6 py-3 text-right">Precio</th>
                                <th class="px-6 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="sale in productSales" :key="sale.sale_id" class="border-b dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 dark:text-white">{{ formatDate(sale.created_at) }}</td>
                                <td class="px-6 py-4 dark:text-white">{{ sale.branch_name }}</td>
                                <td class="px-6 py-4 text-right font-mono dark:text-white">{{ sale.quantity }}</td>
                                <td class="px-6 py-4 text-right font-mono dark:text-white">{{ formatCurrency(sale.price) }}</td>
                                <td class="px-6 py-4 text-right font-semibold text-indigo-400 font-mono">{{ formatCurrency(sale.quantity * sale.price) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="text-center py-10"><p class="text-gray-400">No se encontraron ventas para este producto.</p></div>
            </div>
          </div>
        </div>
      </div>

       <!-- Product Families Donut Charts -->
      <div v-if="productFamiliesSales.length" class="mt-8 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg">
        <h2 class="text-lg font-semibold dark:text-white mb-6">Ventas por Familia de Productos</h2>
        <LoadingIsoLogo v-if="isLoading.productFamiliesSales" class="my-3" />
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
          <div v-for="family in productFamiliesSales" :key="family.name" class="flex flex-col items-center">
            <apexchart type="donut" width="250" :options="getFamilyDonutOptions(family)" :series="[family.percentage]"></apexchart>
            <p class="font-semibold dark:text-white mt-2 text-center">{{ family.name }}</p>
            <p class="text-sm text-green-400 font-mono text-center">{{ formatCurrency(family.total) }}</p>
          </div>
        </div>
      </div>

    </div>
  </div>
</AppLayout>
</template>

<script>
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import AppLayout from "@/Layouts/AppLayout.vue";
import axios from 'axios';
import { useDark } from '@vueuse/core';
import VueApexCharts from 'vue3-apexcharts';
import { ElDatePicker } from 'element-plus';
import 'element-plus/dist/index.css';
import 'element-plus/theme-chalk/dark/css-vars.css'

const defaultChartOptions = {
  chart: { toolbar: { show: false }, zoom: { enabled: false }, foreColor: '#9CA3AF' },
  grid: { borderColor: '#374151', strokeDashArray: 4 },
  tooltip: { theme: 'dark' },
  markers: { size: 5, hover: { size: 7 }, strokeColors: '#111827' },
};

export default {
  name: 'SalesAnalysisDashboard',
  components:{
    AppLayout,
    LoadingIsoLogo,
    apexchart: VueApexCharts,
    ElDatePicker,
  },
  data() {
    const isDarkMode = useDark();
    return {
      // Data
      topProducts: [],
      topCustomers: [],
      selectedProduct: null,
      selectedCustomer: null,
      productSales: [],
      salesMetrics: null,
      customerSalesDetails: [],
      productFamiliesSales: [],
      
      // State
      activePeriod: 'month',
      customDateRange: [],
      periods: [
        { key: 'month', label: 'Este Mes' },
        { key: 'year', label: 'Este Año' },
        { key: 'all', label: 'Histórico' },
        { key: 'custom', label: 'Personalizado' },
      ],
      isLoading: {
        topProducts: false,
        productSales: false,
        topCustomers: false,
        salesMetrics: false,
        customerDetails: false,
        productFamiliesSales: false,
      },
      
      // Charts
      salesCostsChartOptions: {
        ...defaultChartOptions,
        chart: { ...defaultChartOptions.chart, type: 'area', height: 350 },
        xaxis: { type: 'datetime', labels: { datetimeUTC: false, format: 'dd MMM' }},
        yaxis: { labels: { formatter: (val) => `$${new Intl.NumberFormat('es-MX').format(val.toFixed(0))}` }},
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        colors: ['#4F46E5', '#10B981'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.1, stops: [0, 90, 100]}},
        legend: { position: 'top', horizontalAlign: 'right' }
      },
      marginChartOptions: {
         ...defaultChartOptions,
         chart: { type: 'radialBar', height: 120 },
         plotOptions: {
            radialBar: {
                startAngle: -135, endAngle: 135, hollow: { size: '70%' },
                track: { background: '#374151', strokeWidth: '100%'},
                dataLabels: {
                    name: { show: false },
                    value: { fontSize: '22px', fontFamily: 'monospace', fontWeight: 'bold', color: isDarkMode.value ? '#FFFFFF' : '#4B5563', offsetY: 8, formatter: (val) => `${val ? val.toFixed(1) : 0}%` },
                },
            },
         },
         fill: { type: 'gradient', gradient: { shade: 'dark', type: 'horizontal', shadeIntensity: 0.5, gradientToColors: ['#4ade80'], inverseColors: true, opacityFrom: 1, opacityTo: 1, stops: [0, 100]}},
         stroke: { lineCap: 'round' }, labels: ['Margen'],
      },
      customerChartOptions: {
        ...defaultChartOptions,
        chart: { ...defaultChartOptions.chart, type: 'area', height: 300, stacked: true },
        xaxis: { type: 'datetime', labels: { datetimeUTC: false, format: 'dd MMM' }},
        yaxis: { labels: { formatter: (val) => new Intl.NumberFormat('es-MX').format(val.toFixed(0)) }},
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.3, stops: [0, 90, 100]}},
        legend: { position: 'top', horizontalAlign: 'right' }
      }
    };
  },
  computed: {
    isLoadingAny() {
        return Object.values(this.isLoading).some(val => val);
    },
    apiParams() {
        const params = {};
        if (this.activePeriod === 'custom' && this.customDateRange?.length === 2) {
            params.start_date = this.customDateRange[0];
            params.end_date = this.customDateRange[1];
        } else {
            params.period = this.activePeriod;
        }
        return params;
    },
    customerAmountSeries() {
        return this.transformCustomerData('total_amount');
    },
    customerQuantitySeries() {
        return this.transformCustomerData('total_quantity');
    }
  },
  methods: {
    fetchAllData() {
        this.fetchTopProducts();
        this.fetchTopCustomers();
        this.fetchSalesMetrics();
        this.fetchProductFamiliesSales();
        
        // Clear details if selection context changes
        if (this.selectedProduct) this.fetchProductSales();
        if (this.selectedCustomer) this.fetchCustomerSalesDetails();
    },
    async fetchTopProducts() {
      this.isLoading.topProducts = true;
      try {
        const response = await axios.get('/api/sales-analysis/top-products', { params: this.apiParams });
        this.topProducts = response.data;
      } catch (e) { console.error(e); } finally { this.isLoading.topProducts = false; }
    },
    async fetchProductSales() {
      if (!this.selectedProduct) return;
      this.isLoading.productSales = true;
      try {
        const response = await axios.get(`/api/sales-analysis/product-sales/${this.selectedProduct.id}`, { params: this.apiParams });
        this.productSales = response.data;
      } catch (e) { console.error(e); } finally { this.isLoading.productSales = false; }
    },
    async fetchTopCustomers() {
      this.isLoading.topCustomers = true;
      try {
        const response = await axios.get(route('api.sales-analysis.top-customers'), { params: this.apiParams });
        this.topCustomers = response.data;
      } catch (e) { console.error(e); } finally { this.isLoading.topCustomers = false; }
    },
    async fetchSalesMetrics() {
      this.isLoading.salesMetrics = true;
      try {
        const response = await axios.get(route('api.sales-analysis.sales-metrics'), { params: this.apiParams });
        this.salesMetrics = response.data;
        this.updateMainCharts();
      } catch (e) { console.error(e); } finally { this.isLoading.salesMetrics = false; }
    },
    async fetchCustomerSalesDetails() {
        if (!this.selectedCustomer) return;
        this.isLoading.customerDetails = true;
        try {
            const response = await axios.get(`/api/sales-analysis/customer-sales/${this.selectedCustomer.id}`, { params: this.apiParams });
            this.customerSalesDetails = response.data;
        } catch(e) { console.error(e); } finally { this.isLoading.customerDetails = false; }
    },
    async fetchProductFamiliesSales() {
        this.isLoading.productFamiliesSales = true;
        try {
            const response = await axios.get('/api/sales-analysis/product-families-sales', { params: this.apiParams });
            this.productFamiliesSales = response.data;
        } catch(e) { console.error(e); } finally { this.isLoading.productFamiliesSales = false; }
    },
    updateMainCharts() {
        if (!this.salesMetrics) return;
        this.salesCostsChartSeries = [
            { name: 'Ventas', data: this.salesMetrics.time_series.map(i => [new Date(i.date).getTime(), i.daily_sales]) },
            { name: 'Costos', data: this.salesMetrics.time_series.map(i => [new Date(i.date).getTime(), i.daily_costs]) }
        ];
        this.marginChartSeries = [this.salesMetrics.margin_percentage || 0];
    },
    selectProduct(product) {
        this.selectedCustomer = null;
        if (this.selectedProduct?.id === product.id) {
            this.selectedProduct = null;
        } else {
            this.selectedProduct = product;
            this.fetchProductSales();
        }
    },
    selectCustomer(customer) {
        this.selectedProduct = null;
        if (this.selectedCustomer?.id === customer.id) {
            this.selectedCustomer = null;
        } else {
            this.selectedCustomer = customer;
            this.fetchCustomerSalesDetails();
        }
    },
    changePeriod(period) {
      this.activePeriod = period;
      // For non-custom periods, clear date range and fetch data
      if (period !== 'custom') {
        this.customDateRange = [];
        this.fetchAllData();
      }
      // If switching to custom, the user needs to select a date to trigger fetch
    },
    handleDateChange(dates) {
        if (dates && dates.length === 2) {
            this.fetchAllData();
        }
    },
    transformCustomerData(valueKey) {
        if (!this.customerSalesDetails.length) return [];
        const series = {};
        this.customerSalesDetails.forEach(item => {
            if (!series[item.family_name]) {
                series[item.family_name] = { name: item.family_name, data: [] };
            }
            series[item.family_name].data.push([new Date(item.date).getTime(), item[valueKey]]);
        });
        return Object.values(series);
    },
    getFamilyDonutOptions(family) {
        return {
            ...defaultChartOptions,
            chart: { type: 'donut', width: 250 },
            labels: [family.name],
            plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, showAlways: true, formatter: (w) => `${w.globals.seriesTotals[0].toFixed(1)}%` } } } } },
            tooltip: { y: { formatter: (value) => `${value.toFixed(1)}%`, title: { formatter: () => `Porcentaje:` } } },
        };
    },
    formatCurrency(value) {
      return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value ?? 0);
    },
    formatDate(dateString) {
      return new Date(dateString).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
    }
  },
  mounted() {
    // Set default range for custom picker for better UX, but don't fetch yet.
    const end = new Date();
    const start = new Date();
    start.setDate(end.getDate() - 30);
    this.customDateRange = [
        `${start.getFullYear()}-${String(start.getMonth() + 1).padStart(2, '0')}-${String(start.getDate()).padStart(2, '0')}`,
        `${end.getFullYear()}-${String(end.getMonth() + 1).padStart(2, '0')}-${String(end.getDate()).padStart(2, '0')}`
    ];

    this.fetchAllData();
  },
};
</script>

<style>
/* Estilos para el scroll en Webkit */
.overflow-y-auto::-webkit-scrollbar { width: 8px; }
.overflow-y-auto::-webkit-scrollbar-track { background: #f3f4f6; }
.overflow-y-auto::-webkit-scrollbar-thumb { background-color: #7172cc; border-radius: 10px; border: 2px solid #f3f4f6; }
.dark .overflow-y-auto::-webkit-scrollbar-track { background: #1f2937; }
.dark .overflow-y-auto::-webkit-scrollbar-thumb { background-color: #4f46e5; border: 2px solid #1f2937; }

/* Ajustes para Element Plus Date Picker en modo oscuro */
.el-date-editor {
    --el-input-bg-color: theme('colors.gray.700');
    --el-input-text-color: theme('colors.gray.300');
    --el-input-border-color: theme('colors.gray.600');
    --el-border-color: theme('colors.gray.600');
}
.el-date-range-picker {
    --el-bg-color: theme('colors.gray.800');
    --el-text-color-regular: theme('colors.gray.300');
    --el-border-color: theme('colors.gray.600');
    --el-datepicker-icon-color: theme('colors.gray.400');
    --el-datepicker-off-text-color: theme('colors.gray.500');
}
</style>
