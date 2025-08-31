<template>
    <div>
        <div v-show="activeView === 'kanban'" class="p-4 sm:p-6 lg:p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div v-for="column in columns" :key="column.id" class="bg-gray-200 dark:bg-slate-800/50 rounded-lg">
                    <h3 class="font-bold text-gray-800 dark:text-gray-200 px-4 pt-4 pb-3 flex items-center border-b-2 border-gray-300 dark:border-slate-700">
                       <span class="w-3 h-3 rounded-full mr-2" :class="column.color"></span>
                        {{ column.title }}
                        <span class="ml-auto text-xs bg-gray-300 dark:bg-slate-700 text-gray-500 dark:text-gray-400 rounded-full px-2 py-0.5">{{ kanbanState[column.id]?.length || 0 }}</span>
                    </h3>
                    <draggable v-model="kanbanState[column.id]"
                               :item-key="item => item.id"
                               group="productions"
                               @end="onDragEnd"
                               class="space-y-3 p-3 h-[70vh] overflow-auto">
                        <template #item="{element}">
                            <div class="bg-white dark:bg-slate-900 rounded-lg shadow-md p-3 cursor-pointer transition-all duration-200 hover:shadow-xl hover:scale-[1.02]">
                                <div class="flex justify-between items-start">
                                    <p class="text-xs font-bold text-primary">OP-{{ element.id.toString().padStart(4, '0') }}</p>
                                    <img :src="getProductImage(element.sale_product?.product)" class="w-10 h-10 rounded-full object-cover ml-2 border-2 border-gray-200 dark:border-slate-700">
                                </div>
                                <p class="font-semibold text-sm text-gray-800 dark:text-gray-200 mt-1">{{ element.sale_product?.product?.name }}</p>
                                <p class="text-xs text-gray-500">{{ element.sale_product?.sale.branch?.name }} | OV-{{ element.sale_product?.sale.id?.toString().padStart(4, '0') }}</p>

                                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-slate-700">
                                    <div class="flex justify-between items-center text-xs text-gray-500">
                                        <span>Progreso</span>
                                        <span>{{ getProgress(element).completed }}/{{ getProgress(element).total }} Tareas</span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-slate-700 rounded-full h-1.5 mt-1">
                                        <div class="bg-primary h-1.5 rounded-full" :style="{ width: getProgress(element).percentage + '%' }"></div>
                                    </div>
                                    <div class="flex items-center -space-x-3 mt-3">
                                        <span
                                            v-for="operator in getUniqueOperators(element.tasks)"
                                            :key="operator.id"
                                            :title="operator?.name"
                                            class="relative group"
                                        >
                                            <img
                                            class="size-10 rounded-full object-cover border-2  group-hover:scale-110 group-hover:shadow-[0_0_5px_#3b82f6] transition-transform duration-300 ease-out"
                                            :src="operator.profile_photo_url"
                                            :alt="operator?.name"
                                            >
                                        </span>
                                    </div>

                                </div>
                            </div>
                        </template>
                    </draggable>
                </div>
            </div>
        </div>

        <div v-show="activeView === 'table'" class="p-4 sm:p-6 lg:p-8">
            <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg">
                <el-table :data="productions" style="width: 100%" class="dark:!bg-slate-900 dark:!text-gray-300">
                    <el-table-column prop="id" label="OP" width="80" #default="scope">OP-{{ scope.row.id }}</el-table-column>
                    <el-table-column label="Producto">
                        <template #default="scope">
                            {{ scope.row.sale_product?.product.name }}
                        </template>
                    </el-table-column>
                    <el-table-column label="Cliente" #default="scope">{{ scope.row.sale_product?.sale.branch?.name }}</el-table-column>
                    <el-table-column prop="status" label="Estatus" width="150">
                         <template #default="scope">
                            <el-tag :type="statusTagType(scope.row.status)">{{ scope.row.status }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="Progreso" width="180">
                         <template #default="scope">
                            <el-progress :percentage="getProgress(scope.row).percentage" />
                        </template>
                    </el-table-column>
                    <el-table-column label="Acciones" width="120" align="right">
                        <template #default="scope">
                            <Link :href="route('productions.show', scope.row.id)">
                                <PrimaryButton><i class="fa-solid fa-eye"></i></PrimaryButton>
                            </Link>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </div>
    </div>
</template>

<script>
import draggable from 'vuedraggable';
import { Link } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ElMessage } from 'element-plus';

export default {
    name: 'ManagerView',
    components: {
        draggable,
        Link,
        PrimaryButton,
    },
    props: {
        productions: {
            type: Array,
            default: () => [],
        },
        kanbanData: {
            type: Object,
            default: () => ({}),
        },
        activeView: {
            type: String,
            default: 'kanban',
        },
    },
    data() {
        return {
            columns: [
                { id: 'Pendiente', title: 'Pendiente', color: 'bg-yellow-400', borderColor: 'yellow-400' },
                { id: 'En Proceso', title: 'En Proceso', color: 'bg-blue-400', borderColor: 'blue-400' },
                { id: 'Pausada', title: 'Pausada', color: 'bg-orange-400', borderColor: 'orange-400' },
                { id: 'Terminada', title: 'Terminada', color: 'bg-green-400', borderColor: 'green-400' },
            ],
            kanbanState: this.kanbanData || {},
        };
    },
    watch: {
        kanbanData(newData) {
            this.kanbanState = newData || {};
        }
    },
    methods: {
        onDragEnd(event) {
            const productionId = event.item.__draggable_context.element.id;
            const newStatus = event.to.parentElement.__vnode.key;

            this.$inertia.put(this.route('productions.updateStatus', productionId), {
                status: newStatus,
            }, {
                preserveState: true,
                preserveScroll: true,
                onError: () => ElMessage.error('No se pudo actualizar el estado.'),
            });
        },
        getProductImage(product) {
            return product?.media?.length > 0
                ? product.media[0].original_url
                : 'https://placehold.co/100x100/424242/FFFFFF?text=P';
        },
        getProgress(production) {
            const total = production.tasks.length;
            if (total === 0) return { completed: 0, total: 0, percentage: 0 };
            const completed = production.tasks.filter(t => t.status === 'Terminada').length;
            return { completed, total, percentage: Math.round((completed / total) * 100) };
        },
        getUniqueOperators(tasks) {
            if (!tasks) return [];
            const operators = tasks.map(task => task.operator).filter(Boolean);
            return [...new Map(operators.map(item => [item['id'], item])).values()];
        },
        statusTagType(status) {
            const map = {
                'Pendiente': 'warning',
                'En Proceso': '',
                'Pausada': 'danger',
                'Terminada': 'success',
            };
            return map[status] || 'info';
        },
    },
};
</script>

<style>
.ghost {
    opacity: 0.5;
    background: #4A5568;
    border-radius: 0.5rem;
}
</style>