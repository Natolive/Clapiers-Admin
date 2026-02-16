<template>
  <Card>
    <template #header>
      <div class="flex align-items-center justify-content-between p-3 pb-0">
        <div class="flex align-items-center gap-3">
          <Avatar :label="initials" shape="circle" size="large" />
          <div class="flex flex-column">
            <span class="font-semibold">{{ message.firstName }} {{ message.lastName }}</span>
            <a :href="`mailto:${message.email}`" class="text-sm text-color-secondary no-underline hover:underline">
              {{ message.email }}
            </a>
          </div>
        </div>
        <Tag :value="message.subject" severity="info" />
      </div>
    </template>

    <template #content>
      <div class="flex flex-column gap-3">
        <!-- Date and read status -->
        <div class="flex align-items-center justify-content-between">
          <span class="text-sm text-color-secondary">
            <i class="pi pi-clock mr-1"></i>
            {{ formattedDate }}
          </span>
          <Tag v-if="message.isRead" severity="success" value="Lu" icon="pi pi-check" />
          <Tag v-else severity="warn" value="Non lu" icon="pi pi-envelope" />
        </div>

        <!-- Read info -->
        <Message v-if="message.isRead && message.readBy" severity="success" :closable="false" class="m-0">
          Lu par {{ message.readBy.email }} le {{ formattedReadDate }}
        </Message>

        <!-- Message content -->
        <div class="surface-ground border-round p-3">
          <p class="m-0 line-height-3 white-space-pre-wrap">{{ message.message }}</p>
        </div>
      </div>
    </template>

    <template #footer>
      <div class="flex justify-content-end">
        <Button
          v-if="canConfirm && !message.isRead"
          label="Marquer comme lu"
          icon="pi pi-check"
          severity="success"
          :loading="marking"
          @click="markAsRead"
        />
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
