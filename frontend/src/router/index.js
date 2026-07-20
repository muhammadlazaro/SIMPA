import { createRouter, createWebHistory } from 'vue-router'
import { ROLE_HOME_ROUTE, getHomeByRole } from '../constants/roles'
import http from '../lib/http'
import { useAuthStore } from '../stores/auth'
import { warnDev } from '../utils/logger'

const protectedRoutes = [
  {
    path: '/admin-sistem',
    name: 'admin-sistem',
    component: () => import('../views/AdminSistemDashboard.vue'),
    meta: { title: 'Kelola Personil' },
  },
  {
    path: '/pengelola-aplikasi',
    name: 'pengelola-aplikasi',
    component: () => import('../views/PengelolaDashboard.vue'),
    meta: { title: 'Kelola Aplikasi' },
  },
  {
    path: '/pengelola-aplikasi/rfc',
    name: 'pengelola-aplikasi-rfc',
    component: () => import('../views/PengelolaRfc.vue'),
    meta: { title: 'Daftar RFC' },
  },
  {
    path: '/pengelola-aplikasi/app/:id',
    name: 'pengelola-aplikasi-app-detail',
    component: () => import('../views/PengelolaAppDetail.vue'),
    meta: { title: 'Detail Aplikasi' },
  },
  {
    path: '/analis-desain',
    name: 'analis-desain',
    component: () => import('../views/AnalisDashboard.vue'),
    meta: { title: 'Antrian Analisis' },
  },
  {
    path: '/analis-desain/app/:id',
    name: 'analis-desain-app-detail',
    component: () => import('../views/AnalisAppDetail.vue'),
    meta: { title: 'Detail Analisis' },
  },
  {
    path: '/unit-kerja',
    name: 'unit-kerja',
    component: () => import('../views/UnitKerjaDashboard.vue'),
    meta: { title: 'Pengajuan Saya' },
  },
  {
    path: '/unit-kerja/rfc',
    name: 'unit-kerja-rfc',
    component: () => import('../views/UnitKerjaRfc.vue'),
    meta: { title: 'Pengajuan RFC' },
  },
  {
    path: '/unit-kerja/app/:id',
    name: 'unit-kerja-app-detail',
    component: () => import('../views/UnitKerjaAppDetail.vue'),
    meta: { title: 'Detail Pengajuan' },
  },
  {
    path: '/tim-uji-keamanan',
    name: 'tim-uji-keamanan',
    component: () => import('../views/UserDashboard.vue'),
    meta: { title: 'Uji Keamanan' },
  },
  {
    path: '/tim-uji-keamanan/app/:id',
    name: 'tim-uji-keamanan-app-detail',
    component: () => import('../views/TimUjiKeamananAppDetail.vue'),
    meta: { title: 'Detail Uji Keamanan' },
  },
  {
    path: '/tim-implementasi-aplikasi',
    name: 'tim-implementasi-aplikasi',
    component: () => import('../views/UserDashboard.vue'),
    meta: { title: 'Implementasi Aplikasi' },
  },
  {
    path: '/tim-implementasi-aplikasi/app/:id',
    name: 'tim-implementasi-aplikasi-app-detail',
    component: () => import('../views/UserAppDetail.vue'),
    meta: { title: 'Detail Implementasi' },
  },
  {
    path: '/devops-developer',
    name: 'devops-developer',
    component: () => import('../views/UserDashboard.vue'),
    meta: { title: 'Deployment Aplikasi' },
  },
  {
    path: '/devops-developer/app/:id',
    name: 'devops-developer-app-detail',
    component: () => import('../views/DevopsAppDetail.vue'),
    meta: { title: 'Detail Deployment' },
  },
]

const routes = [
  { path: '/', redirect: '/login' },
  {
    path: '/login',
    name: 'login',
    component: () => import('../views/Login.vue'),
    meta: { title: 'Masuk' },
  },
  {
    path: '/',
    component: () => import('../layouts/AppShell.vue'),
    children: protectedRoutes,
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('../views/NotFound.vue'),
    meta: { title: 'Halaman Tidak Ditemukan', public: true },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

const ACCESS_RULES = {
  'admin-sistem': ['admin_sistem'],
  'pengelola-aplikasi': ['pengelola_aplikasi'],
  'pengelola-aplikasi-rfc': ['pengelola_aplikasi'],
  'pengelola-aplikasi-app-detail': ['pengelola_aplikasi'],
  'analis-desain': ['analis_desain'],
  'analis-desain-app-detail': ['analis_desain'],
  'unit-kerja': ['unit_kerja'],
  'unit-kerja-rfc': ['unit_kerja'],
  'unit-kerja-app-detail': ['unit_kerja'],
  'tim-uji-keamanan': ['tim_uji_keamanan'],
  'tim-uji-keamanan-app-detail': ['tim_uji_keamanan'],
  'tim-implementasi-aplikasi': ['tim_implementasi_aplikasi'],
  'tim-implementasi-aplikasi-app-detail': ['tim_implementasi_aplikasi'],
  'devops-developer': ['devops_developer'],
  'devops-developer-app-detail': ['devops_developer']
}

function isKnownRole(role) {
  return !!role && Object.hasOwn(ROLE_HOME_ROUTE, role)
}

async function hydrateUserIfMissing(auth, routeName) {
  if (!auth.isAuthenticated || auth.user || routeName === 'login') {
    return true
  }

  try {
    const { data } = await http.get('/me')
    auth.setUser(data.data)
    return true
  } catch {
    auth.clearSession()
    return false
  }
}

function redirectToRoleHomeOrLogin(auth, role, next) {
  if (!isKnownRole(role)) {
    auth.clearSession()
    next({ name: 'login' })
    return true
  }

  next({ name: getHomeByRole(role).name })
  return true
}

router.beforeEach(async (to, from, next) => {
  try {
    const auth = useAuthStore()
    
    // If not authenticated and trying to access protected route
    if (!auth.isAuthenticated && to.name !== 'login' && !to.meta.public) {
      return next({ name: 'login' })
    }

    const hydrated = await hydrateUserIfMissing(auth, to.name)
    if (!hydrated) {
      return next({ name: 'login' })
    }

    const userRole = auth.role || null

    if (to.name === 'login' && auth.isAuthenticated) {
      return redirectToRoleHomeOrLogin(auth, userRole, next)
    }

    const routeName = typeof to.name === 'string' ? to.name : ''
    const allowedRoles = ACCESS_RULES[routeName]

    if (allowedRoles && !allowedRoles.includes(userRole)) {
      return redirectToRoleHomeOrLogin(auth, userRole, next)
    }

    next()
  } catch (error) {
    warnDev('[router] Router guard error:', error)
    // Fallback: redirect to login on error
    next({ name: 'login' })
  }
})

router.afterEach((to) => {
  const title = typeof to.meta.title === 'string' ? to.meta.title : ''
  document.title = title ? `${title} | SIMPA` : 'SIMPA'
})

export default router


