<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToastStore } from '../stores/toast'
import { useAuthStore } from '../stores/auth'
import { ROLE_DISPLAY_NAME } from '../constants/roles'
import { usePagination } from '../composables/usePagination'
import { warnDev } from '../utils/logger'
import http from '../lib/http'
import AdminSistemLayout from '../layouts/AdminSistemLayout.vue'
import DataCardHead from '../components/DataCardHead.vue'
import DataTable from '../components/DataTable.vue'
import Icons from '../components/Icons.vue'

const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()

const ROLE_OPTIONS = [
  'admin_sistem',
  'pengelola_aplikasi',
  'analis_desain',
  'unit_kerja',
  'tim_implementasi_aplikasi',
  'devops_developer',
  'tim_uji_keamanan',
]

const personil = ref([])
const loading = ref(false)
const saving = ref(false)
const filters = ref({
  q: '',
  role: 'all',
  status: 'all',
})
const stats = ref({
  total: 0,
  active: 0,
  inactive: 0,
  roles: {},
})
const pagination = ref({
  currentPage: 1,
  lastPage: 1,
  perPage: 10,
  total: 0,
})
const { pageNumbers } = usePagination(pagination)

const showFormModal = ref(false)
const editingPersonil = ref(null)
const formErrors = ref({})
const form = ref(defaultForm())

const confirmDialog = ref({
  show: false,
  type: null,
  target: null,
  loading: false,
})

let searchTimer = null

const hasActiveFilter = computed(() =>
  !!filters.value.q.trim() || filters.value.role !== 'all' || filters.value.status !== 'all'
)

const adminSistemCount = computed(() => stats.value.roles?.admin_sistem || 0)

onMounted(async () => {
  await Promise.all([loadStats(), loadPersonil()])
})

onBeforeUnmount(() => {
  if (searchTimer) clearTimeout(searchTimer)
})

function defaultForm() {
  return {
    name: '',
    email: '',
    role: 'tim_implementasi_aplikasi',
    password: '',
    password_confirmation: '',
  }
}

async function loadStats() {
  try {
    const response = await http.get('/personil/stats')
    stats.value = response.data?.data || stats.value
  } catch (error) {
    warnDev('[AdminSistemDashboard] loadStats error:', error)
    toast.push('Gagal memuat statistik personil', 'error')
  }
}

async function loadPersonil(page = 1) {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: String(page),
      per_page: String(pagination.value.perPage),
      status: filters.value.status,
    })

    if (filters.value.q.trim()) params.set('q', filters.value.q.trim())
    if (filters.value.role !== 'all') params.set('role', filters.value.role)

    const response = await http.get(`/personil?${params.toString()}`)
    const meta = response.data?.meta || {}
    personil.value = response.data?.data || []
    pagination.value = {
      currentPage: Number(meta.current_page) || page,
      lastPage: Number(meta.last_page) || 1,
      perPage: Number(meta.per_page) || pagination.value.perPage,
      total: Number(meta.total) || 0,
    }
  } catch (error) {
    warnDev('[AdminSistemDashboard] loadPersonil error:', error)
    toast.push('Gagal memuat daftar personil', 'error')
  } finally {
    loading.value = false
  }
}

function scheduleSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadPersonil(1), 350)
}

function applyFilter() {
  loadPersonil(1)
}

function clearFilters() {
  filters.value = { q: '', role: 'all', status: 'all' }
  loadPersonil(1)
}

function rowNumber(idx) {
  const start = ((pagination.value.currentPage - 1) * pagination.value.perPage) + 1
  return start + idx
}

function roleLabel(role) {
  return ROLE_DISPLAY_NAME[role] || role || '-'
}

function statusLabel(row) {
  return row.deleted_at ? 'Nonaktif' : 'Aktif'
}

function confirmTitle() {
  if (confirmDialog.value.type === 'restore') return 'Aktifkan Personil'
  if (confirmDialog.value.type === 'force-delete') return 'Hapus Permanen Personil'
  return 'Nonaktifkan Personil'
}

