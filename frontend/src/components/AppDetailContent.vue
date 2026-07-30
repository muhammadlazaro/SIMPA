<script setup>
import { ref, onMounted, onBeforeUnmount, watch, computed, nextTick } from 'vue'
import {
  Accordion,
  Alert,
  Button,
  Checkbox,
  Modal,
  SingleFileUpload,
  TabHorizontal,
  TextArea as IddsTextArea,
  TextField,
} from '@idds/vue'
import {
  IconCheck,
  IconPlus,
  IconRocket,
  IconTrash,
} from '@tabler/icons-vue'
import { useRoute, useRouter } from 'vue-router'
import http from '../lib/http'
import { resolveIddsFileSelection } from '../utils/fileUpload'
import AsyncState from './AsyncState.vue'
import ConfirmationDrawer from './ConfirmationDrawer.vue'
import DiscussionThread from './DiscussionThread.vue'
import Icons from './Icons.vue'
import DetailInfoGrid from './DetailInfoGrid.vue'
import PageHeader from './PageHeader.vue'
import { useToastStore } from '../stores/toast'
import { useAuthStore } from '../stores/auth'
import { getHomeByRole } from '../constants/roles'
import { warnDev } from '../utils/logger'
import { getShortStatusLabel } from '../constants/status'

const props = defineProps({
  /** Halaman detail saat dibuka oleh role Analis Desain (layout Analis). */
  analystMode: { type: Boolean, default: false },
  /** Halaman detail saat dibuka oleh role Unit Kerja. */
  unitKerjaMode: { type: Boolean, default: false },
  /** Halaman detail saat dibuka oleh role Tim Uji Keamanan. */
  securityMode: { type: Boolean, default: false },
  /** Halaman detail saat dibuka oleh role DevOps Developer. */
  devopsMode: { type: Boolean, default: false },
  /** Halaman detail saat dibuka oleh role Tim Implementasi Aplikasi. */
  implementationMode: { type: Boolean, default: false },
  /** Halaman detail saat dibuka oleh role Pengelola Aplikasi. */
  pengelolaMode: { type: Boolean, default: false },
})

const toastStore = useToastStore()
const auth = useAuthStore()


const route = useRoute()
const router = useRouter()
const loading = ref(false)
const loadError = ref('')
const app = ref(null)
const activeTab = ref('informasi')
const activeTechnicalConfigTab = ref('proyek')
const loadingDocuments = ref(false)
const uploadingType = ref('')
const selectedFiles = ref({})
const documentsByType = ref({})
const loadingImplementationChecklist = ref(false)
const savingImplementationChecklist = ref(false)
const updatingImplementationChecklistId = ref(null)
const implementationChecklistItems = ref([])
const implementationChecklistCategory = ref('')
const newImplementationItem = ref({ title: '', notes: '' })
const implementationItemTitleTouched = ref(false)
const implementationTitleInput = ref(null)
const implementationTitleLimit = 120
const implementationNotesLimit = 240
const loadingDeploymentStatus = ref(false)
const deploymentStatus = ref({
  staging: { deployed: false, deployed_at: null, deployed_by: null },
  production: { deployed: false, deployed_at: null, deployed_by: null },
  notes: '',
  history: []
})
const deploymentHistory = ref([])
const loadingSecurityReview = ref(false)
const savingSecurityReview = ref(false)
const securityReview = ref({
  security_test_passed: null,
  security_tested_at: null,
  security_test_notes: '',
  security_tester: null,
})
const securityReviewForm = ref(createEmptySecurityReviewForm())
const securityNotes = ref([])
const isSecurityReviewEditable = computed(() => (
  app.value?.status === 'uji_keamanan'
  && securityReview.value.security_test_passed === null
))

function createEmptySecurityReviewForm(status = null) {
  return {
    security_test_passed: status,
    security_test_notes: '',
    note: '',
  }
}

// ==== WORKFLOW (Checklist & Catatan) - untuk semua role selain unit_kerja ====
const workflowLoading = ref(false)
const workflowError = ref('')
const checklistForm = ref({ title: '', notes: '' })
const savingChecklist = ref(false)
const updatingChecklistId = ref(null)
const confirmingDeleteChecklist = ref(null)
const confirmingDeleteImplementationChecklist = ref(null)

// ==== WORKFLOW ACTIONS ====
const showActionModal = ref(false)
const selectedAction = ref(null)
const actionCatatan = ref('')
const actionPayload = ref({})
const isSubmittingAction = ref(false)

// ==== DEPLOY CONFIRMATION MODAL ====
const showDeployModal = ref(false)
const deployModalEnv = ref('') // 'staging' | 'production'
const deployModalNote = ref('')
const isSubmittingDeploy = ref(false)
const showDeactivateModal = ref(false)
const deactivateNote = ref('')
const isSubmittingDeactivate = ref(false)

function openDeployModal(env) {
  deployModalEnv.value = env
  deployModalNote.value = ''
  showDeployModal.value = true
}
function closeDeployModal() {
  showDeployModal.value = false
  deployModalEnv.value = ''
  deployModalNote.value = ''
}

async function confirmDeploy() {
  const env = deployModalEnv.value
  if (!env) return
  isSubmittingDeploy.value = true
  try {
    // Simpan status deployment (staging/production)
    await http.put(`/aplikasi/${route.params.id}/deployment-status`, {
      environment: env,
      deployed: true,
      notes: deployModalNote.value?.trim() || null,
    })
    
    // Update local deployment status immediately (Opsi 1: Keep visible)
    const now = new Date().toISOString()
    const deployedBy = auth.user?.name || 'System'
    
    deploymentStatus.value[env] = {
      deployed: true,
      deployed_at: now,
      deployed_by: { name: deployedBy }
    }
    
    // Update status workflow lokal sesuai environment deployment.
    if (env === 'production') {
      if (app.value) app.value.status = 'deployed_production'
      toastStore.push('Aplikasi berhasil dideploy ke Production. Status workflow diperbarui.', 'success')
    } else {
      if (app.value) app.value.status = 'deployed_staging'
      toastStore.push('Staging berhasil dideploy. Deployment status telah diperbarui.', 'success')
    }
    

    
    // Close modal dan refresh deployment history
    closeDeployModal()
    await loadDeploymentStatus()
    
  } catch (error) {
    const msg = error?.response?.data?.message || `Gagal mengonfirmasi deployment ${env}.`
    toastStore.push(msg, 'error')
  } finally {
    isSubmittingDeploy.value = false
  }
}

const ACTIONS_BY_ROLE_AND_STATUS = {
  'unit_kerja:perlu_perbaikan_pengajuan': [
    {
      label: 'Perbaiki Pengajuan',
      endpoint: '/workflow/perbaikan-pengajuan',
      btnClass: 'btn-primary',
      requiresNote: true,
      noteLabel: 'Catatan Perbaikan',
    },
  ],
  'pengelola_aplikasi:diajukan': [
    { label: 'Setujui Pengajuan', endpoint: '/workflow/verifikasi-pengajuan', payload: { status_target: 'terverifikasi' }, btnClass: 'btn-success', requiresNote: true },
    { label: 'Minta Perbaikan', endpoint: '/workflow/verifikasi-pengajuan', payload: { status_target: 'perlu_perbaikan_pengajuan' }, btnClass: 'btn-warning', requiresNote: true },
    { label: 'Tolak Pengajuan', endpoint: '/workflow/verifikasi-pengajuan', payload: { status_target: 'ditolak' }, btnClass: 'btn-danger', requiresNote: true },
  ],
  'analis_desain:terverifikasi': [
    { label: 'Mulai Analisis Desain', endpoint: '/workflow/mulai-analisa-desain', btnClass: 'btn-primary', requiresNote: false },
  ],
  'analis_desain:analisa_desain': [
    { label: 'Nyatakan Layak', endpoint: '/workflow/studi-kelayakan', payload: { is_layak: true }, btnClass: 'btn-success', requiresNote: true },
    { label: 'Nyatakan Tidak Layak', endpoint: '/workflow/studi-kelayakan', payload: { is_layak: false }, btnClass: 'btn-danger', requiresNote: true },
  ],
  'tim_implementasi_aplikasi:layak': [
    { label: 'Mulai Pengembangan', endpoint: '/workflow/mulai-pengembangan', btnClass: 'btn-primary', requiresNote: true },
  ],
  'tim_implementasi_aplikasi:pengembangan': [
    { label: 'Tandai Siap UAT', endpoint: '/workflow/siap-uat', btnClass: 'btn-success', requiresNote: true },
  ],
  'pengelola_aplikasi:uat': [
    { label: 'UAT Sesuai', endpoint: '/workflow/verifikasi-uat', payload: { is_sesuai: true }, btnClass: 'btn-success', requiresNote: true },
    { label: 'UAT Perlu Perbaikan', endpoint: '/workflow/verifikasi-uat', payload: { is_sesuai: false }, btnClass: 'btn-warning', requiresNote: true },
  ],
  'tim_implementasi_aplikasi:perbaikan_uat': [
    { label: 'Selesai Perbaikan UAT', endpoint: '/workflow/selesai-perbaikan-uat', btnClass: 'btn-primary', requiresNote: true },
  ],
  'tim_uji_keamanan:uji_keamanan': [
    { label: 'Uji Keamanan Lolos', endpoint: '/workflow/hasil-uji-keamanan', payload: { is_lolos: true }, btnClass: 'btn-success', requiresNote: true },
    { label: 'Uji Keamanan Tidak Lolos', endpoint: '/workflow/hasil-uji-keamanan', payload: { is_lolos: false }, btnClass: 'btn-danger', requiresNote: true },
  ],
  'tim_implementasi_aplikasi:perbaikan_keamanan': [
    { label: 'Selesai Perbaikan Keamanan', endpoint: '/workflow/selesai-perbaikan-keamanan', btnClass: 'btn-primary', requiresNote: true },
  ],
}

const availableActions = computed(() => {
  const key = `${auth.role}:${app.value?.status || ''}`
  return ACTIONS_BY_ROLE_AND_STATUS[key] || []
})

const workflowActionVisuals = {
  'Perbaiki Pengajuan': {
    src: '/illustrations/workflow-revision.png',
    alt: 'Petugas memperbaiki dokumen pengajuan',
    description: 'Perbarui pengajuan sesuai catatan pemeriksa sebelum dikirim kembali.',
  },
  'Setujui Pengajuan': {
    src: '/illustrations/workflow-approve.png',
    alt: 'Petugas menyetujui dokumen pengajuan',
    description: 'Pastikan identitas dan dokumen pengajuan sudah lengkap sebelum diteruskan ke Analis Desain.',
  },
  'Minta Perbaikan': {
    src: '/illustrations/workflow-revision.png',
    alt: 'Petugas menandai dokumen yang perlu diperbaiki',
    description: 'Tuliskan perbaikan yang diperlukan agar Unit Kerja dapat menindaklanjutinya dengan jelas.',
  },
  'Tolak Pengajuan': {
    src: '/illustrations/workflow-reject.png',
    alt: 'Petugas menolak dokumen pengajuan',
    description: 'Pengajuan yang ditolak tidak dapat melanjutkan workflow. Pastikan alasan penolakan sudah tepat.',
  },
  'Mulai Analisis Desain': {
    src: '/illustrations/workflow-analysis.png',
    alt: 'Analis memulai analisis desain aplikasi',
    description: 'Mulai penyusunan rancangan teknis dan laporan analisis untuk aplikasi ini.',
  },
  'Nyatakan Layak': {
    src: '/illustrations/workflow-approve.png',
    alt: 'Analis menyatakan aplikasi layak',
    description: 'Pastikan analisis dan laporan desain sudah lengkap sebelum aplikasi diteruskan ke pengembangan.',
  },
  'Nyatakan Tidak Layak': {
    src: '/illustrations/workflow-reject.png',
    alt: 'Analis menyatakan aplikasi tidak layak',
    description: 'Aplikasi tidak akan diteruskan ke pengembangan. Tuliskan dasar keputusan secara jelas.',
  },
  'Mulai Pengembangan': {
    src: '/illustrations/workflow-development.png',
    alt: 'Pengembang memulai pengerjaan aplikasi',
    description: 'Pastikan rancangan teknis sudah dipahami sebelum pekerjaan implementasi dimulai.',
  },
  'Tandai Siap UAT': {
    src: '/illustrations/workflow-testing.png',
    alt: 'Petugas menyiapkan pengujian aplikasi',
    description: 'Pastikan implementasi, checklist, dan dokumen pengujian sudah siap untuk UAT.',
  },
  'UAT Sesuai': {
    src: '/illustrations/workflow-testing.png',
    alt: 'Petugas menyelesaikan validasi UAT',
    description: 'Konfirmasikan bahwa hasil UAT telah sesuai sebelum aplikasi masuk ke uji keamanan.',
  },
  'UAT Perlu Perbaikan': {
    src: '/illustrations/workflow-revision.png',
    alt: 'Petugas mencatat perbaikan hasil UAT',
    description: 'Tuliskan temuan UAT yang harus diperbaiki oleh Tim Implementasi.',
  },
  'Selesai Perbaikan UAT': {
    src: '/illustrations/workflow-testing.png',
    alt: 'Petugas memvalidasi perbaikan UAT',
    description: 'Pastikan seluruh temuan UAT telah ditangani sebelum pengujian dilanjutkan.',
  },
  'Uji Keamanan Lolos': {
    src: '/illustrations/workflow-testing.png',
    alt: 'Petugas menyelesaikan uji keamanan aplikasi',
    description: 'Konfirmasikan bahwa hasil pengujian keamanan memenuhi persyaratan untuk deployment.',
  },
  'Uji Keamanan Tidak Lolos': {
    src: '/illustrations/workflow-reject.png',
    alt: 'Petugas menemukan masalah keamanan aplikasi',
    description: 'Tuliskan temuan keamanan yang harus diperbaiki sebelum dilakukan pengujian ulang.',
  },
  'Selesai Perbaikan Keamanan': {
    src: '/illustrations/workflow-testing.png',
    alt: 'Petugas memvalidasi perbaikan keamanan',
    description: 'Pastikan seluruh temuan keamanan sudah ditangani sebelum aplikasi diuji kembali.',
  },
}

const selectedActionVisual = computed(() => workflowActionVisuals[selectedAction.value?.label] || {
  src: '/illustrations/empty-data.png',
  alt: 'Petugas memproses aplikasi',
  description: `Lanjutkan proses untuk aplikasi ${app.value?.nama_aplikasi || ''}?`,
})

function openActionModal(action) {
  selectedAction.value = action
  actionCatatan.value = ''
  actionPayload.value = action.payload || {}
  showActionModal.value = true
}

function closeActionModal() {
  showActionModal.value = false
  selectedAction.value = null
}

async function submitAction() {
  if (selectedAction.value.requiresNote && !actionCatatan.value.trim()) {
    toastStore.push('Catatan wajib diisi', 'warning')
    return
  }

  isSubmittingAction.value = true
  try {
    const payload = { ...actionPayload.value }
    if (selectedAction.value.requiresNote) {
      payload.catatan = actionCatatan.value
    }
    
    await http.post(`/aplikasi/${app.value.id}${selectedAction.value.endpoint}`, payload)
    toastStore.push(`Berhasil melakukan: ${selectedAction.value.label}`, 'success')
    showActionModal.value = false
    await loadData()
  } catch (error) {
    toastStore.push(error.response?.data?.message || 'Gagal mengeksekusi aksi', 'error')
  } finally {
    isSubmittingAction.value = false
  }
}
// ==========================

function openDeactivateModal() {
  deactivateNote.value = ''
  showDeactivateModal.value = true
}

function closeDeactivateModal() {
  if (isSubmittingDeactivate.value) return
  showDeactivateModal.value = false
  deactivateNote.value = ''
}

async function submitDeactivateApp() {
  if (!app.value?.id) return

  isSubmittingDeactivate.value = true
  try {
    const note = deactivateNote.value.trim()
    await http.post(`/aplikasi/${app.value.id}/nonaktifkan`, note ? { catatan: note } : {})
    toastStore.push('Aplikasi berhasil dinonaktifkan.', 'success')
    showDeactivateModal.value = false
    deactivateNote.value = ''
    await loadData()
  } catch (error) {
    toastStore.push(error.response?.data?.message || 'Gagal menonaktifkan aplikasi.', 'error')
  } finally {
    isSubmittingDeactivate.value = false
  }
}

let redirectTimer = null

// ===== MASTER DOCUMENT SECTIONS =====
// Satu sumber kebenaran untuk semua tipe dokumen dalam sistem.
// stage        : tahap kemunculan dokumen; tahap sebelumnya tetap terlihat, tahap berikutnya disembunyikan.
// uploadRoles  : role yang berwenang mengupload dokumen ini.
// uploadStatuses: status aplikasi saat upload diizinkan.
const STATUS_DOCUMENT_STAGE = {
  diajukan: 1,
  perlu_perbaikan_pengajuan: 1,
  terverifikasi: 1,
  layak: 1,
  tidak_layak: 1,
  ditolak: 1,
  analisa_desain: 2,
  pengembangan: 3,
  perbaikan_uat: 4,
  uat: 4,
  uji_keamanan: 5,
  perbaikan_keamanan: 5,
  siap_deploy: 5,
  deployed_staging: 5,
  deployed_production: 7,
  nonaktif: 7,
}

