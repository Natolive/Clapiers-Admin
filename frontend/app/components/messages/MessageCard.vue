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
        <!-- Date -->
        <div class="flex align-items-center">
          <span class="text-sm text-color-secondary">
            <i class="pi pi-clock mr-1"></i>
            {{ formattedDate }}
          </span>
        </div>

        <!-- Message content -->
        <div class="surface-ground border-round p-3">
          <p class="m-0 line-height-3 white-space-pre-wrap">{{ message.message }}</p>
        </div>
      </div>
    </template>
  </Card>
</template>

<script setup lang="ts">
import type { ContactMessage } from '~/types/entity/ContactMessage';

const props = defineProps<{
  message: ContactMessage;
}>();

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
</script>
