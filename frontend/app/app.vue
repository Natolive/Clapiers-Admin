<template>
  <div id="app">
    <NuxtPage />
  </div>
</template>

<style>
/* Reset and Full Height */
html, body, #__nuxt, #app {
  height: 100vh;
  margin: 0;
  padding: 0;
  font-family: var(--p-font-family, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif),serif;
}

.page-enter-active,
.page-leave-active {
  transition: all 0.4s;
}
.page-enter-from,
.page-leave-to {
  opacity: 0;
  filter: blur(1rem);
}
</style>