<template>
<AppLayout title="Análisis de ventas">
  <!-- Main container -->
  <div class="min-h-screen p-4 sm:p-6 lg:p-8">
    <div class="max-w-[85rem] mx-auto">
      <!-- Header -->
      <header class="mb-8">
        <h1 class="text-3xl font-bold dark:text-white tracking-tight">Análisis de Ventas</h1>
        <p class="text-gray-400 mt-1">Dashboard interactivo para visualizar el rendimiento de ventas.</p>
      </header>

      <!-- Currency Tabs -->
      <div class="mb-4 border-b border-gray-300 dark:border-gray-500">
          <nav class="-mb-px flex space-x-8" aria-label="Tabs">
              <button @click="changeCurrency('MXN')" :class="[activeCurrency === 'MXN' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm']">
                  Ventas MXN
              </button>
              <button @click="changeCurrency('USD')" :class="[activeCurrency === 'USD' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm']">
                  Ventas USD
              </button>
          </nav>
      </div>

      <!-- Filters -->
      <DashboardFilters
        :active-period="activePeriod"
        :periods="periods"
        v-model:custom-date-range="customDateRange"
        :is-loading-any="isLoadingAny"
        @change-period="changePeriod"
        @handle-date-change="handleDateChange"
      />

      <!-- KPIs and Main Charts -->
      <LoadingIsoLogo v-if="isLoading.salesMetrics" class="my-3" />
      <div v-else-if="salesMetrics">
        <KpiCards
          :sales-metrics="salesMetrics"
          :margin-chart-options="marginChartOptions"
          :margin-chart-series="marginChartSeries"
          :format-currency="formatCurrency"
        />
        <SalesCostsChart
          :options="salesCostsChartOptions"
          :series="salesCostsChartSeries"
          :currency="activeCurrency"
        />
      </div>

      <!-- Product and Customer Lists -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-5">
        <TopProductsList
          :top-products="topProducts"
          :is-loading="isLoading.topProducts"
          :selected-product="selectedProduct"
          @select-product="selectProduct"
        />
        <ProductDetails class="col-span-2"
          v-if="selectedProduct"
          :selected-product="selectedProduct"
          :product-sales="productSales"
          :is-loading="isLoading.productSales"
          @close="selectedProduct = null"
          :format-currency="formatCurrency"
          :format-date="formatDate"
          :currency="activeCurrency"
        />
        
      </div>

      <!-- Details Section -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-5">
        <TopCustomersList
          :top-customers="topCustomers"
          :is-loading="isLoading.topCustomers"
          :selected-customer="selectedCustomer"
          @select-customer="selectCustomer"
          :format-currency="formatCurrency"
        />
        <CustomerDetails class="col-span-2"
          v-if="selectedCustomer"
          :selected-customer="selectedCustomer"
          :customer-chart-options="customerChartOptions"
          :customer-amount-series="customerAmountSeries"
          :customer-quantity-series="customerQuantitySeries"
          :is-loading="isLoading.customerDetails"
          :currency="activeCurrency"
          @close="selectedCustomer = null"
        />
      </div>

      <!-- Sellers Section -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-5">
        <TopSellersList
          :top-sellers="topSellers"
          :is-loading="isLoading.topSellers"
          :selected-seller="selectedSeller"
          @select-seller="selectSeller"
          :format-currency="formatCurrency"
        />
        <SellerDetails class="col-span-2"
          v-if="selectedSeller"
          :selected-seller="selectedSeller"
          :seller-chart-options="sellerChartOptions"
          :seller-chart-series="sellerChartSeries"
          :is-loading="isLoading.sellerDetails"
          @close="selectedSeller = null"
          :format-currency="formatCurrency"
        />
      </div>

      <!-- Product Families Donut Charts -->
      <FamiliesDonutCharts
          class="mt-5"
          :product-families-sales="productFamiliesSales"
          :is-loading="isLoading.productFamiliesSales"
          :get-family-donut-options="getFamilyDonutOptions"
          :format-currency="formatCurrency"
          :currency="activeCurrency"
      />
    </div>
  </div>
</AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import DashboardFilters from './Components/DashboardFilters.vue';
import KpiCards from './Components/KpiCards.vue';
import SalesCostsChart from './Components/SalesCostsChart.vue';
import TopProductsList from './Components/TopProductsList.vue';
import TopCustomersList from './Components/TopCustomersList.vue';
import ProductDetails from './Components/ProductDetails.vue';
import CustomerDetails from './Components/CustomerDetails.vue';
import TopSellersList from './Components/TopSellersList.vue';
import SellerDetails from './Components/SellerDetails.vue';
import FamiliesDonutCharts from './Components/FamiliesDonutCharts.vue';
import axios from 'axios';
// import { useDark } from '@vueuse/core';
import { ref, watch } from 'vue';