const MASTER_DOC_SECTIONS = [
  {
    type: 'formulir_pengajuan',
    stage: 1,
    title: 'Formulir Pengajuan',
    desc: 'Formulir pengajuan resmi aplikasi baru atau revisi.',
    template: '/templates/P22-Formulir-Usulan-Pengembangan-Aplikasi.pdf',
    templateLabel: 'Buka template formulir',
    uploadRoles: ['unit_kerja'],
    uploadStatuses: ['diajukan', 'perlu_perbaikan_pengajuan'],
  },
  {
    type: 'lampiran_umum',
    stage: 1,
    title: 'Dokumen Pendukung',
    desc: 'Lampiran pendukung dari unit kerja untuk melengkapi pengajuan.',
    uploadRoles: ['unit_kerja'],
    uploadStatuses: ['diajukan', 'perlu_perbaikan_pengajuan'],
  },
  {
    type: 'laporan_analisa_desain',
    stage: 2,
    title: 'Laporan Analisis Desain',
    desc: 'Laporan hasil analisis desain dari tim analis.',
    uploadRoles: ['analis_desain'],
    uploadStatuses: ['analisa_desain'],
  },
  {
    type: 'template_uat',
    stage: 3,
    title: 'Template UAT',
    desc: 'Template pengujian penerimaan yang disiapkan tim implementasi.',
    template: '/templates/format_uat_template.txt',
    templateLabel: 'Unduh template UAT',
    uploadRoles: ['tim_implementasi_aplikasi'],
    uploadStatuses: ['pengembangan', 'perbaikan_uat'],
  },
  {
    type: 'petunjuk_aplikasi',
    stage: 3,
    title: 'Petunjuk Aplikasi',
    desc: 'Panduan penggunaan aplikasi untuk mendukung pelaksanaan UAT.',
    guidebook: '/templates/panduan_uat.md',
    guidebookLabel: 'Lihat panduan UAT',
    uploadRoles: ['tim_implementasi_aplikasi'],
    uploadStatuses: ['pengembangan', 'perbaikan_uat'],
  },
  {
    type: 'uat',
    stage: 4,
    title: 'Dokumen UAT',
    desc: 'Dokumen pengujian penerimaan (User Acceptance Testing).',
    template: '/templates/format_uat_template.txt',
    templateLabel: 'Unduh template UAT',
    guidebook: '/templates/panduan_uat.md',
    guidebookLabel: 'Lihat panduan UAT',
    uploadRoles: ['unit_kerja'],
    uploadStatuses: ['uat', 'perbaikan_uat'],
  },
  {
    type: 'laporan_uji_keamanan',
    stage: 5,
    title: 'Laporan Hasil Uji Keamanan',
    desc: 'Laporan resmi hasil pengujian keamanan aplikasi (PDF/DOC).',
    uploadRoles: ['tim_uji_keamanan'],
    uploadStatuses: ['uji_keamanan', 'perbaikan_keamanan'],
  },
  {
    type: 'rilis',
    stage: 6,
    title: 'Dokumen Rilis',
    desc: 'Dokumen rilis atau lampiran terkait go-live.',
    uploadRoles: ['unit_kerja'],
    uploadStatuses: ['deployed_production'],
  },
  {
    type: 'berita_acara',
    stage: 7,
    title: 'Berita Acara (BA / TTE)',
    desc: 'BA serah-terima yang sudah ditandatangani elektronik (TTE).',
    uploadRoles: ['unit_kerja'],
    uploadStatuses: ['deployed_production'],
  },
]

/**
 * Dokumen tampil bertahap:
 * - Tahap saat ini dan tahap sebelumnya terlihat sebagai arsip proses.
 * - Tahap berikutnya disembunyikan sampai status workflow mencapainya.
 * - Upload tetap hanya role + status yang bertanggung jawab.
 */
const documentSections = computed(() => {
  if (!app.value) return []
  const currentStage = STATUS_DOCUMENT_STAGE[app.value?.status] || 0

  return MASTER_DOC_SECTIONS.filter(section => {
    return (section.stage || 0) <= currentStage
  })
})
/** Judul panel dokumen (unified) */
const documentPanelTitle = computed(() => 'Dokumen')
const isImplementationRole = computed(() => auth.role === 'tim_implementasi_aplikasi')
const isDevOpsRole = computed(() => auth.role === 'devops_developer' || props.devopsMode)
const isPengelolaRole = computed(() => auth.role === 'pengelola_aplikasi' || props.pengelolaMode)
const isNonUnitKerjaRole = computed(() => props.unitKerjaMode || auth.role === 'unit_kerja' || props.analystMode || props.securityMode || props.devopsMode || props.implementationMode || props.pengelolaMode || isImplementationRole.value || isDevOpsRole.value || isPengelolaRole.value)
const isDocumentPanelMode = computed(() => props.unitKerjaMode || props.analystMode || props.securityMode || props.pengelolaMode || isImplementationRole.value || isDevOpsRole.value || isPengelolaRole.value)
const canDeactivateApp = computed(() => isPengelolaRole.value && app.value?.status === 'deployed_production')
const shouldLoadDocuments = computed(() => isDocumentPanelMode.value)
const isImplementationContext = computed(() => isImplementationRole.value || isDevOpsRole.value || props.implementationMode)
const canManageFeasibilityChecklist = computed(() => props.analystMode || auth.role === 'analis_desain')
const showApplicationProgress = computed(() => props.unitKerjaMode || isPengelolaRole.value)
const showFeasibilityChecklistPanel = computed(() =>
  isNonUnitKerjaRole.value &&
  canManageFeasibilityChecklist.value &&
  !isImplementationContext.value &&
  activeTab.value === 'checklist'
)
const showImplementationChecklistPanel = computed(() =>
  isImplementationContext.value &&
  activeTab.value === 'checklist'
)

function canLoadImplementationChecklistNow() {
  const status = app.value?.status || ''

  if (isDevOpsRole.value) {
    return ['siap_deploy', 'deployed_staging', 'deployed_production'].includes(status)
  }

  if (!(isImplementationRole.value || props.implementationMode)) return false
  return ['pengembangan', 'perbaikan_uat', 'perbaikan_keamanan'].includes(status)
}

function workflowButtonHierarchy(index) {
  return index === 0 ? 'primary' : 'secondary'
}

function workflowButtonClass(action) {
  return action?.btnClass === 'btn-danger' ? 'idds-danger-button' : ''
}

// Flat tabs for all roles
const COMMON_TABS = [
  { id: 'informasi',  label: 'Informasi' },
  { id: 'checklist', label: 'Checklist' },
  { id: 'catatan',   label: 'Catatan' },
  { id: 'dokumen',   label: 'Dokumen' },
]
const MAIN_TABS_WITHOUT_CHECKLIST = COMMON_TABS.filter(tab => tab.id !== 'checklist')

const availableMainTabs = computed(() => {
  // Unit Kerja tetap membutuhkan informasi, riwayat keputusan, dan dokumen miliknya.
  if (props.unitKerjaMode || auth.role === 'unit_kerja') {
    return MAIN_TABS_WITHOUT_CHECKLIST
  }
  // Pengelola Aplikasi: fokus pada informasi, progres, catatan, dan dokumen.
  if (props.pengelolaMode || isPengelolaRole.value) {
    return MAIN_TABS_WITHOUT_CHECKLIST
  }
  // Analis Desain: 4 tab standar
  if (props.analystMode) {
    return COMMON_TABS
  }
  // Tim Implementasi: 4 tab standar + Konfigurasi
  if (isImplementationRole.value || props.implementationMode) {
    return [
      ...COMMON_TABS,
      { id: 'konfigurasi', label: 'Konfigurasi' },
    ]
  }
  // DevOps: 4 tab standar + Konfigurasi + Deployment
  if (isDevOpsRole.value || props.devopsMode) {
    return [
      ...COMMON_TABS,
      { id: 'konfigurasi', label: 'Konfigurasi' },
      { id: 'deployment',  label: 'Deployment' },
    ]
  }
  // Tim Uji Keamanan: 4 tab standar + Hasil Uji
  if (props.securityMode) {
    return [
      ...MAIN_TABS_WITHOUT_CHECKLIST,
      { id: 'hasil', label: 'Hasil Uji' },
    ]
  }
  return []
})

const mainTabItems = computed(() =>
  availableMainTabs.value.map((tab) => ({ value: tab.id, label: tab.label }))
)

function setActiveMainTab(tabId) {
  activeTab.value = tabId
  router.replace({ query: { ...route.query, tab: tabId } })
}

function syncTabFromRoute() {
  const requestedTab = typeof route.query.tab === 'string' ? route.query.tab : ''
  const allowedTabs = availableMainTabs.value.map((tab) => tab.id)
  activeTab.value = allowedTabs.includes(requestedTab) ? requestedTab : (allowedTabs[0] || 'informasi')
}

/**
 * Apakah role saat ini dapat mengupload dokumen section ini sekarang?
 * Berdasarkan pemetaan uploadRoles + uploadStatuses di MASTER_DOC_SECTIONS.
 */
function docSectionCanUploadNow(section) {
  if (!app.value || !section) return false
  return section.uploadRoles.includes(auth.role) &&
         section.uploadStatuses.includes(app.value?.status)
}

function canViewDocumentTemplate() {
  return props.unitKerjaMode || auth.role === 'unit_kerja' || isPengelolaRole.value
}

function getDocumentEmptyHint(section) {
  if (!section) return ''
  if (props.securityMode && section.type === 'uat') {
    return 'Minta unit kerja mengunggah dokumen UAT sebelum mulai pengujian.'
  }
  if (props.securityMode && section.type === 'laporan_uji_keamanan') {
    return 'Unggah laporan hasil uji keamanan sebelum mengirim keputusan.'
  }
  if (isImplementationRole.value && ['template_uat', 'petunjuk_aplikasi'].includes(section.type)) {
    return 'Dokumen ini diperlukan sebelum aplikasi ditandai siap UAT.'
  }
  return ''
}
const implementationChecklistTitle = computed(() =>
  isDevOpsRole.value ? 'Checklist DevOps' : 'Checklist Implementasi'
)

const implementationChecklistEmptyText = computed(() =>
  isDevOpsRole.value
    ? 'Tambahkan item pertama untuk melacak kesiapan deployment.'
    : 'Tambahkan item pertama untuk melacak progres implementasi.'
)

onMounted(async () => {
  syncTabFromRoute()
  await loadData()
})

watch(() => route.query.tab, syncTabFromRoute)

// Watch for route changes (when navigating between different app details)
watch(() => route.params.id, async (newId) => {
  if (newId) {
    selectedFiles.value = {}
    documentsByType.value = {}
    implementationChecklistItems.value = []
    implementationChecklistCategory.value = ''
    newImplementationItem.value = { title: '', notes: '' }
    updatingImplementationChecklistId.value = null
    await loadData()
  }
})

async function loadData() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await http.get(`/aplikasi/${route.params.id}`)
    app.value = data.data || data
    const jobs = []
    if (shouldLoadDocuments.value) {
      jobs.push(loadDocuments())
    }
    // Load checklist progress sesuai role: Implementasi atau DevOps.
    if (canLoadImplementationChecklistNow()) {
      jobs.push(loadImplementationChecklist())
    }
    // Load workflow (checklist + catatan) untuk semua non-unit-kerja
    if (isNonUnitKerjaRole.value) {
      jobs.push(loadWorkflow())
    }
    if (isDevOpsRole.value || props.devopsMode || auth.role === 'pengelola_aplikasi') {
      jobs.push(loadDeploymentStatus())
    }
    if (props.securityMode) {
      jobs.push(loadSecurityReview())
    }
    if (jobs.length > 0) {
      await Promise.all(jobs)
    }
  } catch (error) {
    loadError.value = error.response?.status === 404
      ? 'Aplikasi tidak ditemukan atau tidak lagi dapat Anda akses.'
      : 'Detail aplikasi belum dapat dimuat. Periksa koneksi lalu coba lagi.'
    toastStore.push('Gagal memuat detail aplikasi. Silakan coba lagi.', 'error')
    // Redirect back to list if app not found
    if (error.response?.status === 404) {
      const rolePath = getHomeByRole(auth?.role).path
      redirectTimer = setTimeout(() => router.push(rolePath), 2000)
    }
  } finally {
    loading.value = false
  }
}

async function loadDocuments() {
  if (!shouldLoadDocuments.value || !route.params.id) return
  loadingDocuments.value = true
  try {
    const { data } = await http.get(`/aplikasi/${route.params.id}/documents`)
    const docs = data?.data?.documents || []
    const grouped = {}
    for (const doc of docs) {
      const key = doc.document_type
      if (!grouped[key]) grouped[key] = []
      grouped[key].push(doc)
    }
    documentsByType.value = grouped
  } catch (error) {
    warnDev('[AppDetailContent] loadDocuments error:', error)
    toastStore.push('Gagal memuat dokumen aplikasi.', 'error')
  } finally {
    loadingDocuments.value = false
  }
}

async function loadSecurityReview() {
  if (!props.securityMode || !route.params.id) return
  loadingSecurityReview.value = true
  try {
    const { data } = await http.get(`/aplikasi/${route.params.id}/security-review`)
    const review = data?.data?.review || {}
    securityReview.value = {
      security_test_passed: review.security_test_passed,
      security_tested_at: review.security_tested_at,
      security_test_notes: review.security_test_notes || '',
      security_tester: review.security_tester || null,
    }
    // Hasil lama tetap tersedia pada status dan riwayat. Form selalu menjadi
    // input baru agar keputusan yang sudah dikirim tidak muncul sebagai draft.
    securityReviewForm.value = createEmptySecurityReviewForm(review.security_test_passed ?? null)
    securityNotes.value = data?.data?.security_notes || []
  } catch (error) {
    const msg = error?.response?.data?.message || 'Gagal memuat data uji keamanan.'
    toastStore.push(msg, 'error')
  } finally {
    loadingSecurityReview.value = false
  }
}

/**
 * Menyimpan ringkasan + mengirim keputusan lolos/tidak lolos sekaligus.
 * Menggantikan tombol Aksi Workflow terpisah.
 */
async function submitSecurityVerdict(isLolos) {
  if (!props.securityMode) return
  const notes = securityReviewForm.value.security_test_notes?.trim() || ''
  if (!notes) {
    toastStore.push('Ringkasan hasil uji wajib diisi sebelum mengirim keputusan.', 'warning')
    return
  }
  savingSecurityReview.value = true
  try {
    await http.post(`/aplikasi/${route.params.id}/workflow/hasil-uji-keamanan`, {
      is_lolos: isLolos,
      catatan: notes,
      security_test_notes: notes,
      note: securityReviewForm.value.note?.trim() || null,
    })
    securityReviewForm.value = createEmptySecurityReviewForm()
    toastStore.push(
      isLolos ? 'Aplikasi dinyatakan Lolos Uji Keamanan.' : 'Aplikasi dinyatakan Belum Lolos Uji Keamanan.',
      isLolos ? 'success' : 'warning'
    )
    await loadData()
  } catch (error) {
    const msg = error?.response?.data?.message || 'Gagal mengirim keputusan uji keamanan.'
    toastStore.push(msg, 'error')
  } finally {
    savingSecurityReview.value = false
  }
}

async function loadDeploymentStatus() {
  if (!route.params.id) return
  loadingDeploymentStatus.value = true
  try {
    const { data } = await http.get(`/aplikasi/${route.params.id}/deployment-status`)
    deploymentStatus.value = data?.data?.deployment || {
      staging: { deployed: false, deployed_at: null, deployed_by: null },
      production: { deployed: false, deployed_at: null, deployed_by: null },
      notes: '',
      history: []
    }
    deploymentHistory.value = deploymentStatus.value.history || []

  } catch (error) {
    warnDev('[AppDetailContent] loadDeploymentStatus error:', error)
  } finally {
    loadingDeploymentStatus.value = false
  }
}

function selectDocumentFile(type, file, validation) {
  const selection = resolveIddsFileSelection(file, validation, 'File tidak sesuai ketentuan.')
  selectedFiles.value = { ...selectedFiles.value, [type]: selection.file }

  if (selection.error) {
    toastStore.push(selection.error, 'error')
  }
}

function removeDocumentFile(type) {
  selectedFiles.value = { ...selectedFiles.value, [type]: null }
}

function getLatestDoc(type) {
  const list = documentsByType.value[type] || []
  if (list.length === 0) return null
  const sorted = [...list].sort((a, b) => (b.version || 0) - (a.version || 0))
  return sorted[0]
}

function hasActiveDocument(type) {
  return (documentsByType.value[type] || []).some((doc) => doc.status === 'active')
}

const implementationChecklistStats = computed(() => {
  const list = implementationChecklistItems.value || []
  return {
    total: list.length,
    done: list.filter((item) => item.item_status === 'done').length,
    inProgress: list.filter((item) => item.item_status === 'in_progress').length,
    pending: list.filter((item) => item.item_status === 'pending').length,
  }
})

const implementationTitleError = computed(() => {
  const title = newImplementationItem.value.title?.trim() || ''
  if (!title) return 'Judul item progress wajib diisi.'
  if (title.length > implementationTitleLimit) {
    return `Judul maksimal ${implementationTitleLimit} karakter.`
  }
  return ''
})

const implementationNotesError = computed(() => {
  const notes = newImplementationItem.value.notes?.trim() || ''
  if (!notes) return ''
  if (notes.length > implementationNotesLimit) {
    return `Catatan maksimal ${implementationNotesLimit} karakter.`
  }
  return ''
})

/** Hanya tampilkan error setelah user sudah menyentuh field (touched UX pattern) */
const implementationTitleErrorVisible = computed(() =>
  implementationItemTitleTouched.value ? implementationTitleError.value : ''
)

const canAddImplementationItem = computed(() => !implementationTitleError.value && !implementationNotesError.value)

function getDocHistory(type) {
  const list = documentsByType.value[type] || []
  return [...list].sort((a, b) => (b.version || 0) - (a.version || 0))
}

