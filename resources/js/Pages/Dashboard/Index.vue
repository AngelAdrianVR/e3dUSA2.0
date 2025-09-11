<template>
    <AppLayout title="Dashboard">
        <div>
            <h1 class="text-lg font-bold dark:text-white">Panel de inicio</h1>
            <div class="min-h-screen p-4 font-sans text-gray-800 transition-colors duration-300 sm:p-6 lg:p-8 dark:text-gray-200">
                <!-- Dashboard Grid -->
                <div class="grid grid-cols-1 gap-7 lg:grid-cols-3 xl:grid-cols-4">
                    <!-- Production Orders Chart -->
                    <div class="lg:col-span-2 xl:col-span-3">
                        <ProductionOrdersChart :theme="theme" />
                    </div>

                    <!-- Calendar -->
                    <div class="lg:col-span-1 xl:col-span-1 row-span-1 lg:row-span-1">
                       <CalendarWidget :events="calendarEvents" />
                    </div>
                    
                    <!-- Warehouse Status -->
                    <div class="lg:col-span-1">
                        <WarehouseStatusChart :theme="theme" :warehouse-stats="warehouseStats" />
                    </div>

                    <!-- Required Actions -->
                    <div class="lg:col-span-2">
                        <RequiredActions :actions="requiredActions" />
                    </div>

                    <!-- Employee Performance -->
                    <div class="lg:col-span-1">
                        <EmployeePerformance :employees="employeePerformance" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import ProductionOrdersChart from './Components/ProductionOrdersChart.vue';
import CalendarWidget from './Components/CalendarWidget.vue';
import WarehouseStatusChart from './Components/WarehouseStatusChart.vue';
import RequiredActions from './Components/RequiredActions.vue';
import EmployeePerformance from './Components/EmployeePerformance.vue';

export default {
    components: {
        AppLayout,
        ProductionOrdersChart,
        CalendarWidget,
        WarehouseStatusChart,
        RequiredActions,
        EmployeePerformance,
    },
    props: {
        // productionStats ya no es un prop aquí
        calendarEvents: Array,
        warehouseStats: Array,
        requiredActions: Object,
        employeePerformance: Array,
    },
    data() {
        return {
           isDark: true,
        }
    },
    computed: {
        theme() {
            return this.isDark ? 'dark' : 'light';
        }
    },
    methods: {
        toggleTheme() {
            this.isDark = !this.isDark;
        }
    },
    mounted() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            this.isDark = true;
        } else {
            this.isDark = false;
        }
    }
}
</script>
