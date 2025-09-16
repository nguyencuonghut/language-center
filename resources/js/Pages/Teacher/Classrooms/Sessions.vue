<script setup>
import { usePage, Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import Badge from 'primevue/badge'

defineOptions({ layout: AppLayout })

const { classroom, sessions } = usePage().props

const statusLabel = status => {
  switch (status) {
    case 'planned': return { text: 'Theo KH', severity: 'info' }
    case 'canceled': return { text: 'Đã hủy', severity: 'danger' }
    case 'moved': return { text: 'Đã chuyển', severity: 'warning' }
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
  <Head title="Buổi học" />

  <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Buổi học - {{ classroom.name }}</h1>
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
      :value="sessions"
      dataKey="id"
      paginator
      :rows="10"
      :sortField="'date'"
      :sortOrder="1"
      class="p-datatable-sm"
      responsiveLayout="scroll"
      stripedRows
      :emptyMessage="'Không có buổi học nào'"
    >
      <Column field="session_no" header="Buổi" />
      <Column header="Ngày">
        <template #body="{ data }">
          <div class="flex items-center gap-2">
            {{ data.date }}
            <Badge v-if="data.holiday_name" :value="data.holiday_name" severity="warn" />
          </div>
        </template>
      </Column>
      <Column field="start_time" header="Bắt đầu" />
      <Column field="end_time" header="Kết thúc" />
      <Column field="room.name" header="Phòng" />
      <Column header="Trạng thái">
        <template #body="{ data }">
          <Tag :value="statusLabel(data.status).text" :severity="statusLabel(data.status).severity" />
        </template>
      </Column>
    </DataTable>
  </div>
</template>
