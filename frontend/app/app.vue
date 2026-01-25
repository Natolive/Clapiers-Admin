<template>
  <div id="app">
    <Toast />
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </div>
</template>

<style>
/* Reset and Full Height */
html, body, #__nuxt, #app {
  min-height: 100vh;
  margin: 0;
  padding: 0;
  font-family: var(--p-font-family, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif),serif;
}

/* Smooth scrolling */
html {
  scroll-behavior: smooth;
}

/* Page Transition Animations */
.page-enter-active,
.page-leave-active {
  transition: all 0.3s ease-in-out;
}

.page-enter-from {
  opacity: 0;
  transform: translateX(20px);
}

.page-leave-to {
  opacity: 0;
  transform: translateX(-20px);
}

/* Layout Transition Animations */
.layout-enter-active,
.layout-leave-active {
  transition: all 0.3s ease-in-out;
}

.layout-enter-from {
  opacity: 0;
  filter: blur(4px);
}

.layout-leave-to {
  opacity: 0;
  filter: blur(4px);
}
</style>