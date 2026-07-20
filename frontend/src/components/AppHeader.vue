<script setup>
import { Avatar, Button, Drawer, ThemeToggle } from '@idds/vue'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getHomeByRole, getUserDisplayName } from '../constants/roles'
import { useAuthStore } from '../stores/auth'
import { useNotificationStore } from '../stores/notifications'
import Icons from './Icons.vue'

const emit = defineEmits(['menu-toggle'])
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const notifications = useNotificationStore()

const showNotifications = ref(false)
const isMobile = ref(false)

const role = computed(() => auth.role || '')
const home = computed(() => getHomeByRole(role.value))
const userDisplayName = computed(() => getUserDisplayName(auth.user, role.value))
const initials = computed(() => userDisplayName.value
  .split(/\s+/)
  .filter(Boolean)
  .slice(0, 2)
  .map((part) => part[0]?.toUpperCase())
  .join('') || 'U')
const pageTitle = computed(() => String(route.meta.title || 'SIMPA'))
const notificationCount = computed(() => notifications.unreadCount)
const notificationItems = computed(() => notifications.notifications)
const drawerPosition = computed(() => (isMobile.value ? 'bottom' : 'right'))
const drawerWidth = computed(() => (isMobile.value ? '100%' : '460px'))
const drawerHeight = computed(() => (isMobile.value ? '80vh' : '100%'))

function syncViewport() {
  isMobile.value = window.innerWidth <= 768
}