function confirmMessage() {
  if (confirmDialog.value.type === 'restore') return 'Aktifkan kembali akses personil berikut?'
  if (confirmDialog.value.type === 'force-delete') return 'Hapus permanen akun personil berikut dari sistem? Aksi ini tidak dapat dibatalkan.'
  return 'Nonaktifkan akses personil berikut?'
}

function confirmButtonLabel() {
  if (confirmDialog.value.loading) return 'Memproses...'
  if (confirmDialog.value.type === 'restore') return 'Aktifkan'
  if (confirmDialog.value.type === 'force-delete') return 'Hapus Permanen'
  return 'Nonaktifkan'
}

function confirmButtonIcon() {
  return confirmDialog.value.type === 'restore' ? 'refresh' : 'trash'
}

function openCreateModal() {
  editingPersonil.value = null
  form.value = defaultForm()
  formErrors.value = {}
  showFormModal.value = true
}

function openEditModal(row) {
  editingPersonil.value = row
  form.value = {
    name: row.name || '',
    email: row.email || '',
    role: row.role || 'tim_implementasi_aplikasi',
    password: '',
    password_confirmation: '',
  }
  formErrors.value = {}
  showFormModal.value = true
}

function closeFormModal() {
  if (saving.value) return
  showFormModal.value = false
  editingPersonil.value = null
  formErrors.value = {}
}

async function submitForm() {
  saving.value = true
  formErrors.value = {}
  try {
    const payload = {
      name: form.value.name,
      email: form.value.email,
      role: form.value.role,
    }

    if (!editingPersonil.value || form.value.password) {
      payload.password = form.value.password
      payload.password_confirmation = form.value.password_confirmation
    }

    if (editingPersonil.value) {
      await http.put(`/personil/${editingPersonil.value.id}`, payload)
      toast.push('Personil berhasil diperbarui', 'success')
    } else {
      await http.post('/personil', payload)
      toast.push('Personil berhasil ditambahkan', 'success')
    }

    closeFormModal()
    await Promise.all([loadStats(), loadPersonil(pagination.value.currentPage)])
  } catch (error) {
    if (error?.response?.status === 422) {
      formErrors.value = error.response.data?.errors || {}
      toast.push('Periksa kembali isian personil', 'warning')
    } else {
      toast.push(error?.response?.data?.message || 'Gagal menyimpan personil', 'error')
    }
  } finally {
    saving.value = false
  }
}

function openConfirm(type, target) {
  confirmDialog.value = {
    show: true,
    type,
    target,
    loading: false,
  }
}

function closeConfirm(force = false) {
  if (confirmDialog.value.loading && !force) return
  confirmDialog.value = { show: false, type: null, target: null, loading: false }
}

async function submitConfirm() {
  const target = confirmDialog.value.target
  if (!target) return

  confirmDialog.value.loading = true
  try {
    if (confirmDialog.value.type === 'restore') {
      await http.post(`/personil/${target.id}/restore`)
      toast.push('Personil berhasil diaktifkan kembali', 'success')
    } else if (confirmDialog.value.type === 'force-delete') {
      await http.delete(`/personil/${target.id}/force`)
      toast.push('Personil berhasil dihapus permanen', 'success')
    } else {
      await http.delete(`/personil/${target.id}`)
      toast.push('Personil berhasil dinonaktifkan', 'success')
    }

    closeConfirm(true)
    await Promise.all([loadStats(), loadPersonil(pagination.value.currentPage)])
  } catch (error) {
    toast.push(error?.response?.data?.message || 'Gagal memproses personil', 'error')
  } finally {
    confirmDialog.value.loading = false
  }
}

function fieldError(name) {
  const value = formErrors.value?.[name]
  return Array.isArray(value) ? value[0] : value
}
</script>

