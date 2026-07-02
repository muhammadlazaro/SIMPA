<script setup>
import Toast from './components/Toast.vue'
import ErrorBoundary from './components/ErrorBoundary.vue'
</script>

<template>
  <ErrorBoundary>
    <router-view />
    <Toast />
  </ErrorBoundary>
</template>