function formatNotificationTime(value) {
  if (!value) return ''
  const minutes = Math.floor((Date.now() - new Date(value).getTime()) / 60000)
  if (minutes < 1) return 'Baru saja'
  if (minutes < 60) return `${minutes} menit lalu`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours} jam lalu`
  return `${Math.floor(hours / 24)} hari lalu`
}

async function openNotification(notification) {
  await notifications.markRead(notification.id)
  const detailRouteByRole = {
    pengelola_aplikasi: 'pengelola-aplikasi-app-detail',
    analis_desain: 'analis-desain-app-detail',
    unit_kerja: 'unit-kerja-app-detail',
    tim_uji_keamanan: 'tim-uji-keamanan-app-detail',
    tim_implementasi_aplikasi: 'tim-implementasi-aplikasi-app-detail',
    devops_developer: 'devops-developer-app-detail',
  }
  const routeName = detailRouteByRole[role.value]
  if (notification.aplikasi_id && routeName) {
    await router.push({
      name: routeName,
      params: { id: notification.aplikasi_id },
      query: { tab: 'catatan' },
    })
  }
  showNotifications.value = false
}

onMounted(() => {
  syncViewport()
  window.addEventListener('resize', syncViewport)
  notifications.startPolling(30000)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', syncViewport)
  notifications.stopPolling()
})
</script>

<template>
  <header class="app-header">
    <div class="app-header-context">
      <button type="button" class="header-icon-button mobile-menu-button" aria-label="Buka menu navigasi" @click="emit('menu-toggle')">
        <Icons name="menu" :size="20" />
      </button>
      <nav class="header-breadcrumb" aria-label="Lokasi halaman">
        <RouterLink :to="home.path">Dashboard</RouterLink>
        <Icons name="chevron-right" :size="16" />
        <span aria-current="page">{{ pageTitle }}</span>
      </nav>
      <strong class="mobile-page-title">{{ pageTitle }}</strong>
    </div>

    <div class="app-header-actions">
      <button
        type="button"
        class="header-icon-button"
        aria-label="Buka notifikasi"
        :aria-expanded="showNotifications"
        @click="showNotifications = true"
      >
        <Icons name="bell" :size="20" />
        <span v-if="notificationCount" class="header-notification-badge">{{ notificationCount > 99 ? '99+' : notificationCount }}</span>
      </button>
      <ThemeToggle size="sm" :show-label="false" />

      <div class="header-avatar" :title="userDisplayName">
        <Avatar :initials="initials" :alt="userDisplayName" :size="40" />
      </div>
    </div>
  </header>

  <Drawer
    :model-value="showNotifications"
    title="Notifikasi"
    :description="`${notificationCount} belum dibaca`"
    :position="drawerPosition"
    :width="drawerWidth"
    :height="drawerHeight"
    close-label="Tutup notifikasi"
    panel-class-name="simpa-notification-drawer"
    @update:model-value="showNotifications = $event"
  >
    <div class="notification-toolbar">
      <Button v-if="notificationCount" hierarchy="link" size="sm" @click="notifications.markAllRead()">
        Tandai semua dibaca
      </Button>
    </div>
    <div class="notification-list">
      <div v-if="!notificationItems.length" class="notification-empty">
        <Icons name="bell" :size="48" />
        <strong>Belum ada notifikasi</strong>
        <span>Pembaruan proses aplikasi akan ditampilkan di sini.</span>
      </div>
      <template v-else>
        <button
          v-for="notification in notificationItems"
          :key="notification.id"
          type="button"
          :class="['notification-item', { unread: !notification.read_at }]"
          @click="openNotification(notification)"
        >
          <span class="notification-item-icon"><Icons name="bell" :size="18" /></span>
          <span class="notification-item-copy">
            <strong>{{ notification.title }}</strong>
            <span>{{ notification.body }}</span>
            <small>{{ formatNotificationTime(notification.created_at) }}</small>
          </span>
        </button>
      </template>
    </div>
  </Drawer>
</template>

<style scoped>
.app-header {
  position: sticky;
  z-index: 30;
  top: 0;
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 0 28px;
  border-bottom: 1px solid var(--ina-stroke-primary);
  background: color-mix(in srgb, var(--ina-background-primary) 96%, transparent);
  backdrop-filter: blur(10px);
}

.app-header-context,
.app-header-actions,
.header-breadcrumb {
  display: flex;
  align-items: center;
}

.header-breadcrumb {
  gap: 8px;
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

.header-breadcrumb a {
  color: var(--ina-content-primary);
  font-weight: var(--idds-weight-medium);
  text-decoration: none;
}

.header-breadcrumb a:hover {
  color: var(--ina-primary-primary);
  text-decoration: underline;
}

.app-header-actions {
  gap: 8px;
}

.header-icon-button {
  position: relative;
  width: 40px;
  height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 1px solid transparent;
  border-radius: var(--ina-radius-lg);
  color: var(--ina-content-primary);
  background: transparent;
  cursor: pointer;
}

.header-icon-button:hover {
  background: var(--ina-background-secondary);
}

.header-notification-badge {
  position: absolute;
  top: 3px;
  right: 2px;
  min-width: 16px;
  height: 16px;
  display: grid;
  place-items: center;
  padding: 0 4px;
  border: 2px solid var(--ina-background-primary);
  border-radius: 999px;
  color: #fff;
  background: var(--ina-negative-600);
  font-size: var(--idds-caption-xs-size);
  font-weight: var(--idds-weight-semibold);
  line-height: var(--idds-caption-xs-line);
}

.header-avatar {
  display: inline-flex;
  margin-left: 4px;
}

.mobile-menu-button,
.mobile-page-title {
  display: none;
}

.notification-toolbar {
  min-height: 36px;
  display: flex;
  justify-content: flex-end;
}

.notification-list {
  display: grid;
  gap: 4px;
}

.notification-empty {
  min-height: 260px;
  display: grid;
  place-items: center;
  align-content: center;
  gap: 8px;
  color: var(--ina-content-secondary);
  text-align: center;
}

.notification-empty strong {
  color: var(--ina-content-primary);
}

.notification-item {
  width: 100%;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px;
  border: 0;
  border-radius: var(--ina-radius-lg);
  color: var(--ina-content-primary);
  background: transparent;
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.notification-item:hover,
.notification-item.unread {
  background: var(--ina-primary-50);
}

.notification-item-icon {
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  color: var(--ina-primary-primary);
  background: var(--ina-primary-100);
}

.notification-item-copy {
  min-width: 0;
  display: grid;
  gap: 3px;
}

.notification-item-copy strong {
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

.notification-item-copy span,
.notification-item-copy small {
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-small-size);
  line-height: var(--idds-caption-small-line);
}

@media (max-width: 768px) {
  .app-header {
    height: 56px;
    padding-inline: 12px;
  }

  .mobile-menu-button,
  .mobile-page-title {
    display: inline-flex;
  }

  .mobile-page-title {
    margin-left: 8px;
    font-size: var(--idds-body-small-size);
    line-height: var(--idds-body-small-line);
  }

  .header-breadcrumb,
  .app-header-actions > :deep(.ina-theme-toggle) {
    display: none;
  }
}
</style>
