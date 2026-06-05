<template>
  <div class="messages-list">
    <Transition name="fade" mode="out-in">
      <div v-if="messages.length === 0" class="empty-state">
        <i class="pi pi-inbox"></i>
        <p>{{ emptyLabel }}</p>
      </div>

      <TransitionGroup v-else name="list" tag="div" class="messages-grid" appear>
        <MessageCard
          v-for="(message, index) in messages"
          :key="message.id"
          :message="message"
          :style="{ '--stagger': `${staggerDelay(index)}ms` }"
        />
      </TransitionGroup>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import MessageCard from './MessageCard.vue';
import type { ContactMessage } from '~/types/entity/ContactMessage';

interface Props {
  messages: ContactMessage[];
  emptyLabel?: string;
}

withDefaults(defineProps<Props>(), {
  emptyLabel: 'Aucun message'
});

const STAGGER_STEP_MS = 40;
const STAGGER_MAX_ITEMS = 10;
const PAGE_SIZE = 20;

/** Staggered entrance: each batch of 20 restarts the cascade, capped at 10 items */
const staggerDelay = (index: number): number =>
  Math.min(index % PAGE_SIZE, STAGGER_MAX_ITEMS) * STAGGER_STEP_MS;
</script>

<style scoped>
.messages-list {
  padding: 0;
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
  gap: 0.5rem;
}

/* ── Animations ──────────────────────────────────── */
.list-enter-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
  transition-delay: var(--stagger, 0ms);
}

.list-enter-from {
  opacity: 0;
  transform: translateY(10px);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
