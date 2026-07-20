<script setup>
import { Avatar } from '@idds/vue'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ROLE_DISPLAY_NAME, getHomeByRole, getUserDisplayName } from '../constants/roles'
import { useAuthStore } from '../stores/auth'
import { useNotificationStore } from '../stores/notifications'
import Icons from './Icons.vue'

const emit = defineEmits(['mobile-change'])

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const notifications = useNotificationStore()
const SIDEBAR_STORAGE_KEY = 'simpa_sidebar_collapsed'
const MOBILE_BREAKPOINT = 768

const isCollapsed = ref(localStorage.getItem(SIDEBAR_STORAGE_KEY) === 'true')
const isMobile = ref(false)
const isMobileMenuOpen = ref(false)
const accountMenuOpen = ref(false)
const accountArea = ref(null)

const role = computed(() => auth.role || '')
const homePath = computed(() => getHomeByRole(role.value).path)
const userDisplayName = computed(() => getUserDisplayName(auth.user, role.value))
const roleDisplayName = computed(() => ROLE_DISPLAY_NAME[role.value] || 'Pengguna')
const initials = computed(() => userDisplayName.value
  .split(/\s+/)
  .filter(Boolean)
  .slice(0, 2)
  .map((part) => part[0]?.toUpperCase())
  .join('') || 'U')

const roleMenu = {
  admin_sistem: [
    { label: 'Personil', icon: 'user', to: '/admin-sistem' },
  ],
  pengelola_aplikasi: [
    {
      label: 'Aplikasi', icon: 'apps', to: '/pengelola-aplikasi',
      active: (path) => path === '/pengelola-aplikasi' || path.startsWith('/pengelola-aplikasi/app/'),
    },
    { label: 'Daftar RFC', icon: 'list', to: '/pengelola-aplikasi/rfc' },
  ],
  analis_desain: [
    { label: 'Antrian analisis', icon: 'design', to: '/analis-desain' },
  ],
  unit_kerja: [
    {
      label: 'Pengajuan saya', icon: 'apps', to: '/unit-kerja',
      active: (path) => path === '/unit-kerja' || path.startsWith('/unit-kerja/app/'),
    },
    { label: 'Pengajuan RFC', icon: 'file-text', to: '/unit-kerja/rfc' },
  ],
  tim_uji_keamanan: [
    { label: 'Uji keamanan', icon: 'shield', to: '/tim-uji-keamanan' },
  ],
  tim_implementasi_aplikasi: [
    { label: 'Implementasi', icon: 'code', to: '/tim-implementasi-aplikasi' },
  ],
  devops_developer: [
    { label: 'Deployment', icon: 'server', to: '/devops-developer' },
  ],
}

const menuItems = computed(() => roleMenu[role.value] || [])

function isItemActive(item) {
  if (item.active) return item.active(route.path)
  return route.path === item.to || route.path.startsWith(`${item.to}/`)
}

function toggleSidebar() {
  if (isMobile.value) {
    isMobileMenuOpen.value = !isMobileMenuOpen.value
    emit('mobile-change', isMobileMenuOpen.value)
    return
  }

  isCollapsed.value = !isCollapsed.value
  localStorage.setItem(SIDEBAR_STORAGE_KEY, String(isCollapsed.value))
}

function syncViewport() {
  const mobile = window.innerWidth <= MOBILE_BREAKPOINT
  if (mobile !== isMobile.value) {
    isMobile.value = mobile
    isMobileMenuOpen.value = false
    emit('mobile-change', false)
  }
}

function closeMobileMenu() {
  if (!isMobile.value || !isMobileMenuOpen.value) return
  isMobileMenuOpen.value = false
  emit('mobile-change', false)
}

function closeAccountOnOutsideClick(event) {
  if (!accountMenuOpen.value || accountArea.value?.contains(event.target)) return
  accountMenuOpen.value = false
}

async function handleLogout() {
  accountMenuOpen.value = false
  notifications.reset()
  await auth.logout()
  await router.push('/login')
}

watch(() => route.fullPath, () => {
  closeMobileMenu()
  accountMenuOpen.value = false
})
watch(isMobileMenuOpen, (open) => document.body.classList.toggle('ui-drawer-open', open))

onMounted(() => {
  syncViewport()
  window.addEventListener('resize', syncViewport)
  document.addEventListener('pointerdown', closeAccountOnOutsideClick)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', syncViewport)
  document.removeEventListener('pointerdown', closeAccountOnOutsideClick)
  document.body.classList.remove('ui-drawer-open')
})

