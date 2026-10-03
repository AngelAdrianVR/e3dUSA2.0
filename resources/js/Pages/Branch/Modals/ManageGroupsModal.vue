<template>
    <DialogModal :show="show" maxWidth="2xl" @close="closeModal">
        <template #title>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                <i class="fa-solid fa-layer-group mr-2"></i> Gestionar Grupos de Clientes
            </h2>
        </template>

        <template #content>
            <div class="space-y-5 min-h-72">
                <!-- Selección / creación de grupo -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Grupo
                        </label>
                        <el-button v-if="!creatingGroup" text size="small" type="primary" @click="startCreateGroup">
                            <i class="fa-solid fa-plus mr-1"></i>
                            Nuevo grupo
                        </el-button>
                    </div>

                    <!-- Modo creación de un grupo nuevo -->
                    <div v-if="creatingGroup" class="flex items-center gap-2">
                        <el-input
                            ref="newGroupInput"
                            v-model="newGroupName"
                            placeholder="Nombre del nuevo grupo"
                            maxlength="255"
                            clearable
                            class="!w-full"
                            @keyup.enter="createGroup"
                        />
                        <el-button type="primary" :disabled="!newGroupName.trim()" @click="createGroup">
                            Crear
                        </el-button>
                        <el-button text @click="cancelCreateGroup">Cancelar</el-button>
                    </div>

                    <!-- Modo selección de un grupo existente -->
                    <el-select
                        v-else
                        v-model="selectedGroup"
                        filterable
                        clearable
                        placeholder="Selecciona un grupo"
                        class="!w-full"
                        :teleported="false"
                        @change="handleGroupChange"
                    >
                        <el-option
                            v-for="group in groupsData"
                            :key="group.name"
                            :label="`${group.name} (${(group.members || []).length})`"
                            :value="group.name"
                        />
                    </el-select>

                    <p v-if="creatingGroup" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        El grupo se guardará al agregar el primer cliente.
                    </p>
                    <p v-else class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Selecciona un grupo existente o usa "Nuevo grupo" para crear uno desde cero.
                    </p>
                </div>

                <template v-if="selectedGroup">
                    <!-- Buscador para agregar clientes al grupo -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Agregar cliente al grupo
                        </label>
                        <el-select
                            v-model="branchToAdd"
                            filterable
                            remote
                            reserve-keyword
                            clearable
                            :remote-method="remoteSearch"
                            :loading="searching"
                            placeholder="Busca por nombre, RFC, razón social o número de cliente"
                            class="!w-full"
                            :teleported="false"
                            @change="addMember"
                        >
                            <el-option
                                v-for="item in searchResults"
                                :key="item.id"
                                :label="optionLabel(item)"
                                :value="item.id"
                            />
                        </el-select>
                    </div>

                    <!-- Miembros del grupo -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Clientes en el grupo
                            </p>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ members.length }} cliente(s)
                            </span>
                        </div>

                        <div v-if="members.length" class="max-h-72 overflow-y-auto border border-gray-200 dark:border-slate-700 rounded-lg">
                            <el-table :data="members" size="small" style="width: 100%"
                                class="dark:!bg-slate-900 dark:!text-gray-300">
                                <el-table-column prop="name" label="Nombre" />
                                <el-table-column label="Tipo" width="110">
                                    <template #default="scope">
                                        <el-tag :type="scope.row.parent_branch_id ? 'info' : 'success'" size="small" disable-transitions>
                                            {{ scope.row.parent_branch_id ? 'Sucursal' : 'Matriz' }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column label="Vendedor" width="160">
                                    <template #default="scope">
                                        {{ scope.row.account_manager?.name ?? 'No asignado' }}
                                    </template>
                                </el-table-column>
                                <el-table-column align="right" width="90">
                                    <template #default="scope">
                                        <el-popconfirm
                                            title="¿Sacar a este cliente del grupo?"
                                            confirm-button-text="Sí"
                                            cancel-button-text="No"
                                            icon-color="#EF4444"
                                            @confirm="removeMember(scope.row)"
                                        >
                                            <template #reference>
                                                <el-button size="small" type="danger" plain :loading="removingId === scope.row.id">
                                                    Sacar
                                                </el-button>
                                            </template>
                                        </el-popconfirm>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                        <p v-else class="text-sm text-gray-500 dark:text-gray-400 italic border border-dashed border-gray-300 dark:border-slate-700 rounded-lg p-4 text-center">
                            Este grupo aún no tiene clientes. Usa el buscador de arriba para agregar.
                        </p>
                    </div>
                </template>
            </div>
        </template>

        <template #footer>
            <CancelButton @click="closeModal">Cerrar</CancelButton>
        </template>
    </DialogModal>
</template>

<script>
import DialogModal from '@/Components/DialogModal.vue';
import CancelButton from '@/Components/MyComponents/CancelButton.vue';
import { ElMessage } from 'element-plus';
import axios from 'axios';

export default {
    name: 'ManageGroupsModal',
    components: {
        DialogModal,
        CancelButton,
    },
    props: {
        show: {
            type: Boolean,
            default: false,
        },
        groups: {
            type: Array,
            default: () => [],
        },
    },
    emits: ['close', 'refresh'],
    data() {
        return {
            groupsData: [],
            selectedGroup: null,
            creatingGroup: false,
            newGroupName: '',
            branchToAdd: null,
            searchResults: [],
            searching: false,
            removingId: null,
            loading: false,
        };
    },
    computed: {
        // El grupo seleccionado puede ser uno existente o uno nuevo (sin miembros aún).
        currentGroup() {
            return this.groupsData.find((g) => g.name === this.selectedGroup) || null;
        },
        members() {
            return this.currentGroup ? this.currentGroup.members : [];
        },
    },
    methods: {
        closeModal() {
            this.$emit('close');
        },
        optionLabel(item) {
            const extras = [];
            if (item.rfc) extras.push(item.rfc);
            if (item.group_name) extras.push(`Grupo: ${item.group_name}`);
            return extras.length ? `${item.name} — ${extras.join(' · ')}` : item.name;
        },
        async fetchGroups() {
            try {
                const response = await axios.get(route('branches.groups.index'));
                this.groupsData = response.data.groups ?? [];
            } catch (error) {
                console.error(error);
                ElMessage.error('No se pudieron cargar los grupos');
            }
        },
        handleGroupChange() {
            // Limpiamos búsqueda al cambiar de grupo.
            this.branchToAdd = null;
            this.searchResults = [];
        },
        startCreateGroup() {
            this.creatingGroup = true;
            this.newGroupName = '';
            this.$nextTick(() => this.$refs.newGroupInput?.focus());
        },
        cancelCreateGroup() {
            this.creatingGroup = false;
            this.newGroupName = '';
        },
        createGroup() {
            const name = (this.newGroupName || '').trim();
            if (!name) return;

            // Si ya existe un grupo con ese nombre, lo seleccionamos en lugar de duplicarlo.
            const existing = this.groupsData.find(
                (group) => group.name.toLowerCase() === name.toLowerCase()
            );

            if (existing) {
                this.selectedGroup = existing.name;
                ElMessage.info('Ese grupo ya existe, se seleccionó para editarlo.');
            } else {
                // Grupo nuevo (borrador): se guardará al agregar el primer cliente.
                this.selectedGroup = name;
                ElMessage.success('Grupo listo. Agrega clientes para guardarlo.');
            }

            this.creatingGroup = false;
            this.newGroupName = '';
            this.branchToAdd = null;
            this.searchResults = [];
        },
        async remoteSearch(query) {
            if (!query || query.length < 2) {
                this.searchResults = [];
                return;
            }

            this.searching = true;
            try {
                const response = await axios.get(route('branches.groups.search'), {
                    params: { query, group: this.selectedGroup },
                });
                this.searchResults = response.data.items ?? [];
            } catch (error) {
                console.error(error);
            } finally {
                this.searching = false;
            }
        },
        async addMember(branchId) {
            if (!branchId || !this.selectedGroup) return;

            try {
                await axios.post(route('branches.groups.add', branchId), {
                    group_name: this.selectedGroup,
                });

                ElMessage.success('Cliente agregado al grupo');
                this.branchToAdd = null;
                this.searchResults = [];
                await this.fetchGroups();
                this.$emit('refresh');
            } catch (error) {
                console.error(error);
                ElMessage.error(error.response?.data?.message ?? 'No se pudo agregar el cliente al grupo');
            }
        },
        async removeMember(member) {
            this.removingId = member.id;
            try {
                await axios.delete(route('branches.groups.remove', member.id));

                ElMessage.success('Cliente removido del grupo');
                await this.fetchGroups();
                this.$emit('refresh');
            } catch (error) {
                console.error(error);
                ElMessage.error(error.response?.data?.message ?? 'No se pudo remover el cliente del grupo');
            } finally {
                this.removingId = null;
            }
        },
    },
    watch: {
        show(value) {
            if (value) {
                // El prop `groups` del index sólo trae nombres (strings); el listado
                // completo con miembros se obtiene del endpoint.
                this.selectedGroup = null;
                this.groupsData = [];
                this.creatingGroup = false;
                this.newGroupName = '';
                this.branchToAdd = null;
                this.searchResults = [];
                this.fetchGroups();
            }
        },
    },
};
</script>
