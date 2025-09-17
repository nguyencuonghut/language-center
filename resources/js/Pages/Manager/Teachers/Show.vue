<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed, ref } from 'vue'

// PrimeVue v4 local imports (nếu bạn không đăng ký global)
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import FileUpload from 'primevue/fileupload'
import InputText from 'primevue/inputtext'

import CertificateService from '@/service/CertificateService'

defineOptions({ layout: AppLayout })

const props = defineProps({
  teacher: Object,
  certificates: Array,            // chứng chỉ đã gán cho GV
  assignments: { type: Array, default: () => [] },
  allCertificates: { type: Array, default: () => [] } // danh sách tất cả chứng chỉ để attach
})

const eduLabel = (v) => ({
  bachelor: 'Cử nhân',
  engineer: 'Kỹ sư',
  master: 'Thạc sĩ',
  phd: 'Tiến sĩ',
  other: 'Khác'
}[v] ?? v)

const statusLabel = (v) => ({
  active: 'Đang dạy',
  on_leave: 'Tạm nghỉ',
  terminated: 'Đã nghỉ việc',
  adjunct: 'Cộng tác',
  inactive: 'Không hoạt động'
}[v] ?? v)

const photoUrl = computed(() =>
  props.teacher?.photo_path ? route('files.signed', { path: props.teacher.photo_path }) : null
)

const onEdit = () => router.visit(route('manager.teachers.edit', props.teacher.id))

// ===== Attach/Detach dialog state =====
const showAttach = ref(false)
const dialogKey = ref(0)          // 👈 ép remount nội dung dialog
const fileRef = ref(null)         // 👈 ref để .clear() FileUpload

const defaults = {
  certificate_id: null,
  credential_no: '',
  issued_by: '',
  issued_at: '',
  expires_at: '',
  file: null
}
const attachForm = useForm({ ...defaults })

const hardResetAttach = () => {
  // reset tường minh + xoá lỗi + clear file + tăng key để remount
  attachForm.defaults({ ...defaults })  // đặt default mới
  attachForm.reset()                    // reset về default
  attachForm.clearErrors()
  if (fileRef.value?.clear) fileRef.value.clear()
  dialogKey.value++
}

const openAttach = () => {
  hardResetAttach()
  showAttach.value = true
}

const onSelectAttachFile = (e) => {
  attachForm.file = e.files?.[0] ?? null
}

const onAttach = () => {
  CertificateService.attachTeacher(props.teacher.id, attachForm, {
    onSuccess: () => {
      showAttach.value = false
      hardResetAttach()
    },
    onFinish: () => {
      //
    }
  })
}

const onDetach = (certId) => {
  if (!confirm('Bỏ gán chứng chỉ này?')) return
  CertificateService.detachTeacher(props.teacher.id, certId)
}
</script>

