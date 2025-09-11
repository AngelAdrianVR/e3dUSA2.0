<template>
   <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg h-full transition-colors duration-300">
        <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Desempeño de Colaboradores</h3>
        <ul class="space-y-3">
            <li v-for="(employee, index) in employees" :key="employee.id" class="flex items-center justify-between">
                <div class="flex items-center">
                     <span class="text-lg w-6 text-center">{{ getRankIcon(index) || index + 1 }}</span>
                     <div class="ml-3 flex items-center">
                        <div class="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center font-bold text-indigo-600 dark:text-indigo-300 mr-3">
                            {{ getAvatarInitials(employee.name) }}
                        </div>
                        <span class="font-medium text-gray-700 dark:text-gray-200">{{ employee.name }}</span>
                     </div>
                </div>
                <span class="font-bold text-lg" :class="employee.points >= 0 ? 'text-green-500' : 'text-red-500'">{{ employee.points }} pts</span>
            </li>
        </ul>
        <div v-if="!employees.length" class="text-center py-8">
            <p class="text-gray-500 dark:text-gray-400">No hay datos de desempeño.</p>
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
    methods: {
        getRankIcon(index) {
            const icons = ['🥇', '🥈', '🥉'];
            return icons[index] || null;
        },
        getAvatarInitials(name) {
            if (!name) return '';
            const names = name.split(' ');
            if (names.length > 1) {
                return `${names[0][0]}${names[1][0]}`.toUpperCase();
            }
            return name.substring(0, 2).toUpperCase();
        }
    }
}
</script>
