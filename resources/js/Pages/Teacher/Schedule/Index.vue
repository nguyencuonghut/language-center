<template>
  <AppLayout title="Lịch dạy">
    <div class="p-3 md:p-5">
      <Card>
        <template #title>
          <h2 class="text-xl font-semibold">Lịch dạy của tôi</h2>
        </template>
        <template #content>
          <!-- Filter Form -->
          <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            <div>
              <label for="branch" class="block text-sm font-medium">Chi nhánh</label>
              <Select
                id="branch"
                v-model="filters.branch_id"
                :options="branches"
                option-label="name"
                option-value="id"
                placeholder="Tất cả"
                class="w-full"
                show-clear
              />
            </div>
            <div>
              <label for="class" class="block text-sm font-medium">Lớp</label>
              <Select
                id="class"
                v-model="filters.class_id"
                :options="classes"
                option-label="name"
                option-value="id"
                placeholder="Tất cả"
                class="w-full"
                show-clear
              />
            </div>
            <div>
              <label for="from" class="block text-sm font-medium">Từ ngày</label>
              <DatePicker
                id="from"
                v-model="filters.from"
                date-format="yy-mm-dd"
                placeholder="Chọn từ ngày"
                class="w-full"
                show-clear
              />
            </div>
            <div>
              <label for="to" class="block text-sm font-medium">Đến ngày</label>
              <DatePicker
                id="to"
                v-model="filters.to"
                date-format="yy-mm-dd"
                placeholder="Chọn đến ngày"
                class="w-full"
                show-clear
              />
            </div>
            <div class="md:col-span-4 flex justify-end">
              <Button type="submit" label="Áp dụng" icon="pi pi-search" />
            </div>
          </form>

          <!-- Schedule Table -->
          <DataTable :value="items" class="p-datatable-sm">
            <Column field="date" header="Ngày" sortable />
            <Column field="start_time" header="Bắt đầu" sortable />
            <Column field="end_time" header="Kết thúc" sortable />
            <Column field="class_name" header="Lớp" sortable />
            <Column field="room_name" header="Phòng" sortable />
            <Column field="branch_name" header="Chi nhánh" sortable />
            <Column field="status" header="Trạng thái" sortable />
            <!-- Cập nhật cột 'Loại' để hiển thị tên giáo viên khi dạy thay -->
            <Column header="Loại">
              <template #body="slotProps">
                <Badge
                  :value="slotProps.data.is_substitution ? `Dạy thay: ${slotProps.data.teacher_name || 'N/A'}` : 'Phân công'"
                  :severity="slotProps.data.is_substitution ? 'warn' : 'info'"
                />
              </template>
            </Column>
          </DataTable>
        </template>
      </Card>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { usePageToast } from '@/composables/usePageToast';
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Select from 'primevue/select'; // Thay Dropdown bằng Select
import DatePicker from 'primevue/datepicker';
import Button from 'primevue/button';
import Badge from 'primevue/badge';

const page = usePage();
const { showSuccess, showError } = usePageToast();

// Props từ controller
const props = defineProps({
  items: Array,
  filters: Object,
  branches: Array,
  classes: Array,
});

// Reactive filters
const filters = ref({
  branch_id: props.filters.branch_id || null,
  class_id: props.filters.class_id || null,
  from: props.filters.from || null,
  to: props.filters.to || null,
});

// Hàm áp dụng filter
const applyFilters = () => {
  router.visit(route('teacher.schedule.index'), {
    method: 'get',
    data: filters.value,
    preserveState: true,
  });
};
</script>
