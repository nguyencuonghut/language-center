<script setup>
import { reactive, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'

defineOptions({ layout: AppLayout })

const props = defineProps({
  audiences: {
    type: Array,
    default: () => [
      { label: 'Thiếu nhi', value: 'kids' },
      { label: 'Học sinh/SV', value: 'student' },
      { label: 'Người đi làm', value: 'working' },
      { label: 'TOEIC', value: 'toeic' },
      { label: 'IELTS', value: 'ielts' }
    ]
  },
  languages: {
    type: Array,
    default: () => [
      { label: 'Tiếng Anh', value: 'en' },
      { label: 'Tiếng Trung', value: 'zh' },
      { label: 'Tiếng Hàn', value: 'ko' },
      { label: 'Tiếng Nhật', value: 'ja' }
    ]
  }
})

const form = reactive({
  code: '',
  name: '',
  audience: null,
  language: null,
  active: true,
  errors: {},
  saving: false
})

function save() {
  form.errors = {}
  if (!form.code) form.errors.code = 'Vui lòng nhập mã khoá học'
  if (!form.name) form.errors.name = 'Vui lòng nhập tên khoá học'
  if (!form.audience) form.errors.audience = 'Vui lòng chọn đối tượng'
  if (!form.language) form.errors.language = 'Vui lòng chọn ngôn ngữ'
  if (Object.keys(form.errors).length) return

  form.saving = true
  router.post(route('admin.courses.store'), {
    code: form.code,
    name: form.name,
    audience: form.audience,
    language: form.language,
    active: form.active ? 1 : 0
  }, {
    preserveScroll: true,
    onFinish: () => { form.saving = false }
  })
}
</script>

<template>
  <Head title="Thêm khóa học" />

  <div class="mb-3 flex justify-between items-center">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Thêm khoá học</h1>
    <Link :href="route('admin.courses.index')" class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800">
      ← Quay lại danh sách
    </Link>
  </div>

  <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 max-w-2xl mx-auto">
    <div class="flex flex-col gap-4">
      <!-- Code -->
      <div>
        <label class="block text-sm font-medium mb-1">Mã khoá học</label>
        <InputText v-model="form.code" class="w-full" placeholder="VD: EN101" />
        <div v-if="form.errors.code" class="text-red-500 text-xs mt-1">{{ form.errors.code }}</div>
      </div>

      <!-- Name -->
      <div>
        <label class="block text-sm font-medium mb-1">Tên khoá học</label>
        <InputText v-model="form.name" class="w-full" placeholder="VD: Tiếng Anh Giao Tiếp" />
        <div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div>
      </div>

      <!-- Audience -->
      <div>
        <label class="block text-sm font-medium mb-1">Đối tượng</label>
        <Select
          v-model="form.audience"
          :options="props.audiences"
          optionLabel="label"
          optionValue="value"
          class="w-full"
          placeholder="Chọn đối tượng"
        />
        <div v-if="form.errors.audience" class="text-red-500 text-xs mt-1">{{ form.errors.audience }}</div>
      </div>

      <!-- Language -->
      <div>
        <label class="block text-sm font-medium mb-1">Ngôn ngữ</label>
        <Select
          v-model="form.language"
          :options="props.languages"
          optionLabel="label"
          optionValue="value"
          class="w-full"
          placeholder="Chọn ngôn ngữ"
        />
        <div v-if="form.errors.language" class="text-red-500 text-xs mt-1">{{ form.errors.language }}</div>
      </div>

      <!-- Active -->
      <div>
        <label class="block text-sm font-medium mb-1">Trạng thái</label>
        <div class="flex items-center gap-3">
          <span class="text-sm" :class="{ 'font-medium': form.active }">Đang hoạt động</span>
          <ToggleSwitch v-model="form.active" />
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-2 mt-4">
        <Link
          :href="route('admin.courses.index')"
          class="px-3 py-2 rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          Huỷ
        </Link>
        <Button label="Lưu" icon="pi pi-check" :loading="form.saving" @click="save" />
      </div>
    </div>
  </div>
</template>
