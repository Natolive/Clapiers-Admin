<template>
  <div class="app-shell">

    <!-- Sidebar -->
    <aside
      :class="[
        'sidebar',
        sidebarVisible ? 'sidebar--open' : '',
        sidebarCollapsed && isDesktop ? 'sidebar--collapsed' : '',
        sidebarHovered ? 'sidebar--hovered' : ''
      ]"
      @mouseenter="handleSidebarMouseEnter"
      @mouseleave="handleSidebarMouseLeave"
    >
      <!-- Brand -->
      <div class="sidebar__brand">
        <div class="brand-orb">🏐</div>
        <div class="brand-text">
          <span class="brand-name">Clapiers</span>
          <span class="brand-sub">Volley-Ball Club</span>
        </div>
      </div>

      <!-- Nav -->
      <nav class="sidebar__nav">
        <ul class="nav-list">
          <li v-for="item in navigationItems" :key="item.label">

            <template v-if="item.items">
              <div class="nav-group-label">
                <i :class="item.icon" class="nav-icon"></i>
                <span class="nav-text">{{ item.label }}</span>
              </div>
              <ul class="nav-sublist">
                <li v-for="sub in item.items" :key="sub.label">
                  <a
                    class="nav-link nav-link--sub"
                    :class="{ 'nav-link--active': route.path === sub.route }"
                    @click="sub.command?.()"
                    v-tooltip.right="isCollapsedOnly ? sub.label : undefined"
                  >
                    <span class="nav-indicator"></span>
                    <i :class="sub.icon" class="nav-icon"></i>
                    <span class="nav-text">{{ sub.label }}</span>
                  </a>
                </li>
              </ul>
            </template>

            <template v-else>
              <a
                class="nav-link"
                :class="{ 'nav-link--active': route.path === item.route }"
                @click="item.command?.()"
                v-tooltip.right="isCollapsedOnly ? item.label : undefined"
              >
                <span class="nav-indicator"></span>
                <i :class="item.icon" class="nav-icon"></i>
                <span class="nav-text">{{ item.label }}</span>
                <span v-if="item.badge" class="nav-badge">{{ item.badge }}</span>
              </a>
            </template>

          </li>
        </ul>
      </nav>

      <!-- User footer -->
      <div class="sidebar__footer">
        <div class="user-card">
          <div class="user-avatar">{{ userInitials }}</div>
          <div class="user-info">
            <span class="user-name">{{ userName }}</span>
            <span class="user-role">{{ userRoleLabel }}</span>
          </div>
          <button
            class="logout-btn"
            @click="handleLogout"
            v-tooltip.right="isCollapsedOnly ? 'Déconnexion' : undefined"
            title="Déconnexion"
          >
            <i class="pi pi-sign-out"></i>
          </button>
        </div>
      </div>
    </aside>

    <!-- Main -->
    <main class="main" :style="mainStyle">

      <!-- Mobile backdrop -->
      <div
        v-if="sidebarVisible && !isDesktop"
        class="backdrop"
        @click="sidebarVisible = false"
      />

      <!-- Topbar -->
      <header class="topbar">
        <div class="topbar__left">
          <button class="menu-btn" @click="sidebarVisible = !sidebarVisible" style="display: none" ref="menuBtnRef">
            <i class="pi pi-bars"></i>
          </button>
          <span class="page-title">{{ pageTitle }}</span>
        </div>
        <div class="topbar__right">
          <div class="clock">
            <i class="pi pi-clock"></i>
            <span>{{ currentTime }}</span>
          </div>
        </div>
      </header>

      <div class="main__content">
        <slot />
      </div>
    </main>

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

const SIDEBAR_FULL = '15rem';
const SIDEBAR_COLLAPSED = '4rem';

const authStore = useAuthStore();
const route = useRoute();
const { isSuperAdmin, isAdmin, hasRole } = useUserRole();
const menuBtnRef = ref<HTMLElement | null>(null);

