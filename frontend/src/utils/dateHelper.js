import { warnDev } from './logger'

/**
 * Format date to Indonesian locale
 * @param {string|Date} date - Date to format
 * @param {boolean} includeTime - Whether to include time
 * @returns {string} Formatted date string
 */
export function formatDate(date, includeTime = true) {
  try {
    if (!date) return '-'
    
    // Handle various input types
    const d = typeof date === 'string' || typeof date === 'number' 
      ? new Date(date) 
      : date
    
    if (!d || Number.isNaN(d.getTime())) return '-'
    
    const months = [
      'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
      'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ]
    
    const day = d.getDate()
    const month = months[d.getMonth()]
    const year = d.getFullYear()
    
    // Add timezone indicator (WIB = UTC+7)
    const timezoneOffset = -d.getTimezoneOffset() / 60
    const timezone = timezoneOffset === 7 ? ' WIB' : ''
    
    if (!includeTime) {
      return `${day} ${month} ${year}`
    }
    
    const hours = String(d.getHours()).padStart(2, '0')
    const minutes = String(d.getMinutes()).padStart(2, '0')
    
    return `${day} ${month} ${year}, ${hours}:${minutes}${timezone}`
  } catch (error) {
    warnDev('[dateHelper] Invalid date format:', { date, error })
    return '-'
  }
}

/**
 * Format date to relative time (e.g., "2 jam yang lalu")
 * @param {string|Date} date - Date to format
 * @returns {string} Relative time string
 */
export function formatRelativeTime(date) {
  try {
    if (!date) return '-'
    
    const d = typeof date === 'string' || typeof date === 'number' 
      ? new Date(date) 
      : date
    
    if (!d || Number.isNaN(d.getTime())) return '-'
    
    const now = new Date()
    const diffMs = now - d
    
    // Handle future dates
    if (diffMs < 0) return 'Baru saja'
    
    const diffMins = Math.floor(diffMs / 60000)
    const diffHours = Math.floor(diffMs / 3600000)
    const diffDays = Math.floor(diffMs / 86400000)
    
    if (diffMins < 1) return 'Baru saja'
    if (diffMins < 60) return `${diffMins} menit yang lalu`
    if (diffHours < 24) return `${diffHours} jam yang lalu`
    if (diffDays < 7) return `${diffDays} hari yang lalu`
    if (diffDays < 30) {
      const weeks = Math.floor(diffDays / 7)
      return `${weeks} minggu yang lalu`
    }
    if (diffDays < 365) {
      const months = Math.floor(diffDays / 30)
      return `${months} bulan yang lalu`
    }
    const years = Math.floor(diffDays / 365)
    return `${years} tahun yang lalu`
  } catch (error) {
    warnDev('[dateHelper] Invalid date for relative time:', { date, error })
    return '-'
  }
}

