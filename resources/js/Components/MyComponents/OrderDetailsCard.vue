<template>
    <div class="bg-white dark:bg-slate-800/50 shadow-lg rounded-lg overflow-hidden">
        <!-- Encabezado Colapsable -->
        <div @click="isOpen = !isOpen" class="flex justify-between items-center p-4 cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
            <div class="flex items-center gap-3">
                <i class="fa-solid fa-file-invoice text-blue-500"></i>
                <h3 class="text-lg font-semibold">Detalles de la Órden</h3>
            </div>
            <i :class="isOpen ? 'fa-chevron-up' : 'fa-chevron-down'" class="fa-solid text-gray-400 transition-transform"></i>
        </div>

        <!-- Contenido -->
        <el-collapse-transition>
            <div v-show="isOpen" class="p-5 border-t dark:border-gray-600">
                <ul class="space-y-3 text-sm">
                    <!-- Campos para Venta -->
                    <template v-if="sale.type === 'venta'">
                        <li class="flex justify-between items-center">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Cliente:</span>
                            <!-- Tooltip Moderno -->
                            <el-tooltip placement="right" effect="light" raw-content>
                                <template #content>
                                    <div class="w-72 bg-white/90 dark:bg-slate-800/90 backdrop-blur-md rounded-xl shadow-xl p-4 text-sm">
                                        <div class="flex justify-between items-center border-b pb-2 mb-3">
                                            <h4 class="font-bold text-lg text-primary dark:text-sky-400">{{ sale.branch?.name }}</h4>
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-600 dark:bg-sky-900 dark:text-sky-300">
                                                {{ sale.branch?.status ?? 'N/A' }}
                                            </span>
                                        </div>
                                        <div class="space-y-1 text-gray-700 dark:text-gray-300">
                                            <p><strong class="font-semibold">RFC:</strong> {{ sale.branch?.rfc ?? 'N/A' }}</p>
                                            <p><strong class="font-semibold">Dirección:</strong> {{ sale.branch?.address ?? 'N/A' }}</p>
                                            <p><strong class="font-semibold">C.P.:</strong> {{ sale.branch?.post_code ?? 'N/A' }}</p>
                                            <p><strong class="font-semibold">Medio de contacto:</strong> {{ sale.branch?.meet_way ?? 'N/A' }}</p>
                                            <p><strong class="font-semibold">Última compra:</strong> {{ formatRelative(sale.branch?.last_purchase_date) }}</p>
                                        </div>
                                        <div class="mt-4 pt-2 border-t flex justify-between items-center">
                                            <Link :href="route('branches.show', sale.branch?.id)">
                                                <SecondaryButton class="!py-1.5 !px-3 !text-xs flex items-center gap-1">
                                                    <i class="fa-solid fa-eye"></i> Ver Cliente
                                                </SecondaryButton>
                                            </Link>
                                            <span class="text-[10px] italic text-gray-400">Creado: {{ sale.branch?.created_at?.split('T')[0] }}</span>
                                        </div>
                                    </div>
                                </template>
                                <span class="text-blue-500 hover:underline cursor-default">
                                    {{ sale.branch?.name ?? 'N/A' }}
                                </span>
                            </el-tooltip>
                        </li>

                        <!-- Contacto -->
                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Contacto:</span>
                            <el-tooltip v-if="sale.contact" placement="right" effect="dark">
                                <template #content>
                                    <div class="space-y-2 text-sm">
                                        <p v-if="getPrimaryDetail(sale.contact, 'Correo')" class="flex items-center gap-2">
                                            <i class="fa-solid fa-envelope text-blue-400"></i>
                                            <span>{{ getPrimaryDetail(sale.contact, 'Correo') }}</span>
                                        </p>
                                        <p v-if="getPrimaryDetail(sale.contact, 'Teléfono')" class="flex items-center gap-2">
                                            <i class="fa-solid fa-phone text-green-400"></i>
                                            <span>{{ getPrimaryDetail(sale.contact, 'Teléfono') }}</span>
                                        </p>
                                    </div>
                                </template>
                                <span class="text-blue-500 font-medium hover:underline cursor-default transition-colors duration-200">
                                    {{ sale.contact?.name ?? 'N/A' }}
                                </span>
                            </el-tooltip>
                            <span v-else class="text-gray-400 italic">N/A</span>
                        </li>

                        <li class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">OV:</span>
                            <span @click="$inertia.visit(route('sales.show', sale.id))" class="text-blue-500 hover:underline cursor-pointer">
                                OV-{{ sale.id.toString().padStart(4, '0') ?? 'N/A' }}
                            </span>
                        </li>
                    </template>

                    <!-- Campos Comunes -->
                    <li class="flex justify-between">
                        <span class="font-semibold text-gray-600 dark:text-gray-400">Tipo:</span>
                        <span>{{ sale.type === 'venta' ? 'Venta' : 'Stock' }}</span>
                    </li>
                    <li class="flex justify-between">
                        <span class="font-semibold text-gray-600 dark:text-gray-400">OCE:</span>
                        <span>{{ sale.oce_name ?? 'No especificado' }}</span>
                    </li>
                    <li class="flex justify-between">
                        <span class="font-semibold text-gray-600 dark:text-gray-400">Creado por:</span>
                        <span>{{ sale.user?.name ?? 'N/A' }}</span>
                    </li>
                    <li class="flex justify-between">
                        <span class="font-semibold text-gray-600 dark:text-gray-400">Fecha de Creación:</span>
                        <span>{{ formatDate(sale.created_at) }}</span>
                    </li>
                    <li class="flex justify-between items-center">
                        <span class="font-semibold text-gray-600 dark:text-gray-400">Prioridad:</span>
                        <el-tag v-if="sale.is_high_priority" type="danger" size="small">Alta</el-tag>
                        <el-tag v-else type="info" size="small">Normal</el-tag>
                    </li>
                </ul>
            </div>
        </el-collapse-transition>
    </div>
