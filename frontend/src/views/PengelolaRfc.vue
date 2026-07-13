<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRouter } from 'vue-router'
import http from '../lib/http'
import PengelolaLayout from '../layouts/PengelolaLayout.vue'
import DataCardHead from '../components/DataCardHead.vue'
import DataTable from '../components/DataTable.vue'
import Icons from '../components/Icons.vue'
import { useToastStore } from '../stores/toast'
import { usePagination } from '../composables/usePagination.js'
import { warnDev } from '../utils/logger'
import { getRfcStatusBadgeClass } from '../constants/status'

const router = useRouter()
const toast = useToastStore()


const rfcs = ref([])
const loading = ref(false)
const search = ref('')
const hasActiveSearch = computed(() => !!search.value?.trim())
const pagination = ref({ currentPage: 1, lastPage: 1, perPage: 10, total: 0 })
const { pageNumbers: rfcPageNumbers } = usePagination(pagination)

const appsActive = ref([])
const showForm = ref(false)
const savingRfc = ref(false)
const editing = ref(null)
const rfcStep = ref(1)
const selectedApp = ref('')
const appSearchQuery = ref('')
const showAppDropdown = ref(false)
const rfcFile = ref(null)
const rfcFileInput = ref(null)

const filteredApps = computed(() => {
  const q = appSearchQuery.value.toLowerCase().trim()
  
  // Jika string pencarian persis sama dengan nama aplikasi yang sedang dipilih
  // (artinya user baru klik form dan belum mengetik apa-apa), tampilkan semua opsi
  if (selectedApp.value) {
    const found = appsActive.value.find(a => a.id === selectedApp.value)
    if (found && found.name.toLowerCase() === q) {
      return appsActive.value
    }
  }

  if (!q) return appsActive.value
  return appsActive.value.filter((a) => a.name.toLowerCase().includes(q))
})

function selectApp(app) {
  selectedApp.value = app.id
  appSearchQuery.value = app.name
  showAppDropdown.value = false
}

function onAppInputFocus(e) {
  showAppDropdown.value = true
  // Pilih (highlight) semua teks saat fokus agar mudah ditimpa jika ngetik
  e.target.select()
}

function onAppInputBlur() {
  // Delay agar klik pada item dropdown masih ter-handle
  setTimeout(() => {
    showAppDropdown.value = false
    
    if (appSearchQuery.value.trim() === '') {
      // Jika user menghapus teks sampai kosong dan klik di luar, maka hapus pilihan
      selectedApp.value = ''
    } else {
      // Restore display name jika input tidak kosong tapi tidak di-klik dari dropdown
      const found = appsActive.value.find((a) => a.id === selectedApp.value)
      appSearchQuery.value = found ? found.name : ''
    }
  }, 200)
}

function getSelectedAppName() {
  const found = appsActive.value.find((a) => a.id === selectedApp.value)
  return found ? found.name : ''
}
const selectedTipe = ref('')
const selectedPelaksana = ref('')
const selectedStatus = ref('')
const showDeleteModal = ref(false)
const deleteTarget = ref(null)
const deletingRfc = ref(false)
const stats = ref({ development: 0, operational: 0, inactive: 0 })

let searchTimer = null

function resetForm() {
  rfcStep.value = 1
  selectedApp.value = ''
  appSearchQuery.value = ''
  selectedTipe.value = ''
  selectedPelaksana.value = ''
  selectedStatus.value = ''
  rfcFile.value = null
  if (rfcFileInput.value) rfcFileInput.value.value = ''
}

async function openAdd() {
  editing.value = null
  resetForm()
  showForm.value = true
  await loadActiveApps()
}

function openEdit(item) {
  editing.value = item
  rfcStep.value = 1
  showForm.value = true
}

function closeForm() {
  if (savingRfc.value) return
  showForm.value = false
}

function openDetail(item) {
  const appId = item?.aplikasi_id || item?.aplikasi?.id
  if (!appId) {
    toast.push('Aplikasi RFC tidak ditemukan.', 'error')
    return
  }

  router.push({ name: 'pengelola-aplikasi-app-detail', params: { id: appId } })
}

function confirmDelete(item) {
  deleteTarget.value = item
  showDeleteModal.value = true
}

