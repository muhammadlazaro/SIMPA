<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { ROLE_DISPLAY_NAME, getUserDisplayName } from '../constants/roles'
import Icons from './Icons.vue'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const isCollapsed = ref(false)
const isMobile = ref(false)
const isMobileMenuOpen = ref(false)
const showNotifDropdown = ref(false)

const props = defineProps({
  pengajuanUnitKerjaCount: {
    type: Number,
    default: 0
  }
})

const roleLabel = computed(() =>
  ROLE_DISPLAY_NAME[auth.role] ?? auth.role ?? 'Pengelola Aplikasi'
)

const userDisplayName = computed(() => getUserDisplayName(auth.user, auth.role))

const menuItems = [
  { 
    name: 'Aplikasi', 
    icon: 'apps', 
    path: '/pengelola-aplikasi',
    exact: false,
    excludePaths: ['/pengelola-aplikasi/rfc'],
    badgeKey: 'apps'
  },
  {
    name: 'Daftar RFC',
    icon: 'list',
    path: '/pengelola-aplikasi/rfc',
    exact: false,
    badgeKey: null
  }
]

function isActive(item) {
  if (item.excludePaths?.some((excludedPath) => route.path.startsWith(excludedPath))) {
    return false
  }

  if (item.exact) {
    return route.path === item.path
  }

  return route.path.startsWith(item.path)
}

function navigateTo(path) {
  router.push(path)
  if (isMobile.value) {
    isMobileMenuOpen.value = false
  }
}

async function handleLogout() {
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

onMounted(() => {
  syncMobileState()
  window.addEventListener('resize', syncMobileState)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', syncMobileState)
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
        @click="navigateTo(item.path)"
        :class="['nav-item', { active: isActive(item) }]"
        :title="isCollapsed ? item.name : ''"
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
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
          </svg>
          <span>Notifikasi</span>
          <span v-if="pengajuanUnitKerjaCount > 0" class="notif-badge">
            {{ pengajuanUnitKerjaCount > 99 ? '99+' : pengajuanUnitKerjaCount }}
          </span>
        </button>

        <!-- Dropdown -->
        <div v-if="showNotifDropdown" class="notif-dropdown">
          <div class="notif-dropdown-head">
            <span>Notifikasi</span>
          </div>
          <div class="notif-list">
            <div v-if="pengajuanUnitKerjaCount === 0" class="notif-empty">
              Belum ada notifikasi.
            </div>
            <div v-else class="notif-item unread" @click="router.push('/pengelola-aplikasi'); closeNotifDropdown()">
              <div class="notif-dot"></div>
              <div class="notif-content">
                <div class="notif-title">{{ pengajuanUnitKerjaCount }} pengajuan baru</div>
                <div class="notif-body">Ada pengajuan dari unit kerja yang perlu ditinjau.</div>
              </div>
            </div>
          </div>
        </div>
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
        <span v-if="pengajuanUnitKerjaCount > 0" class="notif-badge-collapsed">
          {{ pengajuanUnitKerjaCount > 99 ? '99+' : pengajuanUnitKerjaCount }}
        </span>
      </button>

      <div class="user-section">
        <div class="user-avatar">
          <Icons name="user" :size="20" />
        </div>
        <div v-if="!isCollapsed" class="user-info">
          <div class="user-name">{{ userDisplayName }}</div>
          <div class="user-role">{{ roleLabel }}</div>
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
  position: relative;
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

.nav-badge {
  margin-left: auto;
  flex-shrink: 0;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  border-radius: 10px;
  background: var(--notion-red);
  color: #fff;
  font-size: 11px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

.collapsed .nav-badge {
  margin-left: 0;
  position: absolute;
  top: 4px;
  right: 6px;
  min-width: 18px;
  height: 18px;
  font-size: 10px;
  padding: 0 4px;
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
  flex: 1;
  min-width: 0;
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

/* Notification */
.notif-wrap {
  position: relative;
  margin-bottom: 8px;
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
  transition: all 0.15s ease;
  font-size: 14px;
  font-weight: 500;
}

.notif-btn:hover {
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
}

.notif-badge {
  margin-left: auto;
  flex-shrink: 0;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  border-radius: 10px;
  background: var(--notion-red);
  color: #fff;
  font-size: 11px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

.notif-btn-collapsed {
  position: relative;
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
  transition: all 0.15s ease;
  margin-bottom: 8px;
}

.notif-btn-collapsed:hover {
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
}

.notif-badge-collapsed {
  position: absolute;
  top: 2px;
  right: 6px;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: 8px;
  background: var(--notion-red);
  color: #fff;
  font-size: 10px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.notif-dropdown {
  position: absolute;
  bottom: 100%;
  left: 0;
  width: 280px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
  z-index: 1100;
  margin-bottom: 8px;
  overflow: hidden;
}

.notif-dropdown-head {
  padding: 12px 16px;
  font-weight: 600;
  font-size: 14px;
  color: var(--notion-text);
  border-bottom: 1px solid var(--notion-border);
}

.notif-list {
  max-height: 260px;
  overflow-y: auto;
}

.notif-empty {
  padding: 24px 16px;
  text-align: center;
  color: var(--notion-text-secondary);
  font-size: 13px;
}

.notif-item {
  display: flex;
  gap: 10px;
  padding: 12px 16px;
  cursor: pointer;
  transition: background 0.1s;
}

.notif-item:hover {
  background: #f5f5f5;
}

.notif-item.unread {
  background: #f0f4ff;
}

.notif-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--notion-navy);
  flex-shrink: 0;
  margin-top: 6px;
}

.notif-content {
  flex: 1;
  min-width: 0;
}

.notif-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--notion-text);
  margin-bottom: 2px;
}

.notif-body {
  font-size: 12px;
  color: var(--notion-text-secondary);
  line-height: 1.4;
}

.notif-overlay {
  position: fixed;
  inset: 0;
  z-index: 1099;
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
</style>
