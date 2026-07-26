<script setup>
import { Avatar, Button, TextArea } from '@idds/vue'
import {
  IconArrowBackUp,
  IconEdit,
  IconMessageCircle,
  IconSend,
  IconTrash,
} from '@tabler/icons-vue'
import { computed, ref } from 'vue'
import { ROLE_DISPLAY_NAME } from '../constants/roles'
import http from '../lib/http'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import ConfirmationDrawer from './ConfirmationDrawer.vue'
import Icons from './Icons.vue'

const props = defineProps({
  appId: { type: [String, Number], required: true },
  notes: { type: Array, default: () => [] },
  histories: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const emit = defineEmits(['refresh'])
const auth = useAuthStore()
const toast = useToastStore()

const body = ref('')
const replyTarget = ref(null)
const replyBody = ref('')
const editTarget = ref(null)
const editBody = ref('')
const deleteTarget = ref(null)
const saving = ref(false)
const deleting = ref(false)
const historyOpen = ref(false)

const hasNotes = computed(() => props.notes.length > 0)

function noteTypeForRole() {
  if (auth.role === 'tim_uji_keamanan') return 'uji_keamanan'
  if (['tim_implementasi_aplikasi', 'devops_developer'].includes(auth.role || '')) return 'perbaikan'
  return 'info'
}

function initials(name) {
  return String(name || 'Sistem')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('') || 'S'
}

function roleLabel(role) {
  return ROLE_DISPLAY_NAME[role] || 'Pengguna'
}

function formatDateTime(value) {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '-'
  return new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}

function canManage(note) {
  return Number(note?.created_by) === Number(auth.user?.id)
}

function cancelInlineForms() {
  replyTarget.value = null
  replyBody.value = ''
  editTarget.value = null
  editBody.value = ''
}

function beginReply(note) {
  editTarget.value = null
  editBody.value = ''
  replyTarget.value = note
  replyBody.value = ''
}

function beginEdit(note) {
  replyTarget.value = null
  replyBody.value = ''
  editTarget.value = note
  editBody.value = note.body || ''
}

async function submitNote(parentId = null) {
  const value = (parentId ? replyBody.value : body.value).trim()
  if (!value || saving.value) return

  saving.value = true
  try {
    await http.post(`/aplikasi/${props.appId}/notes`, {
      body: value,
      note_type: noteTypeForRole(),
      parent_id: parentId,
    })
    if (parentId) {
      replyTarget.value = null
      replyBody.value = ''
      toast.push('Balasan berhasil dikirim.', 'success')
    } else {
      body.value = ''
      toast.push('Komentar berhasil dikirim.', 'success')
    }
    emit('refresh')
  } catch (error) {
    toast.push(error?.response?.data?.message || 'Komentar gagal dikirim.', 'error')
  } finally {
    saving.value = false
  }
}

async function submitEdit() {
  const value = editBody.value.trim()
  if (!editTarget.value || !value || saving.value) return

  saving.value = true
  try {
    await http.patch(`/aplikasi/${props.appId}/notes/${editTarget.value.id}`, { body: value })
    editTarget.value = null
    editBody.value = ''
    toast.push('Komentar berhasil diperbarui.', 'success')
    emit('refresh')
  } catch (error) {
    toast.push(error?.response?.data?.message || 'Komentar gagal diperbarui.', 'error')
  } finally {
    saving.value = false
  }
}

async function confirmDelete() {
  if (!deleteTarget.value || deleting.value) return
  deleting.value = true
  try {
    await http.delete(`/aplikasi/${props.appId}/notes/${deleteTarget.value.id}`)
    deleteTarget.value = null
    toast.push('Komentar berhasil dihapus.', 'success')
    emit('refresh')
  } catch (error) {
    toast.push(error?.response?.data?.message || 'Komentar gagal dihapus.', 'error')
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <section class="discussion" aria-labelledby="discussion-title">
    <div class="discussion-heading">
      <div>
        <h4 id="discussion-title"><IconMessageCircle :size="22" /> Diskusi ({{ notes.length }})</h4>
        <p>Komunikasi antarrole terkait proses dan tindak lanjut aplikasi.</p>
      </div>
    </div>

    <div class="discussion-composer">
      <TextArea
        id="discussion-new-comment"
        v-model="body"
        label="Tulis komentar"
        placeholder="Tulis pertanyaan, hasil review, atau informasi tindak lanjut"
        :max-length="500"
        rows="3"
      />
      <div class="discussion-composer-actions">
        <Button
          hierarchy="primary"
          size="md"
          :suffix-icon="IconSend"
          :disabled="saving || !body.trim()"
          @click="submitNote()"
        >
          {{ saving ? 'Mengirim...' : 'Kirim' }}
        </Button>
      </div>
    </div>

    <div v-if="loading" class="discussion-state">Memuat diskusi...</div>
    <div v-else-if="error" class="discussion-state error">{{ error }}</div>
    <div v-else-if="!hasNotes" class="discussion-empty">
      <Icons name="message" :size="48" />
      <strong>Belum ada diskusi</strong>
      <span>Komentar pertama akan menjadi awal percakapan lintas tim.</span>
    </div>

    <div v-else class="discussion-list">
      <article v-for="note in notes" :key="note.id" class="discussion-thread">
        <div class="discussion-comment">
          <Avatar :initials="initials(note.creator?.name)" :alt="note.creator?.name || 'Sistem'" :size="40" />
          <div class="discussion-comment-body">
            <header class="discussion-comment-header">
              <div class="discussion-author">
                <strong>{{ note.creator?.name || 'Sistem' }}</strong>
                <span>{{ roleLabel(note.creator?.role) }}</span>
                <time :datetime="note.created_at">{{ formatDateTime(note.created_at) }}</time>
                <small v-if="note.edited_at">Diedit</small>
              </div>
              <div class="discussion-actions">
                <button type="button" title="Balas komentar" aria-label="Balas komentar" @click="beginReply(note)">
                  <IconArrowBackUp :size="18" />
                </button>
                <button v-if="canManage(note)" type="button" title="Edit komentar" aria-label="Edit komentar" @click="beginEdit(note)">
                  <IconEdit :size="18" />
                </button>
                <button v-if="canManage(note)" type="button" class="danger" title="Hapus komentar" aria-label="Hapus komentar" @click="deleteTarget = note">
                  <IconTrash :size="18" />
                </button>
              </div>
            </header>

            <template v-if="editTarget?.id === note.id">
              <TextArea
                :id="`discussion-edit-${note.id}`"
                v-model="editBody"
                label="Edit komentar"
                :max-length="500"
                rows="3"
              />
              <div class="discussion-inline-actions">
                <Button hierarchy="secondary" size="sm" @click="cancelInlineForms">Batal</Button>
                <Button hierarchy="primary" size="sm" :disabled="saving || !editBody.trim()" @click="submitEdit">Simpan</Button>
              </div>
            </template>
            <p v-else class="discussion-text">{{ note.body }}</p>

            <div v-if="replyTarget?.id === note.id" class="discussion-reply-form">
              <TextArea
                :id="`discussion-reply-${note.id}`"
                v-model="replyBody"
                :label="`Balas ${note.creator?.name || 'komentar'}`"
                placeholder="Tulis balasan"
                :max-length="500"
                rows="2"
              />
              <div class="discussion-inline-actions">
                <Button hierarchy="secondary" size="sm" @click="cancelInlineForms">Batal</Button>
                <Button hierarchy="primary" size="sm" :suffix-icon="IconSend" :disabled="saving || !replyBody.trim()" @click="submitNote(note.id)">Balas</Button>
              </div>
            </div>
          </div>
        </div>

        <div v-if="note.replies?.length" class="discussion-replies">
          <article v-for="reply in note.replies" :key="reply.id" class="discussion-comment reply">
            <Avatar :initials="initials(reply.creator?.name)" :alt="reply.creator?.name || 'Sistem'" :size="32" />
            <div class="discussion-comment-body">
              <header class="discussion-comment-header">
                <div class="discussion-author">
                  <strong>{{ reply.creator?.name || 'Sistem' }}</strong>
                  <span>{{ roleLabel(reply.creator?.role) }}</span>
                  <time :datetime="reply.created_at">{{ formatDateTime(reply.created_at) }}</time>
                  <small v-if="reply.edited_at">Diedit</small>
                </div>
                <div class="discussion-actions">
                  <button type="button" title="Balas komentar" aria-label="Balas komentar" @click="beginReply(note)"><IconArrowBackUp :size="18" /></button>
                  <button v-if="canManage(reply)" type="button" title="Edit komentar" aria-label="Edit komentar" @click="beginEdit(reply)"><IconEdit :size="18" /></button>
                  <button v-if="canManage(reply)" type="button" class="danger" title="Hapus komentar" aria-label="Hapus komentar" @click="deleteTarget = reply"><IconTrash :size="18" /></button>
                </div>
              </header>
              <template v-if="editTarget?.id === reply.id">
                <TextArea
                  :id="`discussion-edit-reply-${reply.id}`"
                  v-model="editBody"
                  label="Edit balasan"
                  :max-length="500"
                  rows="2"
                />
                <div class="discussion-inline-actions">
                  <Button hierarchy="secondary" size="sm" @click="cancelInlineForms">Batal</Button>
                  <Button hierarchy="primary" size="sm" :disabled="saving || !editBody.trim()" @click="submitEdit">Simpan</Button>
                </div>
              </template>
              <p v-else class="discussion-text">{{ reply.body }}</p>
            </div>
          </article>
        </div>
      </article>
    </div>

    <div v-if="histories.length" class="discussion-history">
      <button type="button" class="discussion-history-trigger" :aria-expanded="historyOpen" @click="historyOpen = !historyOpen">
        <span>Riwayat keputusan ({{ histories.length }})</span>
        <Icons :name="historyOpen ? 'chevron-up' : 'chevron-down'" :size="18" />
      </button>
      <ol v-if="historyOpen" class="discussion-history-list">
        <li v-for="history in histories" :key="history.id">
          <span class="discussion-history-dot" />
          <div>
            <strong>{{ history.aksi || 'Perubahan status' }}</strong>
            <p v-if="history.catatan">{{ history.catatan }}</p>
            <small>{{ history.changer?.name || 'Sistem' }} · {{ formatDateTime(history.created_at) }}</small>
          </div>
        </li>
      </ol>
    </div>
  </section>

  <ConfirmationDrawer
    :model-value="Boolean(deleteTarget)"
    title="Hapus komentar"
    description="Komentar dan seluruh balasan di bawahnya akan dihapus permanen."
    :subject="deleteTarget?.body || 'Komentar'"
    confirm-label="Hapus komentar"
    :loading="deleting"
    @update:model-value="!$event && (deleteTarget = null)"
    @cancel="deleteTarget = null"
    @confirm="confirmDelete"
  />
</template>

<style scoped>
.discussion {
  display: grid;
  gap: 24px;
}

.discussion-heading h4,
.discussion-heading p,
.discussion-text {
  margin: 0;
}

.discussion-heading h4 {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: var(--idds-body-size);
  line-height: var(--idds-body-line);
}

.discussion-heading p {
  margin-top: 4px;
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

.discussion-composer {
  display: grid;
  gap: 12px;
  padding: 16px;
  border: 1px solid var(--ina-stroke-primary);
  border-radius: var(--ina-radius-xl);
  background: var(--ina-background-secondary);
}

.discussion-composer-actions,
.discussion-inline-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.discussion-state,
.discussion-empty {
  padding: 32px;
  color: var(--ina-content-secondary);
  text-align: center;
}

.discussion-state.error {
  color: var(--ina-negative-700);
}

.discussion-empty {
  display: grid;
  justify-items: center;
  gap: 6px;
}

.discussion-empty strong {
  color: var(--ina-content-primary);
}

.discussion-list,
.discussion-thread {
  display: grid;
}

.discussion-list {
  gap: 20px;
}

.discussion-thread {
  gap: 12px;
  padding-bottom: 20px;
  border-bottom: 1px solid var(--ina-stroke-primary);
}

.discussion-comment {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.discussion-comment-body {
  min-width: 0;
  flex: 1;
  display: grid;
  gap: 10px;
}

.discussion-comment-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.discussion-author {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 8px;
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

.discussion-author span {
  padding: 2px 8px;
  border-radius: 999px;
  color: var(--ina-primary-700);
  background: var(--ina-primary-50);
}

.discussion-author time,
.discussion-author small {
  color: var(--ina-content-secondary);
}

.discussion-actions {
  display: flex;
  gap: 16px;
}

.discussion-actions button {
  width: 24px;
  height: 24px;
  display: grid;
  place-items: center;
  padding: 0;
  border: 0;
  color: var(--ina-content-secondary);
  background: transparent;
  cursor: pointer;
}

.discussion-actions button:hover {
  color: var(--ina-primary-primary);
}

.discussion-actions button.danger:hover {
  color: var(--ina-negative-600);
}

.discussion-text {
  color: var(--ina-content-primary);
  line-height: var(--idds-body-small-line);
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.discussion-reply-form {
  display: grid;
  gap: 8px;
  padding-top: 4px;
}

.discussion-replies {
  display: grid;
  gap: 14px;
  margin-left: 52px;
  padding-left: 16px;
  border-left: 2px solid var(--ina-stroke-primary);
}

.discussion-comment.reply {
  padding-top: 2px;
}

.discussion-history {
  border: 1px solid var(--ina-stroke-primary);
  border-radius: var(--ina-radius-lg);
  overflow: hidden;
}

.discussion-history-trigger {
  width: 100%;
  min-height: 48px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  border: 0;
  color: var(--ina-content-primary);
  background: var(--ina-background-secondary);
  font: inherit;
  font-weight: var(--idds-weight-medium);
  cursor: pointer;
}

.discussion-history-list {
  display: grid;
  gap: 16px;
  margin: 0;
  padding: 16px 20px;
  list-style: none;
}

.discussion-history-list li {
  display: grid;
  grid-template-columns: 10px minmax(0, 1fr);
  gap: 10px;
}

.discussion-history-list p,
.discussion-history-list small {
  margin: 4px 0 0;
  color: var(--ina-content-secondary);
}

.discussion-history-dot {
  width: 8px;
  height: 8px;
  margin-top: 7px;
  border-radius: 50%;
  background: var(--ina-primary-primary);
}

@media (max-width: 640px) {
  .discussion-composer { padding: 12px; }
  .discussion-comment-header { align-items: flex-start; }
  .discussion-author { display: grid; justify-items: start; }
  .discussion-replies { margin-left: 20px; padding-left: 12px; }
}
</style>
