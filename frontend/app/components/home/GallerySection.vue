<template>
  <section id="galerie" class="gallery-section">
    <div class="container">
      <div class="section-header">
        <span class="section-label">Galerie</span>
        <h2 class="section-title">
          Moments<br />
          <span class="text-accent">partagés</span>
        </h2>
      </div>

      <div class="carousel-wrapper">
        <Carousel
          :value="images"
          :numVisible="1"
          :numScroll="1"
          :circular="true"
          :autoplayInterval="5000"
          :showIndicators="true"
          :showNavigators="true"
        >
          <template #item="{ data }">
            <div class="carousel-item">
              <img :src="data.src" :alt="data.alt" class="carousel-image" />
            </div>
          </template>
        </Carousel>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
const images = [
  {
    src: '/images/home/434F7201-B392-4A27-BE33-BF6AEF7233D3_1_105_c.jpeg',
    alt: 'Volleyball Club Clapiers'
  },
  {
    src: '/images/home/9EFEFB97-1978-45EF-99E6-B24B6CF9EA65_1_105_c.jpeg',
    alt: 'Volleyball Club Clapiers'
  },
  {
    src: '/images/home/AE2A32CA-3FE9-4614-859A-0A6DB3349AB2_1_105_c.jpeg',
    alt: 'Volleyball Club Clapiers'
  }
]
</script>

<style scoped>
.gallery-section {
  padding: 6rem 2rem;
  background: var(--club-light);
}

.container {
  max-width: 900px;
  margin: 0 auto;
}

.section-header {
  text-align: center;
  margin-bottom: 3rem;
}

.text-accent {
  color: var(--club-secondary);
}

.carousel-wrapper {
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}

.carousel-item {
  width: 100%;
}

.carousel-image {
  width: 100%;
  height: 500px;
  object-fit: cover;
  display: block;
}

:deep(.p-carousel) {
  background: transparent;
}

:deep(.p-carousel-content) {
  position: relative;
}

:deep(.p-carousel-prev),
:deep(.p-carousel-next) {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 10;
  background: rgba(255, 255, 255, 0.9) !important;
  color: var(--club-dark) !important;
  border: none !important;
  width: 48px !important;
  height: 48px !important;
  border-radius: 50% !important;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  transition: all 0.2s ease;
}

:deep(.p-carousel-prev) {
  left: 1rem;
}

:deep(.p-carousel-next) {
  right: 1rem;
}

:deep(.p-carousel-prev:hover),
:deep(.p-carousel-next:hover) {
  background: white !important;
  transform: translateY(-50%) scale(1.05);
}

:deep(.p-carousel-indicator-list) {
  position: absolute;
  bottom: 1.5rem;
  left: 50%;
  transform: translateX(-50%);
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: rgba(0, 0, 0, 0.3);
  border-radius: 20px;
  backdrop-filter: blur(10px);
}

:deep(.p-carousel-indicator) {
  margin: 0;
}

:deep(.p-carousel-indicator-button) {
  width: 10px !important;
  height: 10px !important;
  border-radius: 50% !important;
  background: rgba(255, 255, 255, 0.5) !important;
  border: none !important;
  transition: all 0.2s ease;
}

:deep(.p-carousel-indicator-active .p-carousel-indicator-button) {
  background: white !important;
  transform: scale(1.2);
}

@media (max-width: 768px) {
  .gallery-section {
    padding: 4rem 1.5rem;
  }

  .carousel-image {
    height: 300px;
  }

  :deep(.p-carousel-prev),
  :deep(.p-carousel-next) {
    width: 40px !important;
    height: 40px !important;
  }

  :deep(.p-carousel-prev) {
    left: 0.5rem;
  }

  :deep(.p-carousel-next) {
    right: 0.5rem;
  }
}
</style>
