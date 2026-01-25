<template>
  <Card class="message-card" :class="{ unread: !message.isRead }">
    <template #content>
      <div class="message-header">
        <div class="sender-info">
          <div class="sender-avatar">
            {{ initials }}
          </div>
          <div class="sender-details">
            <span class="sender-name">{{ message.firstName }} {{ message.lastName }}</span>
            <a :href="`mailto:${message.email}`" class="sender-email">{{ message.email }}</a>
          </div>
        </div>
        <div class="message-meta">
          <span class="message-date">{{ formattedDate }}</span>
          <Tag :value="message.subject" severity="info" />
        </div>
      </div>

      <!-- Read info on mobile - shown at top -->
      <div v-if="message.isRead && message.readBy" class="read-info-mobile">
        <i class="pi pi-check-circle"></i>
        <span>Lu par {{ message.readBy.email }} le {{ formattedReadDate }}</span>
      </div>

      <div class="message-body" :class="{ expanded: isExpanded }">
        <p>{{ message.message }}</p>
      </div>

      <div class="message-footer">
        <Button
          v-if="message.message.length > 200"
          :label="isExpanded ? 'Réduire' : 'Voir plus'"
          :icon="isExpanded ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
          text
          size="small"
          @click="isExpanded = !isExpanded"
        />

        <div class="message-actions">
          <div v-if="message.isRead && message.readBy" class="read-info read-info-desktop">
            <i class="pi pi-check-circle"></i>
            <span>Lu par {{ message.readBy.email }} le {{ formattedReadDate }}</span>
          </div>

          <Button
            v-if="canConfirm && !message.isRead"
            label="Marquer comme lu"
            icon="pi pi-check"
            severity="success"
            size="small"
            :loading="marking"
            @click="markAsRead"
          />
        </div>
      </div>
    </template>
  </Card>
</template>

<script setup lang="ts">
import type { ContactMessage } from '~/types/entity/ContactMessage';

const props = defineProps<{
  message: ContactMessage;
  canConfirm: boolean;
}>();

const emit = defineEmits<{
  markAsRead: [];
}>();

const isExpanded = ref(false);
const marking = ref(false);

const initials = computed(() => {
  return `${props.message.firstName.charAt(0)}${props.message.lastName.charAt(0)}`.toUpperCase();
});

const formattedDate = computed(() => {
  const date = new Date(props.message.createdAt);
  return new Intl.DateTimeFormat('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date);
});

const formattedReadDate = computed(() => {
  if (!props.message.readAt) return '';
  const date = new Date(props.message.readAt);
  return new Intl.DateTimeFormat('fr-FR', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date);
});

const markAsRead = async () => {
  marking.value = true;
  emit('markAsRead');
};
</script>

<style scoped>
.message-card {
  transition: all 0.2s ease;
}

.message-card.unread {
  border-left: 4px solid var(--p-primary-color);
  background: var(--p-surface-50);
}

.message-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.sender-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.sender-avatar {
  width: 44px;
  height: 44px;
  background: var(--p-primary-color);
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 0.9rem;
}

.sender-details {
  display: flex;
  flex-direction: column;
}

.sender-name {
  font-weight: 600;
  color: var(--p-text-color);
}

.sender-email {
  font-size: 0.85rem;
  color: var(--p-text-muted-color);
  text-decoration: none;
}

.sender-email:hover {
  text-decoration: underline;
}

.message-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.5rem;
}

.message-date {
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
}

.message-body {
  max-height: 100px;
  overflow: hidden;
  transition: max-height 0.3s ease;
}

.message-body.expanded {
  max-height: none;
}

.message-body p {
  margin: 0;
  line-height: 1.6;
  color: var(--p-text-color);
  white-space: pre-wrap;
}

.message-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--p-surface-200);
}

.message-actions {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-left: auto;
}

.read-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
}

.read-info i {
  color: #22c55e;
}

.read-info-mobile {
  display: none;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
  padding: 0.5rem 0.75rem;
  background: #f0fdf4;
  border-radius: 6px;
  margin-bottom: 1rem;
}

.read-info-mobile i {
  color: #22c55e;
}

@media (max-width: 600px) {
  .message-header {
    flex-direction: column;
    gap: 1rem;
  }

  .message-meta {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
    width: 100%;
  }

  .read-info-mobile {
    display: flex;
  }

  .read-info-desktop {
    display: none;
  }

  .message-footer {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }

  .message-actions {
    margin-left: 0;
  }
}
</style>
