<script setup>
import { Alert, Button, PasswordInput, TextField } from '@idds/vue'
import { reactive, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import http from '../lib/http'
import { getHomeByRole } from '../constants/roles'

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')
const fieldErrors = reactive({ email: '', password: '' })
const emailInput = ref(null)

const router = useRouter()
const auth = useAuthStore()

// Auto-focus email field on mount
onMounted(() => {
  emailInput.value?.inputRef?.focus()
})

// Validate email format
function isValidEmail(value) {
  const normalized = value.trim()
  const atIndex = normalized.indexOf('@')
  const lastDotIndex = normalized.lastIndexOf('.')

  return atIndex > 0
    && lastDotIndex > atIndex + 1
    && lastDotIndex < normalized.length - 1
    && !/\s/u.test(normalized)
}

function resetErrors() {
  error.value = ''
  fieldErrors.email = ''
  fieldErrors.password = ''
}

function validateCredentials() {
  if (!email.value.trim()) {
    fieldErrors.email = 'Masukkan alamat email Anda.'
    return false
  }

  if (!isValidEmail(email.value)) {
    fieldErrors.email = 'Gunakan format email yang valid, misalnya nama@instansi.go.id.'
    return false
  }

  if (!password.value) {
    fieldErrors.password = 'Masukkan password Anda.'
    return false
  }

  if (password.value.length < 8) {
    fieldErrors.password = 'Password minimal terdiri dari 8 karakter.'
    return false
  }

  return true
}

function applyValidationErrors(errors) {
  fieldErrors.email = errors.email?.[0] || errors.email || ''
  fieldErrors.password = errors.password?.[0] || errors.password || ''

  if (!fieldErrors.email && !fieldErrors.password) {
    error.value = 'Periksa kembali data yang Anda masukkan.'
  }
}

function handleLoginError(exception) {
  const response = exception?.response
  if (response?.status === 422 && response.data?.errors) {
    applyValidationErrors(response.data.errors)
    return
  }

  error.value = response?.data?.message
    || (response?.status === 422
      ? 'Periksa kembali data yang Anda masukkan.'
      : 'Akun belum dapat diakses. Periksa koneksi lalu coba lagi.')
}

async function submit() {
  resetErrors()
  if (!validateCredentials()) return

  loading.value = true
  try {
    const normalizedEmail = email.value.trim()
    const { data } = await http.post('/login', {
      email: normalizedEmail,
      password: password.value
    })
    auth.setToken(data.data.token)
    auth.setUser(data.data.user)

    const role = data.data.user?.role
    router.push(getHomeByRole(role).path)
  } catch (exception) {
    handleLoginError(exception)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-container">
    <div class="login-box">
      <div class="login-header">
        <div class="login-brand-mark">
          <img :src="'/bssn.png'" alt="BSSN" />
        </div>
        <h1>Sistem Manajemen Pengembangan Aplikasi</h1>
        <p>Silakan masuk untuk melanjutkan</p>
      </div>

      <Alert
        v-if="error"
        class="login-alert"
        variant="critical"
        title="Belum dapat masuk"
        :message="error"
      />

      <form id="loginForm" autocomplete="on" novalidate @submit.prevent="submit">
        <TextField
          ref="emailInput"
          v-model="email"
          label="Email"
          type="email"
          size="lg"
          placeholder="nama@instansi.go.id"
          autocomplete="username"
          input-mode="email"
          auto-capitalize="off"
          auto-correct="off"
          :disabled="loading"
          :required="true"
          :status="fieldErrors.email ? 'error' : 'neutral'"
          :status-message="fieldErrors.email"
        />

        <PasswordInput
          v-model="password"
          label="Password"
          size="lg"
          placeholder="Masukkan password"
          :max-length="128"
          :disabled="loading"
          :required="true"
          :status="fieldErrors.password ? 'error' : 'neutral'"
          :status-message="fieldErrors.password"
        />

        <Button
          class="login-submit"
          hierarchy="primary"
          size="xl"
          :disabled="loading"
          type="submit"
          :aria-busy="loading"
        >
          <span v-if="loading" class="loading-spinner" aria-hidden="true"></span>
          {{ loading ? 'Sedang masuk' : 'Masuk' }}
        </Button>
      </form>
    </div>
  </div>
</template>

<style scoped>
.login-container {
  position: relative;
  isolation: isolate;
  width: 100%;
  min-width: 0;
  min-height: 100vh;
  min-height: 100dvh;
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  place-items: center;
  overflow: hidden;
  padding: 24px;
  box-sizing: border-box;
  background: #0b2348;
}

.login-container::before {
  content: '';
  position: absolute;
  inset: -18px;
  z-index: -2;
  background: url('/bssn-building-login.png') center / cover no-repeat;
  filter: saturate(0.92);
  transform: scale(1.02);
}

.login-container::after {
  content: '';
  position: absolute;
  inset: 0;
  z-index: -1;
  background: rgba(5, 17, 38, 0.52);
}

:deep(.login-box),
.login-box {
  position: relative;
  z-index: 1;
  width: 100%;
  min-width: 0;
  max-width: 440px;
  justify-self: center;
  margin: 0;
  padding: 36px 34px;
  box-sizing: border-box;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.58);
  background: rgba(255, 255, 255, 0.9);
  box-shadow: 0 8px 24px rgba(5, 18, 38, 0.28);
  backdrop-filter: blur(12px);
}

:global(#app .login-box) {
  border-radius: 8px !important;
  box-shadow: 0 8px 24px rgba(5, 18, 38, 0.28) !important;
}

.login-header {
  min-width: 0;
  text-align: center;
  margin-bottom: 28px;
}

.login-brand-mark {
  width: 78px;
  height: 78px;
  margin: 0 auto 16px;
  border: 0;
  background: transparent;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: none;
  overflow: visible;
}

.login-brand-mark img {
  width: 78px;
  height: 78px;
  object-fit: contain;
}

.login-header h1 {
  font-family: var(--ui-font-display);
  font-size: var(--idds-heading-h4-size);
  font-weight: var(--idds-weight-semibold);
  color: var(--ina-content-primary);
  margin: 0 0 8px 0;
  line-height: var(--idds-heading-h4-line);
}

.login-header p {
  font-size: var(--idds-caption-size);
  color: var(--ina-content-secondary);
  margin: 0;
  line-height: var(--idds-caption-line);
}

.login-alert {
  margin-bottom: 20px;
}

.login-submit {
  width: 100%;
  margin-top: 20px;
}

#loginForm {
  display: grid;
  min-width: 0;
  gap: var(--ina-spacing-4);
}

#loginForm :deep(*) {
  min-width: 0;
  box-sizing: border-box;
}

/* Loading Spinner */
.loading-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 520px) {
  .login-container {
    padding: 16px;
  }

  .login-box {
    width: calc(100vw - 32px) !important;
    max-width: 100%;
    padding: 28px 22px;
  }

  .login-header h1 {
    font-size: var(--idds-heading-h5-size) !important;
    line-height: var(--idds-heading-h5-line) !important;
  }
}
</style>
