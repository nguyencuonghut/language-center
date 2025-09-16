<script setup>
import { computed } from 'vue'
import { usePage, Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'

defineOptions({ layout: AppLayout })

const { classroom, enrollments } = usePage().props

const rows = computed(() =>
  enrollments.map(e => ({
    id: e.id,
    name: e.student?.name,
    email: e.student?.email,
    enrolled_at: e.enrolled_at
      ? new Date(e.enrolled_at).toLocaleDateString('vi-VN')
      : '',
    status: e.status,
  }))
)

const statusLabel = status => {
  switch (status) {
    case 'active': return { text: 'Đang học', severity: 'success' }
    case 'completed': return { text: 'Hoàn thành', severity: 'info' }
    case 'canceled': return { text: 'Hủy', severity: 'danger' }
    default: return { text: status, severity: 'secondary' }
  }
}

const classDetail = () => {
  router.visit(route('teacher.classrooms.show', classroom.id))
}

const classList = () => {
  router.visit(route('teacher.classrooms.index'))
}
</script>

<template>
  <Head title="Học viên" />

  <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Danh sách học viên - {{ classroom.name }}</h1>
    <div class="flex gap-2">
      <Button
        label="Chi tiết lớp"
        outlined
        icon="pi pi-arrow-left"
        severity="help"
        @click="classDetail"
        class="p-button-sm"
      />
      <Button
        label="Danh sách lớp"
        outlined
        icon="pi pi-arrow-left"
        severity="info"
        @click="classList"
        class="p-button-sm"
      />
    </div>
  </div>

  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
    <DataTable
      :value="rows"
      dataKey="id"
      paginator
      :rows="10"
      class="p-datatable-sm"
      responsiveLayout="scroll"
      stripedRows
      :emptyMessage="'Không có học viên nào'"
    >
      <Column field="name" header="Họ tên" />
      <Column field="email" header="Email" />
      <Column field="enrolled_at" header="Ngày ghi danh" />
      <Column header="Trạng thái">
        <template #body="{ data }">
          <Tag :value="statusLabel(data.status).text" :severity="statusLabel(data.status).severity" />
        </template>
      </Column>
    </DataTable>
  </div>
</template>
