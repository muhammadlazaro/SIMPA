<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useNotificationStore } from '../stores/notifications'
import Icons from './Icons.vue'
import { ROLE_DISPLAY_NAME, getHomeByRole, getUserDisplayName } from '../constants/roles'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const notifStore = useNotificationStore()

const isCollapsed = ref(false)
const isMobile = ref(false)
const isMobileMenuOpen = ref(false)
const showNotifDropdown = ref(false)

const userDisplayName = computed(() => getUserDisplayName(auth.user, auth.role))

const menuItems = computed(() => {
  const role = auth.role || 'tim_implementasi_aplikasi'
  const basePath = getHomeByRole(role).path

  if (role === 'admin_sistem') {
    return [
      {
        name: 'Personil',
        icon: 'user',
        path: basePath,
        exact: false,
      },
    ]
  }

  if (role === 'unit_kerja') {
    return [
      {
        name: 'Pengajuan saya',
        titleCollapsed: 'Pengajuan aplikasi yang Anda kirim',
        icon: 'apps',
        path: basePath,
        activeWhen: (currentPath) => currentPath === basePath || currentPath.startsWith(`${basePath}/app/`),
      },
      {
        name: 'Pengajuan RFC',
        titleCollapsed: 'Pengajuan Request for Change dari unit kerja',
        icon: 'file-text',
        path: '/unit-kerja/rfc',
        exact: false,
      },
    ]
  }

  return [
    {
      name: 'Aplikasi',
      titleCollapsed: '',
      icon: 'apps',
      path: basePath,
      exact: false,
    },
  ]
})

function isActive(item) {
  if (typeof item.activeWhen === 'function') return item.activeWhen(route.path)
  if (item.exact) return route.path === item.path
  return route.path.startsWith(item.path)
}

function navigateTo(path) {
  router.push(path)
  if (isMobile.value) {
    isMobileMenuOpen.value = false
  }
}

async function handleLogout() {
  notifStore.reset()
  await auth.logout()
  router.push('/login')
}

function toggleSidebar() {
  if (isMobile.value) {
    isMobileMenuOpen.value = !isMobileMenuOpen.value
    return
  }
  isCollapsed.value = !isCollapsed.value
}

function syncMobileState() {
  isMobile.value = window.innerWidth <= 768
  if (isMobile.value) {
    isCollapsed.value = false
    isMobileMenuOpen.value = false
  }
}

function toggleNotifDropdown() {
  showNotifDropdown.value = !showNotifDropdown.value
}

function closeNotifDropdown() {
  showNotifDropdown.value = false
}

function handleNotifClick(notif) {
  notifStore.markRead(notif.id)
  if (notif.aplikasi_id) {
    const role = auth.role || ''
    if (role === 'admin_sistem') {
      router.push(getHomeByRole(role).path)
      closeNotifDropdown()
      return
    }
    let routeName = 'unit-kerja-app-detail'
    if (role === 'pengelola_aplikasi') routeName = 'pengelola-aplikasi-app-detail'
    else if (role === 'analis_desain') routeName = 'analis-desain-app-detail'
    else if (role === 'tim_uji_keamanan') routeName = 'tim-uji-keamanan-app-detail'
    else if (role === 'tim_implementasi_aplikasi') routeName = 'tim-implementasi-aplikasi-app-detail'
    else if (role === 'devops_developer') routeName = 'devops-developer-app-detail'
    router.push({ name: routeName, params: { id: notif.aplikasi_id } })
  }
  closeNotifDropdown()
}

function formatNotifTime(val) {
  if (!val) return ''
  const d = new Date(val)
  const diff = Date.now() - d.getTime()
  const min = Math.floor(diff / 60000)
  if (min < 1) return 'Baru saja'
  if (min < 60) return `${min} mnt lalu`
  const jam = Math.floor(min / 60)
  if (jam < 24) return `${jam} jam lalu`
  const hari = Math.floor(jam / 24)
  return `${hari} hari lalu`
}

onMounted(() => {
  syncMobileState()
  window.addEventListener('resize', syncMobileState)
  notifStore.startPolling(30000)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', syncMobileState)
  notifStore.stopPolling()
})
</script>