const sidebarVisible = ref(false);
const sidebarCollapsed = ref(true);
const sidebarHovered = ref(false);
const isDesktop = ref(false);
const currentTime = ref('');
const unreadMessagesCount = ref(0);
let timeInterval: ReturnType<typeof setInterval> | null = null;
let messagesInterval: ReturnType<typeof setInterval> | null = null;

const isCollapsedOnly = computed(() => sidebarCollapsed.value && isDesktop.value && !sidebarHovered.value);

// Reactive margin avoids CSS sibling selector issues in scoped styles
const mainStyle = computed(() => {
  if (!isDesktop.value) return { marginLeft: '0' };
  return { marginLeft: isCollapsedOnly.value ? SIDEBAR_COLLAPSED : SIDEBAR_FULL };
});

const closeSidebarOnMobile = () => {
  if (window.innerWidth < 1024) sidebarVisible.value = false;
};

const handleSidebarMouseEnter = () => {
  if (window.innerWidth >= 1024 && sidebarCollapsed.value) sidebarHovered.value = true;
};
const handleSidebarMouseLeave = () => {
  sidebarHovered.value = false;
};

const navigationItems = computed<MenuItem[]>(() => {
  const items: MenuItem[] = [
    { label: 'Tableau de bord', icon: 'pi pi-home', route: '/dashboard', command: () => { navigateTo('/dashboard'); closeSidebarOnMobile(); } },
    { label: 'Calendrier', icon: 'pi pi-calendar', route: '/dashboard/calendar', command: () => { navigateTo('/dashboard/calendar'); closeSidebarOnMobile(); } },
  ];

  if (isAdmin.value) {
    items.push({ label: 'Mon équipe', icon: 'pi pi-users', route: '/dashboard/my-team', command: () => { navigateTo('/dashboard/my-team'); closeSidebarOnMobile(); } });
  }

  if (hasRole(AppUserRole.VIEW_MESSAGE)) {
    const msg: MenuItem = {
      label: 'Messages',
      icon: 'pi pi-envelope',
      route: '/dashboard/messages',
      command: () => { navigateTo('/dashboard/messages'); closeSidebarOnMobile(); }
    };
    if (unreadMessagesCount.value > 0) {
      msg.badge = String(unreadMessagesCount.value);
    }
    items.push(msg);
  }

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
  let found = navigationItems.value.find(i => i.route === route.path);
  if (!found) {
    for (const item of navigationItems.value) {
      if (item.items) {
        found = item.items.find(s => s.route === route.path);
        if (found) break;
      }
    }
  }
  return found?.label || 'Tableau de bord';
});

const userName = computed(() => authStore.user?.email?.split('@')[0] ?? 'Utilisateur');
const userInitials = computed(() => userName.value.slice(0, 2).toUpperCase());
const userRoleLabel = computed(() => {
  if (isSuperAdmin.value) return 'Super Admin';
  if (isAdmin.value) return 'Administrateur';
  return 'Membre';
});

const handleLogout = () => authStore.logout();

const handleResize = () => {
  isDesktop.value = window.innerWidth >= 1024;
  sidebarVisible.value = isDesktop.value;
  if (menuBtnRef.value) {
    menuBtnRef.value.style.display = isDesktop.value ? 'none' : 'flex';
  }
};

const updateTime = () => {
  currentTime.value = new Date().toLocaleTimeString('fr-FR', {
    hour: '2-digit', minute: '2-digit', second: '2-digit'
  });
};

const fetchUnreadMessages = async () => {
  if (!hasRole(AppUserRole.VIEW_MESSAGE)) return;
  try {
    const repo = new ContactMessageRepository();
    const result = await repo.countUnread();
    unreadMessagesCount.value = result.count;
  } catch {}
};

onMounted(async () => {
  isDesktop.value = window.innerWidth >= 1024;
  sidebarVisible.value = isDesktop.value;
  if (menuBtnRef.value) {
    menuBtnRef.value.style.display = isDesktop.value ? 'none' : 'flex';
  }
  window.addEventListener('resize', handleResize);
  updateTime();
  timeInterval = setInterval(updateTime, 1000);
  await fetchUnreadMessages();
  messagesInterval = setInterval(fetchUnreadMessages, 30000);
});

