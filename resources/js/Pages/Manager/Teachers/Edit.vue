<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue v4 Tabs (local imports)
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import FileUpload from 'primevue/fileupload'
import Checkbox from 'primevue/checkbox'
import Button from 'primevue/button'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
  teacher: Object,
  educationLevels: Array,
  teacherStatuses: Array
})

const educationLevelOptions = computed(() =>
  props.educationLevels.map(v => ({ label: labelEdu(v), value: v }))
)
const teacherStatusOptions = computed(() =>
  props.teacherStatuses.map(v => ({ label: labelStatus(v), value: v }))
)

function labelEdu(v) {
  switch (v) {
    case 'bachelor': return 'Cử nhân'
    case 'engineer': return 'Kỹ sư'
    case 'master': return 'Thạc sĩ'
    case 'phd': return 'Tiến sĩ'
    default: return 'Khác'
  }
}
function labelStatus(v) {
  switch (v) {
    case 'active': return 'Đang dạy'
    case 'on_leave': return 'Tạm nghỉ'
    case 'terminated': return 'Đã nghỉ việc'
    case 'adjunct': return 'Cộng tác'
    default: return 'Không hoạt động'
  }
}

const form = useForm({
  code: props.teacher.code ?? '',
  full_name: props.teacher.full_name ?? '',
  phone: props.teacher.phone ?? '',
  email: props.teacher.email ?? '',
  address: props.teacher.address ?? '',
  national_id: props.teacher.national_id ?? '',
  education_level: props.teacher.education_level ?? null,
  status: props.teacher.status ?? 'active',
  notes: props.teacher.notes ?? '',
  photo: null,          // File
  remove_photo: false,  // Boolean
})

const onSelectPhoto = (e) => {
  form.photo = e.files?.[0] ?? null
}

const toFormData = (data) => {
  const fd = new FormData()
  Object.entries(data).forEach(([k, v]) => {
    if (v === undefined || v === null) return
    if (v instanceof File) {
      fd.append(k, v)
    } else if (typeof v === 'boolean') {
      fd.append(k, v ? '1' : '0')
    } else {
      fd.append(k, v)
    }
  })
  fd.append('_method', 'PUT') // spoof PUT cho route resource
  return fd
}

const onUpdate = () => {
  // transform -> FormData để đảm bảo FE -> BE không bị null khi có file
  form.transform((data) => toFormData(data))
    .post(route('manager.teachers.update', props.teacher.id), {
      preserveScroll: true,
      onFinish: () => {
        // trả transform về mặc định để các lần submit khác không bị ảnh hưởng
        form.transform((d) => d)
      }
    })
}

</script>

<template>
  <div class="p-6 space-y-6">
    <Head :title="`Sửa Giáo viên: ${form.full_name || form.code}`" />

    <h1 class="text-2xl font-semibold">Sửa hồ sơ giáo viên</h1>

    <div class="flex justify-end gap-3 mb-5">
      <Link
        :href="route('manager.teachers.show', { teacher: props.teacher.id })"
        class="px-3 py-1.5 rounded-lg border border-indigo-300 dark:border-indigo-600 hover:bg-indigo-100 dark:hover:bg-indigo-800"
      >
        Chi tiết giáo viên
      </Link>

      <Link
        :href="route('manager.teachers.index')"
        class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
      >
        ← Danh sách giáo viên
      </Link>
    </div>

    <Tabs value="profile">
      <TabList>
        <Tab value="profile">Hồ sơ giáo viên</Tab>
        <Tab value="account" disabled>Thông tin đăng nhập (quản lý ở Users)</Tab>
      </TabList>

      <TabPanels>
        <TabPanel value="profile">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Mã giáo viên</label>
              <InputText v-model="form.code" class="w-full" />
              <small v-if="form.errors.code" class="text-red-500">{{ form.errors.code }}</small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Họ tên đầy đủ</label>
              <InputText v-model="form.full_name" class="w-full" />
              <small v-if="form.errors.full_name" class="text-red-500">{{ form.errors.full_name }}</small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">SĐT</label>
              <InputText v-model="form.phone" class="w-full" />
              <small v-if="form.errors.phone" class="text-red-500">{{ form.errors.phone }}</small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Email (liên hệ)</label>
              <InputText v-model="form.email" class="w-full" />
              <small v-if="form.errors.email" class="text-red-500">{{ form.errors.email }}</small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Trình độ</label>
              <Select v-model="form.education_level"
                      :options="educationLevelOptions"
                      optionLabel="label"
                      optionValue="value"
                      placeholder="Chọn trình độ"
                      class="w-full" />
              <small v-if="form.errors.education_level" class="text-red-500">{{ form.errors.education_level }}</small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Trạng thái</label>
              <Select v-model="form.status"
                      :options="teacherStatusOptions"
                      optionLabel="label"
                      optionValue="value"
                      placeholder="Chọn trạng thái"
                      class="w-full" />
              <small v-if="form.errors.status" class="text-red-500">{{ form.errors.status }}</small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Địa chỉ</label>
              <InputText v-model="form.address" class="w-full" />
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">CCCD</label>
              <InputText v-model="form.national_id" class="w-full" />
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium mb-1">Ghi chú</label>
              <Textarea v-model="form.notes" rows="3" class="w-full" />
            </div>

            <div class="md:col-span-2 space-y-2">
              <label class="block text-sm font-medium">Ảnh đại diện</label>

              <div class="flex items-center gap-3" v-if="props.teacher.photo_path">
                <!-- nếu chưa có route ký URL, bỏ link này đi -->
                <a :href="route('files.signed', { path: props.teacher.photo_path })" target="_blank" class="text-primary underline">Xem ảnh hiện tại</a>
                <Checkbox v-model="form.remove_photo" :binary="true" inputId="remove_photo" />
                <label for="remove_photo">Xóa ảnh hiện tại</label>
              </div>

              <FileUpload mode="basic" name="photo" accept="image/*" customUpload :auto="false" @select="onSelectPhoto" />
              <small v-if="form.errors.photo" class="text-red-500">{{ form.errors.photo }}</small>
            </div>
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>

    <div class="flex gap-3">
      <Button label="Lưu thay đổi" :disabled="form.processing" @click="onUpdate" />
    </div>
  </div>
</template>
