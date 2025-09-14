<template>
  <!-- Main container with futuristic dark theme -->
  <div class="dark:bg-gray-800 bg-white dark:text-gray-200 p-6 rounded-2xl shadow-lg h-full transition-colors duration-300 flex flex-col">
    <!-- Header with title and star icon -->
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-xl font-bold dark:text-white">Desempeño de Colaboradores</h3>
      <span class="text-yellow-400 text-2xl animate-pulse">⭐</span>
    </div>

    <!-- Tabs -->
    <div class="flex space-x-4 border-b border-gray-700 mb-4">
      <button class="py-2 px-1 text-blue-400 border-b-2 border-blue-400 font-semibold">Producción</button>
      <button class="py-2 px-1 text-gray-400 hover:text-secondary transition-colors">Ventas</button>
      <button class="py-2 px-1 text-gray-400 hover:text-secondary transition-colors">Diseño</button>
    </div>

    <!-- Performance List -->
    <ul class="space-y-3.5 pr-2">
      <li v-for="(employee, index) in sortedEmployees" :key="employee.id" class="grid grid-cols-12 items-center gap-4 text-sm">
        <!-- Rank -->
        <span class="col-span-1 text-center font-bold text-gray-400">{{ index + 1 }}</span>
        
        <!-- Name and Recognition -->
        <div class="col-span-5 flex items-center">
          <span class="truncate" :class="{'font-bold text-lg': index === 0}">{{ employee.name }}</span>
          <span v-if="getRankIcon(index)" class="ml-2 text-lg">{{ getRankIcon(index) }}</span>
        </div>
        
        <!-- Points -->
        <span class="col-span-2 text-right font-semibold text-white">
            {{ Math.round(employee.points).toLocaleString('es-MX') }}
        </span>

        
        <!-- Bar -->
        <div class="col-span-4 bg-gray-700 rounded-full h-2.5">
          <div :class="getBarClass(index, employee.points)" class="h-2.5 rounded-full transition-all duration-500" :style="{ width: getBarWidth(employee.points) }"></div>
        </div>
      </li>
    </ul>
    
    <div v-if="!employees.length" class="text-center py-8 flex-grow flex items-center justify-center">
      <p class="text-gray-500">No hay datos de desempeño para mostrar.</p>
    </div>
  </div>
</template>

<script>
export default {
    name: 'EmployeePerformance',
    props: {
        employees: {
            type: Array,
            default: () => []
        }
    },
    computed: {
        sortedEmployees() {
            // Sort employees by points in descending order
            return [...this.employees].sort((a, b) => b.points - a.points);
        },
        maxPoints() {
            // Find the maximum point value for scaling the bar correctly
            if (!this.sortedEmployees.length) return 1;
            const max = Math.max(...this.sortedEmployees.map(e => e.points));
            return max > 0 ? max : 1; 
        }
    },
    methods: {
        getRankIcon(index) {
            const icons = ['🥇', '🥈', '🥉'];
            return icons[index] || null;
        },
        getBarWidth(points) {
            if (points <= 0) return '2%'; // Show a sliver for negative/zero scores
            const width = (points / this.maxPoints) * 100;
            return `${Math.min(width, 100)}%`; // Cap at 100%
        },
        getBarClass(index, points) {
            if (points <= 0) return 'bg-red-600';
            if (index < 2) return 'bg-green-500'; // Green for 1st, 2nd
            if (index < 5) return 'bg-yellow-500'; // Yellow for 3rd, 4th, 5th
            return 'bg-orange-500'; // Orange for the rest
        }
    }
}
</script>
