<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import Tag from 'primevue/tag'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'

defineOptions({ layout: AppLayout })

const props = defineProps({
  // user có role 'teacher'
  teacher: {
    type: Object,
    required: true, // { id, name, email, phone, created_at, updated_at, roles?:[] }
  },
  // tuỳ chọn: danh sách phân công dạy
  assignments: {
    type: Array,
    default: () => [] // [{ id, class: {id, code, name}, rate_per_session, effective_from, effective_to }]
  }
})

function toDdMmYyyy(d) {
  if (!d) return '—'
  const dt = new Date(String(d).replace(' ', 'T'))
  if (isNaN(dt.getTime())) {
    const [y, m, day] = String(d).split('-')
    if (y && m && day) return `${day.padStart(2,'0')}/${m.padStart(2,'0')}/${y}`
    return String(d)
  }
  const dd = String(dt.getDate()).padStart(2,'0')
  const mm = String(dt.getMonth() + 1).padStart(2,'0')
  const yy = dt.getFullYear()
  return `${dd}/${mm}/${yy}`
}

const roleChips = computed(() => {
  // Sử dụng role_names_vi từ accessor trong Model User
  return props.teacher?.role_names_vi || []
})

const itemsValue = computed(() => props.assignments || [])
</script>

<template>
  <Head :title="`Giáo viên: ${teacher?.name ?? ''}`" />

  <!-- Header -->
  <div class="mb-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <div>
      <h1 class="text-xl md:text-2xl font-heading font-semibold">
        Giáo viên — {{ teacher?.name }}
      </h1>
      <div class="text-slate-500 dark:text-slate-400 text-sm">
        <span class="mr-2">Email: <span class="font-medium text-slate-900 dark:text-slate-100">{{ teacher?.email ?? '—' }}</span></span>
        <span class="mr-2">·</span>
        <span>Điện thoại: <span class="font-medium text-slate-900 dark:text-slate-100">{{ teacher?.phone ?? '—' }}</span></span>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <Link
        :href="route('manager.teachers.edit', teacher.id)"
        class="px-3 py-1.5 rounded-lg border border-emerald-300 text-emerald-700 hover:bg-emerald-50
               dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/20"
      >
        <i class="pi pi-pencil mr-1"></i> Sửa
      </Link>
      <Link
        :href="route('manager.teachers.index')"
        class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
      >
        ← Danh sách
      </Link>
    </div>
  </div>

  <!-- Summary cards -->
  <div class="grid gap-4 md:grid-cols-2 mb-4">
    <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
      <div class="text-sm text-slate-500 mb-1">Vai trò</div>
      <div class="flex flex-wrap gap-2">
        <Tag v-for="(r, i) in roleChips" :key="i" :value="r" severity="info" />
        <span v-if="!roleChips.length" class="text-slate-500 dark:text-slate-400">—</span>
      </div>

      <div class="mt-3 text-sm text-slate-500 mb-1">Ngày tạo</div>
      <div class="font-medium">{{ toDdMmYyyy(teacher?.created_at) }}</div>

      <div class="mt-3 text-sm text-slate-500 mb-1">Cập nhật</div>
      <div class="font-medium">{{ toDdMmYyyy(teacher?.updated_at) }}</div>
    </div>

    <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
      <div class="text-sm text-slate-500 mb-1">Thông tin liên hệ</div>
      <div class="space-y-1">
        <div><span class="text-slate-500 dark:text-slate-400">Email:</span> <span class="font-medium">{{ teacher?.email ?? '—' }}</span></div>
        <div><span class="text-slate-500 dark:text-slate-400">Điện thoại:</span> <span class="font-medium">{{ teacher?.phone ?? '—' }}</span></div>
        <div>
          <span class="text-slate-500 dark:text-slate-400">Trạng thái:</span>
          <span
            :class="teacher?.active
              ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300'
              : 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300'"
            class="ml-1 px-2 py-1 rounded-full text-xs font-medium"
          >
            {{ teacher?.active ? 'Hoạt động' : 'Không hoạt động' }}
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Assignments -->
  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-3">
    <div class="flex items-center justify-between mb-2">
      <div class="font-medium">Phân công giảng dạy</div>
      <div class="text-sm text-slate-500 dark:text-slate-400">({{ itemsValue.length }} mục)</div>
    </div>

    <DataTable :value="itemsValue" dataKey="id" size="small" responsiveLayout="scroll">
      <Column header="Lớp" style="width: 280px">
        <template #body="{ data }">
          <span v-if="data.classroom">
            {{ data.classroom.code }} · {{ data.classroom.name }}
          </span>
          <span v-else>—</span>
        </template>
      </Column>
      <Column field="rate_per_session" header="Đơn giá/buổi" style="width: 160px">
        <template #body="{ data }">
          {{ new Intl.NumberFormat('vi-VN', { style:'currency', currency:'VND' }).format(data.rate_per_session || 0) }}
        </template>
      </Column>
      <Column field="effective_from" header="Hiệu lực từ" style="width: 140px">
        <template #body="{ data }">{{ toDdMmYyyy(data.effective_from) }}</template>
      </Column>
      <Column field="effective_to" header="Đến" style="width: 140px">
        <template #body="{ data }">{{ toDdMmYyyy(data.effective_to) }}</template>
      </Column>

      <template #empty>
        <div class="p-4 text-center text-slate-500 dark:text-slate-400">
          Chưa có phân công nào.
        </div>
      </template>
    </DataTable>
  </div>
</template>
