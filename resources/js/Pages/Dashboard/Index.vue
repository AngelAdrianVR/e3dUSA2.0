<template>
    <AppLayout title="Dashboard">
        <div class="max-w-[90rem] mx-auto">
            <h1 class="mb-4 text-lg font-bold dark:text-white">Panel de inicio</h1>
            <div class="font-sans text-gray-800 transition-colors duration-300 dark:text-gray-200">
                <!-- Dashboard Grid -->
                <div class="grid grid-cols-1 gap-5 lg:grid-cols-3 xl:grid-cols-4">
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
                    <div v-if="$page.props.auth.user.role === 'Super Administrador'" class="lg:col-span-3">
                        <RequiredActions :actions="requiredActions" />
                    </div>

                    <!-- Employee Performance -->
                    <div class="lg:col-span-2">
                        <EmployeePerformance 
                            :production-performance="productionPerformance"
                            :sales-performance="salesPerformance"
                            :design-performance="designPerformance"
                        />
                    </div>
                    
                    <!-- News Panel -->
                    <div class="lg:col-span-2">
                        <NewsPanel :news="news" />
                    </div>
                    
                    <!-- Personal Panels Column -->
                    <div class="flex flex-col lg:col-span-2 gap-5">
                       <MySalesOrders v-if="$page.props.auth.user.role === 'Vendedor' || $page.props.auth.user.role === 'Super Administrador'" :orders="mySalesOrders" />
                       <UpcomingBirthdays :contacts="upcomingBirthdays" />
                    </div>

                    <!-- My Pending Tasks -->
                    <div class="lg:col-span-2">
                        <MyPendingTasks v-if="$page.props.auth.user.role === 'Auxiliar de producción'" :tasks="myPendingTasks" :user-name="authUserName" />
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
import UpcomingBirthdays from './Components/UpcomingBirthdays.vue';
import MySalesOrders from './Components/MySalesOrders.vue';
import MyPendingTasks from './Components/MyPendingTasks.vue';
import NewsPanel from './Components/NewsPanel.vue';
// import OvertimePanel from './Components/OvertimePanel.vue';

export default {
    components: {
        AppLayout,
        ProductionOrdersChart,
        CalendarWidget,
        WarehouseStatusChart,
        RequiredActions,
        EmployeePerformance,
        UpcomingBirthdays,
        MySalesOrders,
        MyPendingTasks,
        NewsPanel,
        // Overtime Panel,
    },
    props: {
        calendarEvents: Array,
        warehouseStats: Object,
        requiredActions: Object,
        upcomingBirthdays: Array,
        mySalesOrders: Array,
        myPendingTasks: Array,
        authUserName: String,
        news: Array,
        overtimeOpportunity: Object,
        productionPerformance: Object,
        salesPerformance: Object,
        designPerformance: Object,
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
