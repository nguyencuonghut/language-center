<template>
  <div class="sticky top-0 z-10 bg-white/90 dark:bg-gray-900/90 backdrop-blur-sm border-b border-gray-200 dark:border-gray-700">
    <div class="p-3 md:p-4">
      <form @submit.prevent="handleApply" class="flex flex-col lg:flex-row lg:items-center gap-3 lg:gap-4">
        <!-- Left side: Date range -->
        <div class="flex-1 lg:max-w-md">
          <div class="flex items-center gap-2">
            <DatePicker
              v-model="localFilters.start_date"
              placeholder="dd/mm/yyyy"
              dateFormat="dd/mm/yy"
              show-icon
              class="flex-1"
              :class="{ 'p-invalid': errors.start_date }"
            />
            <span class="text-gray-400 dark:text-gray-500">-</span>
            <DatePicker
              v-model="localFilters.end_date"
              placeholder="dd/mm/yyyy"
              dateFormat="dd/mm/yy"
              show-icon
              class="flex-1"
              :class="{ 'p-invalid': errors.end_date }"
            />
          </div>
          <small
            v-if="errors.start_date || errors.end_date"
            class="text-red-500 dark:text-red-400 text-xs"
          >
            {{ errors.start_date || errors.end_date }}
          </small>
        </div>

        <!-- Right side: Additional filters and actions -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 lg:gap-4">
          <!-- Branch filter (Admin only) -->
          <MultiSelect
            v-if="showBranchFilter && filterOptions?.branches?.length"
            v-model="localFilters.branches"
            :options="filterOptions.branches"
            option-label="name"
            option-value="id"
            :placeholder="$t('Tất cả chi nhánh')"
            :max-selected-labels="2"
            class="w-full sm:w-48"
            display="chip"
            show-clear
          />

          <!-- Course filter -->
          <MultiSelect
            v-if="filterOptions?.courses?.length"
            v-model="localFilters.courses"
            :options="filterOptions.courses || []"
            option-label="name"
            option-value="id"
            :placeholder="$t('Tất cả khóa học')"
            :max-selected-labels="2"
            class="w-full sm:w-48"
            display="chip"
            show-clear
          />

          <!-- Teacher filter (if provided) -->
          <MultiSelect
            v-if="filterOptions.teachers"
            v-model="localFilters.teachers"
            :options="filterOptions.teachers"
            option-label="name"
            option-value="id"
            :placeholder="$t('Tất cả giáo viên')"
            :max-selected-labels="2"
            class="w-full sm:w-48"
            display="chip"
            show-clear
          />

          <!-- Action buttons -->
          <div class="flex items-center gap-2">
            <Button
              type="submit"
              :label="$t('Áp dụng')"
              icon="pi pi-filter"
              :loading="loading"
              size="small"
            />

            <Button
              type="button"
              :label="$t('Xóa lọc')"
              severity="secondary"
              text
              icon="pi pi-times"
              size="small"
              @click="handleReset"
            />

            <Button
              type="button"
              :label="$t('Xuất CSV')"
              severity="secondary"
              outlined
              icon="pi pi-download"
              size="small"
              @click="handleExport"
            />
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import DatePicker from 'primevue/datepicker'
import MultiSelect from 'primevue/multiselect'
import Button from 'primevue/button'

const props = defineProps({
  filters: {
    type: Object,
    required: true
  },
  filterOptions: {
    type: Object,
    default: () => ({ branches: [], courses: [] })
  },
  showBranchFilter: {
    type: Boolean,
    default: false
  },
  loading: {
    type: Boolean,
    default: false
  },
  errors: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['apply', 'reset', 'export'])

// Local reactive copy of filters
const localFilters = ref({
  start_date: null,
  end_date: null,
  branches: [],
  courses: [],
  teachers: []
})

// Flag to prevent overwriting local state during user interaction
const isUpdatingFromProps = ref(false)

// Initialize local filters from props
onMounted(() => {
  updateLocalFiltersFromProps(props.filters)
})

// Helper function to update local filters from props
const updateLocalFiltersFromProps = (newFilters) => {
  isUpdatingFromProps.value = true

  if (newFilters?.start_date) {
    localFilters.value.start_date = new Date(newFilters.start_date)
  } else {
    localFilters.value.start_date = null
  }

  if (newFilters?.end_date) {
    localFilters.value.end_date = new Date(newFilters.end_date)
  } else {
    localFilters.value.end_date = null
  }

  localFilters.value.branches = newFilters?.branches || []
  localFilters.value.courses = newFilters?.courses || []
  localFilters.value.teachers = newFilters?.teachers || []

  setTimeout(() => {
    isUpdatingFromProps.value = false
  }, 100)
}

// Watch for external filter changes
watch(() => props.filters, (newFilters) => {
  if (!isUpdatingFromProps.value) {
    updateLocalFiltersFromProps(newFilters)
  }
}, { deep: true })

const handleApply = () => {
  isUpdatingFromProps.value = true

  const filterData = {
    start_date: localFilters.value.start_date?.toISOString().split('T')[0] || null,
    end_date: localFilters.value.end_date?.toISOString().split('T')[0] || null,
    branches: localFilters.value.branches,
    courses: localFilters.value.courses,
    teachers: localFilters.value.teachers
  }

  emit('apply', filterData)

  // Keep local state intact after emitting
  setTimeout(() => {
    isUpdatingFromProps.value = false
  }, 200)
}

const handleReset = () => {
  localFilters.value = {
    start_date: null,
    end_date: null,
    branches: [],
    courses: [],
    teachers: []
  }
  emit('reset')
}

const handleExport = () => {
  emit('export')
}

// Utility function for localization (placeholder)
const $t = (key) => key
</script>

<style scoped>
/* Custom styles for mobile responsiveness */
@media (max-width: 640px) {
  .p-multiselect {
    min-width: 100%;
  }
}

/* DatePicker placeholder text in dark mode - try multiple selectors */
.dark :deep(.p-datepicker-input::placeholder) {
  color: rgb(156 163 175) !important;
}

.dark :deep(.p-inputtext::placeholder) {
  color: rgb(156 163 175) !important;
}

.dark :deep(input::placeholder) {
  color: rgb(156 163 175) !important;
}

.dark :deep(.p-datepicker-input) {
  color: white !important;
}

.dark :deep(.p-inputtext) {
  color: white !important;
}
</style>
