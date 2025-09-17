<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import CertificateService from '@/service/CertificateService'

// PrimeVue v4
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'

defineOptions({ layout: AppLayout })

const form = useForm({
  code: '',
  name: '',
  description: ''
})

const onSubmit = () => {
  CertificateService.store(form)
}
</script>

<template>
  <div class="p-6 space-y-6">
    <Head title="Tạo chứng chỉ" />
    <h1 class="text-2xl font-semibold">Tạo chứng chỉ</h1>

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
      <Button label="Lưu" :disabled="form.processing" @click="onSubmit" />
      <Button label="Hủy" severity="secondary" outlined @click="CertificateService.index()" />
    </div>
  </div>
</template>
