<template>
  <nav
    :class="[
      'fixed',
      'top-0',
      'left-0',
      'w-full',
      'z-5',
      'transition-all',
      'transition-duration-300',
      isScrolled ? 'nav-solid' : 'nav-transparent'
    ]"
  >
    <div class="
      flex
      align-items-center
      justify-content-between
      px-4
      lg:px-8
      py-3
    ">
      <!-- Logo -->
      <img
        :src="config.logo"
        alt="Clapiers Volleyball Logo"
        class="h-3rem cursor-pointer"
        style="max-width: 200px;"
        @click="scrollToSection('hero')"
      />

      <!-- Desktop Menu -->
      <div class="hidden lg:flex gap-4 align-items-center">
        <Button
          label="Accueil"
          :text="!isScrolled"
          :outlined="isScrolled"
          severity="secondary"
          @click="scrollToSection('hero')"
        />
        <Button
          label="À propos"
          :text="!isScrolled"
          :outlined="isScrolled"
          severity="secondary"
          @click="scrollToSection('about')"
        />
        <Button
          label="Contact"
          :text="!isScrolled"
          :outlined="isScrolled"
          severity="secondary"
          @click="scrollToSection('contact')"
        />
        <Button
          label="Nous rejoindre"
          severity="warning"
          @click="scrollToSection('contact')"
          class="font-bold"
        />
      </div>

      <!-- Mobile Menu Toggle -->
      <Button
        icon="pi pi-bars"
        :text="!isScrolled"
        :outlined="isScrolled"
        rounded
        severity="secondary"
        class="lg:hidden"
        @click="mobileMenuVisible = true"
      />
    </div>

    <!-- Mobile Sidebar -->
    <Sidebar v-model:visible="mobileMenuVisible" position="right">
      <template #header>
        <div class="flex align-items-center gap-3">
          <img :src="config.logo" alt="Logo" class="h-2rem" />
        </div>
      </template>

      <div class="flex flex-column gap-3 p-3">
        <Button
          label="Accueil"
          text
          severity="secondary"
          class="justify-content-start"
          @click="scrollToSection('hero'); mobileMenuVisible = false"
        />
        <Button
          label="À propos"
          text
          severity="secondary"
          class="justify-content-start"
          @click="scrollToSection('about'); mobileMenuVisible = false"
        />
        <Button
          label="Contact"
          text
          severity="secondary"
          class="justify-content-start"
          @click="scrollToSection('contact'); mobileMenuVisible = false"
        />
        <Button
          label="Nous rejoindre"
          severity="warning"
          class="justify-content-start font-bold mt-3"
          @click="scrollToSection('contact'); mobileMenuVisible = false"
        />
      </div>
    </Sidebar>
  </nav>
</template>

<script setup lang="ts">
const { config } = useVitrineConfig()
const isScrolled = ref(false)
const mobileMenuVisible = ref(false)

const scrollToSection = (id: string) => {
  const element = document.getElementById(id)
  if (element) {
    const navHeight = 80 // Approximate navbar height
    const elementPosition = element.getBoundingClientRect().top + window.pageYOffset
    const offsetPosition = id === 'hero' ? 0 : elementPosition - navHeight

    window.scrollTo({
      top: offsetPosition,
      behavior: 'smooth'
    })
  }
}

const handleScroll = () => {
  isScrolled.value = window.scrollY > 50
}

onMounted(() => {
  window.addEventListener('scroll', handleScroll)
})

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll)
})
</script>

<style scoped>
/* Additional navigation-specific styles if needed */
</style>
