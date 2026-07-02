/**
 * Utilitas logging; hanya aktif di development mode.
 *
 * @param {string} msg - Pesan kontekstual, misal '[NamaKomponen] aksi gagal:'
 * @param {unknown} [err] - Objek error opsional
 */
export function warnDev(msg, err) {
  if (import.meta.env.DEV) {
    console.warn(msg, err)
  }
}
