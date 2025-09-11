<template>
    <!-- sideNav -->
    <div class="h-screen hidden md:block p-3 bg-white dark:bg-zinc-900">
        <div class="bg-gray-100 dark:bg-zinc-800 h-full rounded-2xl flex flex-col p-2 relative mx-1">
            <div class="">
                <figure class="size-16 mb-3 mx-auto relative p-2">
                    <img class="w-full h-full object-contain rounded-full" src="/images/isoLogoEmblems.png"
                        alt="Logo de la Empresa" />

                    <div v-if="loading" class="absolute inset-0 rounded-full animate-spin
                                border-2 border-blue-500 border-t-red-500">
                    </div>
                </figure>
            </div>
            <nav class="flex flex-col h-full">
                <div @click="showModal = true" v-if="$page.props.auth.user?.employee_properties"
                    class="cursor-pointer hover:underline mb-4 p-2 items-center font-semibold text-xs text-[#0355B5] flex flex-col text-center">
                    <span>Horas / semana</span>
                    <span>{{ '$page.props.week_time.formatted' }} / {{
                        ' $page.props.auth.user?.employee_properties?.hours_per_week_formatted' ?? '0 h' }}</span>
                </div>

                <div class="space-y-1 mx-auto">
                    <template v-for="(menu, index) in topMenus" :key="'top-' + index">
                        <SideNavLink class="relative" v-if="menu.show" :href="menu.route" :active="menu.active"
                            :dropdown="menu.dropdown" :label="menu.label">
                            <template #trigger>
                                <div v-if="menu.active" class="absolute left-0 h-7 w-1 rounded-r-md bg-blue-700 dark:bg-blue-300
                                    dark:shadow-[0_0_8px_5px_rgba(50,50,255,0.9)] 
                                    before:content-[''] before:absolute before:inset-0 
                                    before:rounded-r-md before:blur-md 
                                    before:bg-secondary before:opacity-50 
                                    ">
                                </div>
                                <span v-html="menu.icon"></span>
                                <div v-if="menu.notifications" class="absolute top-2 right-2">
                                    <span class="relative flex h-2.5 w-2.5">
                                        <span
                                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-primary"></span>
                                    </span>
                                </div>
                            </template>
                            <template #content>
                                <div
                                    class="px-3 py-2 text-sm font-semibold bg-[#EAEAEA] dark:bg-zinc-800 text-gray-900 dark:text-white">
                                    {{ menu.label }}</div>
                                <div class="border-t border-gray-300 dark:border-zinc-500"></div>
                                <div class="p-1 space-y-1 bg-[#f2f2f2] dark:bg-zinc-800">
                                    <div v-for="option in menu.options" :key="option.label">
                                        <template v-if="option.show">
                                            <DropdownNavLink v-if="option.route" :href="route(option.route)"
                                                :active="option.active" :notifications="option.notifications">
                                                {{ option.label }}
                                            </DropdownNavLink>
                                            <!-- Handle actions that are not routes, like opening a modal -->
                                            <div v-else-if="option.action" @click="option.action"
                                                class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-800 transition duration-150 ease-in-out cursor-pointer rounded-md">
                                                {{ option.label }}
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </SideNavLink>
                    </template>
                </div>

                <div class="mt-auto space-y-2 pt-4 mx-auto">
                    <template v-for="(menu, index) in bottomMenus" :key="'bottom-' + index">
                        <SideNavLink v-if="menu.show" :href="menu.route" :active="menu.active" :dropdown="menu.dropdown"
                            :label="menu.label">
                            <template #trigger>
                                <span v-html="menu.icon"></span>
                                <i v-if="menu.notifications"
                                    class="fa-solid fa-circle fa-flip text-primary text-[10px] absolute top-1 right-1"></i>
                            </template>
                            <template #content>
                                <div class="px-3 py-2 text-sm font-semibold text-gray-900 dark:text-white">{{ menu.label
                                    }}</div>
                                <div class="border-t border-gray-200 dark:border-zinc-700"></div>
                                <div class="p-1 space-y-1">
                                    <div v-for="option in menu.options" :key="option.label">
                                        <template v-if="option.show">
                                            <DropdownNavLink v-if="option.route" :href="route(option.route)"
                                                :active="option.active" :notifications="option.notifications">
                                                {{ option.label }}
                                            </DropdownNavLink>
                                            <div v-else-if="option.action" @click="option.action"
                                                class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-800 transition duration-150 ease-in-out cursor-pointer rounded-md">
                                                {{ option.label }}
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </SideNavLink>
                    </template>

                    <!-- Avatar de usuario -->
                    <div
                        class="text-center w-full flex justify-center mb-4 border-t-2 border-gray-300 dark:border-zinc-700 pt-4">
                        <button v-if="$page.props.jetstream.managesProfilePhotos"
                            @click="showProfileCard = !showProfileCard"
                            class="flex items-center space-x-2 text-sm border-2 rounded-full focus:outline-none transition"
                            :class="{ 'border-primary': showProfileCard || route().current('profile.*'), 'border-transparent': !showProfileCard && !route().current('profile.*') }">
                            <div class="flex items-center p-1">
                                <img class="size-14 rounded-full object-cover"
                                    :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                            </div>
                        </button>
                        <ProfileCard v-if="showProfileCard" @close="showProfileCard = false"
                            class="bottom-0 left-[calc(100%+0.3rem)]" />
                    </div>
                </div>
            </nav>
        </div>
    </div>

    <!-- Payroll Modal -->
    <DialogModal :show="showModal" @close="showModal = false">
        <template #title>
            Nómina semanal
            <div class="w-1/2 mt-5 mx-10">
                <el-select v-model="payrollId" filterable :reserve-keyword="false" placeholder="Buscar nómina">
                    <el-option v-for="item in payrolls" :key="item.id" :label="'Nómina semana: ' + item.week"
                        :value="item.id" />
                </el-select>
            </div>
        </template>
        <template #content>
            <!-- <PayrollTemplate :user="$page.props.auth.user" :payrollId="payrollId" dontShowDetails /> -->
        </template>
        <template #footer>
            <CancelButton @click="showModal = false">Cerrar</CancelButton>
        </template>
    </DialogModal>

    <!-- Carpet Calculator Modal -->
    <DialogModal :show="showCarpetCalculatorModal" @close="closeCarpetCalculator">
        <template #title>
            <div class="flex items-center space-x-3">
                <span class="bg-blue-100 dark:bg-blue-900 rounded-full p-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="size-6 text-blue-600 dark:text-blue-300">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                    </svg>
                </span>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Cotizador de Alfombras</h2>
            </div>
        </template>
        <template #content>
            <div class="space-y-6 p-4">
                <div
                    class="bg-gray-50 dark:bg-zinc-800 p-3 rounded-lg border border-gray-200 dark:border-zinc-700 flex justify-between items-center">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            El precio se calcula con base en:
                            <strong class="text-gray-900 dark:text-white">{{ formatCurrency(basePriceConfig.price)
                                }}</strong>
                            por una alfombra de
                            <strong class="text-gray-900 dark:text-white">{{ basePriceConfig.length }}cm x {{
                                basePriceConfig.width }}cm</strong>.
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Precio por cm²: {{ formatCurrency(carpetPricePerCm2) }}
                        </p>
                    </div>
                    <button @click="resetBaseConfig" title="Reiniciar configuración"
                        class="text-gray-400 hover:text-red-500 dark:hover:text-red-400 transition-colors duration-200 p-2 rounded-full hover:bg-red-100 dark:hover:bg-red-900/50">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="carpet-length"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Largo (cm)</label>
                        <input type="number" id="carpet-length" v-model="carpetQuote.length" placeholder="e.g., 200"
                            class="w-full px-3 py-2 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="carpet-width"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ancho (cm)</label>
                        <input type="number" id="carpet-width" v-model="carpetQuote.width" placeholder="e.g., 150"
                            class="w-full px-3 py-2 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div>
                    <label for="carpet-discount"
                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descuento (%) <span
                            class="text-gray-500">(Opcional)</span></label>
                    <input type="number" id="carpet-discount" v-model="carpetQuote.discount" placeholder="e.g., 10"
                        min="0" max="100"
                        class="w-full px-3 py-2 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div v-if="totalCarpetPrice > 0" class="mt-6 text-center bg-blue-50 dark:bg-blue-900/50 p-4 rounded-lg">
                    <p class="text-lg font-medium text-gray-600 dark:text-gray-300">Precio Total Estimado</p>
                    <p class="text-4xl font-extrabold text-blue-600 dark:text-blue-300 tracking-tight">{{
                        formatCurrency(totalCarpetPrice) }}</p>
                    <p v-if="carpetQuote.discount > 0" class="text-sm text-green-600 dark:text-green-400 mt-1">
                        Con {{ carpetQuote.discount }}% de descuento aplicado.
                    </p>
                </div>
            </div>
        </template>
        <template #footer>
            <CancelButton @click="closeCarpetCalculator">Cerrar</CancelButton>
        </template>
    </DialogModal>

    <!-- Base Price Config Modal -->
    <DialogModal :show="showBasePriceModal" @close="showBasePriceModal = false">
        <template #title>
            <div class="flex items-center space-x-3">
                <span class="bg-orange-100 dark:bg-orange-900 rounded-full p-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="size-6 text-orange-600 dark:text-orange-300">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.108 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.108 1.204l.527.738c.32.447.27.96-.12 1.45l-.773.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.78.93l-.15.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.149-.894c-.07-.424-.384-.764-.78-.93-.398-.164-.855-.142-1.205.108l-.737.527a1.125 1.125 0 0 1-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.527-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.93l.15-.894Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                </span>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Configuración Inicial</h2>
            </div>
        </template>
        <template #content>
            <div class="p-4 space-y-4">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Es la primera vez que usas el cotizador. Por favor, define los valores base que se usarán para los
                    cálculos. Esta información se guardará en tu navegador.
                </p>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Precio Base
                        (MXN)</label>
                    <input type="number" v-model="basePriceConfig.price" placeholder="7500"
                        class="w-full px-3 py-2 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Largo Base
                            (cm)</label>
                        <input type="number" v-model="basePriceConfig.length" placeholder="120"
                            class="w-full px-3 py-2 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ancho Base
                            (cm)</label>
                        <input type="number" v-model="basePriceConfig.width" placeholder="60"
                            class="w-full px-3 py-2 bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-600 rounded-md shadow-sm focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                    </div>
                </div>
            </div>
        </template>
        <template #footer>
            <CancelButton @click="showBasePriceModal = false">Cancelar</CancelButton>
            <button @click="saveBasePriceConfig"
                class="ml-2 inline-flex items-center justify-center px-4 py-2 bg-orange-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-orange-500 active:bg-orange-700 focus:outline-none focus:border-orange-700 focus:ring focus:ring-orange-200 disabled:opacity-25 transition">
                Guardar y Continuar
            </button>
        </template>
    </DialogModal>
