<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Checkbox from 'primevue/checkbox'
import Button from 'primevue/button'
import FormLabel from '@/Components/FormLabel.vue'
import { createRoomService } from '@/service/RoomService'
import { usePageToast } from '@/composables/usePageToast'

defineOptions({ layout: AppLayout })

const props = defineProps({
  room: Object,
  branches: Array,
  errors: Object,
})

const { showSuccess, showError } = usePageToast()
const roomService = createRoomService({ showSuccess, showError })

const form = useForm({
  branch_id: props.room.branch_id,
  code: props.room.code,
  name: props.room.name,
  capacity: props.room.capacity,
  active: !!props.room.active,
})

function submit() {
  roomService.update(props.room.id, form.data(), {
    onError: (errors) => {
      form.setError(errors)
    }
  })
}
</script>

<template>
  <Head title="Sửa phòng" />

  <div class="max-w-3xl mx-auto">
    <h1 class="text-xl md:text-2xl font-heading font-semibold mb-4">Sửa phòng</h1>

    <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 space-y-4">
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <FormLabel value="Chi nhánh" required />
          <Select
            v-model="form.branch_id"
            :options="(props.branches || []).map(b => ({ label: b.name, value: b.id }))"
            optionLabel="label" optionValue="value"
            placeholder="Chọn chi nhánh"
            :pt="{ root: { class: 'w-full' } }"
          />
          <small v-if="form.errors.branch_id" class="text-red-500">{{ form.errors.branch_id }}</small>
        </div>

        <div>
          <FormLabel value="Mã phòng" required />
          <InputText v-model="form.code" class="w-full" />
          <small v-if="form.errors.code" class="text-red-500">{{ form.errors.code }}</small>
        </div>

        <div>
          <FormLabel value="Tên phòng" required />
          <InputText v-model="form.name" class="w-full" />
          <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
        </div>

        <div>
          <FormLabel value="Sức chứa" required />
          <InputNumber v-model="form.capacity" inputClass="w-full" :min="1" :max="1000" />
          <small v-if="form.errors.capacity" class="text-red-500">{{ form.errors.capacity }}</small>
        </div>

        <div class="md:col-span-2">
          <div class="inline-flex items-center gap-2">
            <Checkbox v-model="form.active" :binary="true" inputId="active" />
            <label for="active">Kích hoạt</label>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <Button label="Cập nhật" icon="pi pi-check" severity="success" :loading="form.processing" @click="submit" />
        <Link :href="route('admin.rooms.index')" class="px-3 py-2 rounded-lg border hover:bg-slate-50 dark:hover:bg-slate-700/30">
          Quay lại
        </Link>
      </div>
    </div>
  </div>
</template>
