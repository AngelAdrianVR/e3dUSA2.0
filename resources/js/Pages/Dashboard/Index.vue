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
                    <div class="lg:col-span-3">
                        <RequiredActions :actions="requiredActions" />
                    </div>

                    <!-- Employee Performance -->
                    <div class="lg:col-span-2">
                        <EmployeePerformance :employees="employeePerformance" />
                    </div>

                    <!-- Personal Panels Column -->
                    <div class="flex flex-col lg:col-span-2 gap-5">
                       <MySalesOrders :orders="mySalesOrders" />
                       <UpcomingBirthdays :contacts="upcomingBirthdays" />
                    </div>

                    <!-- NEW: My Pending Tasks -->
                    <div class="lg:col-span-3 xl:col-span-4">
                        <MyPendingTasks :tasks="myPendingTasks" :user-name="authUserName" />
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
    },
    props: {
        calendarEvents: Array,
        warehouseStats: Object,
        requiredActions: Object,
        employeePerformance: Array,
        upcomingBirthdays: Array,
        mySalesOrders: Array,
        myPendingTasks: Array,
        authUserName: String,
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
