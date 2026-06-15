<template>
  <div class="gh-list">
    <Transition name="fade" mode="out-in">
      <div v-if="entries.length === 0" class="empty-state">
        <i class="pi pi-history"></i>
        <p>{{ emptyLabel }}</p>
      </div>

      <TransitionGroup v-else name="list" tag="div" class="gh-grid" appear>
        <GameHistoryCard
          v-for="entry in entries"
          :key="entry.id"
          :entry="entry"
        />
      </TransitionGroup>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import GameHistoryCard from './GameHistoryCard.vue';
import type { GameHistory } from '~/types/entity/GameHistory';

withDefaults(defineProps<{ entries: GameHistory[]; emptyLabel?: string }>(), {
  emptyLabel: 'Aucune transaction enregistrée',
});
</script>

<style scoped>
.gh-list { padding: 0; }

.gh-grid {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
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

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.list-enter-active { transition: all 0.3s ease; }
.list-enter-from { opacity: 0; transform: translateY(8px); }
</style>
