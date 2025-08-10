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
                        <SecondaryButton type="primary" @click="$inertia.get(route('users.create'))">
                            <i class="fa-solid fa-plus mr-2"></i>
                            Nuevo Usuario
                        </SecondaryButton>

                        <SearchInput @keyup.enter="searchUsers" v-model="search" @cleanSearch="searchUsers" />
                    </div>

                        <!-- Tabla de Element Plus -->
                        <div class="relative">
                            <div v-if="loading" class="absolute inset-0 bg-white/75 dark:bg-slate-900/75 flex items-center justify-center z-20 rounded-lg">
                                <LoadingIsoLogo />
                            </div>

                            <el-table
                                :data="users.data"
                                style="width: 100%"
                                stripe
                                table-layout="auto"
                                max-height="550"
                                class="dark:!bg-slate-900 dark:!text-gray-300"
                            >
                            <!-- Columna ID -->
                            <el-table-column prop="id" label="ID" width="80" sortable />

                            <!-- Columna Nombre con imagen -->
                            <el-table-column prop="name" label="Nombre" sortable="custom">
                                <template #default="scope">
                                    <div class="flex items-center">
                                        <img :src="scope.row.profile_photo_url" class="w-10 h-10 rounded-full object-cover mr-4" alt="Foto de perfil">
                                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ scope.row.name }}</span>
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
                                    <span class="text-gray-600 dark:text-gray-400">{{ formatDate(scope.row.created_at) }}</span>
                                </template>
                            </el-table-column>

                            <!-- Columna de Acciones -->
                            <el-table-column align="right">
                                <template #default="scope">
                                    <el-dropdown trigger="click" @command="handleCommand">
                                        <button @click.stop
                                            class="el-dropdown-link mr-3 justify-center items-center size-8 rounded-full text-secondary hover:bg-[#F2F2F2] dark:hover:bg-slate-500 transition-all duration-200 ease-in-out">
                                            <i class="fa-solid fa-ellipsis-vertical"></i>
                                        </button>
                                        <template #dropdown>
                                            <el-dropdown-menu>
                                                <el-dropdown-item :command="'show-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                        stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                    </svg>
                                                    Ver</el-dropdown-item>
                                                <el-dropdown-item :command="'edit-' + scope.row.id">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                        stroke-width="1.5" stroke="currentColor" class="size-4 mr-2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                    </svg>
                                                    Editar</el-dropdown-item>
                                                <el-dropdown-item :command="'changeStatus-' + scope.row.id">
                                                    <i class="text-xs pr-1" :class="scope.row.is_active ? 'fa-solid fa-user-slash' :
                                                        'fa-solid fa-user'"></i>
                                                    {{ scope.row.is_active ? 'Deshabilitar' :
                                                        'Habilitar' }}</el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>

                    <!-- Paginación -->
                    <div class="flex justify-center mt-6">
                        <el-pagination
                            v-model:current-page="users.current_page"
                            :page-size="users.per_page"
                            :total="users.total"
                            layout="prev, pager, next"
                            background
                            @current-change="handleCurrentChange"
                        />
                    </div>
                </div>
            </div>
        </div>

        <DialogModal :show="showChangeStatusModal" @close="showChangeStatusModal = false" maxWidth="xl">
            <template #title>
                <h1 class="font-bold">Inhabilitación de usuario</h1>
            </template>
            <template #content>
                <div class="h-96">
                    <InputLabel value="Fecha de baja*" />
                    <el-date-picker v-model="statusForm.disabled_at" type="date" class="!w-full" :teleported="false"
                        placeholder="Selecciona una fecha" format="DD MMM, YYYY" value-format="YYYY-MM-DD" />
                </div>
            </template>
            <template #footer>
                <div class="flex space-x-3"> 
                    <CancelButton @click="showChangeStatusModal = false; statusForm.reset()" :disabled="statusForm.processing">
                        Cancelar
                    </CancelButton>
                    <SecondaryButton @click="changeStatus()" :disabled="statusForm.processing">Dar de baja</SecondaryButton>
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
import { ElMessage, ElMessageBox } from 'element-plus'; // Para notificaciones
import { format } from "date-fns";
import { Link, useForm } from '@inertiajs/vue3'; // No se usa directamente pero es buena práctica tenerla
import throttle from 'lodash/throttle';

export default {
    data() {
        const statusForm = useForm({
            disabled_at: format(new Date(), "yyyy-MM-dd"),
        });

        return {
            statusForm,
            loading: false, // Para mostrar un estado de carga en la tabla
            search: this.filters.search || '',
            showChangeStatusModal: false,
            selectedUser: null, // Usuario seleccionado para cambiar estatus
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
    },
    props: {
        users: Object, // La data paginada es un objeto
        filters: Object, // Filtros de búsqueda
    },
    methods: {
        // Busqueda de usuarios
        searchUsers() {
            // 1. Inicia el estado de carga
            this.loading = true;

            this.$inertia.get(this.route('users.index'), {
                search: this.search
            }, {
                preserveState: true,
                replace: true,
                // 2. Usa el callback onFinish para detener la carga cuando todo termine
                onFinish: () => {
                    this.loading = false;
                },
            });
        },
        // Maneja el cambio de página en la paginación.
        handleCurrentChange(page) {
            this.$inertia.get(this.route('users.index', { page: page }));
        },

        // Formatea una fecha en un formato legible.
        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('es-MX', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });
        },

        // Maneja los comandos del menú desplegable de acciones.
        handleCommand(command) {
            const commandName = command.split('-')[0];
            const userId = command.split('-')[1];

            if (commandName == 'changeStatus') {
                this.selectedUser = this.users.data.find(u => u.id == userId);
                if (this.selectedUser.is_active) {
                    this.showChangeStatusModal = true;
                } else {
                    this.changeStatus();
                }
            } else {
                this.$inertia.get(route('users.' + commandName, userId));
            }
        },

        // Cambia el estatus activo/inactivo de un usuario.
        changeStatus() {
            this.statusForm.put(route("users.change-status", this.selectedUser), {
                onSuccess: () => {
                    this.statusForm.reset();
                    this.showChangeStatusModal = false;
                    ElMessage({
                        type: 'success',
                        message: 'Estatus actualizado',
                    });
                },
            });
        },

        // Muestra un diálogo de confirmación para eliminar un usuario.
        deleteUser(user) {
            ElMessageBox.confirm(
                `¿Estás seguro de que deseas eliminar a <strong>${user.name}</strong>? Esta acción no se puede deshacer.`,
                'Confirmar eliminación',
                {
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning',
                    dangerouslyUseHTMLString: true, // Para usar <strong> en el mensaje
                }
            ).then(() => {
                // Si el usuario confirma, se envía la petición de borrado
                this.$inertia.delete(this.route('users.destroy', user), {
                    onSuccess: () => {
                        ElMessage({
                            type: 'success',
                            message: 'Usuario eliminado correctamente',
                        });
                    },
                });
            }).catch(() => {
                // Si el usuario cancela, se muestra un mensaje informativo
                ElMessage({
                    type: 'info',
                    message: 'Eliminación cancelada',
                });
            });
        }
    }
}
</script>
