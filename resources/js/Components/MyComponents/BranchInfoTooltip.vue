<template>
    <el-tooltip v-if="branch" placement="right" effect="light" raw-content>
        <template #content>
            <div class="w-80 bg-white/90 dark:bg-slate-800/90 backdrop-blur-md rounded-xl shadow-xl p-4 text-sm">
                <!-- Header -->
                <div class="flex justify-between items-center border-b pb-2 mb-3">
                    <h4 class="font-bold text-lg text-primary dark:text-sky-400 truncate pr-2">
                        {{ branch.name }}
                    </h4>
                    <span class="px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-600 dark:bg-sky-900 dark:text-sky-300 flex-shrink-0">
                        {{ branch.status ?? 'N/A' }}
                    </span>
                </div>

                <!-- Datos principales -->
                <div class="space-y-1 text-gray-700 dark:text-gray-300 mb-3">
                    <p><strong class="font-semibold">RFC:</strong> {{ branch.rfc ?? 'N/A' }}</p>
                    <p><strong class="font-semibold">Dirección:</strong> {{ branch.address ?? 'N/A' }}</p>
                    <p><strong class="font-semibold">C.P.:</strong> {{ branch.post_code ?? 'N/A' }}</p>
                    <p><strong class="font-semibold">Contacto:</strong> {{ branch.meet_way ?? 'N/A' }}</p>
                    <p><strong class="font-semibold">Última compra:</strong> {{ formatRelative(branch.last_purchase_date) }}</p>
                </div>

                <!-- SECCIÓN: Consumo Estimado -->
                <div class="bg-gray-50 dark:bg-slate-900 rounded-lg p-3 mt-3 border border-gray-100 dark:border-gray-700">
                    <h5 class="text-xs font-bold text-gray-500 uppercase mb-2 flex items-center justify-between">
                        Consumo Estimado
                        <i v-if="loadingAnalytics" class="fa-solid fa-circle-notch fa-spin text-blue-500"></i>
                    </h5>
                    
                    <div v-if="!loadingAnalytics && analytics" class="space-y-1">
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-gray-600 dark:text-gray-400">Mensual (MXN):</span>
                            <span v-if="$page.props.auth.user.permissions.includes('Ver cantidades de dinero')" class="font-bold text-green-600 dark:text-green-400">
                                ${{ formatNumber(analytics.monthly_consumption) }}
                            </span>
                            <span v-else class="font-bold text-green-600 dark:text-green-400" title="Restringido">***</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-gray-600 dark:text-gray-400">Anual (MXN):</span>
                            <span v-if="$page.props.auth.user.permissions.includes('Ver cantidades de dinero')" class="font-bold text-blue-600 dark:text-blue-400">
                                ${{ formatNumber(analytics.annual_consumption) }}
                            </span>
                            <span v-else class="font-bold text-blue-600 dark:text-blue-400" title="Restringido">***</span>
                        </div>

                        <!-- NUEVO: Desglose de Productos -->
                        <div v-if="analytics.product_breakdown?.length || true" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                            <p class="text-[10px] font-bold text-gray-400 uppercase mb-2">Cantidades por Producto</p>
                            <div class="max-h-28 overflow-y-auto custom-scrollbar pr-1 space-y-1.5">
                                <div v-for="prod in analytics.product_breakdown" :key="prod.product_id" 
                                    class="flex items-center gap-2 bg-white dark:bg-slate-800 p-1.5 rounded border border-gray-100 dark:border-gray-700">
                                    
                                    <img v-if="prod.image_url" :src="formatImageUrl(prod.image_url)" 
                                        @click.stop="zoomedImage = formatImageUrl(prod.image_url)"
                                        class="w-8 h-8 rounded object-cover cursor-pointer hover:opacity-80 border dark:border-gray-600 transition-opacity flex-shrink-0"
                                        title="Ver imagen completa">
                                    <div v-else class="w-8 h-8 rounded bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-400 flex-shrink-0">
                                        <i class="fa-solid fa-image text-xs"></i>
                                    </div>
                                    
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate" :title="prod.name">{{ prod.name }}</p>
                                        <div class="flex justify-between items-center text-[10px] text-gray-500 mt-0.5">
                                            <span title="Consumo Mensual">Mensual: <b class="text-gray-700 dark:text-gray-300">{{ Math.round(prod.monthly_quantity) }}u</b></span>
                                            <span title="Consumo Anual">Anual: <b class="text-gray-700 dark:text-gray-300">{{ Math.round(prod.annual_quantity) }}u</b></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div v-else-if="!loadingAnalytics && !analytics" class="text-xs text-gray-400 text-center py-1">
                        Información no disponible
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-4 pt-2 border-t flex justify-between items-center">
                    <Link :href="route('branches.show', branch.id)">
                        <SecondaryButton class="!py-1.5 !px-3 !text-xs flex items-center gap-1">
                            <i class="fa-solid fa-eye"></i> Ver Cliente
                        </SecondaryButton>
                    </Link>
                    <span class="text-[11px] italic text-gray-400">Creado: {{ branch.created_at?.split('T')[0] }}</span>
                </div>
            </div>
        </template>

        <!-- Nombre clickable -->
        <span class="text-blue-500 hover:underline cursor-default">
            {{ branch.name ?? 'N/A' }}
        </span>
    </el-tooltip>
    <span v-else class="font-semibold text-gray-600 dark:text-gray-400">N/A</span>

    <!-- Visor de imagen a pantalla completa (Teleport evita que se corte dentro del tooltip) -->
    <Teleport to="body">
        <div v-if="zoomedImage" @click="zoomedImage = null" 
            class="fixed inset-0 z-[999999] bg-black/80 flex items-center justify-center p-4 cursor-zoom-out backdrop-blur-sm transition-all">
            <img :src="zoomedImage" class="max-w-full max-h-[90vh] rounded-lg shadow-2xl border-4 border-white/10" @click.stop>
            <button @click="zoomedImage = null" class="absolute top-6 right-6 text-white hover:text-gray-300 bg-black/50 rounded-full w-10 h-10 flex items-center justify-center transition-colors">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
    </Teleport>
</template>

<script>
import { Link } from '@inertiajs/vue3';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import axios from 'axios';

export default {
    name: 'BranchInfoTooltip',
    components: {
        Link,
        SecondaryButton,
    },
    props: {
        branch: {
            type: Object,
            required: false,
            default: null
        }
    },
    data() {
        return {
            analytics: null,
            loadingAnalytics: false,
            zoomedImage: null, // Controla qué imagen está ampliada
        };
    },
    methods: {
        async fetchAnalytics() {
            if (!this.branch || !this.branch.id) return;
            
            this.loadingAnalytics = true;
            try {
                // Hacemos uso de la nueva ruta generada para obtener consumo
                const response = await axios.get(route('branches.sales-analytics', this.branch.id));
                this.analytics = response.data;
            } catch (error) {
                console.error('Error obteniendo analíticas de la sucursal:', error);
            } finally {
                this.loadingAnalytics = false;
            }
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
        formatNumber(value) {
            if (value === null || value === undefined) return '0.00';
            const num = Number(value);
            if (isNaN(num)) return '0.00';
            return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
        },
        formatImageUrl(url) {
            if (!url) return null;
            return url.replace('http://localhost:8000', 'https://www.intranetemblems3d.dtw.com.mx');
        }
    },
    mounted() {
        // Cargar las analíticas de forma asíncrona en cuanto el componente carga
        this.fetchAnalytics();
    }
};
</script>