<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  rooms: Object, // paginator { data: [...] }, nếu bạn chưa truyền paginator thì đổi lại Array
})

function destroy(id) {
  if (confirm('Xoá phòng?')) router.delete(route('rooms.destroy', id))
}
</script>

<template>
  <Head title="Phòng học" />

  <div class="flex justify-between items-center mb-3">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Phòng học</h1>
    <Link :href="route('rooms.create')" class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
      <i class="pi pi-plus mr-1"></i>Tạo phòng
    </Link>
  </div>

  <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/40 text-slate-700 dark:text-slate-200">
          <th class="text-left p-3">Mã</th>
          <th class="text-left p-3">Tên</th>
          <th class="text-left p-3">Sức chứa</th>
          <th class="p-3 w-40"></th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="r in (rooms?.data ?? rooms ?? [])"
          :key="r.id"
          class="border-b border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/30"
        >
          <td class="p-3">{{ r.code }}</td>
          <td class="p-3">{{ r.name }}</td>
          <td class="p-3">{{ r.capacity }}</td>
          <td class="p-3">
            <div class="flex gap-2 justify-end">
              <Link :href="route('rooms.edit', r.id)" class="px-3 py-1.5 rounded border border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/20">
                <i class="pi pi-pencil mr-1"></i>Sửa
              </Link>
              <button @click="destroy(r.id)" class="px-3 py-1.5 rounded border border-red-300 text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900/20">
                <i class="pi pi-trash mr-1"></i>Xoá
              </button>
            </div>
          </td>
        </tr>

        <tr v-if="!(rooms?.data ?? rooms ?? []).length">
          <td colspan="4" class="p-6 text-center text-slate-500 dark:text-slate-400">
            Chưa có phòng nào.
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
