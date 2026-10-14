<template>
    <AppLayout :title="`Editar ${requisition.folio}`">
        <div class="flex items-start gap-3">
            <Back route="requisitions.index" />
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Editar Requisición <span class="font-mono">{{ requisition.folio }}</span>
            </h2>
        </div>

        <div v-if="requisition.status === 'Rechazada'" class="mt-4 max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="p-4 rounded-lg border border-red-300 bg-red-50 dark:bg-red-900/20 dark:border-red-800">
                <p class="font-semibold text-red-700 dark:text-red-300">
                    <i class="fa-solid fa-circle-xmark mr-2"></i> Requisición rechazada
                </p>
                <p class="text-sm text-red-600 dark:text-red-300 mt-1">
                    Motivo: {{ requisition.rejection_reason ?? 'Sin motivo registrado.' }}
                </p>
                <p class="text-xs text-red-500 dark:text-red-400 mt-1">
                    Al guardar los cambios la requisición se re-trabaja conservando el folio {{ requisition.folio }}.
                </p>
            </div>
        </div>

        <div class="py-7">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <RequisitionForm
                        :requisition="requisition"
                        :initial-type="requisition.type"
                        :initial-origin="requisition.origin"
                        :branches="branches"
                        :products="products"
                        :priorities="priorities"
                        :sales-frequencies="salesFrequencies"
                        :processing="processing"
                        :server-errors="$page.props.errors"
                        :show-type-selector="false"
                        :show-origin-selector="false"
                        submit-label="Guardar cambios"
                        @submit="submit"
                        @delete-existing-media="handleMediaDeleted" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import Back from "@/Components/MyComponents/Back.vue";
import RequisitionForm from "./Components/RequisitionForm.vue";
import { router } from "@inertiajs/vue3";
import { ElMessage } from "element-plus";

export default {
    components: {
        AppLayout,
        Back,
        RequisitionForm,
    },
    props: {
        requisition: Object,
        branches: {
            type: Array,
            default: () => [],
        },
        products: {
            type: Array,
            default: () => [],
        },
        priorities: {
            type: Array,
            default: () => [],
        },
        salesFrequencies: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            processing: false,
        };
    },
    methods: {
        submit(payload) {
            const { files, ...data } = payload;

            Object.entries(files).forEach(([collection, collectionFiles]) => {
                if (collectionFiles?.length) {
                    data[collection] = collectionFiles;
                }
            });

            Object.keys(data).forEach(key => {
                if (data[key] === undefined) delete data[key];
            });

            this.processing = true;

            // Los adjuntos enviados reemplazan a los existentes de esa colección.
            router.post(route('requisitions.update', this.requisition.id), {
                ...data,
                _method: 'put',
            }, {
                forceFormData: true,
                onError: () => {
                    ElMessage.error('Revisa los campos marcados en rojo.');
                },
                onFinish: () => {
                    this.processing = false;
                },
            });
        },
        handleMediaDeleted(collection, mediaId) {
            // FileView ya elimina el archivo en el servidor; solo se actualiza la vista.
            this.requisition.media = this.requisition.media.filter(file => file.id !== mediaId);
        },
    },
};
</script>