<template>
  <AdminSistemLayout>
    <div class="container workspace-dashboard">
      <div class="workspace-hero-card">
        <div class="workspace-hero-text">
          <nav class="workspace-hero-breadcrumb" aria-label="breadcrumb">
            <button @click="router.push('/admin-sistem')" class="ah-bc-link">
              <Icons name="dashboard" :size="12" />
              Dashboard
            </button>
            <span class="ah-bc-sep">/</span>
            <span class="ah-bc-current">Personil</span>
          </nav>
          <h2 class="workspace-hero-title">Manajemen Personil</h2>
          <p class="workspace-hero-sub">Kelola akun, role, dan status akses personil SIMPA.</p>
        </div>
      </div>

      <div class="content-section active">
        <div class="stats-grid">
          <div class="stat-card total">
            <div class="stat-header">
              <span class="stat-label">Total Personil</span>
              <div class="stat-icon-wrap bg-blue">
                <Icons name="user" :size="18" />
              </div>
            </div>
            <div class="stat-value">{{ stats.total }}</div>
          </div>

          <div class="stat-card production">
            <div class="stat-header">
              <span class="stat-label">Aktif</span>
              <div class="stat-icon-wrap bg-green">
                <Icons name="check-circle" :size="18" />
              </div>
            </div>
            <div class="stat-value">{{ stats.active }}</div>
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

          <div class="stat-card dev">
            <div class="stat-header">
              <span class="stat-label">Admin Sistem</span>
              <div class="stat-icon-wrap bg-amber">
                <Icons name="settings" :size="18" />
              </div>
            </div>
            <div class="stat-value">{{ adminSistemCount }}</div>
          </div>
        </div>

        <div class="card">
          <DataCardHead title="Daftar Personil">
            <template #actions>
              <div class="search-group">
                <span class="search-icon">
                  <Icons name="search" :size="16" />
                </span>
                <input
                  v-model="filters.q"
                  type="text"
                  placeholder="Cari personil..."
                  maxlength="100"
                  aria-label="Cari personil"
                  @input="scheduleSearch"
                />
              </div>

              <select v-model="filters.role" class="filter-select" aria-label="Filter role" @change="applyFilter">
                <option value="all">Semua Role</option>
                <option v-for="role in ROLE_OPTIONS" :key="role" :value="role">
                  {{ roleLabel(role) }}
                </option>
              </select>

              <select v-model="filters.status" class="filter-select" aria-label="Filter status" @change="applyFilter">
                <option value="all">Semua Status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
              </select>

              <button class="btn btn-primary" @click="openCreateModal">
                <Icons name="plus" :size="16" />
                Tambah Personil
              </button>
            </template>
          </DataCardHead>

          <div v-if="loading" class="loading-state">
            <div class="loading-spinner"></div>
            <p>Memuat data personil...</p>
          </div>

          <div v-else-if="personil.length === 0" class="global-empty">
            <div class="global-empty-icon-wrapper">
              <Icons :name="hasActiveFilter ? 'search' : 'inbox'" :size="48" class="global-empty-icon" />
            </div>
            <h3 class="global-empty-title">{{ hasActiveFilter ? 'Tidak Ada Hasil' : 'Belum Ada Personil' }}</h3>
            <p class="global-empty-text">
              {{ hasActiveFilter ? 'Tidak ada personil yang cocok dengan filter ini.' : 'Belum ada personil yang tercatat dalam sistem.' }}
            </p>
            <button v-if="hasActiveFilter" type="button" class="btn btn-secondary" @click="clearFilters">
              Hapus filter
            </button>
            <button v-else type="button" class="btn btn-primary" @click="openCreateModal">
              <Icons name="plus" :size="16" />
              Tambah Personil
            </button>
          </div>

          <div v-else>
            <DataTable>
              <thead>
                <tr>
                  <th scope="col" class="col-num">#</th>
                  <th scope="col">Nama</th>
                  <th scope="col">Email</th>
                  <th scope="col">Role</th>
                  <th scope="col">Status</th>
                  <th scope="col" class="col-aksi">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, idx) in personil" :key="row.id" class="data-table-row">
                  <td class="col-num">{{ rowNumber(idx) }}</td>
                  <td>
                    <div class="personil-name-cell">
                      <span class="personil-name-main">{{ row.name }}</span>
                      <span v-if="row.id === auth.user?.id" class="personil-name-sub">Akun Anda</span>
                    </div>
                  </td>
                  <td class="personil-email">{{ row.email }}</td>
                  <td>
                    <span class="badge badge-info">{{ roleLabel(row.role) }}</span>
                  </td>
                  <td>
                    <span :class="['badge', row.deleted_at ? 'badge-secondary' : 'badge-success']">
                      {{ statusLabel(row) }}
                    </span>
                  </td>
                  <td>
                    <div class="action-group">
                      <button type="button" class="action-btn table-action-btn edit-btn" @click="openEditModal(row)">
                        <Icons name="edit" :size="14" />
                        Edit
                      </button>
                      <button
                        v-if="row.deleted_at"
                        type="button"
                        class="action-btn table-action-btn view-btn"
                        @click="openConfirm('restore', row)"
                      >
                        <Icons name="refresh" :size="14" />
                        Aktifkan
                      </button>
                      <button
                        v-else
                        type="button"
                        class="action-btn table-action-btn delete-btn"
                        :disabled="row.id === auth.user?.id"
                        @click="openConfirm('deactivate', row)"
                      >
                        <Icons name="trash" :size="14" />
                        Nonaktifkan
                      </button>
                      <button
                        type="button"
                        class="action-btn table-action-btn delete-btn permanent-delete-btn"
                        :disabled="row.id === auth.user?.id"
                        @click="openConfirm('force-delete', row)"
                      >
                        <Icons name="trash" :size="14" />
                        Hapus
                      </button>
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
              <button
                @click="loadPersonil(pagination.currentPage - 1)"
                :disabled="pagination.currentPage === 1"
                class="pagination-btn"
              >
                <Icons name="chevron-left" :size="16" />
              </button>

              <button
                v-for="page in pageNumbers"
                :key="page"
                @click="page !== '...' && loadPersonil(page)"
                :class="['pagination-btn', { active: page === pagination.currentPage, disabled: page === '...' }]"
              >
                {{ page }}
              </button>

              <button
                @click="loadPersonil(pagination.currentPage + 1)"
                :disabled="pagination.currentPage === pagination.lastPage"
                class="pagination-btn"
              >
                <Icons name="chevron-right" :size="16" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <dialog v-if="showFormModal" class="modal active" open aria-labelledby="personil-modal-title" @click.self="closeFormModal">
        <div class="modal-content personil-modal">
          <div class="modal-header">
            <h3 id="personil-modal-title">{{ editingPersonil ? 'Edit Personil' : 'Tambah Personil' }}</h3>
            <button class="close-btn" type="button" aria-label="Tutup modal" @click="closeFormModal">&times;</button>
          </div>
          <form class="personil-form" @submit.prevent="submitForm">
            <div class="form-grid">
              <div class="form-group">
                <label>Nama</label>
                <input v-model.trim="form.name" type="text" maxlength="255" class="form-control" required />
                <small v-if="fieldError('name')" class="field-error">{{ fieldError('name') }}</small>
              </div>

              <div class="form-group">
                <label>Email</label>
                <input v-model.trim="form.email" type="email" maxlength="255" class="form-control" required />
                <small v-if="fieldError('email')" class="field-error">{{ fieldError('email') }}</small>
              </div>

              <div class="form-group form-group-wide">
                <label>Role</label>
                <select v-model="form.role" class="form-control" required>
                  <option v-for="role in ROLE_OPTIONS" :key="role" :value="role">
                    {{ roleLabel(role) }}
                  </option>
                </select>
                <small v-if="fieldError('role')" class="field-error">{{ fieldError('role') }}</small>
              </div>

              <div class="form-group">
                <label>Password</label>
                <input
                  v-model="form.password"
                  type="password"
                  maxlength="128"
                  class="form-control"
                  :required="!editingPersonil"
                  autocomplete="new-password"
                />
                <small v-if="editingPersonil" class="field-help">Kosongkan jika tidak diganti.</small>
                <small v-if="fieldError('password')" class="field-error">{{ fieldError('password') }}</small>
              </div>

              <div class="form-group">
                <label>Konfirmasi Password</label>
                <input
                  v-model="form.password_confirmation"
                  type="password"
                  maxlength="128"
                  class="form-control"
                  :required="!editingPersonil || !!form.password"
                  autocomplete="new-password"
                />
              </div>
            </div>

            <div class="modal-actions">
              <button class="btn btn-secondary" type="button" :disabled="saving" @click="closeFormModal">
                Batal
              </button>
              <button class="btn btn-primary" type="submit" :disabled="saving">
                <Icons name="check" :size="14" />
                {{ saving ? 'Menyimpan...' : 'Simpan' }}
              </button>
            </div>
          </form>
        </div>
      </dialog>

      <dialog v-if="confirmDialog.show" class="modal active" open aria-labelledby="personil-confirm-title" @click.self="closeConfirm">
        <div class="modal-content confirm-modal">
          <div class="confirm-header">
            <Icons :name="confirmDialog.type === 'restore' ? 'refresh' : 'alert'" :size="48" class="confirm-icon" />
            <h3 id="personil-confirm-title">{{ confirmTitle() }}</h3>
          </div>
          <div class="confirm-body">
            <p>{{ confirmMessage() }}</p>
            <p class="confirm-target"><strong>{{ confirmDialog.target?.name }}</strong></p>
          </div>
          <div class="confirm-actions">
            <button class="btn btn-secondary" :disabled="confirmDialog.loading" @click="closeConfirm">
              Batal
            </button>
            <button
              class="btn"
              :class="confirmDialog.type === 'restore' ? 'btn-primary' : 'btn-danger'"
              :disabled="confirmDialog.loading"
              @click="submitConfirm"
            >
              <Icons :name="confirmButtonIcon()" :size="14" />
              {{ confirmButtonLabel() }}
            </button>
          </div>
        </div>
      </dialog>
    </div>
  </AdminSistemLayout>
