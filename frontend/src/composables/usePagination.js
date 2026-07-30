import { computed } from 'vue'

/**
 * Composable untuk manajemen pagination yang reusable
 * Menghilangkan duplikasi logic getPageNumbers() di banyak komponen
 * @param {Ref<Object>} paginationRef - object pagination { currentPage, lastPage, perPage, total }
 * @returns {Object} - computed pages array
 */
export function usePagination(paginationRef) {
  const pageNumbers = computed(() => {
    if (!paginationRef?.value) return []
    
    const { currentPage = 1, lastPage = 1 } = paginationRef.value
    const maxVisible = 5
    // Jika halaman total <= maxVisible, tampilkan semua
    if (lastPage <= maxVisible) {
      return Array.from({ length: lastPage }, (_, index) => index + 1)
    }

    // Jika currentPage <= 3, tampilkan 1,2,3,4,...,lastPage
    if (currentPage <= 3) {
      return [1, 2, 3, 4, '...', lastPage]
    }

    // Jika currentPage >= lastPage-2, tampilkan 1,...,lastPage-3,...,lastPage
    if (currentPage >= lastPage - 2) {
      const trailingPages = Array.from(
        { length: 4 },
        (_, index) => lastPage - 3 + index,
      )
      return [1, '...', ...trailingPages]
    }

    // Default: 1,...,currentPage-1,currentPage,currentPage+1,...,lastPage
    return [1, '...', currentPage - 1, currentPage, currentPage + 1, '...', lastPage]
  })

  return {
    pageNumbers,
  }
}
