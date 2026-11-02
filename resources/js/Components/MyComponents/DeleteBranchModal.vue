<template>
    <el-dialog
        :model-value="show"
        @update:model-value="$emit('update:show', $event)"
        :title="branchIds.length > 1 ? 'Eliminar clientes' : 'Eliminar cliente'"
        width="640px"
        destroy-on-close
    >
        <div v-if="loading" class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
            <i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Revisando los registros vinculados...
        </div>

        <div v-else class="space-y-4 text-sm">
            <div class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 px-4 py-3 text-red-700 dark:text-red-300">
                <p class="font-semibold">
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i>Esta acción no se puede deshacer.
                </p>
                <p class="mt-1 text-xs">
                    Se eliminará el cliente junto con los registros que se listan abajo. Si necesitas conservarlos,
                    reasígnalos a otro cliente o cancela la operación.
                </p>
            </div>

            <div v-for="branch in branches" :key="branch.id" class="rounded-lg border border-gray-200 dark:border-slate-700 p-3">
                <p class="font-semibold text-gray-800 dark:text-gray-100">
                    {{ branch.name }}
                    <span class="text-xs font-normal text-gray-500 dark:text-gray-400">(ID {{ branch.id }})</span>
                </p>

                <div v-if="branch.will_delete.length" class="mt-2 space-y-2">
                    <div v-for="group in branch.will_delete" :key="group.label">
                        <p class="text-[11px] uppercase font-bold text-gray-500 dark:text-gray-400">
                            {{ group.label }} ({{ group.items.length }})
                        </p>
                        <ul class="mt-1 max-h-32 space-y-0.5 overflow-y-auto pr-1">
                            <li v-for="(item, index) in group.items" :key="index"
                                class="flex gap-2 text-xs text-gray-700 dark:text-gray-300">
                                <i class="fa-solid fa-angle-right mt-1 text-[9px] text-gray-400"></i>
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
                <p v-else class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Sin registros adicionales que eliminar.
                </p>

                <div v-if="branch.blockers.length"
                    class="mt-3 rounded-md border border-amber-300 bg-amber-50 p-2 dark:border-amber-700 dark:bg-amber-900/20">
                    <p class="text-xs font-semibold text-amber-800 dark:text-amber-300">
                        <i class="fa-solid fa-lock mr-1"></i>
                        No se puede eliminar todavía: estos documentos mueven inventario o facturación y
                        deben depurarse desde su propio módulo.
                    </p>
                    <div v-for="group in branch.blockers" :key="group.label" class="mt-2">
                        <p class="text-[11px] uppercase font-bold text-amber-700 dark:text-amber-400">{{ group.label }}</p>
                        <ul class="mt-0.5 space-y-0.5">
                            <li v-for="(item, index) in group.items" :key="index"
                                class="text-xs text-amber-800 dark:text-amber-300">
                                {{ item }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <el-button @click="$emit('update:show', false)">Cancelar</el-button>
            <el-button type="danger" :disabled="!canDelete || loading" :loading="deleting" @click="confirmDelete">
                Eliminar definitivamente
            </el-button>
        </template>
    </el-dialog>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';

// Confirmación de borrado de clientes: muestra qué registros se perderán antes de eliminar.
export default {
    name: 'DeleteBranchModal',
    props: {
        show: Boolean,
        branchIds: {
            type: Array,
            default: () => [],
        },
    },
    emits: ['update:show', 'deleted'],
    data() {
        return {
            loading: false,
            deleting: false,
            branches: [],
            canDelete: false,
        };
    },
    watch: {
        show: {
            immediate: true,
            handler(isOpen) {
                if (isOpen) {
                    this.loadPreview();
                }
            },
        },
    },
    methods: {
        async loadPreview() {
            this.loading = true;
            this.branches = [];
            this.canDelete = false;

            try {
                const { data } = this.branchIds.length === 1
                    ? await axios.get(route('branches.deletion-preview', this.branchIds[0]))
                    : await axios.post(route('branches.massive-deletion-preview'), { ids: this.branchIds });

                this.branches = data.branches || [];
                this.canDelete = !!data.can_delete;
            } catch (error) {
                console.error('No se pudo obtener la vista previa de la eliminación:', error);
                ElMessage.error('No se pudieron revisar los registros vinculados al cliente.');
            } finally {
                this.loading = false;
            }
        },
        async confirmDelete() {
            this.deleting = true;

            try {
                if (this.branchIds.length === 1) {
                    await axios.delete(route('branches.destroy', this.branchIds[0]), { data: { force: true } });
                } else {
                    await axios.post(route('branches.massive-delete'), { ids: this.branchIds, force: true });
                }

                ElMessage.success('Cliente(s) eliminado(s) correctamente.');
                this.$emit('update:show', false);
                this.$emit('deleted');
            } catch (error) {
                const errors = error.response?.data?.errors;
                const firstError = errors ? Object.values(errors)[0] : null;
                const message = error.response?.data?.message
                    || (Array.isArray(firstError) ? firstError[0] : firstError)
                    || 'No se pudo eliminar el cliente.';

                ElMessage.error(message);
            } finally {
                this.deleting = false;
            }
        },
    },
};
</script>
