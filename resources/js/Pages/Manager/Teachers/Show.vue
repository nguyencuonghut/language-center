<script setup>
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed } from 'vue'

// PrimeVue v4 local imports (nếu bạn không đăng ký global)
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'

// Card, Tag, Button… nếu đăng ký global thì không cần import

defineOptions({ layout: AppLayout })

const props = defineProps({
  teacher: Object,
  certificates: Array,
  assignments: { type: Array, default: () => [] }
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
</script>

<template>
  <div class="p-6 space-y-6">
    <Head :title="`Giáo viên: ${props.teacher.full_name} (${props.teacher.code})`" />

    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold">
          {{ props.teacher.full_name }}
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
                  <div><span class="text-gray-500">Email:</span> <span class="ml-2">{{ props.teacher.email || '—' }}</span></div>
                  <div><span class="text-gray-500">SĐT:</span> <span class="ml-2">{{ props.teacher.phone || '—' }}</span></div>
                  <div class="md:col-span-2"><span class="text-gray-500">Địa chỉ:</span> <span class="ml-2">{{ props.teacher.address || '—' }}</span></div>
                </div>
              </div>

              <div class="p-4 rounded-xl border">
                <h3 class="font-semibold mb-3">Thông tin khác</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                  <div><span class="text-gray-500">CCCD:</span> <span class="ml-2">{{ props.teacher.national_id || '—' }}</span></div>
                  <div><span class="text-gray-500">Ghi chú:</span> <span class="ml-2">{{ props.teacher.notes || '—' }}</span></div>
                </div>
              </div>
            </div>

            <!-- Ảnh đại diện -->
            <div class="p-4 rounded-xl border">
              <h3 class="font-semibold mb-3">Ảnh đại diện</h3>
              <div v-if="photoUrl" class="space-y-3">
                <img :src="photoUrl" alt="Ảnh giáo viên" class="w-full max-w-xs rounded-lg border" />
                <div>
                  <a :href="photoUrl" target="_blank" class="text-primary underline">Mở ảnh trong tab mới</a>
                </div>
              </div>
              <div v-else class="text-sm text-gray-500">Chưa có ảnh.</div>
            </div>
          </div>
        </TabPanel>

        <!-- CHỨNG CHỈ -->
        <TabPanel value="certs">
          <div class="rounded-xl border p-4">
            <div class="flex items-center justify-between mb-3">
              <h3 class="font-semibold">Danh sách chứng chỉ</h3>
              <Button label="Quản lý chứng chỉ" icon="pi pi-external-link" outlined @click="$inertia.visit(route('manager.certificates.index') || '#')" />
            </div>

            <DataTable :value="props.certificates" size="small" class="w-full">
              <Column field="code" header="Mã" />
              <Column field="name" header="Tên chứng chỉ" />
              <Column header="Số hiệu" :body="(row) => row.pivot?.credential_no || '—'" />
              <Column header="Đơn vị cấp" :body="(row) => row.pivot?.issued_by || '—'" />
              <Column header="Ngày cấp" :body="(row) => row.pivot?.issued_at || '—'" />
              <Column header="Hết hạn" :body="(row) => row.pivot?.expires_at || '—'" />
              <Column header="File">
                <template #body="slotProps">
                  <span v-if="slotProps.data.pivot?.file_path">
                    <a :href="route('files.signed', { path: slotProps.data.pivot.file_path })" target="_blank" class="text-primary underline">Xem</a>
                  </span>
                  <span v-else>—</span>
                </template>
              </Column>
            </DataTable>
          </div>
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
              <Column field="effective_from" header="Bắt đầu" />
              <Column field="effective_to" header="Kết thúc" />
            </DataTable>
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>
