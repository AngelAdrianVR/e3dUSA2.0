<template>
  <div class="dark:bg-gray-800 bg-white dark:text-gray-200 p-6 rounded-2xl shadow-lg h-full transition-colors duration-300 flex flex-col">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-xl font-bold dark:text-white">Desempeño de Colaboradores</h3>
      <span class="text-yellow-400 text-2xl animate-pulse">⭐</span>
    </div>

    <div class="flex space-x-4 border-b border-gray-700 mb-4">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key" 
              :class="['py-2 px-1 font-semibold transition-colors', activeTab === tab.key ? 'text-blue-400 border-b-2 border-blue-400' : 'text-gray-400 hover:text-secondary']">
        {{ tab.name }}
      </button>
    </div>

    <ul class="space-y-3.5 pr-2 overflow-y-auto flex-grow">
      <li v-for="(employee, index) in activeList" :key="employee.id" 
          @click="showDetails(employee)"
          class="grid grid-cols-12 items-center gap-4 text-sm cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700/50 p-1 rounded-md">
        <span class="col-span-1 text-center font-bold text-gray-400">{{ index + 1 }}</span>
        <div class="col-span-5 flex items-center">
          <span class="truncate" :class="{'font-bold text-lg': index === 0}">{{ employee.name }}</span>
          <span v-if="getRankIcon(index)" class="ml-2 text-lg">{{ getRankIcon(index) }}</span>
        </div>
        <span class="col-span-2 text-right font-semibold dark:text-white">
            {{ Math.round(employee.points).toLocaleString('es-MX') }}
        </span>
        <div class="col-span-4 bg-gray-700 rounded-full h-2.5">
          <div :class="getBarClass(index, employee.points)" class="h-2.5 rounded-full transition-all duration-500" :style="{ width: getBarWidth(employee.points) }"></div>
        </div>
      </li>
    </ul>
    
    <div v-if="!activeList.length" class="text-center py-8 flex-grow flex items-center justify-center">
      <p class="text-gray-500">No hay datos de desempeño para mostrar en esta categoría.</p>
    </div>
  </div>

  <PerformanceDetailModal v-if="selectedEmployee" :user="selectedEmployee" @close="selectedEmployee = null" />
</template>

<script>
import PerformanceDetailModal from './PerformanceDetailModal.vue';

export default {
    name: 'EmployeePerformance',
    components: {
        PerformanceDetailModal
    },
    props: {
        productionPerformance: { type: Array, default: () => [] },
        salesPerformance: { type: Array, default: () => [] },
        designPerformance: { type: Array, default: () => [] }
    },
    data() {
        return {
            activeTab: 'production',
            selectedEmployee: null,
            tabs: [
                { key: 'production', name: 'Producción' },
                { key: 'sales', name: 'Ventas' },
                { key: 'design', name: 'Diseño' },
            ]
        }
    },
    computed: {
        activeList() {
            switch (this.activeTab) {
                case 'sales': return this.salesPerformance;
                case 'design': return this.designPerformance;
                default: return this.productionPerformance;
            }
        },
        maxPoints() {
            if (!this.activeList.length) return 1;
            const max = Math.max(...this.activeList.map(e => e.points));
            return max > 0 ? max : 1; 
        }
    },
    methods: {
        showDetails(employee) {
            // Updated to open modal for any category that has details
            if (employee.details && employee.details.length > 0) {
                this.selectedEmployee = employee;
            }
        },
        getRankIcon(index) {
            return ['🥇', '🥈', '🥉'][index] || null;
        },
        getBarWidth(points) {
            if (points <= 0) return '2%';
            const width = (points / this.maxPoints) * 100;
            return `${Math.max(2, Math.min(width, 100))}%`; // ensure a min width for visibility
        },
        getBarClass(index, points) {
            if (points <= 0) return 'bg-red-600';
            if (index < 2) return 'bg-green-500';
            if (index < 5) return 'bg-yellow-500';
            return 'bg-orange-500';
        }
    }
}
</script>
