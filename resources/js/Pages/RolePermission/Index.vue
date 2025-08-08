<template>
    <AppLayout title="Roles y Permisos">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Gestión de Roles y Permisos
        </h2>

        <div class="py-3">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 shadow-xl sm:rounded-lg">
                    <div class="md:grid md:grid-cols-3">
                        <!-- Columna de Roles -->
                        <div class="md:col-span-1 border-r border-gray-200 dark:border-slate-700 p-5">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">Roles</h3>
                                <SecondaryButton @click="openCreateRoleModal">Nuevo Rol</SecondaryButton>
                            </div>
                            <div class="space-y-2 max-h-[65vh] overflow-auto">
                                <div v-for="role in roles" :key="role.id"
                                    @click="selectRole(role)"
                                    :class="selectedRole && selectedRole.id === role.id ? 'bg-blue-100 dark:bg-blue-900/50' : 'hover:bg-gray-100 dark:hover:bg-slate-800'"
                                    class="p-3 rounded-lg cursor-pointer transition-colors duration-200 flex justify-between items-center group">
                                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ role.name }}</span>
                                    <div v-if="role.name !== 'Super Administrador'" class="flex items-center space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button @click.stop="openEditRoleModal(role)" class="text-gray-400 hover:text-blue-500"><i class="fa-solid fa-pencil"></i></button>
                                        <el-popconfirm title="¿Eliminar este rol?" @confirm="deleteRole(role)">
                                            <template #reference>
                                                <button @click.stop class="text-gray-400 hover:text-red-500"><i class="fa-solid fa-trash-can"></i></button>
                                            </template>
                                        </el-popconfirm>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Columna de Permisos -->
                        <div class="md:col-span-2 p-5">
                            <div v-if="selectedRole">
                                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">
                                    Permisos para: <span class="text-blue-600 dark:text-blue-400">{{ selectedRole.name }}</span>
                                </h3>
                                <div class="space-y-6 max-h-[65vh] overflow-auto pr-2">
                                    <div v-for="(permissionGroup, module) in permissions" :key="module">
                                        <div class="flex justify-between items-center border-b border-gray-200 dark:border-slate-700 pb-2 mb-3">
                                            <h4 class="font-bold capitalize text-gray-700 dark:text-gray-100">{{ module }}</h4>
                                            <button @click="openCreatePermissionModal(module)" class="text-blue-500 hover:text-blue-700 text-sm font-medium">
                                                <i class="fa-solid fa-plus mr-1"></i> Nuevo Permiso
                                            </button>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-2">
                                            <div v-for="permission in permissionGroup" :key="permission.id" class="group flex items-center justify-between p-2 rounded hover:bg-gray-100 dark:hover:bg-slate-800">
                                                <label class="flex items-center space-x-3 cursor-pointer">
                                                    <el-checkbox 
                                                        :model-value="hasPermission(permission.name)"
                                                        @change="togglePermission(permission.name)"
                                                        :label="permission.name"
                                                        :disabled="selectedRole.name === 'Super Administrador'"
                                                    />
                                                </label>
                                                <div class="flex items-center space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <button @click.stop="openEditPermissionModal(permission)" class="text-gray-400 hover:text-blue-500 text-xs"><i class="fa-solid fa-pencil"></i></button>
                                                    <el-popconfirm title="¿Eliminar este permiso de forma permanente?" @confirm="deletePermission(permission)">
                                                        <template #reference>
                                                            <button @click.stop class="text-gray-400 hover:text-red-500 text-xs"><i class="fa-solid fa-trash-can"></i></button>
                                                        </template>
                                                    </el-popconfirm>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-8 flex justify-end">
                                    <SecondaryButton @click="updateRolePermissions" :disabled="form.processing || selectedRole.name === 'Super Administrador'">
                                        Guardar Cambios
                                    </SecondaryButton>
                                </div>
                            </div>
                            <div v-else class="flex flex-col items-center justify-center h-full text-center text-gray-500 dark:text-gray-400">
                                <i class="fa-solid fa-hand-pointer text-5xl mb-4 -rotate-90"></i>
                                <p>Selecciona un rol para ver y asignar permisos.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para Crear/Editar Rol -->
        <DialogModal :show="showRoleModal" @close="closeRoleModal">
            <template #title>{{ isEditingRole ? 'Editar Rol' : 'Crear Nuevo Rol' }}</template>
            <template #content>
                <form @submit.prevent="submitRole">
                    <InputLabel for="roleName" value="Nombre del Rol" />
                    <TextInput id="roleName" v-model="roleForm.name" required class="w-full mt-1" @keydown.enter.prevent="submitRole" />
                    <InputError :message="roleForm.errors.name" class="mt-2" />
                </form>
            </template>
            <template #footer>
                <div class="flex justify-end space-x-2">
                    <CancelButton @click="closeRoleModal">Cancelar</CancelButton>
                    <SecondaryButton @click="submitRole" :loading="roleForm.processing">
                        {{ isEditingRole ? 'Actualizar' : 'Crear' }}
                    </SecondaryButton>
                </div>
            </template>
        </DialogModal>
        
        <!-- Modal para Crear/Editar Permiso -->
        <DialogModal :show="showPermissionModal" @close="closePermissionModal">
            <template #title>{{ isEditingPermission ? 'Editar Permiso' : 'Crear Nuevo Permiso' }}</template>
            <template #content>
                <form @submit.prevent="submitPermission" class="space-y-4">
                    <div>
                        <InputLabel for="permissionName" value="Nombre del Permiso" />
                        <TextInput id="permissionName" v-model="permissionForm.name" required class="w-full mt-1" />
                        <InputError :message="permissionForm.errors.name" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="permissionModule" value="Módulo" />
                        <TextInput id="permissionModule" v-model="permissionForm.module" required class="w-full mt-1" />
                        <InputError :message="permissionForm.errors.module" class="mt-2" />
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="flex justify-end space-x-2">
                    <CancelButton @click="closePermissionModal">Cancelar</CancelButton>
                    <SecondaryButton @click="submitPermission" :loading="permissionForm.processing">
                        {{ isEditingPermission ? 'Actualizar' : 'Crear' }}
                    </SecondaryButton>
                </div>
            </template>
        </DialogModal>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import CancelButton from '@/Components/MyComponents/CancelButton.vue';