async function openDocumentInNewTab(document) {
  if (!document?.preview_url) {
    toastStore.push('Dokumen belum dapat dibuka.', 'warning')
    return
  }

  const documentTab = window.open('about:blank', '_blank')
  if (!documentTab) {
    toastStore.push('Izinkan pop-up browser untuk membuka dokumen.', 'warning')
    return
  }

  documentTab.opener = null
  documentTab.document.title = 'Memuat dokumen'
  documentTab.document.body.textContent = 'Memuat dokumen...'

  try {
    const previewUrl = String(document.preview_url).replace(/^\/api\//, '/')
    const response = await http.get(previewUrl, { responseType: 'blob' })
    const objectUrl = URL.createObjectURL(response.data)
    documentTab.location.replace(objectUrl)

    // Beri browser cukup waktu untuk memuat PDF sebelum URL sementara dilepas.
    window.setTimeout(() => URL.revokeObjectURL(objectUrl), 5 * 60 * 1000)
  } catch (error) {
    documentTab.close()
    toastStore.push(error?.response?.data?.message || 'Dokumen tidak dapat dibuka.', 'error')
  }
}

function formatDateTime(value) {
  if (!value) return '-'
  const dt = new Date(value)
  if (Number.isNaN(dt.getTime())) return '-'
  return dt.toLocaleString('id-ID')
}

function formatFileSize(value) {
  const n = Number(value)
  if (!Number.isFinite(n) || n <= 0) return '-'
  if (n < 1024) return `${n} B`
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
  return `${(n / (1024 * 1024)).toFixed(1)} MB`
}

async function uploadDocument(type) {
  const file = selectedFiles.value[type]
  if (!file) {
    toastStore.push('Pilih file terlebih dahulu.', 'error')
    return
  }

  uploadingType.value = type
  try {
    const fd = new FormData()
    fd.append('document_type', type)
    fd.append('file', file)
    await http.post(`/aplikasi/${route.params.id}/documents`, fd)
    toastStore.push('Dokumen berhasil diunggah.', 'success')
    selectedFiles.value[type] = null
    await loadDocuments()
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal mengunggah dokumen.'
    toastStore.push(message, 'error')
  } finally {
    uploadingType.value = ''
  }
}

async function loadImplementationChecklist() {
  if (!canLoadImplementationChecklistNow() || !route.params.id) return
  loadingImplementationChecklist.value = true
  try {
    const { data } = await http.get(`/aplikasi/${route.params.id}/implementation-checklists`)
    implementationChecklistItems.value = data?.data?.checklists || []
    implementationChecklistCategory.value = data?.data?.category || ''
  } catch (error) {
    warnDev('[AppDetailContent] loadImplementationChecklist error:', error)
    toastStore.push('Gagal memuat checklist implementasi.', 'error')
  } finally {
    loadingImplementationChecklist.value = false
  }
}

// ==== WORKFLOW FUNCTIONS (Checklist & Catatan untuk semua role) ====
async function loadWorkflow() {
  if (!route.params.id) return
  workflowLoading.value = true
  workflowError.value = ''
  try {
    const { data } = await http.get(`/aplikasi/${route.params.id}/workflow`)
    const workflow = data.data || {}
    if (!app.value) app.value = {}
    app.value.checklists = workflow.checklists || []
    app.value.notes = workflow.notes || []
    app.value.status_histories = workflow.histories || []
  } catch (error) {
    workflowError.value = error?.response?.data?.message || 'Gagal memuat data workflow.'
    warnDev('[AppDetailContent] loadWorkflow error:', error)
  } finally {
    workflowLoading.value = false
  }
}

async function addChecklist() {
  if (!checklistForm.value.title.trim()) {
    toastStore.push('Judul checklist wajib diisi.', 'error')
    return
  }
  savingChecklist.value = true
  try {
    await http.post(`/aplikasi/${route.params.id}/checklists`, {
      category: 'studi_kelayakan',
      title: checklistForm.value.title.trim(),
      notes: checklistForm.value.notes.trim() || null,
    })
    checklistForm.value = { title: '', notes: '' }
    toastStore.push('Checklist berhasil ditambahkan.', 'success')
    await loadWorkflow()
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal menambah checklist.'
    toastStore.push(message, 'error')
  } finally {
    savingChecklist.value = false
  }
}

async function updateChecklist(item, patch) {
  updatingChecklistId.value = item.id
  try {
    await http.patch(`/aplikasi/${route.params.id}/checklists/${item.id}`, patch)
    await loadWorkflow()
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal memperbarui checklist.'
    toastStore.push(message, 'error')
  } finally {
    updatingChecklistId.value = null
  }
}

function deleteChecklist(item) {
  confirmingDeleteChecklist.value = item
}

function closeDeleteChecklistModal() {
  if (updatingChecklistId.value) return
  confirmingDeleteChecklist.value = null
}

async function confirmDeleteChecklist() {
  const item = confirmingDeleteChecklist.value
  if (!item) return
  updatingChecklistId.value = item.id
  try {
    await http.delete(`/aplikasi/${route.params.id}/checklists/${item.id}`)
    toastStore.push('Checklist berhasil dihapus.', 'success')
    confirmingDeleteChecklist.value = null
    await loadWorkflow()
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal menghapus checklist.'
    toastStore.push(message, 'error')
  } finally {
    updatingChecklistId.value = null
  }
}

const checklistStats = computed(() => {
  const items = app.value?.checklists || []
  const done = items.filter(i => i.item_status === 'done').length
  return { total: items.length, done, pending: items.length - done }
})

const filteredNotes = computed(() => {
  return Array.isArray(app.value?.notes) ? app.value.notes : []
})

// ==== END WORKFLOW FUNCTIONS ====

async function addImplementationChecklistItem() {
  implementationItemTitleTouched.value = true
  const title = newImplementationItem.value.title?.trim()
  if (!title) {
    toastStore.push('Judul item progress wajib diisi.', 'error')
    return
  }

  savingImplementationChecklist.value = true
  try {
    await http.post(`/aplikasi/${route.params.id}/implementation-checklists`, {
      title,
      notes: newImplementationItem.value.notes?.trim() || null,
    })
    newImplementationItem.value = { title: '', notes: '' }
    implementationItemTitleTouched.value = false
    await loadImplementationChecklist()
    toastStore.push('Item progress berhasil ditambahkan.', 'success')
  } catch (error) {
    const msg = error?.response?.data?.message || 'Gagal menambah item progress.'
    toastStore.push(msg, 'error')
  } finally {
    savingImplementationChecklist.value = false
  }
}

async function updateImplementationChecklistItem(item, patch) {
  updatingImplementationChecklistId.value = item.id
  try {
    await http.patch(`/aplikasi/${route.params.id}/implementation-checklists/${item.id}`, patch)
    await loadImplementationChecklist()
  } catch (error) {
    const msg = error?.response?.data?.message || 'Gagal memperbarui item progress.'
    toastStore.push(msg, 'error')
  } finally {
    updatingImplementationChecklistId.value = null
  }
}

function deleteImplementationChecklistItem(item) {
  confirmingDeleteImplementationChecklist.value = item
}

function closeDeleteImplementationChecklistModal() {
  if (updatingImplementationChecklistId.value) return
  confirmingDeleteImplementationChecklist.value = null
}

async function confirmDeleteImplementationChecklistItem() {
  const item = confirmingDeleteImplementationChecklist.value
  if (!item) return

  updatingImplementationChecklistId.value = item.id
  try {
    await http.delete(`/aplikasi/${route.params.id}/implementation-checklists/${item.id}`)
    confirmingDeleteImplementationChecklist.value = null
    await loadImplementationChecklist()
    toastStore.push('Item checklist berhasil dihapus.', 'success')
  } catch (error) {
    const msg = error?.response?.data?.message || 'Gagal menghapus item checklist.'
    toastStore.push(msg, 'error')
  } finally {
    updatingImplementationChecklistId.value = null
  }
}

async function focusImplementationTitle() {
  await nextTick()
  const field = implementationTitleInput.value
  field?.focus?.()
  field?.$el?.querySelector?.('input')?.focus()
}

function securityStatusText(value) {
  if (value === true) return 'Lolos uji keamanan'
  if (value === false) return 'Belum lolos uji keamanan'
  return 'Belum diuji'
}

onBeforeUnmount(() => {
  if (redirectTimer) clearTimeout(redirectTimer)
})

// Computed properties untuk setiap tab - Generate dari Analisa Desain
const analisaDesains = computed(() => {
  const raw = app.value?.analisa_desains || app.value?.analisaDesains || []
  // Filter out empty objects (objects with no meaningful data)
  const result = raw.filter((ad) => {
    // Consider an object meaningful if it has at least one of these fields filled
    return ad.ui_platform || ad.interop_type || ad.storage_type || ad.nama_aktor || ad.method || ad.url
  })
  return result
})

// DevOps: Generate dari Storage Type dan Interop di Analisa Desain
const devopsData = computed(() => {
  if (!app.value) return { 
    proyeks: [],
    databaseStaging: [], 
    databaseProduction: [], 
    objectStorageDev: [],
    objectStorageProd: [],
    objectStorage: [], 
    apiGatewayDev: [],
    apiGateway: [],
    environmentStaging: [],
    environmentProduction: [],
    authStaging: [],
    authProduction: [],
    auth: [],
    env: []
  }
  
  // Proyek (generate dari semua UI Platform)
  const uiPlatforms = [...new Set(analisaDesains.value.filter(ad => ad.ui_platform).map(ad => ad.ui_platform))]
  const hasBackend = analisaDesains.value.some(ad => ad.storage_type === 'db')
  const proyeks = []
  if (uiPlatforms.length > 0) {
    uiPlatforms.forEach(platform => {
      proyeks.push({
        id: `frontend-${platform}`,
        modul: `${app.value.nama_aplikasi}-${platform}`,
        jenis: 'Frontend'
      })
    })
  }
  if (hasBackend) {
    proyeks.push({
      id: 'backend-main',
      modul: `${app.value.nama_aplikasi}-backend`,
      jenis: 'Backend'
    })
  }
  
  // Database Staging & Production (dari storage type 'db')
  const hasDatabase = analisaDesains.value.some(ad => ad.storage_type === 'db')
  const namaSingkat = app.value.nama_singkat?.toLowerCase() || ''
  const databaseStaging = hasDatabase ? [{
    deployment: 'staging',
    db_connection: 'mysql',
    db_host: 'dbt-dev.bssn.go.id',
    db_port: '3306',
    db_database: namaSingkat,
    db_username: namaSingkat
  }] : []
  
  const databaseProduction = hasDatabase ? [{
    deployment: 'production',
    db_connection: 'mysql',
    db_host: 'dbt.bssn.go.id',
    db_port: '3306',
    db_database: namaSingkat,
    db_username: namaSingkat
  }] : []
  
  // Object Storage Minio Dev (dari storage type 'object-storage')
  const hasObjectStorage = analisaDesains.value.some(ad => ad.storage_type === 'object-storage')
  const objectStorageDev = hasObjectStorage ? [{
    minio_bucket: namaSingkat,
    minio_default_region: 'us-east-1',
    minio_endpoint: 'https://minio-dev.bssn.go.id:9000',
    minio_url: 'https://minio-dev.bssn.go.id:9000',
    minio_use_path_style_endpoint: 'true'
  }] : []
  
  // Object Storage Minio
  const objectStorageProd = hasObjectStorage ? [{
    minio_bucket: namaSingkat,
    minio_default_region: 'us-east-1',
    minio_endpoint: 'https://minio.bssn.go.id:9000',
    minio_url: 'https://minio.bssn.go.id:9000',
    minio_use_path_style_endpoint: 'true'
  }] : []
  
  // API Gateway SPL Dev (dari interop_type)
  const interops = [...new Set(analisaDesains.value.filter(ad => ad.interop_type).map(ad => ad.interop_type))]
  const apiGatewayDev = interops.length > 0 ? [{
    service_name: namaSingkat,
    host: '',
    path: '/api',
    route_name: namaSingkat,
    route_path: `/${namaSingkat}`
  }] : []
  
  // API Gateway SPL
  const apiGateway = interops.length > 0 ? [{
    service_name: namaSingkat,
    host: '',
    path: '/api',
    route_name: namaSingkat,
    route_path: `/${namaSingkat}`
  }] : []
  
  // Auth (dikosongkan - tetap tampil dengan empty state)
  const authStaging = []
  const authProduction = []
  const auth = []
  
  // Env (dikosongkan - tetap tampil dengan empty state)
  const environmentStaging = []
  const environmentProduction = []
  const env = []
  
  return {
    proyeks,
    databaseStaging,
    databaseProduction,
    objectStorageDev,
    objectStorageProd,
    objectStorage: objectStorageProd,
    apiGatewayDev,
    apiGateway,
    environmentStaging,
    environmentProduction,
    authStaging,
    authProduction,
    auth,
    env
  }
})

const filteredProyeks = computed(() => devopsData.value.proyeks || [])

const TECHNICAL_CONFIG_TABS = [
  { id: 'proyek', label: 'Proyek', icon: 'code' },
  { id: 'database', label: 'Database', icon: 'server' },
  { id: 'objectStorage', label: 'Object Storage', icon: 'inbox' },
  { id: 'apiGateway', label: 'API Gateway', icon: 'settings' },
  { id: 'environment', label: 'Environment', icon: 'file' },
  { id: 'auth', label: 'Auth', icon: 'server' },
]

const technicalConfigTabItems = TECHNICAL_CONFIG_TABS.map((tab) => ({
  value: tab.id,
  label: tab.label,
}))

function formatConfigKey(key) {
  return String(key || '')
    .replaceAll('_', ' ')
    .replace(/\b\w/g, char => char.toUpperCase())
}

function formatConfigValue(value) {
  if (value === null || value === undefined || value === '') return '-'
  if (typeof value === 'boolean') return value ? 'Ya' : 'Tidak'
  return String(value)
}

function makeConfigItem(record, fallbackTitle) {
  const entries = Object.entries(record || {})
    .filter(([key]) => !['id', 'created_at', 'updated_at'].includes(key))

  return {
    title: record?.modul || record?.service_name || record?.deployment || fallbackTitle,
    rows: entries.map(([key, value]) => ({
      key: formatConfigKey(key),
      value: formatConfigValue(value),
    })),
  }
}

function makeConfigGroup(title, items) {
  return {
    title,
    items: (items || []).map((item, idx) => makeConfigItem(item, `${title} ${idx + 1}`)),
  }
}

const technicalConfigSections = computed(() => {
  const data = devopsData.value || {}
  return {
    proyek: [
      makeConfigGroup('Modul Aplikasi', filteredProyeks.value),
    ],
    database: [
      makeConfigGroup('Staging', data.databaseStaging),
      makeConfigGroup('Production', data.databaseProduction),
    ],
    objectStorage: [
      makeConfigGroup('MinIO Dev', data.objectStorageDev),
      makeConfigGroup('MinIO Production', data.objectStorageProd),
    ],
    apiGateway: [
      makeConfigGroup('SPL Dev', data.apiGatewayDev),
      makeConfigGroup('SPL Production', data.apiGateway),
    ],
    environment: [
      makeConfigGroup('Staging', data.environmentStaging),
      makeConfigGroup('Production', data.environmentProduction),
    ],
    auth: [
      makeConfigGroup('Staging', data.authStaging),
      makeConfigGroup('Production', data.authProduction),
    ],
  }
})

const activeTechnicalConfigMeta = computed(() =>
  TECHNICAL_CONFIG_TABS.find(tab => tab.id === activeTechnicalConfigTab.value) || TECHNICAL_CONFIG_TABS[0]
)

const activeTechnicalConfigSections = computed(() =>
  technicalConfigSections.value[activeTechnicalConfigTab.value] || []
)

// === STEPPER / TIMELINE PROGRESS (Unit Kerja Mode) ===
const progressSteps = [
  {
    key: ['diajukan', 'perlu_perbaikan_pengajuan'],
    label: 'Pengajuan',
    desc: 'Formulir pengajuan diterima & dokumen pendukung diunggah',
    icon: 'file',
  },
  {
    key: ['terverifikasi'],
    label: 'Verifikasi',
    desc: 'Pengelola memverifikasi kelengkapan pengajuan',
    icon: 'search',
  },
  {
    key: ['analisa_desain', 'layak', 'tidak_layak'],
    label: 'Analisis & Kelayakan',
    desc: 'Analis menyusun desain teknis, lalu menetapkan kelayakan aplikasi',
    icon: 'chart',
  },
  {
    key: ['pengembangan'],
    label: 'Pengembangan',
    desc: 'Tim implementasi membangun aplikasi',
    icon: 'code',
  },
  {
    key: ['uat', 'perbaikan_uat'],
    label: 'UAT',
    desc: 'Unit kerja menguji aplikasi (User Acceptance Test)',
    icon: 'check',
  },
  {
    key: ['uji_keamanan', 'perbaikan_keamanan'],
    label: 'Uji Keamanan',
    desc: 'Tim uji keamanan melakukan pengujian keamanan aplikasi',
    icon: 'shield',
  },
  {
    key: ['siap_deploy', 'deployed_staging', 'deployed_production', 'nonaktif'],
    label: 'Deployment',
    desc: 'Aplikasi dideploy ke lingkungan produksi',
    icon: 'check',
  },
]

const currentStepIndex = computed(() => {
  const status = app.value?.status || ''
  const idx = progressSteps.findIndex((s) => s.key.includes(status))
  // ditolak = tetap di step pertama
  if (idx === -1 && status === 'ditolak') return 0
  return idx
})

function getStepState(stepIdx) {
  const current = currentStepIndex.value
  if (current === -1) return 'pending'
  if (stepIdx < current) return 'done'
  if (stepIdx === current) return 'active'
  return 'pending'
}

const isSpecialStatus = computed(() =>
  ['ditolak', 'tidak_layak', 'nonaktif'].includes(app.value?.status || '')
)

// === KONTEKS TINDAKAN SELANJUTNYA ===
const userContextMessage = computed(() => {
  if (!props.unitKerjaMode || !app.value) return null
  const status = app.value.status
  if (status === 'uat' && hasActiveDocument('uat')) {
    return {
      type: 'info',
      title: 'Menunggu Verifikasi UAT',
      desc: 'Dokumen UAT sudah diunggah. Pengelola Aplikasi akan memverifikasi hasil UAT: jika sesuai, aplikasi lanjut ke Uji Keamanan; jika belum sesuai, aplikasi masuk Perbaikan UAT.'
    }
  }

  const map = {
    'diajukan': { type: 'info', title: 'Menunggu Verifikasi', desc: 'Pengajuan Anda sedang diperiksa oleh tim pengelola. Tidak ada tindakan yang perlu Anda lakukan saat ini.' },
    'perlu_perbaikan_pengajuan': { type: 'warning', title: 'Perbaikan Diperlukan', desc: 'Tim pengelola meminta perbaikan pada formulir pengajuan Anda. Buka tab Catatan untuk melihat alasan dan tindak lanjutnya.' },
    'terverifikasi': { type: 'info', title: 'Terverifikasi', desc: 'Pengajuan Anda telah diverifikasi dan sedang menunggu jadwal penilaian kelayakan.' },
    'layak': { type: 'success', title: 'Lolos Kelayakan', desc: 'Pengajuan Anda dinyatakan layak. Tim analis akan segera menyusun dokumen analisis dan desain teknis.' },
    'tidak_layak': { type: 'danger', title: 'Tidak Layak', desc: 'Pengajuan Anda dinyatakan tidak layak dan proses dihentikan.' },
    'analisa_desain': { type: 'info', title: 'Analisis & Desain', desc: 'Tim analis sedang menyusun dokumen teknis berdasarkan pengajuan Anda.' },
    'pengembangan': { type: 'info', title: 'Pengembangan', desc: 'Aplikasi sedang dibangun oleh tim implementasi.' },
    'uat': { type: 'warning', title: 'Tindakan Diperlukan: UAT', desc: 'Aplikasi siap diuji. Buka tab Dokumen untuk mengunduh format UAT dan mengunggah hasil pengujian.' },
    'perbaikan_uat': { type: 'info', title: 'Menunggu Perbaikan UAT', desc: 'Pengelola meminta perbaikan dari hasil UAT. Tim Implementasi sedang menindaklanjuti temuan sebelum aplikasi dikirim kembali ke UAT.' },
    'uji_keamanan': { type: 'info', title: 'Uji Keamanan', desc: 'Aplikasi sedang diaudit oleh tim keamanan. Menunggu hasil pengujian.' },
    'perbaikan_keamanan': { type: 'info', title: 'Perbaikan Keamanan', desc: 'Tim implementasi sedang memperbaiki celah keamanan yang ditemukan.' },
    'siap_deploy': { type: 'success', title: 'Siap Deploy', desc: 'Seluruh tahapan selesai! Aplikasi Anda sedang dijadwalkan untuk dirilis ke production.' },
    'deployed_staging': { type: 'info', title: 'Deployed Staging', desc: 'Aplikasi sudah dideploy ke staging dan sedang menunggu rilis production.' },
    'deployed_production': { type: 'success', title: 'Aplikasi Aktif', desc: 'Selamat! Aplikasi Anda sudah dirilis ke production dan dapat digunakan.' },
    'nonaktif': { type: 'danger', title: 'Aplikasi Nonaktif', desc: 'Aplikasi ini sudah ditandai tidak aktif dan tidak lagi digunakan.' },
    'ditolak': { type: 'danger', title: 'Pengajuan Ditolak', desc: 'Pengajuan ini telah ditolak oleh pengelola. Proses dihentikan.' }
  }
  return map[status] || null
})
</script>

<template>
    <div class="ui-page app-detail-container">
      <AsyncState
        v-if="loading || loadError || !app"
        class="detail-root-state"
        :loading="loading"
        :error="loadError"
        :empty="!loading && !loadError && !app"
        empty-icon="inbox"
        empty-title="Aplikasi tidak tersedia"
        empty-description="Data aplikasi tidak ditemukan atau tidak dapat Anda akses."
        @retry="loadData"
      />

      <PageHeader
        v-if="!loading && app"
        :title="app.nama_aplikasi || app.nama_singkat || 'Aplikasi'"
        :description="`${app.nama_layanan || 'Informasi aplikasi'} - ${getShortStatusLabel(app.status)}`"
      >
        <template v-if="availableActions.length > 0 && !props.securityMode" #actions>
          <div class="workflow-action-buttons">
            <Button
              v-for="(action, idx) in availableActions"
              :key="action.label"
              :hierarchy="workflowButtonHierarchy(idx)"
              size="lg"
              class="workflow-action-btn"
              :class="workflowButtonClass(action)"
              :prefix-icon="idx === 0 ? IconRocket : undefined"
              @click="openActionModal(action)"
            >
              {{ action.label }}
            </Button>
          </div>
        </template>
      </PageHeader>

      <!-- ===== STEPPER PROGRES PENGAJUAN ===== -->
      <div v-if="!loading && showApplicationProgress && app" class="uk-stepper-wrap">
        <div class="uk-stepper-header">
          <div class="uk-stepper-title-row">
            <h3 class="uk-stepper-title">Progres pengajuan</h3>
            <span
              v-if="isSpecialStatus"
              class="uk-stepper-special-badge"
            >{{ getShortStatusLabel(app.status) }}</span>
          </div>
          <p class="uk-stepper-subtitle">
            Status saat ini:
            <strong>{{ getShortStatusLabel(app.status) }}</strong>
          </p>
        </div>

        <div class="uk-stepper">
          <div
            v-for="(step, idx) in progressSteps"
            :key="idx"
            :class="['uk-step', `uk-step--${getStepState(idx)}`]"
          >
            <div v-if="idx < progressSteps.length - 1" class="uk-step-connector">
              <div :class="['uk-step-connector-fill', { filled: getStepState(idx) === 'done' || (getStepState(idx) === 'active') }]"></div>
            </div>

            <div class="uk-step-icon-wrap">
              <div class="uk-step-icon">
                <Icons v-if="getStepState(idx) === 'done'" name="check" :size="16" />
                <div v-else-if="getStepState(idx) === 'active'" class="uk-step-active-dot"></div>
                <span v-else class="uk-step-num">{{ idx + 1 }}</span>
              </div>
            </div>

            <div class="uk-step-body">
              <div class="uk-step-label">{{ step.label }}</div>
            </div>
          </div>
        </div>
      </div>
      <!-- ===== END STEPPER ===== -->

      <!-- ===== KONTEKS TINDAKAN SELANJUTNYA ===== -->
      <div v-if="!loading && props.unitKerjaMode && userContextMessage" class="uk-context-box" :class="`uk-context-${userContextMessage.type}`">
        <div class="uk-context-icon">
          <Icons v-if="userContextMessage.type === 'info'" name="info" :size="24" />
          <Icons v-if="userContextMessage.type === 'warning'" name="alert-triangle" :size="24" />
          <Icons v-if="userContextMessage.type === 'success'" name="check-circle" :size="24" />
          <Icons v-if="userContextMessage.type === 'danger'" name="x-circle" :size="24" />
        </div>
        <div class="uk-context-content">
          <h4 class="uk-context-title">{{ userContextMessage.title }}</h4>
          <p class="uk-context-desc">{{ userContextMessage.desc }}</p>
        </div>
      </div>
      <!-- ========================================= -->

      <!-- ===== UNIFIED MAIN TAB NAVIGATION ===== -->
      <div v-if="!loading && isNonUnitKerjaRole && availableMainTabs.length > 0" class="detail-tabs-card">
        <TabHorizontal
          :value="activeTab"
          :items="mainTabItems"
          aria-label="Bagian detail aplikasi"
          @change="setActiveMainTab"
        />
      </div>
      <!-- =========================================================== -->

      <!-- ===== TAB: INFORMASI ===== -->
      <div
        v-if="!loading && isNonUnitKerjaRole && activeTab === 'informasi'"
        id="detail-panel-informasi"
        class="card detail-tab-panel detail-info-card"
        role="tabpanel"
        aria-labelledby="detail-tab-informasi"
        tabindex="0"
      >
        <h4 class="section-title">Informasi aplikasi</h4>
        <DetailInfoGrid :app="app" />
        <div v-if="canDeactivateApp" class="detail-danger-zone">
          <div class="detail-danger-copy">
            <h5>Nonaktifkan aplikasi</h5>
            <p>Aksi ini digunakan untuk aplikasi production yang sudah tidak digunakan, tanpa menghapus riwayat dan dokumennya.</p>
          </div>
          <Button
            hierarchy="secondary"
            size="lg"
            class="idds-danger-button detail-danger-btn"
            :prefix-icon="IconTrash"
            @click="openDeactivateModal"
          >
            Nonaktifkan
          </Button>
        </div>
      </div>
      <!-- ======================================================= -->

      <!-- ===== TAB: CHECKLIST (semua non-unit-kerja role) ===== -->
      <div
        v-if="!loading && showFeasibilityChecklistPanel"
        id="detail-panel-checklist"
        class="detail-tab-panel card"
        role="tabpanel"
        aria-labelledby="detail-tab-checklist"
        tabindex="0"
      >
        <!-- Header + Stats + Progress bar -->
        <div class="checklist-card-header">
          <div class="checklist-header-top">
            <h4 class="checklist-title">
              <Icons name="check-circle" :size="18" />
              Checklist Kelayakan
            </h4>
            <div class="checklist-stat-chips">
              <span class="stat-chip stat-chip--done">
                <Icons name="check" :size="12" />
                {{ checklistStats.done }} Selesai
              </span>
              <span class="stat-chip stat-chip--pending">
                <Icons name="alert-circle" :size="12" />
                {{ checklistStats.pending }} Belum
              </span>
            </div>
          </div>
          <div v-if="checklistStats.total > 0" class="checklist-progress-bar-wrap">
            <div class="checklist-progress-bar">
              <div
                class="checklist-progress-fill"
                :style="{ width: Math.round((checklistStats.done / checklistStats.total) * 100) + '%' }"
              ></div>
            </div>
            <span class="checklist-progress-pct">{{ Math.round((checklistStats.done / checklistStats.total) * 100) }}%</span>
          </div>
        </div>

        <!-- Add form -->
        <form class="checklist-add-form" @submit.prevent="addChecklist">
          <div class="checklist-add-inputs">
            <TextField
              v-model="checklistForm.title"
              label="Item baru"
              placeholder="Contoh: Dokumen sudah lengkap"
              :max-length="120"
              required
            />
            <TextField
              v-model="checklistForm.notes"
              label="Catatan (opsional)"
              placeholder="Tambahkan catatan pendukung"
              :max-length="240"
            />
          </div>
          <Button
            hierarchy="primary"
            size="lg"
            type="submit"
            class="checklist-add-btn"
            :prefix-icon="IconPlus"
            :disabled="savingChecklist || !checklistForm.title?.trim()"
          >
            {{ savingChecklist ? 'Menyimpan...' : 'Tambah item' }}
          </Button>
        </form>

        <!-- List -->
        <div v-if="workflowLoading" class="muted detail-loading">Memuat checklist...</div>
        <div v-else-if="workflowError" class="muted">{{ workflowError }}</div>
        <div v-else-if="!app?.checklists?.length" class="empty-message checklist-empty">
          <Icons name="inbox" :size="36" />
          <p class="empty-title">Belum ada checklist.</p>
          <p class="empty-message-hint">Gunakan form di atas untuk menambahkan item checklist pertama.</p>
        </div>
        <div v-else class="checklist-items-list">
          <div
            v-for="item in app.checklists"
            :key="item.id"
            class="checklist-item-row"
            :class="item.item_status === 'done' ? 'checklist-item--done' : 'checklist-item--pending'"
          >
            <!-- Konten -->
            <div class="checklist-item-body">
              <span class="checklist-item-title" :class="{ 'done-text': item.item_status === 'done' }">{{ item.title }}</span>
              <span v-if="item.item_status !== 'done'" class="checklist-item-inline-note">
                <input
                  v-model="item.notes"
                  class="checklist-note-input-inline"
                  type="text"
                  placeholder="Catatan (opsional)"
                  :aria-label="`Catatan untuk ${item.title}`"
                  maxlength="240"
                  :disabled="updatingChecklistId === item.id"
                  @change="updateChecklist(item, { notes: item.notes?.trim() || null })"
                />
              </span>
              <span v-else-if="item.notes" class="checklist-item-notes">{{ item.notes }}</span>
            </div>
            <!-- Aksi -->
            <div class="checklist-item-action checklist-item-actions-group">
              <span
                class="checklist-checkbox-only"
                :title="item.item_status === 'done' ? 'Layak' : 'Belum layak'"
              >
                <Checkbox
                  :model-value="item.item_status === 'done'"
                  :label="item.item_status === 'done' ? 'Tandai belum layak' : 'Tandai layak'"
                  size="sm"
                  :disabled="updatingChecklistId === item.id"
                  @update:model-value="updateChecklist(item, { item_status: $event ? 'done' : 'pending' })"
                />
              </span>
              <Button
                hierarchy="custom"
                size="sm"
                class="checklist-delete-btn idds-icon-danger-button"
                :prefix-icon="IconTrash"
                @click="deleteChecklist(item)"
                aria-label="Hapus checklist"
                title="Hapus checklist"
                :disabled="updatingChecklistId === item.id"
              />
            </div>
          </div>
        </div>
      </div>
      <!-- =========================================================== -->

      <!-- ===== TAB: CATATAN DAN KEPUTUSAN WORKFLOW ===== -->
      <div
        v-if="!loading && isNonUnitKerjaRole && activeTab === 'catatan'"
        id="detail-panel-catatan"
        class="detail-tab-panel card"
        role="tabpanel"
        aria-labelledby="detail-tab-catatan"
        tabindex="0"
      >
        <DiscussionThread
          :app-id="route.params.id"
          :notes="filteredNotes"
          :histories="app?.status_histories || []"
          :loading="workflowLoading"
          :error="workflowError"
          @refresh="loadWorkflow"
        />
      </div>
      <!-- =========================================================== -->


      <div
        v-if="!loading && showImplementationChecklistPanel"
        id="detail-panel-checklist"
        class="card detail-tab-panel implementation-checklist-card"
        role="tabpanel"
        aria-labelledby="detail-tab-checklist"
        tabindex="0"
      >
        <!-- Header + Progress Stats -->
        <div class="checklist-card-header">
          <div class="checklist-header-top">
            <h4 class="checklist-title">
              <Icons name="checklist" :size="18" />
              {{ implementationChecklistTitle }}
            </h4>
            <div class="checklist-stat-chips">
              <span class="stat-chip stat-chip--done">
                <Icons name="check" :size="12" />
                {{ implementationChecklistStats.done }} Selesai
              </span>
              <span class="stat-chip stat-chip--progress">
                <Icons name="refresh-cw" :size="12" />
                {{ implementationChecklistStats.inProgress }} Proses
              </span>
              <span class="stat-chip stat-chip--pending">
                <Icons name="circle" :size="12" />
                {{ implementationChecklistStats.pending }} Pending
              </span>
            </div>
          </div>
          <!-- Progress bar -->
          <div v-if="implementationChecklistStats.total > 0" class="checklist-progress-bar-wrap">
            <div class="checklist-progress-bar">
              <div
                class="checklist-progress-fill"
                :style="{ width: Math.round((implementationChecklistStats.done / implementationChecklistStats.total) * 100) + '%' }"
              ></div>
            </div>
            <span class="checklist-progress-pct">{{ Math.round((implementationChecklistStats.done / implementationChecklistStats.total) * 100) }}%</span>
          </div>
        </div>

        <!-- Add form -->
        <form class="checklist-add-form" @submit.prevent="addImplementationChecklistItem">
          <div class="checklist-add-inputs">
            <TextField
              ref="implementationTitleInput"
              v-model="newImplementationItem.title"
              label="Item baru"
              placeholder="Contoh: Implementasi halaman login"
              :max-length="implementationTitleLimit"
              required
              :status="implementationTitleErrorVisible ? 'error' : 'neutral'"
              :status-message="implementationTitleErrorVisible"
              @blur="implementationItemTitleTouched = true"
            />
            <TextField
              v-model="newImplementationItem.notes"
              label="Catatan (opsional)"
              placeholder="Tambahkan catatan pendukung"
              :max-length="implementationNotesLimit"
              :status="implementationNotesError ? 'error' : 'neutral'"
              :status-message="implementationNotesError"
            />
          </div>
          <Button
            hierarchy="primary"
            size="lg"
            type="submit"
            class="checklist-add-btn"
            :prefix-icon="IconPlus"
            :disabled="savingImplementationChecklist || !canAddImplementationItem"
          >
            {{ savingImplementationChecklist ? 'Menyimpan...' : 'Tambah item' }}
          </Button>
        </form>

        <!-- List -->
        <div v-if="loadingImplementationChecklist" class="muted detail-loading">Memuat checklist...</div>
        <div v-else-if="implementationChecklistItems.length === 0" class="empty-state compact">
          <Icons name="inbox" :size="40" />
          <p class="empty-title">Belum ada item progress</p>
          <p class="empty-desc">{{ implementationChecklistEmptyText }}</p>
          <Button hierarchy="secondary" size="lg" :prefix-icon="IconPlus" @click="focusImplementationTitle">Tambah item pertama</Button>
        </div>
        <div v-else class="checklist-items-list">
          <div
            v-for="item in implementationChecklistItems"
            :key="item.id"
            class="checklist-item-row"
            :class="`checklist-item--${item.item_status}`"
          >
            <div class="checklist-item-body">
              <span class="checklist-item-title" :class="{ 'done-text': item.item_status === 'done' }">{{ item.title }}</span>
              <span v-if="item.notes" class="checklist-item-notes">{{ item.notes }}</span>
            </div>
            <div class="checklist-item-action checklist-item-actions-group">
              <span
                class="checklist-checkbox-only"
                :title="item.item_status === 'done' ? 'Selesai' : 'Belum selesai'"
              >
                <Checkbox
                  :model-value="item.item_status === 'done'"
                  :label="item.item_status === 'done' ? 'Tandai belum selesai' : 'Tandai selesai'"
                  size="sm"
                  :disabled="updatingImplementationChecklistId === item.id"
                  @update:model-value="updateImplementationChecklistItem(item, { item_status: $event ? 'done' : 'pending' })"
                />
              </span>
              <Button
                hierarchy="custom"
                size="sm"
                class="checklist-delete-btn idds-icon-danger-button"
                :prefix-icon="IconTrash"
                :disabled="updatingImplementationChecklistId === item.id"
                aria-label="Hapus item checklist"
                title="Hapus item checklist"
                @click="deleteImplementationChecklistItem(item)"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- ===== TAB: KONFIGURASI (Tim Implementasi & DevOps) ===== -->
      <div
        v-if="!loading && (isImplementationRole || isDevOpsRole) && activeTab === 'konfigurasi'"
        id="detail-panel-konfigurasi"
        class="card detail-tab-panel deployment-status-card"
        role="tabpanel"
        aria-labelledby="detail-tab-konfigurasi"
        tabindex="0"
      >
        <div class="deployment-status-header">
          <h3>Konfigurasi teknis</h3>
          <p class="muted">Pratinjau konfigurasi teknis aplikasi berdasarkan hasil analisis desain.</p>
        </div>

        <div class="technical-config-shell">
          <TabHorizontal
            :value="activeTechnicalConfigTab"
            :items="technicalConfigTabItems"
            aria-label="Kategori konfigurasi teknis"
            class="technical-config-tabs"
            @change="activeTechnicalConfigTab = $event"
          />

          <div class="technical-config-content" role="tabpanel">
            <div class="technical-config-content-head">
              <h4>{{ activeTechnicalConfigMeta.label }}</h4>
              <span class="technical-config-count">
                {{ activeTechnicalConfigSections.reduce((total, group) => total + group.items.length, 0) }} data
              </span>
            </div>

            <div class="technical-config-groups">
              <section
                v-for="group in activeTechnicalConfigSections"
                :key="group.title"
                class="technical-config-group"
              >
                <h5>{{ group.title }}</h5>
                <div v-if="group.items.length > 0" class="technical-config-items">
                  <article
                    v-for="(item, idx) in group.items"
                    :key="`${group.title}-${idx}`"
                    class="technical-config-item"
                  >
                    <div class="technical-config-item-title">{{ item.title }}</div>
                    <div class="technical-config-kv">
                      <div v-for="row in item.rows" :key="row.key" class="technical-config-row">
                        <span>{{ row.key }}</span>
                        <strong>{{ row.value }}</strong>
                      </div>
                    </div>
                  </article>
                </div>
                <div v-else class="technical-config-empty">
                  <Icons name="inbox" :size="30" />
                  <span>Belum ada konfigurasi {{ group.title.toLowerCase() }}.</span>
                </div>
              </section>
            </div>
          </div>
        </div>
      </div>
      <!-- =========================================================== -->

      <!-- ===== TAB: DEPLOYMENT (DevOps only) ===== -->
      <div
        v-if="!loading && isDevOpsRole && activeTab === 'deployment'"
        id="detail-panel-deployment"
        class="card detail-tab-panel deployment-status-card"
        role="tabpanel"
        aria-labelledby="detail-tab-deployment"
        tabindex="0"
      >
        <div class="deployment-status-header">
          <h3>Deployment</h3>
          <p class="muted">Kelola status deployment aplikasi ke staging dan production.</p>
        </div>

        <div v-if="loadingDeploymentStatus" class="muted detail-loading">Memuat status deployment...</div>
        <div v-else class="deploy-sequential">

          <!-- Step 1: Staging -->
          <div class="deploy-step" :class="{ 'deploy-step--done': deploymentStatus.staging.deployed }">
            <div class="deploy-step-indicator">
              <div class="deploy-step-circle">
                <Icons v-if="deploymentStatus.staging.deployed" name="check" :size="16" />
                <span v-else>1</span>
              </div>
              <div class="deploy-step-line"></div>
            </div>
            <div class="deploy-step-content">
              <div class="deploy-step-head">
                <div>
                  <h4 class="deploy-step-title">Staging</h4>
                  <p class="deploy-step-desc">Deploy dan verifikasi aplikasi di environment staging sebelum lanjut ke production.</p>
                </div>
                <span class="deploy-env-badge" :class="deploymentStatus.staging.deployed ? 'deploy-badge--done' : 'deploy-badge--pending'">
                  {{ deploymentStatus.staging.deployed ? 'Berhasil' : 'Menunggu Deploy' }}
                </span>
              </div>
              <div v-if="deploymentStatus.staging.deployed" class="deploy-done-info">
                <Icons name="check" :size="14" />
                <div>
                  <div><strong>{{ deploymentStatus.staging.deployed_by?.name || 'DevOps' }}</strong></div>
                  <div class="muted deploy-date">{{ formatDateTime(deploymentStatus.staging.deployed_at) }}</div>
                </div>
              </div>
              <div v-else class="deploy-step-action">
                <Button
                  hierarchy="primary"
                  size="lg"
                  class="deploy-confirm-btn"
                  :prefix-icon="IconRocket"
                  :disabled="isSubmittingDeploy"
                  @click="openDeployModal('staging')"
                >
                  Konfirmasi deploy staging
                </Button>
              </div>
            </div>
          </div>

          <!-- Step 2: Production -->
          <div class="deploy-step" :class="{
            'deploy-step--done': deploymentStatus.production.deployed,
            'deploy-step--locked': !deploymentStatus.staging.deployed
          }">
            <div class="deploy-step-indicator">
              <div class="deploy-step-circle">
                <Icons v-if="deploymentStatus.production.deployed" name="check" :size="16" />
                <Icons v-else-if="!deploymentStatus.staging.deployed" name="lock" :size="14" />
                <span v-else>2</span>
              </div>
            </div>
            <div class="deploy-step-content">
              <div class="deploy-step-head">
                <div>
                  <h4 class="deploy-step-title">Production</h4>
                  <p class="deploy-step-desc">
                    <template v-if="!deploymentStatus.staging.deployed">Selesaikan deployment staging terlebih dahulu sebelum melanjutkan ke production.</template>
                    <template v-else>Deployment ke production akan mengubah status workflow aplikasi menjadi <strong>Deployed Production</strong>.</template>
                  </p>
                </div>
                <span class="deploy-env-badge" :class="deploymentStatus.production.deployed ? 'deploy-badge--done' : (!deploymentStatus.staging.deployed ? 'deploy-badge--locked' : 'deploy-badge--pending')">
                  {{ deploymentStatus.production.deployed ? 'Selesai' : (!deploymentStatus.staging.deployed ? 'Terkunci' : 'Siap Deploy') }}
                </span>
              </div>
              <div v-if="deploymentStatus.production.deployed" class="deploy-done-info">
                <Icons name="clock" :size="14" />
                Dideploy oleh <strong>{{ deploymentStatus.production.deployed_by?.name || 'DevOps' }}</strong>
                pada {{ formatDateTime(deploymentStatus.production.deployed_at) }}
              </div>
              <div v-else-if="deploymentStatus.staging.deployed" class="deploy-step-action">
                <Button
                  hierarchy="primary"
                  size="lg"
                  class="deploy-confirm-btn deploy-confirm-btn--production"
                  :prefix-icon="IconRocket"
                  :disabled="isSubmittingDeploy"
                  @click="openDeployModal('production')"
                >
                  Deploy ke production
                </Button>
                <p class="deploy-production-warning">
                  <Icons name="alert-triangle" :size="12" />
                  Aksi ini akan mengubah status aplikasi dan bersifat final.
                </p>
              </div>
            </div>
          </div>

        </div>



        <!-- Deployment History -->
        <div v-if="deploymentHistory.length > 0" class="deploy-history-section">
          <h4 class="deploy-history-title">
            <Icons name="clock" :size="16" />
            Riwayat Deployment
          </h4>
          <div class="deploy-history-list">
            <div v-for="(entry, idx) in deploymentHistory" :key="idx" class="deploy-history-item">
              <div class="deploy-history-env" :class="`env-${entry.environment}`">{{ entry.environment === 'staging' ? 'Staging' : 'Production' }}</div>
              <div class="deploy-history-details">
                <div class="deploy-history-user">{{ entry.deployed_by?.name || 'DevOps' }}</div>
                <div class="deploy-history-time">{{ formatDateTime(entry.deployed_at) }}</div>
              </div>
              <div class="deploy-history-status">
                <Icons name="check" :size="16" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <Modal
        :model-value="showDeployModal"
        :title="deployModalEnv === 'production' ? 'Deploy ke production' : 'Konfirmasi deployment staging'"
        size="md"
        variant="centered"
        :persistent="isSubmittingDeploy"
        @update:model-value="!$event && closeDeployModal()"
      >
        <div class="deploy-confirmation-visual">
          <img
            src="/illustrations/deployment-confirmation.png"
            alt=""
            width="280"
            height="210"
          />
          <p>
            {{ deployModalEnv === 'production'
              ? 'Pastikan monitoring dan rencana rollback sudah siap.'
              : 'Pastikan build dan health check staging sudah valid.' }}
          </p>
        </div>
        <IddsTextArea
          id="deployment-note"
          v-model="deployModalNote"
          label="Catatan deployment (opsional)"
          rows="3"
          :max-length="500"
          :placeholder="deployModalEnv === 'production'
            ? 'Contoh: Monitoring aktif dan rencana rollback sudah siap'
            : 'Contoh: Build berhasil dan health check merespons normal'"
        />
        <div class="modal-actions">
          <Button hierarchy="secondary" size="lg" :disabled="isSubmittingDeploy" @click="closeDeployModal">Batal</Button>
          <Button hierarchy="primary" size="lg" :prefix-icon="IconRocket" :disabled="isSubmittingDeploy" @click="confirmDeploy">
            {{ isSubmittingDeploy ? 'Memproses...' : (deployModalEnv === 'production' ? 'Deploy ke production' : 'Konfirmasi staging') }}
          </Button>
        </div>
      </Modal>

      <!-- ===== TAB: DOKUMEN ===== -->
      <div
        v-if="!loading && isDocumentPanelMode && activeTab === 'dokumen'"
        :id="isNonUnitKerjaRole ? 'detail-panel-dokumen' : undefined"
        class="card unit-doc-card"
        :class="{ 'detail-tab-panel': isNonUnitKerjaRole }"
        :role="isNonUnitKerjaRole ? 'tabpanel' : undefined"
        :aria-labelledby="isNonUnitKerjaRole ? 'detail-tab-dokumen' : undefined"
        :tabindex="isNonUnitKerjaRole ? 0 : undefined"
      >
        <div class="unit-doc-head">
          <h4 class="section-title">{{ documentPanelTitle }}</h4>
        </div>
        <div v-if="loadingDocuments" class="loading-state doc-loading">
          <div class="spinner"></div>
          <p>Memuat dokumen...</p>
        </div>
        <div v-else class="unit-doc-grid">
          <div v-for="section in documentSections" :key="section.type" class="unit-doc-item">
            <h4>{{ section.title }}</h4>
            <p v-if="section.desc" class="unit-doc-desc">{{ section.desc }}</p>
            <div class="unit-doc-actions">
              <a v-if="section.template && canViewDocumentTemplate()" class="action-btn view-btn" :href="section.template" target="_blank" rel="noopener">
                <Icons name="download" :size="14" />
                {{ section.templateLabel }}
              </a>
              <a v-if="section.guidebook" class="action-btn" :href="section.guidebook" target="_blank" rel="noopener">
                <Icons name="file" :size="14" />
                {{ section.guidebookLabel }}
              </a>
            </div>
            <div class="unit-doc-body">
              <!-- Upload form: hanya untuk role+status yang bertanggung jawab -->
              <div v-if="docSectionCanUploadNow(section)" class="unit-doc-upload">
                <SingleFileUpload
                  :title="`Pilih ${section.title.toLowerCase()}`"
                  description="PDF, DOC, atau DOCX; maksimal 8 MB."
                  accept="application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                  :allowed-extensions="['pdf', 'doc', 'docx']"
                  :max-size="8 * 1000 * 1000"
                  :validate-magic-number="true"
                  :disabled="uploadingType === section.type"
                  :status="selectedFiles[section.type] ? 'success' : 'idle'"
                  @change="(file, validation) => selectDocumentFile(section.type, file, validation)"
                  @remove="removeDocumentFile(section.type)"
                />
                <Button
                  hierarchy="primary"
                  size="lg"
                  class="unit-doc-upload-btn unit-doc-upload-btn--full"
                  :prefix-icon="IconPlus"
                  :disabled="uploadingType === section.type || !selectedFiles[section.type]"
                  @click="uploadDocument(section.type)"
                >
                  {{ uploadingType === section.type ? 'Mengunggah...' : 'Unggah dokumen' }}
                </Button>
              </div>
              <div class="unit-doc-meta">
                <template v-if="getLatestDoc(section.type)">
                  <button
                    type="button"
                    class="unit-doc-file"
                    :title="`Lihat ${getLatestDoc(section.type).original_filename}`"
                    @click="openDocumentInNewTab(getLatestDoc(section.type))"
                  >
                    <span class="unit-doc-file-icon">
                      <Icons :name="getLatestDoc(section.type).mime_type === 'application/pdf' ? 'file-pdf' : 'file-doc'" :size="28" />
                    </span>
                    <span class="unit-doc-file-copy">
                      <strong :title="getLatestDoc(section.type).original_filename">{{ getLatestDoc(section.type).original_filename }}</strong>
                      <small>Versi {{ getLatestDoc(section.type).version }} · {{ formatFileSize(getLatestDoc(section.type).file_size) }}</small>
                    </span>
                    <span class="unit-doc-file-action">Lihat</span>
                  </button>
                </template>
                <template v-else>
                  <div class="unit-doc-empty">
                    <Icons name="file" :size="14" />
                    Belum ada dokumen diunggah
                  </div>
                  <div v-if="getDocumentEmptyHint(section)" class="unit-doc-empty-hint">
                    {{ getDocumentEmptyHint(section) }}
                  </div>
                </template>
              </div>
              <!-- Riwayat versi: tampil untuk semua role jika ada lebih dari 1 versi -->
              <Accordion
                v-if="getDocHistory(section.type).length > 1"
                class="unit-doc-history"
                :title="`Riwayat dokumen (${getDocHistory(section.type).length} versi)`"
              >
                <div class="unit-doc-history-list">
                  <div v-for="doc in getDocHistory(section.type)" :key="doc.id" class="unit-doc-history-item">
                    <div class="unit-doc-history-main">
                      <Icons :name="doc.mime_type === 'application/pdf' ? 'file-pdf' : 'file-doc'" :size="22" />
                      <strong>v{{ doc.version }}</strong>
                      <span class="unit-doc-history-name" :title="doc.original_filename">{{ doc.original_filename }}</span>
                      <span class="unit-doc-history-size">{{ formatFileSize(doc.file_size) }}</span>
                      <button v-if="doc.preview_url" type="button" class="unit-doc-history-link" @click="openDocumentInNewTab(doc)">Lihat</button>
                    </div>
                    <div class="unit-doc-history-meta">
                      {{ formatDateTime(doc.created_at) }} - {{ doc.uploaded_by?.name || 'System' }} - {{ doc.status }}
                    </div>
                  </div>
                </div>
              </Accordion>
            </div>
          </div>
        </div>
      </div>

      <div
        v-if="!loading && props.securityMode && activeTab === 'hasil'"
        id="detail-panel-hasil"
        class="card detail-tab-panel security-review-card"
        role="tabpanel"
        aria-labelledby="detail-tab-hasil"
        tabindex="0"
      >
        <h4 class="section-title">Hasil uji keamanan</h4>
        <p class="security-subtitle">Isi status dan ringkasan temuan. Catatan perbaikan akan diteruskan ke pengelola aplikasi.</p>

        <div v-if="loadingSecurityReview" class="muted detail-loading">Memuat data uji keamanan...</div>
        <div v-else>

          <!-- Status Card -->
          <div class="sec-status-card"
               :class="{
                 'sec-status--pass': securityReview.security_test_passed === true,
                 'sec-status--fail': securityReview.security_test_passed === false,
                 'sec-status--pending': securityReview.security_test_passed === null
               }">
            <div class="sec-status-icon">
              <Icons v-if="securityReview.security_test_passed === true" name="check-circle" :size="22" />
              <Icons v-else-if="securityReview.security_test_passed === false" name="x-circle" :size="22" />
              <Icons v-else name="alert-circle" :size="22" />
            </div>
            <div class="sec-status-text">
              <span class="sec-status-label">Status pengujian</span>
              <span class="sec-status-value">{{ securityStatusText(securityReview.security_test_passed) }}</span>
            </div>
            <div v-if="securityReview.security_tester || securityReview.security_tested_at" class="sec-status-meta">
              <span v-if="securityReview.security_tester">oleh {{ securityReview.security_tester.name }}</span>
              <span v-if="securityReview.security_tested_at">{{ formatDateTime(securityReview.security_tested_at) }}</span>
            </div>
          </div>

          <!-- Form input baru hanya tersedia selama hasil masih dapat diubah. -->
          <div v-if="isSecurityReviewEditable" class="sec-form">
            <!-- Ringkasan -->
            <IddsTextArea
              id="security-test-summary"
              v-model="securityReviewForm.security_test_notes"
              label="Ringkasan hasil uji"
              rows="4"
              :max-length="500"
              placeholder="Jelaskan cakupan pengujian, temuan utama, dan kondisi akhir aplikasi"
            />

            <!-- Catatan perbaikan -->
            <IddsTextArea
              id="security-remediation-note"
              v-model="securityReviewForm.note"
              label="Catatan perbaikan (opsional)"
              rows="3"
              :max-length="300"
              placeholder="Tulis temuan spesifik yang perlu diperbaiki sebelum pengujian ulang"
            />
          </div>

          <!-- Error & Verdict Actions -->
          <div v-if="isSecurityReviewEditable" class="sec-actions">
            <div v-if="!securityReviewForm.security_test_notes?.trim()" class="sec-info-hint">
              <Icons name="alert-circle" :size="14" />
              Isi ringkasan hasil uji terlebih dahulu sebelum mengirim keputusan.
            </div>

            <!-- Tombol keputusan - hanya tampil saat status masih uji_keamanan -->
            <template v-if="app?.status === 'uji_keamanan'">
              <div class="sec-verdict-row">
                <Button
                  hierarchy="primary"
                  size="lg"
                  class="sec-verdict-btn sec-verdict-btn--pass"
                  :prefix-icon="IconCheck"
                  :disabled="savingSecurityReview || !securityReviewForm.security_test_notes?.trim()"
                  @click="submitSecurityVerdict(true)"
                >
                  Lolos uji keamanan
                </Button>
                <Button
                  hierarchy="secondary"
                  size="lg"
                  class="sec-verdict-btn sec-verdict-btn--fail idds-danger-button"
                  :disabled="savingSecurityReview || !securityReviewForm.security_test_notes?.trim()"
                  @click="submitSecurityVerdict(false)"
                >
                  Belum lolos
                </Button>
              </div>
              <p class="sec-verdict-note">Keputusan ini akan mengubah status aplikasi dan tidak dapat dibatalkan dari halaman ini.</p>
            </template>

          </div>

          <!-- History -->
          <div class="sec-history">
            <div class="sec-history-header">
              <Icons name="clock" :size="14" />
              Riwayat catatan
            </div>
            <div v-if="securityNotes.length > 0" class="sec-history-list">
              <div v-for="note in securityNotes" :key="note.id" class="sec-history-item">
                <div class="sec-history-dot"></div>
                <div class="sec-history-content">
                  <div class="sec-history-meta">
                    <strong>{{ note.creator?.name || 'System' }}</strong>
                    <span>{{ formatDateTime(note.created_at) }}</span>
                  </div>
                  <p class="sec-history-body">{{ note.body }}</p>
                </div>
              </div>
            </div>
            <div v-else class="sec-history-empty">
              <Icons name="file" :size="32" />
              <span>Belum ada catatan pengujian</span>
            </div>
          </div>

        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Memuat data...</p>
      </div>

      <!-- ===== DELETE CHECKLIST MODAL ===== -->
      <ConfirmationDrawer
        :model-value="Boolean(confirmingDeleteChecklist)"
        title="Hapus checklist"
        description="Item checklist yang dipilih akan dihapus dari pemeriksaan aplikasi."
        :subject="confirmingDeleteChecklist?.title || 'Checklist'"
        confirm-label="Hapus checklist"
        :loading="updatingChecklistId === confirmingDeleteChecklist?.id"
        @update:model-value="!$event && closeDeleteChecklistModal()"
        @confirm="confirmDeleteChecklist"
        @cancel="closeDeleteChecklistModal"
      />
      <ConfirmationDrawer
        :model-value="Boolean(confirmingDeleteImplementationChecklist)"
        title="Hapus item checklist"
        description="Item yang dipilih akan dihapus dari checklist progress aplikasi."
        :subject="confirmingDeleteImplementationChecklist?.title || 'Item checklist'"
        confirm-label="Hapus item"
        :loading="updatingImplementationChecklistId === confirmingDeleteImplementationChecklist?.id"
        @update:model-value="!$event && closeDeleteImplementationChecklistModal()"
        @confirm="confirmDeleteImplementationChecklistItem"
        @cancel="closeDeleteImplementationChecklistModal"
      />
      <!-- ================================= -->

      <!-- ===== DEACTIVATE APPLICATION MODAL ===== -->
      <Modal
        :model-value="showDeactivateModal"
        title="Nonaktifkan aplikasi"
        :description="`${app?.nama_aplikasi || 'Aplikasi'} akan ditandai nonaktif dan tetap tercatat dalam sistem.`"
        size="md"
        variant="centered"
        :persistent="isSubmittingDeactivate"
        @update:model-value="!$event && closeDeactivateModal()"
      >
        <Alert variant="caution" title="Aplikasi production" message="Pastikan aplikasi sudah tidak digunakan sebelum dinonaktifkan." />
        <IddsTextArea
          id="deactivation-note"
          v-model="deactivateNote"
          label="Catatan (opsional)"
          rows="4"
          :max-length="240"
          placeholder="Contoh: Aplikasi sudah tidak digunakan oleh unit kerja"
        />
        <div class="modal-actions">
          <Button hierarchy="secondary" size="lg" :disabled="isSubmittingDeactivate" @click="closeDeactivateModal">Batal</Button>
          <Button hierarchy="secondary" size="lg" class="idds-danger-button" :prefix-icon="IconTrash" :disabled="isSubmittingDeactivate" @click="submitDeactivateApp">
            {{ isSubmittingDeactivate ? 'Menonaktifkan...' : 'Nonaktifkan' }}
          </Button>
        </div>
      </Modal>
      <!-- ======================================= -->

      <!-- ===== WORKFLOW ACTION MODAL ===== -->
      <Modal
        :model-value="showActionModal"
        :title="selectedAction?.label || 'Konfirmasi aksi'"
        :description="selectedActionVisual.description"
        size="md"
        variant="centered"
        :persistent="isSubmittingAction"
        @update:model-value="!$event && closeActionModal()"
      >
        <div class="workflow-action-visual">
          <img
            :src="selectedActionVisual.src"
            :alt="selectedActionVisual.alt"
          />
        </div>
        <IddsTextArea
          id="workflow-action-note"
          v-if="selectedAction?.requiresNote"
          v-model="actionCatatan"
          :label="selectedAction.noteLabel || 'Catatan tambahan'"
          rows="4"
          placeholder="Tulis catatan yang relevan untuk aksi ini"
          required
          :status="!actionCatatan.trim() ? 'error' : 'neutral'"
          :status-message="!actionCatatan.trim() ? 'Catatan wajib diisi.' : ''"
        />
        <div class="modal-actions">
          <Button hierarchy="secondary" size="lg" :disabled="isSubmittingAction" @click="closeActionModal">Batal</Button>
          <Button
            :hierarchy="selectedAction?.btnClass === 'btn-danger' ? 'secondary' : 'primary'"
            size="lg"
            :class="workflowButtonClass(selectedAction)"
            :prefix-icon="IconCheck"
            :disabled="isSubmittingAction || (selectedAction?.requiresNote && !actionCatatan.trim())"
            @click="submitAction"
          >
            {{ isSubmittingAction ? 'Memproses...' : 'Konfirmasi' }}
          </Button>
        </div>
      </Modal>
      <!-- ================================= -->
    </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  z-index: 1000;
}
.modal-card {
  background: #fff;
  border-radius: 8px;
  border: 1px solid var(--ina-stroke-primary);
  width: 90%;
  max-width: 500px;
  max-height: calc(100vh - 32px);
  overflow-y: auto;
  padding: 24px;
  box-shadow: 0 20px 40px rgba(17, 24, 39, 0.18);
}
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}
.modal-header h3 {
  margin: 0;
  font-size: var(--idds-body-size);
  color: #1f2937;
  line-height: var(--idds-body-line);
}
.modal-close {
  background: transparent;
  border: none;
  font-size: var(--idds-heading-h5-size);
  cursor: pointer;
  color: #6b7280;
  flex-shrink: 0;
  line-height: var(--idds-heading-h5-line);
  padding: 0;
}
.modal-close:hover {
  color: #111827;
}
.modal-title {
  margin: 0 0 4px;
  font-size: var(--idds-body-small-size);
  font-weight: var(--idds-weight-bold);
  color: #1e293b;
  line-height: var(--idds-body-small-line);
}
.modal-subtitle {
  margin: 0;
  font-size: var(--idds-caption-size);
  color: #64748b;
  line-height: var(--idds-caption-line);
}
.modal-body {
  margin-bottom: 20px;
}
.modal-label {
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-semibold);
  color: #374151;
  line-height: var(--idds-caption-line);
}
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  gap: 16px;
}

