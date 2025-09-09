<script setup>
import { reactive, ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'

// PrimeVue
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'

const props = defineProps({
  visible: { type: Boolean, default: false },
  classroomId: { type: [Number, String], required: true },
  sessionId:   { type: [Number, String, null], default: null },

  // danh sách GV dạng [{id,name,label,value,rate_per_session?}]
  teachers: { type: Array, default: () => [] },

  // nếu đã có bản ghi dạy thay, truyền vào để edit; nếu chưa có → null
  substitution: {
    type: Object,
    default: null // { id, substitute_teacher_id, rate_override, reason }
  }
})

const emit = defineEmits(['update:visible'])

const form = reactive({
  substitute_teacher_id: null,
  rate_override: null,
  reason: '',
  saving: false,
  errors: {}
})

const isEdit = computed(() => !!props.substitution?.id)
const title  = computed(() => isEdit.value ? 'Sửa dạy thay' : 'Gán dạy thay')

watch(
  () => props.visible,
  (v) => {
    if (v) {
      // fill form khi mở
      form.substitute_teacher_id = props.substitution?.substitute_teacher_id
        ? String(props.substitution.substitute_teacher_id)
        : null
      form.rate_override = props.substitution?.rate_override ?? null
      form.reason = props.substitution?.reason ?? ''
      form.errors = {}
    }
  },
  { immediate: true }
)

function close() { emit('update:visible', false) }

function save() {
  form.errors = {}
  if (!form.substitute_teacher_id) {
    form.errors.substitute_teacher_id = 'Vui lòng chọn giáo viên dạy thay'
    return
  }

  // Validate props
  if (!props.classroomId || !props.sessionId) {
    console.error('Missing required props:', { classroomId: props.classroomId, sessionId: props.sessionId })
    form.errors.general = 'Thiếu thông tin cần thiết'
    return
  }

  form.saving = true
  const payload = {
    substitute_teacher_id: Number(form.substitute_teacher_id),
    rate_override: form.rate_override != null ? Number(form.rate_override) : null,
    reason: form.reason || null,
  }

  if (!isEdit.value) {
    router.post(
      route('manager.classrooms.sessions.substitutions.store', {
        classroom: String(props.classroomId),
        class_session: String(props.sessionId)
      }),
      payload,
      {
        preserveScroll: true,
        onFinish: () => { form.saving = false },
        onSuccess: () => close(),
        onError: (errors) => { form.errors = errors || {} }
      }
    )
  } else {
    router.put(
      route('manager.classrooms.sessions.substitutions.update', {
        classroom: String(props.classroomId),
        class_session: String(props.sessionId),
        substitution: String(props.substitution.id)
      }),
      payload,
      {
        preserveScroll: true,
        onFinish: () => { form.saving = false },
        onSuccess: () => close(),
        onError: (errors) => { form.errors = errors || {} }
      }
    )
  }
}

function destroySubstitution() {
  if (!isEdit.value) return
  if (!confirm('Huỷ dạy thay cho buổi này?')) return

  form.saving = true
  router.delete(
    route('manager.classrooms.sessions.substitutions.destroy', {
      classroom: String(props.classroomId),
      class_session: String(props.sessionId),
      substitution: String(props.substitution.id)
    }),
    {
      preserveScroll: true,
      onFinish: () => { form.saving = false },
      onSuccess: () => close()
    }
  )
}
</script>

<template>
  <Dialog
    :visible="visible"
    @update:visible="(val) => emit('update:visible', val)"
    modal
    :header="title"
    :style="{ width: '520px', maxWidth: '96vw' }"
  >
    <div class="flex flex-col gap-4">
      <div v-if="form.errors?.general" class="text-red-500 text-sm bg-red-50 p-3 rounded">
        {{ form.errors.general }}
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Giáo viên dạy thay</label>
        <Select
          v-model="form.substitute_teacher_id"
          :options="(teachers||[]).map(t => ({ label: t.label ?? t.name, value: String(t.id) }))"
          optionLabel="label"
          optionValue="value"
          class="w-full"
          placeholder="Chọn giáo viên..."
        />
        <div v-if="form.errors?.substitute_teacher_id" class="text-red-500 text-xs mt-1">
          {{ form.errors.substitute_teacher_id }}
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Đơn giá thay (tuỳ chọn)</label>
        <InputNumber v-model="form.rate_override" class="w-full" mode="currency" currency="VND" locale="vi-VN" :min="0" />
        <p class="text-xs text-slate-500 mt-1">Bỏ trống để dùng đơn giá mặc định theo phân công.</p>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Lý do (tuỳ chọn)</label>
        <Textarea v-model="form.reason" rows="3" autoResize class="w-full" />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-between w-full">
        <Button
          v-if="isEdit"
          label="Huỷ dạy thay"
          icon="pi pi-trash"
          severity="danger"
          :loading="form.saving"
          @click="destroySubstitution"
        />
        <div class="ml-auto flex gap-2">
          <Button label="Đóng" icon="pi pi-times" text @click="close" />
          <Button :label="isEdit ? 'Lưu' : 'Gán dạy thay'" icon="pi pi-check" :loading="form.saving" @click="save" />
        </div>
      </div>
    </template>
  </Dialog>
</template>