function closeDeleteModal() {
  if (deletingRfc.value) return
  showDeleteModal.value = false
  deleteTarget.value = null
}

async function deleteRfc() {
  if (!deleteTarget.value) return
  deletingRfc.value = true
  try {
    await http.delete(`/rfc/${deleteTarget.value.id}`)
    toast.push('RFC berhasil dihapus', 'success')
    showDeleteModal.value = false
    deleteTarget.value = null
    await loadRfcs(pagination.value.currentPage)
  } catch (error) {
    const message = error.response?.data?.message || error.message || 'Gagal menghapus RFC'
    toast.push(message, 'error')
    warnDev('[PengelolaRfc] deleteRfc error:', error)
  } finally {
    deletingRfc.value = false
  }
}

function onRfcFileChange(event) {
  rfcFile.value = event.target.files?.[0] || null
}

function removeRfcFile() {
  rfcFile.value = null
  if (rfcFileInput.value) rfcFileInput.value.value = ''
}

function validateRfcData() {
  if (!selectedApp.value || !selectedTipe.value || !selectedPelaksana.value || !selectedStatus.value) {
    toast.push('Mohon lengkapi semua field yang wajib diisi', 'error')
    return false
  }

  return true
}

function nextRfcStep() {
  if (!validateRfcData()) return
  rfcStep.value = 2
}

function previousRfcStep() {
  rfcStep.value = 1
}

function scheduleSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    loadRfcs(1)
  }, 350)
}

function clearSearch() {
  search.value = ''
  loadRfcs(1)
}

async function loadGlobalStats() {
  try {
    const resp = await http.get('/aplikasi/stats')
    const data = resp.data?.data || {}
    stats.value = {
      development: data.development || 0,
      operational: data.operational || 0,
      inactive: data.inactive || 0,
    }
  } catch (error) {
    warnDev('[PengelolaRfc] loadGlobalStats error:', error)
    stats.value = { development: 0, operational: 0, inactive: 0 }
  }
}

async function loadRfcs(page = 1) {
  loading.value = true
  try {
    const q = search.value?.trim() ? `q=${encodeURIComponent(search.value.trim())}&` : ''
    const resp = await http.get(`/rfc?${q}per_page=${pagination.value.perPage}&page=${page}`)
    if (resp.data.data) {
      const meta = resp.data.meta || resp.data
      rfcs.value = resp.data.data
      pagination.value = {
        currentPage: Number(meta.current_page) || page,
        lastPage: Number(meta.last_page) || 1,
        perPage: Number(meta.per_page) || pagination.value.perPage || 10,
        total: Number(meta.total) || 0,
      }
    } else {
      rfcs.value = resp.data || []
    }
  } catch (error) {
    warnDev('[PengelolaRfc] loadRfcs error:', error)
    toast.push('Gagal memuat data RFC', 'error')
  } finally {
    loading.value = false
  }
}

async function loadActiveApps() {
  try {
    const resp = await http.get('/aplikasi?status=deployed_production&per_page=100')
    appsActive.value = (resp.data.data || resp.data || []).map((a) => ({
      id: a.id,
      name: a.nama_aplikasi || a.nama_layanan || a.nama_singkat || `Aplikasi #${a.id}`,
    }))
  } catch (error) {
    warnDev('[PengelolaRfc] loadActiveApps error:', error)
    appsActive.value = []
  }
}

onMounted(async () => {
  await Promise.all([loadRfcs(), loadActiveApps(), loadGlobalStats()])
})

onBeforeUnmount(() => {
  if (searchTimer) clearTimeout(searchTimer)
})

function changePage(page) {
  loadRfcs(page)
}

function rowNumber(idx) {
  return ((pagination.value.currentPage - 1) * pagination.value.perPage) + idx + 1
}

watch(editing, (val) => {
  if (val) {
    rfcStep.value = 1
    rfcFile.value = null
    if (rfcFileInput.value) rfcFileInput.value.value = ''
    selectedApp.value = val.aplikasi_id
    appSearchQuery.value = val.aplikasi?.nama_aplikasi || getSelectedAppName()
    selectedTipe.value = val.tipe_rfc
    selectedPelaksana.value = val.pelaksana || 'Internal Pusdatik'
    selectedStatus.value = val.status_tindaklanjut || 'Analisa Desain'
  } else {
    resetForm()
  }
}, { immediate: true })



