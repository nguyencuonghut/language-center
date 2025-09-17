<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import CertificateService from '@/service/CertificateService'

// PrimeVue v4
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'

defineOptions({ layout: AppLayout })

const props = defineProps({
  certificate: Object
})

const form = useForm({
  code: props.certificate.code ?? '',
  name: props.certificate.name ?? '',
  description: props.certificate.description ?? ''
})

const onUpdate = () => CertificateService.update(props.certificate.id, form)
const onDelete = () => {
  if (!confirm('Xóa chứng chỉ này?')) return
  CertificateService.destroy(props.certificate.id)
}
</script>

<template>
  <div class="p-6 space-y-6">
    <Head :title="`Sửa chứng chỉ: ${form.name}`" />
    <h1 class="text-2xl font-semibold">Sửa chứng chỉ</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Mã</label>
        <InputText v-model="form.code" class="w-full" />
        <small v-if="form.errors.code" class="text-red-500">{{ form.errors.code }}</small>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Tên chứng chỉ</label>
        <InputText v-model="form.name" class="w-full" />
        <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
      </div>

      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Mô tả</label>
        <Textarea v-model="form.description" rows="3" class="w-full" />
        <small v-if="form.errors.description" class="text-red-500">{{ form.errors.description }}</small>
      </div>
    </div>

    <div class="flex gap-3">
      <Button label="Lưu thay đổi" :disabled="form.processing" @click="onUpdate" />
      <Button label="Xóa" severity="danger" outlined @click="onDelete" />
      <Button label="Quay lại" severity="secondary" outlined @click="CertificateService.index()" />
    </div>
  </div>
</template>
