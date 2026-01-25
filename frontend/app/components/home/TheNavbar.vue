<template>
  <header class="navbar" :class="{ scrolled: isScrolled }">
    <div class="navbar-container">
      <NuxtLink to="/" class="logo">
        <span class="logo-icon">🏐</span>
        <span class="logo-text">
          <span class="logo-main">VBC</span>
          <span class="logo-sub">Clapiers</span>
        </span>
      </NuxtLink>

      <nav class="nav-links hide-mobile">
        <a href="#accueil" class="nav-link">Accueil</a>
        <a href="#club" class="nav-link">Le Club</a>
        <a href="#galerie" class="nav-link">Galerie</a>
        <a href="#horaires" class="nav-link">Horaires</a>
        <a href="#contact" class="nav-link">Contact</a>
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
        <a href="#accueil" class="mobile-link" @click="closeMenu">Accueil</a>
        <a href="#club" class="mobile-link" @click="closeMenu">Le Club</a>
        <a href="#galerie" class="mobile-link" @click="closeMenu">Galerie</a>
        <a href="#horaires" class="mobile-link" @click="closeMenu">Horaires</a>
        <a href="#contact" class="mobile-link" @click="closeMenu">Contact</a>
      </div>
    </Transition>
  </header>
</template>

<script setup lang="ts">
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
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
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

.scrolled .logo {
  color: var(--club-dark);
}

.logo-icon {
  font-size: 2rem;
}

.logo-text {
  display: flex;
  flex-direction: column;
  line-height: 1.1;
}

.logo-main {
  font-size: 1.25rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.logo-sub {
  font-size: 0.75rem;
  font-weight: 500;
  opacity: 0.8;
  text-transform: uppercase;
  letter-spacing: 0.1em;
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

.scrolled .nav-link {
  color: var(--club-dark);
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

.nav-link:hover::after {
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

.scrolled .btn-login {
  background: var(--club-primary);
  color: white;
  border-color: var(--club-primary);
}

.btn-login:hover {
  background: rgba(255, 255, 255, 0.25);
  transform: translateY(-1px);
}

.scrolled .btn-login:hover {
  background: #2d5a87;
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

.scrolled .mobile-menu-btn {
  color: var(--club-dark);
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

.mobile-link:hover {
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