onUnmounted(() => {
  window.removeEventListener('resize', handleResize);
  if (timeInterval) clearInterval(timeInterval);
  if (messagesInterval) clearInterval(messagesInterval);
});
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap');

/* ── Shell ───────────────────────────────────────── */
.app-shell {
  display: flex;
  min-height: 100vh;
  background: var(--p-surface-ground);
  font-family: 'Sora', sans-serif;
}

/* ── Sidebar ─────────────────────────────────────── */
.sidebar {
  position: fixed;
  left: 0; top: 0; bottom: 0;
  width: 15rem;
  background: var(--p-surface-card);
  border-right: 1px solid var(--p-surface-border);
  display: flex;
  flex-direction: column;
  z-index: 100;
  transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1),
              transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  overflow: hidden;
}

/* ── Brand ───────────────────────────────────────── */
.sidebar__brand {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1.125rem 1rem;
  border-bottom: 1px solid var(--p-surface-border);
  flex-shrink: 0;
  overflow: hidden;
}

.brand-orb {
  width: 2.25rem;
  height: 2.25rem;
  min-width: 2.25rem;
  background: var(--p-primary-color);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
}

.brand-text {
  display: flex;
  flex-direction: column;
  line-height: 1.25;
  overflow: hidden;
  white-space: nowrap;
  transition: opacity 0.2s ease;
}

.brand-name {
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--p-text-color);
  letter-spacing: -0.01em;
}

.brand-sub {
  font-size: 0.6875rem;
  font-weight: 400;
  color: var(--p-text-muted-color);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

/* ── Nav ─────────────────────────────────────────── */
.sidebar__nav {
  flex: 1;
  padding: 0.75rem 0.5rem;
  overflow-y: auto;
  overflow-x: hidden;
  scrollbar-width: none;
}
.sidebar__nav::-webkit-scrollbar { display: none; }

.nav-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.nav-link {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5625rem 0.75rem;
  border-radius: 8px;
  color: var(--p-text-muted-color);
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
  white-space: nowrap;
  overflow: hidden;
  user-select: none;
}

.nav-link:hover {
  background: var(--p-surface-hover);
  color: var(--p-text-color);
}

.nav-link--active {
  background: var(--p-primary-50, #eff6ff);
  color: var(--p-primary-color);
}

.nav-link--active .nav-indicator {
  opacity: 1;
  transform: translateY(-50%) scaleY(1);
}

.nav-indicator {
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%) scaleY(0);
  width: 3px;
  height: 1.25rem;
  background: var(--p-primary-color);
  border-radius: 0 3px 3px 0;
  opacity: 0;
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.nav-icon {
  font-size: 0.9375rem;
  width: 1.125rem;
  min-width: 1.125rem;
  text-align: center;
  flex-shrink: 0;
}

.nav-text {
  font-size: 0.875rem;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  transition: opacity 0.2s ease;
}

.nav-badge {
  margin-left: auto;
  background: #ef4444;
  color: white;
  font-size: 0.6875rem;
  font-weight: 700;
  font-family: 'JetBrains Mono', monospace;
  padding: 0.1rem 0.4rem;
  border-radius: 999px;
  min-width: 1.25rem;
  text-align: center;
  flex-shrink: 0;
}

.nav-group-label {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.875rem 0.75rem 0.375rem;
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--p-text-muted-color);
  text-transform: uppercase;
  letter-spacing: 0.07em;
  overflow: hidden;
  white-space: nowrap;
}

.nav-sublist {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.nav-link--sub {
  padding-left: 1rem;
}

/* ── Footer ──────────────────────────────────────── */
.sidebar__footer {
  padding: 0.75rem 0.5rem;
  border-top: 1px solid var(--p-surface-border);
  flex-shrink: 0;
}

.user-card {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem 0.5rem;
  border-radius: 8px;
  overflow: hidden;
  transition: background 0.15s ease;
}

.user-card:hover {
  background: var(--p-surface-hover);
}

.user-avatar {
  width: 2rem;
  height: 2rem;
  min-width: 2rem;
  border-radius: 8px;
  background: var(--p-primary-color);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  flex-shrink: 0;
}

.user-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  line-height: 1.3;
  min-width: 0;
  overflow: hidden;
  white-space: nowrap;
  transition: opacity 0.2s ease;
}

.user-name {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--p-text-color);
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-role {
  font-size: 0.6875rem;
  color: var(--p-text-muted-color);
}

.logout-btn {
  width: 1.75rem;
  height: 1.75rem;
  min-width: 1.75rem;
  border: none;
  background: transparent;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--p-text-muted-color);
  cursor: pointer;
  transition: all 0.15s ease;
  font-size: 0.875rem;
  flex-shrink: 0;
  overflow: hidden;
  transition: opacity 0.2s ease, width 0.2s ease;
}