<template>
  <button
    v-if="isMobile && !isMobileMenuOpen"
    type="button"
    class="mobile-menu-btn"
    aria-label="Buka menu"
    @click="toggleSidebar"
  >
    <Icons name="list" :size="20" />
  </button>
  <div
    v-if="isMobile && isMobileMenuOpen"
    class="mobile-sidebar-backdrop"
    @click="toggleSidebar"
  ></div>
  <div :class="['sidebar', { collapsed: isCollapsed, 'mobile-closed': isMobile && !isMobileMenuOpen }]">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
      <div class="brand-block" :class="{ collapsed: isCollapsed }">
        <img v-if="!isCollapsed" src="/bssn.png" alt="Logo BSSN" class="brand-logo" />
        <div v-if="!isCollapsed" class="brand-text">
          <span class="brand-name">
            <span class="brand-name-line">Badan Siber</span>
            <span class="brand-name-line">dan Sandi</span>
            <span class="brand-name-line">Negara</span>
          </span>
        </div>
        <img v-else src="/bssn.png" alt="Logo BSSN" class="brand-logo brand-logo-collapsed" />
      </div>
      <button 
        @click="toggleSidebar" 
        class="toggle-btn"
        :title="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
      >
        <Icons :name="isCollapsed ? 'chevron-right' : 'chevron-left'" :size="16" />
      </button>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
      <button
        v-for="item in menuItems"
        :key="item.path"
        type="button"
        @click="navigateTo(item.path)"
        :class="['nav-item', { active: isActive(item) }]"
        :title="isCollapsed ? (item.titleCollapsed || item.name) : ''"
      >
        <Icons :name="item.icon" :size="20" />
        <span v-if="!isCollapsed" class="nav-text">{{ item.name }}</span>
      </button>
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
      <!-- Notification Bell -->
      <div class="notif-wrap" v-if="!isCollapsed">
        <button
          class="notif-btn"
          @click="toggleNotifDropdown"
          title="Notifikasi"
          type="button"
        >
          <!-- Bell icon -->
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
          </svg>
          <span v-if="!isCollapsed">Notifikasi</span>
          <span v-if="notifStore.unreadCount > 0" class="notif-badge">
            {{ notifStore.unreadCount > 99 ? '99+' : notifStore.unreadCount }}
          </span>
        </button>

        <!-- Dropdown -->
        <div v-if="showNotifDropdown" class="notif-dropdown">
          <div class="notif-dropdown-head">
            <span>Notifikasi</span>
            <button
              v-if="notifStore.unreadCount > 0"
              class="notif-read-all"
              @click="notifStore.markAllRead()"
            >Tandai semua dibaca</button>
          </div>
          <div class="notif-list">
            <div
              v-if="notifStore.notifications.length === 0"
              class="notif-empty"
            >Belum ada notifikasi.</div>
            <div
              v-for="n in notifStore.notifications"
              :key="n.id"
              :class="['notif-item', { unread: !n.is_read }]"
              @click="handleNotifClick(n)"
            >
              <div class="notif-dot" v-if="!n.is_read"></div>
              <div class="notif-content">
                <div class="notif-title">{{ n.title }}</div>
                <div class="notif-body">{{ n.body }}</div>
                <div class="notif-time">{{ formatNotifTime(n.created_at) }}</div>
              </div>
            </div>
          </div>
        </div>
        <!-- overlay to close -->
        <div v-if="showNotifDropdown" class="notif-overlay" @mousedown="closeNotifDropdown"></div>
      </div>

      <!-- Collapsed: just bell icon -->
      <button
        v-if="isCollapsed"
        class="notif-btn-collapsed"
        @click="toggleNotifDropdown"
        title="Notifikasi"
        type="button"
      >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <span v-if="notifStore.unreadCount > 0" class="notif-badge">{{ notifStore.unreadCount }}</span>
      </button>

      <div class="user-section">
        <div class="user-avatar">
          <Icons name="user" :size="20" />
        </div>
        <div v-if="!isCollapsed" class="user-info">
          <div class="user-name">{{ userDisplayName }}</div>
          <div class="user-role">{{ ROLE_DISPLAY_NAME[auth.role] || 'Pengguna' }}</div>
        </div>
      </div>

      <button
        @click="handleLogout"
        class="logout-btn"
        :title="isCollapsed ? 'Logout' : ''"
      >
        <Icons name="logout" :size="20" />
        <span v-if="!isCollapsed">Logout</span>
      </button>
    </div>
  </div>
