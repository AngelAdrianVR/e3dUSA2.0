<template>
    <AppLayout :title="`Nueva Requisición (${type})`">
        <div class="flex items-start gap-3">
            <Back route="requisitions.index" />
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Nueva Requisición de Material
                    <el-tag class="ml-2" :type="type === 'Importación' ? 'primary' : 'success'">{{ type }}</el-tag>
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    El folio se asignará automáticamente (REQ-####). Si la requisición es rechazada se re-trabaja sobre el mismo folio.
                </p>
            </div>
        </div>

        <div class="py-7">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <RequisitionForm
                        :initial-type="type"
                        :initial-origin="origin"
                        :allowed-origins="origins"
                        :branches="branches"
                        :products="products"
                        :priorities="priorities"
                        :sales-frequencies="salesFrequencies"
                        :processing="processing"
                        :server-errors="$page.props.errors"
                        submit-label="Levantar requisición"
                        @submit="submit" />
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
        type: {
            type: String,
            default: 'Local',
        },
        origin: {
            type: String,
            default: 'Vendedor',
        },
        origins: {
            type: Array,
            default: () => ['Vendedor', 'Almacén'],
        },
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

            router.post(route('requisitions.store'), data, {
                forceFormData: true,
                onError: () => {
                    ElMessage.error('Revisa los campos marcados en rojo.');
                },
                onFinish: () => {
                    this.processing = false;
                },
            });
        },
    },
};
</script>
