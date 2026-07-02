import { defineStore } from 'pinia'
import axios from 'axios'

const apiBase = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'

/** Namespace storage keys to avoid collisions with other apps on same origin. */
const STORAGE_KEY_TOKEN = 'smpa_token'
const STORAGE_KEY_USER = 'smpa_user'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem(STORAGE_KEY_TOKEN) || '',
    user: JSON.parse(localStorage.getItem(STORAGE_KEY_USER) || 'null'),
  }),
  getters: {
    isAuthenticated: (state) => !!state.token,
    role: (state) => state.user?.role || null,
  },
  actions: {
    setToken(token) {
      this.token = token
      if (token) {
        localStorage.setItem(STORAGE_KEY_TOKEN, token)
      } else {
        localStorage.removeItem(STORAGE_KEY_TOKEN)
      }
    },
    setUser(user) {
      this.user = user
      if (user) {
        localStorage.setItem(STORAGE_KEY_USER, JSON.stringify(user))
      } else {
        localStorage.removeItem(STORAGE_KEY_USER)
      }
    },
    /** Hapus sesi lokal saja (401, guard router, interceptor). */
    clearSession() {
      this.setToken('')
      this.setUser(null)
    },
    /**
     * Cabut token di server (Sanctum), lalu bersihkan sesi lokal.
     * Gagal jaringan/server tetap melanjutkan clear lokal.
     */
    async logout() {
      if (this.token) {
        try {
          await axios.post(`${apiBase}/api/logout`, null, {
            headers: {
              Authorization: `Bearer ${this.token}`,
            },
          })
        } catch {
          // Token mungkin sudah tidak valid atau offline - tetap lanjut clear lokal
        }
      }
      this.clearSession()
    },
  },
})
