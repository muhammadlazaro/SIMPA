<script setup>
import {
  Button,
  Modal,
  PasswordInput,
  TextField,
} from '@idds/vue'
import {
  IconCheck,
  IconPlus,
} from '@tabler/icons-vue'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useToastStore } from '../stores/toast'
import { useAuthStore } from '../stores/auth'
import { ROLE_DISPLAY_NAME } from '../constants/roles'
import { warnDev } from '../utils/logger'
import http from '../lib/http'
import ConfirmationDrawer from '../components/ConfirmationDrawer.vue'
import IconActionButton from '../components/IconActionButton.vue'
import IconActionCell from '../components/IconActionCell.vue'
import AsyncState from '../components/AsyncState.vue'
import DataTable from '../components/DataTable.vue'
import IddsSelect from '../components/IddsSelect.vue'
import MetricCard from '../components/MetricCard.vue'
import PageHeader from '../components/PageHeader.vue'
import PaginationBar from '../components/PaginationBar.vue'
import SearchField from '../components/SearchField.vue'
import StatusBadge from '../components/StatusBadge.vue'

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
const loadError = ref('')
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
  perPage: 30,
  total: 0,
})
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
const roleOptions = computed(() => ROLE_OPTIONS.map((role) => ({
  label: roleLabel(role),
  value: role,
})))
const roleFilterOptions = computed(() => [
  { label: 'Seluruh role', value: 'all' },
  ...roleOptions.value,
])
const statusFilterOptions = [
  { label: 'Seluruh status', value: 'all' },
  { label: 'Aktif', value: 'active' },
  { label: 'Nonaktif', value: 'inactive' },
]

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
  loadError.value = ''
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
    loadError.value = 'Daftar personil belum dapat dimuat. Periksa koneksi lalu coba lagi.'
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

