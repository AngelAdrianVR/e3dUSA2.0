<template>
    <AppLayout title="Personal">
        <!-- Encabezado de la página -->
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Gestión de Personal
        </h2>

        <!-- Contenido principal -->
        <div class="py-7">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Tarjeta contenedora con diseño moderno -->
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">

                        <!-- Componente de búsqueda personalizado -->
                        <SecondaryButton v-if="hasPermission('Crear personal')" type="primary" @click="$inertia.get(route('users.create'))">
                            <i class="fa-solid fa-plus mr-2"></i>
                            Nuevo Usuario
                        </SecondaryButton>

                        <SearchInput @keyup.enter="searchUsers" v-model="search" @cleanSearch="searchUsers"
                            :searchProps="SearchProps" />
                    </div>

                    <!-- Tabla de Element Plus -->
                    <div class="relative">
                        <div v-if="loading"
                            class="absolute inset-0 bg-white/75 dark:bg-slate-900/75 flex items-center justify-center z-20 rounded-lg">
                            <LoadingIsoLogo />
                        </div>

                        <el-table :data="users.data" style="width: 100%" stripe table-layout="auto" max-height="550" @row-click="handleRowClick"
                            class="cursor-pointer dark:!bg-slate-900 dark:!text-gray-300">
                            <!-- Columna ID -->
                            <el-table-column prop="id" label="ID" width="80" sortable />

                            <!-- Columna Nombre con imagen -->
                            <el-table-column prop="name" label="Nombre" sortable="custom">
                                <template #default="scope">
                                    <div class="flex items-center">
                                        <img :src="scope.row.profile_photo_url"
                                            class="w-10 h-10 rounded-full object-cover mr-4" alt="Foto de perfil">
                                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ scope.row.name
                                            }}</span>
                                    </div>
                                </template>
                            </el-table-column>

                            <!-- Columna Estatus con tag de color -->
                            <el-table-column prop="is_active" label="Estatus" width="150" align="center">
                                <template #default="scope">
                                    <el-tag :type="scope.row.is_active ? 'success' : 'danger'" disable-transitions>
                                        {{ scope.row.is_active ? 'Activo' : 'Inactivo' }}
                                    </el-tag>
                                </template>
                            </el-table-column>

                            <!-- Columna Fecha de Creación formateada -->
                            <el-table-column prop="created_at" label="Fecha de creación" width="200">
                                <template #default="scope">
                                    <span class="text-gray-600 dark:text-gray-400">{{ formatDate(scope.row.created_at)
                                        }}</span>
                                </template>
                            </el-table-column>

                            <!-- Columna de Acciones -->
                            <el-table-column align="right">
                                <template #default="scope">
                                    <el-dropdown trigger="click" @command="handleCommand">
                                        <button @click.stop
                                            class="el-dropdown-link mr-3 justify-center items-center size-8 rounded-full text-primary hover:bg-[#F2F2F2] dark:hover:bg-slate-500 transition-all duration-200 ease-in-out">
                                            <i class="fa-solid fa-ellipsis-vertical"></i>
                                        </button>
                                        <template #dropdown>
                                            <el-dropdown-menu>
                                                <el-dropdown-item :command="'show-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                                        class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                    </svg>
                                                    Ver</el-dropdown-item>
                                                <el-dropdown-item v-if="hasPermission('Editar personal')" :command="'edit-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                                        class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                    </svg>
                                                    Editar</el-dropdown-item>
                                                <el-dropdown-item v-if="hasPermission('Editar personal')" :command="'changeStatus-' + scope.row.id">
                                                    <i class="text-xs pr-1" :class="scope.row.is_active ? 'fa-solid fa-user-slash' :
                                                        'fa-solid fa-user'"></i>
                                                    {{ scope.row.is_active ? 'Dar de baja' : 'Reactivar' }}
                                                </el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>

                    <!-- Paginación -->
                    <div class="flex justify-center mt-6">
                        <el-pagination v-model:current-page="users.current_page" :page-size="users.per_page"
                            :total="users.total" layout="prev, pager, next" background
                            @current-change="handleCurrentChange" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para Dar de Baja / Reactivar -->
        <DialogModal :show="showChangeStatusModal" @close="showChangeStatusModal = false">
            <template #title>
                <span v-if="selectedUser?.is_active">Dar de baja a "{{ selectedUser?.name }}"</span>
                <span v-else>Reactivar a "{{ selectedUser?.name }}"</span>
            </template>
            <template #content>
                <div v-if="selectedUser?.is_active" class="mb-28">
                    <p class="dark:text-gray-300">
                        Se registrará la baja del empleado y se desvinculará de cualquier cliente que tenga asignado.
                    </p>
                    <form @submit.prevent="changeStatus" class="mt-4 space-y-4">
                        <div>
                            <InputLabel value="Fecha de baja*" />
                            <el-date-picker v-model="statusForm.disabled_at" type="date" class="!w-full"
                                placeholder="Selecciona una fecha" format="DD MMMM, YYYY" value-format="YYYY-MM-DD" />
                            <InputError :message="statusForm.errors.disabled_at" />
                        </div>
                        <div>
                            <TextInput v-model="statusForm.reason" label="Motivo de la baja (opcional)"
                                :isTextarea="true" :error="statusForm.errors.reason" />
                        </div>
                    </form>
                </div>
                <div v-else class="mb-28">
                    <p class="dark:text-gray-300">
                        ¿Estás seguro de que deseas reactivar a este usuario? Se registrará la fecha de reactivación y
                        el usuario podrá acceder de nuevo al sistema.
                    </p>
                </div>
            </template>
            <template #footer>
                <div class="flex items-center space-x-1">
                    <CancelButton @click="showChangeStatusModal = false" :disabled="statusForm.processing">Cancelar
                    </CancelButton>
                    <SecondaryButton v-if="selectedUser?.is_active" @click="changeStatus" :loading="statusForm.processing"
                        class="!bg-red-600 hover:!bg-red-700 text-white">
                        Confirmar Baja
                    </SecondaryButton>
                    <SecondaryButton v-else @click="changeStatus" :loading="statusForm.processing"
                        class="!bg-green-600 hover:!bg-green-700 text-white">
                        Confirmar Reactivación
                    </SecondaryButton>
                </div>
            </template>
        </DialogModal>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import LoadingIsoLogo from '@/Components/MyComponents/LoadingIsoLogo.vue';
