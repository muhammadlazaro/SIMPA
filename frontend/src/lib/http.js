import axios from 'axios'
import { useAuthStore } from '../stores/auth'
import router from '../router'

const apiBase = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'

// Use unversioned API prefix. Backend is configured with apiPrefix 'api'.
export const http = axios.create({
  baseURL: `${apiBase}/api`,
})

http.interceptors.request.use((config) => {
  const auth = useAuthStore()
  if (auth.token) {
    config.headers.Authorization = `Bearer ${auth.token}`
  }
  return config
})

http.interceptors.response.use(
  (response) => response,
  (error) => {
    // Hanya 401 (Unauthenticated) yang harus clear session & redirect.
    // 403 (Forbidden) berarti user SUDAH login tapi tidak punya akses - jangan logout.
    if (error?.response?.status === 401) {
      const auth = useAuthStore()
      auth.clearSession()
      if (router.currentRoute.value.name !== 'login') {
        router.replace({ name: 'login' })
      }
    }
    return Promise.reject(error)
  }
)

export default http
