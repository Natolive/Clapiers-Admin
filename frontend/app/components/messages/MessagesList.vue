<template>
  <div class="messages-list">
    <div v-if="messages.length === 0" class="empty-state">
      <i class="pi pi-inbox"></i>
      <p>Aucun message</p>
    </div>

    <div v-else class="messages-grid">
      <MessageCard
        v-for="message in messages"
        :key="message.id"
        :message="message"
        :can-confirm="canConfirm"
        @mark-as-read="$emit('markAsRead', message)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import MessageCard from './MessageCard.vue';
import type { ContactMessage } from '~/types/entity/ContactMessage';

defineProps<{
  messages: ContactMessage[];
  canConfirm: boolean;
}>();

defineEmits<{
  markAsRead: [message: ContactMessage];
}>();
</script>

<style scoped>
.messages-list {
  padding: 1rem 0;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  color: var(--p-text-muted-color);
}

.empty-state i {
  font-size: 3rem;
  margin-bottom: 1rem;
  opacity: 0.5;
}

.empty-state p {
  margin: 0;
  font-size: 1rem;
}

.messages-grid {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
</style>
