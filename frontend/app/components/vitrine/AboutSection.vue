<template>
  <section id="about" class="vitrine-section surface-ground">
    <div class="max-w-screen-xl mx-auto">
      <!-- Section Title -->
      <div class="text-center mb-6" ref="titleRef">
        <h2 class="text-5xl lg:text-6xl font-bold mb-3 gradient-text">
          À propos du club
        </h2>
        <p class="text-xl text-600 max-w-3xl mx-auto">
          Découvrez l'histoire et les valeurs qui font de Clapiers Volleyball un club unique
        </p>
      </div>

      <!-- Stats Cards -->
      <div class="grid mb-6 lg:mb-8">
        <div
          v-for="(stat, index) in config.stats"
          :key="index"
          class="col-12 md:col-6 lg:col-3"
          ref="statsRefs"
        >
          <Card class="text-center hover-lift">
            <template #content>
              <i :class="`pi ${stat.icon} text-6xl mb-3`" style="color: var(--vitrine-primary)"></i>
              <div class="text-4xl font-bold mb-2" style="color: var(--vitrine-secondary)">
                {{ stat.value }}
              </div>
              <div class="text-lg text-600">{{ stat.label }}</div>
            </template>
          </Card>
        </div>
      </div>

      <!-- Description and Values -->
      <div class="grid gap-5 mb-6">
        <div class="col-12" ref="descriptionRef">
          <h3 class="text-3xl font-bold mb-4">Notre histoire</h3>
          <p class="text-lg text-700 line-height-3 mb-4">
            {{ config.clubInfo.description }}
          </p>
        </div>

        <!-- Values -->
        <div class="col-12">
          <h3 class="text-3xl font-bold mb-4">Nos valeurs</h3>
          <div class="grid">
            <div
              v-for="(value, index) in config.values"
              :key="index"
              class="col-12 md:col-4"
              ref="valuesRefs"
            >
              <div class="flex gap-3 align-items-start">
                <div
                  class="flex align-items-center justify-content-center border-circle flex-shrink-0"
                  style="width: 3rem; height: 3rem; background: var(--vitrine-primary);"
                >
                  <i :class="`pi ${value.icon} text-white text-xl`"></i>
                </div>
                <div>
                  <h4 class="text-xl font-bold mb-2">{{ value.title }}</h4>
                  <p class="text-600 line-height-3 m-0">{{ value.description }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Photos Carousel - Full Width -->
      <div ref="photosRef">
        <h3 class="text-3xl font-bold mb-4 text-center">Galerie photos</h3>
        <Carousel
          :value="config.clubPhotos"
          :numVisible="3"
          :numScroll="1"
          :autoplayInterval="4000"
          circular
          :showNavigators="true"
          :showIndicators="true"
          :responsiveOptions="carouselResponsiveOptions"
        >
          <template #item="slotProps">
            <div class="px-3">
              <img
                :src="slotProps.data"
                :alt="`Photo du club ${slotProps.index + 1}`"
                class="w-full border-round-lg shadow-3 hover-scale"
                style="height: 400px; object-fit: cover;"
              />
            </div>
          </template>
        </Carousel>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
const { config } = useVitrineConfig()
const { observe, observeMultiple } = useScrollAnimation('fade-up')

const titleRef = ref<HTMLElement | null>(null)
const descriptionRef = ref<HTMLElement | null>(null)
const photosRef = ref<HTMLElement | null>(null)
const statsRefs = ref<HTMLElement[]>([])
const valuesRefs = ref<HTMLElement[]>([])

// Carousel responsive options
const carouselResponsiveOptions = ref([
  {
    breakpoint: '1024px',
    numVisible: 3,
    numScroll: 1
  },
  {
    breakpoint: '768px',
    numVisible: 2,
    numScroll: 1
  },
  {
    breakpoint: '560px',
    numVisible: 1,
    numScroll: 1
  }
])

onMounted(() => {
  observe(titleRef.value)
  observe(descriptionRef.value, 100)
  observe(photosRef.value, 200)
  observeMultiple(statsRefs.value, 100)
  observeMultiple(valuesRefs.value, 100)
})
</script>

<style scoped>
.max-w-screen-xl {
  max-width: 1280px;
}

.max-w-3xl {
  max-width: 48rem;
}
</style>
