<script setup>
import { reactive, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// PrimeVue
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Checkbox from 'primevue/checkbox'
import Button from 'primevue/button'

defineOptions({ layout: AppLayout })

const props = defineProps({
  teacher: {
    type: Object,
    required: true, // {id, name, email, phone}
  }
})

const form = reactive({
  name: props.teacher?.name ?? '',
  email: props.teacher?.email ?? '',
  phone: props.teacher?.phone ?? '',
  active: props.teacher?.active ?? true,
  password: '', // optional
  errors: {},
  saving: false,
})

watch(() => props.teacher, (t) => {
  form.name  = t?.name ?? ''
  form.email = t?.email ?? ''
  form.phone = t?.phone ?? ''
  form.active = t?.active ?? true
  form.password = ''
}, { immediate: true })

function save() {
  form.errors = {}
  form.saving = true

  const payload = {
    name: form.name || '',
    email: form.email || '',
    phone: form.phone || '',
    active: form.active,
  }
  // chỉ gửi password nếu điền
  if (form.password && form.password.length > 0) {
    payload.password = form.password
  }

  router.put(route('manager.teachers.update', props.teacher.id), payload, {
    preserveScroll: true,
    onFinish: () => { form.saving = false },
    onError: (errors) => { form.errors = errors || {} },
  })
}
</script>

<template>
  <Head :title="`Sửa giáo viên - ${teacher?.name ?? ''}`" />

  <div class="mb-3 flex items-center justify-between">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Sửa giáo viên</h1>
    <Link
      :href="route('manager.teachers.index')"
      class="px-3 py-2 text-sm rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
    >
      ← Danh sách
    </Link>
  </div>

  <div class="max-w-2xl mx-auto rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
    <div class="flex flex-col gap-4">
      <!-- Họ tên -->
      <div>
        <label class="block text-sm font-medium mb-1">Họ và tên</label>
        <InputText v-model="form.name" class="w-full" placeholder="VD: Nguyễn Văn A" />
        <div v-if="form.errors?.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div>
      </div>

      <!-- Email -->
      <div>
        <label class="block text-sm font-medium mb-1">Email</label>
        <InputText v-model="form.email" class="w-full" placeholder="email@domain.com" />
        <div v-if="form.errors?.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</div>
      </div>

      <!-- Số điện thoại -->
      <div>
        <label class="block text-sm font-medium mb-1">Số điện thoại <span class="text-red-500">*</span></label>
        <InputText
          v-model="form.phone"
          class="w-full"
          placeholder="VD: 0974936497"
          maxlength="10"
          pattern="0[0-9]{9}"
          required
        />
        <div class="text-xs text-slate-500 mt-1">Định dạng: 10 số bắt đầu bằng 0</div>
        <div v-if="form.errors?.phone" class="text-red-500 text-xs mt-1">{{ form.errors.phone }}</div>
      </div>

      <!-- Mật khẩu (tuỳ chọn) -->
      <div>
        <label class="block text-sm font-medium mb-1">Mật khẩu mới (tuỳ chọn)</label>
        <Password v-model="form.password" class="w-full" :feedback="false" toggleMask placeholder="Để trống nếu không đổi" />
        <div v-if="form.errors?.password" class="text-red-500 text-xs mt-1">{{ form.errors.password }}</div>
      </div>

      <!-- Trạng thái hoạt động -->
      <div>
        <div class="flex items-center gap-2">
          <Checkbox v-model="form.active" :binary="true" inputId="active" />
          <label for="active" class="text-sm font-medium">Hoạt động</label>
        </div>
        <div class="text-xs text-slate-500 mt-1">Bỏ chọn để tạm khóa giáo viên</div>
        <div v-if="form.errors?.active" class="text-red-500 text-xs mt-1">{{ form.errors.active }}</div>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-2 mt-2">
        <Link
          :href="route('manager.teachers.index')"
          class="px-3 py-2 rounded border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          Huỷ
        </Link>
        <Button label="Lưu" icon="pi pi-check" :loading="form.saving" @click="save" />
      </div>
    </div>
  </div>
</template>
