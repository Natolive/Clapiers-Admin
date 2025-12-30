<template>
  <div class="flex min-h-screen surface-ground">
    <!-- Sidebar -->
    <aside
      :class="[
        'sidebar fixed left-0 top-0 bottom-0 z-5 surface-card border-right-1 surface-border shadow-2 transition-all transition-duration-300 flex flex-column',
        sidebarVisible ? 'sidebar-open' : 'sidebar-closed'
      ]"
    >
      <!-- Header -->
      <div class="p-4 border-bottom-1 surface-border">
        <h2 class="text-2xl font-bold text-primary m-0">Clapiers VB</h2>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 p-3 overflow-y-auto">
        <Menu :model="navigationItems" class="w-full border-none bg-transparent" />
      </nav>

      <!-- Footer -->
      <div class="p-3 border-top-1 surface-border">
        <!-- Actual Season Display -->
        <div v-if="seasonsStore.hasActualSeason" class="mb-3 p-3 surface-100 border-round">
          <div class="flex align-items-center gap-2 mb-2">
            <i class="pi pi-calendar text-primary"></i>
            <span class="font-semibold text-sm">Saison actuelle</span>
          </div>
          <div class="text-primary font-bold text-lg">
            {{ seasonsStore.actualSeason?.startYear }}/{{ seasonsStore.actualSeason?.endYear }}
          </div>
        </div>

        <!-- Logout Button -->
        <Button
          icon="pi pi-sign-out"
          label="Déconnexion"
          severity="danger"
          text
          @click="handleLogout"
          class="w-full justify-content-start"
        />
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content flex-1 flex flex-column transition-all transition-duration-300">
      <!-- Mobile Backdrop -->
      <div
        v-if="sidebarVisible"
        @click="sidebarVisible = false"
        class="backdrop lg:hidden"
      ></div>

      <!-- Top Header -->
      <header class="surface-card border-bottom-1 surface-border p-3 px-4 flex justify-content-between align-items-center sticky top-0 z-4 shadow-1">
        <div class="flex align-items-center gap-3">
          <Button
            icon="pi pi-bars"
            text
            rounded
            @click="sidebarVisible = !sidebarVisible"
            class="lg:hidden"
          />
          <h1 class="text-xl font-bold text-color m-0">{{ pageTitle }}</h1>
        </div>
        <div class="flex align-items-center gap-2">
          <i class="pi pi-clock text-primary text-xl"></i>
          <span class="font-semibold text-lg">{{ currentTime }}</span>
        </div>
      </header>

      <!-- Content Area -->
      <div class="p-4 flex-1">
        <slot />
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '~/stores/auth.store';
import { useSeasonsStore } from '~/stores/seasons.store';

type MenuItem = {
  label: string;
  icon: string;
  route?: string;
  command?: () => void;
  items?: MenuItem[];
};

const authStore = useAuthStore();
const seasonsStore = useSeasonsStore();
const route = useRoute();
const { isSuperAdmin, isAdmin } = useUserRole();
const sidebarVisible = ref(false);
const currentTime = ref('');
let timeInterval: ReturnType<typeof setInterval> | null = null;

const closeSidebarOnMobile = () => {
  if (window.innerWidth < 1024) {
    sidebarVisible.value = false;
  }
};

const navigationItems = computed<MenuItem[]>(() => {
  const items: MenuItem[] = [
    { label: 'Tableau de bord', icon: 'pi pi-home', route: '/dashboard', command: () => { navigateTo('/dashboard'); closeSidebarOnMobile(); } },
    { label: 'Calendrier', icon: 'pi pi-calendar', route: '/dashboard/calendar', command: () => { navigateTo('/dashboard/calendar'); closeSidebarOnMobile(); } },
  ];

  // Show Mon équipe for admin and super admin
  if (isAdmin.value) {
    items.push({ label: 'Mon équipe', icon: 'pi pi-users', route: '/dashboard/my-team', command: () => { navigateTo('/dashboard/my-team'); closeSidebarOnMobile(); } });
  }

  // Only show Paramètres menu if user is super admin
  if (isSuperAdmin.value) {
    items.push({
      label: 'Paramètres',
      icon: 'pi pi-cog',
      items: [
        { label: 'Saisons', icon: 'pi pi-calendar', route: '/dashboard/settings/seasons', command: () => { navigateTo('/dashboard/settings/seasons'); closeSidebarOnMobile(); } },
        { label: 'Utilisateurs', icon: 'pi pi-users', route: '/dashboard/settings/users', command: () => { navigateTo('/dashboard/settings/users'); closeSidebarOnMobile(); } },
        { label: 'Équipes', icon: 'pi pi-sitemap', route: '/dashboard/settings/teams', command: () => { navigateTo('/dashboard/settings/teams'); closeSidebarOnMobile(); } },
        { label: 'Licenciés', icon: 'pi pi-id-card', route: '/dashboard/settings/members', command: () => { navigateTo('/dashboard/settings/members'); closeSidebarOnMobile(); } },
      ]
    });
  }

  return items;
});

const pageTitle = computed(() => {
  // Check top-level items
  let currentItem = navigationItems.value.find(item => item.route === route.path);

  // If not found, check nested items
  if (!currentItem) {
    for (const item of navigationItems.value) {
      if (item.items) {
        currentItem = item.items.find(subItem => subItem.route === route.path);
        if (currentItem) break;
      }
    }
  }

  return currentItem?.label || 'Tableau de bord';
});

const handleLogout = () => {
  authStore.logout();
};

const handleResize = () => {
  // Auto-open sidebar on desktop, auto-close on mobile
  sidebarVisible.value = window.innerWidth >= 1024;
};

const updateTime = () => {
  const now = new Date();
  currentTime.value = now.toLocaleTimeString('fr-FR', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  });
};

onMounted(() => {
  sidebarVisible.value = window.innerWidth >= 1024;
  window.addEventListener('resize', handleResize);
  updateTime();
  timeInterval = setInterval(updateTime, 1000);
});

onUnmounted(() => {
  window.removeEventListener('resize', handleResize);
  if (timeInterval) {
    clearInterval(timeInterval);
  }
});
</script>

<style scoped>
/* Desktop styles */
.sidebar {
  width: 18rem;
}

.sidebar-closed {
  width: 0;
  overflow: hidden;
}

.main-content {
  margin-left: 18rem;
}

.sidebar-closed ~ .main-content {
  margin-left: 0;
}

/* Backdrop - hidden by default */
.backdrop {
  display: none;
}

/* Mobile styles */
@media (max-width: 1023px) {
  .sidebar {
    width: 0;
    overflow: hidden;
  }

  .sidebar-open {
    width: 18rem;
    z-index: 1001;
  }

  .main-content {
    margin-left: 0;
  }

  .backdrop {
    display: block;
    position: fixed;
    top: 0;
    left: 18rem;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    z-index: 999;
    opacity: 0;
    animation: backdropFadeIn 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
  }

  @keyframes backdropFadeIn {
    from {
      opacity: 0;
      backdrop-filter: blur(0px);
    }
    to {
      opacity: 1;
      backdrop-filter: blur(4px);
    }
  }
}
</style>