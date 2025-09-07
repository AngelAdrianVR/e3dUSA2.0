<template>
<AppLayout title="Análisis de ventas">
  <!-- Main container with dark background -->
  <div class="min-h-screen p-4 sm:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto">
      <!-- Header -->
      <header class="mb-8">
        <h1 class="text-3xl font-bold dark:text-white tracking-tight">Análisis de Ventas</h1>
        <p class="text-gray-400 mt-1">Dashboard interactivo para visualizar el rendimiento de ventas, productos y clientes.</p>
      </header>

      <!-- Filters -->
      <div class="mb-6">
        <div class="flex items-center space-x-2 bg-white dark:bg-gray-800 p-1.5 rounded-lg max-w-xs shadow-md">
          <button
            v-for="period in periods"
            :key="period.key"
            @click="changePeriod(period.key)"
            :disabled="Object.values(isLoading).some(val => val)"
            :class="[
                'w-full text-center px-4 py-2 rounded-md text-sm font-medium transition-colors duration-200 focus:outline-none',
                activePeriod === period.key 
                ? 'bg-blue-600 text-white shadow-md' 
                : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700',
                Object.values(isLoading).some(val => val) ? 'opacity-50 cursor-not-allowed' : ''
            ]"
            >
            {{ period.label }}
        </button>

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
            <li
              v-for="(product, index) in topProducts"
              :key="product.id"
              @click="selectProduct(product)"
              :class="[
                'flex items-center space-x-4 p-3 rounded-lg cursor-pointer transition-all duration-200',
                selectedProduct && selectedProduct.id === product.id ? 'dark:bg-indigo-900 bg-blue-200  shadow-lg' : 'dark:hover:bg-gray-700 hover:bg-gray-100'
              ]"
            >
              <span :class="['font-bold text-lg w-8 text-center flex-shrink-0', selectedProduct && selectedProduct.id === product.id ? 'text-white' : 'text-secondary dark:text-indigo-400']">{{ index + 1 }}</span>
              <img :src="product.image_url || 'https://placehold.co/40x40/1f2937/9ca3af?text=N/A'" alt="Product Image" class="w-10 h-10 rounded-md object-cover bg-gray-700">
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
           <div v-else class="text-center py-10">
                <p class="text-gray-400">No se encontraron productos para este período.</p>
            </div>
        </div>

        <!-- Right Column: Product Details or Top Customers -->
        <div class="lg:col-span-2 space-y-7">
            <!-- Top Customers List (shown when no product is selected) -->
            <div v-if="!selectedProduct" class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-lg">
                <h2 class="text-lg font-semibold dark:text-white mb-4">Top 10 Clientes</h2>
                <LoadingIsoLogo v-if="isLoading.topCustomers" class="my-3" />
                <ul v-else-if="topCustomers.length" class="space-y-1">
                    <li v-for="(customer, index) in topCustomers" :key="customer.id" class="flex items-center justify-between p-3 bg-gray-100 dark:bg-gray-700/50 rounded-lg">
                        <div class="flex items-center space-x-4">
                             <span class="font-bold text-lg w-8 text-center text-secondary dark:text-indigo-400">{{ index + 1 }}</span>
                             <div>
                                <p class="font-semibold dark:text-white">{{ customer.name }}</p>
                             </div>
                        </div>
                        <p class="font-semibold text-lg text-green-400 font-mono">{{ formatCurrency(customer.total_purchased) }}</p>
                    </li>
                </ul>
                 <div v-else class="text-center py-10">
                    <p class="text-gray-400">No se encontraron clientes para este período.</p>
                </div>
            </div>

          <!-- Product Details Placeholder (shown when no product is selected) -->
          <div v-if="!selectedProduct && !isLoading.topCustomers" class="bg-white dark:bg-gray-800 p-7 rounded-xl shadow-lg flex flex-col justify-center items-center min-h-[300px]">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-600 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="text-lg font-semibold dark:text-white">Selecciona un Producto</h3>
            <p class="text-gray-400 text-center mt-1">Haz clic en un producto de la lista para ver sus detalles.</p>
          </div>

          <!-- Selected Product Details View -->
          <div v-if="selectedProduct" class="space-y-8">
            <!-- Product Info Card -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold dark:text-white">Detalles del Producto</h2>
                    <button @click="selectedProduct = null" class="text-gray-400 hover:text-red-500 text-xl transition">&times;</button>
                </div>
                <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-4 sm:space-y-0 sm:space-x-6">
                    <img :src="selectedProduct.image_url || 'https://placehold.co/128x128/1f2937/9ca3af?text=N/A'" alt="Product Image" class="w-32 h-32 rounded-lg object-cover bg-gray-700 flex-shrink-0">
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

            <!-- Product Sales Table -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg">
                <h2 class="text-lg font-semibold dark:text-white mb-4">Historial de Ventas</h2>
                <LoadingIsoLogo v-if="isLoading.productSales" class="my-3" />
                <div v-else-if="productSales.length" class="overflow-x-auto max-h-[50vh] overflow-y-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-600 dark:text-gray-400 uppercase bg-gray-300 dark:bg-gray-700/50 sticky top-0">
                            <tr>
                                <th scope="col" class="px-6 py-3">Fecha</th>
                                <th scope="col" class="px-6 py-3">Cliente / Sucursal</th>
                                <th scope="col" class="px-6 py-3 text-right">Cantidad</th>
                                <th scope="col" class="px-6 py-3 text-right">Precio Unitario</th>
                                <th scope="col" class="px-6 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="sale in productSales" :key="sale.sale_id" class="border-b dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 font-medium whitespace-nowrap text-gray-500 dark:text-white">{{ formatDate(sale.created_at) }}</td>
                                <td class="px-6 py-4 dark:text-white">{{ sale.branch_name }}</td>
                                <td class="px-6 py-4 text-right font-mono dark:text-white">{{ sale.quantity }}</td>
                                <td class="px-6 py-4 text-right font-mono dark:text-white">{{ formatCurrency(sale.price) }}</td>
                                <td class="px-6 py-4 text-right font-semibold text-indigo-400 font-mono">{{ formatCurrency(sale.quantity * sale.price) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="text-center py-10">
                    <p class="text-gray-400">No se encontraron ventas para este producto en el período seleccionado.</p>
                </div>
            </div>
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
import { useDark } from '@vueuse/core'
import VueApexCharts from 'vue3-apexcharts';

const defaultChartOptions = {
  chart: {
    toolbar: { show: false },
    zoom: { enabled: false },
    foreColor: '#9CA3AF', // text-gray-400
  },
  grid: {
    borderColor: '#374151', // border-gray-700
    strokeDashArray: 4,
  },
  tooltip: {
    theme: 'dark',
  },
  markers: {
    size: 5,
    hover: { size: 7 },
    strokeColors: '#111827', // bg-gray-900
  },
};

export default {
  name: 'SalesAnalysisDashboard',
  components:{
    AppLayout,
    LoadingIsoLogo,
    apexchart: VueApexCharts,
  },
  data() {
    return {
      // Data
      topProducts: [],
      topCustomers: [],
      selectedProduct: null,
      productSales: [],
      salesMetrics: null,
      
      // State
      activePeriod: 'month',
      periods: [
        { key: 'month', label: 'Este Mes' },
        { key: 'year', label: 'Este Año' },
        { key: 'all', label: 'Histórico' },
      ],
      isLoading: {
        topProducts: false,
        productSales: false,
        topCustomers: false,
        salesMetrics: false,
      },
      
      // Charts
      salesCostsChartSeries: [],
      salesCostsChartOptions: {
        ...defaultChartOptions,
        chart: { ...defaultChartOptions.chart, type: 'area', height: 350 },
        xaxis: {
          type: 'datetime',
          labels: { datetimeUTC: false, format: 'dd MMM' }
        },
        yaxis: {
          labels: {
            formatter: (val) => `$${new Intl.NumberFormat('es-MX').format(val.toFixed(0))}`,
          },
        },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        colors: ['#4F46E5', '#10B981'], // Indigo, Green
        fill: {
          type: 'gradient',
          gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.7,
            opacityTo: 0.1,
            stops: [0, 90, 100],
          },
        },
        legend: {
            position: 'top',
            horizontalAlign: 'right',
        }
      },

      marginChartSeries: [0],
      marginChartOptions: {
         ...defaultChartOptions,
         chart: { type: 'radialBar', height: 120 },
         plotOptions: {
            radialBar: {
                startAngle: -135,
                endAngle: 135,
                hollow: {
                    margin: 0,
                    size: '70%',
                    background: 'transparent',
                },
                track: {
                    background: '#374151', // gray-700
                    strokeWidth: '100%',
                },
                dataLabels: {
                    name: { show: false },
                    value: {
                        fontSize: '22px',
                        fontFamily: 'monospace',
                        fontWeight: 'bold',
                        color: this.isDarkMode() ? '#FFFFFF' : '#4B5563', // blanco o gris
                        offsetY: 8,
                        formatter: (val) => `${val ? val.toFixed(1) : 0}%`,
                    },
                },
            },
         },
         fill: {
            type: 'gradient',
            gradient: {
                shade: 'dark',
                type: 'horizontal',
                shadeIntensity: 0.5,
                gradientToColors: ['#4ade80'], // green-400
                inverseColors: true,
                opacityFrom: 1,
                opacityTo: 1,
                stops: [0, 100],
            },
         },
         stroke: { lineCap: 'round' },
         labels: ['Margen'],
      },
    };
  },
  methods: {
    async fetchAllData() {
        this.fetchTopProducts();
        this.fetchTopCustomers();
        this.fetchSalesMetrics();

        if (this.selectedProduct) {
            this.fetchProductSales();
        } else {
            this.productSales = [];
        }
    },
    isDarkMode() {
      return document.documentElement.classList.contains('dark')
    },

    async fetchTopProducts() {
      this.isLoading.topProducts = true;
      try {
        const response = await axios.get('/api/sales-analysis/top-products', {
          params: { period: this.activePeriod }
        });
        this.topProducts = response.data;
      } catch (error) {
        console.error("Error fetching top products:", error);
      } finally {
        this.isLoading.topProducts = false;
      }
    },
    
    async fetchProductSales() {
      if (!this.selectedProduct) return;
      this.isLoading.productSales = true;
      this.productSales = [];
      try {
        const response = await axios.get(`/api/sales-analysis/product-sales/${this.selectedProduct.id}`, {
          params: { period: this.activePeriod }
        });
        this.productSales = response.data;
      } catch (error) {
        console.error("Error fetching product sales:", error);
      } finally {
        this.isLoading.productSales = false;
      }
    },

    async fetchTopCustomers() {
      this.isLoading.topCustomers = true;
      try {
        const response = await axios.get(route('api.sales-analysis.top-customers'), {
          params: { period: this.activePeriod }
        });
        this.topCustomers = response.data;
      } catch (error) {
        console.error("Error fetching top customers:", error);
      } finally {
        this.isLoading.topCustomers = false;
      }
    },

    async fetchSalesMetrics() {
      this.isLoading.salesMetrics = true;
      this.salesMetrics = null;
      try {
        const response = await axios.get(route('api.sales-analysis.sales-metrics'), {
          params: { period: this.activePeriod }
        });
        this.salesMetrics = response.data;
        this.updateCharts();
      } catch (error) {
        console.error("Error fetching sales metrics:", error);
      } finally {
        this.isLoading.salesMetrics = false;
      }
    },

    updateCharts() {
        if (!this.salesMetrics) return;

        const timeSeries = this.salesMetrics.time_series;
        this.salesCostsChartSeries = [
            {
                name: 'Ventas',
                data: timeSeries.map(item => [new Date(item.date).getTime(), item.daily_sales])
            },
            {
                name: 'Costos',
                data: timeSeries.map(item => [new Date(item.date).getTime(), item.daily_costs])
            }
        ];

        this.marginChartSeries = [this.salesMetrics.margin_percentage || 0];
    },

    selectProduct(product) {
        if (this.selectedProduct && this.selectedProduct.id === product.id) {
            this.selectedProduct = null;
            this.productSales = [];
        } else {
            this.selectedProduct = product;
            this.fetchProductSales();
        }
    },
    changePeriod(period) {
      this.activePeriod = period;
      this.selectedProduct = null;
      this.fetchAllData();
    },
    formatCurrency(value) {
      if (value === null || value === undefined) return '$0.00';
      return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
      }).format(value);
    },
    formatDate(dateString) {
      const options = { year: 'numeric', month: 'short', day: 'numeric' };
      return new Date(dateString).toLocaleDateString('es-MX', options);
    }
  },
  mounted() {
    this.fetchAllData();
  },
};
</script>

<style scoped>
/* Estilos para la barra de scroll en navegadores Webkit (Chrome, Safari) */
.overflow-y-auto::-webkit-scrollbar {
  width: 8px;
}

/* Light mode */
.overflow-y-auto::-webkit-scrollbar-track {
  background: #f3f4f6; /* bg-gray-100 */
}

.overflow-y-auto::-webkit-scrollbar-thumb {
  background-color: #7172cc; /* bg-indigo-500 */
  border-radius: 10px;
  border: 2px solid #f3f4f6; /* bg-gray-100 */
}

/* Dark mode */
.dark .overflow-y-auto::-webkit-scrollbar-track {
  background: #1f2937; /* bg-gray-800 */
}

.dark .overflow-y-auto::-webkit-scrollbar-thumb {
  background-color: #4f46e5; /* bg-indigo-600 */
  border-radius: 10px;
  border: 2px solid #1f2937; /* bg-gray-800 */
}
</style>