async function submitRfc() {
  if (savingRfc.value) return
  if (!validateRfcData()) return

  if (!editing.value && !rfcFile.value) {
    toast.push('Formulir RFC wajib diunggah', 'error')
    return
  }

  savingRfc.value = true
  const fd = new FormData()
  fd.append('aplikasi_id', editing.value?.aplikasi_id || selectedApp.value)
  fd.append('tipe_rfc', selectedTipe.value)
  fd.append('pelaksana', selectedPelaksana.value)
  fd.append('status_tindaklanjut', selectedStatus.value)
  if (rfcFile.value) fd.append('formulir_rfc', rfcFile.value)
  try {
    if (editing.value) {
      fd.append('_method', 'PUT')
      await http.post(`/rfc/${editing.value.id}`, fd)
      toast.push('RFC berhasil diperbarui!', 'success')
    } else {
      await http.post('/rfc', fd)
      toast.push('RFC berhasil ditambahkan!', 'success')
    }
    showForm.value = false
    editing.value = null
    await loadRfcs(pagination.value.currentPage)
  } catch (error) {
    const message = error.response?.data?.message || error.message || 'Gagal menyimpan RFC'
    toast.push(message, 'error')
    warnDev('[PengelolaRfc] submitRfc error:', error)
  } finally {
    savingRfc.value = false
  }
}
</script>