.doc-loading {
  padding: 12px 0;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid rgba(55, 53, 47, 0.1);
  border-top-color: var(--ina-primary-primary);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
  color: var(--ina-content-secondary);
}

.empty-state svg {
  opacity: 0.3;
  margin-bottom: 16px;
}

.empty-title {
  font-size: var(--idds-body-small-size);
  font-weight: var(--idds-weight-semibold);
  color: var(--ina-content-primary);
  margin: 0 0 8px 0;
  line-height: var(--idds-body-small-line);
}

.empty-desc {
  font-size: var(--idds-caption-size);
  color: var(--ina-content-secondary);
  margin: 0;
  max-width: 320px;
  line-height: var(--idds-caption-line);
}

.field-hint {
  display: inline-block;
  margin-top: 6px;
  font-size: var(--idds-caption-small-size);
  color: var(--ina-content-secondary);
  line-height: var(--idds-caption-small-line);
}

.url-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.url-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px;
  background: var(--ina-background-primary);
  border-radius: 6px;
  border: 1px solid var(--ina-stroke-primary);
}

.url-item label {
  font-size: var(--idds-caption-size);
  color: var(--ina-content-secondary);
  font-weight: var(--idds-weight-medium);
  line-height: var(--idds-caption-line);
}

.url-value {
  font-size: var(--idds-caption-size);
  color: var(--ina-content-primary);
  font-weight: var(--idds-weight-semibold);
  line-height: var(--idds-caption-line);
}

