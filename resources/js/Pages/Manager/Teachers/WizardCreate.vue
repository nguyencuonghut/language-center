<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue v4 Tabs
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import FileUpload from 'primevue/fileupload'
import Checkbox from 'primevue/checkbox'
import Button from 'primevue/button'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'

// Các component khác (giả định đã đăng ký global trong main.js)
// Nếu bạn đăng ký local thì import thêm InputText, Password, Select, Textarea, FileUpload, Button, Checkbox

defineOptions({ layout: AppLayout })

const props = defineProps({
  educationLevels: { type: Array, default: () => ['bachelor','engineer','master','phd','other'] },
  teacherStatuses: { type: Array, default: () => ['active','on_leave','terminated','adjunct','inactive'] },
  defaults: { type: Object, default: () => ({ user: { active: true }, teacher: { status: 'active', education_level: null }}) }
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
  user: {
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    active: props.defaults?.user?.active ?? true,
  },
  teacher: {
    code: '',
    name: '',
    phone: '',
    email: '',
    address: '',
    national_id: '',
    education_level: props.defaults?.teacher?.education_level ?? null,
    status: props.defaults?.teacher?.status ?? 'active',
    notes: '',
    photo: null,
  }
})

const onSelectPhoto = (e) => {
  form.teacher.photo = e.files?.[0] ?? null
}

const onSubmit = () => {
  form.post(route('manager.teachers.wizard.store'), {
    forceFormData: true,
    onSuccess: () => form.reset()
  })
}
</script>

<template>
  <div class="p-6 space-y-6">
    <Head title="Tạo Giáo viên (User + Teacher)" />

    <h1 class="text-2xl font-semibold">Tạo mới Giáo viên (User + Teacher)</h1>

    <Tabs value="login">
      <TabList>
        <Tab value="login">1) Tài khoản đăng nhập</Tab>
        <Tab value="profile">2) Hồ sơ giáo viên</Tab>
      </TabList>

      <TabPanels>
        <TabPanel value="login">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- User fields -->
            <div>
              <label class="block text-sm font-medium mb-1">Họ tên (hiển thị)</label>
              <InputText v-model="form.user.name" class="w-full" />
              <small v-if="form.errors['user.name']" class="text-red-500">{{ form.errors['user.name'] }}</small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Email (đăng nhập)</label>
              <InputText v-model="form.user.email" class="w-full" />
              <small v-if="form.errors['user.email']" class="text-red-500">{{ form.errors['user.email'] }}</small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Mật khẩu</label>
              <Password v-model="form.user.password" toggleMask :feedback="false" inputClass="w-full" class="w-full" />
              <small v-if="form.errors['user.password']" class="text-red-500">{{ form.errors['user.password'] }}</small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Xác nhận mật khẩu</label>
              <Password v-model="form.user.password_confirmation" toggleMask :feedback="false" inputClass="w-full" class="w-full" />
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="form.user.active" :binary="true" inputId="active" />
              <label for="active">Kích hoạt đăng nhập</label>
            </div>
          </div>
        </TabPanel>

        <TabPanel value="profile">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Teacher fields -->
            <div>
              <label class="block text-sm font-medium mb-1">Mã giáo viên</label>
              <InputText v-model="form.teacher.code" class="w-full" />
              <small v-if="form.errors['teacher.code']" class="text-red-500">{{ form.errors['teacher.code'] }}</small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Họ tên đầy đủ</label>
              <InputText v-model="form.teacher.name" class="w-full" />
              <small v-if="form.errors['teacher.name']" class="text-red-500">{{ form.errors['teacher.name'] }}</small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">SĐT</label>
              <InputText v-model="form.teacher.phone" class="w-full" />
              <small v-if="form.errors['teacher.phone']" class="text-red-500">{{ form.errors['teacher.phone'] }}</small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Email (liên hệ)</label>
              <InputText v-model="form.teacher.email" class="w-full" placeholder="Mặc định dùng email đăng nhập nếu để trống" />
              <small v-if="form.errors['teacher.email']" class="text-red-500">{{ form.errors['teacher.email'] }}</small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Trình độ</label>
              <Select v-model="form.teacher.education_level"
                      :options="educationLevelOptions"
                      optionLabel="label"
                      optionValue="value"
                      placeholder="Chọn trình độ"
                      class="w-full" />
              <small v-if="form.errors['teacher.education_level']" class="text-red-500">{{ form.errors['teacher.education_level'] }}</small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Trạng thái</label>
              <Select v-model="form.teacher.status"
                      :options="teacherStatusOptions"
                      optionLabel="label"
                      optionValue="value"
                      placeholder="Chọn trạng thái"
                      class="w-full" />
              <small v-if="form.errors['teacher.status']" class="text-red-500">{{ form.errors['teacher.status'] }}</small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Địa chỉ</label>
              <InputText v-model="form.teacher.address" class="w-full" />
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">CCCD</label>
              <InputText v-model="form.teacher.national_id" class="w-full" />
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium mb-1">Ghi chú</label>
              <Textarea v-model="form.teacher.notes" rows="3" class="w-full" />
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium mb-1">Ảnh đại diện</label>
              <FileUpload mode="basic" name="photo" accept="image/*" customUpload :auto="false" @select="onSelectPhoto" />
              <small v-if="form.errors['teacher.photo']" class="text-red-500">{{ form.errors['teacher.photo'] }}</small>
            </div>
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>

    <div class="flex gap-3 mt-4">
      <Button label="Tạo mới" :disabled="form.processing" @click="onSubmit" />
      <Button label="Reset" severity="secondary" outlined @click="form.reset()" />
    </div>
  </div>
</template>
