<template>
  <div class="public-layout">
    <HomeTheNavbar />
    <main class="main-content">
      <slot />
    </main>
    <HomeTheFooter />
  </div>
</template>

<style scoped>
.public-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.main-content {
  flex: 1;
}
</style>
