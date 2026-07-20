<script setup>
import { ToastProvider } from '@idds/vue'
import Toast from './components/Toast.vue'
import ErrorBoundary from './components/ErrorBoundary.vue'
</script>

<template>
  <ToastProvider>
    <ErrorBoundary>
      <router-view />
      <Toast />
    </ErrorBoundary>
  </ToastProvider>
</template>
