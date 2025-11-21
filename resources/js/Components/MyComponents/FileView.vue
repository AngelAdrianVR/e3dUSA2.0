<template>
    <el-tooltip :disabled="!file.original_url" placement="top" effect="dark" raw-content>
        <template #content>
            <div class="p-2">
                <img v-if="isImage" :src="file.original_url" class="rounded-md max-w-xs max-h-32 object-contain"
                    alt="Previsualización">
                <div v-else class="flex flex-col items-center space-y-2 text-white dark:text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="size-8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <p class="text-xs">Visualización no disponible</p>
                </div>
            </div>
        </template>

        <div @click="openFile"
            class="group flex items-center space-x-2 border border-gray-200 bg-gray-100 dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-white rounded-lg py-2 px-4 cursor-pointer transition-all duration-300 ease-in-out hover:border-primary dark:hover:border-primary hover:shadow-lg hover:shadow-primary/20 dark:hover:shadow-primary/10 hover:-translate-y-1">
            <figure class="h-8 w-1/5 flex-shrink-0">
                <img class="w-full h-full object-contain" :src="getFileTypeIcon()">
            </figure>
            <div :class="deletable ? 'w-3/5' : 'w-4/5'">
                <p :title="file.file_name" class="font-bold text-xs lg:text-sm truncate">{{ file.file_name }}</p>
                <p class="text-[10px] lg:text-xs text-gray-500 dark:text-gray-400">{{ (file.size / 1024).toFixed(2) }} KB</p>
            </div>

            <div v-if="deletable" class="w-1/5 flex justify-end opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                <el-popconfirm confirm-button-text="Sí" cancel-button-text="No" icon-color="#D33"
                    :title="'¿Deseas eliminar este archivo?'" @confirm="deleteFile()">
                    <template #reference>
                        <button @click.stop="" type="button" class="text-red-500 hover:text-red-700 p-1 rounded-full hover:bg-red-500/10 transition-all duration-200 hover:scale-125">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="size-5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    </template>
                </el-popconfirm>
            </div>
        </div>
    </el-tooltip>
</template>

<script>
import { ElMessage } from 'element-plus';
import axios from 'axios';

export default {
    data() {
        return {
            // No se necesitan datos locales en este componente
        }
    },
    props: {
        file: Object,
        deletable: {
            type: Boolean,
            default: false
        },
        url: {
            type: String,
            default: null
        }
    },
    emits: ['delete-file'],
    computed: {
        /**
         * Determina si el archivo es una imagen basándose en su extensión.
         * Esencial para la lógica de previsualización en el tooltip.
         */
        isImage() {
            if (!this.file.file_name) return false;
            const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            const fileExtension = this.file.file_name.split('.').pop().toLowerCase();
            return imageExtensions.includes(fileExtension);
        }
    },
    methods: {
        /**
         * Devuelve la ruta del ícono correspondiente a la extensión del archivo.
         */
        getFileTypeIcon() {
            const fileExtension = this.file.file_name?.split('.').pop().toLowerCase();

            // Si es una imagen (usando la nueva propiedad computada), muestra el ícono de imagen.
            if (this.isImage) {
                return '/images/image.png';
            }

            switch (fileExtension) {
                case 'pdf':
                    return '/images/pdf.png';
                case 'mp4':
                case 'avi':
                case 'mkv':
                case 'mov':
                    return '/images/video.png';
                case 'doc':
                case 'docx':
                    return '/images/doc.png';
                case 'xls':
                case 'xlsx':
                    return '/images/xls.png';
                default:
                    // Un ícono genérico para otros tipos de archivo.
                    return '/images/doc.png';
            }
        },
        /**
         * Abre el archivo en una nueva pestaña.
         */
        openFile() {
            let fileUrl = this.file.original_url;

            if (this.url) {
                fileUrl = this.url;
            }

            if (fileUrl) {
                window.open(fileUrl, '_blank');
            } else {
                console.error('La URL del archivo no está definida.');
                ElMessage ({
                    title: "Error de servidor",
                    message: "No se pudo abrir el archivo. Probablemente no exista o esté dañado",
                    type: "error",
                });
            }
        },
        /**
         * Elimina el archivo y emite un evento al componente padre.
         */
        async deleteFile() {
            try {
                const response = await axios.delete(route('media.delete-file', this.file.id));
                if (response.status === 200) {
                    ElMessage ({
                        title: "Archivo eliminado",
                        message: "El archivo ha sido eliminado correctamente.",
                        type: "success",
                    });
                    this.$emit('delete-file', this.file.id);
                }
            } catch (error) {
                console.error("Error al eliminar el archivo:", error);
                ElMessage ({
                    title: "Error",
                    message: "No se pudo eliminar el archivo. Inténtalo de nuevo.",
                    type: "error",
                });
            }
        }
    }
}
</script>