<template>
    <AppLayout title="Editar Maquinaria">
        <div class="px-4 sm:px-0">
            <div class="flex items-center space-x-2">
                <Back />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Editar máquina
                </h2>
            </div>
        </div>

        <div ref="formContainer" class="py-7">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6 md:p-8">
                    
                    <form :model="form" label-position="top" @submit.prevent="update" class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
                        <div>
                            <TextInput
                                label="Nombre*"
                                v-model="form.name"
                                type="text"
                                :error="form.errors.name"
                                placeholder="Ej. Torno CNC"
                            />
                        </div>
                        
                        <div>
                            <TextInput
                                label="Número de serie"
                                v-model="form.serial_number"
                                type="text"
                                :error="form.errors.serial_number"
                                placeholder="Ej. XJ-2000"
                            />
                        </div>

                        <div>
                            <TextInput label="Peso (Kg)" v-model="form.weight" type="number" step="0.01" :error="form.errors.weight" placeholder="Ej. 1500" />
                        </div>

                        <div>
                            <TextInput label="Largo (cm)" v-model="form.large" type="number" step="0.01" :error="form.errors.large" placeholder="Ej. 220" />
                        </div>

                        <div>
                            <TextInput label="Ancho (cm)" v-model="form.width" type="number" step="0.01" :error="form.errors.width" placeholder="Ej. 90" />
                        </div>

                        <div>
                            <TextInput label="Alto (cm)" v-model="form.height" type="number" step="0.01" :error="form.errors.height" placeholder="Ej. 180" />
                        </div>
                        
                        <div>
                            <TextInput label="Costo de adquisición" v-model="form.cost" type="number" step="0.01" :error="form.errors.cost" placeholder="Ej. 250000" />
                        </div>

                        <div>
                            <TextInput
                                label="Proveedor"
                                v-model="form.supplier"
                                type="text"
                                :error="form.errors.supplier"
                                placeholder="Nombre del proveedor"
                            />
                        </div>

                        <div>
                            <TextInput
                                label="Mantenimiento cada (días)*"
                                v-model="form.days_next_maintenance"
                                type="number"
                                :error="form.errors.days_next_maintenance"
                                placeholder="Ej. 90"
                            />
                        </div>

                        <div>
                            <label class="text-gray-700 dark:text-gray-100 text-sm ml-3" for="">Fecha de adquisición*</label>
                            <el-date-picker
                                v-model="form.adquisition_date"
                                type="date"
                                placeholder="Selecciona una fecha"
                                format="YYYY/MM/DD"
                                value-format="YYYY-MM-DD"
                                :disabled-date="disabledDate"
                                class="!w-full"
                            />
                            <InputError :message="form.errors.adquisition_date" />
                        </div>

                        <div v-if="machine.media?.length" label="Archivos adjuntos" class="grid grid-cols-2 lg:grid-cols-3 gap-3 col-span-full mb-3">
                              <label class="col-span-full text-gray-700 dark:text-white text-sm" for="">Archivos adjuntos</label>
                              <FileView v-for="file in machine.media" :key="file" :file="file" :deletable="true"
                                  @delete-file="deleteFile($event)" />
                          </div>

                        <div label="Imagen de la máquina" prop="media" class="col-span-2 mt-5">
                            <FileUploader @files-selected="form.media = $event" :multiple="true" acceptedFormat="imagen" />
                            <InputError :message="form.errors.media" class="mt-2" />
                        </div>
                        
                        <div class="flex justify-end mt-8 col-span-full">
                            <SecondaryButton :loading="form.processing" :disabled="form.processing">
                                Guardar cambios
                            </SecondaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import InputError from "@/Components/InputError.vue";
import FileUploader from "@/Components/MyComponents/FileUploader.vue";
import TextInput from "@/Components/TextInput.vue";
import FileView from "@/Components/MyComponents/FileView.vue";
import Back from "@/Components/MyComponents/Back.vue";
import { ElMessage, ElMessageBox } from 'element-plus'; // Para notificaciones
import { useForm } from "@inertiajs/vue3";

export default {
    data() {
        // Inicialización del formulario con useForm de Inertia
        const form = useForm({
            name: this.machine.name,
            serial_number: this.machine.serial_number,
            weight: this.machine.weight,
            width: this.machine.width,
            large: this.machine.large,
            height: this.machine.height,
            cost: this.machine.cost,
            supplier: this.machine.supplier,
            adquisition_date: this.machine.adquisition_date,
            days_next_maintenance: this.machine.days_next_maintenance,
            media: [],
        });

        return {
            form,
        };
    },
    components: {
        Back,
        FileView,
        TextInput,
        AppLayout,
        InputError,
        FileUploader,
        SecondaryButton,
    },
    props:{
      machine: Object
    },
    methods: {
        // Método para enviar el formulario
        update() {
            this.form.put(route("machines.update"), {
                onSuccess: () => {
                    ElMessage({
                        type: 'success',
                        message: 'Máquina actualizada',
                    });
                    // Opcional: resetear el formulario tras el éxito
                    this.form.reset(); 
                },
                onError: () => {
                    // Hacer scroll al principio de la página si hay errores
                    this.$refs.formContainer.scrollIntoView({ behavior: 'smooth' });
                }
            });
        },
        deleteFile(fileId) {
            this.machine.media = this.machine.media.filter(m => m.id !== fileId);
        },
        // Función para deshabilitar fechas futuras en el date-picker
        disabledDate(time) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return time.getTime() > today.getTime();
        },
    }
};
</script>