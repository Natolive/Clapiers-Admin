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
  height: 100vh;
  margin: 0;
  padding: 0;
  font-family: var(--p-font-family, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif),serif;
}
</style>