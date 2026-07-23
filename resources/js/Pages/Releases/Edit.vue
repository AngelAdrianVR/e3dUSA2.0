<template>
    <AppLayout title="Editar Versión">
        <div class="py-12 min-h-screen transition-colors duration-300">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                
                <div class="flex items-center justify-between mb-8 px-4 sm:px-0">
                    <Link :href="route('admin.releases.index')" class="flex items-center text-sm text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 transition-colors">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        Atrás
                    </Link>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Editar Actualización</h2>
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
                                <input v-model="form.version" type="text" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:bg-white dark:focus:bg-gray-800 focus:border-blue-500 focus:ring-blue-500 transition-all shadow-sm text-sm">
                                <p v-if="form.errors.version" class="text-red-500 text-xs mt-1">{{ form.errors.version }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Título</label>
                                <input v-model="form.title" type="text" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:bg-white dark:focus:bg-gray-800 focus:border-blue-500 focus:ring-blue-500 transition-all shadow-sm text-sm">
                                <p v-if="form.errors.title" class="text-red-500 text-xs mt-1">{{ form.errors.title }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Sección: Audiencia -->
                    <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 sm:p-8 mb-8 transition-colors">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <span class="w-8 h-8 rounded-full bg-purple-100 text-purple-600 dark:bg-purple-900/50 dark:text-purple-300 flex items-center justify-center text-sm mr-3 font-bold">
                                <i class="fa-solid fa-users text-xs"></i>
                            </span>
                            Audiencia
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Define quiénes verán esta actualización.</p>

                        <!-- Toggle: Todos los usuarios -->
                        <div class="flex items-center justify-between p-4 rounded-xl bg-gray-50 dark:bg-gray-900 mb-4">
                            <div>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Mostrar a todos los usuarios activos</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500">La actualización se mostrará a todos los usuarios con cuenta activa.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                                <input type="checkbox" v-model="form.target_all" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-purple-300 dark:peer-focus:ring-purple-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-purple-600"></div>
                            </label>
                        </div>

                        <!-- Selector de usuarios específicos (si no es para todos) -->
                        <div v-if="!form.target_all">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Usuarios específicos</label>
                                <button type="button" @click="selectAllUsers" class="text-xs text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 font-medium">
                                    Seleccionar todos
                                </button>
                            </div>
                            <el-select 
                                v-model="form.target_users" 
                                multiple 
                                filterable 
                                placeholder="Busca y selecciona usuarios..."
                                class="w-full"
                                collapse-tags
                                collapse-tags-tooltip
                            >
                                <el-option 
                                    v-for="user in allUsers" 
                                    :key="user.id" 
                                    :label="user.name" 
                                    :value="user.id" 
                                />
                            </el-select>
                            <p class="text-xs text-gray-400 mt-2">
                                <span v-if="form.target_users.length > 0">{{ form.target_users.length }} usuario(s) seleccionado(s)</span>
                                <span v-else>No has seleccionado ningún usuario.</span>
                            </p>
                        </div>

                        <p v-if="form.errors.target_users" class="text-red-500 text-xs mt-1">{{ form.errors.target_users }}</p>
                    </div>

                    <!-- Sección 2: Items -->
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
                            <button type="button" @click="removeItem(index)" v-if="form.items.length > 1" class="absolute top-4 right-4 text-gray-400 hover:text-red-500 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                            
                            <div class="grid grid-cols-1 gap-6">
                                <!-- CAMPO RESTAURADO: Módulo -->
                                <div>
                                    <label class="block text-xs font-semibold uppercase text-gray-400 tracking-wider mb-2">Módulo {{ index + 1 }}</label>
                                    <input v-model="item.module_name" type="text" placeholder="Ej: Producción" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:bg-white dark:focus:bg-gray-800 focus:border-indigo-500 focus:ring-indigo-500 transition-all text-sm">
                                </div>
                                
                                <!-- CAMPO RESTAURADO: Explicación -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Explicación</label>
                                    <textarea v-model="item.description" rows="3" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:bg-white dark:focus:bg-gray-800 focus:border-indigo-500 focus:ring-indigo-500 transition-all text-sm"></textarea>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Evidencia Visual (Imagen/Video)</label>
                                    
                                    <!-- Previsualización de lo existente -->
                                    <div v-if="item.existing_media_url && !item.image" class="mb-3 p-2 bg-gray-100 dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-zinc-700 flex items-center gap-3">
                                        <div class="h-12 w-12 rounded-lg bg-gray-200 dark:bg-zinc-800 overflow-hidden flex-shrink-0">
                                            <video v-if="item.existing_mime?.startsWith('video/')" :src="item.existing_media_url" class="w-full h-full object-cover"></video>
                                            <img v-else :src="item.existing_media_url" class="w-full h-full object-cover">
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Archivo actual guardado</p>
                                            <p class="text-xs text-blue-500">Sube uno nuevo para reemplazarlo</p>
                                        </div>
                                    </div>

                                    <div class="relative group">
                                        <input type="file" @change="handleFileChange($event, index)" accept="image/*,video/mp4,video/webm" class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 dark:file:bg-indigo-900/30 file:text-indigo-700 dark:file:text-indigo-300 hover:file:bg-indigo-100 dark:hover:file:bg-indigo-900/50 transition-all cursor-pointer rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                                    </div>
                                    <p class="text-xs text-gray-400 mt-2">Soporta Imágenes, GIFs y Videos cortos (MP4/WebM)</p>
                                    <p v-if="form.errors[`items.${index}.image`]" class="text-red-500 text-xs mt-1">{{ form.errors[`items.${index}.image`] }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-10 mb-20 flex justify-end items-center gap-4">
                        <Link :href="route('admin.releases.index')" class="text-gray-500 dark:text-gray-400 text-sm font-medium hover:text-gray-700 dark:hover:text-gray-200 transition-colors">Cancelar</Link>
                        
                        <button type="submit" :disabled="form.processing" class="inline-flex items-center px-8 py-3 bg-black dark:bg-white text-white dark:text-black text-sm font-bold rounded-full shadow-xl hover:shadow-2xl hover:scale-105 transition-all transform duration-200 disabled:opacity-50">
                            {{ form.processing ? 'Guardando...' : 'Actualizar Cambios' }}
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
    components: { AppLayout, Link },
    props: {
        release: Object,
        allUsers: Array,
    },
    data() {
        return {
            form: useForm({
                _method: 'PUT',
                version: this.release.version,
                title: this.release.title,
                target_all: this.release.target_all ?? true,
                target_users: this.release.target_users?.map(u => u.id) || [],
                items: this.release.items.map(item => ({
                    id: item.id, // Importante para identificarlo
                    ui_id: item.id,
                    module_name: item.module_name,
                    description: item.description,
                    image: null,
                    // Datos auxiliares para vista (no se envían al form.post automáticamente si no están en la data, pero useForm es reactivo)
                    existing_media_url: item.media?.[0]?.original_url || null,
                    existing_mime: item.media?.[0]?.mime_type || null
                }))
            })
        }
    },
    methods: {
        selectAllUsers() {
            this.form.target_users = this.allUsers.map(u => u.id);
        },
        addItem() {
            this.form.items.push({
                ui_id: Date.now(),
                id: null,
                module_name: '',
                description: '',
                image: null,
                existing_media_url: null
            });
        },
        removeItem(index) {
            if (this.form.items.length > 1) {
                this.form.items.splice(index, 1);
            }
        },
        handleFileChange(event, index) {
            this.form.items[index].image = event.target.files[0];
        },
        submit() {
            // Nota: usamos post a la ruta update por el _method: PUT
            this.form.post(route('admin.releases.update', this.release.id), {
                forceFormData: true,
            });
        }
    }
}
</script>