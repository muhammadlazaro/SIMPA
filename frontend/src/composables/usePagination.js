import { computed } from 'vue'

/**
 * Composable untuk manajemen pagination yang reusable
 * Menghilangkan duplikasi logic getPageNumbers() di banyak komponen
 * @param {Ref<Object>} paginationRef - object pagination { currentPage, lastPage, perPage, total }
 * @returns {Object} - computed pages array
 */
export function usePagination(paginationRef) {
  const pageNumbers = computed(() => {
    if (!paginationRef || !paginationRef.value) return []
    
    const { currentPage = 1, lastPage = 1 } = paginationRef.value
    const maxVisible = 5
    const pages = []

    // Jika halaman total <= maxVisible, tampilkan semua
    if (lastPage <= maxVisible) {
      for (let i = 1; i <= lastPage; i++) {
        pages.push(i)
      }
      return pages
    }

    // Jika currentPage <= 3, tampilkan 1,2,3,4,...,lastPage
    if (currentPage <= 3) {
      for (let i = 1; i <= 4; i++) {
        pages.push(i)
      }
      pages.push('...')
      pages.push(lastPage)
      return pages
    }

    // Jika currentPage >= lastPage-2, tampilkan 1,...,lastPage-3,...,lastPage
    if (currentPage >= lastPage - 2) {
      pages.push(1)
      pages.push('...')
      for (let i = lastPage - 3; i <= lastPage; i++) {
        pages.push(i)
      }
      return pages
    }

    // Default: 1,...,currentPage-1,currentPage,currentPage+1,...,lastPage
    pages.push(1)
    pages.push('...')
    for (let i = currentPage - 1; i <= currentPage + 1; i++) {
      pages.push(i)
    }
    pages.push('...')
    pages.push(lastPage)
    return pages
  })

  return {
    pageNumbers,
  }
}
