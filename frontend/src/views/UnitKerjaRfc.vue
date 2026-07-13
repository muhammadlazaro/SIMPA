<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import http from '../lib/http'
import UserLayout from '../layouts/UserLayout.vue'
import DataCardHead from '../components/DataCardHead.vue'
import DataTable from '../components/DataTable.vue'
import Icons from '../components/Icons.vue'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { getHomeByRole } from '../constants/roles'
import { getRfcStatusBadgeClass } from '../constants/status'
import { usePagination } from '../composables/usePagination.js'
import { warnDev } from '../utils/logger'

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const rfcs = ref([])
const productionApps = ref([])
const loading = ref(false)
const saving = ref(false)
const search = ref('')
const showForm = ref(false)
const selectedApp = ref('')
const selectedTipe = ref('')
const formStep = ref(1)
const rfcFile = ref(null)
const rfcFileInput = ref(null)
const pagination = ref({ currentPage: 1, lastPage: 1, perPage: 10, total: 0 })
const stats = ref({ total: 0, diajukan: 0, diproses: 0, production: 0 })
const { pageNumbers } = usePagination(pagination)

let searchTimer = null

const basePath = computed(() => getHomeByRole(auth.role).path)
const hasActiveSearch = computed(() => !!search.value?.trim())
const canSubmit = computed(() => productionApps.value.length > 0)

onMounted(async () => {
  await Promise.all([loadRfcs(), loadProductionApps(), loadRfcStats()])
})

onBeforeUnmount(() => {
  if (searchTimer) clearTimeout(searchTimer)
})

async function loadRfcs(page = 1) {
  loading.value = true
  try {
    const q = search.value?.trim() ? `q=${encodeURIComponent(search.value.trim())}&` : ''
    const response = await http.get(`/rfc?${q}per_page=${pagination.value.perPage}&page=${page}`)

    if (response.data.data) {
      const meta = response.data.meta || response.data
      rfcs.value = response.data.data
      pagination.value = {
        currentPage: Number(meta.current_page) || page,
        lastPage: Number(meta.last_page) || 1,
        perPage: Number(meta.per_page) || pagination.value.perPage || 10,
        total: Number(meta.total) || 0,
      }
    } else {
      rfcs.value = response.data || []
      pagination.value.currentPage = page
    }
  } catch (error) {
    warnDev('[UnitKerjaRfc] loadRfcs error:', error)
    toast.push('Tidak dapat memuat daftar RFC.', 'error', 4000)
  } finally {
    loading.value = false
  }
}

async function loadProductionApps() {
  try {
    const response = await http.get('/aplikasi?status=deployed_production&per_page=100')
    productionApps.value = (response.data.data || response.data || []).map((app) => ({
      id: app.id,
      name: app.nama_aplikasi || app.nama_layanan || app.nama_singkat || `Aplikasi #${app.id}`,
      layanan: app.nama_layanan,
    }))
  } catch (error) {
    warnDev('[UnitKerjaRfc] loadProductionApps error:', error)
    productionApps.value = []
  }
}

async function loadRfcStats() {
  try {
    const response = await http.get('/rfc/stats')
    stats.value = {
      total: Number(response.data?.data?.total) || 0,
      diajukan: Number(response.data?.data?.diajukan) || 0,
      diproses: Number(response.data?.data?.diproses) || 0,
      production: Number(response.data?.data?.production) || 0,
    }
  } catch (error) {
    warnDev('[UnitKerjaRfc] loadRfcStats error:', error)
    stats.value = { total: 0, diajukan: 0, diproses: 0, production: 0 }
  }
}

async function openForm() {
  resetForm()
  showForm.value = true
  await loadProductionApps()
}

function resetForm() {
  formStep.value = 1
  selectedApp.value = ''
  selectedTipe.value = ''
  rfcFile.value = null
  if (rfcFileInput.value) rfcFileInput.value.value = ''
}

function closeForm() {
  if (saving.value) return
  showForm.value = false
}

