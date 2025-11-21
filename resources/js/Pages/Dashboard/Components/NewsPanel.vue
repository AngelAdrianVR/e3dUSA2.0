<template>
    <div class="p-5 bg-white rounded-lg shadow-md dark:bg-gray-800 h-full">
        <h3 class="mb-4 text-base font-semibold text-gray-800 dark:text-white">Novedades</h3>
        <ul v-if="news && news.length > 0" class="space-y-4 max-h-96 overflow-y-auto">
            <li v-for="(item, index) in news" :key="index" class="flex items-center">
                <div class="flex-shrink-0 w-10 h-10 mr-3 rounded-full flex items-center justify-center" :class="iconBackground(item.type)">
                    <span class="text-lg font-bold text-white">{{ item.user_name.charAt(0) }}</span>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ item.user_name }} - {{ item.department }}</p>
                    <p class="text-sm font-semibold" :class="textColor(item.type)">
                        {{ item.secondary_string }} <span class="text-gray-400 dark:text-gray-500 font-normal">({{ item.date_string }})</span>
                    </p>
                </div>
            </li>
        </ul>
        <p v-else class="text-sm text-gray-500 dark:text-gray-400">No hay novedades por ahora.</p>
    </div>
</template>

<script>
export default {
    name: 'NewsPanel',
    props: {
        news: {
            type: Array,
            default: () => [],
        },
    },
    methods: {
        iconBackground(type) {
            const colors = {
                birthday: 'bg-purple-500',
                anniversary: 'bg-yellow-500',
                new_hire: 'bg-green-500',
            };
            return colors[type] || 'bg-gray-500';
        },
        textColor(type) {
             const colors = {
                birthday: 'text-purple-600 dark:text-purple-400',
                anniversary: 'text-yellow-600 dark:text-yellow-400',
                new_hire: 'text-green-600 dark:text-green-400',
            };
            return colors[type] || 'text-gray-500';
        }
    }
}
</script>