</template>


<style scoped>
.sidebar {
  width: 240px;
  height: 100vh;
  background: linear-gradient(180deg, #1b2553 0%, #1e2d63 100%);
  border-right: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  flex-direction: column;
  position: sticky;
  top: 0;
  z-index: 1000;
  transition: width 0.2s ease;
}

.sidebar.collapsed {
  width: 72px;
}

/* Sidebar Header */
.sidebar-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 14px 12px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.12);
  min-height: 86px;
}

.brand-block {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.brand-logo {
  width: 52px;
  height: 52px;
  border-radius: 0;
  object-fit: contain;
  background: transparent;
  padding: 0;
  flex-shrink: 0;
}

.brand-logo-collapsed {
  width: 34px;
  height: 34px;
  border-radius: 7px;
}

.brand-text {
  display: flex;
  flex-direction: column;
  gap: 0;
  min-width: 0;
}

.brand-name {
  color: rgba(241, 245, 255, 0.85);
  font-family: var(--notion-font-brand);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.2px;
  text-transform: uppercase;
  display: flex;
  flex-direction: column;
  line-height: 1.2;
}

.brand-name-line {
  display: block;
}

.toggle-btn {
  background: rgba(255, 255, 255, 0.08);
  border: none;
  color: rgba(241, 245, 255, 0.9);
  cursor: pointer;
  padding: 6px;
  border-radius: 8px;
  transition: all 0.15s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 2px;
}

.toggle-btn:hover {
  background: rgba(255, 255, 255, 0.16);
  color: #fff;
}

.collapsed .toggle-btn {
  margin-left: auto;
  margin-right: auto;
}

/* Navigation */
.sidebar-nav {
  flex: 1;
  padding: 14px 8px;
  overflow-y: auto;
  overflow-x: hidden;
}

.nav-item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 12px;
  margin-bottom: 4px;
  background: none;
  border: none;
  border-radius: 10px;
  color: rgba(241, 245, 255, 0.9);
  cursor: pointer;
  transition: all 0.15s ease;
  font-size: 14px;
  font-weight: 500;
  text-align: left;
}

.nav-item:hover {
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
}

.nav-item.active {
  box-shadow: inset 3px 0 0 #facc15;
  background: rgba(255, 255, 255, 0.14);
  color: #facc15;
}

.nav-item.active:hover {
  background: rgba(255, 255, 255, 0.16);
}

.nav-text {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.collapsed .nav-item {
  justify-content: center;
  padding: 10px;
}

/* Sidebar Footer */
.sidebar-footer {
  padding: 12px 8px;
  border-top: 1px solid rgba(255, 255, 255, 0.12);
}

.user-section {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 12px;
  margin-bottom: 8px;
  border-radius: 10px;
  transition: background 0.15s ease;
}

.user-section:hover {
  background: rgba(255, 255, 255, 0.1);
}

.user-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.user-info {
  flex: 1;
  min-width: 0;
}

.user-name {
  font-size: 13px;
  font-weight: 600;
  color: #fff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-role {
  font-size: 12px;
  color: rgba(241, 245, 255, 0.8);
}

.collapsed .user-section {
  justify-content: center;
  padding: 8px;
}

.logout-btn {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  background: none;
  border: none;
  border-radius: 10px;
  color: #ffd7d7;
  cursor: pointer;
  transition: all 0.15s ease;
  font-size: 14px;
  font-weight: 500;
}

.logout-btn:hover {
  background: rgba(239, 68, 68, 0.2);
  color: #fff;
}

.collapsed .logout-btn {
  justify-content: center;
  padding: 10px;
}

/* Scrollbar */
.sidebar-nav::-webkit-scrollbar {
  width: 6px;
}

.sidebar-nav::-webkit-scrollbar-track {
  background: transparent;
}

.sidebar-nav::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.24);
  border-radius: 3px;
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.32);
}

