<template>
  <AppLayout title="Proyección de Compras">
    <div class="min-h-screen p-4 sm:p-6 lg:p-8">
      <div class="max-w-[85rem] mx-auto space-y-6">
        
        <!-- Header -->
        <header class="mb-8">
          <h1 class="text-3xl font-bold dark:text-white tracking-tight">Proyección de Compras (Importaciones)</h1>
          <p class="text-gray-500 mt-1 dark:text-gray-400">Analiza el historial de ventas para proyectar la compra de productos.</p>
        </header>

        <!-- Control Panel (Filtros) -->
        <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <!-- Fechas -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Rango de Fechas (Ventas)</label>
              <div class="flex items-center gap-2">
                <el-date-picker
                  v-model="filters.start_date"
                  type="date"
                  placeholder="Fecha inicio"
                  value-format="YYYY-MM-DD"
                  class="w-full !rounded-lg"
                />
                <span class="text-gray-400 font-medium">a</span>
                <el-date-picker
                  v-model="filters.end_date"
                  type="date"
                  placeholder="Fecha fin"
                  value-format="YYYY-MM-DD"
                  class="w-full !rounded-lg"
                />
              </div>
            </div>

            <!-- Modalidad de Productos -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Filtro de Productos</label>
              <div class="flex items-center gap-4 mt-2">
                <label class="inline-flex items-center cursor-pointer">
                  <input type="radio" value="all" v-model="filters.product_mode" class="text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800">
                  <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Todos</span>
                </label>
                <label class="inline-flex items-center cursor-pointer">
                  <input type="radio" value="selected" v-model="filters.product_mode" class="text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800">
                  <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Seleccionar Específicos</span>
                </label>
              </div>
              <div v-if="filters.product_mode === 'selected'" class="mt-3">
                <button @click="openProductModal" class="text-sm bg-blue-50 text-blue-600 hover:bg-blue-100 px-3 py-1.5 rounded-md font-medium border border-blue-200 transition">
                  Seleccionar Productos ({{ filters.product_ids.length }})
                </button>
              </div>
            </div>

            <!-- Botones de Acción -->
            <div class="flex items-end justify-end gap-3 flex-col sm:flex-row">
              <button 
                @click="exportToExcel" 
                :disabled="!reportData || isLoading.export" 
                class="w-full sm:w-auto bg-green-600 hover:bg-green-700 text-white px-4 py-2.5 rounded-lg text-sm font-bold transition flex items-center justify-center gap-2 disabled:opacity-50"
                title="Descargar en Excel"
              >
                <svg v-if="isLoading.export" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <i v-else class="fa-solid fa-file-excel"></i>
                Exportar Excel
              </button>
              
              <button 
                @click="generateReport" 
                :disabled="isLoading.report" 
                class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg text-sm font-bold transition flex items-center justify-center gap-2 disabled:opacity-50"
              >
                <svg v-if="isLoading.report" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                Generar Proyección
              </button>
            </div>
          </div>
        </div>

        <div v-if="reportData" class="space-y-6">
          <!-- KPIs -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
              <p class="text-sm text-gray-500 dark:text-gray-400">Total Unidades Vendidas</p>
              <h3 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ formatNumber(reportData.kpis.total_sold) }}</h3>
            </div>
            <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
              <p class="text-sm text-gray-500 dark:text-gray-400">Promedio Mensual (Global)</p>
              <h3 class="text-2xl font-bold text-green-600 mt-1">{{ formatNumber(reportData.kpis.monthly_average) }}</h3>
            </div>
            <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
              <p class="text-sm text-gray-500 dark:text-gray-400">Producto Estrella</p>
              <h3 class="text-lg font-bold text-blue-600 mt-1 truncate" :title="reportData.kpis.top_product">{{ reportData.kpis.top_product }}</h3>
            </div>
            <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
              <p class="text-sm text-gray-500 dark:text-gray-400">Alertas de Stock Crítico</p>
              <h3 class="text-2xl font-bold text-red-600 mt-1">{{ reportData.kpis.critical_alerts }} prods.</h3>
            </div>
          </div>

          <!-- Gráfica -->
          <div v-if="reportData.chart.series.length > 0" class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Tendencia Mensual (Top 5 Productos)</h3>
            <apexchart type="line" height="350" :options="chartOptions" :series="reportData.chart.series"></apexchart>
          </div>

          <!-- Tabla de Proyección -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="p-5 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
              <h3 class="text-lg font-bold text-gray-800 dark:text-white">Proyección de Compras y Alertas</h3>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-left border-collapse">
                <thead>
                  <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider">
                    <th class="p-4 font-semibold">Producto</th>
                    <th class="p-4 font-semibold text-right border-l border-gray-200 dark:border-gray-600">Stock Actual</th>
                    <th class="p-4 font-semibold text-right">Vendido</th>
                    <th class="p-4 font-semibold text-right">Prom. Mensual</th>
                    <th class="p-4 font-semibold text-right bg-blue-50/50 dark:bg-blue-900/20">Proyección (3 Meses)</th>
                    <th class="p-4 font-semibold text-right bg-orange-50/50 dark:bg-orange-900/20">Faltante (Sugerencia)</th>
                    <th class="p-4 font-semibold text-center w-56">Estado</th>
                  </tr>
                </thead>
                <tbody class="text-sm divide-y divide-gray-100 dark:divide-gray-700">
                  <tr v-for="item in reportData.table" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                    
                    <td class="p-4 font-medium text-gray-900 dark:text-white">
                      <div class="flex items-center gap-3">
                        <!-- Imagen pequeña en la tabla con handler de error -->
                        <img v-if="item.image_url" 
                             @click.stop="viewLargeImage(item.image_url)" 
                             :src="item.image_url" 
                             alt="Img" 
                             @error="handleImageError"
                             class="w-10 h-10 rounded object-cover border border-gray-200 dark:border-gray-600 cursor-zoom-in hover:opacity-80 transition">
                        <div v-else class="w-10 h-10 rounded bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-[10px] text-gray-400 border border-gray-200 dark:border-gray-600">N/A</div>
                        
                        <div class="flex flex-col">
                          <span>{{ item.name }}</span>
                          <!-- Código del producto debajo del nombre -->
                          <span class="text-xs text-gray-500 font-mono mt-0.5">{{ item.code }}</span>
                        </div>
                      </div>
                    </td>

                    <!-- Stock Actual -->
                    <td class="p-4 text-right font-bold text-gray-800 dark:text-gray-200 border-l border-gray-100 dark:border-gray-700">
                      {{ formatNumber(item.current_stock) }} u.
                    </td>

                    <td class="p-4 text-right text-gray-600 dark:text-gray-400">{{ formatNumber(item.total_sold) }}</td>
                    <td class="p-4 text-right font-semibold text-gray-700 dark:text-gray-300">{{ formatNumber(item.monthly_average) }} /m</td>
                    
                    <!-- Proyección Total (3 Meses) -->
                    <td class="p-4 text-right bg-blue-50/30 dark:bg-blue-900/10 font-bold text-blue-700 dark:text-blue-400">
                      {{ formatNumber(item.monthly_average * 3) }} u.
                    </td>

                    <!-- Faltante para cumplir la proyección -->
                    <td class="p-4 text-right bg-orange-50/30 dark:bg-orange-900/10 font-bold text-orange-700 dark:text-orange-400">
                      {{ formatNumber(item.to_order) }} u.
                    </td>
                    
                    <td class="p-4 text-center">
                      <span :class="item.status === 'Pedir Pronto' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'" class="text-xs px-2 py-1 rounded-full font-medium">
                        {{ item.status }}
                      </span>
                    </td>
                  </tr>
                  <tr v-if="reportData.table.length === 0">
                    <td colspan="7" class="p-8 text-center text-gray-500">No hay ventas registradas en el periodo con los filtros seleccionados.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Seleccionador de Productos -->
    <div v-if="modals.productSelector" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col">
        <div class="p-5 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
          <h2 class="text-xl font-bold text-gray-800 dark:text-white">Seleccionar Productos Comprables</h2>
          <button @click="closeProductModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>
        
        <div class="p-4 border-b border-gray-100 dark:border-gray-700">
          <input type="text" v-model="productSearch" @input="debouncedFetchProducts" placeholder="Buscar por código o nombre..." class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div class="p-4 flex-1 overflow-y-auto bg-gray-50 dark:bg-gray-900">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <label v-for="product in productsList" :key="product.id" class="flex items-center p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer hover:shadow-md transition">
              <input type="checkbox" :value="product.id" v-model="filters.product_ids" class="rounded text-blue-600 focus:ring-blue-500 mr-3">
              <!-- Imagen pequeña del modal con handler de error -->
              <img @click.stop="viewLargeImage(product.image_url)" 
                   :src="product.image_url" 
                   alt="Img" 
                   @error="handleImageError"
                   class="w-12 h-12 rounded object-cover border border-gray-100 mr-3 cursor-zoom-in">
              <div class="flex-1 min-w-0">
                <p class="text-xs font-mono text-gray-500 truncate">{{ product.code }}</p>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate">{{ product.name }}</p>
              </div>
            </label>
          </div>
          
          <div class="mt-6 text-center">
            <button v-if="pagination.currentPage < pagination.lastPage" @click="loadMoreProducts" :disabled="isLoading.products" class="bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg text-sm transition">
              {{ isLoading.products ? 'Cargando...' : 'Cargar más productos' }}
            </button>
          </div>
        </div>

        <div class="p-4 border-t border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 flex justify-end gap-3 rounded-b-xl">
          <button @click="filters.product_ids = []" class="px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg font-medium">Limpiar</button>
          <button @click="closeProductModal" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg text-sm font-bold">Aceptar y Cerrar</button>
        </div>
      </div>
    </div>

    <!-- Image Viewer Modal -->
    <div v-if="modals.imageViewer" @click="modals.imageViewer = false" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 p-4 cursor-zoom-out">
      <!-- Imagen en grande con handler de error -->
      <img :src="currentLargeImage" 
           @error="handleImageError"
           class="max-w-full max-h-[90vh] rounded-lg shadow-2xl object-contain">
    </div>

  </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import VueApexCharts from "vue3-apexcharts";
