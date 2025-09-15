<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import Back from "@/Components/MyComponents/Back.vue";
import InputError from "@/Components/InputError.vue";
import TextInput from "@/Components/TextInput.vue";
import InputLabel from "@/Components/InputLabel.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import { useForm } from "@inertiajs/vue3";
import { computed, onMounted } from 'vue';

export default {
    components: {
        AppLayout,
        Back,
        InputError,
        TextInput,
        InputLabel,
        SecondaryButton,
    },
    props: {
        user: Object,
        roles: Array,
        bonuses: Array,
        discounts: Array,
    },
    setup(props) {
        const form = useForm({
            // User data
            name: props.user.name,
            email: props.user.email,
            password: '',
            role: props.user.roles[0]?.name, // Asume que el usuario tiene un solo rol
            // EmployeeDetail data
            department: props.user.employee_detail?.department || '',
            job_position: props.user.employee_detail?.job_position || '',
            week_salary: props.user.employee_detail?.week_salary || null,
            birthdate: props.user.employee_detail?.birthdate,
            join_date: props.user.employee_detail?.join_date,
            selected_bonuses: props.user.employee_detail?.bonuses.map(b => b.id) || [],
            selected_discounts: props.user.employee_detail?.discounts.map(d => d.id) || [],
            work_schedule: [
                { day: 'Lunes', works: false, start_time: null, end_time: null, break_minutes: 0 },
                { day: 'Martes', works: false, start_time: null, end_time: null, break_minutes: 0 },
                { day: 'Miércoles', works: false, start_time: null, end_time: null, break_minutes: 0 },
                { day: 'Jueves', works: false, start_time: null, end_time: null, break_minutes: 0 },
                { day: 'Viernes', works: false, start_time: null, end_time: null, break_minutes: 0 },
                { day: 'Sábado', works: false, start_time: null, end_time: null, break_minutes: 0 },
                { day: 'Domingo', works: false, start_time: null, end_time: null, break_minutes: 0 },
            ]
        });

        // Poblar el horario con los datos guardados
        onMounted(() => {
            const savedSchedule = props.user.employee_detail?.work_days || [];
            if (Array.isArray(savedSchedule)) {
                form.work_schedule.forEach(daySchedule => {
                    const savedDay = savedSchedule.find(d => d.day === daySchedule.day);
                    if (savedDay) {
                        daySchedule.works = savedDay.works;
                        daySchedule.start_time = savedDay.start_time;
                        daySchedule.end_time = savedDay.end_time;
                        daySchedule.break_minutes = savedDay.break_minutes;
                    }
                });
            }
        });

        const update = () => {
            form.put(route("users.update", props.user.id));
        };

        const totalWeeklyMinutes = computed(() => {
            return form.work_schedule.reduce((total, day) => {
                if (day.works && day.start_time && day.end_time) {
                    const startTime = new Date(`1970-01-01T${day.start_time}`);
                    const endTime = new Date(`1970-01-01T${day.end_time}`);
                    const diff = (endTime - startTime) / (1000 * 60); // Diferencia en minutos
                    return total + (diff - (day.break_minutes || 0));
                }
                return total;
            }, 0);
        });

        const formattedTotalHours = computed(() => {
            if (totalWeeklyMinutes.value <= 0) return '0h 0m';
            const hours = Math.floor(totalWeeklyMinutes.value / 60);
            const minutes = Math.floor(totalWeeklyMinutes.value % 60);
            return `${hours}h ${minutes}m`;
        });

        return { form, update, formattedTotalHours };
    }
}
</script>