<template>
  <div class="p-6 space-y-6">
    <Head :title="`Giáo viên: ${props.teacher.name} (${props.teacher.code})`" />

    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold">
          {{ props.teacher.name }}
          <span class="text-gray-500 font-normal">• {{ props.teacher.code }}</span>
        </h1>
        <div class="mt-1 text-sm text-gray-500">
          Trạng thái:
          <span class="px-2 py-0.5 rounded-full bg-gray-100">{{ statusLabel(props.teacher.status) }}</span>
          <span class="ml-3">Trình độ: <b>{{ eduLabel(props.teacher.education_level) || '—' }}</b></span>
        </div>
      </div>

      <div class="flex gap-2">
        <Button label="Sửa" icon="pi pi-pencil" @click="onEdit" />
        <Button label="Quay lại danh sách" severity="secondary" outlined @click="$inertia.visit(route('manager.teachers.index'))" />
      </div>
    </div>

    <Tabs value="profile">
      <TabList>
        <Tab value="profile">Hồ sơ</Tab>
        <Tab value="certs">Chứng chỉ</Tab>
        <Tab value="assignments" :disabled="!assignments?.length">Phân công gần đây</Tab>
      </TabList>

      <TabPanels>
        <!-- HỒ SƠ -->
        <TabPanel value="profile">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Thông tin + Ảnh -->
            <div class="lg:col-span-2 space-y-4">
                <div class="p-4 rounded-xl border">
                <h3 class="font-semibold mb-3">Thông tin liên hệ</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <div class="flex items-center">
                    <i class="pi pi-envelope mr-2 text-gray-400"></i>
                    <span class="text-gray-500">Email:</span>
                    <span class="ml-2">{{ props.teacher.email || '—' }}</span>
                    </div>
                    <div class="flex items-center">
                    <i class="pi pi-phone mr-2 text-gray-400"></i>
                    <span class="text-gray-500">SĐT:</span>
                    <span class="ml-2">{{ props.teacher.phone || '—' }}</span>
                    </div>
                    <div class="md:col-span-2 flex items-start">
                    <i class="pi pi-map-marker mr-2 text-gray-400 mt-0.5"></i>
                    <span class="text-gray-500">Địa chỉ:</span>
                    <span class="ml-2">{{ props.teacher.address || '—' }}</span>
                    </div>
                </div>
                </div>

                <div class="p-4 rounded-xl border">
                <h3 class="font-semibold mb-3">Thông tin khác</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <div class="flex items-center">
                    <i class="pi pi-id-card mr-2 text-gray-400"></i>
                    <span class="text-gray-500">CCCD:</span>
                    <span class="ml-2">{{ props.teacher.national_id || '—' }}</span>
                    </div>
                    <div class="flex items-start">
                    <i class="pi pi-sticky-note mr-2 text-gray-400 mt-0.5"></i>
                    <span class="text-gray-500">Ghi chú:</span>
                    <span class="ml-2">{{ props.teacher.notes || '—' }}</span>
                    </div>
                </div>
                </div>
            </div>

            <!-- Ảnh đại diện -->
            <div class="p-4 rounded-xl border">
                <h3 class="font-semibold mb-3">Ảnh đại diện</h3>
                <div v-if="photoUrl" class="space-y-3">
                    <div class="flex justify-center">
                        <img :src="photoUrl" alt="Ảnh giáo viên" class="w-full max-w-xs rounded-lg border" />
                    </div>
                    <div class="text-center">
                        <a :href="photoUrl" target="_blank" class="text-primary underline">Mở ảnh trong tab mới</a>
                    </div>
                </div>
                <div v-else class="text-sm text-gray-500 text-center">Chưa có ảnh.</div>
            </div>
            </div>
        </TabPanel>

        <!-- CHỨNG CHỈ -->
        <TabPanel value="certs">
          <div class="rounded-xl border p-4">
            <div class="flex items-center justify-between mb-3">
              <h3 class="font-semibold">Danh sách chứng chỉ</h3>
              <div class="flex gap-2">
                <Button label="Gán chứng chỉ" icon="pi pi-plus" @click="openAttach" />
                <Button label="Quản lý chứng chỉ" icon="pi pi-external-link" outlined @click="$inertia.visit(route('manager.certificates.index') || '#')" />
              </div>
            </div>

            <DataTable :value="props.certificates" size="small" class="w-full">
              <Column field="code" header="Mã" />
              <Column field="name" header="Tên chứng chỉ" />
              <Column header="Số hiệu">
                <template #body="slotProps">
                  {{ slotProps.data.pivot?.credential_no || '—' }}
                </template>
              </Column>
              <Column header="Đơn vị cấp">
                <template #body="slotProps">
                  {{ slotProps.data.pivot?.issued_by || '—' }}
                </template>
              </Column>
              <Column header="Ngày cấp">
                <template #body="slotProps">
                  {{ slotProps.data.pivot?.issued_at || '—' }}
                </template>
              </Column>
              <Column header="Hết hạn">
                <template #body="slotProps">
                  {{ slotProps.data.pivot?.expires_at || '—' }}
                </template>
              </Column  >
              <Column header="File">
                <template #body="slotProps">
                  <span v-if="slotProps.data.pivot?.file_path">
                    <a :href="route('files.signed', { path: slotProps.data.pivot.file_path })" target="_blank" class="text-primary underline">Xem</a>
                  </span>
                  <span v-else>—</span>
                </template>
              </Column>
              <Column header="Thao tác" style="width: 140px">
                <template #body="slotProps">
                  <Button label="Bỏ gán" size="small" severity="danger" outlined
                          @click="onDetach(slotProps.data.id)" />
                </template>
              </Column>
            </DataTable>
          </div>

          <!-- Dialog Gán chứng chỉ -->
          <Dialog
            v-if="showAttach"
            :key="dialogKey"
            v-model:visible="showAttach"
            header="Gán chứng chỉ cho giáo viên"
            modal
            class="w-full md:w-2/3"
            @hide="hardResetAttach"
            >
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium mb-1">Chứng chỉ</label>
                <Select
                  v-model="attachForm.certificate_id"
                  :options="props.allCertificates"
                  optionLabel="name"
                  optionValue="id"
                  placeholder="Chọn chứng chỉ"
                  class="w-full"
                />
                <small v-if="attachForm.errors.certificate_id" class="text-red-500">{{ attachForm.errors.certificate_id }}</small>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label class="block text-sm font-medium mb-1">Số hiệu</label>
                  <InputText v-model="attachForm.credential_no" class="w-full" />
                </div>
                <div>
                  <label class="block text-sm font-medium mb-1">Đơn vị cấp</label>
                  <InputText v-model="attachForm.issued_by" class="w-full" />
                </div>
                <div>
                  <label class="block text-sm font-medium mb-1">Ngày cấp</label>
                  <InputText type="date" v-model="attachForm.issued_at" class="w-full" />
                </div>
                <div>
                  <label class="block text-sm font-medium mb-1">Ngày hết hạn</label>
                  <InputText type="date" v-model="attachForm.expires_at" class="w-full" />
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium mb-1">File đính kèm</label>
                <FileUpload
                    ref="fileRef"
                    mode="basic"
                    accept="application/pdf,image/*"
                    customUpload
                    :auto="false"
                    @select="onSelectAttachFile"
                />
                <small v-if="attachForm.errors.file" class="text-red-500">{{ attachForm.errors.file }}</small>
              </div>
            </div>

            <template #footer>
              <Button label="Lưu" @click="onAttach" :disabled="attachForm.processing" />
              <Button label="Hủy" severity="secondary" outlined @click="showAttach = false" />
            </template>
          </Dialog>
        </TabPanel>

        <!-- PHÂN CÔNG GẦN ĐÂY (tuỳ chọn) -->
        <TabPanel value="assignments" v-if="assignments?.length">
          <div class="rounded-xl border p-4">
            <h3 class="font-semibold mb-3">Phân công dạy gần đây</h3>
            <DataTable :value="assignments" size="small" class="w-full">
              <Column field="id" header="#" style="width: 80px" />
              <Column header="Lớp">
              <template #body="slotProps">
                <span v-if="slotProps.data.classroom">
                  <a :href="route('manager.classrooms.edit', slotProps.data.classroom.id)" class="text-primary underline">
                    {{ slotProps.data.classroom.name }}
                  </a>
                </span>
                <span v-else>—</span>
              </template>
              </Column>
              <Column field="effective_from" header="Bắt đầu">
                <template #body="{ data }">
                    {{ data.effective_from ? new Date(data.effective_from).toISOString().split('T')[0] : '—' }}
                </template>
              </Column>
              <Column field="effective_to" header="Kết thúc">
                <template #body="{ data }">
                    {{ data.effective_to ? new Date(data.effective_to).toISOString().split('T')[0] : '—' }}
                </template>
              </Column>
            </DataTable>
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>