import DialogModal from '@/Components/DialogModal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

export default {
    name: 'RolePermissionIndex',
    components: { AppLayout, SecondaryButton, CancelButton, DialogModal, TextInput, InputLabel, InputError },
    props: {
        roles: Array,
        permissions: Object,
    },
    data() {
        return {
            // State for Roles
            selectedRole: null,
            assignedPermissions: [],
            form: useForm({ permissions: [] }),
            roleForm: useForm({ id: null, name: '' }),
            showRoleModal: false,
            isEditingRole: false,
            
            // State for Permissions
            permissionForm: useForm({ id: null, name: '', module: '' }),
            showPermissionModal: false,
            isEditingPermission: false,
        };
    },
    methods: {
        // --- Role Methods ---
        selectRole(role) {
            this.selectedRole = role;
            this.assignedPermissions = role.permissions.map(p => p.name);
        },
        openCreateRoleModal() {
            this.isEditingRole = false;
            this.roleForm.reset();
            this.showRoleModal = true;
        },
        openEditRoleModal(role) {
            this.isEditingRole = true;
            this.roleForm.id = role.id;
            this.roleForm.name = role.name;
            this.showRoleModal = true;
        },
        closeRoleModal() {
            this.showRoleModal = false;
        },
        submitRole() {
            if (this.isEditingRole) {
                this.roleForm.put(route('roles-permissions.update', this.roleForm.id), {
                    onSuccess: () => { this.closeRoleModal(); ElMessage.success('Rol actualizado.'); },
                    preserveState: false,
                });
            } else {
                this.roleForm.post(route('roles-permissions.store'), {
                    onSuccess: () => { this.closeRoleModal(); ElMessage.success('Rol creado.'); },
                    preserveState: false,
                });
            }
        },
        deleteRole(role) {
            this.$inertia.delete(route('roles-permissions.destroy', role.id), {
                onSuccess: () => ElMessage.success('Rol eliminado.'),
                onFinish: () => { if (this.selectedRole && this.selectedRole.id === role.id) { this.selectedRole = null; } },
                preserveState: false,
            });
        },

        // --- Permission Methods ---
        openCreatePermissionModal(moduleName) {
            this.isEditingPermission = false;
            this.permissionForm.reset();
            this.permissionForm.module = moduleName;
            this.showPermissionModal = true;
        },
        openEditPermissionModal(permission) {
            this.isEditingPermission = true;
            this.permissionForm.id = permission.id;
            this.permissionForm.name = permission.name;
            this.permissionForm.module = permission.module;
            this.showPermissionModal = true;
        },
        closePermissionModal() {
            this.showPermissionModal = false;
        },
        submitPermission() {
            const options = {
                onSuccess: () => {
                    this.closePermissionModal();
                    ElMessage.success(`Permiso ${this.isEditingPermission ? 'actualizado' : 'creado'}.`);
                },
                preserveState: false,
            };
            if (this.isEditingPermission) {
                this.permissionForm.put(route('permissions.update', this.permissionForm.id), options);
            } else {
                this.permissionForm.post(route('permissions.store'), options);
            }
        },
        deletePermission(permission) {
            this.$inertia.delete(route('permissions.destroy', permission.id), {
                onSuccess: () => ElMessage.success('Permiso eliminado.'),
                preserveState: false,
            });
        },

        // --- Common Methods ---
        hasPermission(permissionName) {
            return this.assignedPermissions.includes(permissionName);
        },
        togglePermission(permissionName) {
            const index = this.assignedPermissions.indexOf(permissionName);
            if (index > -1) {
                this.assignedPermissions.splice(index, 1);
            } else {
                this.assignedPermissions.push(permissionName);
            }
        },
        updateRolePermissions() {
            if (!this.selectedRole) return;
            this.form.permissions = this.assignedPermissions;
            this.form.put(route('roles-permissions.update', this.selectedRole.id), {
                onSuccess: () => ElMessage.success('Permisos actualizados.'),
                preserveState: false,
            });
        },
    }
}
</script>