.logout-btn:hover {
  background: #fee2e2;
  color: #ef4444;
}

/* ── Collapsed ───────────────────────────────────── */
@media (min-width: 1024px) {
  .sidebar--collapsed:not(.sidebar--hovered) {
    width: 4rem;
  }

  .sidebar--collapsed:not(.sidebar--hovered) .brand-text,
  .sidebar--collapsed:not(.sidebar--hovered) .nav-text,
  .sidebar--collapsed:not(.sidebar--hovered) .nav-badge,
  .sidebar--collapsed:not(.sidebar--hovered) .nav-group-label .nav-text,
  .sidebar--collapsed:not(.sidebar--hovered) .user-info,
  .sidebar--collapsed:not(.sidebar--hovered) .logout-btn {
    opacity: 0;
    width: 0;
    overflow: hidden;
  }

  .sidebar--collapsed:not(.sidebar--hovered) .nav-link {
    justify-content: center;
    padding: 0.5625rem;
  }

  .sidebar--collapsed:not(.sidebar--hovered) .nav-link--sub {
    padding-left: 0.5625rem;
  }

  .sidebar--collapsed:not(.sidebar--hovered) .nav-group-label {
    justify-content: center;
    padding: 0.875rem 0 0.375rem;
  }

  .sidebar--collapsed:not(.sidebar--hovered) .user-card {
    justify-content: center;
  }

  .sidebar--hovered {
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.08);
    z-index: 101;
  }
}

/* ── Main ────────────────────────────────────────── */
.main {
  flex: 1;
  display: flex;
  flex-direction: column;
  transition: margin-left 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  min-width: 0;
  min-height: 100vh;
}

/* ── Topbar ──────────────────────────────────────── */
.topbar {
  height: 3.75rem;
  background: var(--p-surface-card);
  border-bottom: 1px solid var(--p-surface-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 1.5rem;
  position: sticky;
  top: 0;
  z-index: 50;
  flex-shrink: 0;
}

.topbar__left {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.menu-btn {
  width: 2.25rem;
  height: 2.25rem;
  border: 1px solid var(--p-surface-border);
  border-radius: 8px;
  background: transparent;
  cursor: pointer;
  align-items: center;
  justify-content: center;
  color: var(--p-text-muted-color);
  font-size: 0.875rem;
  transition: all 0.15s ease;
}
.menu-btn:hover {
  background: var(--p-surface-hover);
}

.page-title {
  font-family: 'Sora', sans-serif;
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--p-text-color);
  letter-spacing: -0.01em;
}

.topbar__right {
  display: flex;
  align-items: center;
}

.clock {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: var(--p-surface-ground);
  border: 1px solid var(--p-surface-border);
  border-radius: 8px;
  padding: 0.375rem 0.75rem;
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--p-text-muted-color);
}

.clock i {
  font-size: 0.75rem;
}

/* ── Content ─────────────────────────────────────── */
.main__content {
  flex: 1;
  padding: 1.5rem;
}

/* ── Backdrop ────────────────────────────────────── */
.backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(2px);
  z-index: 99;
  animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to   { opacity: 1; }
}

/* ── Mobile ──────────────────────────────────────── */
@media (max-width: 1023px) {
  .sidebar {
    transform: translateX(-100%);
  }
  .sidebar--open {
    transform: translateX(0);
    box-shadow: 4px 0 24px rgba(0, 0, 0, 0.12);
  }
}
</style>