defineExpose({ toggleSidebar })
</script>

<template>
  <button
    v-if="isMobile && isMobileMenuOpen"
    type="button"
    class="sidebar-backdrop"
    aria-label="Tutup menu navigasi"
    @click="toggleSidebar"
  />

  <aside
    :class="['app-sidebar', { collapsed: isCollapsed, 'mobile-open': isMobileMenuOpen }]"
    aria-label="Navigasi utama"
  >
    <div class="sidebar-header">
      <RouterLink :to="homePath" class="brand" :aria-label="isCollapsed ? 'Beranda SIMPA' : undefined">
        <img src="/bssn.png" alt="" class="brand-logo" />
        <span v-if="!isCollapsed || isMobile" class="brand-copy">
          <strong>Badan Siber dan Sandi Negara</strong>
          <span>SIMPA</span>
        </span>
      </RouterLink>
      <button
        type="button"
        class="collapse-toggle"
        :aria-label="isCollapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'"
        :title="isCollapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'"
        @click="toggleSidebar"
      >
        <Icons :name="isCollapsed ? 'sidebar-expand' : 'sidebar-collapse'" :size="20" />
      </button>
    </div>

    <nav class="sidebar-navigation" aria-label="Menu utama">
      <RouterLink
        v-for="item in menuItems"
        :key="item.to"
        :to="item.to"
        :class="['sidebar-link', { active: isItemActive(item) }]"
        :aria-current="isItemActive(item) ? 'page' : undefined"
        :title="isCollapsed ? item.label : undefined"
      >
        <Icons :name="item.icon" :size="20" />
        <span v-if="!isCollapsed">{{ item.label }}</span>
      </RouterLink>
    </nav>

    <div ref="accountArea" class="sidebar-account">
      <button
        type="button"
        class="sidebar-account-trigger"
        aria-haspopup="menu"
        :aria-expanded="accountMenuOpen"
        :title="isCollapsed ? `${userDisplayName} - ${roleDisplayName}` : undefined"
        @click="accountMenuOpen = !accountMenuOpen"
      >
        <Avatar :initials="initials" :alt="userDisplayName" :size="40" />
        <span v-if="!isCollapsed || isMobile" class="sidebar-account-copy">
          <strong>{{ userDisplayName }}</strong>
          <span>{{ roleDisplayName }}</span>
        </span>
        <Icons v-if="!isCollapsed || isMobile" name="ellipsis-vertical" :size="20" />
      </button>
      <div v-if="accountMenuOpen" class="sidebar-account-menu" role="menu">
        <button type="button" role="menuitem" @click="handleLogout">
          <Icons name="logout" :size="20" />
          Keluar
        </button>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.app-sidebar {
  position: sticky;
  z-index: 40;
  top: 0;
  width: 248px;
  height: 100vh;
  flex: 0 0 248px;
  display: flex;
  flex-direction: column;
  border-right: 1px solid var(--ina-stroke-primary);
  color: #fff;
  background: linear-gradient(180deg, var(--ui-sidebar) 0%, var(--ui-sidebar-2) 100%);
  transition: width 180ms ease, flex-basis 180ms ease, transform 180ms ease;
}

.app-sidebar.collapsed {
  width: 72px;
  flex-basis: 72px;
}

.sidebar-header {
  height: 64px;
  flex: 0 0 64px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 10px 12px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.14);
}

.brand {
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 12px;
  color: inherit;
  text-decoration: none;
}

.brand-logo {
  width: 40px;
  height: 40px;
  flex: 0 0 40px;
  object-fit: contain;
}

.brand-copy {
  min-width: 0;
  display: grid;
  gap: 2px;
  line-height: var(--idds-caption-small-line);
}

.brand-copy strong {
  max-width: 160px;
  font-size: var(--idds-caption-small-size);
  font-weight: var(--idds-weight-semibold);
  text-transform: uppercase;
  line-height: var(--idds-caption-small-line);
}

.brand-copy span {
  color: rgba(255, 255, 255, 0.68);
  font-size: var(--idds-caption-small-size);
  line-height: var(--idds-caption-small-line);
}

.sidebar-navigation {
  flex: 1;
  display: grid;
  align-content: start;
  gap: 4px;
  padding: 16px 12px;
  overflow-y: auto;
}

.sidebar-link,
.collapse-toggle {
  min-height: 44px;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border: 0;
  border-radius: var(--ina-radius-lg);
  color: rgba(255, 255, 255, 0.78);
  background: transparent;
  font: inherit;
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-medium);
  text-decoration: none;
  cursor: pointer;
  line-height: var(--idds-caption-line);
}

