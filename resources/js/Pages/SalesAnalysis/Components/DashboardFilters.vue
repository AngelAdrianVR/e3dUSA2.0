<template>
  <!-- Filters -->
  <div class="mb-6 flex flex-wrap items-center gap-4">
    <div class="flex items-center space-x-2 bg-white dark:bg-gray-800 p-1.5 rounded-lg shadow-md w-full lg:w-[450px]">
      <button
        v-for="period in periods"
        :key="period.key"
        @click="$emit('changePeriod', period.key)"
        :disabled="isLoadingAny"
        :class="[
            'w-full text-center px-4 py-2 rounded-md text-sm font-medium transition-colors duration-200 focus:outline-none',
            activePeriod === period.key
            ? 'bg-blue-600 text-white shadow-md'
            : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700',
            isLoadingAny ? 'opacity-50 cursor-not-allowed' : ''
        ]">
        {{ period.label }}
      </button>
    </div>
    <div v-if="activePeriod === 'custom'">
        <el-date-picker
            :model-value="customDateRange"
            @update:modelValue="$emit('update:customDateRange', $event)"
            @change="$emit('handleDateChange', $event)"
            type="daterange"
            range-separator="a"
            start-placeholder="Fecha de inicio"
            end-placeholder="Fecha de fin"
            :disabled="isLoadingAny"
            format="DD/MM/YYYY"
            value-format="YYYY-MM-DD"
        />
    </div>
  </div>
</template>

<script>
import { ElDatePicker } from 'element-plus';
import 'element-plus/dist/index.css';
import 'element-plus/theme-chalk/dark/css-vars.css'

export default {
  name: 'DashboardFilters',
  components: { ElDatePicker },
  props: {
    periods: Array,
    activePeriod: String,
    customDateRange: Array,
    isLoadingAny: Boolean,
  },
  emits: ['changePeriod', 'update:customDateRange', 'handleDateChange'],
}
</script>
