<template>
  <header class="navbar" :class="{ scrolled: isScrolled, solid: !isHome && !isScrolled }">
    <div class="navbar-container">
      <NuxtLink to="/" class="logo">
        <img src="/logo-banner.svg" alt="Clapiers Volley Ball" width="111" height="44" class="logo-img">
      </NuxtLink>

      <nav class="nav-links hide-mobile">
        <NuxtLink to="/" class="nav-link">Accueil</NuxtLink>
        <NuxtLink to="/club" class="nav-link">Le Club</NuxtLink>
        <NuxtLink to="/horaires" class="nav-link">Horaires</NuxtLink>
        <NuxtLink to="/calendrier" class="nav-link">Calendrier</NuxtLink>
        <NuxtLink to="/contact" class="nav-link">Contact</NuxtLink>
      </nav>

      <div class="nav-actions">
        <NuxtLink to="/dashboard" class="btn-login">
          <i class="pi pi-user"></i>
          <span class="hide-mobile">Espace membre</span>
        </NuxtLink>

        <button class="mobile-menu-btn show-mobile" @click="toggleMenu">
          <i class="pi" :class="menuOpen ? 'pi-times' : 'pi-bars'"></i>
        </button>
      </div>
    </div>

    <!-- Mobile menu -->
    <Transition name="slide-down">
      <div v-if="menuOpen" class="mobile-menu">
        <NuxtLink to="/" class="mobile-link" @click="closeMenu">Accueil</NuxtLink>
        <NuxtLink to="/club" class="mobile-link" @click="closeMenu">Le Club</NuxtLink>
        <NuxtLink to="/horaires" class="mobile-link" @click="closeMenu">Horaires</NuxtLink>
        <NuxtLink to="/calendrier" class="mobile-link" @click="closeMenu">Calendrier</NuxtLink>
        <NuxtLink to="/contact" class="mobile-link" @click="closeMenu">Contact</NuxtLink>
      </div>
    </Transition>
  </header>
</template>

<script setup lang="ts">
const route = useRoute()
const isHome = computed(() => route.path === '/')
const isScrolled = ref(false)
const menuOpen = ref(false)

const toggleMenu = () => {
  menuOpen.value = !menuOpen.value
}

const closeMenu = () => {
  menuOpen.value = false
}

onMounted(() => {
  window.addEventListener('scroll', handleScroll)
})

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll)
})

const handleScroll = () => {
  isScrolled.value = window.scrollY > 50
}
</script>

<style scoped>
.navbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 1000;
  padding: 1rem 2rem;
  transition: all 0.3s ease;
}

.navbar.scrolled {
  background: rgba(58, 26, 40, 0.95); /* deep plum — logo hue, darkened so the mauve/pink logo pops */
  backdrop-filter: blur(10px);
  box-shadow: 0 2px 20px rgba(0, 0, 0, 0.2);
  padding: 0.75rem 2rem;
}

.navbar.solid {
  background: #3a1a28; /* deep plum */
  box-shadow: 0 2px 20px rgba(0, 0, 0, 0.2);
  padding: 0.75rem 2rem;
}

.navbar-container {
  max-width: 1200px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.logo {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  text-decoration: none;
  color: white;
  transition: color 0.3s ease;
}

.logo-img {
  height: 2.75rem;
  width: auto;
  object-fit: contain;
}

.nav-links {
  display: flex;
  gap: 2.5rem;
}

.nav-link {
  color: rgba(255, 255, 255, 0.9);
  text-decoration: none;
  font-weight: 500;
  font-size: 0.95rem;
  transition: all 0.2s ease;
  position: relative;
}

.nav-link::after {
  content: '';
  position: absolute;
  bottom: -4px;
  left: 0;
  width: 0;
  height: 2px;
  background: var(--club-secondary);
  transition: width 0.2s ease;
}

.nav-link:hover::after,
.nav-link.router-link-active::after {
  width: 100%;
}

.nav-actions {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.btn-login {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem 1.25rem;
  background: rgba(255, 255, 255, 0.15);
  color: white;
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 8px;
  text-decoration: none;
  font-weight: 500;
  font-size: 0.9rem;
  transition: all 0.2s ease;
}

.btn-login:hover {
  background: rgba(255, 255, 255, 0.25);
  transform: translateY(-1px);
}

.mobile-menu-btn {
  display: none;
  background: transparent;
  border: none;
  color: white;
  font-size: 1.5rem;
  cursor: pointer;
  padding: 0.5rem;
}

.mobile-menu {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: white;
  padding: 1rem;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
}

.mobile-link {
  display: block;
  padding: 1rem;
  color: var(--club-dark);
  text-decoration: none;
  font-weight: 500;
  border-radius: 8px;
  transition: background 0.2s ease;
}

.mobile-link:hover,
.mobile-link.router-link-active {
  background: var(--club-light);
}

.hide-mobile {
  display: flex;
}

.show-mobile {
  display: none;
}

@media (max-width: 768px) {
  .navbar {
    padding: 1rem;
  }

  .hide-mobile {
    display: none !important;
  }

  .show-mobile {
    display: flex;
  }

  .btn-login {
    padding: 0.5rem 0.75rem;
  }
}

.slide-down-enter-active,
.slide-down-leave-active {
  transition: all 0.3s ease;
}

.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
