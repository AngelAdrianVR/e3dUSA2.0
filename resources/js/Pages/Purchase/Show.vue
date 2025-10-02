<template>
    <AppLayout :title="`Detalles de Compra OC-${purchase.id.toString().padStart(4, '0')}`">
        <!-- Productos a favor -->
        <SupplierFavoredProducts :supplier-id="purchase.supplier.id" />

        <!-- === ENCABEZADO === -->
        <header class="flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0 pb-4 mb-1">
            <div>
                <div class="flex space-x-2 items-center">
                    <h1 class="dark:text-white font-bold text-2xl my-2">
                        <span class="text-gray-500 dark:text-gray-400">Orden de Compra:</span> OC-{{ purchase.id.toString().padStart(4, '0') }}
                    </h1>
                </div>
            </div>
            
            <div class="flex items-center space-x-2 dark:text-white">
                <el-tooltip v-if="$page.props.auth.user.permissions.includes('Autorizar ordenes de compra') && !purchase.authorized_at && purchase.status !== 'Cancelada'" content="Autorizar Compra" placement="top">
                    <button @click="updateStatus('Autorizada')" class="size-9 flex items-center justify-center rounded-lg bg-green-300 hover:bg-green-400 dark:bg-green-800 dark:hover:bg-green-700 transition-colors">
                        <i class="fa-solid fa-check-double"></i>
                    </button>
                </el-tooltip>
                 <el-tooltip v-if="purchase.status === 'Autorizada'" content="Marcar como Compra Realizada" placement="top">
                    <button @click="updateStatus('Compra realizada')" class="size-9 flex items-center justify-center rounded-lg bg-blue-300 hover:bg-blue-400 dark:bg-blue-800 dark:hover:bg-blue-700 transition-colors">
                        <i class="fa-solid fa-bag-shopping"></i>
                    </button>
                </el-tooltip>
                <el-tooltip v-if="purchase.status === 'Compra realizada'" content="Marcar como Recibida" placement="top">
                    <button @click="updateStatus('Compra recibida')" class="size-9 flex items-center justify-center rounded-lg bg-green-300 hover:bg-green-400 dark:bg-green-800 dark:hover:bg-green-700 transition-colors">
                        <i class="fa-solid fa-square-check"></i>
                    </button>
                </el-tooltip>

                <el-tooltip content="Imprimir Orden" placement="top">
                    <a :href="route('purchases.print', purchase.id)" target="_blank" class="size-9 flex items-center justify-center rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors">
                        <i class="fa-solid fa-print"></i>
                    </a>
                </el-tooltip>

                <el-tooltip :content="purchase.authorized_at ? 'No puedes editarla una vez realizada la compra' : 'Editar Orden'" placement="top">
                    <Link :href="(purchase.status === 'Cancelada' || purchase.status === 'Compra realizada') ? '' : route('purchases.edit', purchase.id)">
                        <button :disabled="!!purchase.status === 'Cancelada' || purchase.status === 'Compra realizada'" 
                            class="size-9 flex items-center justify-center rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors disabled:cursor-not-allowed disabled:opacity-50">
                            <i class="fa-solid fa-pencil text-sm"></i>
                        </button>
                    </Link>
                </el-tooltip>
                
                <Dropdown align="right" width="48">
                    <template #trigger>
                        <button class="h-9 px-3 rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 flex items-center justify-center text-sm transition-colors">
                            Acciones <i class="fa-solid fa-chevron-down text-[10px] ml-2"></i>
                        </button>
                    </template>
                    <template #content>
                        <DropdownLink :href="route('purchases.create')">
                            <i class="fa-solid fa-plus w-4 mr-2"></i> Nueva Orden
                        </DropdownLink>
                        <div class="border-t border-gray-200 dark:border-gray-600" />
                        <DropdownLink v-if="purchase.status !== 'Compra recibida' && purchase.status !== 'Cancelada'" @click="showCancelConfirmModal = true" as="button" class="text-amber-600 hover:!bg-amber-50 dark:hover:!bg-amber-900/50">
                            <i class="fa-solid fa-ban w-4 mr-2"></i> Cancelar Orden
                        </DropdownLink>
                        <DropdownLink @click="showConfirmModal = true" as="button" class="text-red-500 hover:!bg-red-50 dark:hover:!bg-red-900/50">
                            <i class="fa-regular fa-trash-can w-4 mr-2"></i> Eliminar Orden
                        </DropdownLink>
                    </template>
                </Dropdown>

                <Link :href="route('purchases.index')"
                    class="flex-shrink-0 size-9 focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800 flex items-center justify-center rounded-full bg-white dark:bg-slate-800/80 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-red-600 transition-all duration-200">
                    <i class="fa-solid fa-xmark"></i>
                </Link>
            </div>
        </header>

        <!-- === CONTENIDO PRINCIPAL === -->
        <main class="grid grid-cols-1 lg:grid-cols-3 gap-5 mt-3 dark:text-white">
            <!-- COLUMNA IZQUIERDA -->
            <div class="lg:col-span-1 space-y-4">
                <!-- === AVISO DE CANCELACIÓN === -->
                <div v-if="purchase.status === 'Cancelada'" class="bg-amber-100 border-l-4 border-amber-500 text-amber-700 p-4 rounded-lg shadow-md dark:bg-amber-800/50 dark:text-amber-300 dark:border-amber-600">
                    <div class="flex">
                        <div class="py-1"><i class="fa-solid fa-circle-exclamation text-2xl mr-4"></i></div>
                        <div>
                            <p class="font-bold">Orden de Compra Cancelada</p>
                            <p class="text-sm">Esta orden de compra fue cancelada y no se puede procesar más.</p>
                        </div>
                    </div>
                </div>

                <!-- === STEPPER DE ESTADO === -->
                <Stepper v-if="purchase.status !== 'Cancelada'" :currentStatus="purchase.status" :steps="purchaseSteps" :treatCurrentAsCompleted="true" />
                
                <!-- Card de Información de la Compra -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Detalles de la Compra</h3>
                    <ul class="space-y-3 text-sm">
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Creado por:</span>
                            <span>{{ purchase.user?.name ?? 'N/A' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Fecha de Emisión:</span>
                            <span>{{ formatDateTime(purchase.emited_at) }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Fecha estimada de Entrega:</span>
                            <span>{{ formatDate(purchase.expected_delivery_date) }}</span>
                        </li>
                         <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Autorizado por:</span>
                            <span>{{ purchase.authorizer?.name ?? 'N/A' }}</span>
                        </li>
                         <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Fecha de Autorización:</span>
                            <span>{{ formatDateTime(purchase.authorized_at) }}</span>
                        </li>
                         <li v-if="purchase.recieved_at" class="flex justify-between">
                            <span class="font-semibold text-green-600 dark:text-green-400">Recibida el:</span>
                            <span class="text-green-500">{{ formatDateTime(purchase.recieved_at) }}</span>
                        </li>
                         <li class="flex justify-between text-base font-bold">
                            <span class="text-gray-700 dark:text-gray-300">Monto Total:</span>
                            <span>{{ formatCurrency(purchase.total) }} {{ purchase.currency }}</span>
                        </li>
                    </ul>
                </div>
                
                <!-- Card de Información del Proveedor -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Información del Proveedor</h3>
                    <ul class="space-y-3 text-sm">
                        <li class="flex justify-between items-center">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Nombre:</span>
                            <span @click="$inertia.visit(route('suppliers.show', purchase.supplier.id))" class="text-blue-500 hover:underline cursor-pointer">{{ purchase.supplier.name }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Contacto:</span>
                            <el-tooltip placement="right">
                                <template #content>
                                    <div class="space-y-2 text-sm">
                                        <p v-if="getPrimaryDetail(purchase.contact, 'Correo')" class="flex items-center gap-2">
                                        <i class="fa-solid fa-envelope text-blue-400"></i>
                                        <span>{{ getPrimaryDetail(purchase.contact, 'Correo') }}</span>
                                        </p>
                                        <p v-if="getPrimaryDetail(purchase.contact, 'Teléfono')" class="flex items-center gap-2">
                                        <i class="fa-solid fa-phone text-green-400"></i>
                                        <span>{{ getPrimaryDetail(purchase.contact, 'Teléfono') }}</span>
                                        </p>
                                    </div>
                                </template>
                            <span class="text-blue-500 cursor-default">{{ purchase.contact?.name ?? 'N/A' }}</span>
                            </el-tooltip>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Dirección:</span>
                            <span>{{ purchase.supplier.address }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Teléfono:</span>
                            <span>{{ purchase.supplier.phone }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Card de Información de pago -->
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Información de Cuenta bancaria</h3>
                    <ul class="space-y-3 text-sm">
                        <h2 class="dark:text-white text-lg">{{ purchase.bank_account?.name }}</h2>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Nombrede propietario:</span>
                            <span>{{ purchase.bank_account?.account_holder ?? 'N/A' }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">N° Cuenta:</span>
                            <span>{{ purchase.bank_account?.account_number }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Clabe:</span>
                            <span>{{ purchase.bank_account?.clabe }}</span>
                        </li>
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Moneda:</span>
                            <span>{{ purchase.bank_account?.currency }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- COLUMNA DERECHA: PRODUCTOS -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-4 min-h-[300px] max-h-[50vh] overflow-y-auto">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Productos de la Orden</h3>
                    <div v-if="purchase.items?.length" class="space-y-3 max-h-[65vh] overflow-auto custom-scroll p-1">
                        <ProductPurchaseCard v-for="item in purchase.items" :key="item.id" :purchase-item="item" />
                    </div>
                    <div v-else class="text-center text-gray-500 dark:text-gray-400 py-10">
                        <i class="fa-solid fa-boxes-stacked text-3xl mb-3"></i>
                        <p>Esta orden no tiene productos registrados.</p>
                    </div>
                </div>

                <!-- === SECCIÓN DE EVALUACIÓN REALIZADA === -->
                <div v-if="purchase.rating" class="mt-5 bg-white dark:bg-slate-800/50 shadow-lg rounded-lg p-5">
                    <h3 class="text-lg font-semibold border-b dark:border-gray-600 pb-3 mb-4">Evaluación de Proveedor Realizada</h3>
                    <ul class="space-y-2 text-sm mb-4">
                        <li v-for="(question, index) in purchase.rating.questions" :key="index">
                            <p class="font-semibold">{{ getQuestionText(index) }}</p>
                            <p v-if="index == 5" class="pl-3 text-amber-600 dark:text-amber-400">Notas: {{ question.answer }}</p>
                            <p v-else class="pl-3 text-gray-600 dark:text-gray-400">R: {{ question.answer }} ({{ question.points }} pts)</p>
                        </li>
                    </ul>
                    
                    <div v-if="evidenceMedia.length">
                        <h4 class="font-semibold mb-2 text-sm">Archivos de Testigo:</h4>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <a v-for="media in evidenceMedia" :key="media.id" :href="media.original_url" target="_blank" class="hover:opacity-75 transition-opacity">
                                <img :src="media.original_url" :alt="media.name" class="rounded-lg object-cover aspect-square border dark:border-gray-700">
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </main>

        <!-- Modal de Confirmación para Eliminar -->
        <ConfirmationModal :show="showConfirmModal" @close="showConfirmModal = false">
            <template #title>
                Eliminar Orden de Compra OC-{{ purchase.id.toString().padStart(4, '0') }}
            </template>
            <template #content>
                ¿Estás seguro de que deseas eliminar permanentemente esta Orden? Esta acción no se puede deshacer.
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="showConfirmModal = false">Cancelar</CancelButton>
                    <SecondaryButton @click="deleteItem" class="!bg-red-600 hover:!bg-red-700">Eliminar</SecondaryButton>
                </div>
            </template>
        </ConfirmationModal>

        <!-- Modal de Confirmación para Cancelar -->
        <ConfirmationModal :show="showCancelConfirmModal" @close="showCancelConfirmModal = false">
            <template #title>
                Cancelar Orden de Compra OC-{{ purchase.id.toString().padStart(4, '0') }}
            </template>
            <template #content>
                ¿Estás seguro de que deseas cancelar esta Orden de Compra? Esta acción no se puede deshacer. Los siguientes pasos del proceso ya no estarán disponibles.
            </template>
            <template #footer>
                <div class="flex space-x-2">
                    <CancelButton @click="showCancelConfirmModal = false">Cerrar</CancelButton>
                    <SecondaryButton @click="cancelPurchase" class="!bg-amber-600 hover:!bg-amber-700">Sí, Cancelar Orden</SecondaryButton>
                </div>
            </template>
        </ConfirmationModal>

        <!-- Modal de Encuesta de Satisfacción -->
        <DialogModal :show="showRatingModal" @close="showRatingModal = false" maxWidth="3xl">
            <template #title>
                <h1 class="font-bold">Recepción de Compra y Evaluación de Proveedor</h1>
            </template>
            <template #content>
                <p class="text-gray-600 dark:text-gray-400">Por favor, completa la siguiente evaluación del proveedor.</p>
                <form @submit.prevent="submitRating" class="mt-5 space-y-4">
                    <section>
                        <h2 class="text-gray-800 dark:text-gray-300 font-semibold mb-2">1. ¿Cumplió con el tiempo de entrega?</h2>
                        <div class="flex flex-col space-y-2 ml-3">
                            <label class="flex items-center">
                                <input type="radio" value="Si" v-model="ratingForm.q1" class="text-blue-500 focus:ring-blue-500" />
                                <span class="ml-2">Sí</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" :value="`No, hubo un retraso de ${ratingForm.q1_days} día(s)`" v-model="ratingForm.q1" class="text-blue-500 focus:ring-blue-500" />
                                <span class="ml-2">No, hubo un retraso de</span>
                                <input @input="handleChangeLateDays" @click="handleInputClick" v-model="ratingForm.q1_days" type="number" min="1" class="w-16 ml-2 rounded-md border-gray-300 dark:bg-slate-700 text-sm" />
                                <span class="ml-2">día(s)</span>
                            </label>
                        </div>
                    </section>
                     <section>
                        <h2 class="text-gray-800 dark:text-gray-300 font-semibold mb-2">2. ¿Las características solicitadas fueron cubiertas?</h2>
                        <div class="flex flex-col space-y-2 ml-3">
                            <label class="flex items-center">
                                <input type="radio" value="Sí, cumplió con todo" v-model="ratingForm.q2" class="text-blue-500 focus:ring-blue-500" />
                                <span class="ml-2">Sí, cumplió con todo</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" value="No, no se cumplieron las especificaciones" v-model="ratingForm.q2" class="text-blue-500 focus:ring-blue-500" />
                                <span class="ml-2">No, no se cumplieron las especificaciones</span>
                            </label>
                        </div>
                    </section>
                    <section>
                        <h2 class="text-gray-800 dark:text-gray-300 font-semibold mb-2">3. ¿Cumplió con el apoyo técnico ofrecido?</h2>
                        <div class="ml-3 space-y-2">
                            <el-select @change="handleQ31Change" :teleported="false" v-model="ratingForm.q3_1" placeholder="Tipo de soporte brindado" class="w-full">
                                <el-option v-for="item in a3_1" :key="item" :label="item" :value="item" />
                            </el-select>
                            <el-select v-if="ratingForm.q3_1 !== 'No se requirió soporte'" :teleported="false" v-model="ratingForm.q3_2" placeholder="Tiempo de atención" class="w-full">
                                <el-option v-for="item in a3_2" :key="item" :label="item" :value="item" />
                            </el-select>
                        </div>
                    </section>
                     <section>
                        <h2 class="text-gray-800 dark:text-gray-300 font-semibold mb-2">4. Ante alguna urgencia, ¿se ofreció apoyo en la entrega?</h2>
                        <div class="ml-3">
                            <el-select v-model="ratingForm.q4" :teleported="false" placeholder="Seleccionar" class="w-full">
                                <el-option v-for="item in a4" :key="item" :label="item" :value="item" />
                            </el-select>
                        </div>
                    </section>
                    <section>
                        <h2 class="text-gray-800 dark:text-gray-300 font-semibold mb-2">5. ¿Hubo alguna incidencia?</h2>
                        <div class="ml-3">
                            <el-select v-model="ratingForm.q5" :teleported="false" placeholder="Avisos de rechazo" class="w-full">
                                <el-option v-for="item in a5" :key="item" :label="item" :value="item" />
                            </el-select>
                        </div>
                    </section>
                    <!-- --- SECCIÓN PARA SUBIR ARCHIVOS --- -->
                    <section>
                        <h2 class="text-gray-800 dark:text-gray-300 font-semibold mb-2">6. Archivos de Testigo (Opcional)</h2>
                        <div class="ml-3">
                           <input @change="handleFileChange" type="file" multiple accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-slate-700 dark:file:text-blue-300 dark:hover:file:bg-slate-600"/>
                           <p class="text-xs text-gray-500 mt-1">Puedes subir hasta 4 imágenes (jpeg, png, jpg, gif). Máximo 2MB por imagen.</p>
                           <!-- Previsualización de imágenes -->
                           <div v-if="filePreviews.length" class="mt-4 grid grid-cols-4 gap-3">
                                <div v-for="(preview, index) in filePreviews" :key="index" class="relative">
                                    <img :src="preview" class="rounded-lg object-cover aspect-square" />
                                    <button @click="removeFile(index)" type="button" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full size-6 flex items-center justify-center text-xs">&times;</button>
                                </div>
                           </div>
                        </div>

                        <TextInput
                            v-model="ratingForm.notes"
                            label="Notas o comentarios"
                            :isTextarea="true"
                            placeholder="Escribe alguna nota o comentarios referente a la compra recibida"
                        />
                    </section>
                </form>
            </template>
            <template #footer>
                 <div class="flex space-x-2">
                    <CancelButton @click="showRatingModal = false">Cancelar</CancelButton>
                    <SecondaryButton @click="submitRating" :disabled="ratingForm.processing">Guardar Evaluación</SecondaryButton>
                </div>
            </template>
        </DialogModal>
    </AppLayout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout.vue";
import ConfirmationModal from "@/Components/ConfirmationModal.vue";
import DialogModal from "@/Components/DialogModal.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import SupplierFavoredProducts from "@/Components/MyComponents/SupplierFavoredProducts.vue";
import ProductPurchaseCard from "@/Components/MyComponents/ProductPurchaseCard.vue";
import TextInput from "@/Components/TextInput.vue";
import Stepper from "@/Components/MyComponents/Stepper.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import { ElMessage } from 'element-plus';
import { Link, router, useForm } from "@inertiajs/vue3";
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    name: 'PurchaseShow',
    components: {
        Link,
        Stepper,
        Dropdown,
        AppLayout,
        TextInput,
        DialogModal,
        DropdownLink,
        CancelButton,
        SecondaryButton,
        ConfirmationModal,
        ProductPurchaseCard,
        SupplierFavoredProducts,
    },
    props: {
        purchase: Object,
    },
    data() {
        return {
            showConfirmModal: false,
            showCancelConfirmModal: false,
            showRatingModal: false,
            filePreviews: [],
            purchaseSteps: ['Autorizada', 'Compra realizada', 'Compra recibida'],
            ratingForm: useForm({
                q1: 'Si',
                q1_days: 1,
                q2: 'Sí, cumplió con todo',
                q3_1: 'No se requirió soporte',
                q3_2: null,
                q4: 'No se presentó ninguna urgencia',
                q5: '0 avisos de rechazo',
                notes: null,
                evidence_files: [],
            }),
             // Opciones para las preguntas de la encuesta
            a3_1: ['No se requirió soporte', 'Soporte por defecto del producto', 'Soporte de acuerdo al desarrollo del material'],
            a3_2: ['Atención inmediata', 'Atención tardía (2 o más días para atender)', 'No brindó soporte'],
            a4: ['No se presentó ninguna urgencia', '1 día de atraso', '2 a 3 días de atraso', '4 a 5 días de atraso', 'Más de 6 días de atraso'],
            a5: ['0 avisos de rechazo', '1 aviso de rechazo', '2 o más avisos de rechazo'],
        };
    },
    computed: {
        evidenceMedia() {
            return this.purchase.media?.filter(media => media.collection_name === 'evidence_files') || [];
        }
    },
    methods: {
        getQuestionText(index) {
            const questions = [
                "1. ¿Cumplió con el tiempo de entrega?",
                "2. ¿Las características solicitadas fueron cubiertas?",
                "3. ¿Cumplió con el apoyo técnico ofrecido?",
                "4. Ante alguna urgencia, ¿se ofreció apoyo en la entrega?",
                "5. ¿Hubo alguna incidencia?"
            ];
            return questions[index] || '';
        },
        handleFileChange(event) {
            const files = Array.from(event.target.files);
            if (this.ratingForm.evidence_files.length + files.length > 4) {
                ElMessage.error('No puedes subir más de 4 imágenes en total.');
                return;
            }
            this.ratingForm.evidence_files.push(...files);
            this.updateFilePreviews();
        },
        removeFile(index) {
            this.ratingForm.evidence_files.splice(index, 1);
            this.updateFilePreviews();
        },
        updateFilePreviews() {
            this.filePreviews = this.ratingForm.evidence_files.map(file => URL.createObjectURL(file));
        },
        getPrimaryDetail(contact, type) {
            if (!contact?.details) return null;
            const primary = contact.details.find(d => d.type === type && d.is_primary);
            if (primary) return primary.value;
            const first = contact.details.find(d => d.type === type);
            return first ? first.value : null;
        },
        formatDateTime(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return format(date, "d 'de' MMM, yyyy 'a las' HH:mm", { locale: es });
        },
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return format(date, "d 'de' MMM, yyyy", { locale: es });
        },
        formatCurrency(value) {
            if (value === null || value === undefined) return '$0.00';
            return Number(value).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
        },
        updateStatus(newStatus) {
            if (newStatus === 'Compra recibida') {
                this.showRatingModal = true;
                return;
            }

            let routeName = (newStatus === 'Autorizada') ? 'purchases.authorize' : 'purchases.update-status';
            let payload = (newStatus === 'Autorizada') ? {} : { status: newStatus };

            router.put(route(routeName, this.purchase.id), payload, {
                preserveScroll: true,
                onSuccess: () => ElMessage.success('Estatus de la compra actualizado'),
                onError: (errors) => console.error("Error al actualizar:", errors),
            });
        },
        cancelPurchase() {
            router.put(route('purchases.update-status', this.purchase.id), {
                status: 'Cancelada'
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    ElMessage.success('La orden de compra ha sido cancelada.');
                    this.showCancelConfirmModal = false;
                },
                onError: (errors) => {
                    ElMessage.error('Ocurrió un error al cancelar la orden.');
                    console.error("Error al cancelar:", errors);
                }
            });
        },
        submitRating() {
             const transformedData = this.ratingForm.data();
            transformedData.status = 'Compra recibida';

            router.post(route('purchases.update-status', this.purchase.id), {
                _method: 'PUT',
                status: 'Compra recibida',
                rating: {
                    q1: this.ratingForm.q1,
                    q1_days: this.ratingForm.q1_days,
                    q2: this.ratingForm.q2,
                    q3_1: this.ratingForm.q3_1,
                    q3_2: this.ratingForm.q3_2,
                    q4: this.ratingForm.q4,
                    q5: this.ratingForm.q5,
                    notes: this.ratingForm.notes,
                },
                evidence_files: this.ratingForm.evidence_files,
            }, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    ElMessage.success('¡Compra recibida y evaluación guardada!');
                    this.showRatingModal = false;
                    this.ratingForm.reset();
                    this.filePreviews = [];
                },
                onError: (errors) => {
                    ElMessage.error('Ocurrió un error al guardar la evaluación.');
                    console.error("Error al guardar evaluación:", errors);
                }
            });
        },
        deleteItem() {
            router.delete(route('purchases.destroy', this.purchase.id), {
                onSuccess: () => {
                    ElMessage.success('Compra eliminada con éxito.');
                },
                onError: () => {
                    ElMessage.error('Ocurrió un error al eliminar la compra.');
                },
                onFinish: () => {
                    this.showConfirmModal = false;
                }
            });
        },
        handleQ31Change() {
            if (this.ratingForm.q3_1 === 'No se requirió soporte') {
                this.ratingForm.q3_2 = null;
            }
        },
        handleChangeLateDays() {
            if (this.ratingForm.q1 !== 'Si') {
                this.ratingForm.q1 = `No, hubo un retraso de ${this.ratingForm.q1_days} día(s)`;
            }
        },
        handleInputClick() {
             this.ratingForm.q1 = `No, hubo un retraso de ${this.ratingForm.q1_days} día(s)`;
        }
    }
};
</script>
