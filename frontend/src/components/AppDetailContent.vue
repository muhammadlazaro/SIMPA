<script setup>
import { ref, onMounted, onBeforeUnmount, watch, computed, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import http from '../lib/http'
import Icons from './Icons.vue'
import DetailInfoGrid from './DetailInfoGrid.vue'
import { useToastStore } from '../stores/toast'
import { useAuthStore } from '../stores/auth'
import { getHomeByRole } from '../constants/roles'
import { warnDev } from '../utils/logger'
import { getHttpMethodClass, getShortStatusLabel } from '../constants/status'

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
const savingDeploymentStatus = ref(false)
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
const securityReviewForm = ref({
  security_test_passed: null,
  security_test_notes: '',
  note: '',
})
const securityNotes = ref([])

// ==== WORKFLOW (Checklist & Catatan) - untuk semua role selain unit_kerja ====
const workflowLoading = ref(false)
const workflowError = ref('')
const checklistForm = ref({ title: '', notes: '' })
const noteForm = ref({ body: '' })
const savingChecklist = ref(false)
const savingNote = ref(false)
const updatingChecklistId = ref(null)

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

const availableActions = computed(() => {
  const actions = []
  const role = auth.role
  const status = app.value?.status

  if (role === 'unit_kerja' && status === 'perlu_perbaikan_pengajuan') {
    actions.push({
      label: 'Perbaiki Pengajuan',
      endpoint: '/workflow/perbaikan-pengajuan',
      btnClass: 'btn-primary',
      requiresNote: true,
      noteLabel: 'Catatan Perbaikan'
    })
  } else if (role === 'pengelola_aplikasi' && status === 'diajukan') {
    actions.push(
      { label: 'Setujui Pengajuan', endpoint: '/workflow/verifikasi-pengajuan', payload: { status_target: 'terverifikasi' }, btnClass: 'btn-success', requiresNote: true },
      { label: 'Minta Perbaikan', endpoint: '/workflow/verifikasi-pengajuan', payload: { status_target: 'perlu_perbaikan_pengajuan' }, btnClass: 'btn-warning', requiresNote: true },
      { label: 'Tolak Pengajuan', endpoint: '/workflow/verifikasi-pengajuan', payload: { status_target: 'ditolak' }, btnClass: 'btn-danger', requiresNote: true }
    )
  } else if (role === 'pengelola_aplikasi' && status === 'terverifikasi') {
    actions.push(
      { label: 'Nyatakan Layak', endpoint: '/workflow/studi-kelayakan', payload: { is_layak: true }, btnClass: 'btn-success', requiresNote: true },
      { label: 'Nyatakan Tidak Layak', endpoint: '/workflow/studi-kelayakan', payload: { is_layak: false }, btnClass: 'btn-danger', requiresNote: true }
    )
  } else if (role === 'analis_desain' && status === 'layak') {
    actions.push({ label: 'Mulai Analisa Desain', endpoint: '/workflow/mulai-analisa-desain', btnClass: 'btn-primary', requiresNote: false })
  } else if (role === 'tim_implementasi_aplikasi' && status === 'analisa_desain') {
    actions.push({ label: 'Mulai Pengembangan', endpoint: '/workflow/mulai-pengembangan', btnClass: 'btn-primary', requiresNote: true })
  } else if (role === 'tim_implementasi_aplikasi' && status === 'pengembangan') {
    actions.push({ label: 'Tandai Siap UAT', endpoint: '/workflow/siap-uat', btnClass: 'btn-success', requiresNote: true })
  } else if (role === 'pengelola_aplikasi' && status === 'uat') {
    actions.push(
      { label: 'UAT Sesuai', endpoint: '/workflow/verifikasi-uat', payload: { is_sesuai: true }, btnClass: 'btn-success', requiresNote: true },
      { label: 'UAT Perlu Perbaikan', endpoint: '/workflow/verifikasi-uat', payload: { is_sesuai: false }, btnClass: 'btn-warning', requiresNote: true }
    )
  } else if (role === 'tim_implementasi_aplikasi' && status === 'perbaikan_uat') {
    actions.push({ label: 'Selesai Perbaikan UAT', endpoint: '/workflow/selesai-perbaikan-uat', btnClass: 'btn-primary', requiresNote: true })
  } else if (role === 'tim_uji_keamanan' && status === 'uji_keamanan') {
    actions.push(
      { label: 'Uji Keamanan Lolos', endpoint: '/workflow/hasil-uji-keamanan', payload: { is_lolos: true }, btnClass: 'btn-success', requiresNote: true },
      { label: 'Uji Keamanan Tidak Lolos', endpoint: '/workflow/hasil-uji-keamanan', payload: { is_lolos: false }, btnClass: 'btn-danger', requiresNote: true }
    )
  } else if (role === 'tim_implementasi_aplikasi' && status === 'perbaikan_keamanan') {
    actions.push({ label: 'Selesai Perbaikan Keamanan', endpoint: '/workflow/selesai-perbaikan-keamanan', btnClass: 'btn-primary', requiresNote: true })
  }
  // DevOps: tombol deploy dipindah ke Status Deployment section (sequential flow)

  return actions
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

const basePath = computed(() => getHomeByRole(auth.role).path)
/** Teks breadcrumb item kedua */
const listBreadcrumbLabel = computed(() => {
  if (props.analystMode) return 'Analisa & Desain'
  if (props.securityMode) return 'Uji Keamanan Aplikasi'
  if (auth.role === 'unit_kerja') return 'Pengajuan saya'
  if (['tim_implementasi_aplikasi', 'devops_developer'].includes(auth.role || '')) return 'Kelola Aplikasi'
  return 'Aplikasi'
})
/** Subjek untuk copy empty state (siapa yang mengisi analisa) */
const whoFillsAnalisa = computed(() =>
  props.analystMode ? 'Anda' : 'Analis desain'
)
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
    title: 'Laporan Analisa Desain',
    desc: 'Laporan hasil analisa desain dari tim analis.',
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
const isNonUnitKerjaRole = computed(() => !props.unitKerjaMode && (props.analystMode || props.securityMode || props.devopsMode || props.implementationMode || props.pengelolaMode || isImplementationRole.value || isDevOpsRole.value || isPengelolaRole.value))
const isDocumentPanelMode = computed(() => props.unitKerjaMode || props.analystMode || props.securityMode || props.pengelolaMode || isImplementationRole.value || isDevOpsRole.value || isPengelolaRole.value)
const canDeactivateApp = computed(() => isPengelolaRole.value && app.value?.status === 'deployed_production')
const shouldLoadDocuments = computed(() => isDocumentPanelMode.value)
const showTechnicalSections = computed(() => !props.unitKerjaMode && !props.securityMode && !props.analystMode)
const showTabMenu = computed(() => true)
const isImplementationContext = computed(() => isImplementationRole.value || isDevOpsRole.value || props.implementationMode)
const showFeasibilityChecklistPanel = computed(() =>
  isNonUnitKerjaRole.value &&
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

// Flat tabs for all roles
const COMMON_TABS = [
  { id: 'informasi',  label: 'Informasi' },
  { id: 'checklist', label: 'Checklist' },
  { id: 'catatan',   label: 'Catatan' },
  { id: 'dokumen',   label: 'Dokumen' },
]

const availableMainTabs = computed(() => {
  // Pengelola Aplikasi: 4 tab standar (informasi, checklist, catatan, dokumen)
  if (props.pengelolaMode || isPengelolaRole.value) {
    return COMMON_TABS
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
      ...COMMON_TABS,
      { id: 'hasil', label: 'Hasil Uji' },
    ]
  }
  return []
})

/**
 * Apakah role saat ini dapat mengupload dokumen section ini sekarang?
 * Berdasarkan pemetaan uploadRoles + uploadStatuses di MASTER_DOC_SECTIONS.
 */
function docSectionCanUploadNow(section) {
  if (!app.value || !section) return false
  return section.uploadRoles.includes(auth.role) &&
         section.uploadStatuses.includes(app.value?.status)
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
const implementationRoleLabel = computed(() => {
  if (auth.role === 'tim_implementasi_aplikasi') return 'Implementasi'
  if (auth.role === 'devops_developer') return 'DevOps'
  return 'Implementasi'
})

const implementationChecklistTitle = computed(() =>
  isDevOpsRole.value ? 'Checklist DevOps' : 'Checklist Implementasi'
)

const implementationChecklistEmptyText = computed(() =>
  isDevOpsRole.value
    ? 'Tambahkan item pertama untuk melacak kesiapan deployment.'
    : 'Tambahkan item pertama untuk melacak progres implementasi.'
)

onMounted(async () => {
  await loadData()
})

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
    securityReviewForm.value = {
      security_test_passed: review.security_test_passed ?? null,
      security_test_notes: review.security_test_notes || '',
      note: '',
    }
    securityNotes.value = data?.data?.security_notes || []
  } catch (error) {
    const msg = error?.response?.data?.message || 'Gagal memuat data uji keamanan.'
    toastStore.push(msg, 'error')
  } finally {
    loadingSecurityReview.value = false
  }
}

const securityNotesLimit = 500
const securityNoteLimit = 300

const securityReviewErrors = computed(() => {
  const errors = []
  if (securityReviewForm.value.security_test_passed === null) {
    errors.push('Status uji keamanan wajib dipilih.')
  }
  const notes = securityReviewForm.value.security_test_notes?.trim() || ''
  const note = securityReviewForm.value.note?.trim() || ''
  if (notes.length > securityNotesLimit) {
    errors.push(`Ringkasan hasil uji maksimal ${securityNotesLimit} karakter.`)
  }
  if (note.length > securityNoteLimit) {
    errors.push(`Catatan perbaikan maksimal ${securityNoteLimit} karakter.`)
  }
  return errors
})

const canSaveSecurityReview = computed(() => securityReviewErrors.value.length === 0)

const securityNotesRemaining = computed(() => {
  const notes = securityReviewForm.value.security_test_notes || ''
  return securityNotesLimit - notes.length
})

const securityNoteRemaining = computed(() => {
  const note = securityReviewForm.value.note || ''
  return securityNoteLimit - note.length
})

async function saveSecurityReview() {
  if (!props.securityMode) return
  if (!canSaveSecurityReview.value) return
  savingSecurityReview.value = true
  try {
    await http.put(`/aplikasi/${route.params.id}/security-review`, {
      security_test_passed: !!securityReviewForm.value.security_test_passed,
      security_test_notes: securityReviewForm.value.security_test_notes?.trim() || null,
      note: securityReviewForm.value.note?.trim() || null,
    })
    securityReviewForm.value.note = ''
    await loadData()
    toastStore.push('Hasil uji keamanan berhasil disimpan.', 'success')
  } catch (error) {
    const msg = error?.response?.data?.message || 'Gagal menyimpan hasil uji keamanan.'
    toastStore.push(msg, 'error')
  } finally {
    savingSecurityReview.value = false
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
    // Simpan review detail
    await http.put(`/aplikasi/${route.params.id}/security-review`, {
      security_test_passed: isLolos,
      security_test_notes: notes,
      note: securityReviewForm.value.note?.trim() || null,
    })
    // Trigger workflow
    await http.post(`/aplikasi/${route.params.id}/workflow/hasil-uji-keamanan`, {
      is_lolos: isLolos,
      catatan: notes,
    })
    securityReviewForm.value.note = ''
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

async function updateDeploymentStatus(environment, isDeployed) {
  if (!isDevOpsRole.value) return
  savingDeploymentStatus.value = true
  try {
    await http.put(`/aplikasi/${route.params.id}/deployment-status`, {
      environment,
      deployed: isDeployed,
      notes: deploymentStatus.value.notes?.trim() || null
    })
    if (isDeployed && app.value) {
      app.value.status = environment === 'production' ? 'deployed_production' : 'deployed_staging'
    }
    await loadDeploymentStatus()
    toastStore.push(`Status deployment ${environment} berhasil diperbarui.`, 'success')
  } catch (error) {
    const msg = error?.response?.data?.message || 'Gagal memperbarui status deployment.'
    toastStore.push(msg, 'error')
  } finally {
    savingDeploymentStatus.value = false
  }
}

function selectDocumentFile(type, event) {
  const file = event?.target?.files?.[0] || null
  selectedFiles.value[type] = file
}

function getLatestDoc(type) {
  const list = documentsByType.value[type] || []
  if (list.length === 0) return null
  const sorted = [...list].sort((a, b) => (b.version || 0) - (a.version || 0))
  return sorted[0]
}

const latestAnalystReport = computed(() => getLatestDoc('laporan_analisa_desain'))

const implementationAnalisaSummary = computed(() => {
  const items = analisaDesains.value || []
  const ui = [...new Set(items.filter((a) => a.ui_platform).map((a) => a.ui_platform))]
  const interop = [...new Set(items.filter((a) => a.interop_type).map((a) => a.interop_type))]
  const storage = [...new Set(items.filter((a) => a.storage_type).map((a) => a.storage_type))]
  const aktor = [...new Set(items.filter((a) => a.nama_aktor).map((a) => a.nama_aktor))]
  const transaksi = items.filter((a) => a.method && a.url).length
  return { ui, interop, storage, aktor, transaksi }
})

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

async function deleteChecklist(item) {
  if (!confirm(`Yakin ingin menghapus checklist "${item.title}"?`)) return
  try {
    await http.delete(`/aplikasi/${route.params.id}/checklists/${item.id}`)
    toastStore.push('Checklist berhasil dihapus.', 'success')
    await loadWorkflow()
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal menghapus checklist.'
    toastStore.push(message, 'error')
  }
}

async function addNote() {
  if (!noteForm.value.body.trim()) {
    toastStore.push('Isi catatan tidak boleh kosong.', 'error')
    return
  }
  // Auto-kategorisasi berdasarkan role
  let autoType = 'info'
  if (auth.role === 'tim_uji_keamanan') autoType = 'uji_keamanan'
  else if (['tim_implementasi_aplikasi', 'devops_developer'].includes(auth.role || '')) autoType = 'perbaikan'
  else if (auth.role === 'analis_desain') autoType = 'info'

  savingNote.value = true
  try {
    await http.post(`/aplikasi/${route.params.id}/notes`, {
      note_type: autoType,
      body: noteForm.value.body.trim(),
    })
    noteForm.value = { body: '' }
    toastStore.push('Catatan berhasil ditambahkan.', 'success')
    await loadWorkflow()
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal menambah catatan.'
    toastStore.push(message, 'error')
  } finally {
    savingNote.value = false
  }
}

async function deleteNote(item) {
  if (!confirm('Yakin ingin menghapus catatan ini?')) return
  try {
    await http.delete(`/aplikasi/${route.params.id}/notes/${item.id}`)
    toastStore.push('Catatan berhasil dihapus.', 'success')
    await loadWorkflow()
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal menghapus catatan.'
    toastStore.push(message, 'error')
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

function noteTypeLabel(value) {
  const map = { perbaikan: 'Perbaikan', uji_keamanan: 'Uji Keamanan', info: 'Info' }
  return map[value] || value || 'Catatan'
}
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

function formatChecklistStatus(value) {
  if (value === 'done') return 'Selesai'
  if (value === 'in_progress') return 'Diproses'
  return 'Pending'
}

function getChecklistBadgeClass(value) {
  if (value === 'done') return 'badge-success'
  if (value === 'in_progress') return 'badge-warning'
  return 'badge-info'
}

async function focusImplementationTitle() {
  await nextTick()
  implementationTitleInput.value?.focus()
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

// Frontend: Generate dari UI Platform di Analisa Desain
const frontendData = computed(() => {
  if (!app.value || !analisaDesains.value.length) return { proyeks: [], configs: [], apiGatewayDev: [], apiGateway: [] }
  
  // Get unique UI Platforms
  const uiPlatforms = [...new Set(analisaDesains.value.filter(ad => ad.ui_platform).map(ad => ad.ui_platform))]
  
  // Generate proyek frontend dari UI Platform
  const proyeks = uiPlatforms.map(platform => ({
    id: `frontend-${platform}`,
    modul: `${app.value.nama_aplikasi}-${platform}`,
    jenis: 'Frontend'
  }))
  
  // Generate URL config
  const configs = uiPlatforms.length > 0 ? [{
    local_url: `/spl-dev.bssn.go.id/${app.value.nama_singkat?.toLowerCase() || ''}`,
    feat_staging_production_url: `/api/${app.value.nama_singkat?.toLowerCase() || ''}`
  }] : []
  
  // API Gateway SPL Dev & SPL (dari interop_type)
  const interops = [...new Set(analisaDesains.value.filter(ad => ad.interop_type).map(ad => ad.interop_type))]
  const apiGatewayDev = interops.length > 0 ? [{
    service_name: app.value.nama_singkat?.toLowerCase() || '',
    path: '/api',
    route_name: app.value.nama_singkat?.toLowerCase() || '',
    full_path: `/${app.value.nama_singkat?.toLowerCase() || ''}`
  }] : []
  
  const apiGateway = interops.length > 0 ? [{
    service_name: app.value.nama_singkat?.toLowerCase() || '',
    path: '/api',
    route_name: app.value.nama_singkat?.toLowerCase() || '',
    full_path: `/${app.value.nama_singkat?.toLowerCase() || ''}`
  }] : []
  
  return { proyeks, configs, apiGatewayDev, apiGateway }
})

// Backend: Generate dari Storage Type 'db' dan Transaksi di Analisa Desain
const backendData = computed(() => {
  if (!app.value) return { 
    proyeks: [], 
    databaseStaging: [],
    databaseProduction: [],
    objectStorageDev: [],
    objectStorage: [],
    apiGatewayDev: [],
    apiGateway: [],
    auth: [],
    env: [],
    endpoints: [] 
  }
  
  // Get storage dengan type 'db' untuk database config
  const hasDatabase = analisaDesains.value.some(ad => ad.storage_type === 'db')
  const hasObjectStorage = analisaDesains.value.some(ad => ad.storage_type === 'object-storage')
  const interops = [...new Set(analisaDesains.value.filter(ad => ad.interop_type).map(ad => ad.interop_type))]
  
  // Generate proyek backend
  const proyeks = hasDatabase ? [{
    id: 'backend-main',
    modul: `${app.value.nama_aplikasi}-backend`,
    jenis: 'Backend'
  }] : []
  
  // Database Staging
  const databaseStaging = hasDatabase ? [{
    db_connection: 'mysql',
    db_host: 'dbt-dev.bssn.go.id',
    db_port: '3306',
    db_database: app.value.nama_singkat?.toLowerCase() || '',
    db_username: app.value.nama_singkat?.toLowerCase() || ''
  }] : []
  
  // Database Production
  const databaseProduction = hasDatabase ? [{
    db_connection: 'mysql',
    db_host: 'dbt.bssn.go.id',
    db_port: '3306',
    db_database: app.value.nama_singkat?.toLowerCase() || '',
    db_username: app.value.nama_singkat?.toLowerCase() || ''
  }] : []
  
  // Object Storage Minio Dev
  const objectStorageDev = hasObjectStorage ? [{
    bucket: app.value.nama_singkat?.toLowerCase() || '',
    region: 'us-east-1',
    endpoint: 'https://minio-dev.bssn.go.id:9000'
  }] : []
  
  // Object Storage Minio
  const objectStorage = hasObjectStorage ? [{
    bucket: app.value.nama_singkat?.toLowerCase() || '',
    region: 'us-east-1',
    endpoint: 'https://minio.bssn.go.id:9000'
  }] : []
  
  // API Gateway SPL Dev
  const apiGatewayDev = interops.length > 0 ? [{
    service_name: app.value.nama_singkat?.toLowerCase() || '',
    path: '/api',
    route_name: app.value.nama_singkat?.toLowerCase() || '',
    full_path: `/${app.value.nama_singkat?.toLowerCase() || ''}`
  }] : []
  
  // API Gateway SPL
  const apiGateway = interops.length > 0 ? [{
    service_name: app.value.nama_singkat?.toLowerCase() || '',
    path: '/api',
    route_name: app.value.nama_singkat?.toLowerCase() || '',
    full_path: `/${app.value.nama_singkat?.toLowerCase() || ''}`
  }] : []
  
  // Auth (dikosongkan - tetap tampil dengan empty state)
  const auth = []
  
  // Env (dikosongkan - tetap tampil dengan empty state)
  const env = []
  
  // Endpoints dari transaksi
  const endpoints = analisaDesains.value.filter(ad => ad.method && ad.url)
  
  return { 
    proyeks, 
    databaseStaging,
    databaseProduction,
    objectStorageDev,
    objectStorage,
    apiGatewayDev,
    apiGateway,
    auth,
    env,
    endpoints 
  }
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

// Filter proyek berdasarkan role user
const filteredProyeks = computed(() => {
  const allProyeks = devopsData.value.proyeks || []
  const userRole = auth.role || 'tim_implementasi_aplikasi'
  
  // DevOps: tampilkan semua proyek
  if (userRole === 'devops_developer') {
    return allProyeks
  }

  // Tim implementasi: tampilkan semua proyek (frontend + backend)
  if (userRole === 'tim_implementasi_aplikasi') {
    return allProyeks
  }

  // Analis desain: pratinjau penuh modul (Frontend + Backend) dari data analisa
  if (userRole === 'analis_desain' || props.analystMode) {
    return allProyeks
  }
  
  // Default: tampilkan semua (fallback)
  return allProyeks
})

const TECHNICAL_CONFIG_TABS = [
  { id: 'proyek', label: 'Proyek', icon: 'code' },
  { id: 'database', label: 'Database', icon: 'server' },
  { id: 'objectStorage', label: 'Object Storage', icon: 'inbox' },
  { id: 'apiGateway', label: 'API Gateway', icon: 'settings' },
  { id: 'environment', label: 'Environment', icon: 'file' },
  { id: 'auth', label: 'Auth', icon: 'server' },
]

function formatConfigKey(key) {
  return String(key || '')
    .replace(/_/g, ' ')
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
    key: ['terverifikasi', 'layak', 'tidak_layak'],
    label: 'Verifikasi & Kelayakan',
    desc: 'Tim pengelola memverifikasi dan menilai kelayakan pengajuan',
    icon: 'search',
  },
  {
    key: ['analisa_desain'],
    label: 'Analisa & Desain',
    desc: 'Tim analis menyusun laporan analisa dan desain teknis',
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
  const map = {
    'diajukan': { type: 'info', title: 'Menunggu Verifikasi', desc: 'Pengajuan Anda sedang diperiksa oleh tim pengelola. Tidak ada tindakan yang perlu Anda lakukan saat ini.' },
    'perlu_perbaikan_pengajuan': { type: 'warning', title: 'Perbaikan Diperlukan', desc: 'Tim pengelola meminta perbaikan pada formulir pengajuan Anda. Silakan cek catatan di bawah.' },
    'terverifikasi': { type: 'info', title: 'Terverifikasi', desc: 'Pengajuan Anda telah diverifikasi dan sedang menunggu jadwal penilaian kelayakan.' },
    'layak': { type: 'success', title: 'Lolos Kelayakan', desc: 'Pengajuan Anda dinyatakan layak. Tim analis akan segera menyusun dokumen analisa & desain teknis.' },
    'tidak_layak': { type: 'danger', title: 'Tidak Layak', desc: 'Pengajuan Anda dinyatakan tidak layak dan proses dihentikan.' },
    'analisa_desain': { type: 'info', title: 'Analisa & Desain', desc: 'Tim analis sedang menyusun dokumen teknis berdasarkan pengajuan Anda.' },
    'pengembangan': { type: 'info', title: 'Pengembangan', desc: 'Aplikasi sedang dibangun oleh tim implementasi.' },
    'uat': { type: 'warning', title: 'Tindakan Diperlukan: UAT', desc: 'Aplikasi siap diuji. Silakan unduh format UAT, lakukan pengujian, dan unggah hasilnya di panel dokumen di bawah.' },
    'perbaikan_uat': { type: 'warning', title: 'Perbaikan UAT', desc: 'Tim implementasi sedang memperbaiki temuan dari pengujian (UAT) Anda.' },
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
    <div class="container">
      <!-- Sticky Header Area for Desktop -->
      <div class="sticky-header-container">
        <!-- Hero header card (konsisten dengan dashboard) -->
        <div class="detail-hero-card">
          <div class="detail-hero-text">
            <nav class="detail-hero-breadcrumb" aria-label="breadcrumb">
              <button @click="router.push(basePath)" class="dh-bc-link">
                <Icons name="dashboard" :size="12" />
                Dashboard
              </button>
              <span class="dh-bc-sep">/</span>
              <button @click="router.push(basePath)" class="dh-bc-link">
                {{ listBreadcrumbLabel }}
              </button>
              <span class="dh-bc-sep">/</span>
              <span class="dh-bc-current">{{ app?.nama_aplikasi || app?.nama_singkat || 'Detail' }}</span>
            </nav>
            <h1 class="detail-hero-title">Detail Aplikasi</h1>
            <p class="detail-hero-sub">{{ app?.nama_aplikasi || app?.nama_singkat || 'Detail aplikasi' }}</p>
          </div>

        </div>

        <!-- ===== ACTION BUTTONS (WORKFLOW) - disembunyikan untuk security role ===== -->
        <div v-if="!loading && availableActions.length > 0 && !props.securityMode" class="card actions-card">
          <div class="actions-header-inline">
            <div>
              <div class="actions-title-row">
                <h3 class="detail-section-title">Aksi Workflow</h3>
                <span v-if="app?.status" class="workflow-stage-badge">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  {{ getShortStatusLabel(app.status) }}
                </span>
              </div>
              <p class="actions-subtitle">Silakan pilih aksi berikut untuk memproses aplikasi ke tahap selanjutnya.</p>
            </div>
            <div class="actions-row">
              <button
                v-for="(action, idx) in availableActions"
                :key="idx"
                class="btn"
                :class="action.btnClass || 'btn-primary'"
                @click="openActionModal(action)"
              >
                {{ action.label }}
              </button>
            </div>
          </div>
        </div>
        <!-- ===================================== -->
      </div>


      <!-- ===== UNIFIED MAIN TAB NAVIGATION (semua role kecuali unit_kerja) ===== -->
      <div v-if="!loading && isNonUnitKerjaRole && availableMainTabs.length > 0" class="card detail-tabs-card">
        <nav class="nav-menu workspace-tabs detail-tabs">
          <button
            v-for="tab in availableMainTabs"
            :key="tab.id"
            :class="{ active: activeTab === tab.id }"
            @click="activeTab = tab.id"
          >
            {{ tab.label }}
          </button>
        </nav>
      </div>
      <!-- =========================================================== -->

      <!-- ===== TAB: INFORMASI (semua non-unit-kerja role) ===== -->
      <div v-if="!loading && isNonUnitKerjaRole && activeTab === 'informasi'" class="card detail-tab-panel detail-info-card">
        <h4 class="section-title">Informasi Aplikasi</h4>
        <DetailInfoGrid :app="app" />
        <div v-if="canDeactivateApp" class="detail-danger-zone">
          <div class="detail-danger-copy">
            <h5>Nonaktifkan Aplikasi</h5>
            <p>Aksi ini digunakan untuk aplikasi production yang sudah tidak digunakan, tanpa menghapus riwayat dan dokumennya.</p>
          </div>
          <button type="button" class="btn btn-danger detail-danger-btn" @click="openDeactivateModal">
            <Icons name="trash" :size="14" />
            Nonaktifkan
          </button>
        </div>
      </div>
      <!-- ======================================================= -->

      <!-- ===== TAB: CHECKLIST (semua non-unit-kerja role) ===== -->
      <div v-if="!loading && showFeasibilityChecklistPanel" class="detail-tab-panel card">
        <!-- Header + Stats + Progress bar -->
        <div class="checklist-card-header">
          <div class="checklist-header-top">
            <h4 class="checklist-title">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
              Checklist Kelayakan
            </h4>
            <div class="checklist-stat-chips">
              <span class="stat-chip stat-chip--done">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                {{ checklistStats.done }} Selesai
              </span>
              <span class="stat-chip stat-chip--pending">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/></svg>
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
        <div class="checklist-add-form">
          <div class="checklist-add-inputs">
            <div class="checklist-add-field">
              <label class="checklist-add-label">Item baru</label>
              <input
                v-model="checklistForm.title"
                type="text"
                placeholder="Contoh: Dokumen sudah lengkap..."
                maxlength="120"
                @keyup.enter="checklistForm.title?.trim() && addChecklist()"
              />
            </div>
            <div class="checklist-add-field">
              <label class="checklist-add-label">Catatan <span class="muted">(opsional)</span></label>
              <input v-model="checklistForm.notes" type="text" placeholder="Catatan tambahan..." maxlength="240" />
            </div>
          </div>
          <button
            class="btn btn-primary checklist-add-btn"
            :disabled="savingChecklist || !checklistForm.title?.trim()"
            @click="addChecklist"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            {{ savingChecklist ? 'Menyimpan...' : 'Tambah' }}
          </button>
        </div>

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
            <!-- Ikon status -->
            <div class="checklist-item-indicator">
              <svg v-if="item.item_status === 'done'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>
            </div>
            <!-- Konten -->
            <div class="checklist-item-body">
              <span class="checklist-item-title" :class="{ 'done-text': item.item_status === 'done' }">{{ item.title }}</span>
              <span v-if="item.item_status !== 'done'" class="checklist-item-inline-note">
                <input
                  v-model="item.notes"
                  class="checklist-note-input-inline"
                  type="text"
                  placeholder="Catatan (opsional)"
                  maxlength="240"
                  :disabled="updatingChecklistId === item.id"
                  @change="updateChecklist(item, { notes: item.notes?.trim() || null })"
                />
              </span>
              <span v-else-if="item.notes" class="checklist-item-notes">{{ item.notes }}</span>
            </div>
            <!-- Aksi -->
            <div class="checklist-item-action checklist-item-actions-group">
              <label class="checklist-toggle-compact">
                <input
                  type="checkbox"
                  :checked="item.item_status === 'done'"
                  :disabled="updatingChecklistId === item.id"
                  @change="updateChecklist(item, { item_status: $event.target.checked ? 'done' : 'pending' })"
                />
                <span>{{ item.item_status === 'done' ? 'Layak' : 'Belum layak' }}</span>
              </label>
              <button
                class="btn btn-icon btn-delete"
                @click="deleteChecklist(item)"
                aria-label="Hapus checklist"
                :disabled="updatingChecklistId === item.id"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
              </button>
            </div>
          </div>
        </div>
      </div>
      <!-- =========================================================== -->

      <!-- ===== TAB: CATATAN (semua non-unit-kerja role) ===== -->
      <div v-if="!loading && isNonUnitKerjaRole && activeTab === 'catatan'" class="detail-tab-panel card">
        <h4 class="section-title">Riwayat Catatan &amp; Diskusi</h4>
        <!-- Form tambah catatan -->
        <div :class="['modern-note-form', { 'modern-note-form--empty-hint': !filteredNotes.length && !workflowLoading }]">
          <textarea
            v-model="noteForm.body"
            rows="3"
            placeholder="Ketik catatan, hasil review, atau informasi terkait aplikasi ini..."
            maxlength="500"
            class="modern-textarea"
          ></textarea>
          <div class="modern-note-actions">
            <button class="btn btn-primary modern-submit-btn" :disabled="savingNote || !noteForm.body.trim()" @click="addNote">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              {{ savingNote ? 'Menyimpan...' : 'Simpan Catatan' }}
            </button>
          </div>
        </div>
        <div class="timeline-container">
          <div v-if="workflowLoading" class="muted">Memuat catatan...</div>
          <div v-else-if="workflowError" class="muted">{{ workflowError }}</div>
          <div v-else-if="!filteredNotes.length" class="empty-message note-empty">
            <Icons name="inbox" :size="36" />
            <p class="empty-title">Belum ada catatan diskusi.</p>
            <p class="empty-message-hint">Gunakan form di atas untuk memulai diskusi pertama.</p>
          </div>
          <div v-else class="timeline-list">
            <div v-for="note in filteredNotes" :key="note.id" class="timeline-item">
              <div class="timeline-avatar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </div>
              <div class="timeline-content">
                <div class="timeline-header">
                  <span class="timeline-author">{{ note.creator?.name || 'Sistem' }}</span>
                  <span v-if="note.note_type !== 'info'" class="timeline-badge" :class="note.note_type">{{ noteTypeLabel(note.note_type) }}</span>
                  <span class="timeline-time">{{ formatDateTime(note.created_at) }}</span>
                  <button class="btn btn-icon btn-delete btn-delete-timeline" @click="deleteNote(note)" aria-label="Hapus catatan">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                  </button>
                </div>
                <div class="timeline-body">
                  <p>{{ note.body }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- =========================================================== -->

      <!-- ===== STEPPER PROGRES PENGAJUAN (Unit Kerja only) ===== -->
      <div v-if="!loading && props.unitKerjaMode && app" class="uk-stepper-wrap">
        <div class="uk-stepper-header">
          <div class="uk-stepper-title-row">
            <h3 class="uk-stepper-title">Progres Pengajuan</h3>
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
            <!-- Connector line -->
            <div v-if="idx < progressSteps.length - 1" class="uk-step-connector">
              <div :class="['uk-step-connector-fill', { filled: getStepState(idx) === 'done' || (getStepState(idx) === 'active') }]"></div>
            </div>

            <!-- Icon circle -->
            <div class="uk-step-icon-wrap">
              <div class="uk-step-icon">
                <!-- Done -->
                <svg v-if="getStepState(idx) === 'done'" viewBox="0 0 20 20" fill="currentColor" width="16" height="16">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
                <!-- Active -->
                <div v-else-if="getStepState(idx) === 'active'" class="uk-step-active-dot"></div>
                <!-- Pending -->
                <span v-else class="uk-step-num">{{ idx + 1 }}</span>
              </div>
            </div>

            <!-- Label only; deskripsi muncul saat hover via title. -->
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


      <div v-if="!loading && showImplementationChecklistPanel" class="card detail-tab-panel implementation-checklist-card">
        <!-- Header + Progress Stats -->
        <div class="checklist-card-header">
          <div class="checklist-header-top">
            <h4 class="checklist-title">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
              {{ implementationChecklistTitle }}
            </h4>
            <div class="checklist-stat-chips">
              <span class="stat-chip stat-chip--done">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                {{ implementationChecklistStats.done }} Selesai
              </span>
              <span class="stat-chip stat-chip--progress">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                {{ implementationChecklistStats.inProgress }} Proses
              </span>
              <span class="stat-chip stat-chip--pending">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/></svg>
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
        <div class="checklist-add-form">
          <div class="checklist-add-inputs">
            <div class="checklist-add-field">
              <label class="checklist-add-label">Item baru</label>
              <input
                v-model="newImplementationItem.title"
                type="text"
                placeholder="Contoh: Implementasi halaman login..."
                maxlength="120"
                ref="implementationTitleInput"
                :class="{ 'input-invalid': !!implementationTitleErrorVisible }"
                @blur="implementationItemTitleTouched = true"
                @keyup.enter="canAddImplementationItem && addImplementationChecklistItem()"
              />
              <p v-if="implementationTitleErrorVisible" class="form-hint error">{{ implementationTitleErrorVisible }}</p>
            </div>
            <div class="checklist-add-field">
              <label class="checklist-add-label">Catatan <span class="muted">(opsional)</span></label>
              <input
                v-model="newImplementationItem.notes"
                type="text"
                placeholder="Catatan tambahan..."
                maxlength="240"
              />
            </div>
          </div>
          <button
            class="btn btn-primary checklist-add-btn"
            :disabled="savingImplementationChecklist || !canAddImplementationItem"
            @click="addImplementationChecklistItem"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            {{ savingImplementationChecklist ? 'Menyimpan...' : 'Tambah' }}
          </button>
        </div>

        <!-- List -->
        <div v-if="loadingImplementationChecklist" class="muted detail-loading">Memuat checklist...</div>
        <div v-else-if="implementationChecklistItems.length === 0" class="empty-state compact">
          <Icons name="inbox" :size="40" />
          <p class="empty-title">Belum ada item progress</p>
          <p class="empty-desc">{{ implementationChecklistEmptyText }}</p>
          <button type="button" class="btn btn-secondary" @click="focusImplementationTitle">Tambah item pertama</button>
        </div>
        <div v-else class="checklist-items-list">
          <div
            v-for="item in implementationChecklistItems"
            :key="item.id"
            class="checklist-item-row"
            :class="`checklist-item--${item.item_status}`"
          >
            <div class="checklist-item-indicator">
              <!-- done -->
              <svg v-if="item.item_status === 'done'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              <!-- in_progress -->
              <svg v-else-if="item.item_status === 'in_progress'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
              <!-- pending -->
              <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>
            </div>
            <div class="checklist-item-body">
              <span class="checklist-item-title" :class="{ 'done-text': item.item_status === 'done' }">{{ item.title }}</span>
              <span v-if="item.notes" class="checklist-item-notes">{{ item.notes }}</span>
            </div>
            <div class="checklist-item-action">
              <label class="checklist-toggle-compact">
                <input
                  type="checkbox"
                  :checked="item.item_status === 'done'"
                  :disabled="updatingImplementationChecklistId === item.id"
                  @change="updateImplementationChecklistItem(item, { item_status: $event.target.checked ? 'done' : 'pending' })"
                />
                <span>{{ item.item_status === 'done' ? 'Selesai' : 'Belum selesai' }}</span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- ===== TAB: KONFIGURASI (Tim Implementasi & DevOps) ===== -->
      <div v-if="!loading && (isImplementationRole || isDevOpsRole) && activeTab === 'konfigurasi'" class="card detail-tab-panel deployment-status-card">
        <div class="deployment-status-header">
          <h3>Konfigurasi Teknis</h3>
          <p class="muted">Pratinjau konfigurasi teknis aplikasi berdasarkan hasil analisa desain.</p>
        </div>

        <div class="technical-config-shell">
          <div class="technical-config-tabs" role="tablist" aria-label="Kategori konfigurasi teknis">
            <button
              v-for="tab in TECHNICAL_CONFIG_TABS"
              :key="tab.id"
              type="button"
              :class="['technical-config-tab', { active: activeTechnicalConfigTab === tab.id }]"
              role="tab"
              :aria-selected="activeTechnicalConfigTab === tab.id"
              @click="activeTechnicalConfigTab = tab.id"
            >
              <Icons :name="tab.icon" :size="15" />
              {{ tab.label }}
            </button>
          </div>

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
      <div v-if="!loading && isDevOpsRole && activeTab === 'deployment'" class="card detail-tab-panel deployment-status-card">
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
                <svg v-if="deploymentStatus.staging.deployed" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
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
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <div>
                  <div><strong>{{ deploymentStatus.staging.deployed_by?.name || 'DevOps' }}</strong></div>
                  <div class="muted deploy-date">{{ formatDateTime(deploymentStatus.staging.deployed_at) }}</div>
                </div>
              </div>
              <div v-else class="deploy-step-action">
                <button
                  class="btn btn-primary deploy-confirm-btn"
                  :disabled="savingDeploymentStatus || isSubmittingDeploy"
                  @click="openDeployModal('staging')"
                >
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                  Konfirmasi Deploy Staging
                </button>
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
                <svg v-if="deploymentStatus.production.deployed" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <svg v-else-if="!deploymentStatus.staging.deployed" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
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
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Dideploy oleh <strong>{{ deploymentStatus.production.deployed_by?.name || 'DevOps' }}</strong>
                pada {{ formatDateTime(deploymentStatus.production.deployed_at) }}
              </div>
              <div v-else-if="deploymentStatus.staging.deployed" class="deploy-step-action">
                <button
                  class="btn deploy-confirm-btn deploy-confirm-btn--production"
                  :disabled="savingDeploymentStatus || isSubmittingDeploy"
                  @click="openDeployModal('production')"
                >
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                  Deploy ke Production
                </button>
                <p class="deploy-production-warning">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                  Aksi ini akan mengubah status aplikasi dan bersifat final.
                </p>
              </div>
            </div>
          </div>

        </div>



        <!-- Deployment History -->
        <div v-if="deploymentHistory.length > 0" class="deploy-history-section">
          <h4 class="deploy-history-title">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
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
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="showDeployModal" class="modal-backdrop" @click.self="closeDeployModal">
        <div class="modal-card">
          <div class="modal-header">
            <div>
              <h3 class="modal-title">
                {{ deployModalEnv === 'production' ? 'Konfirmasi Deploy ke Production' : 'Konfirmasi Deploy ke Staging' }}
              </h3>
              <p class="modal-subtitle">
                {{ deployModalEnv === 'production'
                  ? 'Tindakan ini bersifat final dan akan mengubah status aplikasi menjadi Deployed Production. Pastikan semua requirements sudah terpenuhi.'
                  : 'Pastikan build sudah stabil dan semua konfigurasi staging sudah diverifikasi sebelum mengonfirmasi.' }}
              </p>
            </div>
            <button class="modal-close" @click="closeDeployModal">&times;</button>
          </div>
          <div class="modal-body">
            <!-- Environment Badge -->
            <div class="deploy-modal-env-badge" :class="deployModalEnv === 'production' ? 'env-production' : 'env-staging'">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
              Environment: <strong>{{ deployModalEnv === 'production' ? 'Production' : 'Staging' }}</strong>
            </div>

            <!-- Notes textarea -->
            <label class="modal-label modal-label-spaced">
              Catatan deployment
              <span class="modal-optional"> (opsional)</span>
            </label>
            <textarea
              v-model="deployModalNote"
              rows="3"
              maxlength="500"
              class="deploy-modal-textarea"
              :placeholder="deployModalEnv === 'production'
                ? 'Contoh: Semua service berjalan normal, monitoring aktif, rollback plan siap.'
                : 'Contoh: Build #47 sukses di-deploy, endpoint /api/health merespons 200.'" 
            ></textarea>
            <p class="modal-char-count">{{ deployModalNote.length }}/500</p>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="closeDeployModal" :disabled="isSubmittingDeploy">Batal</button>
            <button
              class="btn"
              :class="deployModalEnv === 'production' ? 'btn-success' : 'btn-primary'"
              @click="confirmDeploy"
              :disabled="isSubmittingDeploy"
            >
              <svg v-if="isSubmittingDeploy" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
              {{ isSubmittingDeploy ? 'Memproses...' : (deployModalEnv === 'production' ? 'Ya, Deploy ke Production' : 'Ya, Konfirmasi Staging') }}
            </button>
          </div>
        </div>
      </div>

      <!-- ===== TAB: DOKUMEN ===== -->
      <div v-if="!loading && isDocumentPanelMode && (props.unitKerjaMode || activeTab === 'dokumen')" class="card unit-doc-card" :class="{ 'detail-tab-panel': isNonUnitKerjaRole }">
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
              <a v-if="section.template" class="action-btn view-btn" :href="section.template" target="_blank" rel="noopener">
                <Icons name="download" :size="14" />
                {{ section.templateLabel }}
              </a>
              <a v-if="section.guidebook" class="action-btn" :href="section.guidebook" target="_blank" rel="noopener">
                <Icons name="file" :size="14" />
                {{ section.guidebookLabel }}
              </a>
            </div>
            <!-- Upload form: hanya untuk role+status yang bertanggung jawab -->
            <div v-if="docSectionCanUploadNow(section)" class="unit-doc-upload">
              <label class="unit-doc-picker">
                <input class="unit-doc-picker-input" type="file" accept=".pdf,.doc,.docx" @change="selectDocumentFile(section.type, $event)" />
                <span class="unit-doc-picker-button">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                  {{ selectedFiles[section.type] ? selectedFiles[section.type].name : 'Pilih File (PDF / DOC)' }}
                </span>
              </label>
              <button type="button" class="action-btn unit-doc-upload-btn unit-doc-upload-btn--full" :disabled="uploadingType === section.type || !selectedFiles[section.type]" @click="uploadDocument(section.type)">
                <Icons name="plus" :size="14" />
                {{ uploadingType === section.type ? 'Mengunggah...' : 'Unggah Dokumen' }}
              </button>
            </div>
            <div class="unit-doc-meta">
              <template v-if="getLatestDoc(section.type)">
                <div class="unit-doc-status-badge">
                  <Icons name="check" :size="14" />
                  <span>v{{ getLatestDoc(section.type).version }} - {{ getLatestDoc(section.type).original_filename }}</span>
                  <a v-if="getLatestDoc(section.type).file_url" :href="getLatestDoc(section.type).file_url" target="_blank" rel="noopener" class="unit-doc-download-link" title="Unduh dokumen">
                    <Icons name="download" :size="12" />
                  </a>
                </div>
              </template>
              <template v-else>
                <div class="unit-doc-empty">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                  Belum ada dokumen diunggah
                </div>
                <div v-if="getDocumentEmptyHint(section)" class="unit-doc-empty-hint">
                  {{ getDocumentEmptyHint(section) }}
                </div>
              </template>
            </div>
            <!-- Riwayat versi: tampil untuk semua role jika ada lebih dari 1 versi -->
            <div v-if="getDocHistory(section.type).length > 1" class="unit-doc-history">
              <h5>Riwayat versi dokumen</h5>
              <div v-for="doc in getDocHistory(section.type)" :key="doc.id" class="unit-doc-history-item">
                <div class="unit-doc-history-main">
                  <strong>v{{ doc.version }}</strong>
                  <span>{{ doc.original_filename }}</span>
                  <span class="unit-doc-history-size">{{ formatFileSize(doc.file_size) }}</span>
                  <a v-if="doc.file_url" class="unit-doc-history-link" :href="doc.file_url" target="_blank" rel="noopener">Unduh</a>
                </div>
                <div class="unit-doc-history-meta">
                  {{ formatDateTime(doc.created_at) }} - {{ doc.uploaded_by?.name || 'System' }} - {{ doc.status }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="!loading && props.securityMode && activeTab === 'hasil'" class="card detail-tab-panel security-review-card">
        <h4 class="section-title">Hasil Uji Keamanan</h4>
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
              <!-- Lolos -->
              <svg v-if="securityReview.security_test_passed === true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              <!-- Tidak Lolos -->
              <svg v-else-if="securityReview.security_test_passed === false" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
              <!-- Belum Diuji -->
              <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <div class="sec-status-text">
              <span class="sec-status-label">Status Pengujian</span>
              <span class="sec-status-value">{{ securityStatusText(securityReview.security_test_passed) }}</span>
            </div>
            <div v-if="securityReview.security_tester || securityReview.security_tested_at" class="sec-status-meta">
              <span v-if="securityReview.security_tester">oleh {{ securityReview.security_tester.name }}</span>
              <span v-if="securityReview.security_tested_at">{{ formatDateTime(securityReview.security_tested_at) }}</span>
            </div>
          </div>

          <!-- Form -->
          <div class="sec-form">
            <!-- Ringkasan -->
            <div class="sec-form-field">
              <label class="sec-form-label">Ringkasan hasil uji</label>
              <p class="sec-form-hint">Jelaskan cakupan pengujian, temuan utama, dan kondisi akhir aplikasi.</p>
              <textarea
                v-model="securityReviewForm.security_test_notes"
                class="sec-textarea"
                rows="4"
                maxlength="500"
                placeholder="Contoh: Pengujian mencakup autentikasi, otorisasi, dan validasi input. Ditemukan 2 celah XSS pada form pencarian yang telah terdokumentasi."
              ></textarea>
              <span class="sec-char-count">{{ (securityReviewForm.security_test_notes || '').length }} / 500</span>
            </div>

            <!-- Catatan perbaikan -->
            <div class="sec-form-field">
              <label class="sec-form-label">
                Catatan perbaikan
                <span class="sec-optional">opsional</span>
              </label>
              <p class="sec-form-hint">Tulis temuan spesifik yang perlu diperbaiki sebelum retesting.</p>
              <textarea
                v-model="securityReviewForm.note"
                class="sec-textarea"
                rows="3"
                maxlength="300"
                placeholder="Contoh: Perbaiki validasi input pada endpoint /api/login - rentan terhadap SQL injection."
              ></textarea>
            </div>
          </div>

          <!-- Error & Verdict Actions -->
          <div class="sec-actions">
            <div v-if="!securityReviewForm.security_test_notes?.trim()" class="sec-info-hint">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              Isi ringkasan hasil uji terlebih dahulu sebelum mengirim keputusan.
            </div>

            <!-- Tombol keputusan - hanya tampil saat status masih uji_keamanan -->
            <template v-if="app?.status === 'uji_keamanan'">
              <div class="sec-verdict-row">
                <button
                  class="btn sec-verdict-btn sec-verdict-btn--pass"
                  :disabled="savingSecurityReview || !securityReviewForm.security_test_notes?.trim()"
                  @click="submitSecurityVerdict(true)"
                >
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                  <span>
                    <strong>Lolos Uji Keamanan</strong>
                    <small>Aplikasi dinyatakan aman</small>
                  </span>
                </button>
                <button
                  class="btn sec-verdict-btn sec-verdict-btn--fail"
                  :disabled="savingSecurityReview || !securityReviewForm.security_test_notes?.trim()"
                  @click="submitSecurityVerdict(false)"
                >
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                  <span>
                    <strong>Belum Lolos</strong>
                    <small>Perlu perbaikan</small>
                  </span>
                </button>
              </div>
              <p class="sec-verdict-note">Keputusan ini akan mengubah status aplikasi dan tidak dapat dibatalkan dari halaman ini.</p>
            </template>

            <!-- Jika sudah diputuskan, tampilkan tombol simpan catatan saja -->
            <template v-else>
              <button
                class="btn btn-secondary sec-save-btn"
                :disabled="savingSecurityReview || !securityReviewForm.security_test_notes?.trim()"
                @click="saveSecurityReview"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                Simpan Catatan
              </button>
            </template>
          </div>

          <!-- History -->
          <div class="sec-history">
            <div class="sec-history-header">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              Riwayat Catatan
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
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
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

      <!-- ===== DEACTIVATE APPLICATION MODAL ===== -->
      <div v-if="showDeactivateModal" class="modal-backdrop" @click="closeDeactivateModal">
        <div class="modal-card" @click.stop>
          <div class="modal-header">
            <div>
              <h3>Nonaktifkan Aplikasi</h3>
              <p class="modal-subtitle">Aksi ini hanya untuk aplikasi yang sudah production.</p>
            </div>
            <button class="modal-close" :disabled="isSubmittingDeactivate" @click="closeDeactivateModal">&times;</button>
          </div>
          <div class="modal-body">
            <p class="action-modal-copy">
              Aplikasi <strong>{{ app?.nama_aplikasi }}</strong> akan ditandai nonaktif dan tetap tercatat dalam sistem.
            </p>
            <div class="form-group">
              <label class="action-modal-label">Catatan <span class="muted">(opsional)</span></label>
              <textarea
                v-model="deactivateNote"
                class="form-control action-modal-textarea"
                rows="4"
                maxlength="240"
                placeholder="Contoh: Aplikasi sudah tidak digunakan oleh unit kerja."
              ></textarea>
            </div>
          </div>
          <div class="modal-footer action-modal-footer">
            <button class="btn btn-secondary" :disabled="isSubmittingDeactivate" @click="closeDeactivateModal">Batal</button>
            <button class="btn btn-danger" :disabled="isSubmittingDeactivate" @click="submitDeactivateApp">
              <span v-if="isSubmittingDeactivate" class="spinner-small spinner-inline"></span>
              {{ isSubmittingDeactivate ? 'Menonaktifkan...' : 'Nonaktifkan' }}
            </button>
          </div>
        </div>
      </div>
      <!-- ======================================= -->

      <!-- ===== WORKFLOW ACTION MODAL ===== -->
      <div v-if="showActionModal" class="modal-backdrop" @click="closeActionModal">
        <div class="modal-card" @click.stop>
          <div class="modal-header">
            <h3>{{ selectedAction?.label }}</h3>
            <button class="modal-close" @click="closeActionModal">&times;</button>
          </div>
          <div class="modal-body">
            <p class="action-modal-copy">
              Anda yakin ingin melanjutkan proses <strong>{{ selectedAction?.label }}</strong> untuk aplikasi <strong>{{ app?.nama_aplikasi }}</strong>?
            </p>
            <div v-if="selectedAction?.requiresNote" class="form-group">
              <label class="action-modal-label">
                {{ selectedAction.noteLabel || 'Catatan Tambahan' }} <span class="text-danger">*</span>
              </label>
              <textarea 
                v-model="actionCatatan" 
                class="form-control action-modal-textarea" 
                rows="4" 
                placeholder="Masukkan catatan yang relevan untuk aksi ini..."
              ></textarea>
            </div>
          </div>
          <div class="modal-footer action-modal-footer">
            <button class="btn btn-secondary" @click="closeActionModal" :disabled="isSubmittingAction">Batal</button>
            <button class="btn" :class="selectedAction?.btnClass || 'btn-primary'" @click="submitAction" :disabled="isSubmittingAction">
              <span v-if="isSubmittingAction" class="spinner-small spinner-inline"></span>
              {{ isSubmittingAction ? 'Memproses...' : 'Konfirmasi' }}
            </button>
          </div>
        </div>
      </div>
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
  border-radius: 14px;
  border: 1px solid var(--notion-border);
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
  font-size: 18px;
  color: #1f2937;
}
.modal-close {
  background: transparent;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #6b7280;
  flex-shrink: 0;
  line-height: 1;
  padding: 0;
}
.modal-close:hover {
  color: #111827;
}
.modal-title {
  margin: 0 0 4px;
  font-size: 16px;
  font-weight: 700;
  color: #1e293b;
}
.modal-subtitle {
  margin: 0;
  font-size: 13px;
  color: #64748b;
  line-height: 1.5;
}
.modal-body {
  margin-bottom: 20px;
}
.modal-label {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
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
  border-top-color: var(--notion-blue);
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
  color: var(--notion-text-secondary);
}

.empty-state svg {
  opacity: 0.3;
  margin-bottom: 16px;
}

.empty-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--notion-text);
  margin: 0 0 8px 0;
}

.empty-desc {
  font-size: 13px;
  color: var(--notion-text-secondary);
  margin: 0;
  max-width: 320px;
}

.field-hint {
  display: inline-block;
  margin-top: 6px;
  font-size: 12px;
  color: var(--notion-text-secondary);
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
  background: var(--notion-bg);
  border-radius: 6px;
  border: 1px solid var(--notion-border);
}

.url-item label {
  font-size: 13px;
  color: var(--notion-text-secondary);
  font-weight: 500;
}

.url-value {
  font-size: 14px;
  color: var(--notion-text);
  font-weight: 600;
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
  background: var(--notion-bg);
  border-radius: 6px;
  border: 1px solid var(--notion-border);
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
  font-size: 13px;
  color: var(--notion-text-secondary);
  font-weight: 500;
}

.db-label {
  font-size: 13px;
  color: var(--notion-text-secondary);
  font-weight: 500;
}

.db-value {
  font-size: 14px;
  color: var(--notion-text);
  font-weight: 600;
}

.unit-doc-card {
  margin-bottom: 16px;
}



.unit-doc-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
}

.unit-doc-item {
  border: 1px solid var(--notion-border);
  border-radius: 10px;
  padding: 14px;
  background: var(--notion-bg);
}

.unit-doc-item h4 {
  margin: 0 0 10px;
  font-size: 14px;
}

.unit-doc-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}

.unit-doc-upload {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
}

.unit-doc-picker {
  flex: 0 0 auto;
  min-width: 0;
  display: inline-flex;
  align-items: center;
  gap: 0;
  padding: 0;
  border: 0;
  background: transparent;
  min-height: 0;
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
  flex: 0 0 auto;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 6px 10px;
  border-radius: 6px;
  border: 1px solid var(--notion-border);
  background: var(--notion-muted-surface);
  color: var(--notion-text);
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
  transition: box-shadow 0.15s ease;
}

.unit-doc-picker:hover .unit-doc-picker-button {
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}

.unit-doc-upload button {
  flex-shrink: 0;
  white-space: nowrap;
  margin-right: 0;
}

.unit-doc-upload-btn {
  background: #263053;
  color: #ffffff;
}

.unit-doc-upload-btn:hover {
  background: #39456f;
}

.unit-doc-meta {
  margin-top: 10px;
}

.unit-doc-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  background: var(--notion-green-bg);
  border: 1px solid var(--notion-green-border);
  border-radius: 6px;
  font-size: 12px;
  color: var(--notion-green);
  font-weight: 500;
  max-width: 100%;
}

.unit-doc-status-badge span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.unit-doc-download-link {
  flex-shrink: 0;
  margin-left: 4px;
  color: var(--notion-green);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  cursor: pointer;
}

.unit-doc-download-link:hover {
  opacity: 0.7;
}

.unit-doc-empty {
  font-size: 12px;
  color: var(--notion-text-secondary);
}

.unit-doc-empty-hint {
  margin-top: 6px;
  font-size: 12px;
  color: var(--notion-text-tertiary);
}

.unit-doc-download-row {
  margin-top: 8px;
}

.unit-doc-history {
  margin-top: 10px;
  border-top: 1px dashed var(--notion-border);
  padding-top: 10px;
}

.unit-doc-history h5 {
  margin: 0 0 8px;
  font-size: 12px;
  color: var(--notion-text-secondary);
}

.unit-doc-history-item {
  border: 1px solid var(--notion-border);
  border-radius: 6px;
  padding: 8px;
  margin-bottom: 8px;
}

.unit-doc-history-main {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  font-size: 12px;
}

.unit-doc-history-size {
  color: var(--notion-text-secondary);
}

.unit-doc-history-link {
  margin-left: auto;
  color: var(--notion-blue);
  text-decoration: none;
  font-weight: 600;
}

.unit-doc-history-link:hover {
  text-decoration: underline;
}

.unit-doc-history-meta {
  margin-top: 4px;
  font-size: 11px;
  color: var(--notion-text-secondary);
}

.analyst-focus-note {
  margin-top: 10px;
  padding: 10px 12px;
  border: 1px solid var(--notion-border);
  border-radius: 8px;
  font-size: 13px;
  color: var(--notion-text-secondary);
  background: var(--notion-bg);
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
  border: 1px solid var(--notion-border);
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 12px;
  background: var(--notion-bg);
}

.summary-pill.success {
  background: var(--notion-green-bg);
  border-color: var(--notion-green-border);
}

.summary-pill.warning {
  background: var(--notion-amber-bg);
  border-color: var(--notion-amber-border);
}

.implementation-header-side {
  border: 1px dashed var(--notion-border);
  border-radius: 8px;
  padding: 12px;
}

.implementation-side-label {
  margin: 0 0 4px;
  font-size: 12px;
  color: var(--notion-text-secondary);
}

.implementation-side-file {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
}

.implementation-side-meta {
  margin: 4px 0 10px;
  font-size: 12px;
  color: var(--notion-text-secondary);
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
  border: 1px solid var(--notion-border);
  border-radius: 6px;
  background: var(--notion-bg);
}

.implementation-add-row .input-invalid {
  border-color: var(--notion-red);
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
}

.form-hint {
  margin: 0 0 10px;
  font-size: 12px;
}

.form-hint.error {
  color: var(--notion-red);
}

.implementation-checklist-card select {
  width: 100%;
  padding: 8px 10px;
  border: 1px solid var(--notion-border);
  border-radius: 6px;
  background: var(--notion-bg);
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
  font-size: 11px;
  color: var(--notion-text-secondary);
}

.muted {
  color: var(--notion-text-secondary);
  font-size: 13px;
}

.detail-loading {
  padding: 16px 0;
}

.deploy-date {
  font-size: 12px;
}

.modal-label-spaced {
  display: block;
  margin-top: 16px;
}

.modal-optional {
  color: #94a3b8;
  font-weight: 400;
}

.action-modal-copy {
  margin: 0 0 12px;
  font-size: 14px;
  color: #4b5563;
  line-height: 1.55;
}

.action-modal-label {
  display: block;
  margin-bottom: 6px;
  color: #374151;
  font-size: 13px;
  font-weight: 600;
}

.action-modal-textarea {
  width: 100%;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 10px 12px;
  font-size: 14px;
  line-height: 1.5;
  resize: vertical;
}

.action-modal-textarea:focus {
  outline: none;
  border-color: var(--notion-blue);
  box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.08);
}

.action-modal-footer {
  margin-top: 20px;
  gap: 12px;
}

.text-danger {
  color: var(--notion-red);
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
  font-size: 12px;
  color: var(--notion-text-secondary);
  background: var(--notion-muted-surface);
  border: 1px solid var(--notion-border);
  border-radius: 999px;
  padding: 6px 10px;
  display: inline-flex;
  align-items: center;
}

.unit-doc-stage-lock {
  margin-top: 8px;
  font-size: 12px;
  color: var(--notion-text-tertiary);
  background: var(--notion-muted-surface);
  border: 1px dashed var(--notion-border);
  border-radius: 999px;
  padding: 6px 10px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-style: italic;
}

.security-review-card {
  margin-bottom: 16px;
}

.security-subtitle {
  font-size: 13.5px;
  color: var(--notion-text-secondary);
  margin: -10px 0 20px;
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
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #94a3b8;
}
.sec-status-value {
  font-size: 15px;
  font-weight: 700;
  color: #1e293b;
}
.sec-status--pass .sec-status-value { color: #15803d; }
.sec-status--fail .sec-status-value { color: #b91c1c; }
.sec-status-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 12px;
  color: #64748b;
  text-align: right;
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
  font-size: 13.5px;
  font-weight: 600;
  color: #374151;
}
.sec-required { color: #ef4444; font-size: 13px; }
.sec-optional {
  font-size: 11px;
  font-weight: 500;
  color: #94a3b8;
  background: #f1f5f9;
  border-radius: 4px;
  padding: 1px 6px;
  text-transform: lowercase;
}
.sec-form-hint {
  font-size: 12px;
  color: #94a3b8;
  margin: 0;
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
  font-size: 14px;
  color: #1e293b;
  cursor: pointer;
  transition: border-color 0.15s;
}
.sec-select:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.12); }
.sec-select--pass { border-color: #86efac; background: #f0fdf4; color: #15803d; font-weight: 600; }
.sec-select--fail { border-color: #fca5a5; background: #fff1f2; color: #b91c1c; font-weight: 600; }
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
  font-size: 13.5px;
  color: #1e293b;
  resize: vertical;
  transition: border-color 0.15s;
  font-family: inherit;
  line-height: 1.6;
}
.sec-textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.12); }
.sec-textarea::placeholder { color: #cbd5e1; font-size: 13px; }
.sec-char-count {
  font-size: 11.5px;
  color: #94a3b8;
  text-align: right;
  margin-top: 2px;
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
  font-size: 13px;
  color: #b91c1c;
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
  font-size: 13px;
  color: #1d4ed8;
  margin-bottom: 4px;
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
  font-size: 14px;
  text-align: left;
  transition: all 0.15s ease;
}
.sec-verdict-btn span {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.sec-verdict-btn strong {
  font-size: 14px;
  font-weight: 700;
  line-height: 1.2;
}
.sec-verdict-btn small {
  font-size: 12px;
  font-weight: 400;
  opacity: 0.85;
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
  font-size: 11.5px;
  color: #94a3b8;
  margin: 8px 0 0;
  font-style: italic;
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
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: #94a3b8;
  margin-bottom: 14px;
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
  font-size: 12.5px;
  color: #64748b;
}
.sec-history-meta strong { color: #1e293b; font-size: 13px; }
.sec-history-body {
  margin: 0;
  font-size: 13.5px;
  color: #374151;
  line-height: 1.6;
  white-space: pre-wrap;
}
.sec-history-empty {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 16px;
  background: #f8fafc;
  border-radius: 8px;
  font-size: 13px;
  color: #94a3b8;
}
.sec-history-empty svg { color: #cbd5e1; }

@media (max-width: 900px) {
  .unit-doc-grid {
    grid-template-columns: 1fr;
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

@media (max-width: 1200px) {
  .unit-doc-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

/* ===== STEPPER / TIMELINE PROGRESS ===== */
.uk-stepper-wrap {
  margin: 20px 20px 0;
  background: var(--notion-bg);
  border: 1px solid rgba(228, 224, 213, 0.8);
  border-radius: var(--notion-radius-lg);
  box-shadow: var(--notion-shadow-card);
  padding: 20px 24px 24px;
  overflow: hidden;
}

.uk-stepper-header {
  margin-bottom: 24px;
}

.unit-doc-upload {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 10px;
}

.unit-doc-picker {
  display: block;
  cursor: pointer;
}

.unit-doc-picker-input {
  display: none;
}

.unit-doc-picker-button {
  display: flex;
  align-items: center;
  gap: 6px;
  width: 100%;
  padding: 8px 12px;
  border: 1.5px dashed var(--notion-border);
  border-radius: 8px;
  font-size: 13px;
  color: var(--notion-text-secondary);
  background: var(--notion-muted-surface);
  transition: border-color 0.15s, background 0.15s;
  box-sizing: border-box;
  cursor: pointer;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.unit-doc-picker:hover .unit-doc-picker-button {
  border-color: var(--notion-blue);
  background: #eef2fb;
  color: var(--notion-blue);
}

.unit-doc-upload-btn--full {
  width: 100%;
  justify-content: center;
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
  font-size: 14px;
  font-weight: 600;
  color: #4b5563;
  margin-bottom: 2px;
}

.unit-doc-stage-lock-desc {
  font-size: 13px;
  color: #6b7280;
  line-height: 1.4;
}

/* Konteks Box (Next Action) */
.uk-context-box {
  display: flex;
  gap: 16px;
  padding: 20px;
  border-radius: 12px;
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
  font-size: 16px;
  font-weight: 700;
  line-height: 1.2;
}

.uk-context-desc {
  margin: 0;
  font-size: 14px;
  line-height: 1.5;
}

/* Empty doc state */
.unit-doc-empty {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 8px;
  font-size: 13px;
  color: var(--notion-text-tertiary);
}

.uk-stepper-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.uk-stepper-title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--notion-text);
  letter-spacing: -0.01em;
}

.uk-stepper-special-badge {
  font-size: 12px;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 999px;
  background: var(--notion-red-bg);
  color: var(--notion-red);
}

.uk-stepper-subtitle {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--notion-text-secondary);
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
  background: var(--notion-border);
  z-index: 0;
  border-radius: 3px;
}

.uk-step-connector-fill {
  height: 100%;
  width: 0%;
  background: linear-gradient(90deg, #1e3a8a, #3b5bdb);
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
  font-size: 13px;
  font-weight: 700;
  transition: all 0.3s ease;
  border: 2px solid var(--notion-border);
  background: var(--notion-bg);
  color: var(--notion-text-secondary);
}

/* Done state */
.uk-step--done .uk-step-icon {
  background: linear-gradient(135deg, #1e3a8a, #3b5bdb);
  border-color: #1e3a8a;
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
  font-size: 13px;
  color: var(--notion-text-tertiary);
}

/* Body */
.uk-step-body {
  text-align: center;
  padding: 0 4px;
}

.uk-step-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--notion-text-secondary);
  line-height: 1.3;
  margin-bottom: 4px;
  transition: color 0.2s;
}

.uk-step--done .uk-step-label {
  color: #1e3a8a;
}

.uk-step--active .uk-step-label {
  color: var(--notion-text);
  font-weight: 800;
}

.uk-step-desc {
  font-size: 11px;
  color: var(--notion-text-tertiary);
  line-height: 1.4;
  max-width: 120px;
  margin: 0 auto;
}

.uk-step--active .uk-step-desc {
  color: var(--notion-text-secondary);
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
  background: linear-gradient(135deg, #1e3a8a 0%, #2c4fa8 100%);
  border-radius: 14px;
  padding: 20px 24px;
  margin: 0 20px !important;
  box-shadow: 0 4px 14px rgba(30, 58, 138, 0.18);
}

.actions-header-inline {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 20px;
  flex-wrap: wrap;
}

.actions-card {
  margin: 0 20px !important;
  padding: 16px 20px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
  border-color: rgba(228, 224, 213, 1);
}

.detail-tabs-card {
  margin: 14px 20px 0 !important;
  padding: 18px 24px 0;
  border-bottom: 0;
  border-bottom-left-radius: 0;
  border-bottom-right-radius: 0;
  position: relative;
  z-index: 1;
}

.detail-tabs {
  border-bottom: 1px solid var(--notion-border);
  display: flex;
  gap: 6px;
  margin: 0;
  padding: 0 0 14px;
  overflow-x: auto;
  scrollbar-width: thin;
}

.detail-tabs button {
  min-height: 38px;
  padding: 8px 14px;
  border-radius: 8px;
  white-space: nowrap;
  font-size: 14px;
  font-weight: 600;
}

.detail-tab-panel {
  margin: 0 20px 20px !important;
  border-top: 0;
  border-top-left-radius: 0;
  border-top-right-radius: 0;
  padding: 24px;
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
  border-top: 1px solid var(--notion-border);
}

.detail-danger-copy {
  min-width: 0;
}

.detail-danger-copy h5 {
  margin: 0 0 4px;
  font-size: 14px;
  font-weight: 800;
  color: #991b1b;
}

.detail-danger-copy p {
  margin: 0;
  max-width: 560px;
  color: var(--notion-text-secondary);
  font-size: 13px;
  line-height: 1.5;
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
  font-family: var(--notion-font-brand);
  font-size: 17px !important;
  font-weight: 800 !important;
  line-height: 1.35 !important;
  letter-spacing: 0 !important;
}

.detail-tab-panel .checklist-title svg {
  color: var(--notion-blue);
}

.detail-tab-panel .checklist-add-form {
  background: var(--notion-bg-secondary);
  border-radius: 10px;
  box-shadow: none;
}

.detail-tab-panel .checklist-items-list {
  border-top: 1px solid var(--notion-border);
}

.detail-tab-panel .checklist-item-row {
  display: grid;
  grid-template-columns: 30px minmax(0, 1fr) auto;
  align-items: center;
  gap: 14px;
  padding: 14px 0;
}

.detail-tab-panel .checklist-item-row:hover {
  background: transparent;
}

.detail-tab-panel .checklist-item-title {
  white-space: normal;
  line-height: 1.45;
}

.detail-tab-panel .checklist-note-input-inline,
.detail-tab-panel .checklist-status-select {
  min-height: 36px;
  border-radius: 8px;
  font-size: 13px;
}

.detail-tab-panel .empty-message svg {
  color: var(--notion-text-tertiary);
  opacity: 0.55;
  margin-bottom: 10px;
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
  border: 1px solid var(--notion-border);
  border-radius: 10px;
  background: var(--notion-bg-secondary);
}

.technical-config-tab {
  min-height: 38px;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 8px 12px;
  border: 1px solid var(--notion-border);
  border-radius: 8px;
  background: #fff;
  color: var(--notion-text-secondary);
  font-size: 13.5px;
  font-weight: 700;
  cursor: pointer;
  transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease;
}

.technical-config-tab:hover {
  border-color: rgba(31, 63, 147, 0.35);
  color: var(--notion-blue);
  background: var(--notion-blue-bg);
}

.technical-config-tab.active {
  background: var(--notion-blue);
  border-color: var(--notion-blue);
  color: #fff;
}

.technical-config-content {
  border: 1px solid var(--notion-border);
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
  border-bottom: 1px solid var(--notion-border);
  background: var(--notion-bg-secondary);
}

.technical-config-content-head h4 {
  margin: 0;
  color: var(--ui-text);
  font-family: var(--notion-font-brand);
  font-size: 16px;
  font-weight: 800;
  letter-spacing: 0;
}

.technical-config-count {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border: 1px solid var(--notion-border);
  border-radius: 999px;
  background: #fff;
  color: var(--notion-text-secondary);
  font-size: 12px;
  font-weight: 700;
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
  color: var(--notion-text-secondary);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.technical-config-items {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 12px;
}

.technical-config-item {
  border: 1px solid var(--notion-border);
  border-radius: 10px;
  background: var(--notion-bg-secondary);
  overflow: hidden;
}

.technical-config-item-title {
  padding: 12px 14px;
  border-bottom: 1px solid var(--notion-border);
  color: var(--notion-text);
  font-size: 14px;
  font-weight: 800;
  word-break: break-word;
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
  color: var(--notion-text-secondary);
  font-size: 12.5px;
  font-weight: 700;
}

.technical-config-row strong {
  color: var(--notion-text);
  font-size: 13px;
  font-weight: 650;
  text-align: right;
  word-break: break-word;
}

.technical-config-empty {
  min-height: 112px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: 1px dashed var(--notion-border);
  border-radius: 10px;
  background: var(--notion-bg-secondary);
  color: var(--notion-text-secondary);
  font-size: 13.5px;
  text-align: center;
}

.technical-config-empty svg {
  color: var(--notion-text-tertiary);
  opacity: 0.7;
}

@media (max-width: 768px) {
  .sticky-header-container {
    position: static;
    gap: 12px;
    margin-bottom: 0;
    padding-bottom: 14px;
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
  font-size: 12px;
}

.dh-bc-link {
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

.dh-bc-link:hover {
  color: rgba(255, 255, 255, 0.9);
}

.dh-bc-sep {
  color: rgba(255, 255, 255, 0.35);
}

.dh-bc-current {
  color: rgba(255, 255, 255, 0.8);
  font-weight: 500;
}

.detail-hero-title {
  margin: 0;
  font-size: 22px;
  font-weight: 700;
  color: #fff;
  letter-spacing: -0.01em;
}

.detail-hero-sub {
  margin: 4px 0 0;
  font-size: 13px;
  color: rgba(255, 255, 255, 0.6);
}

.detail-hero-refresh {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 500;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.3);
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
  cursor: pointer;
  transition: all 0.15s;
  flex-shrink: 0;
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
  font-size: 14px;
  font-weight: 700;
  background: #e2e8f0;
  color: #64748b;
  border: 2px solid #cbd5e1;
  flex-shrink: 0;
  transition: all 0.2s ease;
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
  font-size: 15px;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 4px;
}
.deploy-step--locked .deploy-step-title {
  color: #94a3b8;
}
.deploy-step-desc {
  font-size: 13px;
  color: #64748b;
  margin: 0;
  line-height: 1.5;
}
.deploy-env-badge {
  font-size: 12px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 20px;
  white-space: nowrap;
  flex-shrink: 0;
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
  font-size: 13px;
  color: #4b5563;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  padding: 8px 12px;
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
  font-size: 13.5px;
  font-weight: 600;
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
  font-size: 11.5px;
  color: #d97706;
  margin: 0;
  font-style: italic;
}
.deploy-production-warning svg { flex-shrink: 0; }

/* Deploy Modal env badge */
.deploy-modal-env-badge {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 6px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
}
.deploy-modal-env-badge.env-staging {
  background: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #bfdbfe;
}
.deploy-modal-env-badge.env-production {
  background: #f0fdf4;
  color: #15803d;
  border: 1px solid #bbf7d0;
}
.deploy-modal-textarea {
  width: 100%;
  margin-top: 8px;
  padding: 12px;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  font-size: 13px;
  font-family: inherit;
  resize: vertical;
  color: #1e293b;
  transition: border-color 0.15s;
  box-sizing: border-box;
}
.deploy-modal-textarea:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}


@media (max-width: 768px) {
  .detail-hero-card {
    flex-direction: column;
    align-items: flex-start;
    margin: 0 12px 16px;
    padding: 18px 20px;
  }

  .detail-hero-title {
    font-size: 18px;
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
  font-size: 18px;
  color: #1e3a8a;
}
</style>