/* Mobile Responsive */
@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    left: 0;
    z-index: 1000;
    width: min(82vw, 300px);
    box-shadow: 20px 0 40px rgba(15, 23, 42, 0.28);
    transition: transform 0.22s ease;
  }

  .sidebar.mobile-closed {
    transform: translateX(-100%);
    width: min(82vw, 300px);
  }
}

.mobile-menu-btn {
  display: none;
}

.mobile-sidebar-backdrop {
  display: none;
}

@media (max-width: 768px) {
  .mobile-menu-btn {
    position: fixed;
    top: 14px;
    left: 14px;
    z-index: 1200;
    width: 42px;
    height: 42px;
    border: 1px solid rgba(255, 255, 255, 0.28);
    border-radius: 12px;
    background: #1f3f93;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 24px rgba(31, 63, 147, 0.26);
  }

  .mobile-sidebar-backdrop {
    position: fixed;
    inset: 0;
    z-index: 999;
    display: block;
    background: rgba(15, 23, 42, 0.42);
    backdrop-filter: blur(2px);
  }
}

/* ===== NOTIFICATION BELL ===== */
.notif-wrap {
  position: relative;
  margin-bottom: 4px;
}

.notif-btn {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  background: none;
  border: none;
  border-radius: 10px;
  color: rgba(241, 245, 255, 0.9);
  cursor: pointer;
  font-size: 14px;
  font-weight: 500;
  transition: all 0.15s ease;
  position: relative;
}

.notif-btn:hover {
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
}

.notif-btn-collapsed {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  padding: 10px;
  background: none;
  border: none;
  border-radius: 10px;
  color: rgba(241, 245, 255, 0.9);
  cursor: pointer;
  position: relative;
  transition: background 0.15s ease;
  margin-bottom: 4px;
}

.notif-btn-collapsed:hover {
  background: rgba(255, 255, 255, 0.1);
}

.notif-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 999px;
  background: #ef4444;
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  margin-left: auto;
  flex-shrink: 0;
}

/* Dropdown */
.notif-dropdown {
  position: absolute;
  bottom: calc(100% + 6px);
  left: 0;
  width: 300px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.18);
  border: 1px solid rgba(228,224,213,0.8);
  z-index: 200;
  overflow: hidden;
  animation: notifFadeIn 0.15s ease;
}

@keyframes notifFadeIn {
  from { opacity: 0; transform: translateY(6px); }
  to   { opacity: 1; transform: translateY(0); }
}

.notif-dropdown-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px 10px;
  border-bottom: 1px solid #f0ece2;
  font-size: 13px;
  font-weight: 700;
  color: #2f2a23;
}

.notif-read-all {
  font-size: 11px;
  font-weight: 600;
  color: #1e3a8a;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
}

.notif-read-all:hover { text-decoration: underline; }

.notif-list {
  max-height: 320px;
  overflow-y: auto;
}

.notif-empty {
  padding: 24px 14px;
  text-align: center;
  font-size: 13px;
  color: #6f6a62;
}

.notif-item {
  display: flex;
  gap: 10px;
  padding: 10px 14px;
  cursor: pointer;
  border-bottom: 1px solid #f5f4ee;
  transition: background 0.12s ease;
  align-items: flex-start;
}

.notif-item:hover { background: #f5f4ee; }

.notif-item.unread { background: #eef2fb; }
.notif-item.unread:hover { background: #e4eaf8; }

.notif-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #1e3a8a;
  margin-top: 5px;
  flex-shrink: 0;
}

.notif-content { flex: 1; min-width: 0; }

.notif-title {
  font-size: 12px;
  font-weight: 700;
  color: #2f2a23;
  margin-bottom: 2px;
}

.notif-body {
  font-size: 12px;
  color: #4b4640;
  line-height: 1.4;
  margin-bottom: 4px;
}

.notif-time {
  font-size: 11px;
  color: #938d84;
}

.notif-overlay {
  position: fixed;
  inset: 0;
  z-index: 199;
}
</style>
