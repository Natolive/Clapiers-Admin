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
        <ul class="nav-list">
          <li v-for="item in navigationItems" :key="item.label">
            <!-- Item with subitems -->
            <template v-if="item.items">
              <div class="nav-group-label">
                <i :class="item.icon"></i>
                <span>{{ item.label }}</span>
              </div>
              <ul class="nav-sublist">
                <li v-for="subitem in item.items" :key="subitem.label">
                  <a
                    class="nav-link"
                    :class="{ active: route.path === subitem.route }"
                    @click="subitem.command?.()"
                  >
                    <i :class="subitem.icon"></i>
                    <span>{{ subitem.label }}</span>
                  </a>
                </li>
              </ul>
            </template>
            <!-- Single item -->
            <template v-else>
              <a
                class="nav-link"
                :class="{ active: route.path === item.route }"
                @click="item.command?.()"
              >
                <i :class="item.icon"></i>
                <span>{{ item.label }}</span>
                <Badge
                  v-if="item.badge"
                  :value="item.badge"
                  :severity="item.badgeSeverity || 'primary'"
                  class="nav-badge"
                />
              </a>
            </template>
          </li>
        </ul>
      </nav>

      <!-- Footer -->
      <div class="p-3 border-top-1 surface-border">
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
      <div class="p-1 md:p-4 flex-1">
        <slot />
      </div>
    </main>

    <!-- Global Dialog Container -->
    <DialogContainer />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '~/stores/auth.store';
import DialogContainer from '~/components/common/DialogContainer.vue';
import { AppUserRole } from '~/types/entity/AppUser';
import { ContactMessageRepository } from '~/repository/contact-message-repository';

type MenuItem = {
  label: string;
  icon: string;
  route?: string;
  command?: () => void;
  items?: MenuItem[];
  badge?: string;
  badgeSeverity?: string;
};

const authStore = useAuthStore();
const route = useRoute();
const { isSuperAdmin, isAdmin, hasRole } = useUserRole();
const sidebarVisible = ref(false);
const currentTime = ref('');
const unreadMessagesCount = ref(0);
let timeInterval: ReturnType<typeof setInterval> | null = null;
let messagesInterval: ReturnType<typeof setInterval> | null = null;

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

  // Show Messages for users with VIEW_MESSAGE role
  if (hasRole(AppUserRole.VIEW_MESSAGE)) {
    const messageItem: MenuItem = {
      label: 'Messages',
      icon: 'pi pi-envelope',
      route: '/dashboard/messages',
      command: () => { navigateTo('/dashboard/messages'); closeSidebarOnMobile(); }
    };
    if (unreadMessagesCount.value > 0) {
      messageItem.badge = String(unreadMessagesCount.value);
      messageItem.badgeSeverity = 'danger';
    }
    items.push(messageItem);
  }

  // Only show Paramètres menu if user is super admin
  if (isSuperAdmin.value) {
    items.push({
      label: 'Paramètres',
      icon: 'pi pi-cog',
      items: [
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

const fetchUnreadMessagesCount = async () => {
  if (!hasRole(AppUserRole.VIEW_MESSAGE)) return;

  try {
    const repository = new ContactMessageRepository();
    const result = await repository.countUnread();
    unreadMessagesCount.value = result.count;
  } catch (e) {
    // Silently fail - don't show error for background fetch
  }
};

onMounted(async () => {
  sidebarVisible.value = window.innerWidth >= 1024;
  window.addEventListener('resize', handleResize);
  updateTime();
  timeInterval = setInterval(updateTime, 1000);

  // Fetch unread messages count
  await fetchUnreadMessagesCount();
  // Refresh every 30 seconds
  messagesInterval = setInterval(fetchUnreadMessagesCount, 30000);
});

onUnmounted(() => {
  window.removeEventListener('resize', handleResize);
  if (timeInterval) {
    clearInterval(timeInterval);
  }
  if (messagesInterval) {
    clearInterval(messagesInterval);
  }
});
</script>

<style scoped>
/* Navigation styles */
.nav-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  color: var(--p-text-color);
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s ease;
}

.nav-link:hover {
  background: var(--p-surface-100);
}

.nav-link.active {
  background: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
}

.nav-link i {
  font-size: 1.1rem;
  width: 1.25rem;
  text-align: center;
}

.nav-badge {
  margin-left: auto;
}

.nav-group-label {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  font-weight: 600;
  color: var(--p-text-muted-color);
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-top: 0.5rem;
}

.nav-group-label i {
  font-size: 1rem;
}

.nav-sublist {
  list-style: none;
  padding: 0;
  margin: 0;
  padding-left: 1rem;
}

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