.db-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
  margin-bottom: 16px;
}

.db-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px;
  background: var(--ina-background-primary);
  border-radius: 6px;
  border: 1px solid var(--ina-stroke-primary);
}

.db-item--full {
  grid-column: 1 / -1;
}

.col-status {
  width: 160px;
}

.stacked-card {
  margin-top: 20px;
}

.db-item label {
  font-size: var(--idds-caption-size);
  color: var(--ina-content-secondary);
  font-weight: var(--idds-weight-medium);
  line-height: var(--idds-caption-line);
}

.db-label {
  font-size: var(--idds-caption-size);
  color: var(--ina-content-secondary);
  font-weight: var(--idds-weight-medium);
  line-height: var(--idds-caption-line);
}

.db-value {
  font-size: var(--idds-caption-size);
  color: var(--ina-content-primary);
  font-weight: var(--idds-weight-semibold);
  line-height: var(--idds-caption-line);
}

.unit-doc-card {
  margin-bottom: 16px;
  padding: 20px;
}



.unit-doc-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
  align-items: stretch;
}

.unit-doc-item {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--ina-stroke-primary);
  border-radius: 10px;
  padding: 14px;
  background: var(--ina-background-primary);
  min-height: 250px;
  height: auto;
  width: 100%;
  max-width: 100%;
  min-width: 0;
  align-self: stretch;
  box-sizing: border-box;
  overflow-wrap: anywhere;
}