</template>

<script>

import SideNavLink from "@/Components/MyComponents/SideNavLink.vue";
import DropdownNavLink from "@/Components/MyComponents/DropdownNavLink.vue";
import CancelButton from "@/Components/MyComponents/CancelButton.vue";
import ProfileCard from './ProfileCard.vue';
// import PayrollTemplate from "@/Components/MyComponents/payrollTemplate.vue";
import DialogModal from "@/Components/DialogModal.vue";

export default {
    data() {
        return {
            showCarpetCalculatorModal: false,
            showBasePriceModal: false,
            basePriceConfig: {
                price: 7500,
                width: 60,
                length: 120,
            },
            carpetQuote: {
                width: null,
                length: null,
                discount: 0,
            },
            menus: [
                {
                    label: 'Inicio',
                    icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-[19px]"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>',
                    route: route('dashboard'),
                    active: route().current('dashboard'),
                    notifications: false,
                    options: [],
                    dropdown: false,
                    show: true
                },
                {
                    label: 'Catálogo de productos',
                    icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-[19px]"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>',
                    route: route('catalog-products.index'),
                    active: route().current('catalog-products.*'),
                    notifications: false,
                    options: [],
                    dropdown: false,
                    show: this.$page.props.auth.user.permissions.includes('Ver catalogo de productos')
                },
                {
                    label: 'CRM',
                    icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-[19px]"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605" /></svg>',
                    active:
                        route().current('crm.*')
                        || route().current('quotes.*')
                        || route().current('branches.*')
                        || route().current('sales.*')
                        || route().current('sales-analysis.*'),
                    options: [
                        {
                            label: 'Análisis de ventas',
                            route: 'sales-analysis.index',
                            active: route().current('sales-analysis.*'),
                            show: this.$page.props.auth.user.permissions.includes('Ver analisis de ventas'),
                        },
                        {
                            label: 'Clientes',
                            route: 'branches.index',
                            active: route().current('branches.*'),
                            show: this.$page.props.auth.user.permissions.includes('Ver clientes'),
                        },
                        {
                            label: 'Cotizaciones',
                            route: 'quotes.index',
                            active: route().current('quotes.*'),
                            show: this.$page.props.auth.user.permissions.includes('Ver cotizaciones'),
                            // notifications: this.$page.props.auth.user?.notifications?.some(notification => {
                            //     return notification.data.module === 'quote';
                            // }),
                        },
                        {
                            label: 'Órdenes de venta / stock',
                            route: 'sales.index',
                            show: this.$page.props.auth.user.permissions.includes('Ver ordenes de venta'),
                        },
                    ],
                    dropdown: true,
                    show:
                        this.$page.props.auth.user.permissions.includes('Ver clientes')
                        || this.$page.props.auth.user.permissions.includes('Ver cotizaciones')
                        || this.$page.props.auth.user.permissions.includes('Ver ordenes de venta')
                        || this.$page.props.auth.user.permissions.includes('Ver analisis de ventas')
                },
                {
                    label: 'Compras',
                    icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-[19px]"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg>',
                    active:
                        route().current('suppliers.*')
                        || route().current('purchases.*'),
                    options: [
                        {
                            label: 'Proveedores',
                            route: 'suppliers.index',
                            active: route().current('suppliers.*'),
                            show: this.$page.props.auth.user.permissions.includes('Ver proveedores'),
                            notifications: false,
                        },
                        {
                            label: 'Órdenes de compra',
                            route: 'purchases.index',
                            active: route().current('purchases.*'),
                            show: this.$page.props.auth.user.permissions.includes('Ver ordenes de compra'),
                        },

                    ],
                    dropdown: true,
                    show: this.$page.props.auth.user.permissions.includes('Ver proveedores')
                        || this.$page.props.auth.user.permissions.includes('Ver ordenes de compra')
                },
                {
                    label: 'Logistica',
                    icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-[19px]"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" /></svg>',
                    active: route().current('shipments.*'),
                    // || route().current('boxes.*') 
                    // || route().current('shipping-rates.*'),
                    // notifications: this.$page.props.auth.user?.notifications?.some(notification => {
                    //     return notification.data.module === 'shipments';
                    // }),
                    options: [
                        {
                            label: 'Envíos',
                            route: 'shipments.index',
                            show: this.$page.props.auth.user.permissions.includes('Ver envios'),
                            active: route().current('shipments.*'),
                            // notifications: this.$page.props.auth.user?.notifications?.some(notification => {
                            //     return notification.data.module === 'suppler';
                            // }),
                            // notifications: false,
                        },
                        // {
                        //     label: 'Administrador de cajas',
                        //     route: 'boxes.index',
                        //     show: this.$page.props.auth.user.permissions.includes('Ver logistica'),
                        //     // notifications: this.$page.props.auth.user?.notifications?.some(notification => {
                        //     //     return notification.data.module === 'suppler';
                        //     // }),
                        //     // notifications: false,
                        // },
                        // {
                        //     label: 'Tarifas',
                        //     route: 'shipping-rates.index',
                        //     show: this.$page.props.auth.user.permissions.includes('Ver logistica'),
                        //     // notifications: this.$page.props.auth.user?.notifications?.some(notification => {
                        //     //     return notification.data.module === 'suppler';
                        //     // }),
                        //     // notifications: false,
                        // },
                    ],
                    dropdown: true,
                    show: this.$page.props.auth.user.permissions.includes('Ver envios')
                },
                {
                    label: 'Recursos Humanos',
                    icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-[19px]"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>',
                    active:
                        route().current('users.*')
                        || route().current('role-permissions.*')
                        || route().current('bonuses.*')
                        || route().current('holidays.*')
                        || route().current('discounts.*')
                        || route().current('payrolls.*'),
                    // notifications: this.$page.props.auth.user?.notifications?.some(notification => {
                    //     return ['payroll', 'admin-additional-time', 'user'].includes(notification.data.module);
                    // }),
                    options: [
                        {
                            label: 'Nóminas',
                            route: 'payrolls.index',
                            active: route().current('payrolls.*'),
                            show: this.$page.props.auth.user.permissions?.includes('Ver nominas'),
                            notifications: false,
                        },
                        {
                            label: 'Solicitudes de tiempo adicional',
                            route: 'overtime-requests.index',
                            active: route().current('overtime-requests.*'),
                            show: this.$page.props.auth.user.permissions?.includes('Ver solicitudes de tiempo adicional'),
                            notifications: false,
                        },
                        {
                            label: 'Personal',
                            route: 'users.index',
                            active: route().current('users.*'),
                            show: this.$page.props.auth.user.permissions?.includes('Ver personal'),
                            // notifications: this.$page.props.auth.user?.notifications?.some(notification => {
                            //     return notification.data.module === 'user';
                            // }),
                        },
                        {
                            label: 'Roles y permisos',
                            route: 'role-permissions.index',
                            active: route().current('role-permissions.*'),
                            show: this.$page.props.auth.user.permissions?.includes('Ver roles y permisos'),
                            notifications: false,
                        },
                        {
                            label: 'Bonos',
                            route: 'bonuses.index',
                            active: route().current('bonuses.*'),
                            show: this.$page.props.auth.user.permissions?.includes('Ver bonos'),
                            notifications: false,
                        },
                        {
                            label: 'Descuentos',
                            route: 'discounts.index',
                            active: route().current('discounts.*'),
                            show: this.$page.props.auth.user.permissions?.includes('Ver descuentos')
                        },
                        {
                            label: 'Dias festivos',
                            route: 'holidays.index',
                            active: route().current('holidays.*'),
                            show: this.$page.props.auth.user.permissions.includes('Ver dias festivos'),
                            notifications: false,
                        },
                    ],
                    dropdown: true,
                    show:
                        this.$page.props.auth.user.permissions.includes('Ver roles y permisos')
                        || this.$page.props.auth.user.permissions.includes('Ver bonos')
                        || this.$page.props.auth.user.permissions.includes('Ver dias festivos')
                },
                {
                    label: 'Diseño',
                    icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-[19px]"><path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" /></svg>',
                    active: route().current('design-orders.*') 
                    || route().current('design-authorizations.*'),
                    // notifications: this.$page.props.auth.user?.notifications?.some(notification => {
                    //     return ['design', 'design-authorization'].includes(notification.data.module);
                    // }),
                    options: [
                        {
                            label: 'Órdenes de diseño',
                            route: 'design-orders.index',
                            active: route().current('design-orders.*'),
                            show: true,
                            // notifications: this.$page.props.auth.user?.notifications?.some(notification => {
                            //     return notification.data.module === 'design';
                            // }),
                        },
                        {
                            label: 'Formatos de autorización de diseño',
                            route: 'design-authorizations.index',
                            active: route().current('design-authorizations.*'),
                            show: true,
                            // notifications: this.$page.props.auth.user?.notifications?.some(notification => {
                            //     return notification.data.module === 'design-authorization';
                            // }),
                        },
                    ],
                    dropdown: true,
                    show: true
                },
                {
                    label: 'Producción',
                    icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-[19px]"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" /></svg>',
                    route: route('productions.index'),
                    active: route().current('productions.*'),
                    // notifications: this.$page.props.auth.user?.notifications?.some(notification => {
                    //     return notification.data.module === 'production';
                    // }),
                    show: true
                },
                {
                    label: 'Más',
                    icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>',
                    active:
                        route().current('machines.*') ||
                        route().current('production-costs.*') ||
                        route().current('manuals.*') ||
                        route().current('audits.*'),
                    options: [
                        {
                            label: 'Tutoriales y manuales',
                            route: 'manuals.index',
                            active: route().current('manuals.*'),
                            show: this.$page.props.auth.user.permissions.includes('Ver tutoriales y manuales'),
                            // notifications: this.$page.props.auth.user?.notifications?.some(notification => {
                            //     return notification.data.module === 'manual';
                            // }),
                        },
                        {
                            label: 'Máquinaria',
                            route: 'machines.index',
                            active: route().current('machines.*'),
                            show: this.$page.props.auth.user.permissions.includes('Ver maquinas'),
                            // notifications: this.$page.props.auth.user?.notifications?.some(notification => {
                            //     return notification.data.module === 'machine';
                            // }),
                        },
                        {
                            label: 'Procesos de producción',
                            route: 'production-costs.index',
                            active: route().current('production-costs.*'),
                            show: this.$page.props.auth.user.permissions.includes('Ver costos de produccion'),
                            notifications: false,
                        },
                        {
                            label: 'Historial de acciones',
                            route: 'audits.index',
                            active: route().current('audits.*'),
                            show: this.$page.props.auth.user.permissions?.includes('Ver historial de acciones')
                        },
                        {
                            label: 'Cotizador de alfombras',
                            action: this.openCarpetCalculator,
                            show: true,
                        },
                    ],
                    dropdown: true,
                    show: this.$page.props.auth.user.permissions.includes('Ver maquinas')
                        || this.$page.props.auth.user.permissions.includes('Ver historial de acciones')
                        || this.$page.props.auth.user.permissions.includes('Ver costos de produccion')
                },
            ],
            showModal: false,
            payrollId: null,
            payrolls: null,
            showProfileCard: false,
        }
    },
    props: {
        loading: {
            type: Boolean,
            default: false,
        }
    },
    components: {
        DropdownNavLink,
        CancelButton,
        ProfileCard,
        SideNavLink,
        DialogModal,
    },
    computed: {
        // Separa los menús que irán en la parte inferior
        bottomMenus() {
            const bottomLabels = ['Configuración'];
            return this.menus.filter(menu => bottomLabels.includes(menu.label));
        },
        // Filtra los menús que no están en la parte inferior
        topMenus() {
            const bottomLabels = ['Configuración'];
            return this.menus.filter(menu => !bottomLabels.includes(menu.label));
        },
        carpetPricePerCm2() {
            if (this.basePriceConfig.width > 0 && this.basePriceConfig.length > 0) {
                const baseArea = this.basePriceConfig.width * this.basePriceConfig.length;
                return this.basePriceConfig.price / baseArea;
            }
            return 0;
        },
        totalCarpetPrice() {
            if (this.carpetQuote.width > 0 && this.carpetQuote.length > 0 && this.carpetPricePerCm2 > 0) {
                const quoteArea = this.carpetQuote.width * this.carpetQuote.length;
                let price = quoteArea * this.carpetPricePerCm2;
                if (this.carpetQuote.discount > 0 && this.carpetQuote.discount <= 100) {
                    const discountAmount = price * (this.carpetQuote.discount / 100);
                    price -= discountAmount;
                }
                return price;
            }
            return 0;
        },
    },
    methods: {
        formatCurrency(value) {
            if (typeof value !== 'number') {
                return '$0.00';
            }
            return value.toLocaleString('es-MX', {
                style: 'currency',
                currency: 'MXN'
            });
        },
        openCarpetCalculator() {
            const storedConfig = localStorage.getItem('carpetBaseConfig');
            if (storedConfig) {
                this.basePriceConfig = JSON.parse(storedConfig);
                this.showCarpetCalculatorModal = true;
            } else {
                this.basePriceConfig = { price: 7500, width: 60, length: 120 };
                this.showBasePriceModal = true;
            }
        },
        saveBasePriceConfig() {
            if (this.basePriceConfig.price > 0 && this.basePriceConfig.width > 0 && this.basePriceConfig.length > 0) {
                localStorage.setItem('carpetBaseConfig', JSON.stringify(this.basePriceConfig));
                this.showBasePriceModal = false;
                this.showCarpetCalculatorModal = true;
            } else {
                // Here you can add a more sophisticated feedback, like a toast message.
                console.error("Please provide valid base configuration values.");
            }
        },
        closeCarpetCalculator() {
            this.showCarpetCalculatorModal = false;
            this.carpetQuote = {
                width: null,
                length: null,
                discount: 0,
            };
        },
        resetBaseConfig() {
            localStorage.removeItem('carpetBaseConfig');
            this.showCarpetCalculatorModal = false;
            this.openCarpetCalculator();
        },
    },
    mounted() {
    }
}
</script>
