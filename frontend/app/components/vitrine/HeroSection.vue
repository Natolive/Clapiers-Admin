<template>
  <section
    id="hero"
    class="
      hero-section
      flex
      flex-column
      align-items-center
      justify-content-center
      min-h-screen
      relative
      overflow-hidden
    "
  >
    <!-- Background Image -->
    <div
      class="absolute top-0 left-0 w-full h-full"
      :style="{
        backgroundImage: `url(${config.heroBackground})`,
        backgroundSize: 'cover',
        backgroundPosition: 'center',
        backgroundRepeat: 'no-repeat'
      }"
    ></div>

    <!-- Overlay -->
    <div class="absolute top-0 left-0 w-full h-full hero-background-gradient" style="opacity: 0.85;"></div>

    <!-- Content -->
    <div class="z-content text-center px-4 w-full" style="max-width: 900px;">
      <h1 class="
        hero-title
        text-6xl
        md:text-7xl
        lg:text-8xl
        font-bold
        text-white
        mb-4
        text-shadow-strong
      ">
        {{ config.clubInfo.name }}
      </h1>

      <p class="
        hero-subtitle
        text-2xl
        md:text-3xl
        text-white
        mb-6
        text-shadow
      ">
        {{ config.clubInfo.tagline }}
      </p>

      <div class="hero-cta flex gap-3 justify-content-center flex-wrap">
        <Button
          label="Nous rejoindre"
          size="large"
          severity="warning"
          class="font-bold px-6 py-3 text-xl"
          @click="scrollToContact"
        />
        <Button
          label="En savoir plus"
          size="large"
          outlined
          severity="secondary"
          class="font-bold px-6 py-3 text-xl"
          style="background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); border-color: white; color: white;"
          @click="scrollToAbout"
        />
      </div>
    </div>

    <!-- Scroll Indicator -->
    <div class="absolute bottom-8 animate-bounce z-content">
      <i class="pi pi-chevron-down text-white text-3xl cursor-pointer" @click="scrollToAbout"></i>
    </div>
  </section>
</template>

<script setup lang="ts">
const { config } = useVitrineConfig()

const scrollToContact = () => {
  const element = document.getElementById('contact')
  if (element) {
    const navHeight = 80
    const elementPosition = element.getBoundingClientRect().top + window.pageYOffset
    window.scrollTo({
      top: elementPosition - navHeight,
      behavior: 'smooth'
    })
  }
}

const scrollToAbout = () => {
  const element = document.getElementById('about')
  if (element) {
    const navHeight = 80
    const elementPosition = element.getBoundingClientRect().top + window.pageYOffset
    window.scrollTo({
      top: elementPosition - navHeight,
      behavior: 'smooth'
    })
  }
}
</script>

<style scoped>
.hero-section {
  position: relative;
}
</style>