.unit-doc-file {
  width: 100%;
  min-width: 0;
  display: grid;
  grid-template-columns: 36px minmax(0, 1fr) auto;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid var(--ina-stroke-primary);
  border-radius: var(--ina-radius-lg);
  color: var(--ina-content-primary);
  background: var(--ina-background-primary);
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.unit-doc-file:hover {
  border-color: var(--ina-stroke-secondary);
  background: var(--ina-background-secondary);
}

.unit-doc-file-icon {
  width: 36px;
  height: 36px;
  display: grid;
  place-items: center;
  border-radius: var(--ina-radius-lg);
  color: var(--ina-negative-600);
  background: var(--ina-negative-50);
}

.unit-doc-file-copy {
  min-width: 0;
  display: grid;
  gap: 2px;
}

.unit-doc-file-copy strong {
  overflow: hidden;
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-semibold);
  text-overflow: ellipsis;
  white-space: nowrap;
  line-height: var(--idds-caption-line);
}

.unit-doc-file-copy small {
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-small-size);
  line-height: var(--idds-caption-small-line);
}

.unit-doc-file-action {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--ina-primary-primary);
  font-size: var(--idds-caption-small-size);
  font-weight: var(--idds-weight-medium);
  line-height: var(--idds-caption-small-line);
}

.unit-doc-item h4 {
  margin: 0 0 10px;
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

.unit-doc-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 12px;
  min-width: 0;
}

.unit-doc-actions > * {
  min-width: 0;
  max-width: 100%;
}

.unit-doc-body {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 10px;
  min-width: 0;
}

.unit-doc-upload {
  display: grid;
  grid-template-rows: auto auto;
  align-content: start;
  align-items: stretch;
  gap: 8px;
  width: 100%;
  min-width: 0;
  min-height: 0;
  height: fit-content;
  max-height: max-content;
}

.unit-doc-upload > * {
  min-width: 0;
  max-width: 100%;
}

.unit-doc-picker {
  display: block;
  width: 100%;
  min-width: 0;
  max-width: 100%;
  cursor: pointer;
  box-sizing: border-box;
}

.unit-doc-picker-input {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.unit-doc-picker-button {
  width: 100%;
  min-width: 0;
  max-width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 12px;
  border-radius: 8px;
  border: 1.5px dashed var(--ina-stroke-primary);
  background: var(--ina-background-secondary);
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-semibold);
  white-space: nowrap;
  cursor: pointer;
  overflow: hidden;
  box-sizing: border-box;
  transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease;
  line-height: var(--idds-caption-line);
}

.unit-doc-picker-button svg {
  flex-shrink: 0;
}

.unit-doc-picker-label {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.unit-doc-picker:hover .unit-doc-picker-button {
  border-color: var(--ina-primary-primary);
  background: #eef2fb;
  color: var(--ina-primary-primary);
}

.unit-doc-upload button {
  flex-shrink: 0;
  white-space: nowrap;
  margin-top: 0;
  margin-right: 0;
}

.unit-doc-upload-btn {
  background: #263053;
  color: #ffffff;
}

.unit-doc-upload-btn--full {
  width: 100%;
  justify-content: center;
}

.unit-doc-upload-btn:hover {
  background: #39456f;
}

.unit-doc-meta {
  margin-top: 0;
}

.unit-doc-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  background: var(--ina-positive-50);
  border: 1px solid var(--ina-positive-300);
  border-radius: 6px;
  font-size: var(--idds-caption-small-size);
  color: var(--ina-positive-600);
  font-weight: var(--idds-weight-medium);
  max-width: 100%;
  box-sizing: border-box;
  line-height: var(--idds-caption-small-line);
}

.unit-doc-status-badge span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.unit-doc-download-link {
  flex-shrink: 0;
  margin-left: 4px;
  color: var(--ina-positive-600);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  cursor: pointer;
}

.unit-doc-download-link:hover {
  opacity: 0.7;
}

.unit-doc-empty {
  font-size: var(--idds-caption-small-size);
  color: var(--ina-content-secondary);
  line-height: var(--idds-caption-small-line);
}

.unit-doc-empty-hint {
  margin-top: 6px;
  font-size: var(--idds-caption-small-size);
  color: var(--ina-content-tertiary);
  line-height: var(--idds-caption-small-line);
}

.unit-doc-download-row {
  margin-top: 8px;
}

.unit-doc-history {
  margin-top: 0;
  border-top: 1px dashed var(--ina-stroke-primary);
  padding-top: 8px;
}

.unit-doc-history-list {
  display: grid;
  gap: 8px;
  margin-top: 8px;
}

.unit-doc-history-item {
  border: 1px solid var(--ina-stroke-primary);
  border-radius: 6px;
  padding: 8px;
}

.unit-doc-history-main {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  font-size: var(--idds-caption-small-size);
  line-height: var(--idds-caption-small-line);
}

.unit-doc-history-name {
  min-width: 0;
  flex: 1 1 150px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.unit-doc-history-size {
  color: var(--ina-content-secondary);
  white-space: nowrap;
}

.unit-doc-history-link {
  margin-left: auto;
  padding: 0;
  border: 0;
  color: var(--ina-primary-primary);
  background: transparent;
  font: inherit;
  text-decoration: none;
  font-weight: var(--idds-weight-semibold);
  cursor: pointer;
}

.unit-doc-history-link:hover {
  text-decoration: underline;
}

.unit-doc-history-meta {
  margin-top: 4px;
  font-size: var(--idds-caption-small-size);
  color: var(--ina-content-secondary);
  line-height: var(--idds-caption-small-line);
}

.analyst-focus-note {
  margin-top: 10px;
  padding: 10px 12px;
  border: 1px solid var(--ina-stroke-primary);
  border-radius: 8px;
  font-size: var(--idds-caption-size);
  color: var(--ina-content-secondary);
  background: var(--ina-background-primary);
  line-height: var(--idds-caption-line);
}

.implementation-header-card,
.implementation-checklist-card {
  margin-bottom: 16px;
}

.implementation-header-card {
  display: grid;
  grid-template-columns: 1.7fr 1fr;
  gap: 16px;
}

.implementation-meta-row,
.implementation-summary-row,
.implementation-stats-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}

.summary-pill {
  border: 1px solid var(--ina-stroke-primary);
  border-radius: 999px;
  padding: 4px 10px;
  font-size: var(--idds-caption-small-size);
  background: var(--ina-background-primary);
  line-height: var(--idds-caption-small-line);
}

.summary-pill.success {
  background: var(--ina-positive-50);
  border-color: var(--ina-positive-300);
}

.summary-pill.warning {
  background: var(--ina-warning-50);
  border-color: var(--ina-warning-300);
}

.implementation-header-side {
  border: 1px dashed var(--ina-stroke-primary);
  border-radius: 8px;
  padding: 12px;
}

.implementation-side-label {
  margin: 0 0 4px;
  font-size: var(--idds-caption-small-size);
  color: var(--ina-content-secondary);
  line-height: var(--idds-caption-small-line);
}

.implementation-side-file {
  margin: 0;
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-semibold);
  line-height: var(--idds-caption-line);
}

.implementation-side-meta {
  margin: 4px 0 10px;
  font-size: var(--idds-caption-small-size);
  color: var(--ina-content-secondary);
  line-height: var(--idds-caption-small-line);
}

.implementation-checklist-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
}

.implementation-add-row {
  display: grid;
  grid-template-columns: 1.8fr 1.2fr auto;
  gap: 8px;
  margin-bottom: 12px;
}

.implementation-add-row input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--ina-stroke-primary);
  border-radius: 6px;
  background: var(--ina-background-primary);
}

.implementation-add-row .input-invalid {
  border-color: var(--ina-negative-600);
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
}

.form-hint {
  margin: 0 0 10px;
  font-size: var(--idds-caption-small-size);
  line-height: var(--idds-caption-small-line);
}

.form-hint.error {
  color: var(--ina-negative-600);
}

.implementation-checklist-card select {
  width: 100%;
  padding: 8px 10px;
  border: 1px solid var(--ina-stroke-primary);
  border-radius: 6px;
  background: var(--ina-background-primary);
}

.implementation-checklist-card td .badge {
  display: inline-flex;
  align-items: center;
  margin-bottom: 6px;
}

.implementation-checklist-card .impl-status-cell {
  display: flex;
  flex-direction: column;
  gap: 6px;
  align-items: flex-start;
}