function setQuickFilter({ role = 'all', status = 'all' } = {}) {
  filters.value.role = role
  filters.value.status = status
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

function handleFormModalChange(open) {
  if (!open) closeFormModal()
}

function handleConfirmModalChange(open) {
  if (!open) closeConfirm()
}
</script>

<template>
  <div class="ui-page">
    <PageHeader
      eyebrow="Admin Sistem"
      title="Manajemen personil"
      description="Kelola akun, role, dan status akses personil SIMPA."
    >
      <template #actions>
        <Button hierarchy="primary" size="lg" :prefix-icon="IconPlus" @click="openCreateModal">
          Tambah Personil
        </Button>
      </template>
    </PageHeader>

    <div class="ui-page-content">
      <section class="ui-metric-grid" aria-label="Ringkasan personil">
        <MetricCard
          label="Total Personil"
          :value="stats.total"
          icon="user"
          tone="blue"
          interactive
          :active="filters.role === 'all' && filters.status === 'all'"
          @select="setQuickFilter()"
        />
        <MetricCard
          label="Aktif"
          :value="stats.active"
          icon="check-circle"
          tone="green"
          interactive
          :active="filters.status === 'active' && filters.role === 'all'"
          @select="setQuickFilter({ status: 'active' })"
        />
        <MetricCard
          label="Nonaktif"
          :value="stats.inactive"
          icon="alert-circle"
          tone="red"
          interactive
          :active="filters.status === 'inactive' && filters.role === 'all'"
          @select="setQuickFilter({ status: 'inactive' })"
        />
        <MetricCard
          label="Admin Sistem"
          :value="adminSistemCount"
          icon="settings"
          tone="amber"
          interactive
          :active="filters.role === 'admin_sistem'"
          @select="setQuickFilter({ role: 'admin_sistem' })"
        />
      </section>

      <section class="ui-panel" aria-labelledby="personil-list-title">
        <header class="ui-panel-header">
          <div>
            <h2 id="personil-list-title">Daftar personil</h2>
            <p class="ui-table-subtitle">{{ pagination.total }} akun sesuai filter</p>
          </div>
          <div class="ui-panel-actions">
            <SearchField
              v-model="filters.q"
              label="Cari personil"
              placeholder="Cari personil"
              @update:model-value="scheduleSearch"
            />
            <IddsSelect
              v-model="filters.role"
              :options="roleFilterOptions"
              accessible-label="Filter role"
              placeholder="Semua role"
              width="190px"
              @change="applyFilter"
            />
            <IddsSelect
              v-model="filters.status"
              :options="statusFilterOptions"
              accessible-label="Filter status"
              placeholder="Semua status"
              width="170px"
              @change="applyFilter"
            />
          </div>
        </header>

        <AsyncState
          :loading="loading"
          :error="loadError"
          :empty="personil.length === 0"
          :empty-icon="hasActiveFilter ? 'search' : 'inbox'"
          :empty-title="hasActiveFilter ? 'Personil tidak ditemukan' : 'Belum ada personil'"
          :empty-description="hasActiveFilter
            ? 'Sesuaikan pencarian atau hapus filter yang aktif.'
            : 'Belum ada personil yang tercatat dalam sistem.'"
          @retry="loadPersonil(pagination.currentPage)"
        >
          <template v-if="hasActiveFilter" #action>
            <Button hierarchy="secondary" size="sm" @click="clearFilters">
              Hapus filter
            </Button>
          </template>

          <DataTable>
            <template #header>
              <tr>
                <th scope="col" class="col-num">#</th>
                <th scope="col">Nama</th>
                <th scope="col">Email</th>
                <th scope="col">Role</th>
                <th scope="col">Status</th>
                <th scope="col" class="ui-table-actions"><span class="sr-only">Aksi</span></th>
              </tr>
            </template>
            <template #body>
              <tr v-for="(row, idx) in personil" :key="row.id">
                <td data-label="Nomor" data-hide-mobile="true" class="col-num">{{ rowNumber(idx) }}</td>
                <td data-primary="true">
                  <span class="ui-table-primary">{{ row.name }}</span>
                  <span v-if="row.id === auth.user?.id" class="ui-table-subtitle">Akun Anda</span>
                </td>
                <td data-label="Email" class="personil-email">{{ row.email }}</td>
                <td data-label="Role">
                  <StatusBadge tone="neutral">{{ roleLabel(row.role) }}</StatusBadge>
                </td>
                <td data-label="Status">
                  <StatusBadge :tone="row.deleted_at ? 'danger' : 'success'">
                    {{ statusLabel(row) }}
                  </StatusBadge>
                </td>
                <td class="ui-table-actions">
                  <IconActionCell :label="`Aksi untuk ${row.name}`">
                    <IconActionButton label="Edit personil" icon="edit" @click="openEditModal(row)" />
                    <IconActionButton
                      v-if="row.deleted_at"
                      label="Aktifkan kembali"
                      icon="refresh"
                      tone="positive"
                      @click="openConfirm('restore', row)"
                    />
                    <IconActionButton
                      v-else
                      label="Nonaktifkan personil"
                      icon="user"
                      tone="danger"
                      :disabled="row.id === auth.user?.id"
                      @click="openConfirm('deactivate', row)"
                    />
                    <IconActionButton
                      label="Hapus permanen"
                      icon="trash"
                      tone="danger"
                      :disabled="row.id === auth.user?.id"
                      @click="openConfirm('force-delete', row)"
                    />
                  </IconActionCell>
                </td>
              </tr>
            </template>
          </DataTable>
        </AsyncState>

        <PaginationBar
          :page="pagination.currentPage"
          :last-page="pagination.lastPage"
          :total="pagination.total"
          item-label="personil"
          @change="loadPersonil"
        />
      </section>
    </div>

      <Modal
        :model-value="showFormModal"
        :title="editingPersonil ? 'Edit Personil' : 'Tambah Personil'"
        description="Lengkapi identitas dan hak akses personil."
        size="lg"
        variant="centered"
        :persistent="saving"
        @update:model-value="handleFormModalChange"
      >
        <form
          class="personil-form idds-personil-form"
          autocomplete="off"
          @submit.prevent="submitForm"
        >
          <div class="form-grid">
            <TextField
              v-model="form.name"
              label="Nama lengkap"
              placeholder="Masukkan nama lengkap"
              :max-length="255"
              required
              :status="fieldError('name') ? 'error' : 'neutral'"
              :status-message="fieldError('name')"
            />
            <TextField
              v-model="form.email"
              label="Email"
              placeholder="nama@instansi.go.id"
              type="email"
              :max-length="255"
              required
              :status="fieldError('email') ? 'error' : 'neutral'"
              :status-message="fieldError('email')"
            />
            <div class="form-group-wide">
              <IddsSelect
                v-model="form.role"
                :options="roleOptions"
                label="Role"
                placeholder="Pilih role"
                required
                width="100%"
                :status="fieldError('role') ? 'error' : 'neutral'"
                :status-message="fieldError('role')"
              />
            </div>
            <PasswordInput
              v-model="form.password"
              label="Password"
              placeholder="Masukkan password"
              :max-length="128"
              :required="!editingPersonil"
              :helper-text="editingPersonil ? 'Kosongkan jika password tidak diubah.' : 'Minimal 8 karakter dengan huruf besar, angka, dan simbol.'"
              :status="fieldError('password') ? 'error' : 'neutral'"
              :status-message="fieldError('password')"
            />
            <PasswordInput
              v-model="form.password_confirmation"
              label="Konfirmasi password"
              placeholder="Ulangi password"
              :max-length="128"
              :required="!editingPersonil || !!form.password"
            />
          </div>
          <div class="modal-actions">
            <Button hierarchy="secondary" size="lg" type="button" :disabled="saving" @click="closeFormModal">
              Batal
            </Button>
            <Button hierarchy="primary" size="lg" type="submit" :prefix-icon="IconCheck" :disabled="saving">
              {{ saving ? 'Menyimpan...' : 'Simpan personil' }}
            </Button>
          </div>
        </form>
      </Modal>

      <ConfirmationDrawer
        :model-value="confirmDialog.show"
        :title="confirmTitle()"
        :description="confirmMessage()"
        :subject="confirmDialog.target?.name || 'Personil'"
        :confirm-label="confirmButtonLabel()"
        :tone="confirmDialog.type === 'restore' ? 'positive' : 'danger'"
        :illustration="confirmDialog.type === 'restore' ? '/illustrations/empty-data.png' : '/illustrations/confirm-delete.png'"
        :loading="confirmDialog.loading"
        @update:model-value="handleConfirmModalChange"
        @confirm="submitConfirm"
        @cancel="closeConfirm"
      />
  </div>
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
  border-radius: 8px;
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

.stat-icon-wrap.bg-blue { background: #eff6ff; color: #1d4ed8; }
.stat-icon-wrap.bg-green { background: #f0fdf4; color: #047857; }
.stat-icon-wrap.bg-amber { background: #fffbeb; color: #92400e; }
.stat-icon-wrap.bg-red { background: #fef2f2; color: #b91c1c; }

.stat-label {
  font-size: var(--idds-caption-size);
  color: #6b7280;
  font-weight: var(--idds-weight-medium);
  line-height: var(--idds-caption-line);
}

.stat-value {
  font-size: var(--idds-heading-h4-size);
  font-weight: var(--idds-weight-bold);
  color: #111827;
  line-height: var(--idds-heading-h4-line);
}

.workspace-hero-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
  background: var(--ina-background-primary);
  border-radius: 8px;
  padding: 24px 28px;
  margin: 0 20px 20px;
  box-shadow: 0 4px 14px rgba(30, 58, 138, 0.18);
}

.workspace-hero-breadcrumb {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 8px;
  font-size: var(--idds-caption-small-size);
  line-height: var(--idds-caption-small-line);
}

.ah-bc-link {
  background: none;
  border: none;
  color: rgba(255, 255, 255, 0.55);
  cursor: pointer;
  padding: 0;
  font-size: var(--idds-caption-small-size);
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: color 0.15s;
  line-height: var(--idds-caption-small-line);
}

.ah-bc-link:hover { color: rgba(255, 255, 255, 0.9); }
.ah-bc-sep { color: rgba(255, 255, 255, 0.35); }
.ah-bc-current { color: rgba(255, 255, 255, 0.8); font-weight: var(--idds-weight-medium); }

.workspace-hero-title {
  margin: 0 0 4px;
  font-size: var(--idds-body-large-size);
  font-weight: var(--idds-weight-bold);
  color: #fff;
  line-height: var(--idds-body-large-line);
}

.workspace-hero-sub {
  margin: 0;
  font-size: var(--idds-caption-size);
  color: rgba(255, 255, 255, 0.7);
  line-height: var(--idds-caption-line);
}

.personil-name-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.personil-name-main {
  font-weight: var(--idds-weight-semibold);
  color: var(--ina-content-primary);
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

.personil-name-sub {
  font-size: var(--idds-caption-small-size);
  color: var(--ina-primary-primary);
  line-height: var(--idds-caption-small-line);
}

.personil-email {
  color: var(--ina-content-secondary);
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
  font-size: var(--idds-caption-small-size);
  line-height: var(--idds-caption-small-line);
}

.field-help {
  display: block;
  margin-top: 6px;
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-small-size);
  line-height: var(--idds-caption-small-line);
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 18px;
  margin-top: 18px;
  border-top: 1px solid var(--ina-stroke-primary);
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

  .form-grid {
    grid-template-columns: 1fr;
  }

  .data-card-head-actions {
    flex-direction: column;
  }
}
</style>
