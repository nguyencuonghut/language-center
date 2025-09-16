<script setup>
import { computed } from 'vue'
import { usePage, Link, Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'

defineOptions({ layout: AppLayout })

const { classrooms } = usePage().props

const rows = computed(() =>
  classrooms.data.map(c => ({
    id: c.id,
    code: c.code,
    name: c.name,
    course: c.course?.name,
    branch: c.branch?.name,
    start_date: c.start_date,
    status: c.status,
  }))
)

const statusLabel = status => {
  switch (status) {
    case 'open': return { text: 'Đang mở', severity: 'success' }
    case 'closed': return { text: 'Đã đóng', severity: 'danger' }
    default: return { text: status, severity: 'secondary' }
  }
}

const viewClassroom = (id) => {
  router.visit(route('teacher.classrooms.show', id))
}
</script>

<template>
  <Head title="Lớp của tôi" />

  <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Lớp bạn đang dạy</h1>
  </div>

  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-2">
    <DataTable
      :value="rows"
      dataKey="id"
      paginator
      :rows="10"
      class="p-datatable-sm"
      responsiveLayout="scroll"
      stripedRows
      :emptyMessage="'Không có lớp nào'"
    >
      <Column field="code" header="Mã lớp" />
      <Column field="name" header="Tên lớp" />
      <Column field="course" header="Khóa học" />
      <Column field="branch" header="Cơ sở" />
      <Column field="start_date" header="Khai giảng" />
      <Column header="Trạng thái">
        <template #body="{ data }">
          <Tag :value="statusLabel(data.status).text" :severity="statusLabel(data.status).severity" />
        </template>
      </Column>
      <Column header="Xem">
        <template #body="{ data }">
          <Button
            label="Chi tiết"
            outlined
            icon="pi pi-eye"
            severity="info"
            @click="viewClassroom(data.id)"
            class="p-button-sm"
          />
        </template>
      </Column>
    </DataTable>
  </div>
</template>