import SearchInput from '@/Components/MyComponents/SearchInput.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from "@/Components/InputLabel.vue";
import DialogModal from "@/Components/DialogModal.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { ElMessage } from 'element-plus';
import { useForm } from '@inertiajs/vue3';

export default {
    data() {
        return {
            statusForm: useForm({
                disabled_at: new Date().toISOString().slice(0, 10),
                reason: '',
            }),
            loading: false,
            search: this.filters.search || '',
            showChangeStatusModal: false,
            selectedUser: null,
            SearchProps: ['Nombre', 'ID', 'Estatus'],
        };
    },
    components: {
        AppLayout,
        InputLabel,
        SearchInput,
        DialogModal,
        CancelButton,
        LoadingIsoLogo,
        SecondaryButton,
        TextInput,
        InputError,
    },
    props: {
        users: Object,
        filters: Object,
    },
    methods: {
        hasPermission(permission) {
            const permissions = this.$page.props.auth.user.permissions;
            return permissions.includes(permission);
        },
        handleRowClick(row) {
            this.$inertia.get(route('users.show', row.id));
        },
        searchUsers() {
            this.loading = true;
            this.$inertia.get(this.route('users.index'), {
                search: this.search
            }, {
                preserveState: true,
                replace: true,
                onFinish: () => {
                    this.loading = false;
                },
            });
        },
        handleCurrentChange(page) {
            this.$inertia.get(this.route('users.index', { page: page, search: this.search }));
        },
        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('es-MX', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });
        },
        handleCommand(command) {
            const commandName = command.split('-')[0];
            const userId = command.split('-')[1];

            if (commandName === 'changeStatus') {
                this.selectedUser = this.users.data.find(u => u.id == userId);
                this.showChangeStatusModal = true;
            } else {
                this.$inertia.get(route('users.' + commandName, userId));
            }
        },
        changeStatus() {
            this.statusForm.put(route("users.change-status", this.selectedUser), {
                onSuccess: () => {
                    this.showChangeStatusModal = false;
                    this.statusForm.reset();
                    const message = this.selectedUser.is_active ? 'Usuario dado de baja' : 'Usuario reactivado';
                    ElMessage.success(message);
                    this.selectedUser = null;
                },
                preserveScroll: true,
            });
        },
    }
}
</script>