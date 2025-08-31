<script setup>
import { reactive, ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'

defineOptions({ layout: AppLayout })

const props = defineProps({
  teachers: Object, // paginator
  filters: Object   // { search }
})

const state = reactive({
  search: props.filters?.search || '',
  perPage: props.teachers?.per_page || 20
})

const value = computed(() => props.teachers?.data || [])
const totalRecords = computed(() => props.teachers?.total || value.value.length)
const rows = computed(() => props.teachers?.per_page || 20)
const first = computed(() => Math.max(0, (props.teachers?.from || 1) - 1))

function applyFilters() {
  router.visit(route('manager.teachers.index', {
    search: state.search || undefined,
    per_page: state.perPage
  }), { preserveScroll: true, preserveState: true })
}

function onPage(e) {
  const page = Math.floor(e.first / e.rows) + 1
  router.visit(route('manager.teachers.index', {
    search: state.search || undefined,
    per_page: e.rows,
    page: page > 1 ? page : undefined
  }), { preserveScroll: true, preserveState: true })
}

function destroyTeacher(id) {
  if (!confirm('Bạn có chắc chắn muốn xoá giáo viên này?')) return
  router.delete(route('manager.teachers.destroy', id), {
    preserveScroll: true
  })
}
</script>

<template>
  <Head title="Quản lý giáo viên" />

  <div class="mb-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <div>
      <h1 class="text-xl md:text-2xl font-heading font-semibold">Quản lý giáo viên</h1>
      <div class="text-slate-500 dark:text-slate-400 text-sm">
        Tổng số: {{ totalRecords }} giáo viên
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <InputText v-model="state.search" placeholder="Tìm kiếm theo tên, email, SĐT" @keyup.enter="applyFilters" class="w-64" />
      <Button label="Tìm kiếm" icon="pi pi-search" @click="applyFilters" />
      <Link
        :href="route('manager.teachers.create')"
        class="px-3 py-2 rounded-lg border border-emerald-300 text-emerald-700 hover:bg-emerald-50
               dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/20"
      >
        <i class="pi pi-plus mr-1"></i> Thêm giáo viên
      </Link>
    </div>
  </div>

  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-3">
    <DataTable
      :value="value"
      :paginator="true"
      :rows="rows"
      :totalRecords="totalRecords"
      :first="first"
      lazy
      @page="onPage"
      dataKey="id"
      responsiveLayout="scroll"
      size="small"
    >
      <Column field="id" header="#" style="width: 80px" />

      <Column field="name" header="Tên" style="min-width: 200px" />

      <Column field="email" header="Email" style="min-width: 200px">
        <template #body="{ data }">
          {{ data.email || '—' }}
        </template>
      </Column>

      <Column field="phone" header="Số điện thoại" style="min-width: 140px">
        <template #body="{ data }">
          {{ data.phone || '—' }}
        </template>
      </Column>

      <Column field="active" header="Trạng thái" style="min-width: 120px">
        <template #body="{ data }">
          <span
            :class="data.active
              ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300'
              : 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300'"
            class="px-2 py-1 rounded-full text-xs font-medium"
          >
            {{ data.active ? 'Hoạt động' : 'Không hoạt động' }}
          </span>
        </template>
      </Column>

      <Column header="Thao tác" style="width: 240px">
        <template #body="{ data }">
          <div class="flex justify-end gap-2">
            <Link
              :href="route('manager.teachers.show', data.id)"
              class="px-3 py-1.5 rounded border border-slate-300 hover:bg-slate-50
                     dark:border-slate-700 dark:hover:bg-slate-900/20"
            >
              Chi tiết
            </Link>
            <Link
              :href="route('manager.teachers.edit', data.id)"
              class="px-3 py-1.5 rounded border border-emerald-300 text-emerald-700 hover:bg-emerald-50
                     dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/20"
            >
              Sửa
            </Link>
            <button
              @click="destroyTeacher(data.id)"
              class="px-3 py-1.5 rounded border border-red-300 text-red-600 hover:bg-red-50
                     dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900/20"
            >
              Xoá
            </button>
          </div>
        </template>
      </Column>

      <template #empty>
        <div class="p-6 text-center text-slate-500 dark:text-slate-400">Chưa có giáo viên nào.</div>
      </template>
    </DataTable>
  </div>
</template>
