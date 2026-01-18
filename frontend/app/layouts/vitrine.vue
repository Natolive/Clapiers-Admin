<template>
  <div class="vitrine-layout">
    <NavigationBar />

    <main>
      <slot />
    </main>

    <footer class="surface-section p-6 lg:p-8 border-top-1 surface-border">
      <div class="max-w-screen-xl mx-auto">
        <div class="grid">
          <!-- Logo and Description -->
          <div class="col-12 lg:col-4 mb-4 lg:mb-0">
            <img :src="config.logo" alt="Clapiers Volleyball" class="h-3rem mb-3" />
            <p class="text-600 line-height-3">
              {{ config.clubInfo.tagline }}
            </p>
          </div>

          <!-- Quick Links -->
          <div class="col-12 md:col-6 lg:col-4 mb-4 lg:mb-0">
            <h3 class="text-xl font-bold mb-3">Liens rapides</h3>
            <ul class="list-none p-0 m-0">
              <li class="mb-2">
                <a href="#hero" class="text-600 hover:text-primary no-underline transition-colors transition-duration-300">
                  <i class="pi pi-angle-right mr-2"></i>Accueil
                </a>
              </li>
              <li class="mb-2">
                <a href="#about" class="text-600 hover:text-primary no-underline transition-colors transition-duration-300">
                  <i class="pi pi-angle-right mr-2"></i>À propos
                </a>
              </li>
              <li class="mb-2">
                <a href="#contact" class="text-600 hover:text-primary no-underline transition-colors transition-duration-300">
                  <i class="pi pi-angle-right mr-2"></i>Contact
                </a>
              </li>
              <li class="mb-2">
                <a href="/login" class="text-600 hover:text-primary no-underline transition-colors transition-duration-300">
                  <i class="pi pi-angle-right mr-2"></i>Espace membre
                </a>
              </li>
            </ul>
          </div>

          <!-- Contact Info -->
          <div class="col-12 md:col-6 lg:col-4">
            <h3 class="text-xl font-bold mb-3">Contact</h3>
            <div class="flex flex-column gap-2">
              <div class="flex align-items-start gap-2">
                <i class="pi pi-map-marker text-primary mt-1"></i>
                <span class="text-600">{{ config.clubInfo.address }}</span>
              </div>
              <div class="flex align-items-center gap-2">
                <i class="pi pi-envelope text-primary"></i>
                <a :href="`mailto:${config.clubInfo.email}`" class="text-600 hover:text-primary no-underline">
                  {{ config.clubInfo.email }}
                </a>
              </div>
              <div class="flex align-items-center gap-2">
                <i class="pi pi-phone text-primary"></i>
                <a :href="`tel:${config.clubInfo.phone}`" class="text-600 hover:text-primary no-underline">
                  {{ config.clubInfo.phone }}
                </a>
              </div>
            </div>

            <!-- Social Media -->
            <div class="flex gap-3 mt-4">
              <a
                v-if="config.clubInfo.socialMedia.facebook"
                :href="config.clubInfo.socialMedia.facebook"
                target="_blank"
                rel="noopener noreferrer"
                class="flex align-items-center justify-content-center border-circle w-3rem h-3rem surface-card hover:surface-hover transition-all transition-duration-300"
              >
                <i class="pi pi-facebook text-xl"></i>
              </a>
              <a
                v-if="config.clubInfo.socialMedia.instagram"
                :href="config.clubInfo.socialMedia.instagram"
                target="_blank"
                rel="noopener noreferrer"
                class="flex align-items-center justify-content-center border-circle w-3rem h-3rem surface-card hover:surface-hover transition-all transition-duration-300"
              >
                <i class="pi pi-instagram text-xl"></i>
              </a>
              <a
                v-if="config.clubInfo.socialMedia.twitter"
                :href="config.clubInfo.socialMedia.twitter"
                target="_blank"
                rel="noopener noreferrer"
                class="flex align-items-center justify-content-center border-circle w-3rem h-3rem surface-card hover:surface-hover transition-all transition-duration-300"
              >
                <i class="pi pi-twitter text-xl"></i>
              </a>
            </div>
          </div>
        </div>

        <!-- Copyright -->
        <div class="border-top-1 surface-border pt-4 mt-6 text-center">
          <p class="text-600 m-0">
            © {{ new Date().getFullYear() }} {{ config.clubInfo.name }}. Tous droits réservés.
          </p>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import NavigationBar from '~/components/vitrine/NavigationBar.vue'

const { config, applyBrandColors } = useVitrineConfig()

onMounted(() => {
  applyBrandColors()
})
</script>

<style scoped>
.vitrine-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

main {
  flex: 1;
}

.max-w-screen-xl {
  max-width: 1280px;
}
</style>
