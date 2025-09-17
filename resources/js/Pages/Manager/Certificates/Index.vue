<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import CertificateService from '@/service/CertificateService'

// PrimeVue v4
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'

defineOptions({ layout: AppLayout })

const props = defineProps({
  certificates: Object, // paginator
  filters: Object
})

const keyword = ref(props.filters?.keyword ?? '')

watch(keyword, (v) => {
  CertificateService.index({ keyword: v })
})

const onCreate = () => CertificateService.goCreate()
const onEdit = (row) => CertificateService.goEdit(row.id)
const onDelete = (row) => {
  if (!confirm(`Xóa chứng chỉ "${row.name}"?`)) return
  CertificateService.destroy(row.id)
}
</script>

<template>
  <div class="p-6 space-y-6">
    <Head title="Chứng chỉ" />

    <div class="flex items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold">Chứng chỉ</h1>
      <Button label="Tạo chứng chỉ" icon="pi pi-plus" @click="onCreate" />
    </div>

    <div class="flex items-center gap-3">
      <span class="text-sm text-gray-500">Tìm kiếm</span>
      <InputText v-model="keyword" class="w-64" placeholder="Nhập mã/tên/ghi chú..." />
    </div>

    <DataTable :value="props.certificates.data" paginator :rows="props.certificates.per_page"
               :totalRecords="props.certificates.total" :first="(props.certificates.current_page - 1) * props.certificates.per_page"
               class="w-full" size="small">
      <Column field="code" header="Mã" style="width: 160px" />
      <Column field="name" header="Tên chứng chỉ" />
      <Column field="description" header="Mô tả" />
      <Column header="Thao tác" style="width: 180px">
        <template #body="{ data }">
          <div class="flex gap-2">
            <Button label="Sửa" size="small" @click="onEdit(data)" />
            <Button label="Xóa" size="small" severity="danger" outlined @click="onDelete(data)" />
          </div>
        </template>
      </Column>
    </DataTable>

    <div class="flex justify-end gap-2" v-if="props.certificates.links?.length">
      <template v-for="(l, i) in props.certificates.links" :key="i">
        <Button
          :label="l.label.replace('&laquo;','«').replace('&raquo;','»')"
          :disabled="!l.url"
          :severity="l.active ? 'primary' : 'secondary'"
          outlined
          size="small"
          @click="l.url && CertificateService.index({ keyword: keyword, page: new URL(l.url).searchParams.get('page') })" />
      </template>
    </div>
  </div>
</template>