.implementation-checklist-card .impl-status-cell select {
  min-width: 140px;
}

.empty-state.compact {
  padding: 32px 20px;
}

.empty-state.compact .empty-title {
  margin-top: 8px;
}

.implementation-status-text {
  margin-top: 4px;
  font-size: var(--idds-caption-small-size);
  color: var(--ina-content-secondary);
  line-height: var(--idds-caption-small-line);
}

.muted {
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

.detail-loading {
  padding: 16px 0;
}

.deploy-date {
  font-size: var(--idds-caption-small-size);
  line-height: var(--idds-caption-small-line);
}

.modal-label-spaced {
  display: block;
  margin-top: 16px;
}

.modal-optional {
  color: #94a3b8;
  font-weight: var(--idds-weight-regular);
}

.action-modal-copy {
  margin: 0 0 12px;
  font-size: var(--idds-caption-size);
  color: #4b5563;
  line-height: var(--idds-caption-line);
}

.action-modal-label {
  display: block;
  margin-bottom: 6px;
  color: #374151;
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-semibold);
  line-height: var(--idds-caption-line);
}

.action-modal-textarea {
  width: 100%;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 10px 12px;
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
  resize: vertical;
}

.action-modal-textarea:focus {
  outline: none;
  border-color: var(--ina-primary-primary);
  box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.08);
}

.action-modal-footer {
  margin-top: 20px;
  gap: 12px;
}

.text-danger {
  color: var(--ina-negative-600);
}

.spinner-small {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.42);
  border-top-color: #ffffff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

.spinner-inline {
  margin-right: 6px;
}

.unit-doc-readonly-note {
  margin-top: 8px;
  font-size: var(--idds-caption-small-size);
  color: var(--ina-content-secondary);
  background: var(--ina-background-secondary);
  border: 1px solid var(--ina-stroke-primary);
  border-radius: 999px;
  padding: 6px 10px;
  display: inline-flex;
  align-items: center;
  line-height: var(--idds-caption-small-line);
}

.unit-doc-stage-lock {
  margin-top: 8px;
  font-size: var(--idds-caption-small-size);
  color: var(--ina-content-tertiary);
  background: var(--ina-background-secondary);
  border: 1px dashed var(--ina-stroke-primary);
  border-radius: 999px;
  padding: 6px 10px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-style: italic;
  line-height: var(--idds-caption-small-line);
}

.security-review-card {
  margin-bottom: 16px;
}

.security-subtitle {
  font-size: var(--idds-caption-size);
  color: var(--ina-content-secondary);
  margin: -10px 0 20px;
  line-height: var(--idds-caption-line);
}

/* Status Card */
.sec-status-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 18px;
  border-radius: 10px;
  margin-bottom: 24px;
  border: 1.5px solid #e2e8f0;
  background: #f8fafc;
}
.sec-status--pass {
  background: #f0fdf4;
  border-color: #86efac;
}
.sec-status--fail {
  background: #fff1f2;
  border-color: #fca5a5;
}
.sec-status--pending {
  background: #f8fafc;
  border-color: #e2e8f0;
}
.sec-status-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  flex-shrink: 0;
  background: rgba(255,255,255,0.7);
}
.sec-status--pass .sec-status-icon { color: #16a34a; }
.sec-status--fail .sec-status-icon { color: #dc2626; }
.sec-status--pending .sec-status-icon { color: #94a3b8; }
.sec-status-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
}
.sec-status-label {
  font-size: var(--idds-caption-small-size);
  font-weight: var(--idds-weight-semibold);
  text-transform: uppercase;
  letter-spacing: var(--idds-letter-spacing);
  color: #94a3b8;
  line-height: var(--idds-caption-small-line);
}
.sec-status-value {
  font-size: var(--idds-body-small-size);
  font-weight: var(--idds-weight-bold);
  color: #1e293b;
  line-height: var(--idds-body-small-line);
}
.sec-status--pass .sec-status-value { color: #15803d; }
.sec-status--fail .sec-status-value { color: #b91c1c; }
.sec-status-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: var(--idds-caption-small-size);
  color: #64748b;
  text-align: right;
  line-height: var(--idds-caption-small-line);
}

/* Form */
.sec-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
  margin-bottom: 20px;
}
.sec-form-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.sec-form-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-semibold);
  color: #374151;
  line-height: var(--idds-caption-line);
}
.sec-required { color: #ef4444; font-size: var(--idds-caption-size); line-height: var(--idds-caption-line); }
.sec-optional {
  font-size: var(--idds-caption-small-size);
  font-weight: var(--idds-weight-medium);
  color: #94a3b8;
  background: #f1f5f9;
  border-radius: 4px;
  padding: 1px 6px;
  text-transform: lowercase;
  line-height: var(--idds-caption-small-line);
}
.sec-form-hint {
  font-size: var(--idds-caption-small-size);
  color: #94a3b8;
  margin: 0;
  line-height: var(--idds-caption-small-line);
}
.sec-select-wrap {
  position: relative;
}
.sec-select {
  width: 100%;
  appearance: none;
  -webkit-appearance: none;
  padding: 10px 38px 10px 14px;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
  font-size: var(--idds-caption-size);
  color: #1e293b;
  cursor: pointer;
  transition: border-color 0.15s;
  line-height: var(--idds-caption-line);
}
.sec-select:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.12); }
.sec-select--pass { border-color: #86efac; background: #f0fdf4; color: #15803d; font-weight: var(--idds-weight-semibold); }
.sec-select--fail { border-color: #fca5a5; background: #fff1f2; color: #b91c1c; font-weight: var(--idds-weight-semibold); }
.sec-select-chevron {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
  color: #94a3b8;
}
.sec-textarea {
  width: 100%;
  padding: 10px 14px;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
  font-size: var(--idds-caption-size);
  color: #1e293b;
  resize: vertical;
  transition: border-color 0.15s;
  font-family: inherit;
  line-height: var(--idds-caption-line);
}
.sec-textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.12); }
.sec-textarea::placeholder { color: #cbd5e1; font-size: var(--idds-caption-size); line-height: var(--idds-caption-line); }
.sec-char-count {
  font-size: var(--idds-caption-small-size);
  color: #94a3b8;
  text-align: right;
  margin-top: 2px;
  line-height: var(--idds-caption-small-line);
}

/* Actions */
.sec-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-top: 4px;
  margin-bottom: 24px;
}
.sec-error {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  background: #fff1f2;
  border: 1px solid #fca5a5;
  border-radius: 8px;
  font-size: var(--idds-caption-size);
  color: #b91c1c;
  line-height: var(--idds-caption-line);
}
.sec-error svg { flex-shrink: 0; color: #ef4444; }
.sec-save-btn {
  align-self: flex-start;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.sec-save-btn .spin {
  animation: spin 1s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Info hint */
.sec-info-hint {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 10px 14px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 8px;
  font-size: var(--idds-caption-size);
  color: #1d4ed8;
  margin-bottom: 4px;
  line-height: var(--idds-caption-line);
}
.sec-info-hint svg { flex-shrink: 0; }

/* Verdict buttons */
.sec-verdict-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.sec-verdict-btn {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 20px;
  border-radius: 10px;
  border: 2px solid transparent;
  cursor: pointer;
  font-size: var(--idds-caption-size);
  text-align: left;
  transition: all 0.15s ease;
  line-height: var(--idds-caption-line);
}
.sec-verdict-btn span {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.sec-verdict-btn strong {
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-bold);
  line-height: var(--idds-caption-line);
}
.sec-verdict-btn small {
  font-size: var(--idds-caption-small-size);
  font-weight: var(--idds-weight-regular);
  opacity: 0.85;
  line-height: var(--idds-caption-small-line);
}
.sec-verdict-btn svg { flex-shrink: 0; }

.sec-verdict-btn--pass {
  background: #16a34a;
  color: #fff;
  border-color: #15803d;
}
.sec-verdict-btn--pass:hover:not(:disabled) {
  background: #15803d;
  box-shadow: 0 4px 14px rgba(22, 163, 74, 0.35);
  transform: translateY(-1px);
}
.sec-verdict-btn--fail {
  background: #dc2626;
  color: #fff;
  border-color: #b91c1c;
}
.sec-verdict-btn--fail:hover:not(:disabled) {
  background: #b91c1c;
  box-shadow: 0 4px 14px rgba(220, 38, 38, 0.35);
  transform: translateY(-1px);
}
.sec-verdict-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}
.sec-verdict-note {
  font-size: var(--idds-caption-small-size);
  color: #94a3b8;
  margin: 8px 0 0;
  font-style: italic;
  line-height: var(--idds-caption-small-line);
}

/* History */
.sec-history {
  border-top: 1px solid #f1f5f9;
  padding-top: 20px;
}
.sec-history-header {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: var(--idds-caption-small-size);
  font-weight: var(--idds-weight-bold);
  text-transform: uppercase;
  letter-spacing: var(--idds-letter-spacing);
  color: #94a3b8;
  margin-bottom: 14px;
  line-height: var(--idds-caption-small-line);
}
.sec-history-list {
  display: flex;
  flex-direction: column;
  gap: 0;
  padding-left: 8px;
  border-left: 2px solid #e2e8f0;
}
.sec-history-item {
  display: flex;
  gap: 14px;
  padding-bottom: 16px;
  position: relative;
}
.sec-history-dot {
  position: absolute;
  left: -17px;
  top: 4px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #3b82f6;
  border: 2px solid #fff;
  box-shadow: 0 0 0 1px #3b82f6;
  flex-shrink: 0;
}
.sec-history-content {
  flex: 1;
  background: #f8fafc;
  border: 1px solid #e9ecef;
  border-radius: 8px;
  padding: 10px 14px;
}
.sec-history-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
  font-size: var(--idds-caption-small-size);
  color: #64748b;
  line-height: var(--idds-caption-small-line);
}
.sec-history-meta strong { color: #1e293b; font-size: var(--idds-caption-size); line-height: var(--idds-caption-line); }
.sec-history-body {
  margin: 0;
  font-size: var(--idds-caption-size);
  color: #374151;
  line-height: var(--idds-caption-line);
  white-space: pre-wrap;
}
.sec-history-empty {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 16px;
  background: #f8fafc;
  border-radius: 8px;
  font-size: var(--idds-caption-size);
  color: #94a3b8;
  line-height: var(--idds-caption-line);
}
.sec-history-empty svg { color: #cbd5e1; }

@media (max-width: 900px) {
  .unit-doc-grid {
    grid-template-columns: 1fr;
  }

  .unit-doc-item {
    min-height: 0;
  }

  .implementation-header-card {
    grid-template-columns: 1fr;
  }

  .implementation-checklist-top {
    flex-direction: column;
    align-items: flex-start;
  }

  .implementation-add-row {
    grid-template-columns: 1fr;
  }
}

/* ===== STEPPER / TIMELINE PROGRESS ===== */
.uk-stepper-wrap {
  margin: 20px 20px 0;
  background: var(--ina-background-primary);
  border: 1px solid rgba(228, 224, 213, 0.8);
  border-radius: var(--ina-radius-xl);
  box-shadow: var(--ina-shadow-base);
  padding: 20px 22px;
  overflow: hidden;
}

.uk-stepper-header {
  margin-bottom: 24px;
}

/* Stage lock card */
.unit-doc-stage-lock-card {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #f9fafb;
  border: 1px dashed #d1d5db;
  padding: 16px;
  border-radius: 8px;
  margin-top: 12px;
}

.unit-doc-stage-lock-icon {
  background: #f3f4f6;
  color: #9ca3af;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.unit-doc-stage-lock-title {
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-semibold);
  color: #4b5563;
  margin-bottom: 2px;
  line-height: var(--idds-caption-line);
}

.unit-doc-stage-lock-desc {
  font-size: var(--idds-caption-size);
  color: #6b7280;
  line-height: var(--idds-caption-line);
}

/* Konteks Box (Next Action) */
.uk-context-box {
  display: flex;
  gap: 16px;
  padding: 20px;
  border-radius: 8px;
  margin: 16px 20px 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  border: 1px solid transparent;
}

.uk-context-info {
  background-color: #eff6ff;
  border-color: #bfdbfe;
}
.uk-context-info .uk-context-icon { color: #2563eb; }
.uk-context-info .uk-context-title { color: #1e3a8a; }
.uk-context-info .uk-context-desc { color: #1e40af; }

.uk-context-warning {
  background-color: #fffbeb;
  border-color: #fde68a;
}
.uk-context-warning .uk-context-icon { color: #d97706; }
.uk-context-warning .uk-context-title { color: #78350f; }
.uk-context-warning .uk-context-desc { color: #92400e; }

.uk-context-success {
  background-color: #f0fdf4;
  border-color: #bbf7d0;
}
.uk-context-success .uk-context-icon { color: #059669; }
.uk-context-success .uk-context-title { color: #14532d; }
.uk-context-success .uk-context-desc { color: #166534; }

.uk-context-danger {
  background-color: #fef2f2;
  border-color: #fecaca;
}
.uk-context-danger .uk-context-icon { color: #dc2626; }
.uk-context-danger .uk-context-title { color: #7f1d1d; }
.uk-context-danger .uk-context-desc { color: #991b1b; }

.uk-context-icon {
  flex-shrink: 0;
  display: flex;
  align-items: flex-start;
  padding-top: 2px;
}

.uk-context-content {
  flex: 1;
}

.uk-context-title {
  margin: 0 0 6px 0;
  font-size: var(--idds-body-small-size);
  font-weight: var(--idds-weight-bold);
  line-height: var(--idds-body-small-line);
}

.uk-context-desc {
  margin: 0;
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

/* Empty doc state */
.unit-doc-empty {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 8px;
  font-size: var(--idds-caption-size);
  color: var(--ina-content-tertiary);
  line-height: var(--idds-caption-line);
}

.uk-stepper-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.uk-stepper-title {
  margin: 0;
  font-size: var(--idds-body-small-size);
  font-weight: var(--idds-weight-bold);
  color: var(--ina-content-primary);
  letter-spacing: var(--idds-letter-spacing);
  line-height: var(--idds-body-small-line);
}

.uk-stepper-special-badge {
  font-size: var(--idds-caption-small-size);
  font-weight: var(--idds-weight-bold);
  padding: 3px 10px;
  border-radius: 999px;
  background: var(--ina-negative-50);
  color: var(--ina-negative-600);
  line-height: var(--idds-caption-small-line);
}

.uk-stepper-subtitle {
  margin: 4px 0 0;
  font-size: var(--idds-caption-size);
  color: var(--ina-content-secondary);
  line-height: var(--idds-caption-line);
}

/* === Stepper track === */
.uk-stepper {
  display: flex;
  align-items: flex-start;
  position: relative;
  gap: 0;
  overflow-x: auto;
  padding-bottom: 4px;
}

.uk-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
  flex: 1;
  min-width: 100px;
}

/* Connector line between steps */
.uk-step-connector {
  position: absolute;
  top: 17px;
  left: 50%;
  width: 100%;
  height: 3px;
  background: var(--ina-stroke-primary);
  z-index: 0;
  border-radius: 3px;
}

.uk-step-connector-fill {
  height: 100%;
  width: 0%;
  background: var(--ina-primary-primary);
  transition: width 0.5s ease;
  border-radius: 2px;
}

.uk-step-connector-fill.filled {
  width: 100%;
}

/* Icon wrapper */
.uk-step-icon-wrap {
  position: relative;
  z-index: 1;
  margin-bottom: 10px;
}

.uk-step-icon {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-bold);
  transition: all 0.3s ease;
  border: 2px solid var(--ina-stroke-primary);
  background: var(--ina-background-primary);
  color: var(--ina-content-secondary);
  line-height: var(--idds-caption-line);
}

/* Done state */
.uk-step--done .uk-step-icon {
  background: var(--ina-primary-primary);
  border-color: var(--ina-primary-primary);
  color: #fff;
  box-shadow: 0 4px 12px rgba(30, 58, 138, 0.35);
}

/* Active state */
.uk-step--active .uk-step-icon {
  border-color: #1e3a8a;
  background: #e8ecf8;
  box-shadow: 0 0 0 4px rgba(30, 58, 138, 0.15);
}

.uk-step-active-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #1e3a8a;
  animation: uk-pulse 1.8s ease-in-out infinite;
}

@keyframes uk-pulse {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.3); opacity: 0.7; }
}

.uk-step-num {
  font-size: var(--idds-caption-size);
  color: var(--ina-content-tertiary);
  line-height: var(--idds-caption-line);
}

/* Body */
.uk-step-body {
  text-align: center;
  padding: 0 4px;
}

.uk-step-label {
  font-size: var(--idds-caption-small-size);
  font-weight: var(--idds-weight-bold);
  color: var(--ina-content-secondary);
  line-height: var(--idds-caption-small-line);
  margin-bottom: 4px;
  transition: color 0.2s;
}

.uk-step--done .uk-step-label {
  color: #1e3a8a;
}

.uk-step--active .uk-step-label {
  color: var(--ina-content-primary);
  font-weight: var(--idds-weight-bold);
}

.uk-step-desc {
  font-size: var(--idds-caption-small-size);
  color: var(--ina-content-tertiary);
  line-height: var(--idds-caption-small-line);
  max-width: 120px;
  margin: 0 auto;
}

.uk-step--active .uk-step-desc {
  color: var(--ina-content-secondary);
}

@media (max-width: 768px) {
  .uk-stepper {
    flex-direction: column;
    align-items: flex-start;
    gap: 0;
  }

  .uk-step {
    flex-direction: row;
    align-items: flex-start;
    min-width: unset;
    width: 100%;
    gap: 12px;
    padding-bottom: 16px;
    flex: unset;
  }

  .uk-step-connector {
    top: 36px;
    left: 17px;
    width: 2px;
    height: calc(100% - 36px);
  }

  .uk-step-connector-fill {
    width: 100%;
    height: 0%;
  }

  .uk-step-connector-fill.filled {
    height: 100%;
    width: 100%;
  }

  .uk-step-icon-wrap {
    margin-bottom: 0;
    flex-shrink: 0;
  }

  .uk-step-body {
    text-align: left;
  }

  .uk-step-desc {
    max-width: unset;
    margin: 0;
  }
}

/* ===== STICKY HEADER WRAPPER ===== */
.sticky-header-container {
  position: sticky;
  top: 16px;
  z-index: 100;
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-bottom: 0;
  padding-bottom: 16px;
}

/* ===== DETAIL HERO CARD ===== */
.detail-hero-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
  background: var(--ina-background-primary);
  border-radius: 8px;
  padding: 20px 24px;
  margin: 0;
  box-shadow: 0 4px 14px rgba(30, 58, 138, 0.18);
}

.workflow-action-visual {
  display: flex;
  justify-content: center;
  min-height: 168px;
  margin: -4px 0 12px;
  overflow: hidden;
}

.workflow-action-visual img {
  width: min(100%, 264px);
  aspect-ratio: 4 / 3;
  object-fit: contain;
  animation: workflow-visual-enter 240ms ease-out both,
    workflow-visual-float 3.6s ease-in-out 240ms infinite;
}

@keyframes workflow-visual-enter {
  from {
    opacity: 0;
    transform: translateY(8px) scale(0.98);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

@keyframes workflow-visual-float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-5px); }
}

@media (prefers-reduced-motion: reduce) {
  .workflow-action-visual img {
    animation: none;
  }
}

@media (max-width: 640px) {
  .workflow-action-visual {
    min-height: 144px;
  }

  .workflow-action-visual img {
    width: min(100%, 224px);
  }
}

.detail-hero-text {
  flex: 1 1 360px;
  min-width: 0;
}

.detail-hero-actions {
  display: flex;
  justify-content: flex-end;
  margin-left: auto;
}

.workflow-action-buttons {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 10px;
}

.workflow-action-btn {
  min-height: 42px;
  padding: 10px 20px;
  border-radius: 8px;
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-bold);
  white-space: nowrap;
  line-height: var(--idds-caption-line);
}

.actions-header-inline {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 20px;
  flex-wrap: wrap;
}

.actions-card {
  margin: 0;
  padding: 16px 20px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
  border-color: rgba(228, 224, 213, 1);
}

.detail-tabs-card {
  margin: 14px 0 0;
  padding: 0;
  border: 0;
  border-bottom-left-radius: 0;
  border-bottom-right-radius: 0;
  background: transparent;
  position: relative;
  z-index: 1;
}

.detail-tabs {
  border-bottom: 1px solid var(--ui-border);
  display: flex;
  gap: 18px;
  margin: 0;
  padding: 0;
  overflow-x: auto;
  scrollbar-width: thin;
}

.detail-tabs button {
  min-height: 38px;
  padding: 8px 14px;
  border-radius: 8px;
  white-space: nowrap;
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-semibold);
  line-height: var(--idds-caption-line);
}

.detail-tab-panel {
  margin: 0 0 20px;
  border-top: 0;
  border-top-left-radius: 0;
  border-top-right-radius: 0;
  padding: 22px;
  position: relative;
  z-index: 1;
}

.detail-tab-panel .section-title {
  margin-top: 0;
}

.detail-danger-zone {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-top: 22px;
  padding-top: 18px;
  border-top: 1px solid var(--ina-stroke-primary);
}

.detail-danger-copy {
  min-width: 0;
}

.detail-danger-copy h5 {
  margin: 0 0 4px;
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-bold);
  color: #991b1b;
  line-height: var(--idds-caption-line);
}

.detail-danger-copy p {
  margin: 0;
  max-width: 560px;
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

.detail-danger-btn {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.detail-tab-panel .checklist-card-header {
  margin-bottom: 18px;
  padding-bottom: 18px;
}

.detail-tab-panel .checklist-title {
  color: var(--ui-text) !important;
  font-family: var(--ui-font-display);
  font-size: var(--idds-body-size) !important;
  font-weight: var(--idds-weight-bold) !important;
  line-height: var(--idds-body-line) !important;
  letter-spacing: var(--idds-letter-spacing) !important;
}

.detail-tab-panel .checklist-title svg {
  color: var(--ina-primary-primary);
}

.detail-tab-panel .checklist-add-form {
  background: var(--ina-background-secondary);
  border-radius: 10px;
  box-shadow: none;
}

.detail-tab-panel .checklist-items-list {
  border-top: 1px solid var(--ina-stroke-primary);
}

.detail-tab-panel .checklist-item-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 14px;
  padding: 14px 0;
}

.detail-tab-panel .checklist-item-row:hover {
  background: transparent;
}

.detail-tab-panel .checklist-item-title {
  white-space: normal;
  line-height: var(--idds-caption-line);
}

.detail-tab-panel .checklist-note-input-inline,
.detail-tab-panel .checklist-status-select {
  min-height: 36px;
  border-radius: 8px;
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

.detail-tab-panel .empty-message svg {
  color: var(--ina-content-tertiary);
  opacity: 0.55;
  margin-bottom: 10px;
}

.confirm-delete-note {
  margin: 10px 0 0;
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

.confirm-delete-preview {
  margin-top: 14px;
  padding: 14px 16px;
  border: 1px solid var(--ina-stroke-primary);
  border-radius: 10px;
  background: var(--ina-background-secondary);
}

.confirm-delete-preview p {
  display: -webkit-box;
  margin: 8px 0 0;
  color: var(--ina-content-primary);
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 4;
}

.implementation-checklist-card {
  margin-bottom: 20px;
}

.technical-config-shell {
  display: grid;
  gap: 18px;
}

.technical-config-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 10px;
  border: 1px solid var(--ina-stroke-primary);
  border-radius: 10px;
  background: var(--ina-background-secondary);
}

.technical-config-tab {
  min-height: 38px;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 8px 12px;
  border: 1px solid var(--ina-stroke-primary);
  border-radius: 8px;
  background: #fff;
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-bold);
  cursor: pointer;
  transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease;
  line-height: var(--idds-caption-line);
}

.technical-config-tab:hover {
  border-color: rgba(31, 63, 147, 0.35);
  color: var(--ina-primary-primary);
  background: var(--ina-primary-50);
}

.technical-config-tab.active {
  background: var(--ina-primary-primary);
  border-color: var(--ina-primary-primary);
  color: #fff;
}

.technical-config-content {
  border: 1px solid var(--ina-stroke-primary);
  border-radius: 10px;
  background: #fff;
  overflow: hidden;
}

.technical-config-content-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 18px;
  border-bottom: 1px solid var(--ina-stroke-primary);
  background: var(--ina-background-secondary);
}

.technical-config-content-head h4 {
  margin: 0;
  color: var(--ui-text);
  font-family: var(--ui-font-display);
  font-size: var(--idds-body-small-size);
  font-weight: var(--idds-weight-bold);
  letter-spacing: var(--idds-letter-spacing);
  line-height: var(--idds-body-small-line);
}

.technical-config-count {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border: 1px solid var(--ina-stroke-primary);
  border-radius: 999px;
  background: #fff;
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-small-size);
  font-weight: var(--idds-weight-bold);
  line-height: var(--idds-caption-small-line);
}

.technical-config-groups {
  display: grid;
  gap: 18px;
  padding: 18px;
}

.technical-config-group {
  display: grid;
  gap: 10px;
}

.technical-config-group h5 {
  margin: 0;
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-small-size);
  font-weight: var(--idds-weight-bold);
  letter-spacing: var(--idds-letter-spacing);
  text-transform: uppercase;
  line-height: var(--idds-caption-small-line);
}

.technical-config-items {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 12px;
}

.technical-config-item {
  border: 1px solid var(--ina-stroke-primary);
  border-radius: 10px;
  background: var(--ina-background-secondary);
  overflow: hidden;
}

.technical-config-item-title {
  padding: 12px 14px;
  border-bottom: 1px solid var(--ina-stroke-primary);
  color: var(--ina-content-primary);
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-bold);
  word-break: break-word;
  line-height: var(--idds-caption-line);
}

.technical-config-kv {
  display: grid;
}

.technical-config-row {
  display: grid;
  grid-template-columns: minmax(110px, 0.45fr) minmax(0, 1fr);
  gap: 12px;
  padding: 10px 14px;
  border-bottom: 1px solid rgba(228, 231, 236, 0.75);
}

.technical-config-row:last-child {
  border-bottom: 0;
}

.technical-config-row span {
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-small-size);
  font-weight: var(--idds-weight-bold);
  line-height: var(--idds-caption-small-line);
}

.technical-config-row strong {
  color: var(--ina-content-primary);
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-semibold);
  text-align: right;
  word-break: break-word;
  line-height: var(--idds-caption-line);
}

