<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Tag from 'primevue/tag'
import Button from 'primevue/button'

defineOptions({ layout: AppLayout })

const props = defineProps({
  course: {
    type: Object,
    required: true // {id, code, name, audience, language, active, created_at, updated_at}
  }
})

/* Map audience */
const audienceMap = {
  kids: 'Thiếu nhi',
  student: 'Học sinh/SV',
  working: 'Người đi làm',
  toeic: 'TOEIC',
  ielts: 'IELTS'
}

/* Map language */
const languageMap = {
  en: 'Tiếng Anh',
  zh: 'Tiếng Trung',
  ko: 'Tiếng Hàn',
  ja: 'Tiếng Nhật'
}

/* Format datetime dd/mm/yyyy */
function toDdMmYyyy(date) {
  if (!date) return '—'
  const d = new Date(date)
  if (isNaN(d.getTime())) return String(date)
  const dd = String(d.getDate()).padStart(2, '0')
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const yyyy = d.getFullYear()
  return `${dd}/${mm}/${yyyy}`
}
</script>

<template>
  <Head :title="`Chi tiết khóa học · ${course.name}`" />

  <!-- Header -->
  <div class="mb-3 flex justify-between items-center">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">
      Khóa học: {{ course.name }}
    </h1>
    <div class="flex items-center gap-2">
      <Link
        :href="route('admin.courses.edit', course.id)"
        class="px-3 py-2 rounded-lg border border-emerald-300 text-emerald-700 hover:bg-emerald-50
               dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/20"
      >
        <i class="pi pi-pencil mr-1"></i> Sửa
      </Link>
      <Link
        :href="route('admin.courses.index')"
        class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600
               hover:bg-slate-100 dark:hover:bg-slate-800"
      >
        ← Quay lại danh sách
      </Link>
    </div>
  </div>

  <!-- Course details -->
  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 max-w-3xl mx-auto space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- Left -->
      <div>
        <p class="text-sm text-slate-500 mb-1">Mã khoá học</p>
        <p class="font-medium">{{ course.code }}</p>

        <p class="text-sm text-slate-500 mb-1 mt-3">Tên khoá học</p>
        <p class="font-medium">{{ course.name }}</p>

        <p class="text-sm text-slate-500 mb-1 mt-3">Trạng thái</p>
        <Tag :value="course.active ? 'Đang hoạt động' : 'Ngừng hoạt động'" :severity="course.active ? 'success' : 'danger'" />
      </div>

      <!-- Right -->
      <div>
        <p class="text-sm text-slate-500 mb-1">Đối tượng</p>
        <p class="font-medium">{{ audienceMap[course.audience] || '—' }}</p>

        <p class="text-sm text-slate-500 mb-1 mt-3">Ngôn ngữ</p>
        <p class="font-medium">{{ languageMap[course.language] || '—' }}</p>

        <p class="text-sm text-slate-500 mb-1 mt-3">Ngày tạo</p>
        <p class="font-medium">{{ toDdMmYyyy(course.created_at) }}</p>

        <p class="text-sm text-slate-500 mb-1 mt-3">Ngày cập nhật</p>
        <p class="font-medium">{{ toDdMmYyyy(course.updated_at) }}</p>
      </div>
    </div>
  </div>
</template>