</template>

<style scoped>
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 20px;
  margin: 12px 20px 16px;
}

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
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
  overflow: hidden;
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
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  border-color: #d1d5db;
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

.filter-select {
  min-width: 150px;
  height: 40px;
  border: 1px solid #d8dee9;
  border-radius: 8px;
  background: #fff;
  color: var(--notion-text);
  font-size: 14px;
  padding: 0 12px;
}

.personil-name-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.personil-name-main {
  font-weight: 600;
  color: var(--notion-text);
  font-size: 14px;
}

.personil-name-sub {
  font-size: 12px;
  color: var(--notion-blue);
}

.personil-email {
  color: var(--notion-text-secondary);
  white-space: nowrap;
}

.personil-modal {
  max-width: 720px;
}

.personil-form {
  padding: 20px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.form-group-wide {
  grid-column: 1 / -1;
}

.field-error {
  display: block;
  margin-top: 6px;
  color: #dc2626;
  font-size: 12px;
}

.field-help {
  display: block;
  margin-top: 6px;
  color: var(--notion-text-secondary);
  font-size: 12px;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 18px;
  margin-top: 18px;
  border-top: 1px solid var(--notion-border);
}

.action-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: 1fr;
    margin: 12px 12px 16px;
  }

  .workspace-hero-card {
    flex-direction: column;
    align-items: flex-start;
    margin: 0 12px 16px;
  }

  .filter-select {
    width: 100%;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .data-card-head-actions {
    flex-direction: column;
  }
}
</style>