.technical-config-empty {
  min-height: 112px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: 1px dashed var(--ina-stroke-primary);
  border-radius: 10px;
  background: var(--ina-background-secondary);
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-size);
  text-align: center;
  line-height: var(--idds-caption-line);
}

.technical-config-empty svg {
  color: var(--ina-content-tertiary);
  opacity: 0.7;
}

@media (max-width: 768px) {
  .sticky-header-container {
    position: static;
    gap: 12px;
    margin-bottom: 0;
    padding-bottom: 14px;
  }
  .detail-hero-actions,
  .workflow-action-buttons {
    width: 100%;
  }

  .workflow-action-buttons {
    justify-content: stretch;
  }

  .workflow-action-btn {
    flex: 1 1 100%;
    width: 100%;
  }

  .actions-header-inline {
    flex-direction: column;
    align-items: flex-start;
  }

  .detail-tabs-card,
  .detail-tab-panel,
  .detail-hero-card,
  .actions-card {
    margin-left: 12px;
    margin-right: 12px;
  }

  .detail-tabs-card,
  .detail-tab-panel {
    padding-left: 16px;
    padding-right: 16px;
  }

  .detail-tab-panel .checklist-add-form,
  .detail-tab-panel .checklist-item-row {
    grid-template-columns: 1fr;
  }

  .detail-tab-panel .checklist-item-row {
    align-items: flex-start;
  }

  .detail-danger-zone {
    align-items: stretch;
    flex-direction: column;
  }

  .detail-danger-btn {
    justify-content: center;
    width: 100%;
  }

  .technical-config-tabs {
    flex-wrap: nowrap;
    overflow-x: auto;
  }

  .technical-config-tab {
    flex: 0 0 auto;
  }

  .technical-config-row {
    grid-template-columns: 1fr;
    gap: 4px;
  }

  .technical-config-row strong {
    text-align: left;
  }
}

.detail-hero-breadcrumb {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 8px;
  font-size: var(--idds-caption-small-size);
  line-height: var(--idds-caption-small-line);
}

.dh-bc-link {
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

.dh-bc-link:hover {
  color: rgba(255, 255, 255, 0.9);
}

.dh-bc-sep {
  color: rgba(255, 255, 255, 0.35);
}

.dh-bc-current {
  color: rgba(255, 255, 255, 0.8);
  font-weight: var(--idds-weight-medium);
}

.detail-hero-title {
  margin: 0;
  font-size: var(--idds-heading-h5-size);
  font-weight: var(--idds-weight-bold);
  color: #fff;
  letter-spacing: var(--idds-letter-spacing);
  line-height: var(--idds-heading-h5-line);
}

.detail-hero-sub {
  margin: 4px 0 0;
  font-size: var(--idds-caption-size);
  color: rgba(255, 255, 255, 0.6);
  line-height: var(--idds-caption-line);
}

.detail-hero-refresh {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-medium);
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.3);
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
  cursor: pointer;
  transition: all 0.15s;
  flex-shrink: 0;
  line-height: var(--idds-caption-line);
}

.detail-hero-refresh:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.2);
  border-color: rgba(255, 255, 255, 0.5);
}

.detail-hero-refresh:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.detail-info-card {
  margin: 0 20px 20px;
}

/* Sequential Deployment Layout */
.deploy-sequential {
  display: flex;
  flex-direction: column;
  gap: 0;
  padding-top: 8px;
}
.deploy-step {
  display: flex;
  gap: 16px;
  position: relative;
}
.deploy-step-indicator {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
  width: 36px;
}
.deploy-step-circle {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-bold);
  background: #e2e8f0;
  color: #64748b;
  border: 2px solid #cbd5e1;
  flex-shrink: 0;
  transition: all 0.2s ease;
  line-height: var(--idds-caption-line);
}
.deploy-step--done .deploy-step-circle {
  background: #16a34a;
  color: #fff;
  border-color: #15803d;
}
.deploy-step--locked .deploy-step-circle {
  background: #f1f5f9;
  color: #94a3b8;
  border-color: #e2e8f0;
}
.deploy-step-line {
  flex: 1;
  width: 2px;
  background: #e2e8f0;
  margin: 4px 0;
  min-height: 32px;
}
.deploy-step--done + .deploy-step .deploy-step-line,
.deploy-step--done .deploy-step-line {
  background: #16a34a;
}
.deploy-step-content {
  flex: 1;
  padding: 4px 0 28px;
}
.deploy-step--done .deploy-step-content {
  padding-bottom: 24px;
}
.deploy-step-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
}
.deploy-step-title {
  font-size: var(--idds-body-small-size);
  font-weight: var(--idds-weight-bold);
  color: #1e293b;
  margin: 0 0 4px;
  line-height: var(--idds-body-small-line);
}
.deploy-step--locked .deploy-step-title {
  color: #94a3b8;
}
.deploy-step-desc {
  font-size: var(--idds-caption-size);
  color: #64748b;
  margin: 0;
  line-height: var(--idds-caption-line);
}
.deploy-env-badge {
  font-size: var(--idds-caption-small-size);
  font-weight: var(--idds-weight-semibold);
  padding: 3px 10px;
  border-radius: 20px;
  white-space: nowrap;
  flex-shrink: 0;
  line-height: var(--idds-caption-small-line);
}
.deploy-badge--done {
  background: #dcfce7;
  color: #15803d;
}
.deploy-badge--pending {
  background: #fef9c3;
  color: #854d0e;
}
.deploy-badge--locked {
  background: #f1f5f9;
  color: #94a3b8;
}
.deploy-done-info {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: var(--idds-caption-size);
  color: #4b5563;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  padding: 8px 12px;
  line-height: var(--idds-caption-line);
}
.deploy-done-info svg { flex-shrink: 0; color: #16a34a; }
.deploy-step-action {
  display: flex;
  flex-direction: column;
  gap: 6px;
  align-items: flex-start;
}
.deploy-confirm-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-semibold);
  line-height: var(--idds-caption-line);
}
.deploy-confirm-btn--production {
  background: #16a34a;
  color: #fff;
  border: 2px solid #15803d;
}
.deploy-confirm-btn--production:hover:not(:disabled) {
  background: #15803d;
  box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
}
.deploy-production-warning {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: var(--idds-caption-small-size);
  color: #d97706;
  margin: 0;
  font-style: italic;
  line-height: var(--idds-caption-small-line);
}
.deploy-production-warning svg { flex-shrink: 0; }

.deploy-confirmation-visual {
  display: grid;
  justify-items: center;
  gap: var(--ina-spacing-2);
  padding: 0 0 var(--ina-spacing-3);
  text-align: center;
}

.deploy-confirmation-visual img {
  width: min(280px, 100%);
  height: auto;
  aspect-ratio: 4 / 3;
  object-fit: contain;
  animation: deployment-float 3.4s ease-in-out infinite;
}

.deploy-confirmation-visual p {
  max-width: 40ch;
  margin: 0;
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

@keyframes deployment-float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-6px); }
}

@media (prefers-reduced-motion: reduce) {
  .deploy-confirmation-visual img {
    animation: none;
  }
}


@media (max-width: 768px) {
  .detail-hero-card {
    flex-direction: column;
    align-items: flex-start;
    margin: 0 12px 16px;
    padding: 18px 20px;
  }

  .detail-hero-title {
    font-size: var(--idds-body-size);
    line-height: var(--idds-body-line);
  }

  .detail-info-card {
    margin: 0 12px 16px;
  }

  .detail-grid {
    grid-template-columns: 1fr;
  }

  .detail-hero-refresh {
    width: 100%;
    justify-content: center;
  }
}
.deployment-status-header {
  margin-bottom: 24px;
}
.deployment-status-header h3 {
  margin: 0 0 4px;
  font-size: var(--idds-body-size);
  color: #1e3a8a;
  line-height: var(--idds-body-line);
}

/* Unified detail workspace */
.app-detail-container {
  display: grid;
  align-content: start;
  gap: 20px;
}

.detail-root-state {
  min-height: 320px;
  display: grid;
  align-items: center;
}

.uk-stepper-wrap,
.detail-tabs-card,
.detail-tab-panel,
.detail-info-card,
.unit-doc-card,
.uk-context-box,
.actions-card {
  margin: 0;
}

.detail-tabs-card .detail-tabs button {
  min-height: 44px;
  padding: 10px 2px 9px;
  border: 0;
  border-bottom: 3px solid transparent;
  border-radius: 0;
  color: var(--ui-text-muted);
  background: transparent;
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-bold);
  line-height: var(--idds-caption-line);
}

.detail-tabs-card .detail-tabs button:hover {
  color: var(--ui-primary);
  background: transparent;
}

.detail-tabs-card .detail-tabs button.active {
  border-bottom-color: var(--ui-primary);
  color: var(--ui-primary);
  background: transparent;
}

.detail-tab-panel,
.unit-doc-card,
.uk-stepper-wrap {
  border: 1px solid var(--ui-border);
  border-radius: var(--ui-radius);
  background: var(--ui-surface);
  box-shadow: var(--ui-shadow-xs);
}

.unit-doc-desc {
  min-height: 42px;
  margin-bottom: 10px;
}

.unit-doc-actions:empty {
  display: none;
}

@media (max-width: 768px) {
  .app-detail-container {
    gap: 16px;
  }

  .detail-tabs {
    gap: 14px;
    overflow-x: auto;
  }

  .detail-tab-panel,
  .unit-doc-card,
  .uk-stepper-wrap {
    padding: 16px;
  }

  .unit-doc-desc {
    min-height: 0;
  }

  .detail-hero-text {
    flex-basis: auto;
  }
}
</style>