<template>
    <AppLayout title="Editar Usuario">
        <div class="px-4 sm:px-0">
            <div class="flex items-center space-x-2">
                <Back :href="route('users.index')" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Editar usuario: {{ user.name }}
                </h2>
            </div>
        </div>

        <div class="py-7">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6 md:p-8">
                    <form @submit.prevent="update">
                        <!-- SECCIÓN DE INFORMACIÓN DEL EMPLEADO -->
                        <section class="mb-8">
                            <h3 class="font-bold text-lg text-gray-800 dark:text-gray-200 mb-4 border-b pb-2">
                                Información del empleado</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-x-5 gap-y-1">
                                <div>
                                    <InputLabel value="Nombre completo*" />
                                    <TextInput v-model="form.name" type="text" class="w-full" :error="form.errors.name"
                                        placeholder="John Doe" />
                                </div>
                                <div>
                                    <InputLabel value="Puesto*" />
                                    <TextInput v-model="form.job_position" type="text" :error="form.errors.job_position"
                                        class="w-full" placeholder="Ej. Gerente de ventas" />
                                </div>
                                <div>
                                    <InputLabel value="Departamento*" />
                                    <el-select v-model="form.department" placeholder="Selecciona el departamento"
                                        class="!w-full">
                                        <el-option v-for="department in ['Ventas', 'Producción', 'Diseño', 'Administración', 'Finanzas', 'Mercadotecnia']" :key="department"
                                            :label="department" :value="department" />
                                    </el-select>
                                    <InputError :message="form.errors.department" class="mt-3" />
                                </div>
                                <div>
                                    <InputLabel value="Salario semanal*" />
                                    <TextInput v-model="form.week_salary" type="number" :step="0.01" class="w-full"
                                        placeholder="0.00" :error="form.errors.week_salary">
                                        <template #icon-left>$</template>
                                    </TextInput>
                                </div>
                                <div>
                                    <InputLabel value="Fecha de nacimiento*" />
                                    <el-date-picker v-model="form.birthdate" type="date" placeholder="Selecciona"
                                        format="YYYY/MM/DD" value-format="YYYY-MM-DD" class="!w-full" />
                                    <InputError :message="form.errors.birthdate" class="mt-3" />
                                </div>
                                <div>
                                    <InputLabel value="Fecha de ingreso*" />
                                    <el-date-picker v-model="form.join_date" type="date" placeholder="Selecciona"
                                        format="YYYY/MM/DD" value-format="YYYY-MM-DD" class="!w-full" />
                                    <InputError :message="form.errors.join_date" />
                                </div>
                            </div>
                        </section>

                        <!-- SECCIÓN DE ASIGNACIONES Y HORARIO -->
                        <section class="mb-8">
                            <h3 class="font-bold text-lg text-gray-800 dark:text-gray-200 mb-4 border-b pb-2">
                                Asignaciones y horario</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-1">
                                <div>
                                    <InputLabel value="Bonos aplicables" />
                                    <el-select v-model="form.selected_bonuses" multiple
                                        placeholder="Selecciona uno o más bonos" class="!w-full">
                                        <el-option v-for="bonus in bonuses" :key="bonus.id" :label="bonus.name"
                                            :value="bonus.id" />
                                    </el-select>
                                    <InputError :message="form.errors.selected_bonuses" />
                                </div>
                                <div>
                                    <InputLabel value="Descuentos aplicables" />
                                    <el-select v-model="form.selected_discounts" multiple
                                        placeholder="Selecciona uno o más descuentos" class="!w-full">
                                        <el-option v-for="discount in discounts" :key="discount.id"
                                            :label="discount.name" :value="discount.id" />
                                    </el-select>
                                    <InputError :message="form.errors.selected_discounts" />
                                </div>
                            </div>

                            <div class="mt-6">
                                <InputLabel value="Horario Semanal" />
                                <div class="border dark:border-slate-700 rounded-lg mt-2 overflow-hidden">
                                    <!-- Header para Desktop -->
                                    <div
                                        class="hidden md:grid md:grid-cols-5 bg-gray-100 dark:bg-slate-800 dark:text-gray-100 p-2 text-sm font-semibold">
                                        <span>Día</span>
                                        <span class="text-center">Trabaja</span>
                                        <span class="text-center">Entrada</span>
                                        <span class="text-center">Salida</span>
                                        <span class="text-center">Comida (min)</span>
                                    </div>
                                    <!-- Filas de días -->
                                    <div v-for="(day, index) in form.work_schedule" :key="index"
                                        class="grid grid-cols-2 md:grid-cols-5 items-center p-3 gap-4 border-b dark:border-slate-700 dark:text-gray-100 last:border-b-0">
                                        <div class="font-semibold col-span-2 md:col-span-1">{{ day.day }}</div>
                                        <div class="text-center">
                                            <el-checkbox v-model="day.works" size="large" />
                                        </div>
                                        <div v-if="day.works"
                                            class="col-span-full md:col-span-3 grid grid-cols-1 sm:grid-cols-3 gap-4">
                                            <div>
                                                <el-time-picker v-model="day.start_time" placeholder="Entrada"
                                                    format="hh:mm A" value-format="HH:mm:ss" class="!w-full" />
                                                <InputError :message="form.errors[`work_schedule.${index}.start_time`]"
                                                    class="mt-3" />
                                            </div>
                                            <div>
                                                <el-time-picker v-model="day.end_time" placeholder="Salida"
                                                    format="hh:mm A" value-format="HH:mm:ss" class="!w-full" />
                                                <InputError :message="form.errors[`work_schedule.${index}.end_time`]"
                                                    class="mt-3" />
                                            </div>
                                            <div>
                                                <el-input-number v-model="day.break_minutes" :min="0" ::step="15"
                                                    placeholder="Minutos" class="!w-full" />
                                                <InputError
                                                    :message="form.errors[`work_schedule.${index}.break_minutes`]"
                                                    class="mt-3" />
                                            </div>
                                        </div>
                                        <div v-else
                                            class="col-span-full md:col-span-3 text-center text-gray-400 italic">
                                            No laborable
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right mt-2 font-semibold text-base text-[#373737] dark:text-gray-100">
                                    Total semanal: {{ formattedTotalHours }}
                                </div>
                            </div>
                        </section>

                        <!-- SECCIÓN DE DATOS DE ACCESO -->
                        <section class="mb-8">
                            <h3 class="font-bold text-lg text-gray-800 dark:text-gray-200 mb-4 border-b pb-2">Datos de
                                acceso al sistema</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-1">
                                <div>
                                    <InputLabel value="Correo electrónico*" />
                                    <TextInput v-model="form.email" type="email" class="w-full"
                                        placeholder="Personal, empresarial o inventado" :error="form.errors.email" />
                                </div>
                                <div>
                                    <InputLabel value="Contraseña*" />
                                    <TextInput v-model="form.password" type="text" :error="form.errors.password"
                                        class="w-full" />
                                </div>
                                <div>
                                    <InputLabel value="Rol del sistema*" />
                                    <el-select v-model="form.role" placeholder="Selecciona un rol" class="!w-full">
                                        <el-option v-for="role in roles" :key="role.id" :label="role.name"
                                            :value="role.name" />
                                    </el-select>
                                    <InputError :message="form.errors.role" />
                                </div>
                            </div>
                        </section>

                        <div class="flex justify-end mt-8">
                            <SecondaryButton :loading="form.processing" :disabled="form.processing">
                                Actualizar Usuario
                            </SecondaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