</template>

<script>
import { Link } from "@inertiajs/vue3";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';

export default {
    name: 'OrderDetailsCard',
    components: { Link, SecondaryButton },
    props: {
        sale: { type: Object, required: true }
    },
    data() {
        return {
            isOpen: false // Cerrado por defecto
        }
    },
    methods: {
        getPrimaryDetail(contact, type) {
            if (!contact?.details) return 'No disponible';
            const detail = contact.details.find(d => d.type === type && d.is_primary);
            return detail ? detail.value : 'No disponible';
        },
        formatRelative(dateString) {
            if (!dateString) return "Sin registro";
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            if (diffMs < 0) return "En el futuro";
            const seconds = Math.floor(diffMs / 1000);
            const minutes = Math.floor(seconds / 60);
            const hours = Math.floor(minutes / 60);
            const days = Math.floor(hours / 24);
            const months = Math.floor(days / 30);
            const years = Math.floor(months / 12);
            if (seconds < 60) return `Hace ${seconds} segundos`;
            if (minutes < 60) return `Hace ${minutes} minutos`;
            if (hours < 24) return `Hace ${hours} horas`;
            if (days < 30) return `Hace ${days} días`;
            if (months < 12) return `Hace ${months} mes${months > 1 ? "es" : ""}`;
            return `Hace ${years} año${years > 1 ? "s" : ""}`;
        },
        formatDate(dateString) {
            if (!dateString) return '';
            let date;
            if (dateString.includes(' ')) {
                const [datePart] = dateString.split(' '); 
                const [year, month, day] = datePart.split('-');
                date = new Date(year, month - 1, day);
            } else {
                try { date = parseISO(dateString); } catch { return ''; }
            }
            if (isNaN(date)) return ''; 
            return format(date, "d 'de' MMMM, yyyy", { locale: es });
        }
    }
}
</script>