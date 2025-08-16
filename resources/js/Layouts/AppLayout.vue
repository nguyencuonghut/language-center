<script setup>
import { ref, computed, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

// PrimeVue
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import Drawer from 'primevue/drawer'
import Button from 'primevue/button'

const page = usePage()
const toast = useToast()

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

/* Menu items */
const menu = [
  { label: 'Dashboard', icon: 'pi pi-home', url: '/admin/dashboard', ready: true },
  { label: 'Phòng học', icon: 'pi pi-building', url: '/rooms', ready: true },          // ĐÃ có route
  { label: 'Lớp học', icon: 'pi pi-users', url: '/admin/classrooms', ready: false },
  { label: 'Học viên', icon: 'pi pi-id-card', url: '/admin/students', ready: false },
  { label: 'Điểm danh', icon: 'pi pi-check-square', url: '/admin/attendance', ready: false },
  { label: 'Khóa học', icon: 'pi pi-book', url: '/admin/courses', ready: false },
  { label: 'Hóa đơn', icon: 'pi pi-wallet', url: '/admin/billing', ready: false },
  { label: 'Báo cáo', icon: 'pi pi-chart-bar', url: '/admin/reports', ready: false },
  { label: 'Cài đặt', icon: 'pi pi-cog', url: '/admin/settings', ready: false },
]

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
    toast.add({ severity: 'info', summary: 'Đang phát triển', detail: `${item.label} sẽ có sớm`, life: 1800 })
    return
  }
  router.visit(item.url)
}

/* Logout (nếu bạn có route logout) */
function logout(){ try { router.post(route('logout')) } catch { /* optional */ } }
</script>

<template>
  <div class="min-h-screen flex font-sans bg-gray-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100">
    <Toast position="top-right" />

    <!-- Sidebar desktop -->
    <aside
      :class="[
        'hidden lg:flex lg:flex-col bg-white dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700 transition-all duration-300 ease-in-out',
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
              ? 'text-emerald-700 bg-emerald-50 ring-1 ring-emerald-200 font-semibold dark:text-emerald-300 dark:bg-emerald-900/30 dark:ring-emerald-700/40'
              : (item.ready
                    ? 'text-slate-700 hover:text-emerald-700 hover:bg-emerald-50 dark:text-slate-300 dark:hover:text-emerald-300 dark:hover:bg-emerald-900/20'
                    : 'text-slate-400 dark:text-slate-500 cursor-not-allowed'),
            isCollapsed ? 'justify-center' : ''
          ]"
          :disabled="!item.ready"
          :title="item.ready ? item.label : 'Đang phát triển'"
        >
          <i :class="['pi', item.icon, 'text-base']"></i>
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
              ? 'text-emerald-700 bg-emerald-50 ring-1 ring-emerald-200 font-semibold dark:text-emerald-300 dark:bg-emerald-900/30 dark:ring-emerald-700/40'
              : (item.ready
                    ? 'text-slate-700 hover:text-emerald-700 hover:bg-emerald-50 dark:text-slate-300 dark:hover:text-emerald-300 dark:hover:bg-emerald-900/20'
                    : 'text-slate-400 dark:text-slate-500 cursor-not-allowed')
          ]"
          :disabled="!item.ready"
          :title="item.ready ? item.label : 'Đang phát triển'"
        >
          <i :class="['pi', item.icon, 'text-base']"></i>
          <span class="truncate">{{ item.label }}</span>
        </button>
      </nav>
    </Drawer>

    <!-- Main -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Topbar -->
      <header class="flex items-center justify-between bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 px-3 py-2">
        <div class="flex items-center gap-2">
          <Button icon="pi pi-bars" text rounded class="lg:!hidden" @click="showDrawer = true" />
          <span class="hidden sm:block text-sm">Xin chào, {{ page.props?.auth?.user?.name ?? 'User' }}</span>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="toggleDark"
            :title="isDark ? 'Chuyển Light' : 'Chuyển Dark'"
            class="h-9 w-9 inline-flex items-center justify-center rounded-full border border-slate-300 dark:border-slate-600 hover:bg-slate-100/60 dark:hover:bg-slate-700/60"
            aria-label="Dark / Light"
          >
            <i :class="[ isDark ? 'pi pi-sun' : 'pi pi-moon', 'text-base' ]" />
          </button>
          <Button label="Đăng xuất" icon="pi pi-sign-out" severity="secondary" outlined @click="logout" />
        </div>
      </header>

      <main class="flex-1 p-3 md:p-5">
        <slot />
      </main>
    </div>
  </div>
</template>
