<script setup>
import { reactive } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { createClassScheduleService } from '@/service/ClassScheduleService'
import { usePageToast } from '@/composables/usePageToast'

// PrimeVue
import Card from 'primevue/card'
import Button from 'primevue/button'
import Select from 'primevue/select'

defineOptions({ layout: AppLayout })

const props = defineProps({
  classroom: Object, // {id, code, name, branch_id}
  schedule: Object,  // {id, class_id, weekday, start_time, end_time}
  errors: Object
})

const { showSuccess, showError } = usePageToast()
const scheduleService = createClassScheduleService({ showSuccess, showError })

const form = reactive({
  weekday: props.schedule?.weekday ?? null,
  start_time: props.schedule?.start_time ?? '', // có thể là "08:00" hoặc "08:00 AM" tuỳ trình duyệt
  end_time: props.schedule?.end_time ?? ''
})

const weekdays = [
  { label: 'Chủ nhật', value: 0 },
  { label: 'Thứ hai',  value: 1 },
  { label: 'Thứ ba',   value: 2 },
  { label: 'Thứ tư',   value: 3 },
  { label: 'Thứ năm',  value: 4 },
  { label: 'Thứ sáu',  value: 5 },
  { label: 'Thứ bảy',  value: 6 },
]

/** Chuẩn hoá chuỗi giờ về HH:mm (24h). Hỗ trợ "08:00 AM", "10:00 PM", "8:00", "08:00:00" */
function normalizeTime(val) {
  if (!val) return ''
  const s = String(val).trim()

  // 1) 12h có AM/PM
  const ampm = s.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i)
  if (ampm) {
    let h = parseInt(ampm[1], 10)
    const m = ampm[2]
    const isPM = ampm[3].toUpperCase() === 'PM'
    if (h === 12) h = isPM ? 12 : 0
    else if (isPM) h += 12
    return `${String(h).padStart(2, '0')}:${m}`
  }

  // 2) HH:mm[:ss]
  const hm = s.match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/)
  if (hm) {
    return `${hm[1].padStart(2, '0')}:${hm[2]}`
  }

  // Trường hợp khác – trả nguyên, để backend báo lỗi nếu không hợp lệ
  return s
}

function toMinutes(hhmm) {
  const [h, m] = normalizeTime(hhmm).split(':').map(Number)
  if (Number.isNaN(h) || Number.isNaN(m)) return NaN
  return h * 60 + m
}

function submit() {
  const start = normalizeTime(form.start_time)
  const end   = normalizeTime(form.end_time)

  // Client guard: end > start
  if (start && end) {
    const sMin = toMinutes(start)
    const eMin = toMinutes(end)
    if (!Number.isNaN(sMin) && !Number.isNaN(eMin) && eMin <= sMin) {
      showError('Giờ không hợp lệ', 'Giờ kết thúc phải sau giờ bắt đầu')
      return
    }
  }

  scheduleService.update(
    props.classroom.id,
    props.schedule.id,
    {
      class_id: props.classroom.id,
      weekday: form.weekday,
      start_time: start, // gửi dạng HH:mm
      end_time: end
    },
    {
      onError: (errors) => {
        form.setError(errors)
      }
    }
  )
}
</script>

<template>
  <Head :title="`Sửa lịch - ${classroom?.name ?? ''}`" />

  <div class="mb-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
    <nav class="text-sm text-slate-600 dark:text-slate-300 flex items-center gap-2">
      <Link :href="route('admin.classrooms.index')" class="hover:text-emerald-600 dark:hover:text-emerald-300">Lớp học</Link>
      <span>/</span>
      <Link :href="route('admin.classrooms.schedules.index', { classroom: classroom.id })" class="hover:text-emerald-600 dark:hover:text-emerald-300">
        {{ classroom.name }} / Lịch học
      </Link>
      <span>/</span>
      <span class="font-medium text-slate-900 dark:text-slate-100">Sửa lịch</span>
    </nav>

    <Link
      :href="route('admin.classrooms.schedules.index', { classroom: classroom.id })"
      class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 self-start md:self-auto"
    >
      ← Danh sách lịch
    </Link>
  </div>

  <Card>
    <template #title>
      Sửa lịch của lớp: <span class="font-semibold">{{ classroom.name }}</span>
    </template>

    <template #content>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm mb-1">Thứ trong tuần</label>
          <Select v-model="form.weekday" :options="[
            { label: 'Chủ nhật', value: 0 }, { label: 'Thứ hai', value: 1 }, { label: 'Thứ ba', value: 2 },
            { label: 'Thứ tư', value: 3 }, { label: 'Thứ năm', value: 4 }, { label: 'Thứ sáu', value: 5 }, { label: 'Thứ bảy', value: 6 },
          ]" optionLabel="label" optionValue="value" class="w-full" />
          <small v-if="errors?.weekday" class="text-red-500">{{ errors.weekday }}</small>
        </div>

        <div>
          <label class="block text-sm mb-1">Giờ bắt đầu</label>
          <input v-model="form.start_time" type="time" class="w-full px-3 py-2 border rounded bg-transparent" />
          <small v-if="errors?.start_time" class="block text-red-500">{{ errors.start_time }}</small>
        </div>

        <div>
          <label class="block text-sm mb-1">Giờ kết thúc</label>
          <input v-model="form.end_time" type="time" class="w-full px-3 py-2 border rounded bg-transparent" />
          <small v-if="errors?.end_time" class="block text-red-500">{{ errors.end_time }}</small>
        </div>
      </div>

      <div class="mt-5 flex gap-2 justify-end">
        <Button label="Cập nhật" icon="pi pi-check" severity="success" @click="submit" />
        <Link
          :href="route('admin.classrooms.schedules.index', { classroom: classroom.id })"
          class="px-3 py-2 rounded-lg border hover:bg-slate-50 dark:hover:bg-slate-700/30"
        >
          Huỷ
        </Link>
      </div>
    </template>
  </Card>
</template>