import axios from "axios";

export default {
  name: 'StockProjectionDashboard',
  components: {
    AppLayout,
    apexchart: VueApexCharts
  },
  data() {
    return {
      filters: {
        start_date: new Date(new Date().setMonth(new Date().getMonth() - 6)).toISOString().split('T')[0], // 6 meses atras
        end_date: new Date().toISOString().split('T')[0],
        product_mode: 'all',
        product_ids: []
      },
      reportData: null,
      
      // Productos Modal Data
      modals: { productSelector: false, imageViewer: false },
      productsList: [],
      productSearch: '',
      searchTimeout: null,
      pagination: { currentPage: 1, lastPage: 1 },
      currentLargeImage: '',

      // Se agregó "export" al state the loading
      isLoading: { report: false, products: false, export: false }
    };
  },
  computed: {
    chartOptions() {
      return {
        chart: { toolbar: { show: false }, zoom: { enabled: false }, foreColor: '#9CA3AF' },
        xaxis: { categories: this.reportData?.chart?.categories || [] },
        stroke: { curve: 'smooth', width: 3 },
        dataLabels: { enabled: false },
        tooltip: { theme: 'light' },
        grid: { borderColor: '#E5E7EB', strokeDashArray: 4 },
      };
    }
  },
  methods: {
    async generateReport() {
      if (this.filters.product_mode === 'selected' && this.filters.product_ids.length === 0) {
        alert("Selecciona al menos un producto para generar el reporte.");
        return;
      }
      
      this.isLoading.report = true;
      try {
        const response = await axios.post(route('stock-projection.report'), this.filters);
        this.reportData = response.data;
      } catch (error) {
        console.error(error);
        alert("Ocurrió un error al generar la proyección.");
      } finally {
        this.isLoading.report = false;
      }
    },
    
    // Nueve método para exportar los datos a Excel
    async exportToExcel() {
      if (this.filters.product_mode === 'selected' && this.filters.product_ids.length === 0) {
        alert("Selecciona al menos un producto para generar el reporte.");
        return;
      }

      this.isLoading.export = true;
      try {
        const response = await axios.post(route('stock-projection.export'), this.filters, {
          responseType: 'blob' // Es vital el blob para procesar el archivo Excel
        });
        
        // Crear un link temporal para forzar la descarga en el navegador
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        
        // Nombre del archivo de forma temporal, se sobreescribe con el del back
        link.setAttribute('download', `Proyeccion_Compras_${new Date().getTime()}.xlsx`); 
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      } catch (error) {
        console.error("Error al exportar:", error);
        alert("Ocurrió un error al exportar el archivo Excel.");
      } finally {
        this.isLoading.export = false;
      }
    },
    
    // Métodos para el modal de productos
    openProductModal() {
      this.modals.productSelector = true;
      if (this.productsList.length === 0) {
        this.fetchProducts(1);
      }
    },
    closeProductModal() {
      this.modals.productSelector = false;
    },
    debouncedFetchProducts() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => {
        this.productsList = []; // Limpiar lista al buscar
        this.fetchProducts(1);
      }, 500);
    },
    async fetchProducts(page = 1) {
      this.isLoading.products = true;
      try {
        const response = await axios.get(route('stock-projection.products'), {
          params: { page: page, search: this.productSearch }
        });
        
        if (page === 1) {
          this.productsList = response.data.data;
        } else {
          this.productsList.push(...response.data.data);
        }
        
        this.pagination.currentPage = response.data.current_page;
        this.pagination.lastPage = response.data.last_page;
      } catch (error) {
        console.error("Error cargando productos", error);
      } finally {
        this.isLoading.products = false;
      }
    },
    loadMoreProducts() {
      if (this.pagination.currentPage < this.pagination.lastPage) {
        this.fetchProducts(this.pagination.currentPage + 1);
      }
    },
    viewLargeImage(url) {
      this.currentLargeImage = url;
      this.modals.imageViewer = true;
    },

    // Manejador de error en imágenes (Fallback a Producción)
    handleImageError(event) {
        const img = event.target;
        const currentSrc = img.src;
        const prodDomain = 'https://www.intranetemblems3d.dtw.com.mx';
        
        if (img.dataset.fallbackAttempted || currentSrc.includes(prodDomain)) return;
        img.dataset.fallbackAttempted = "true";

        try {
            const urlObj = new URL(currentSrc);
            img.src = prodDomain + urlObj.pathname;
        } catch (e) {
            img.src = currentSrc.replace(/^https?:\/\/[^\/]+/, prodDomain);
        }
    },
    
    // Helpers
    formatNumber(value) {
      return new Intl.NumberFormat('es-MX').format(value || 0);
    }
  },
  mounted() {
    // Generar reporte automático al inicio (con modo 'all' por defecto)
    this.generateReport();
  }
};
</script>