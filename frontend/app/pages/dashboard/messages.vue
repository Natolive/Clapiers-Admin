<template>
  <div class="messages-page">

    <!-- Header: search + total -->
    <div class="page-header">
      <div class="search-box">
        <i class="pi pi-search search-icon"></i>
        <input
          v-model="search"
          type="text"
          class="search-input"
          placeholder="Rechercher par nom ou prénom..."
          aria-label="Rechercher un message par nom ou prénom"
        />
        <button v-if="search" class="search-clear" aria-label="Effacer la recherche" @click="search = ''">
          <i class="pi pi-times"></i>
        </button>
      </div>

      <span v-if="!loading" class="stat">
        <i class="pi pi-inbox"></i>
        {{ total }} message{{ total > 1 ? 's' : '' }}
      </span>
    </div>

    <SkeletonLoader v-if="loading" type="list" />

    <template v-else>
      <MessagesList
        :messages="messages"
        :empty-label="emptyLabel"
      />

      <!-- Infinite scroll sentinel -->
      <div ref="sentinel" class="scroll-sentinel" aria-hidden="true">
        <i v-if="loadingMore" class="pi pi-spin pi-spinner"></i>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import SkeletonLoader from '~/components/common/skeleton/SkeletonLoader.vue';
import MessagesList from '~/components/messages/MessagesList.vue';
import { AppUserRole } from '~/types/entity/AppUser';

definePageMeta({
  middleware: 'auth-middleware',
  layout: 'dashboard',
  requiredRoles: [AppUserRole.VIEW_MESSAGE],
  redirectTo: '/dashboard/calendar'
});

useHead({ title: 'Messages' });

const { messages, total, search, loading, loadingMore, loadFirstPage, loadMore } = useContactMessages();

const sentinel = ref<HTMLElement | null>(null);
const { recheck } = useInfiniteScroll(sentinel, loadMore);

// If the appended page doesn't push the sentinel off-screen (short search
// results, tall viewport), re-trigger loading until the screen is filled
watch(() => messages.value.length, () => nextTick(recheck), { flush: 'post' });

const emptyLabel = computed(() =>
  search.value ? `Aucun message pour « ${search.value.trim()} »` : 'Aucun message'
);

onMounted(loadFirstPage);
</script>

<style scoped>
.messages-page {
  padding: 0;
}

/* ── Header ──────────────────────────────────────── */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
  animation: slide-down 0.3s ease;
}

@keyframes slide-down {
  from {
    opacity: 0;
    transform: translateY(-8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* ── Search ──────────────────────────────────────── */
.search-box {
  position: relative;
  display: flex;
  align-items: center;
  flex: 1;
  max-width: 22rem;
}

.search-icon {
  position: absolute;
  left: 0.875rem;
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
  pointer-events: none;
}

.search-input {
  width: 100%;
  padding: 0.625rem 2.5rem;
  border: 1px solid var(--p-surface-border);
  border-radius: 8px;
  background: var(--p-surface-card);
  font: inherit;
  font-size: 0.875rem;
  color: var(--p-text-color);
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.search-input::placeholder {
  color: var(--p-text-muted-color);
}

.search-input:focus {
  outline: none;
  border-color: var(--p-primary-color);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--p-primary-color) 15%, transparent);
}

.search-clear {
  position: absolute;
  right: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  border: none;
  border-radius: 6px;
  background: transparent;
  color: var(--p-text-muted-color);
  font-size: 0.75rem;
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}

.search-clear:hover {
  background: var(--p-surface-hover);
  color: var(--p-text-color);
}

/* ── Stat ────────────────────────────────────────── */
.stat {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: var(--p-surface-100);
  border-radius: 8px;
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
  white-space: nowrap;
  flex-shrink: 0;
}

.stat i {
  font-size: 1rem;
}

/* ── Infinite scroll sentinel ────────────────────── */
.scroll-sentinel {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 3rem;
  color: var(--p-text-muted-color);
  font-size: 1.125rem;
}

/* ── Mobile ──────────────────────────────────────── */
@media (max-width: 640px) {
  .page-header {
    margin-bottom: 1rem;
  }

  .search-box {
    max-width: none;
  }

  .stat {
    padding: 0.375rem 0.625rem;
    font-size: 0.8125rem;
  }
}
</style>
