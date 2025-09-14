<template>
  <Head title="Lịch dạy" />

  <!-- Header -->
  <div class="mb-2">
    <h1 class="text-xl md:text-2xl font-heading font-semibold">Lịch dạy của tôi</h1>
  </div>

  <!-- Filter Form -->
  <div class="mb-3 flex flex-col md:flex-row md:items-end md:justify-between gap-2">
    <!-- Filters: 2 rows, 2 columns each -->
    <div class="flex flex-col gap-2 w-full md:w-auto">
      <div class="flex flex-col md:flex-row gap-2">
        <div class="min-w-[180px] flex-1">
          <label class="block text-xs text-slate-500 mb-1">Chi nhánh</label>
          <Select
            v-model="filters.branch_id"
            :options="branchOptions"
            option-label="name"
            option-value="id"
            placeholder="Tất cả"
            class="w-full"
            show-clear
            @change="applyFilters"
          />
        </div>
        <div class="min-w-[180px] flex-1">
          <label class="block text-xs text-slate-500 mb-1">Lớp</label>
          <Select
            v-model="filters.class_id"
            :options="classOptions"
            option-label="name"
            option-value="id"
            placeholder="Tất cả"
            class="w-full"
            show-clear
            @change="applyFilters"
          />
        </div>
      </div>
      <div class="flex flex-col md:flex-row gap-2">
        <div class="min-w-[180px] flex-1">
          <label class="block text-xs text-slate-500 mb-1">Từ ngày</label>
          <DatePicker
            v-model="filters.from"
            date-format="yy-mm-dd"
            placeholder="Chọn từ ngày"
            class="w-full"
            show-clear
            @date-select="applyFilters"
          />
        </div>
        <div class="min-w-[180px] flex-1">
          <label class="block text-xs text-slate-500 mb-1">Đến ngày</label>
          <DatePicker
            v-model="filters.to"
            date-format="yy-mm-dd"
            placeholder="Chọn đến ngày"
            class="w-full"
            show-clear
            @date-select="applyFilters"
          />
        </div>
      </div>
    </div>
    <!-- Buttons -->
    <div class="flex gap-2 mt-2 md:mt-0 justify-end">
      <Button label="Xóa lọc" icon="pi pi-times" @click="clearFilters" class="p-button-secondary py-1 h-8" />
      <Button label="Lịch theo tuần" icon="pi pi-calendar" @click="weeklyView" class="p-button-info py-1 h-8" />
    </div>
  </div>

  <!-- Schedule Table -->
  <DataTable :value="items" class="p-datatable-sm shadow-sm">
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

<script setup>
import { ref, computed } from 'vue'; // Thêm computed
import { Head, router } from '@inertiajs/vue3'; // Thêm Head
import { usePageToast } from '@/composables/usePageToast';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import Button from 'primevue/button';
import Badge from 'primevue/badge';

defineOptions({ layout: AppLayout }); // Thêm defineOptions

const { showSuccess, showError } = usePageToast();

// Props từ controller
const props = defineProps({
  items: Array,
  filters: Object,
  branches: Array,
  classes: Array,
});

// Computed cho branch options với 'Tất cả'
const branchOptions = computed(() => {
  return [{ id: null, name: 'Tất cả' }, ...props.branches];
});

// Computed cho class options với 'Tất cả'
const classOptions = computed(() => {
  return [{ id: null, name: 'Tất cả' }, ...props.classes];
});

// Computed cho default branch_id
const defaultBranchId = computed(() => {
  if (props.branches.length === 1) {
    return props.branches[0].id;
  }
  return null; // 'Tất cả'
});

// Reactive filters với default
const filters = ref({
  branch_id: props.filters.branch_id !== undefined ? props.filters.branch_id : defaultBranchId.value,
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

// Hàm xóa lọc (reset về default)
const clearFilters = () => {
  filters.value = {
    branch_id: defaultBranchId.value,
    class_id: null,
    from: null,
    to: null,
  };
  applyFilters(); // Tự động áp dụng sau khi reset
};

// Hàm lịch theo tuần (set from/to cho tuần hiện tại)
const weeklyView = () => {
  const now = new Date();
  const startOfWeek = new Date(now);
  startOfWeek.setDate(now.getDate() - now.getDay()); // Chủ nhật
  const endOfWeek = new Date(startOfWeek);
  endOfWeek.setDate(startOfWeek.getDate() + 6); // Thứ bảy

  filters.value.from = startOfWeek.toISOString().split('T')[0];
  filters.value.to = endOfWeek.toISOString().split('T')[0];
  applyFilters(); // Tự động áp dụng
};
</script>
