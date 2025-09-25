<template>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg h-full transition-colors duration-300 flex flex-col">
        <!-- Encabezado del componente -->
        <div class="flex justify-between items-start mb-4">
            <h3 class="text-xl font-bold text-gray-800 dark:text-white">Estado de almacén</h3>            
        </div>

        <!-- Contenedor del gráfico para que ocupe el espacio restante -->
        <div class="flex-grow flex items-center justify-center">
             <apexchart type="donut" height="300" :options="chartOptions" :series="series"></apexchart>
        </div>

        <!-- Nuevo indicador de productos bajos en stock -->
        <div class="text-right">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Bajos en Stock</span>
            <p class="text-2xl font-bold text-red-500 flex items-center justify-end">
                <!-- Icono de alerta -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                {{ warehouseStats.lowStockCount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") }}
            </p>
        </div>
    </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';

export default {
    name: 'WarehouseStatusChart',
    components: { apexchart: VueApexCharts },
    props: {
        theme: String,
        warehouseStats: {
            // Se actualiza el tipo a Objeto
            type: Object,
            default: () => ({
                counts: [65, 20, 15], // Valores por defecto
                lowStockCount: 0      // Valor por defecto
            })
        }
    },
    computed: {
        series() {
            // El gráfico ahora usa los datos de 'counts'
            return this.warehouseStats.counts;
        },
        chartOptions() {
             return {
                chart: { type: 'donut' },
                labels: ['Materia prima', 'Insumo', 'Producto terminado'],
                colors: ['#3B82F6', '#FBBF24', '#10B981'],
                dataLabels: { enabled: false, },
                legend: {
                    position: 'bottom',
                    labels: {
                        colors: this.theme === 'dark' ? '#9CA3AF' : '#4B5563',
                    },
                    markers: {
                        radius: 12,
                    }
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '75%',
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: 'Inventario',
                                    color: this.theme === 'dark' ? '#9CA3AF' : '#4B5563',
                                    formatter: (w) => {
                                        const total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                        return total.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                },
                stroke: { width: 0 },
                tooltip: {
                    theme: this.theme,
                    y: {
                        formatter: (val) => val.toLocaleString() + " items"
                    }
                }
            };
        }
    }
}
</script>
