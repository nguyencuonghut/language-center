<template>
  <Card class="kpi-card hover:shadow-lg transition-shadow duration-200">
    <template #content>
      <div class="flex items-center justify-between">
        <div class="flex-1">
          <div class="flex items-center gap-3">
            <!-- Icon -->
            <div
              class="flex items-center justify-center w-12 h-12 rounded-lg"
              :class="iconBgClass"
            >
              <i
                :class="[icon, iconColorClass]"
                class="text-xl"
              ></i>
            </div>

            <!-- Content -->
            <div class="flex-1">
              <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ formattedValue }}
              </h3>
              <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                {{ displayText }}
              </p>
            </div>
          </div>

          <!-- Growth indicator -->
          <div
            v-if="growth !== undefined && growth !== null"
            class="flex items-center gap-1 mt-3"
          >
            <i
              :class="growthIcon"
              class="text-sm"
            ></i>
            <span
              class="text-sm font-medium"
              :class="growthTextClass"
            >
              {{ formattedGrowth }}
            </span>
            <span class="text-xs text-gray-500 dark:text-gray-400">
              so với kỳ trước
            </span>
          </div>
        </div>
      </div>
    </template>
  </Card>
</template>

<script setup>
import { computed } from 'vue'
import Card from 'primevue/card'

const props = defineProps({
  label: {
    type: String,
    required: false
  },
  title: {
    type: String,
    required: false
  },
  value: {
    type: [Number, String],
    required: true
  },
  icon: {
    type: String,
    required: true
  },
  color: {
    type: String,
    default: 'blue',
    validator: (value) => ['blue', 'green', 'red', 'yellow', 'purple', 'indigo', 'orange'].includes(value)
  },
  growth: {
    type: Number,
    default: null
  },
  format: {
    type: String,
    default: 'number',
    validator: (value) => ['number', 'currency', 'percentage'].includes(value)
  },
  loading: {
    type: Boolean,
    default: false
  }
})

// Computed for display text - prioritize title over label  
const displayText = computed(() => props.title || props.label || 'N/A')

const colorClasses = {
  blue: {
    iconBg: 'bg-blue-100 dark:bg-blue-900/30',
    iconColor: 'text-blue-600 dark:text-blue-400'
  },
  green: {
    iconBg: 'bg-green-100 dark:bg-green-900/30',
    iconColor: 'text-green-600 dark:text-green-400'
  },
  red: {
    iconBg: 'bg-red-100 dark:bg-red-900/30',
    iconColor: 'text-red-600 dark:text-red-400'
  },
  yellow: {
    iconBg: 'bg-yellow-100 dark:bg-yellow-900/30',
    iconColor: 'text-yellow-600 dark:text-yellow-400'
  },
  purple: {
    iconBg: 'bg-purple-100 dark:bg-purple-900/30',
    iconColor: 'text-purple-600 dark:text-purple-400'
  },
  indigo: {
    iconBg: 'bg-indigo-100 dark:bg-indigo-900/30',
    iconColor: 'text-indigo-600 dark:text-indigo-400'
  },
  orange: {
    iconBg: 'bg-orange-100 dark:bg-orange-900/30',
    iconColor: 'text-orange-600 dark:text-orange-400'
  }
}

const iconBgClass = computed(() => colorClasses[props.color]?.iconBg || colorClasses.blue.iconBg)
const iconColorClass = computed(() => colorClasses[props.color]?.iconColor || colorClasses.blue.iconColor)

const formattedValue = computed(() => {
  if (props.loading) return '...'

  const value = Number(props.value) || 0

  switch (props.format) {
    case 'currency':
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        minimumFractionDigits: 0
      }).format(value)

    case 'percentage':
      return `${value}%`

    case 'number':
    default:
      return new Intl.NumberFormat('vi-VN').format(value)
  }
})

const growthIcon = computed(() => {
  const growth = Number(props.growth)
  if (isNaN(growth) || growth === null || growth === undefined) return 'pi pi-minus text-gray-500'
  if (growth > 0) return 'pi pi-arrow-up text-green-500'
  if (growth < 0) return 'pi pi-arrow-down text-red-500'
  return 'pi pi-minus text-gray-500'
})

const growthTextClass = computed(() => {
  const growth = Number(props.growth)
  if (isNaN(growth) || growth === null || growth === undefined) return 'text-gray-600 dark:text-gray-400'
  if (growth > 0) return 'text-green-600 dark:text-green-400'
  if (growth < 0) return 'text-red-600 dark:text-red-400'
  return 'text-gray-600 dark:text-gray-400'
})

const formattedGrowth = computed(() => {
  const growth = Number(props.growth)
  if (growth === null || growth === undefined || isNaN(growth)) return ''

  const abs = Math.abs(growth)
  return props.format === 'percentage' ? `${abs}%` : `${abs}%`
})
</script>

<style scoped>
.kpi-card {
  @apply cursor-default;
}

.kpi-card:hover {
  @apply transform scale-[1.02];
}
</style>
