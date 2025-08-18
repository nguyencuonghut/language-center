<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import Button from 'primevue/button'
import FormLabel from '@/Components/FormLabel.vue'
import { createBranchService } from '@/service/BranchService'
import { usePageToast } from '@/composables/usePageToast'

defineOptions({ layout: AppLayout })

const props = defineProps({
  branch: Object,
  errors: Object,
})

const { showSuccess, showError } = usePageToast()
const branchService = createBranchService({ showSuccess, showError })

const form = useForm({
  name: props.branch.name,
  address: props.branch.address,
  active: !!props.branch.active,
})

function submit() {
  branchService.update(props.branch.id, form.data(), {
    onError: (errors) => {
      form.setError(errors)
    }
  })
}
</script>

<template>
  <Head title="Sửa chi nhánh" />

  <div class="max-w-3xl mx-auto">
    <h1 class="text-xl md:text-2xl font-heading font-semibold mb-4">Sửa chi nhánh</h1>

    <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 space-y-4">
      <div class="grid md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
          <FormLabel value="Tên chi nhánh" required />
          <InputText v-model="form.name" class="w-full" />
          <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
        </div>

        <div class="md:col-span-2">
          <FormLabel value="Địa chỉ" required />
          <Textarea v-model="form.address" class="w-full" rows="3" />
          <small v-if="form.errors.address" class="text-red-500">{{ form.errors.address }}</small>
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
        <Link :href="route('admin.branches.index')" class="px-3 py-2 rounded-lg border hover:bg-slate-50 dark:hover:bg-slate-700/30">
          Quay lại
        </Link>
      </div>
    </div>
  </div>
</template>