.sidebar-link svg,
.collapse-toggle svg {
  flex: 0 0 20px;
}

.sidebar-link:hover,
.collapse-toggle:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.1);
}

.sidebar-link.active {
  color: #fff;
  background: rgba(255, 255, 255, 0.14);
  box-shadow: inset 3px 0 0 var(--ina-warning-400);
  font-weight: var(--idds-weight-semibold);
}

.collapse-toggle {
  width: 36px;
  min-width: 36px;
  min-height: 36px;
  justify-content: center;
  padding: 0;
  border: 1px solid rgba(255, 255, 255, 0.22);
}

.collapsed .sidebar-header {
  justify-content: center;
  padding-inline: 10px;
}

.collapsed .sidebar-header .brand {
  display: none;
}

.collapsed .sidebar-navigation {
  padding-inline: 10px;
}

.collapsed .sidebar-link {
  justify-content: center;
  padding-inline: 0;
}

.sidebar-account {
  position: relative;
  padding: 12px;
  border-top: 1px solid rgba(255, 255, 255, 0.14);
}

.sidebar-account-trigger {
  width: 100%;
  min-width: 0;
  min-height: 48px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 4px;
  border: 0;
  border-radius: var(--ina-radius-lg);
  color: #fff;
  background: transparent;
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.sidebar-account-trigger:hover {
  background: rgba(255, 255, 255, 0.1);
}

.sidebar-account-copy {
  min-width: 0;
  flex: 1;
  display: grid;
  gap: 1px;
}

.sidebar-account-copy strong,
.sidebar-account-copy span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sidebar-account-copy strong {
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-semibold);
  line-height: var(--idds-caption-line);
}

.sidebar-account-copy span {
  color: rgba(255, 255, 255, 0.68);
  font-size: var(--idds-caption-small-size);
  line-height: var(--idds-caption-small-line);
}

.sidebar-account-menu {
  position: absolute;
  z-index: 5;
  right: 12px;
  bottom: calc(100% + 4px);
  left: 12px;
  padding: 8px;
  border: 1px solid var(--ina-stroke-primary);
  border-radius: var(--ina-radius-xl);
  color: var(--ina-content-primary);
  background: var(--ina-background-primary);
  box-shadow: var(--ina-shadow-lg);
}

.sidebar-account-menu button {
  width: 100%;
  min-height: 40px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border: 0;
  border-radius: var(--ina-radius-lg);
  color: inherit;
  background: transparent;
  font: inherit;
  font-size: var(--idds-caption-size);
  cursor: pointer;
  line-height: var(--idds-caption-line);
}

.sidebar-account-menu button:hover {
  background: var(--ina-background-secondary);
}

.collapsed .sidebar-account {
  padding-inline: 10px;
}

.collapsed .sidebar-account-trigger {
  justify-content: center;
  padding-inline: 0;
}

.collapsed .sidebar-account-menu {
  right: auto;
  bottom: 12px;
  left: calc(100% + 8px);
  width: 160px;
}

.sidebar-backdrop {
  display: none;
}

@media (max-width: 768px) {
  .app-sidebar,
  .app-sidebar.collapsed {
    position: fixed;
    inset: 0 auto 0 0;
    width: min(280px, 86vw);
    height: 100dvh;
    flex-basis: auto;
    transform: translateX(-100%);
  }

  .app-sidebar.mobile-open {
    transform: translateX(0);
  }

  .sidebar-header {
    height: 56px;
    flex-basis: 56px;
    padding-block: 8px;
  }

  .app-sidebar.collapsed .brand-copy,
  .app-sidebar.collapsed .sidebar-link span,
  .app-sidebar.collapsed .sidebar-account-copy,
  .app-sidebar.collapsed .sidebar-account-trigger > svg {
    display: initial;
  }

  .app-sidebar.collapsed .sidebar-header,
  .app-sidebar.collapsed .sidebar-link {
    justify-content: flex-start;
  }

  .app-sidebar.collapsed .sidebar-header .brand {
    display: flex;
  }

  .app-sidebar.collapsed .sidebar-account-trigger {
    justify-content: flex-start;
  }

  .app-sidebar.collapsed .sidebar-account-menu {
    right: 12px;
    bottom: calc(100% + 4px);
    left: 12px;
    width: auto;
  }

  .sidebar-backdrop {
    position: fixed;
    z-index: 35;
    inset: 0;
    display: block;
    border: 0;
    background: rgba(15, 23, 42, 0.48);
  }
}
</style>
