<template>
    <AppLayout title="Crear tutorial / manual">
        <div class="px-4 sm:px-0">
            <div class="flex items-center space-x-2">
                <Back />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Crear nuevo tutorial o manual
                </h2>
            </div>
        </div>

        <div class="py-7">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6 md:p-8">
                    
                    <el-form :model="form" label-position="top" @submit.prevent="store">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">
                            
                            <el-form-item label="Tipo de recurso*" prop="type" class="col-span-2">
                                <el-radio-group v-model="form.type">
                                    <el-radio-button label="Tutorial" />
                                    <el-radio-button label="Manual" />
                                </el-radio-group>
                                <InputError :message="form.errors.type" class="mt-2" />
                            </el-form-item>

                            <el-form-item class="col-span-2">
                                <TextInput
                                    v-model="form.title"
                                    type="text"
                                    label="Título*"
                                    :error="form.errors.title"
                                    placeholder="Escribe aquí el título"
                                />
                            </el-form-item>
                            
                            <el-form-item class="col-span-2">
                                <TextInput
                                    v-model="form.description"
                                    type="text"
                                    label="Objetivo o descripción*"
                                    :error="form.errors.description"
                                    :isTextarea="true" :withMaxLength="true" :maxLength="255"
                                    placeholder="Describe brevemente el propósito de este recurso"
                                />
                            </el-form-item>
                            
                            <el-form-item v-if="form.type == 'Tutorial'" label="Imagen de portada*" prop="cover" class="col-span-2 md:col-span-1">
                                <FileUploader @files-selected="form.cover = $event[0]" :multiple="false" acceptedFormat="imagen" />
                                <InputError :message="form.errors.cover" class="mt-2" />
                            </el-form-item>

                            <el-form-item :label="form.type == 'Manual' ? 'Archivo del Manual (PDF)*' : 'Archivo de Video*'" prop="media" class="col-span-2 md:col-span-1">
                                <FileUploader @files-selected="form.media = $event[0]" :multiple="false"
                                              :acceptedFormat="form.type == 'Manual' ? 'pdf' : 'Video'" />
                                <InputError :message="form.errors.media" class="mt-2" />
                            </el-form-item>
                        </div>
                        
                        <div class="flex justify-end mt-8">
                            <SecondaryButton :loading="form.processing">
                                Guardar
                            </SecondaryButton>
                        </div>
                    </el-form>
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
import Back from "@/Components/MyComponents/Back.vue";
import { Link, useForm } from "@inertiajs/vue3";

export default {
    data() {
        const form = useForm({
            title: null,
            description: null,
            type: 'Tutorial', // Valor por defecto
            media: null,
            cover: null,
        });
        return {
            form,
        };
    },
    components: {
        Back,
        Link,
        TextInput,
        InputError,
        AppLayout,
        FileUploader,
        SecondaryButton,
    },
    methods: {
        store() {
            this.form.post(route("manuals.store"), {
                onSuccess: () => {
                    this.$notify({
                        title: "Éxito",
                        message: "Se ha registrado el nuevo tutorial / manual",
                        type: "success",
                    });
                },
            });
        },
    }
};
</script>

<style scoped>
/* Clase para resaltar inputs con errores de Inertia */
.is-invalid .el-input__wrapper {
    box-shadow: 0 0 0 1px #f56c6c;
}
</style>