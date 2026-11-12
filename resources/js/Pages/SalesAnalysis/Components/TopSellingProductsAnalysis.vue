<template>
  <div class="space-y-4">
    <!-- ===== Filters ===== -->
    <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-lg">
      <div class="flex flex-wrap items-center gap-3">
        <!-- Period -->
        <div class="flex items-center space-x-1 bg-gray-100 dark:bg-gray-900 p-1.5 rounded-lg">
          <button
            v-for="p in periods"
            :key="p.key"
            @click="changePeriod(p.key)"
            :disabled="loadingProducts"
            :class="[
              'px-3 py-1.5 rounded-md text-sm font-medium transition-colors duration-200 focus:outline-none',
              period === p.key
                ? 'bg-blue-600 text-white shadow-md'
                : 'text-gray-500 dark:text-gray-400 hover:bg-white dark:hover:bg-gray-700',
              loadingProducts ? 'opacity-50 cursor-not-allowed' : ''
            ]"
          >
            {{ p.label }}
          </button>
        </div>

        <!-- Custom range -->
        <el-date-picker
          v-if="period === 'custom'"
          v-model="customDateRange"
          type="daterange"
          range-separator="a"
          start-placeholder="Fecha de inicio"
          end-placeholder="Fecha de fin"
          :disabled="loadingProducts"
          format="DD/MM/YYYY"
          value-format="YYYY-MM-DD"
          @change="onCustomDateChange"
        />

        <!-- Year -->
        <el-select
          v-if="period === 'year'"
          v-model="selectedYear"
          style="width: 110px"
          :disabled="loadingProducts"
          @change="fetchProducts"
        >
          <el-option v-for="year in availableYears" :key="year" :label="year" :value="year" />
        </el-select>

        <!-- Metric -->
        <div class="flex items-center space-x-1 bg-gray-100 dark:bg-gray-900 p-1.5 rounded-lg">
          <button
            @click="changeMetric('units')"
            :disabled="loadingProducts"
            :class="[
              'px-3 py-1.5 rounded-md text-sm font-medium transition-colors duration-200 focus:outline-none',
              metric === 'units'
                ? 'bg-indigo-600 text-white shadow-md'
                : 'text-gray-500 dark:text-gray-400 hover:bg-white dark:hover:bg-gray-700'
            ]"
          >
            <i class="fa-solid fa-cubes mr-1"></i> Piezas
          </button>
          <button
            @click="changeMetric('amount')"
            v-if="canSeeMoney"
            :disabled="loadingProducts"
            :class="[
              'px-3 py-1.5 rounded-md text-sm font-medium transition-colors duration-200 focus:outline-none',
              metric === 'amount'
                ? 'bg-indigo-600 text-white shadow-md'
                : 'text-gray-500 dark:text-gray-400 hover:bg-white dark:hover:bg-gray-700'
            ]"
          >
            <i class="fa-solid fa-dollar-sign mr-1"></i> Dinero
          </button>
        </div>

        <!-- Currency (solo aplica al ranking por dinero, las piezas no dependen de la moneda) -->
        <el-select
          v-if="metric === 'amount'"
          v-model="currency"
          style="width: 160px"
          :disabled="loadingProducts"
          @change="changeCurrency"
        >
          <el-option label="Solo MXN" value="MXN" />
          <el-option label="Solo USD" value="USD" />
        </el-select>

        <!-- Product search -->
        <el-select
          v-model="searchProductId"
          filterable
          remote
          clearable
          reserve-keyword
          :remote-method="searchProducts"
          :loading="searchingProducts"
          placeholder="Buscar producto por nombre o código…"
          style="width: 300px"
          @change="onSearchProductChange"
        >
          <el-option
            v-for="item in searchResults"
            :key="item.id"
            :label="`${item.code} · ${item.name}`"
            :value="item.id"
          >
            <div class="flex items-center gap-2">
              <img
                :src="item.image_url || 'https://placehold.co/24x24/1f2937/9ca3af?text=N/A'"
                @error="handleImageError"
                class="w-6 h-6 rounded object-cover bg-gray-700 flex-shrink-0"
                alt=""
              />
              <span class="font-mono text-xs text-gray-400 flex-shrink-0">{{ item.code }}</span>
              <span class="truncate text-sm">{{ item.name }}</span>
            </div>
          </el-option>
        </el-select>

        <!-- Family -->
        <el-select
          v-model="familyId"
          placeholder="Todas las familias"
          clearable
          filterable
          style="width: 220px"
          :disabled="loadingProducts"
          @change="reloadAll"
        >
          <el-option label="Todas las familias" value="" />
          <el-option v-for="family in families" :key="family.id" :label="family.name" :value="family.id" />
        </el-select>
      </div>
    </div>

    <!-- ===== Ranking table + detalle del producto (lado a lado) ===== -->
    <div class="flex flex-col lg:flex-row gap-4 items-start">
      <div
        :class="[
          'bg-white dark:bg-gray-800 p-4 rounded-xl shadow-lg w-full transition-all duration-500 ease-in-out',
          selectedProduct ? 'lg:w-[38%] xl:w-[34%] lg:flex-shrink-0' : ''
        ]"
      >
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
          <h2 class="text-lg font-semibold dark:text-white">
            Top {{ limit }} productos
            <span class="text-xs font-normal text-gray-400 sm:text-sm">
              ({{ metricLabel }})
            </span>
          </h2>
          <div class="flex items-center gap-2">
            <span
              v-if="familyLabel"
              class="text-[11px] px-2 py-1 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300"
            >
              {{ familyLabel }}
            </span>
            <el-select
              v-model="limit"
              size="small"
              style="width: 82px"
              :disabled="loadingProducts"
              title="Cantidad de productos a mostrar"
              @change="fetchProducts"
            >
              <el-option v-for="option in limitOptions" :key="option" :label="option" :value="option" />
            </el-select>
            <button
              type="button"
              class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 text-xs font-medium transition"
              title="Imprimir los productos de la tabla"
              @click="openPrintView"
            >
              <i class="fa-solid fa-print"></i>
              <span class="hidden sm:inline">Imprimir</span>
            </button>
          </div>
        </div>

        <LoadingIsoLogo v-if="loadingProducts" class="my-3" />

        <div
          v-else-if="products.length"
          class="overflow-auto max-h-[calc(100vh-15rem)] rounded-md"
        >
          <table
            class="w-full text-sm"
            :class="selectedProduct ? '[&_th]:px-2 [&_td]:px-2 [&_th]:py-2 [&_td]:py-2' : ''"
          >
            <thead class="text-xs text-gray-600 dark:text-gray-400 uppercase bg-gray-300/70 dark:bg-gray-700/80 sticky top-0 z-10 backdrop-blur-sm">
              <tr>
                <th class="px-4 py-3 text-left">#</th>
                <th class="px-4 py-3 text-left">Imagen</th>
                <th v-if="!selectedProduct" class="px-4 py-3 text-left">Código</th>
                <th class="px-4 py-3 text-left">Producto</th>
                <th v-if="!selectedProduct" class="px-4 py-3 text-left">Familia</th>
                <th class="px-4 py-3 text-right">Piezas</th>
                <th v-if="metric === 'amount'" class="px-4 py-3 text-right">Dinero</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(product, index) in products"
                :key="product.id"
                @click="selectProduct(product)"
                :class="[
                  'cursor-pointer border-b dark:border-gray-700 transition-colors',
                  selectedProduct?.id === product.id
                    ? 'bg-indigo-100 dark:bg-indigo-900/60'
                    : 'hover:bg-gray-100 dark:hover:bg-gray-700/50'
                ]"
              >
                <td class="px-4 py-3 font-bold text-center text-secondary dark:text-indigo-400">{{ index + 1 }}</td>
                <td class="px-4 py-3">
                  <div class="relative group w-12 h-12">
                    <img
                      :src="product.image_url || 'https://placehold.co/48x48/1f2937/9ca3af?text=N/A'"
                      @error="handleImageError"
                      class="w-12 h-12 rounded-md object-cover bg-gray-700"
                      alt=""
                    />
                    <button
                      @click.stop="openImage(product)"
                      class="absolute inset-0 flex items-center justify-center bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity rounded-md text-white"
                      title="Ver imagen en grande"
                    >
                      <i class="fa-solid fa-magnifying-glass-plus"></i>
                    </button>
                  </div>
                </td>
                <td v-if="!selectedProduct" class="px-4 py-3 font-mono text-xs dark:text-gray-300 whitespace-nowrap">
                  {{ product.code }}
                </td>
                <td class="px-4 py-3">
                  <p class="font-semibold text-gray-700 dark:text-white leading-tight">{{ product.name }}</p>
                  <span v-if="product.archived_at" class="text-[10px] uppercase text-amber-500 font-semibold">Obsoleto</span>
                  <span v-if="selectedProduct" class="block text-[11px] font-mono text-gray-400">{{ product.code }}</span>
                  <span v-if="selectedProduct" class="block text-[11px] text-gray-400">{{ product.family_name }}</span>
                </td>
                <td v-if="!selectedProduct" class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                  {{ product.family_name }}
                </td>
                <td class="px-4 py-3 text-right font-mono font-semibold dark:text-white whitespace-nowrap">
                  {{ formatNumber(product.total_quantity) }}
                </td>
                <td
                  v-if="metric === 'amount'"
                  class="px-4 py-3 text-right font-mono font-semibold text-green-600 dark:text-green-400 whitespace-nowrap"
                >
                  {{ formatMoney(product.total_amount) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="text-center py-10">
          <p class="text-gray-400">No se encontraron productos vendidos en el periodo seleccionado.</p>
        </div>
      </div>

      <!-- ===== Selected product detail (aparece con transición) ===== -->
      <Transition
        enter-active-class="transition-all duration-500 ease-out"
        enter-from-class="opacity-0 translate-y-6 lg:translate-y-0 lg:translate-x-10 lg:scale-[0.97]"
        enter-to-class="opacity-100 translate-y-0 translate-x-0 scale-100"
        leave-active-class="transition-all duration-300 ease-in"
        leave-from-class="opacity-100 translate-y-0 translate-x-0 scale-100"
        leave-to-class="opacity-0 translate-y-6 lg:translate-y-0 lg:translate-x-10 lg:scale-[0.97]"
      >
        <ProductSalesPanel
          v-if="selectedProduct"
          class="w-full flex-1 min-w-0"
          :product="selectedProduct"
          :filters="apiFilters"
          :currency="effectiveCurrency"
          :format-money="formatMoney"
          :format-number="formatNumber"
          @close="selectedProduct = null"
        />
      </Transition>
    </div>

    <!-- ===== Image modal ===== -->
    <teleport to="body">
      <div
        v-if="imageProduct"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75"
        @click.self="imageProduct = null"
      >
        <div class="relative max-w-3xl w-full bg-white dark:bg-gray-800 rounded-xl shadow-2xl overflow-hidden">
          <button
            @click="imageProduct = null"
            class="absolute top-2 right-3 text-3xl leading-none text-gray-400 hover:text-red-500 transition z-10"
            title="Cerrar"
          >
            &times;
          </button>
          <img
            :src="imageProduct.image_url || 'https://placehold.co/800x600/1f2937/9ca3af?text=Sin+Imagen'"
            @error="handleImageError"
            class="w-full max-h-[70vh] object-contain bg-gray-100 dark:bg-gray-900"
            alt=""
          />
          <div class="p-4 flex flex-wrap items-center justify-between gap-2">
            <div>
              <p class="font-semibold dark:text-white">{{ imageProduct.name }}</p>
              <p class="text-xs font-mono text-gray-400">{{ imageProduct.code }}</p>
            </div>
            <div class="flex items-center gap-3 text-sm font-mono">
              <span v-if="imageProduct.total_quantity != null" class="text-gray-600 dark:text-gray-300">
                {{ formatNumber(imageProduct.total_quantity) }} pzas
              </span>
              <span
                v-if="metric === 'amount' && imageProduct.total_amount != null"
                class="text-green-600 dark:text-green-400 font-semibold"
              >
                {{ formatMoney(imageProduct.total_amount) }}
              </span>
              <a
                :href="route('catalog-products.show', imageProduct.id)"
                target="_blank"
                class="px-3 py-1.5 rounded-md bg-blue-600 text-white text-xs font-medium hover:bg-blue-700"
              >
                Abrir en catálogo
              </a>
            </div>
          </div>
        </div>
      </div>
    </teleport>
  </div>
</template>

<script>
import axios from 'axios';
import { ElDatePicker, ElSelect, ElOption } from 'element-plus';
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import ProductSalesPanel from './ProductSalesPanel.vue';

export default {
  name: 'TopSellingProductsAnalysis',
  components: { LoadingIsoLogo, ProductSalesPanel, ElDatePicker, ElSelect, ElOption },
  data() {
    const now = new Date();
    const firstDay = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
    const lastDay = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

    return {
      periods: [
        { key: 'month', label: 'Mes en curso' },
        { key: 'year', label: 'Año' },
        { key: 'custom', label: 'Personalizado' },
      ],
      period: 'month',
      customDateRange: [firstDay, lastDay],
      selectedYear: now.getFullYear(),
      availableYears: Array.from({ length: 7 }, (_, i) => now.getFullYear() - i),
      metric: 'units',
      limit: 20,
      limitOptions: [20, 30, 40, 50],
      // 'ALL' = todas las monedas (para el ranking por piezas)
      currency: 'ALL',
      familyId: '',
      families: [],
      products: [],
      selectedProduct: null,
      selectionSource: null, // 'top' | 'search'
      searchProductId: null,
      searchResults: [],
      searchingProducts: false,
      imageProduct: null,
      loadingProducts: false,
    };
  },
  computed: {
    /** Solo con permiso se pueden ver importes (ranking por dinero, columnas y moneda). */
    canSeeMoney() {
      const permissions = this.$page?.props?.auth?.user?.permissions ?? [];
      return Array.isArray(permissions) && permissions.includes('Ver cantidades de dinero');
    },
    apiFilters() {
      const params = {
        metric: this.metric,
        limit: this.limit,
      };

      // La moneda solo aplica al ranking por dinero; por piezas se suman MXN + USD.
      if (this.metric === 'amount' && this.currency !== 'ALL') {
        params.currency = this.currency;
      }

      if (this.period === 'custom' && this.customDateRange?.length === 2) {
        params.start_date = this.customDateRange[0];
        params.end_date = this.customDateRange[1];
      } else if (this.period === 'year') {
        params.start_date = `${this.selectedYear}-01-01`;
        params.end_date = `${this.selectedYear}-12-31`;
      } else {
        params.period = this.period;
      }

      if (this.familyId !== '' && this.familyId !== null && this.familyId !== undefined) {
        params.family_id = this.familyId;
      }

      return params;
    },
    familyLabel() {
      if (this.familyId === '' || this.familyId === null) return null;
      return this.families.find((f) => f.id === this.familyId)?.name ?? null;
    },
    metricLabel() {
      if (this.metric === 'units') {
        return 'por piezas · todas las monedas';
      }
      return `por dinero · ${this.currency === 'ALL' ? 'MXN' : this.currency}`;
    },
    /** Moneda que se pasa al panel de detalle (piezas = todas las monedas). */
    effectiveCurrency() {
      return this.metric === 'amount' ? this.currency : 'ALL';
    },
  },
  methods: {
    async fetchFamilies() {
      try {
        const response = await axios.get(route('api.sales-analysis.product-families'));
        this.families = response.data;
      } catch (e) {
        console.error('Error fetching product families:', e);
      }
    },
    async fetchProducts() {
      this.loadingProducts = true;
      try {
        const response = await axios.get(route('api.sales-analysis.top-selling-products'), { params: this.apiFilters });
        this.products = response.data;

        // Mantiene la selección si el producto sigue en el top. Si el producto
        // se eligió desde el buscador, la selección se conserva aunque no esté en el top.
        if (this.selectedProduct && this.selectionSource === 'top') {
          const stillThere = this.products.find((p) => p.id === this.selectedProduct.id);
          if (stillThere) {
            this.selectedProduct = stillThere;
          } else {
            this.selectedProduct = null;
            this.selectionSource = null;
          }
        }
      } catch (e) {
        console.error('Error fetching top selling products:', e);
      } finally {
        this.loadingProducts = false;
      }
    },
    reloadAll() {
      if (this.familyId === null || this.familyId === undefined) {
        this.familyId = '';
      }
      this.fetchProducts();
    },
    changePeriod(period) {
      this.period = period;
      if (period !== 'custom') {
        this.fetchProducts();
      }
    },
    onCustomDateChange(dates) {
      if (dates && dates.length === 2) {
        this.fetchProducts();
      }
    },
    changeCurrency() {
      this.reloadAll();
    },
    changeMetric(metric) {
      if (this.metric === metric) return;
      if (metric === 'amount' && !this.canSeeMoney) return;
      this.metric = metric;

      // Para rankear por dinero se necesita una moneda concreta.
      if (metric === 'amount' && this.currency === 'ALL') {
        this.currency = 'MXN';
      }

      this.fetchProducts();
    },
    selectProduct(product) {
      if (this.selectedProduct?.id === product.id) {
        this.selectedProduct = null;
        this.selectionSource = null;
      } else {
        this.selectedProduct = product;
        this.selectionSource = 'top';
      }
      this.searchProductId = null;
    },
    async searchProducts(query) {
      const term = (query || '').trim();

      if (term.length < 2) {
        this.searchResults = [];
        return;
      }

      this.searchingProducts = true;
      try {
        const response = await axios.get(route('api.sales-analysis.product-search'), { params: { q: term } });
        this.searchResults = response.data;
      } catch (e) {
        console.error('Error searching products:', e);
        this.searchResults = [];
      } finally {
        this.searchingProducts = false;
      }
    },
    onSearchProductChange(productId) {
      if (!productId) {
        if (this.selectionSource === 'search') {
          this.selectedProduct = null;
          this.selectionSource = null;
        }
        return;
      }

      const product = this.searchResults.find((p) => p.id === productId);
      if (product) {
        this.selectedProduct = product;
        this.selectionSource = 'search';
      }
    },
    openImage(product) {
      this.imageProduct = product;
    },
    /**
     * Abre (en otra pestaña) la vista imprimible con los mismos filtros.
     */
    openPrintView() {
      const url = route('sales-analysis.products-print', this.apiFilters);
      window.open(url, '_blank');
    },
    formatNumber(value) {
      return new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 }).format(value ?? 0);
    },
    formatMoney(value, code = null) {
      // Sin permiso, cualquier importe se muestra enmascarado.
      if (!this.canSeeMoney) return '***';

      const target = code || (this.currency === 'ALL' ? 'MXN' : this.currency);
      const locale = target === 'USD' ? 'en-US' : 'es-MX';
      return new Intl.NumberFormat(locale, { style: 'currency', currency: target }).format(value ?? 0);
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
    // Sin permiso de dinero, la única métrica posible es piezas.
    if (!this.canSeeMoney) {
      this.metric = 'units';
      this.currency = 'ALL';
    }

    this.fetchFamilies();
    this.fetchProducts();
  },
};
</script>
