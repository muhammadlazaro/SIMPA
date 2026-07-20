<script setup>
import { Button } from '@idds/vue'
import { IconArrowLeft, IconHome } from '@tabler/icons-vue'
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { getHomeByRole } from '../constants/roles'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const home = computed(() => (
  auth.isAuthenticated ? getHomeByRole(auth.role).path : '/login'
))

function goBack() {
  if (window.history.length > 1) {
    router.back()
    return
  }

  router.push(home.value)
}
</script>

<template>
  <main class="not-found-page">
    <section class="not-found-content" aria-labelledby="not-found-title">
      <img
        class="not-found-illustration"
        src="/illustrations/not-found.svg"
        alt=""
        width="260"
        height="172"
      />
      <span class="not-found-code">404</span>
      <h1 id="not-found-title">Halaman tidak ditemukan</h1>
      <p>
        Alamat mungkin berubah atau halaman sudah tidak tersedia. Kembali ke halaman utama untuk melanjutkan pekerjaan Anda.
      </p>
      <div class="not-found-actions">
        <Button hierarchy="primary" size="lg" :prefix-icon="IconHome" @click="router.push(home)">
          Ke halaman utama
        </Button>
        <Button hierarchy="secondary" size="lg" :prefix-icon="IconArrowLeft" @click="goBack">
          Kembali
        </Button>
      </div>
    </section>
  </main>
</template>

<style scoped>
.not-found-page {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: var(--ina-spacing-6);
  background: var(--ina-background-secondary);
}

.not-found-content {
  width: min(100%, 620px);
  text-align: center;
}

.not-found-illustration {
  width: 260px;
  max-width: 100%;
  height: auto;
  margin-bottom: var(--ina-spacing-4);
}

.not-found-code {
  display: block;
  color: var(--ina-content-guide);
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-semibold);
  line-height: var(--idds-caption-line);
}

h1 {
  margin: var(--ina-spacing-2) 0 0;
  color: var(--ina-content-primary);
  font-size: var(--idds-heading-h2-size);
  line-height: var(--idds-heading-h2-line);
}

p {
  max-width: 56ch;
  margin: var(--ina-spacing-4) auto 0;
  color: var(--ina-content-secondary);
  font-size: var(--idds-body-size);
  line-height: var(--idds-body-line);
}

.not-found-actions {
  display: flex;
  justify-content: center;
  gap: var(--ina-spacing-3);
  margin-top: var(--ina-spacing-6);
}

@media (max-width: 520px) {
  h1 {
    font-size: var(--idds-heading-h4-size);
    line-height: var(--idds-heading-h4-line);
  }

  .not-found-actions {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
