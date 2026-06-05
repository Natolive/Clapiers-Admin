<template>
  <div class="messages-page">
    <div class="page-header">
      <div class="header-stats" v-if="!loading">
        <span class="stat total">
          <i class="pi pi-inbox"></i>
          {{ allMessages.length }} message{{ allMessages.length > 1 ? 's' : '' }}
        </span>
      </div>
    </div>

    <SkeletonLoader v-if="loading" type="list" />

    <MessagesList v-else :messages="allMessages" />
  </div>
</template>

<script setup lang="ts">
import SkeletonLoader from '~/components/common/skeleton/SkeletonLoader.vue';
import MessagesList from '~/components/messages/MessagesList.vue';
import { ContactMessageRepository } from '~/repository/contact-message-repository';
import type { ContactMessage } from '~/types/entity/ContactMessage';
import { AppUserRole } from '~/types/entity/AppUser';

definePageMeta({
  middleware: 'auth-middleware',
  layout: 'dashboard',
  requiredRoles: [AppUserRole.VIEW_MESSAGE],
  redirectTo: '/dashboard/calendar'
});

useHead({ title: 'Messages' });

const { hasRole } = useUserRole();
const repository = new ContactMessageRepository();
const loading = ref(true);
const allMessages = ref<ContactMessage[]>([]);

const canViewMessages = computed(() => hasRole(AppUserRole.VIEW_MESSAGE));

onMounted(async () => {
  if (!canViewMessages.value) {
    await navigateTo('/dashboard');
    return;
  }

  try {
    allMessages.value = await repository.getAll();
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.messages-page {
  padding: 0;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.page-header h1 {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 600;
}

.header-stats {
  display: flex;
  gap: 1rem;
}

.stat {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: var(--p-surface-100);
  border-radius: 8px;
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
}

.stat i {
  font-size: 1rem;
}
</style>