export default {
  name: 'SalesAnalysisDashboard',
  components:{
    AppLayout,
    LoadingIsoLogo,
    DashboardFilters,
    KpiCards,
    SalesCostsChart,
    TopProductsList,
    TopCustomersList,
    ProductDetails,
    CustomerDetails,
    FamiliesDonutCharts,
    TopSellersList,
    SellerDetails,
  },
  setup() {
    // Composition API hook to get dark mode reactivity
    // const isDarkMode = useDark();
    return { isDarkMode: true };
  },
  data() {
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
      topSellers: [],
      selectedSeller: null,
      sellerSalesDetails: [],

      // State
      activePeriod: 'month',
      activeCurrency: 'MXN', // New state for currency
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
        topSellers: false,
        sellerDetails: false,
      },
       salesCostsChartSeries: [],
       marginChartSeries: [],
    };
  },
  computed: {
    isLoadingAny() {
        return Object.values(this.isLoading).some(val => val);
    },
    apiParams() {
        const params = {
            currency: this.activeCurrency, // Add currency to all API requests
        };
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
    },

    // --- Chart Series ---
    sellerChartSeries() {
        if (!this.sellerSalesDetails) return [];
        return this.sellerSalesDetails.map(item => item.percentage);
    },

    // --- Reactive Chart Options ---
    chartForeColor() {
      return this.isDarkMode ? '#9CA3AF' : '#374151'; 
    },
    chartGridColor() {
      return this.isDarkMode ? '#9CA3AF' : '#E5E7EB';
    },
    baseChartOptions() {
      return {
        chart: { toolbar: { show: false }, zoom: { enabled: false }, foreColor: this.chartForeColor },
        grid: { borderColor: this.chartGridColor, strokeDashArray: 4 },
        tooltip: { theme: this.isDarkMode ? 'dark' : 'light' },
        markers: { size: 5, hover: { size: 7 }, strokeColors: this.isDarkMode ? '#111827' : '#FFFFFF' },
      }
    },
    salesCostsChartOptions() {
      return {
        ...this.baseChartOptions,
        chart: { ...this.baseChartOptions.chart, type: 'area', height: 350 },
        xaxis: { type: 'datetime', labels: { datetimeUTC: false, format: 'dd MMM' }},
        yaxis: { labels: { formatter: (val) => this.formatCurrency(val) }}, // Use dynamic formatter
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        colors: ['#4F46E5', '#10B981'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.1, stops: [0, 90, 100]}},
        legend: { position: 'top', horizontalAlign: 'right', labels: { colors: [this.chartForeColor, this.chartForeColor] } }
      }
    },
    marginChartOptions() {
      return {
        ...this.baseChartOptions,
        chart: { type: 'radialBar', height: 120 },
        plotOptions: {
          radialBar: {
            startAngle: -135, endAngle: 135, hollow: { size: '70%' },
            track: { background: this.isDarkMode ? '#374151' : '#E5E7EB', strokeWidth: '100%'},
            dataLabels: {
              name: { show: false },
              value: { fontSize: '22px', fontFamily: 'monospace', fontWeight: 'bold', color: this.chartForeColor, offsetY: 8, formatter: (val) => `${val ? val.toFixed(1) : 0}%` },
            },
          },
        },
        fill: { type: 'gradient', gradient: { shade: 'dark', type: 'horizontal', shadeIntensity: 0.5, gradientToColors: ['#4ade80'], inverseColors: true, opacityFrom: 1, opacityTo: 1, stops: [0, 100]}},
        stroke: { lineCap: 'round' }, labels: ['Margen'],
      }
    },
    customerChartOptions() {
      return {
        ...this.baseChartOptions,
        chart: { ...this.baseChartOptions.chart, type: 'area', height: 300, stacked: true },
        xaxis: { type: 'datetime', labels: { datetimeUTC: false, format: 'dd MMM' }},
        yaxis: { labels: { formatter: (val) => new Intl.NumberFormat('es-MX').format(val.toFixed(0)) }},
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.3, stops: [0, 90, 100]}},
        legend: { position: 'top', horizontalAlign: 'right', labels: { colors: this.chartForeColor } }
      }
    },
    sellerChartOptions() {
      return {
        fill: {
          type: 'gradient',
        },
        chart: { type: 'donut', height: 300 },
        labels: this.sellerSalesDetails?.map(item => item.name) || [],
        plotOptions: {
          pie: {
            donut: {
              labels: {
                show: true,
                total: {
                  show: true,
                  showAlways: true,
                  label: 'Total Vendido',
                  color: '#9CA3AF', 
                  formatter: () => this.formatCurrency(this.selectedSeller.total_sold),
                },
                value: {
                  color: '#9CA3AF',
                },
              }
            },
            dropShadow: {
              enabled: true,
              top: 5,
              left: 5,
              blur: 10,
              opacity: 0.35,
              color: '#000000'
            },
          }
        },
        stroke: {
          show: true,
          width: 1,
          colors: ['#fff'],
          lineCap: 'round'
        },
        dataLabels: {
          enabled: true,
          formatter: (val) => `${val.toFixed(1)}%`,
          style: {
            colors: ['#fff'],
            fontWeight: 'bold'
          }
        },
        legend: {
          position: 'bottom',
          labels: {
            colors: '#9CA3AF'
          }
        },
        tooltip: {
          y: {
            formatter: (value, { series, seriesIndex }) => {
              const total = this.sellerSalesDetails[seriesIndex]?.total || 0;
              return this.formatCurrency(total);
            },
            title: {
              formatter: (seriesName) => `${seriesName}:`
            }
          }
        }
      };
    },
  },
  methods: {
    fetchAllData() {
        this.fetchTopProducts();
        this.fetchTopCustomers();
        this.fetchSalesMetrics();
        this.fetchProductFamiliesSales();
        this.fetchTopSellers();

        if (this.selectedProduct) this.fetchProductSales();
        if (this.selectedCustomer) this.fetchCustomerSalesDetails();
        if (this.selectedSeller) this.fetchSellerSalesDetails();
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
    async fetchTopSellers() {
        this.isLoading.topSellers = true;
        try {
            const response = await axios.get(route('api.sales-analysis.top-sellers'), { params: this.apiParams });
            this.topSellers = response.data;
        } catch (e) { console.error("Error fetching top sellers:", e); } 
        finally { this.isLoading.topSellers = false; }
    },
    async fetchSellerSalesDetails() {
        if (!this.selectedSeller) return;
        this.isLoading.sellerDetails = true;
        try {
            const response = await axios.get(route('api.sales-analysis.seller-sales', { user: this.selectedSeller.id }), { params: this.apiParams });
            this.sellerSalesDetails = response.data;
        } catch (e) { console.error("Error fetching seller details:", e); } 
        finally { this.isLoading.sellerDetails = false; }
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
        if (this.selectedProduct?.id === product.id) {
            this.selectedProduct = null;
        } else {
            this.selectedProduct = product;
            this.fetchProductSales();
        }
    },
    selectCustomer(customer) {
        if (this.selectedCustomer?.id === customer.id) {
            this.selectedCustomer = null;
        } else {
            this.selectedCustomer = customer;
            this.fetchCustomerSalesDetails();
        }
    },
    selectSeller(seller) {
        if (this.selectedSeller?.id === seller.id) {
            this.selectedSeller = null;
        } else {
            this.selectedSeller = seller;
            this.fetchSellerSalesDetails();
        }
    },
    changePeriod(period) {
      this.activePeriod = period;
      if (period !== 'custom') {
        this.customDateRange = [];
        this.fetchAllData();
      }
    },
    changeCurrency(currency) {
      this.activeCurrency = currency;
      this.fetchAllData();
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
            ...this.baseChartOptions,
            chart: { type: 'radialBar' },
            plotOptions: {
                radialBar: {
                    hollow: { size: '70%' },
                    track: { background: this.isDarkMode ? '#374151' : '#E5E7EB' },
                    dataLabels: {
                        name: { show: false },
                        value: { color: this.chartForeColor, fontSize: '24px', fontWeight: 'bold', fontFamily: 'sans-serif', formatter: (val) => `${val.toFixed(1)}%` },
                    },
                },
            },
            fill: {
                type: 'gradient',
                gradient: { shade: 'dark', type: 'horizontal', shadeIntensity: 0.5, gradientToColors: ['#34D399', '#3B82F6'], inverseColors: true, opacityFrom: 1, opacityTo: 1, stops: [0, 100] },
            },
            stroke: { lineCap: 'round' },
            labels: ['Percent'],
        };
    },
    formatCurrency(value) {
      const currencyCode = this.activeCurrency === 'USD' ? 'USD' : 'MXN';
      const locale = this.activeCurrency === 'USD' ? 'en-US' : 'es-MX';
      return new Intl.NumberFormat(locale, { style: 'currency', currency: currencyCode }).format(value ?? 0);
    },
    formatDate(dateString) {
      return new Date(dateString).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
    }
  },
  mounted() {
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
