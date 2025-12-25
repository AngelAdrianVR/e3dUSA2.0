<template>
    <AppLayout title="Nueva Versión">
        <div class="py-12 min-h-screen transition-colors duration-300">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                
                <div class="flex items-center justify-between mb-8 px-4 sm:px-0">
                    <Link :href="route('admin.releases.index')" class="flex items-center text-sm text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 transition-colors">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        Atrás
                    </Link>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Nueva Actualización</h2>
                    <div class="w-12"></div>
                </div>

                <form @submit.prevent="submit">
                    
                    <!-- Sección 1: Datos Generales -->
                    <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 sm:p-8 mb-8 transition-colors">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900/50 dark:text-blue-300 flex items-center justify-center text-sm mr-3 font-bold">1</span>
                            Información General
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Versión</label>
                                <input v-model="form.version" type="text" placeholder="Ej: v2.5.0" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:bg-white dark:focus:bg-gray-800 focus:border-blue-500 focus:ring-blue-500 transition-all shadow-sm text-sm">
                                <p v-if="form.errors.version" class="text-red-500 text-xs mt-1">{{ form.errors.version }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Título del Release</label>
                                <input v-model="form.title" type="text" placeholder="Ej: Mejoras en Inventario" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:bg-white dark:focus:bg-gray-800 focus:border-blue-500 focus:ring-blue-500 transition-all shadow-sm text-sm">
                                <p v-if="form.errors.title" class="text-red-500 text-xs mt-1">{{ form.errors.title }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 2: Items / Slides -->
                    <div class="space-y-6">
                        <div class="flex items-center justify-between px-2">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                                <span class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-300 flex items-center justify-center text-sm mr-3 font-bold">2</span>
                                Detalles de la Actualización
                            </h3>
                            <button type="button" @click="addItem" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                Agregar Sección
                            </button>
                        </div>

                        <div v-for="(item, index) in form.items" :key="item.ui_id" class="bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 relative transition-all duration-300 hover:shadow-md">
                            
                            <!-- Botón eliminar item -->
                            <button type="button" @click="removeItem(index)" v-if="form.items.length > 1" class="absolute top-4 right-4 text-gray-400 hover:text-red-500 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                            
                            <div class="grid grid-cols-1 gap-6">
                                <!-- CAMPO RESTAURADO: Nombre del Módulo -->
                                <div>
                                    <label class="block text-xs font-semibold uppercase text-gray-400 tracking-wider mb-2">Módulo / Sección {{ index + 1 }}</label>
                                    <input v-model="item.module_name" type="text" placeholder="Ej: Producción, Reportes..." class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:bg-white dark:focus:bg-gray-800 focus:border-indigo-500 focus:ring-indigo-500 transition-all text-sm">
                                    <p v-if="form.errors[`items.${index}.module_name`]" class="text-red-500 text-xs mt-1">{{ form.errors[`items.${index}.module_name`] }}</p>
                                </div>
                                
                                <!-- CAMPO RESTAURADO: Explicación -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Explicación</label>
                                    <textarea v-model="item.description" rows="3" placeholder="Describe qué cambió en esta sección..." class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:bg-white dark:focus:bg-gray-800 focus:border-indigo-500 focus:ring-indigo-500 transition-all text-sm"></textarea>
                                    <p v-if="form.errors[`items.${index}.description`]" class="text-red-500 text-xs mt-1">{{ form.errors[`items.${index}.description`] }}</p>
                                </div>

                                <!-- Input de Imagen/Video -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Evidencia Visual (Imagen/Video)</label>
                                    <div class="relative group">
                                        <input type="file" @change="handleFileChange($event, index)" accept="image/*,video/mp4,video/webm" class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 dark:file:bg-indigo-900/30 file:text-indigo-700 dark:file:text-indigo-300 hover:file:bg-indigo-100 dark:hover:file:bg-indigo-900/50 transition-all cursor-pointer rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                                    </div>
                                    <p class="text-xs text-gray-400 mt-2">Soporta Imágenes, GIFs y Videos cortos (MP4/WebM)</p>
                                    <p v-if="form.errors[`items.${index}.image`]" class="text-red-500 text-xs mt-1">{{ form.errors[`items.${index}.image`] }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="mt-10 mb-20 flex justify-end items-center gap-4">
                        <Link :href="route('admin.releases.index')" class="text-gray-500 dark:text-gray-400 text-sm font-medium hover:text-gray-700 dark:hover:text-gray-200 transition-colors">Cancelar</Link>
                        
                        <button type="submit" :disabled="form.processing" class="inline-flex items-center px-8 py-3 bg-black dark:bg-white text-white dark:text-black text-sm font-bold rounded-full shadow-xl hover:shadow-2xl hover:scale-105 transition-all transform duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span v-if="form.processing">Guardando...</span>
                            <span v-else class="flex items-center">
                                Guardar Borrador
                                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </span>
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

export default {
    components: {
        AppLayout,
        Link
    },
    data() {
        return {
            form: useForm({
                version: '',
                title: '',
                items: [
                    { ui_id: Date.now(), module_name: '', description: '', image: null }
                ]
            })
        }
    },
    methods: {
        addItem() {
            this.form.items.push({
                ui_id: Date.now(), // ID único temporal para el v-for key
                module_name: '',
                description: '',
                image: null
            });
        },
        removeItem(index) {
            if (this.form.items.length > 1) {
                this.form.items.splice(index, 1);
            }
        },
        handleFileChange(event, index) {
            // Asignar el archivo real al objeto del formulario
            const file = event.target.files[0];
            this.form.items[index].image = file;
        },
        submit() {
            this.form.post(route('admin.releases.store'), {
                onSuccess: () => {
                    // Redirección manejada por el controlador, pero aquí podrías limpiar si quisieras
                }
            });
        }
    }
}
</script>