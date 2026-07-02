export const ROLE_HOME_ROUTE = {
  admin_sistem: { name: 'admin-sistem', path: '/admin-sistem' },
  pengelola_aplikasi: { name: 'pengelola-aplikasi', path: '/pengelola-aplikasi' },
  analis_desain: { name: 'analis-desain', path: '/analis-desain' },
  unit_kerja: { name: 'unit-kerja', path: '/unit-kerja' },
  tim_uji_keamanan: { name: 'tim-uji-keamanan', path: '/tim-uji-keamanan' },
  tim_implementasi_aplikasi: { name: 'tim-implementasi-aplikasi', path: '/tim-implementasi-aplikasi' },
  devops_developer: { name: 'devops-developer', path: '/devops-developer' }
}

export const ROLE_DISPLAY_NAME = {
  admin_sistem: 'Admin Sistem',
  pengelola_aplikasi: 'Pengelola Aplikasi',
  analis_desain: 'Analis Desain',
  unit_kerja: 'Unit Kerja',
  tim_uji_keamanan: 'Tim Uji Keamanan',
  tim_implementasi_aplikasi: 'Tim Implementasi Aplikasi',
  devops_developer: 'DevOps Developer'
}

export function getHomeByRole(role) {
  return ROLE_HOME_ROUTE[role] || ROLE_HOME_ROUTE.tim_implementasi_aplikasi
}

export function getUserDisplayName(user, role) {
  const fallback = ROLE_DISPLAY_NAME[role] || 'Pengguna'
  const rawName = typeof user?.name === 'string' ? user.name.trim() : ''

  if (!rawName) return fallback
  if (role === 'admin_sistem') return rawName

  const legacyPrefix = ['ad', 'min'].join('')
  const cleanName = rawName
    .replace(new RegExp(`^${legacyPrefix}\\s+`, 'i'), '')
    .replace(/\s+User$/i, '')
    .trim()

  if (!cleanName || cleanName.toLowerCase() === 'user') return fallback
  return cleanName
}