<template>
  <PengelolaLayout>
    <div class="container workspace-dashboard">
      <div class="workspace-hero-card">
        <div class="workspace-hero-text">
          <nav class="workspace-hero-breadcrumb" aria-label="breadcrumb">
            <button @click="router.push('/pengelola-aplikasi')" class="ah-bc-link">
              <Icons name="dashboard" :size="12" />
              Dashboard
            </button>
            <span class="ah-bc-sep">/</span>
            <span class="ah-bc-current">Kelola RFC</span>
          </nav>
          <h2 class="workspace-hero-title">Kelola RFC</h2>
          <p class="workspace-hero-sub">Pantau, kelola, dan proses seluruh RFC dari aplikasi.</p>
        </div>
      </div>

      <div class="content-section active">
        <!-- Stats modern style -->
        <div class="stats-grid">
          <div class="stat-card dev">
            <div class="stat-header">
              <span class="stat-label">Development</span>
              <div class="stat-icon-wrap bg-amber">
                <Icons name="code" :size="18" />
              </div>
            </div>
            <div class="stat-value">{{ stats.development }}</div>
          </div>
          <div class="stat-card production">
            <div class="stat-header">
              <span class="stat-label">Operasional</span>
              <div class="stat-icon-wrap bg-green">
                <Icons name="check-circle" :size="18" />
              </div>
            </div>
            <div class="stat-value">{{ stats.operational }}</div>
          </div>
          <div class="stat-card maintenance">
            <div class="stat-header">
              <span class="stat-label">Nonaktif</span>
              <div class="stat-icon-wrap bg-red">
                <Icons name="alert-circle" :size="18" />
              </div>
            </div>
            <div class="stat-value">{{ stats.inactive }}</div>
          </div>
        </div>

        <div class="card">
          <DataCardHead title="Daftar RFC">
            <template #actions>
              <div class="search-group">
                <span class="search-icon">
                  <Icons name="search" :size="16" />
                </span>
                <input 
                  type="text" 
                  v-model="search" 
                  @input="scheduleSearch"
                  placeholder="Cari RFC..."
                  maxlength="50" 
                  aria-label="Cari RFC..."
                />
              </div>
              <button class="btn btn-primary" @click="openAdd">
                <Icons name="plus" :size="16" />
                Tambah RFC
              </button>
            </template>
          </DataCardHead>

        <div v-if="loading" class="loading-state">
          <div class="loading-spinner"></div>
          <p>Memuat data RFC...</p>
        </div>
        <div v-else-if="rfcs.length === 0 && hasActiveSearch" class="global-empty">
          <div class="global-empty-icon-wrapper">
            <Icons name="search" :size="48" class="global-empty-icon" />
          </div>
          <h3 class="global-empty-title">Tidak Ada Hasil</h3>
          <p class="global-empty-text">
            Tidak ada RFC yang cocok dengan kata kunci pencarian ini.
          </p>
          <button type="button" class="btn btn-secondary" @click="clearSearch">
            Hapus pencarian
          </button>
        </div>
        <div v-else-if="rfcs.length === 0" class="global-empty">
          <div class="global-empty-icon-wrapper">
            <Icons name="file-text" :size="48" class="global-empty-icon" />
          </div>
          <h3 class="global-empty-title">Belum Ada RFC</h3>
          <p class="global-empty-text">
            Belum ada data Request for Change (RFC) yang tercatat dalam sistem ini.
          </p>
          <button @click="openAdd" class="btn btn-primary">
            <Icons name="plus" :size="16" />
            Tambah RFC
          </button>
        </div>
        <div v-else>
          <DataTable>
            <thead>
              <tr>
                <th scope="col" class="col-num">#</th>
                <th scope="col">Nama Aplikasi</th>
                <th scope="col">Tipe RFC</th>
                <th scope="col">Pelaksana</th>
                <th scope="col">Status</th>
                <th scope="col" class="col-aksi">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in rfcs" :key="item.id" class="data-table-row is-clickable" @click="openDetail(item)">
                <td class="col-num">{{ rowNumber(idx) }}</td>
                <td>{{ item.aplikasi?.nama_aplikasi || '-' }}</td>
                <td>{{ item.tipe_rfc }}</td>
                <td>{{ item.pelaksana || '-' }}</td>
                <td>
                  <span :class="['badge', getRfcStatusBadgeClass(item.status_tindaklanjut)]">
                    {{ item.status_tindaklanjut }}
                  </span>
                </td>
                <td @click.stop>
                  <div class="action-group">
                    <button class="action-btn table-action-btn view-btn" @click="openDetail(item)"><Icons name="eye" :size="14" /> Detail</button>
                    <button class="action-btn table-action-btn edit-btn" @click="openEdit(item)"><Icons name="edit" :size="14" /> Edit</button>
                    <button class="action-btn table-action-btn delete-btn" @click="confirmDelete(item)"><Icons name="trash" :size="14" /> Hapus</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </DataTable>
        </div>

        <div v-if="pagination.lastPage > 1" class="pagination">
          <div class="pagination-info">
            Menampilkan {{ ((pagination.currentPage - 1) * pagination.perPage) + 1 }} - 
            {{ Math.min(pagination.currentPage * pagination.perPage, pagination.total) }} 
            dari {{ pagination.total }} data
          </div>
          <div class="pagination-controls">
            <button @click="changePage(pagination.currentPage - 1)" :disabled="pagination.currentPage === 1" class="pagination-btn">
              <Icons name="chevron-left" :size="16" />
            </button>
            <button v-for="page in rfcPageNumbers" :key="page" @click="page !== '...' && changePage(page)" :class="['pagination-btn', { active: page === pagination.currentPage, disabled: page === '...' }]">
              {{ page }}
            </button>
            <button @click="changePage(pagination.currentPage + 1)" :disabled="pagination.currentPage === pagination.lastPage" class="pagination-btn">
              <Icons name="chevron-right" :size="16" />
            </button>
          </div>
        </div>
        </div>
      </div>

      <!-- Simple Form Modal inside page -->
      <dialog v-if="showForm" class="modal active" open :aria-labelledby="`modal-rfc-title`" @click.self="closeForm">
        <div class="modal-content rfc-form-modal">
          <div class="modal-header">
            <h3 :id="`modal-rfc-title`">{{ editing ? 'Edit RFC' : 'Tambah RFC' }}</h3>
            <button class="close-btn" :disabled="savingRfc" @click="closeForm" aria-label="Tutup modal">&times;</button>
          </div>

          <div class="rfc-stepper" aria-label="Tahap pengisian RFC">
            <div class="rfc-step" :class="{ active: rfcStep === 1, done: rfcStep > 1 }">
              <span>
                <Icons v-if="rfcStep > 1" name="check" :size="12" />
                <template v-else>1</template>
              </span>
              <strong>Data RFC</strong>
            </div>
            <div class="rfc-step-line" :class="{ active: rfcStep > 1 }"></div>
            <div class="rfc-step" :class="{ active: rfcStep === 2 }">
              <span>2</span>
              <strong>Formulir RFC</strong>
            </div>
          </div>

          <form class="rfc-form" @submit.prevent="rfcStep === 1 ? nextRfcStep() : submitRfc()">
            <template v-if="rfcStep === 1">
              <div class="form-row">
                <div class="form-group">
                  <label for="rfc-app">Nama Aplikasi <span class="required-mark">*</span></label>
                  <div class="combobox-wrapper">
                    <input
                      id="rfc-app"
                      type="text"
                      class="combobox-input"
                      v-model="appSearchQuery"
                      :placeholder="getSelectedAppName() || 'Cari aplikasi...'"
                      :disabled="!!editing"
                      autocomplete="off"
                      @focus="onAppInputFocus"
                      @blur="onAppInputBlur"
                    />
                    <Icons name="chevron-down" :size="14" class="combobox-chevron" />
                    <div v-if="showAppDropdown && !editing" class="combobox-dropdown">
                      <div v-if="filteredApps.length === 0" class="combobox-empty">Tidak ditemukan</div>
                      <button
                        v-for="app in filteredApps"
                        :key="app.id"
                        type="button"
                        class="combobox-option"
                        :class="{ active: app.id === selectedApp }"
                        @mousedown.prevent="selectApp(app)"
                      >
                        {{ app.name }}
                      </button>
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label for="rfc-type">Tipe RFC <span class="required-mark">*</span></label>
                  <select id="rfc-type" v-model="selectedTipe">
                    <option value="" disabled>-- Pilih Tipe --</option>
                    <option value="Medium">Medium</option>
                    <option value="Standar">Standar</option>
                    <option value="Minor">Minor</option>
                    <option value="Major">Major</option>
                    <option value="Darurat">Darurat</option>
                  </select>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label for="rfc-pelaksana">Pelaksana <span class="required-mark">*</span></label>
                  <select id="rfc-pelaksana" v-model="selectedPelaksana">
                    <option value="" disabled>-- Pilih Pelaksana --</option>
                    <option value="Internal Pusdatik">Internal Pusdatik</option>
                    <option value="Eksternal">Eksternal</option>
                    <option value="Internal D13">Internal D13</option>
                  </select>
                </div>
                <div class="form-group">
                  <label for="rfc-status">Status <span class="required-mark">*</span></label>
                  <select id="rfc-status" v-model="selectedStatus">
                    <option value="" disabled>-- Pilih Status --</option>
                    <option value="Diajukan">Diajukan</option>
                    <option value="Analisa Desain">Analisa Desain</option>
                    <option value="Dev-Staging">Dev-Staging</option>
                    <option value="Production">Production</option>
                    <option value="UAT">UAT</option>
                  </select>
                </div>
              </div>
              <div class="rfc-modal-actions">
                <button type="button" class="btn btn-secondary" :disabled="savingRfc" @click="closeForm">Batal</button>
                <button type="button" class="btn btn-primary" :disabled="savingRfc" @click="nextRfcStep">
                  Lanjut
                  <span aria-hidden="true">&rarr;</span>
                </button>
              </div>
            </template>

            <template v-else>
              <div class="rfc-upload-section">
                <div class="rfc-upload-header">
                  <div class="rfc-upload-icon">
                    <Icons name="file-text" :size="24" />
                  </div>
                  <div class="rfc-upload-copy">
                    <h4>Unggah Formulir RFC</h4>
                    <p>Formulir RFC wajib diunggah saat membuat RFC baru. Pada edit RFC, unggah file baru hanya jika ingin mengganti formulir sebelumnya.</p>
                  </div>
                </div>

                <div class="rfc-template-row">
                  <Icons name="download" :size="14" />
                  <a
                    href="/templates/Formulir-RFC.xlsx"
                    class="rfc-template-link"
                    target="_blank"
                    rel="noopener"
                  >
                    Buka template formulir RFC
                  </a>
                </div>

                <a
                  v-if="editing?.formulir_url"
                  :href="editing.formulir_url"
                  class="existing-file-link"
                  target="_blank"
                  rel="noopener"
                >
                  <Icons name="file-text" :size="16" />
                  Lihat formulir RFC saat ini
                </a>

                <div class="rfc-upload-area" :class="{ 'has-file': !!rfcFile }">
                  <template v-if="!rfcFile">
                    <label class="rfc-upload-label" for="rfc-formulir-input">
                      <Icons name="upload" :size="28" />
                      <strong>Pilih Formulir RFC</strong>
                      <span>PDF, DOC, DOCX, XLS, atau XLSX maksimal 10 MB</span>
                    </label>
                    <input
                      id="rfc-formulir-input"
                      ref="rfcFileInput"
                      type="file"
                      accept=".pdf,.doc,.docx,.xls,.xlsx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                      @change="onRfcFileChange"
                    />
                  </template>
                  <template v-else>
                    <div class="rfc-file-preview">
                      <Icons name="file-text" :size="28" />
                      <div>
                        <span class="rfc-file-name">{{ rfcFile.name }}</span>
                        <span class="rfc-file-size">{{ (rfcFile.size / 1024).toFixed(1) }} KB</span>
                      </div>
                      <button type="button" class="rfc-remove-file-btn" @click="removeRfcFile" aria-label="Hapus file RFC">
                        <Icons name="trash" :size="16" />
                      </button>
                    </div>
                  </template>
                </div>
              </div>

              <div class="rfc-modal-actions">
                <button type="button" class="btn btn-secondary" :disabled="savingRfc" @click="previousRfcStep">
                  <span aria-hidden="true">&larr;</span>
                  Kembali
                </button>
                <button type="submit" class="btn btn-primary" :disabled="savingRfc || (!editing && !rfcFile)">
                  <Icons v-if="!savingRfc" name="check" :size="14" />
                  {{ savingRfc ? 'Menyimpan...' : (editing ? 'Simpan RFC' : 'Kirim RFC') }}
                </button>
              </div>
            </template>
          </form>
        </div>
      </dialog>

      <!-- Delete Confirmation Modal -->
      <dialog v-if="showDeleteModal" class="modal active" open aria-labelledby="modal-delete-title" @click.self="closeDeleteModal">
        <div class="modal-content confirm-modal">
          <div class="confirm-header">
            <Icons name="alert" :size="48" class="confirm-icon" />
            <h3 id="modal-delete-title">Hapus RFC</h3>
          </div>
          <div class="confirm-body">
            <p>RFC berikut akan dihapus permanen beserta formulir yang diunggah:</p>
            <p class="confirm-target"><strong>{{ deleteTarget?.aplikasi?.nama_aplikasi || '-' }}</strong></p>
            <p class="confirm-warning">Tindakan ini tidak dapat dibatalkan.</p>
          </div>
          <div class="confirm-actions">
            <button class="btn btn-secondary" :disabled="deletingRfc" @click="closeDeleteModal">Batal</button>
            <button class="btn btn-danger" :disabled="deletingRfc" @click="deleteRfc">
              <Icons name="trash" :size="14" />
              {{ deletingRfc ? 'Menghapus...' : 'Hapus' }}
            </button>
          </div>
        </div>
      </dialog>
    </div>
  </PengelolaLayout>
 </template>