async function submitRfc() {
  if (saving.value) return
  if (!selectedApp.value || !selectedTipe.value) {
    toast.push('Pilih aplikasi dan tipe RFC terlebih dahulu.', 'error')
    return
  }
  if (!rfcFile.value) {
    toast.push('Formulir RFC wajib diunggah.', 'error')
    return
  }

  saving.value = true
  try {
    const fd = new FormData()
    fd.append('aplikasi_id', selectedApp.value)
    fd.append('tipe_rfc', selectedTipe.value)
    fd.append('formulir_rfc', rfcFile.value)

    await http.post('/rfc', fd)

    toast.push('Pengajuan RFC berhasil dikirim.', 'success')
    showForm.value = false
    await Promise.all([loadRfcs(1), loadRfcStats()])
  } catch (error) {
    const message = error.response?.data?.message || error.message || 'Gagal mengajukan RFC.'
    toast.push(message, 'error')
    warnDev('[UnitKerjaRfc] submitRfc error:', error)
  } finally {
    saving.value = false
  }
}

function nextStep() {
  if (!selectedApp.value || !selectedTipe.value) {
    toast.push('Pilih aplikasi dan tipe RFC terlebih dahulu.', 'error')
    return
  }
  formStep.value = 2
}

function previousStep() {
  formStep.value = 1
}

function onRfcFileChange(event) {
  rfcFile.value = event.target.files?.[0] || null
}

