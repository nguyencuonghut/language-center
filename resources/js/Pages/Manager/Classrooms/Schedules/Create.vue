<script setup>
import { reactive } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { createManagerClassScheduleService } from '@/service/ManagerClassScheduleService'
import { usePageToast } from '@/composables/usePageToast'

// PrimeVue
import Card from 'primevue/card'
import Button from 'primevue/button'
import Select from 'primevue/select'

defineOptions({ layout: AppLayout })

const props = defineProps({
  classroom: Object, // {id, code, name}
  errors: Object
})

const { showSuccess, showError } = usePageToast()
const scheduleService = createManagerClassScheduleService({ showSuccess, showError })

const form = reactive({
  weekday: null,
  start_time: '',
  end_time: ''
})

const weekdays = [
  { label: 'Chủ nhật', value: 0 },
  { label: 'Thứ hai',  value: 1 },
  { label: 'Thứ ba',   value: 2 },
  { label: 'Thứ tư',   value: 3 },
  { label: 'Thứ năm',  value: 4 },
  { label: 'Thứ sáu',  value: 5 },
  { label: 'Thứ bảy',  value: 6 },
];

function submit() {
  // Client guard nhỏ: end_time phải sau start_time
  if (form.start_time && form.end_time && form.end_time <= form.start_time) {
    showError('Giờ không hợp lệ', 'Giờ kết thúc phải sau giờ bắt đầu')
    return
  }

  scheduleService.create(
    props.classroom.id,
    {
      class_id: props.classroom.id,
      weekday: form.weekday,
      start_time: form.start_time,
      end_time: form.end_time,
    },
    {
      onSuccess: () => {
        toast.showSuccess('Thành công', 'Đã tạo lịch học mới')
      },
      onError: (errors) => {
        form.setError(errors)
      }
    }
  )
}
</script>

<template>
  <Head :title="`Thêm lịch - ${classroom?.name ?? ''}`" />

  <!-- Breadcrumb + back -->
  <div class="mb-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
    <nav class="text-sm text-slate-600 dark:text-slate-300 flex items-center gap-2">
      <Link :href="route('manager.classrooms.index')" class="hover:text-emerald-600 dark:hover:text-emerald-300">
        Lớp học
      </Link>
      <span>/</span>
      <Link :href="route('manager.classrooms.schedules.index', { classroom: classroom.id })" class="hover:text-emerald-600 dark:hover:text-emerald-300">
        {{ classroom.name }} / Lịch học
      </Link>
      <span>/</span>
      <span class="font-medium text-slate-900 dark:text-slate-100">Thêm lịch</span>
    </nav>

    <Link
      :href="route('manager.classrooms.schedules.index', { classroom: classroom.id })"
      class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 self-start md:self-auto"
    >
      ← Danh sách lịch
    </Link>
  </div>

  <Card>
    <template #title>
      Thêm lịch cho lớp: <span class="font-semibold">{{ classroom.name }}</span>
    </template>

    <template #content>
      <!-- 3 inputs cùng một hàng ở ≥ md -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Weekday -->
        <div>
          <label class="block text-sm mb-1">Thứ trong tuần</label>
          <Select v-model="form.weekday" :options="weekdays" optionLabel="label" optionValue="value" class="w-full" />
          <small v-if="errors?.weekday" class="text-red-500">{{ errors.weekday }}</small>
        </div>

        <!-- Start time -->
        <div>
          <label class="block text-sm mb-1">Giờ bắt đầu</label>
          <input
            v-model="form.start_time"
            type="time"
            class="w-full px-3 py-2 border rounded bg-transparent"
          />
          <small v-if="errors?.start_time" class="block text-red-500">{{ errors.start_time }}</small>
        </div>

        <!-- End time -->
        <div>
          <label class="block text-sm mb-1">Giờ kết thúc</label>
          <input
            v-model="form.end_time"
            type="time"
            class="w-full px-3 py-2 border rounded bg-transparent"
          />
          <small v-if="errors?.end_time" class="block text-red-500">{{ errors.end_time }}</small>
        </div>
      </div>

      <!-- Actions -->
      <div class="mt-5 flex gap-2">
        <Button label="Tạo lịch" icon="pi pi-check" severity="success" @click="submit" />
        <Link
          :href="route('manager.classrooms.schedules.index', { classroom: classroom.id })"
          class="px-3 py-2 rounded-lg border hover:bg-slate-50 dark:hover:bg-slate-700/30"
        >
          Huỷ
        </Link>
      </div>
    </template>
  </Card>
</template>
