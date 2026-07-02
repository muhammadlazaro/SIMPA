import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '../lib/http'

export const useNotificationStore = defineStore('notifications', () => {
  const notifications = ref([])
  const unreadCount = ref(0)
  const loading = ref(false)
  let _pollInterval = null

  async function fetchNotifications() {
    loading.value = true
    try {
      const { data } = await http.get('/notifications')
      notifications.value = data?.data?.notifications || []
      unreadCount.value = data?.data?.unread_count || 0
    } catch {
      // Diam saja jika gagal (misal logout / 401)
    } finally {
      loading.value = false
    }
  }

  async function markRead(id) {
    try {
      await http.patch(`/notifications/${id}/read`)
      const target = notifications.value.find((item) => item.id === id)
      if (target && !target.is_read) {
        target.is_read = true
        unreadCount.value = Math.max(0, unreadCount.value - 1)
      }
    } catch { /* silent */ }
  }

  async function markAllRead() {
    try {
      await http.patch('/notifications/read-all')
      notifications.value.forEach((item) => { item.is_read = true })
      unreadCount.value = 0
    } catch { /* silent */ }
  }

  function startPolling(intervalMs = 30000) {
    if (_pollInterval) return // guard: cegah duplicate interval jika dipanggil lebih dari sekali
    fetchNotifications()
    _pollInterval = setInterval(fetchNotifications, intervalMs)
  }

  function stopPolling() {
    if (_pollInterval) {
      clearInterval(_pollInterval)
      _pollInterval = null
    }
  }

  /** Reset state - panggil saat logout agar data tidak tersisa di memory. */
  function reset() {
    stopPolling()
    notifications.value = []
    unreadCount.value = 0
  }

  return {
    notifications,
    unreadCount,
    loading,
    fetchNotifications,
    markRead,
    markAllRead,
    startPolling,
    stopPolling,
    reset,
  }
})
