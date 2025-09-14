<template>
    <div class="relative h-full p-6 transition-colors duration-300 bg-white rounded-2xl shadow-lg dark:bg-gray-800">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-gray-800 dark:text-white">Órdenes de producción</h3>
            <div class="flex space-x-2">
                <button
                    v-for="days in [7, 15, 30]"
                    :key="days"
                    @click="fetchData(days)"
                    :class="[
                        'px-4 py-2 text-sm font-medium rounded-lg transition-colors',
                        selectedDays === days
                            ? 'bg-blue-600 text-white'
                            : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                    ]">
                    Últimos {{ days }} días
                </button>
            </div>
        </div>
        <div class="relative min-h-[350px]">
            <!-- Loading Overlay -->
            <div v-if="isLoading" class="absolute inset-0 z-10 flex items-center justify-center rounded-lg bg-white/50 dark:bg-gray-800/50">
                <div class="flex items-center space-x-2 text-gray-500 dark:text-gray-400">
                    <svg class="w-5 h-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Cargando...</span>
                </div>
            </div>
            <!-- Chart -->
            <apexchart v-if="!isLoading" type="bar" height="350" :options="chartOptions" :series="series"></apexchart>
        </div>
    </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import axios from 'axios';

export default {
    name: 'ProductionOrdersChart',
    components: { apexchart: VueApexCharts },
    props: {
        theme: String,
    },
    data() {
        return {
            isLoading: true,
            stats: {},
            selectedDays: 15, // Default value
        }
    },
    computed: {
        series() {
            // The order here MUST match the colors and categories in chartOptions
            const statusOrder = ['Pendiente', 'Sin material', 'En proceso', 'Terminada', 'Atrasadas'];
            return [{
                name: 'Órdenes',
                data: statusOrder.map(status => this.stats[status] || 0)
            }];
        },
        chartOptions() {
            const statusColors = {
                'Pendientes': '#6B7280',   // gray-500
                'Sin material': '#F97316', // orange-500
                'En proceso': '#3B82F6',   // blue-500
                'Terminadas': '#22C55E',   // green-500
                'Atrasadas': '#EF4444',    // red-500
            };

            const categories = ['Pendientes', 'Sin material', 'En proceso', 'Terminadas', 'Atrasadas'];

            return {
                chart: {
                    type: 'bar',
                    height: 350,
                    toolbar: { show: false },
                    background: 'transparent'
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '55%',
                        endingShape: 'rounded',
                        borderRadius: 8,
                        distributed: true, // Important for different colors per bar
                    },
                },
                colors: categories.map(cat => statusColors[cat]),
                dataLabels: { enabled: false },
                stroke: {
                    show: true,
                    width: 2,
                    colors: ['transparent']
                },
                xaxis: {
                    categories: categories,
                    labels: {
                        style: {
                            colors: this.theme === 'dark' ? '#9CA3AF' : '#4B5563',
                            fontSize: '14px',
                        }
                    },
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                },
                yaxis: {
                    labels: {
                        style: {
                            colors: this.theme === 'dark' ? '#9CA3AF' : '#4B5563',
                            fontSize: '14px',
                        },
                         formatter: (val) => { return val.toFixed(0) },
                    },
                },
                fill: { opacity: 1 },
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return val + " órdenes"
                        }
                    },
                    theme: this.theme
                },
                grid: {
                    borderColor: this.theme === 'dark' ? '#374151' : '#E5E7EB',
                    strokeDashArray: 4,
                },
                legend: { show: false },
            };
        }
    },
    methods: {
        async fetchData(days) {
            this.isLoading = true;
            this.selectedDays = days;
            try {
                // IMPORTANTE: Asegúrate que esta ruta esté definida en routes/web.php o routes/api.php
                const response = await axios.get('/dashboard/production-stats', {
                    params: { days: this.selectedDays }
                });
                this.stats = response.data;
            } catch (error) {
                console.error("Error fetching production stats:", error);
                // Opcionalmente, puedes mostrar un mensaje de error al usuario
                this.stats = {}; // Reiniciar stats en caso de error
            } finally {
                this.isLoading = false;
            }
        }
    },
    mounted() {
        // Cargar los datos iniciales para el período por defecto (15 días)
        this.fetchData(this.selectedDays);
    }
}
</script>
