const DEFAULT_FILE_ERROR = 'File belum dapat digunakan. Periksa tipe dan ukurannya.'

/**
 * IDDS SingleFileUpload emits (file, null) for a valid selection and
 * (null, validationError) for a rejected selection. A separate (null, null)
 * event is emitted when the user removes the selected file.
 */
export function resolveIddsFileSelection(file, validation, fallbackError = DEFAULT_FILE_ERROR) {
  if (file) {
    return { file, error: '' }
  }

  if (validation?.isValid === false) {
    return {
      file: null,
      error: validation.error || fallbackError,
    }
  }

  return { file: null, error: '' }
}
