<script setup>
// PrimeVue
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import { ref, computed, onMounted, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

// Composables
import { usePageToast } from '@/composables/usePageToast'
import Drawer from 'primevue/drawer'
import Button from 'primevue/button'

const page = usePage()
const { showSuccess, showError, showInfo } = usePageToast()

/* Flash messages từ server */
watch(
  () => page.props.flash,
  (flash) => {
    if (flash?.success) showSuccess('Thành công', flash.success)
    if (flash?.error)   showError('Lỗi', flash.error)
    if (flash?.message) showInfo('Thông báo', flash.message)
  },
  { deep: true, immediate: true }
)

/* Theme (dark/light) — đơn giản */
const isDark = ref(false)
function applyTheme(next) {
  const html = document.documentElement
  next ? html.classList.add('dark') : html.classList.remove('dark')
  isDark.value = next
  localStorage.setItem('theme', next ? 'dark' : 'light')
}
onMounted(() => {
  const saved = localStorage.getItem('theme')
  const initial = saved ? saved === 'dark' : (window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false)
  applyTheme(initial)
})
function toggleDark(){ applyTheme(!isDark.value) }

/* Sidebar */
const showDrawer = ref(false)
const isCollapsed = ref(false)

/* ===== Menu theo vai trò ===== */
// Chuẩn hoá roles → mảng tên (string)
const rolesRaw = computed(() => page.props?.auth?.user?.roles ?? [])
const roleNames = computed(() => {
  const arr = Array.isArray(rolesRaw.value) ? rolesRaw.value : []
  return arr.map(r => (typeof r === 'string' ? r : r?.name)).filter(Boolean)
})

// Helpers check vai trò
const hasRole = (name) => roleNames.value.includes(name)
const isAdmin   = computed(() => hasRole('admin'))
const isManager = computed(() => hasRole('manager'))
const isTeacher = computed(() => hasRole('teacher'))

// Admin menu
const adminMenu = [
  { label: 'Dashboard', icon: 'pi pi-home',     url: '/admin/dashboard',   ready: true },
  { label: 'Chi nhánh', icon: 'pi pi-sitemap',  url: '/admin/branches',    ready: true },
  { label: 'Phòng học', icon: 'pi pi-building', url: '/manager/rooms',     ready: true },
  { label: 'Giáo viên', icon: 'pi pi-id-card',  url: '/manager/teachers',  ready: true },
  { label: 'Bảng công',  icon: 'pi pi-clock',    url: '/manager/timesheets',  ready: true },
  { label: 'Lớp học',   icon: 'pi pi-users',    url: '/admin/classrooms',  ready: true },
  { label: 'Học viên',  icon: 'pi pi-id-card',  url: '/manager/students',    ready: true },
  { label: 'Điểm danh', icon: 'pi pi-check-square', url: '/admin/attendance', ready: false },
  { label: 'Chuyển lớp', icon: 'pi pi-arrow-right-arrow-left', url: '/manager/transfers', ready: true },
  { label: 'Khóa học',  icon: 'pi pi-book',     url: '/admin/courses',     ready: true },
  { label: 'Hoá đơn', icon: 'pi pi-wallet', url: '/admin/invoices', ready: true },
  { label: 'Báo cáo',   icon: 'pi pi-chart-bar',url: '/admin/reports',     ready: false },
  { label: 'Cài đặt',   icon: 'pi pi-cog',      url: '/admin/settings',    ready: false },
]

// Manager menu — đã thêm “Bảng công”
const managerMenu = [
  { label: 'Dashboard',  icon: 'pi pi-home',     url: '/manager/dashboard',   ready: true },
  { label: 'Lớp học',    icon: 'pi pi-users',    url: '/manager/classrooms',  ready: true },
  { label: 'Học viên',   icon: 'pi pi-id-card',  url: '/manager/students',    ready: true },
  { label: 'Chuyển lớp', icon: 'pi pi-arrow-right-arrow-left', url: '/manager/transfers', ready: true },
  { label: 'Phòng học',  icon: 'pi pi-building', url: '/manager/rooms',       ready: true },
  { label: 'Giáo viên', icon: 'pi pi-id-card',  url: '/manager/teachers',  ready: true },
  { label: 'Bảng công',  icon: 'pi pi-clock',    url: '/manager/timesheets',  ready: true },
  { label: 'Bảng lương', icon: 'pi pi-briefcase', url: '/manager/payrolls', ready: true },
  { label: 'Hoá đơn',    icon: 'pi pi-wallet',   url: '/manager/invoices',     ready: true },
  { label: 'Báo cáo',    icon: 'pi pi-chart-bar',url: '/manager/reports',     ready: false },
]

// Teacher menu
const teacherMenu = [
  { label: 'Dashboard', icon: 'pi pi-home',         url: '/teacher/dashboard',    ready: true },
  { label: 'Điểm danh', icon: 'pi pi-check-square', url: '/teacher/attendance',   ready: true },
  { label: 'Lịch dạy',  icon: 'pi pi-calendar',     url: '/teacher/schedule',     ready: false },
]

// Chọn menu theo vai trò (ưu tiên admin > manager > teacher)
const menu = computed(() => {
  if (isAdmin.value)   return adminMenu
  if (isManager.value) return managerMenu
  if (isTeacher.value) return teacherMenu
  return [] // fallback
})

/* Active route highlight */
function normalizePath(path = '') {
  path = String(path).split('#')[0].split('?')[0] || '/'
  if (path.length > 1 && path.endsWith('/')) path = path.slice(0, -1)
  return path
}
const currentPath = computed(() => normalizePath(page.url || window.location.pathname))
function isActive(item) {
  const base = normalizePath(item.url)
  return currentPath.value === base || currentPath.value.startsWith(base + '/')
}

function go(item) {
  if (!item.ready) {
    showInfo('Đang phát triển', `${item.label} sẽ có sớm`)
    return
  }
  router.visit(item.url)
}

/* Logout (nếu bạn có route logout) */
function logout(){ try { router.post(route('logout')) } catch { /* optional */ } }
</script>

<template>
  <div class="min-h-screen flex font-sans bg-[#f6f8fa] dark:bg-[#181c23] text-[#23272f] dark:text-[#f6f8fa] transition-colors duration-theme">
    <Toast position="top-right" />

    <!-- Sidebar desktop -->
    <aside
      :class="[
        'hidden lg:flex lg:flex-col bg-white dark:bg-[#23272f] border-r border-[#e5e7eb] dark:border-[#23272f] shadow transition-all duration-300 ease-in-out',
        isCollapsed ? 'w-16 p-2' : 'w-60 p-3'
      ]"
    >
      <div class="flex items-center justify-between mb-2" :class="{ 'justify-center': isCollapsed }">
        <div v-if="!isCollapsed" class="font-bold truncate">Language Center</div>
        <Button
          :icon="isCollapsed ? 'pi pi-arrow-right' : 'pi pi-arrow-left'"
          text
          rounded
          @click="isCollapsed = !isCollapsed"
          class="!text-slate-700 dark:!text-slate-100"
          :title="isCollapsed ? 'Mở rộng sidebar' : 'Thu gọn sidebar'"
        />
      </div>

      <nav class="flex-1 overflow-y-auto space-y-1">
        <button
          v-for="(item, i) in menu"
          :key="i"
          type="button"
          @click="go(item)"
          :class="[
            'w-full flex items-center gap-2 px-3 py-2 rounded-lg text-left transition-colors',
            isActive(item)
              ? 'text-[#10b981] bg-[#e6f9f3] ring-1 ring-[#b6f0dd] font-semibold dark:text-[#6ee7b7] dark:bg-[#1e293b] dark:ring-[#10b981]'
              : (item.ready
                    ? 'text-[#23272f] hover:text-[#10b981] hover:bg-[#e6f9f3] dark:text-[#f6f8fa] dark:hover:text-[#6ee7b7] dark:hover:bg-[#23272f]'
                    : 'text-[#b0b7c3] dark:text-[#4b5563] cursor-not-allowed'),
            isCollapsed ? 'justify-center' : ''
          ]"
          :disabled="!item.ready"
          :title="item.ready ? item.label : 'Đang phát triển'"
        >
          <i :class="['pi', item.icon, 'text-lg']"></i>
          <span v-if="!isCollapsed" class="truncate">{{ item.label }}</span>
        </button>
      </nav>
    </aside>

    <!-- Drawer mobile -->
    <Drawer v-model:visible="showDrawer" position="left" class="!w-80" :modal="true" :showCloseIcon="true">
      <div class="mb-3 font-semibold">Menu</div>
      <nav class="flex flex-col gap-1">
        <button
          v-for="(item, i) in menu"
          :key="'m'+i"
          type="button"
          @click="showDrawer=false; go(item)"
          :class="[
            'w-full flex items-center gap-2 px-3 py-2 rounded-lg text-left transition-colors',
            isActive(item)
              ? 'text-[#10b981] bg-[#e6f9f3] ring-1 ring-[#b6f0dd] font-semibold dark:text-[#6ee7b7] dark:bg-[#1e293b] dark:ring-[#10b981]'
              : (item.ready
                    ? 'text-[#23272f] hover:text-[#10b981] hover:bg-[#e6f9f3] dark:text-[#f6f8fa] dark:hover:text-[#6ee7b7] dark:hover:bg-[#23272f]'
                    : 'text-[#b0b7c3] dark:text-[#4b5563] cursor-not-allowed')
          ]"
          :disabled="!item.ready"
          :title="item.ready ? item.label : 'Đang phát triển'"
        >
          <i :class="['pi', item.icon, 'text-lg']"></i>
          <span class="truncate">{{ item.label }}</span>
        </button>
      </nav>
    </Drawer>

    <!-- Main -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Topbar -->
      <header class="flex items-center justify-between bg-white dark:bg-[#23272f] border-b border-[#e5e7eb] dark:border-[#23272f] px-3 py-2 shadow-sm transition-colors duration-theme">
        <div class="flex items-center gap-2">
          <Button icon="pi pi-bars" text rounded class="lg:!hidden !text-[#10b981] dark:!text-[#6ee7b7]" @click="showDrawer = true" />
          <span class="hidden sm:block text-sm">Xin chào, {{ page.props?.auth?.user?.name ?? 'User' }}</span>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="toggleDark"
            :title="isDark ? 'Chuyển Light' : 'Chuyển Dark'"
            class="h-9 w-9 inline-flex items-center justify-center rounded-full dark:border-[#10b981] hover:bg-[#e6f9f3]/60 dark:hover:bg-[#23272f]/60 transition-colors duration-theme"
            aria-label="Dark / Light"
          >
            <i :class="[ isDark ? 'pi pi-sun' : 'pi pi-moon', 'text-lg', 'text-[#10b981] dark:text-[#6ee7b7]' ]" />
          </button>
          <Button icon="pi pi-sign-out" severity="secondary" :outlined="false" class="!text-[#10b981] dark:!text-[#6ee7b7] !border-0 !bg-transparent hover:!bg-[#e6f9f3] dark:hover:!bg-[#23272f]" @click="logout" />
        </div>
      </header>

      <main class="flex-1 p-3 md:p-5">
        <slot />
      </main>
    </div>
  </div>
</template>
