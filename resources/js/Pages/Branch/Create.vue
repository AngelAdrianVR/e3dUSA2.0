<template>
    <AppLayout title="Agregar Cliente">
        <div class="px-4 sm:px-0">
            <div class="flex items-center space-x-2">
                <Back :href="route('branches.index')" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Agregar nuevo cliente
                </h2>
            </div>
        </div>

        <div ref="formContainer" class="py-7">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6 md:p-8">
                    
                    <form @submit.prevent="store">
                        <!-- === SECCIÓN DATOS GENERALES === -->
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 border-b pb-2">Datos Generales</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4">
                            <TextInput label="Nombre*" v-model="form.name" type="text" :error="form.errors.name" placeholder="Nombre del cliente o prospecto" />
                            <TextInput label="RFC" v-model="form.rfc" type="text" :error="form.errors.rfc" placeholder="Registro Federal de Contribuyentes" />
                            <div class="md:col-span-2">
                                <TextInput label="Dirección" v-model="form.address" type="text" :error="form.errors.address" placeholder="Calle, número, colonia" />
                            </div>
                            <TextInput label="Código Postal" v-model="form.post_code" type="text" :error="form.errors.post_code" />
                            <div>
                                <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Estatus*</label>
                                <el-select v-model="form.status" placeholder="Selecciona un estatus" class="!w-full">
                                    <el-option label="Prospecto" value="Prospecto" />
                                    <el-option label="Cliente" value="Cliente" />
                                </el-select>
                                <InputError :message="form.errors.status" />
                            </div>
                            <div>
                                <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Vendedor Asignado</label>
                                <el-select v-model="form.account_manager_id" placeholder="Selecciona un vendedor" class="!w-full" filterable>
                                    <el-option v-for="user in users" :key="user.id" :label="user.name" :value="user.id" />
                                </el-select>
                                <InputError :message="form.errors.account_manager_id" />
                            </div>
                            <div>
                                <label class="text-gray-700 dark:text-gray-100 text-sm ml-3">Sucursal Matriz (Opcional)</label>
                                <el-select v-model="form.parent_branch_id" placeholder="Selecciona una matriz" class="!w-full" filterable clearable>
                                    <el-option v-for="branch in branches" :key="branch.id" :label="branch.name" :value="branch.id" />
                                </el-select>
                                <InputError :message="form.errors.parent_branch_id" />
                            </div>
                             <TextInput label="¿Cómo nos conoció?" v-model="form.meet_way" placeholder="Recomendación, internet, redes sociales" type="text" :error="form.errors.meet_way" />
                        </div>
                        
                        <!-- === SECCIÓN CONTACTOS === -->
                        <div class="flex justify-between items-center mt-8 mb-4 border-b pb-2">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Contactos</h3>
                            <el-button @click="addContact" type="primary" plain>
                                <i class="fa-solid fa-plus mr-2"></i> Agregar Contacto
                            </el-button>
                        </div>

                        <!-- Contactos dinámicos -->
                        <div v-for="(contact, index) in form.contacts" :key="index" class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4 border rounded-lg p-4 mb-4 relative">
                            <TextInput label="Nombre del contacto*" v-model="contact.name" type="text" :error="form.errors[`contacts.${index}.name`]" />
                            <TextInput label="Cargo" v-model="contact.charge" type="text" :error="form.errors[`contacts.${index}.charge`]" />
                            <TextInput label="Teléfono*" v-model="contact.phone" type="text" :error="form.errors[`contacts.${index}.phone`]" />
                            <TextInput label="Email*" v-model="contact.email" type="email" :error="form.errors[`contacts.${index}.email`]" />
                            
                            <!-- Botón para eliminar contacto -->
                            <button @click="removeContact(index)" type="button" class="absolute top-2 right-2 text-gray-400 hover:text-red-500 transition-colors">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                        <InputError :message="form.errors.contacts" />

                        <!-- Botón de envío -->
                        <div class="flex justify-end mt-8 col-span-full">
                            <SecondaryButton :loading="form.processing">
                                Guardar Cliente
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
import TextInput from "@/Components/TextInput.vue";
import Back from "@/Components/MyComponents/Back.vue";
import { ElMessage } from 'element-plus';
import { useForm } from "@inertiajs/vue3";

export default {
    data() {
        const form = useForm({
            name: null,
            rfc: null,
            address: null,
            post_code: null,
            status: 'Prospecto',
            parent_branch_id: null,
            account_manager_id: null,
            meet_way: null,
            contacts: [], // Array para los contactos dinámicos
        });

        return {
            form,
        };
    },
    components: {
        AppLayout,
        SecondaryButton,
        InputError,
        TextInput,
        Back,
    },
    props: {
        users: Array,
        branches: Array,
    },
    mounted() {
        // Añadir un contacto por defecto al cargar la página
        this.addContact();
    },
    methods: {
        store() {
            this.form.post(route("branches.store"), {
                onSuccess: () => {
                    ElMessage.success('Cliente registrado correctamente');
                },
                onError: () => {
                    this.$refs.formContainer.scrollIntoView({ behavior: 'smooth' });
                    ElMessage.error('Por favor, revisa los errores en el formulario.');
                }
            });
        },
        addContact() {
            this.form.contacts.push({
                name: null,
                charge: null,
                phone: null,
                email: null,
            });
        },
        removeContact(index) {
            this.form.contacts.splice(index, 1);
        }
    }
};
</script>
