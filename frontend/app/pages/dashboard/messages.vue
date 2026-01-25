<template>
  <div class="messages-page">
    <div class="page-header">
      <div class="header-stats" v-if="!loading">
        <span class="stat unread" v-if="unreadCount > 0">
          <i class="pi pi-envelope"></i>
          {{ unreadCount }} non lu{{ unreadCount > 1 ? 's' : '' }}
        </span>
        <span class="stat total">
          <i class="pi pi-inbox"></i>
          {{ allMessages.length }} message{{ allMessages.length > 1 ? 's' : '' }}
        </span>
      </div>
    </div>

    <SkeletonLoader v-if="loading" type="list" />

    <template v-else>
      <TabView v-model:activeIndex="activeTab">
        <TabPanel>
          <template #header>
            <span class="tab-header">
              <i class="pi pi-envelope"></i>
              Non lus
              <Badge v-if="unreadMessages.length > 0" :value="unreadMessages.length" severity="danger" />
            </span>
          </template>
          <MessagesList
            :messages="unreadMessages"
            :can-confirm="canConfirm"
            @mark-as-read="handleMarkAsRead"
          />
        </TabPanel>
        <TabPanel>
          <template #header>
            <span class="tab-header">
              <i class="pi pi-check-circle"></i>
              Lus
            </span>
          </template>
          <MessagesList
            :messages="readMessages"
            :can-confirm="false"
          />
        </TabPanel>
      </TabView>
    </template>
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
  layout: 'dashboard'
});

const { hasRole } = useUserRole();
const repository = new ContactMessageRepository();
const loading = ref(true);
const activeTab = ref(0);
const allMessages = ref<ContactMessage[]>([]);

const canViewMessages = computed(() => hasRole(AppUserRole.VIEW_MESSAGE));
const canConfirm = computed(() => hasRole(AppUserRole.CONFIRM_MESSAGE));

const unreadMessages = computed(() => allMessages.value.filter(m => !m.isRead));
const readMessages = computed(() => allMessages.value.filter(m => m.isRead));
const unreadCount = computed(() => unreadMessages.value.length);

const handleMarkAsRead = async (message: ContactMessage) => {
  const updated = await repository.markAsRead(message.id);
  const index = allMessages.value.findIndex(m => m.id === message.id);
  if (index !== -1) {
    allMessages.value[index] = updated;
  }
};

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

.stat.unread {
  background: #fef2f2;
  color: #dc2626;
}

.stat i {
  font-size: 1rem;
}

.tab-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.tab-header :deep(.p-badge) {
  margin-left: 0.25rem;
}
</style>