function removeRfcFile() {
  rfcFile.value = null
  if (rfcFileInput.value) rfcFileInput.value.value = ''
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

function changePage(page) {
  if (page === '...' || page < 1 || page > pagination.value.lastPage) return
  loadRfcs(page)
}

function rowNumber(idx) {
  return ((pagination.value.currentPage - 1) * pagination.value.perPage) + idx + 1
}

function formatDate(value) {
  if (!value) return '-'
  return new Date(value).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}
</script>

<template>
  <UserLayout>
    <div class="container uk-rfc-page">
      <div class="uk-welcome-card">
        <div class="uk-welcome-text">
          <nav class="uk-welcome-breadcrumb" aria-label="breadcrumb">
            <button type="button" @click="router.push(basePath)" class="uk-bc-link">
              <Icons name="dashboard" :size="12" />
              Dashboard
            </button>
            <span class="uk-bc-sep">/</span>
            <span class="uk-bc-current">Pengajuan RFC</span>
          </nav>
          <h2 class="uk-welcome-title">Pengajuan RFC</h2>
          <p class="uk-welcome-sub">Ajukan perubahan untuk aplikasi operasional milik unit kerja Anda.</p>
        </div>
        <button
          type="button"
          class="btn btn-light-primary"
          :disabled="!canSubmit"
          @click="openForm"
        >
          <Icons name="plus" :size="16" />
          Ajukan RFC
        </button>
      </div>

      <div class="stats-grid">
        <div class="stat-card total">
          <div class="stat-header">
            <span class="stat-label">Total RFC</span>
            <div class="stat-icon-wrap bg-blue">
              <Icons name="file-text" :size="18" />
            </div>
          </div>
          <div class="stat-value">{{ stats.total }}</div>
        </div>
        <div class="stat-card submitted">
          <div class="stat-header">
            <span class="stat-label">Diajukan</span>
            <div class="stat-icon-wrap bg-amber">
              <Icons name="file" :size="18" />
            </div>
          </div>
          <div class="stat-value">{{ stats.diajukan }}</div>
        </div>
        <div class="stat-card progress">
          <div class="stat-header">
            <span class="stat-label">Diproses</span>
            <div class="stat-icon-wrap bg-indigo">
              <Icons name="code" :size="18" />
            </div>
          </div>
          <div class="stat-value">{{ stats.diproses }}</div>
        </div>
        <div class="stat-card done">
          <div class="stat-header">
            <span class="stat-label">Production</span>
            <div class="stat-icon-wrap bg-green">
              <Icons name="check-circle" :size="18" />
            </div>
          </div>
          <div class="stat-value">{{ stats.production }}</div>
        </div>
      </div>

      <div v-if="!canSubmit" class="notice-card">
        <Icons name="alert-circle" :size="18" />
        <span>RFC dapat diajukan setelah aplikasi Anda berstatus deployed production.</span>
      </div>

      <div class="content-section active">
        <div class="card uk-card">
          <DataCardHead title="Daftar RFC Anda">
            <template #actions>
              <div class="search-group">
                <span class="search-icon">
                  <Icons name="search" :size="16" />
                </span>
                <input
                  type="search"
                  v-model="search"
                  @input="scheduleSearch"
                  placeholder="Cari RFC..."
                  maxlength="50"
                  aria-label="Cari RFC"
                />
              </div>
              <button
                class="btn btn-primary"
                type="button"
                :disabled="!canSubmit"
                @click="openForm"
              >
                <Icons name="plus" :size="16" />
                Ajukan RFC
              </button>
            </template>
          </DataCardHead>

          <div v-if="loading" class="loading-state">
            <div class="loading-spinner"></div>
            <p>Memuat daftar RFC...</p>
          </div>

          <div v-else-if="rfcs.length === 0 && hasActiveSearch" class="global-empty">
            <div class="global-empty-icon-wrapper">
              <Icons name="search" :size="48" class="global-empty-icon" />
            </div>
            <h3 class="global-empty-title">Tidak Ada Hasil</h3>
            <p class="global-empty-text">Tidak ada RFC yang cocok dengan kata kunci pencarian ini.</p>
            <button type="button" class="btn btn-secondary" @click="clearSearch">Hapus pencarian</button>
          </div>

          <div v-else-if="rfcs.length === 0" class="global-empty">
            <div class="global-empty-icon-wrapper">
              <Icons name="file-text" :size="48" class="global-empty-icon" />
            </div>
            <h3 class="global-empty-title">Belum Ada RFC</h3>
            <p class="global-empty-text">Ajukan RFC pertama untuk perubahan aplikasi operasional unit kerja Anda.</p>
            <button
              type="button"
              class="btn btn-primary"
              :disabled="!canSubmit"
              @click="openForm"
            >
              <Icons name="plus" :size="16" />
              Ajukan RFC
            </button>
          </div>

          <template v-else>
            <DataTable>
              <thead>
                <tr>
                  <th scope="col" class="col-num">#</th>
                  <th scope="col">Nama Aplikasi</th>
                  <th scope="col">Tipe RFC</th>
                  <th scope="col">Status</th>
                  <th scope="col">Tanggal</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in rfcs" :key="item.id" class="data-table-row">
                  <td class="col-num">{{ rowNumber(idx) }}</td>
                  <td>
                    <div class="app-name-cell">
                      <span class="app-name-main">{{ item.aplikasi?.nama_aplikasi || '-' }}</span>
                      <span class="app-name-sub">RFC #{{ item.id }}</span>
                    </div>
                  </td>
                  <td>{{ item.tipe_rfc }}</td>
                  <td>
                    <span :class="['badge', getRfcStatusBadgeClass(item.status_tindaklanjut)]">
                      {{ item.status_tindaklanjut }}
                    </span>
                  </td>
                  <td class="col-date">{{ formatDate(item.created_at) }}</td>
                </tr>
              </tbody>
            </DataTable>

            <div v-if="pagination.lastPage > 1" class="pagination">
              <div class="pagination-info">
                Menampilkan {{ ((pagination.currentPage - 1) * pagination.perPage) + 1 }} -
                {{ Math.min(pagination.currentPage * pagination.perPage, pagination.total) }}
                dari {{ pagination.total }} RFC
              </div>
              <div class="pagination-controls">
                <button
                  type="button"
                  @click="changePage(pagination.currentPage - 1)"
                  :disabled="pagination.currentPage === 1"
                  class="pagination-btn"
                >
                  <Icons name="chevron-left" :size="16" />
                </button>
                <button
                  v-for="page in pageNumbers"
                  :key="page"
                  type="button"
                  @click="changePage(page)"
                  :class="['pagination-btn', { active: page === pagination.currentPage, disabled: page === '...' }]"
                >
                  {{ page }}
                </button>
                <button
                  type="button"
                  @click="changePage(pagination.currentPage + 1)"
                  :disabled="pagination.currentPage === pagination.lastPage"
                  class="pagination-btn"
                >
                  <Icons name="chevron-right" :size="16" />
                </button>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <dialog v-if="showForm" class="modal active" open aria-labelledby="modal-rfc-title" @click.self="closeForm">
      <div class="modal-content rfc-form-modal">
        <div class="modal-header">
          <h3 id="modal-rfc-title">Ajukan RFC</h3>
          <button class="close-btn" type="button" :disabled="saving" @click="closeForm">&times;</button>
        </div>

        <div class="rfc-stepper" aria-label="Tahap pengajuan RFC">
          <div class="rfc-step" :class="{ active: formStep === 1, done: formStep > 1 }">
            <span>
              <Icons v-if="formStep > 1" name="check" :size="12" />
              <template v-else>1</template>
            </span>
            <strong>Data RFC</strong>
          </div>
          <div class="rfc-step-line" :class="{ active: formStep > 1 }"></div>
          <div class="rfc-step" :class="{ active: formStep === 2 }">
            <span>2</span>
            <strong>Formulir RFC</strong>
          </div>
        </div>

        <form class="rfc-form" @submit.prevent="formStep === 1 ? nextStep() : submitRfc()">
          <template v-if="formStep === 1">
            <div class="form-group">
              <label for="rfc-app">Aplikasi Production <span class="required-mark">*</span></label>
              <select id="rfc-app" v-model="selectedApp" :disabled="saving || productionApps.length === 0">
                <option value="" disabled>-- Pilih aplikasi --</option>
                <option v-for="app in productionApps" :key="app.id" :value="app.id">
                  {{ app.name }}{{ app.layanan ? ` - ${app.layanan}` : '' }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label for="rfc-type">Tipe RFC <span class="required-mark">*</span></label>
              <select id="rfc-type" v-model="selectedTipe" :disabled="saving">
                <option value="" disabled>-- Pilih tipe --</option>
                <option value="Medium">Medium</option>
                <option value="Standar">Standar</option>
                <option value="Minor">Minor</option>
                <option value="Major">Major</option>
                <option value="Darurat">Darurat</option>
              </select>
            </div>

            <div class="rfc-modal-actions">
              <button type="button" class="btn btn-secondary" :disabled="saving" @click="closeForm">Batal</button>
              <button type="button" class="btn btn-primary" :disabled="saving" @click="nextStep">
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
                  <p>Unggah formulir RFC resmi. Dokumen ini wajib agar pengajuan dapat diproses.</p>
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

              <div class="rfc-upload-area" :class="{ 'has-file': !!rfcFile }">
                <template v-if="!rfcFile">
                  <label class="rfc-upload-label" for="unit-rfc-formulir-input">
                    <Icons name="upload" :size="28" />
                    <strong>Pilih Formulir RFC</strong>
                    <span>PDF, DOC, DOCX, XLS, atau XLSX maksimal 10 MB</span>
                  </label>
                  <input
                    id="unit-rfc-formulir-input"
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
              <button type="button" class="btn btn-secondary" :disabled="saving" @click="previousStep">
                <span aria-hidden="true">&larr;</span>
                Kembali
              </button>
              <button type="submit" class="btn btn-primary" :disabled="saving || !rfcFile">
                <Icons v-if="!saving" name="check" :size="14" />
                {{ saving ? 'Mengirim...' : 'Kirim Pengajuan' }}
              </button>
            </div>
          </template>
        </form>
      </div>
    </dialog>
  </UserLayout>
</template>

<style scoped>
.uk-welcome-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
  background: linear-gradient(135deg, #1e3a8a 0%, #2c4fa8 100%);
  border-radius: 14px;
  padding: 24px 28px;
  margin: 0 20px 28px;
  box-shadow: 0 4px 14px rgba(30, 58, 138, 0.18);
}

.uk-welcome-breadcrumb {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 8px;
  font-size: 12px;
}

.uk-bc-link {
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

.uk-bc-link:hover {
  color: rgba(255, 255, 255, 0.9);
}

.uk-bc-sep {
  color: rgba(255, 255, 255, 0.35);
}

.uk-bc-current {
  color: rgba(255, 255, 255, 0.8);
  font-weight: 500;
}

.uk-welcome-title {
  margin: 0 0 4px;
  font-size: 20px;
  font-weight: 700;
  color: #fff;
}

.uk-welcome-sub {
  margin: 0;
  font-size: 14px;
  color: rgba(255, 255, 255, 0.75);
  line-height: 1.5;
}

.btn-light-primary {
  background: #fff;
  color: #1e3a8a;
  border: none;
}

.btn-light-primary:hover:not(:disabled) {
  background: #eef4ff;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 20px;
  margin: 12px 20px 16px;
}

.stat-card {
  position: relative;
  background: #ffffff;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 12px;
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  transition: all 0.2s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

.stat-card::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: transparent;
}

.stat-card.total::after { background: #3b82f6; }
.stat-card.submitted::after { background: #f59e0b; }
.stat-card.progress::after { background: #6366f1; }
.stat-card.done::after { background: #10b981; }

.stat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.stat-label {
  font-size: 14px;
  color: #4b5563;
  font-weight: 600;
  letter-spacing: 0.02em;
}

.stat-icon-wrap {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.bg-blue { background: #eff6ff; color: #3b82f6; }
.bg-green { background: #ecfdf5; color: #10b981; }
.bg-amber { background: #fffbeb; color: #f59e0b; }
.bg-indigo { background: #eef2ff; color: #4f46e5; }

.stat-value {
  font-size: 32px;
  font-weight: 700;
  color: #111827;
  line-height: 1;
}

.notice-card {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0 20px 16px;
  padding: 12px 16px;
  border-radius: 10px;
  border: 1px solid #fde68a;
  background: #fffbeb;
  color: #92400e;
  font-size: 13px;
  font-weight: 600;
}

.app-name-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.app-name-main {
  font-weight: 600;
  font-size: 14px;
  color: var(--notion-text);
}

.app-name-sub {
  font-size: 12px;
  color: var(--notion-text-secondary);
}

.description-cell {
  max-width: 360px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.col-date {
  white-space: nowrap;
  font-size: 13px;
  color: var(--notion-text-secondary);
}

.global-empty {
  text-align: center;
  padding: 72px 24px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  margin: 20px;
}

.global-empty-icon-wrapper {
  width: 64px;
  height: 64px;
  margin: 0 auto 20px;
  background: #f3f4f6;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.global-empty-title {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 8px;
}

.global-empty-text {
  font-size: 14px;
  color: #6b7280;
  max-width: 360px;
  margin: 0 auto 24px;
}

.rfc-form-modal {
  width: min(760px, calc(100vw - 32px));
  max-width: 760px;
  max-height: calc(100vh - 32px);
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
}

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

@media (max-width: 768px) {
  .uk-welcome-card {
    flex-direction: column;
    align-items: flex-start;
    margin: 0 12px 20px;
  }

  .btn-light-primary {
    width: 100%;
    justify-content: center;
  }

  .stats-grid {
    grid-template-columns: 1fr;
    margin: 12px 12px 16px;
  }

  .notice-card {
    margin: 0 12px 16px;
  }

  .uk-card-head {
    flex-direction: column;
    align-items: flex-start;
  }

  .uk-card-head .search-group {
    max-width: 100%;
    width: 100%;
  }

  .rfc-modal-actions {
    flex-direction: column-reverse;
  }

  .rfc-modal-actions .btn {
    width: 100%;
    justify-content: center;
  }
}
</style>
