<template>
  <div class="message-item" :class="{ 'message-item--open': expanded }">

    <!-- Compact row (always visible) -->
    <button class="message-row" @click="expanded = !expanded">
      <span class="message-avatar">{{ initials }}</span>

      <span class="message-meta">
        <span class="message-name">{{ message.firstName }} {{ message.lastName }}</span>
        <span class="message-subject" :data-date="shortDate">{{ message.subject }}</span>
      </span>

      <span class="message-side">
        <span class="message-date">{{ formattedDate }}</span>
        <i class="pi pi-chevron-down message-chevron" :class="{ 'message-chevron--open': expanded }"></i>
      </span>
    </button>

    <!-- Expanded content (animated height) -->
    <div class="message-body-wrap" :class="{ 'message-body-wrap--open': expanded }">
      <div class="message-body">
        <div class="message-body__inner">
          <a :href="`mailto:${message.email}`" class="message-email">
            <i class="pi pi-envelope"></i>
            {{ message.email }}
          </a>
          <div class="message-content" v-html="sanitizedMessage"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import DOMPurify from 'dompurify';
import type { ContactMessage } from '~/types/entity/ContactMessage';

const props = defineProps<{
  message: ContactMessage;
}>();

const expanded = ref(false);

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

// Short date shown inline next to the subject on mobile
const shortDate = computed(() => {
  const date = new Date(props.message.createdAt);
  return new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' }).format(date);
});

// Interpret HTML in messages, stripped of anything dangerous (scripts, event handlers...)
const sanitizedMessage = computed(() => {
  if (!import.meta.client) return '';
  return DOMPurify.sanitize(props.message.message, { USE_PROFILES: { html: true } });
});
</script>

<style scoped>
.message-item {
  background: var(--p-surface-card);
  border: 1px solid var(--p-surface-border);
  border-radius: 10px;
  overflow: hidden;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}

.message-item:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.message-item--open {
  border-color: var(--p-primary-color);
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
}

/* ── Compact row ─────────────────────────────────── */
.message-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  padding: 0.75rem 1rem;
  border: none;
  background: transparent;
  cursor: pointer;
  text-align: left;
  font: inherit;
  color: inherit;
}

.message-row:hover {
  background: var(--p-surface-hover);
}

.message-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  min-width: 2.25rem;
  border-radius: 8px;
  background: var(--p-primary-color);
  color: white;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.03em;
}

.message-meta {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 0;
  flex: 1;
}

.message-name {
  font-weight: 600;
  font-size: 0.875rem;
  color: var(--p-text-color);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.message-subject {
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.message-side {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-shrink: 0;
}

.message-date {
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
  white-space: nowrap;
}

.message-chevron {
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
  transition: transform 0.2s ease;
}

.message-chevron--open {
  transform: rotate(180deg);
}

/* ── Expanded body (grid-rows height animation) ──── */
.message-body-wrap {
  display: grid;
  grid-template-rows: 0fr;
  transition: grid-template-rows 0.3s ease;
}

.message-body-wrap--open {
  grid-template-rows: 1fr;
}

.message-body {
  overflow: hidden;
  min-height: 0;
}

.message-body__inner {
  padding: 0 1rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.message-email {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.8125rem;
  color: var(--p-primary-color);
  text-decoration: none;
  width: fit-content;
}

.message-email:hover {
  text-decoration: underline;
}

.message-content {
  background: var(--p-surface-ground);
  border-radius: 8px;
  padding: 0.875rem 1rem;
  font-size: 0.875rem;
  line-height: 1.6;
  color: var(--p-text-color);
  white-space: pre-wrap;
  word-break: break-word;
  overflow-wrap: anywhere;
}

.message-content :deep(p) {
  margin: 0 0 0.5rem;
}

.message-content :deep(p:last-child) {
  margin-bottom: 0;
}

.message-content :deep(a) {
  color: var(--p-primary-color);
}

.message-content :deep(img) {
  max-width: 100%;
  height: auto;
}

/* ── Mobile ──────────────────────────────────────── */
@media (max-width: 640px) {
  .message-row {
    padding: 0.625rem 0.75rem;
    gap: 0.625rem;
  }

  .message-avatar {
    width: 2rem;
    height: 2rem;
    min-width: 2rem;
    font-size: 0.6875rem;
  }

  /* date moves under the subject to free horizontal space */
  .message-side {
    gap: 0.5rem;
  }

  .message-date {
    display: none;
  }

  .message-subject::after {
    content: ' · ' attr(data-date);
  }

  .message-body__inner {
    padding: 0 0.75rem 0.75rem;
  }

  .message-content {
    padding: 0.75rem;
    font-size: 0.8125rem;
  }
}
</style>
