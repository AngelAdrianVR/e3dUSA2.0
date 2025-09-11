<template>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg h-full transition-colors duration-300">
         <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Estado de almacén</h3>
         <apexchart type="donut" height="300" :options="chartOptions" :series="series"></apexchart>
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
            type: Array,
            default: () => [65, 20, 15] // Default values
        }
    },
    computed: {
        series() {
            return this.warehouseStats;
        },
        chartOptions() {
             return {
                chart: { type: 'donut' },
                labels: ['Materia prima', 'Insumo', 'Producto terminado'],
                colors: ['#3B82F6', '#FBBF24', '#10B981'],
                dataLabels: { enabled: false },
                legend: {
                    position: 'bottom',
                    labels: {
                        colors: this.theme === 'dark' ? '#fff' : '#4B5563',
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
                                    color: this.theme === 'dark' ? '#fff' : '#4B5563',
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
