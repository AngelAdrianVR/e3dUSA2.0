<template>
    <AppLayout title="Editar tutorial / manual">
        <div class="px-4 sm:px-0">
            <div class="flex items-center space-x-2">
                <Back />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Editar tutorial o manual
                </h2>
            </div>
        </div>

        <div class="py-7">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6 md:p-8">
                    
                    <div v-if="loading" class="text-center py-10 text-gray-500 dark:text-gray-400">
                        <i class="fa-solid fa-spinner fa-spin text-4xl"></i>
                        <p class="mt-4">Cargando archivos existentes...</p>
                    </div>

                    <el-form v-else :model="form" label-position="top" @submit.prevent="update">
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

                            <div v-if="manual.media?.length" label="Archivos adjuntos" class="grid grid-cols-2 lg:grid-cols-3 gap-3 col-span-full mb-3">
                                <label class="col-span-full text-gray-700 dark:text-white text-sm" for="">Archivos adjuntos</label>
                                <FileView v-for="file in manual.media" :key="file" :file="file" :deletable="true"
                                    @delete-file="deleteFile($event)" />
                            </div>
                            
                            <el-form-item v-if="form.type == 'Tutorial'" label="Imagen de portada" prop="cover" class="col-span-2 md:col-span-1">
                                <FileUploader ref="cover" @files-selected="form.cover = $event[0]; updateMedia = true;" :multiple="false" acceptedFormat="imagen" />
                                <InputError :message="form.errors.cover" class="mt-2" />
                            </el-form-item>

                            <el-form-item :label="form.type == 'Manual' ? 'Archivo del Manual (PDF)' : 'Archivo de Video'" prop="media" class="col-span-2 md:col-span-1">
                                <FileUploader ref="media" @files-selected="form.media = $event[0]; updateMedia = true;" :multiple="false" :acceptedFormat="form.type == 'Manual' ? 'pdf' : 'Video'" />
                                <InputError :message="form.errors.media" class="mt-2" />
                            </el-form-item>
                        </div>
                        
                        <div class="flex justify-end mt-8">
                            <SecondaryButton @click="update" :loading="form.processing">
                                Guardar cambios
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
import FileView from "@/Components/MyComponents/FileView.vue";
import { Link, useForm } from "@inertiajs/vue3";
// Importaciones explícitas de Element Plus para claridad

export default {
    data() {
        const form = useForm({
            title: this.manual.title,
            description: this.manual.description,
            type: this.manual.type,
            media: null,
            cover: null,
        });
        return {
            form,
            updateMedia: false,
            loading: false, // para el pre-cargado de archivos
        }
    },
    props: {
        manual: Object,
    },
    components: {
        SecondaryButton,
        FileUploader,
        InputError,
        AppLayout,
        TextInput,
        FileView,
        Back,
        Link,
    },
    methods: {
        update() {
            if (this.updateMedia) {
                this.form.post(route("manuals.update-with-media", this.manual), {
                    onSuccess: () => {
                        this.$notify({
                            title: "Éxito",
                            message: "El recurso se ha actualizado correctamente",
                            type: "success",
                        });
                    },
                });
            } else {
                this.form.put(route("manuals.update", this.manual), {
                    onSuccess: () => {
                        this.$notify({
                            title: "Éxito",
                            message: "El recurso se ha actualizado correctamente",
                            type: "success",
                        });
                    },
                });
            }
        },
        deleteFile(fileId) {
            this.manual.media = this.manual.media.filter(m => m.id !== fileId);
        },
        async createFileFromUrl(url, fileName, mimeType) {
            return await fetch(url)
                .then(response => response.blob())
                .then(blob => new File([blob], fileName, { type: mimeType }))
        },
        async addMedia() {
            this.loading = true;
            // Agregar media principal al formulario
            const media = this.manual.media.find(item => item.collection_name == 'default');
            if (media) {
                const fileMedia = await this.createFileFromUrl(media.original_url, media.file_name, media.mime_type);
                // Usamos un timeout para asegurar que el ref esté disponible
                setTimeout(() => this.$refs.media?.selectedFiles.push(fileMedia), 100);
            }
            
            // Agregar la portada si es un tutorial
            if (this.manual.type !== 'Manual') {
                const cover = this.manual.media.find(item => item.collection_name == 'cover');
                if (cover) {
                    const fileCover = await this.createFileFromUrl(cover.original_url, cover.file_name, cover.mime_type);
                    setTimeout(() => this.$refs.cover?.selectedFiles.push(fileCover), 100);
                }
            }
            this.loading = false;
        }
    },
    mounted() {
    //    this.addMedia();
    },
};
</script>