<style scoped>
.rfc-form-modal {
  width: min(760px, calc(100vw - 32px));
  max-width: 760px;
  max-height: calc(100vh - 32px);
}

.col-aksi {
  width: 300px;
}

.action-group {
  flex-wrap: wrap;
}

.rfc-form {
  display: flex;
  flex-direction: column;
}

.rfc-stepper {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 24px;
  padding: 0 20px;
}

.rfc-step {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--notion-text-tertiary);
  transition: all 0.3s ease;
  white-space: nowrap;
}

.rfc-step span {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--notion-bg-secondary);
  border: 1px solid var(--notion-border);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.rfc-step.active { color: var(--notion-text); }
.rfc-step.done { color: #10b981; }

.rfc-step.active span {
  background: var(--notion-blue);
  border-color: var(--notion-blue);
  color: #fff;
}

.rfc-step.done span {
  background: #10b981;
  border-color: #10b981;
  color: #fff;
}

.rfc-step-line {
  flex: 1;
  height: 2px;
  background: var(--notion-border);
  margin: 0 16px;
  border-radius: 2px;
  transition: all 0.3s ease;
}

.rfc-step-line.active { background: #10b981; }

.rfc-upload-section {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.rfc-upload-header {
  display: flex;
  align-items: flex-start;
  gap: 14px;
}

.rfc-upload-icon {
  flex-shrink: 0;
  width: 48px;
  height: 48px;
  border-radius: 10px;
  background: var(--notion-blue-bg, #eff6ff);
  border: 1px solid rgba(59, 130, 246, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--notion-blue, #2563eb);
}

.rfc-upload-copy h4 {
  margin: 0 0 6px;
  font-size: 15px;
  font-weight: 700;
  color: var(--notion-text);
}

.rfc-upload-copy p {
  margin: 0;
  color: var(--notion-text-secondary);
  font-size: 13px;
  line-height: 1.6;
}

.rfc-template-row {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--notion-text-secondary);
}

.rfc-template-link {
  font-size: 13px;
  color: var(--notion-blue);
  text-decoration: none;
  font-weight: 600;
}

.rfc-template-link:hover {
  text-decoration: underline;
}

.existing-file-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  width: fit-content;
  color: var(--notion-blue-dark);
  font-weight: 700;
  text-decoration: none;
}

.existing-file-link:hover {
  text-decoration: underline;
}

.rfc-upload-area {
  border: 2px dashed var(--notion-border);
  border-radius: 10px;
  background: var(--notion-bg);
  min-height: auto;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: border-color 0.2s, background 0.2s;
}

.rfc-upload-area:hover {
  border-color: var(--notion-blue);
  background: var(--notion-blue-bg, #eff6ff);
}

.rfc-upload-area.has-file {
  border-style: solid;
  border-color: #10b981;
  background: #f0fdf4;
}

.rfc-upload-label {
  width: 100%;
  min-height: auto;
  padding: 32px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
  color: var(--notion-text-secondary);
  text-align: center;
}

.rfc-upload-label strong {
  color: var(--notion-blue-dark);
  font-size: 15px;
}

.rfc-upload-label span {
  font-size: 13px;
}

.rfc-upload-area input[type='file'] {
  display: none;
}

.rfc-file-preview {
  width: 100%;
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 12px;
  padding: 20px;
  color: var(--notion-blue-dark);
}

.rfc-file-name,
.rfc-file-size {
  display: block;
}

.rfc-file-name {
  font-weight: 800;
  color: var(--notion-text);
}

.rfc-file-size {
  margin-top: 3px;
  font-size: 12px;
  color: var(--notion-text-secondary);
}

.rfc-remove-file-btn {
  width: 38px;
  height: 38px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #fecaca;
  border-radius: 10px;
  background: #fef2f2;
  color: #dc2626;
  cursor: pointer;
}

.rfc-detail-modal {
  width: min(720px, calc(100vw - 32px));
  max-width: 720px;
}

.rfc-detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.rfc-detail-grid > div {
  border: 1px solid var(--border-color);
  border-radius: 10px;
  padding: 14px;
  background: #fff;
}

.rfc-detail-grid span {
  display: block;
  margin-bottom: 6px;
  color: var(--notion-text-secondary);
  font-size: 12px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.rfc-detail-grid strong,
.rfc-detail-grid p {
  margin: 0;
  color: var(--notion-text);
  font-size: 14px;
  line-height: 1.45;
}

.rfc-detail-full {
  grid-column: 1 / -1;
}

.required-mark {
  color: var(--notion-red);
  font-weight: 800;
}

.rfc-modal-actions {
  display: flex;
  justify-content: stretch;
  gap: 10px;
  padding-top: 8px;
}

.rfc-modal-actions .btn {
  flex: 1;
  justify-content: center;
  margin-top: 0;
}

.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin: 12px 20px 16px; }

/* Stat Cards - Modern Enterprise Style */
.stat-card {
  position: relative;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  transition: all 0.2s ease;
  box-shadow: 0 1px 3px rgba(0,0,0,0.02);
  overflow: hidden;
}

.stat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.stat-icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 8px;
}

.stat-icon-wrap.bg-blue { background: #eff6ff; color: #3b82f6; }
.stat-icon-wrap.bg-green { background: #f0fdf4; color: #10b981; }
.stat-icon-wrap.bg-amber { background: #fffbeb; color: #f59e0b; }
.stat-icon-wrap.bg-red { background: #fef2f2; color: #ef4444; }

.stat-label {
  font-size: 14px;
  color: #6b7280;
  font-weight: 500;
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #111827;
  line-height: 1.2;
}

.stat-card::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  opacity: 0.8;
}

.stat-card.total::after { background: #3b82f6; }
.stat-card.production::after { background: #10b981; }
.stat-card.dev::after { background: #f59e0b; }
.stat-card.maintenance::after { background: #ef4444; }

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
  border-color: #d1d5db;
}

@media (max-width: 768px) {
  .stats-grid { grid-template-columns: 1fr; }
  .stat-card { padding: 16px; }
  .stat-value { font-size: 24px; }
  .stat-label { font-size: 13px; }
  .rfc-form-modal {
    width: min(100vw - 20px, 760px);
    max-height: calc(100vh - 20px);
  }
  .rfc-form-modal .form-row {
    grid-template-columns: 1fr;
    gap: 0;
  }
  .rfc-modal-actions {
    flex-direction: column-reverse;
  }
  .rfc-modal-actions .btn {
    width: 100%;
    justify-content: center;
  }
}

.workspace-hero-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
  background: linear-gradient(135deg, #1e3a8a 0%, #2c4fa8 100%);
  border-radius: 14px;
  padding: 24px 28px;
  margin: 0 20px 20px;
  box-shadow: 0 4px 14px rgba(30, 58, 138, 0.18);
}

.workspace-hero-breadcrumb {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 8px;
  font-size: 12px;
}

.ah-bc-link {
  background: none;
  border: none;
  color: rgba(255, 255, 255, 0.55);
  cursor: pointer;
  padding: 0;
  font-size: 12px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: color 0.15s;
}

.ah-bc-link:hover { color: rgba(255, 255, 255, 0.9); }
.ah-bc-sep { color: rgba(255, 255, 255, 0.35); }
.ah-bc-current { color: rgba(255, 255, 255, 0.8); font-weight: 500; }

.workspace-hero-title {
  margin: 0 0 4px;
  font-size: 20px;
  font-weight: 700;
  color: #fff;
}

.workspace-hero-sub {
  margin: 0;
  font-size: 14px;
  color: rgba(255, 255, 255, 0.7);
  line-height: 1.5;
}

@media (max-width: 768px) {
  .workspace-hero-card {
    flex-direction: column;
    align-items: flex-start;
    margin: 0 12px 16px;
  }

  .data-card-head {
    flex-direction: column;
    align-items: flex-start;
  }

  .data-card-head-actions {
    width: 100%;
    flex-direction: column;
  }

  .data-card-head-actions .search-group {
    max-width: 100%;
    width: 100%;
  }
}

/* Searchable Combobox */
.combobox-wrapper {
  position: relative;
}

.combobox-input {
  width: 100%;
  padding: 10px 36px 10px 12px;
  border: 1px solid var(--notion-border);
  border-radius: 6px;
  background: var(--notion-bg);
  font-size: 14px;
  color: var(--notion-text);
  cursor: text;
  transition: border-color 0.15s;
}

.combobox-input:focus {
  outline: none;
  border-color: var(--notion-blue);
  box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.08);
}

.combobox-input:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  background: var(--notion-muted-surface, #f5f5f5);
}

.combobox-chevron {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--notion-text-secondary);
  pointer-events: none;
}

.combobox-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: var(--notion-bg);
  border: 1px solid var(--notion-border);
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.12);
  max-height: 200px;
  overflow-y: auto;
  z-index: 100;
  padding: 4px;
}

.combobox-dropdown::-webkit-scrollbar {
  width: 6px;
}

.combobox-dropdown::-webkit-scrollbar-track {
  background: transparent;
}

.combobox-dropdown::-webkit-scrollbar-thumb {
  background: var(--notion-border);
  border-radius: 3px;
}

.combobox-option {
  display: block;
  width: 100%;
  text-align: left;
  padding: 8px 12px;
  border: none;
  background: transparent;
  border-radius: 6px;
  font-size: 14px;
  color: var(--notion-text);
  cursor: pointer;
  transition: background 0.1s;
}

.combobox-option:hover {
  background: var(--notion-hover);
}

.combobox-option.active {
  background: rgba(30, 58, 138, 0.08);
  color: var(--notion-blue);
  font-weight: 600;
}

.combobox-empty {
  padding: 12px;
  text-align: center;
  font-size: 13px;
  color: var(--notion-text-secondary);
}
</style>


