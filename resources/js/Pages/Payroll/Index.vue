<script>
import AppLayout from '@/Layouts/AppLayout.vue'; // Cambiado a AppLayout como en tu ejemplo
import SecondaryButton from '@/Components/SecondaryButton.vue'; // Componente de tu proyecto
import { Head, Link } from '@inertiajs/vue3';

export default {
    components: {
        AppLayout,
        SecondaryButton,
        Head,
        Link,
    },
    props: {
        payrolls: {
            type: Array,
            required: true,
        },
    },
    methods: {
        // Maneja los comandos del menú desplegable de acciones.
        handleCommand(command) {
            const [action, payrollId] = command.split('-');

            if (action === 'manage') {
                this.$inertia.get(route('payrolls.show', payrollId));
            }
        },
    }
}
</script>

<template>
    <Head title="Periodos de Nómina" />

    <AppLayout title="Nóminas">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Gestión de Nóminas
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Periodos Registrados
                        </h3>
                        <!-- Asumo que tendrás una ruta 'payrolls.create' en el futuro -->
                        <Link :href="route('payrolls.index')">
                            <SecondaryButton>
                                <i class="fa-solid fa-plus mr-2"></i>
                                Nuevo Periodo
                            </SecondaryButton>
                        </Link>
                    </div>

                    <!-- Tabla de Element Plus con el nuevo estilo -->
                    <el-table :data="payrolls" stripe style="width: 100%" max-height="550"
                        class="dark:!bg-slate-900 dark:!text-gray-300">
                        <el-table-column prop="id" label="ID" width="80" />
                        <el-table-column prop="week_number" label="Semana #" width="120" align="center" />
                        <el-table-column prop="start_date" label="Fecha de Inicio" />
                        <el-table-column prop="end_date" label="Fecha de Fin" />
                        <el-table-column label="Estatus" width="150" align="center">
                            <template #default="scope">
                                <el-tag :type="scope.row.status === 'Abierta' ? 'success' : 'info'" disable-transitions>
                                    {{ scope.row.status }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="Acciones" width="120" align="right">
                            <template #default="scope">
                                <el-dropdown trigger="click" @command="handleCommand">
                                    <button @click.stop
                                        class="el-dropdown-link justify-center items-center size-8 rounded-full text-gray-500 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-slate-700 transition-all duration-200 ease-in-out">
                                        <i class="fa-solid fa-ellipsis-vertical"></i>
                                    </button>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item :command="'manage-' + scope.row.id">
                                                <i class="fa-solid fa-folder-open mr-2"></i>
                                                Gestionar
                                            </el-dropdown-item>
                                            <!-- Aquí puedes agregar más opciones en el futuro -->
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>