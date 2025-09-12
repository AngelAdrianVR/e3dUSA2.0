<template>
    <div class="p-4 bg-white rounded-lg shadow-md dark:bg-gray-800">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-800 dark:text-white">Tareas pendientes</h3>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ userName }}</span>
        </div>
        <div class="space-y-3 max-h-[450px] overflow-y-auto">
            <div v-for="task in tasks" :key="task.id"
                 :class="['p-4 border rounded-lg flex flex-col md:flex-row md:justify-between md:items-center transition-all', 
                 task.status === 'En Proceso' ? 'border-blue-600 dark:bg-blue-500/20 bg-blue-300/10' : '',
                 task.status === 'Pendiente' ? 'border-gray-500 dark:bg-gray-500/20 bg-gray-300/10' : '',
                 task.status === 'Pausada' ? 'border-yellow-700 dark:bg-yellow-500/20 bg-yellow-300/10' : '',
                 task.status === 'Sin material' ? 'border-red-500 dark:bg-red-500/20 bg-red-300/10' : ''
                 ]">
                
                <div class="flex-1 mb-3 md:mb-0">
                    <div class="flex items-center gap-2 mb-1">
                        <p class="font-bold text-gray-700 dark:text-gray-300">{{ task.production_folio }}</p>
                        <span v-if="task.status === 'En Proceso'" class="px-2 py-0.5 text-xs text-blue-800 bg-blue-200 rounded-full dark:bg-blue-900 dark:text-blue-300">
                            ⚡ Tarea actual
                        </span>
                    </div>
                    <p class="font-semibold text-gray-800 dark:text-gray-100">{{ task.name }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Creado: {{ task.created_at }}</p>
                </div>

                <div class="flex-1 text-sm text-right text-gray-700 dark:text-gray-300">
                    <el-tag :type="getStatusTagType(task.status)" class="mb-2">
                        {{ task.status }}
                    </el-tag>
                    <div class="flex justify-end gap-6">
                        <div>
                            <p>Piezas ordenadas:</p>
                            <p>Disponibles:</p>
                            <p class="font-bold text-green-600 dark:text-green-400">A producir:</p>
                        </div>
                         <div>
                            <p class="font-semibold">{{ task.pieces_ordered }}</p>
                            <p class="font-semibold">{{ task.pieces_available }}</p>
                            <p class="font-bold text-green-600 dark:text-green-400">{{ task.pieces_to_produce }}</p>
                        </div>
                    </div>
                </div>

                <div class="pl-4 ml-4 border-l border-gray-200 dark:border-gray-600">
                    <button @click="$inertia.visit(route('productions.index'))" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                    </button>
                </div>
            </div>
            <p v-if="!tasks.length" class="pt-3 text-sm text-center text-gray-500 dark:text-gray-400">¡Felicidades! No tienes tareas pendientes.</p>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        tasks: Array,
        userName: String,
    },
    methods:{
        getStatusTagType(status) {
            const statusMap = {
                'Pendiente': 'info',
                'En Proceso': 'primary',
                'Pausada': 'warning',
                'Sin material': 'danger',
            };
            return statusMap[status] || 'info';
        }
    }
};
</script>
