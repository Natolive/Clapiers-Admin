<template>
  <div class="gh-item" :class="`gh-item--${entry.action}`">
    <span class="gh-action" :class="`gh-action--${entry.action}`">
      <i :class="actionIcon"></i>
      {{ actionLabel }}
    </span>

    <div class="gh-main">
      <div class="gh-head">
        <span class="gh-title">{{ entry.opponent }}</span>
        <span class="gh-team"><i class="pi pi-users"></i>{{ entry.teamName }}</span>
        <span class="gh-gamedate"><i class="pi pi-calendar"></i>{{ formattedGameDate }}</span>
      </div>

      <ul class="gh-changes">
        <li v-for="change in changeRows" :key="change.field" class="gh-change">
          <span class="gh-field">{{ change.label }}</span>
          <template v-if="change.isDiff">
            <span class="gh-old">{{ change.old }}</span>
            <i class="pi pi-arrow-right gh-arrow"></i>
            <span class="gh-new">{{ change.new }}</span>
          </template>
          <span v-else class="gh-value">{{ change.new }}</span>
        </li>
      </ul>

      <div class="gh-foot">
        <span class="gh-actor">
          <i class="pi pi-user"></i>{{ entry.actorEmail ?? 'Système' }}
        </span>
        <span class="gh-when">{{ formattedWhen }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { GameHistoryAction, type GameHistory } from '~/types/entity/GameHistory';

const props = defineProps<{ entry: GameHistory }>();

const ACTION_META: Record<GameHistoryAction, { label: string; icon: string }> = {
  [GameHistoryAction.CREATED]: { label: 'Création', icon: 'pi pi-plus-circle' },
  [GameHistoryAction.UPDATED]: { label: 'Modification', icon: 'pi pi-pencil' },
  [GameHistoryAction.DELETED]: { label: 'Suppression', icon: 'pi pi-trash' },
};

const FIELD_LABELS: Record<string, string> = {
  opponent: 'Adversaire',
  date: 'Date',
  meetingTime: 'Heure de RDV',
  venue: 'Lieu',
  location: 'Salle',
  team: 'Équipe',
};

const actionLabel = computed(() => ACTION_META[props.entry.action].label);
const actionIcon = computed(() => ACTION_META[props.entry.action].icon);

const formatValue = (field: string, value: unknown): string => {
  if (value === null || value === '' || value === undefined) return '—';
  if (field === 'venue') return value === 'home' ? 'Domicile' : 'Extérieur';
  return String(value);
};

type ChangeRow = { field: string; label: string; isDiff: boolean; old: string; new: string };

/** Normalizes both shapes: snapshot {field: value} and diff {field: {old, new}}. */
const changeRows = computed<ChangeRow[]>(() =>
  Object.entries(props.entry.changes).map(([field, raw]) => {
    const label = FIELD_LABELS[field] ?? field;
    const isDiff = raw !== null && typeof raw === 'object' && 'old' in (raw as object) && 'new' in (raw as object);

    if (isDiff) {
      const diff = raw as { old: unknown; new: unknown };
      return { field, label, isDiff: true, old: formatValue(field, diff.old), new: formatValue(field, diff.new) };
    }
    return { field, label, isDiff: false, old: '', new: formatValue(field, raw) };
  })
);

const formattedGameDate = computed(() =>
  new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' })
    .format(new Date(props.entry.gameDate))
);

const formattedWhen = computed(() =>
  new Intl.DateTimeFormat('fr-FR', {
    day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
  }).format(new Date(props.entry.createdAt))
);
</script>

<style scoped>
.gh-item {
  display: flex;
  gap: 1rem;
  background: var(--p-surface-card);
  border: 1px solid var(--p-surface-border);
  border-left: 3px solid var(--p-surface-border);
  border-radius: 10px;
  padding: 1rem;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}

.gh-item:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.gh-item--created { border-left-color: var(--p-green-500); }
.gh-item--updated { border-left-color: var(--p-blue-500); }
.gh-item--deleted { border-left-color: var(--p-red-500); }

.gh-action {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  align-self: flex-start;
  padding: 0.25rem 0.625rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
}

.gh-action--created { background: color-mix(in srgb, var(--p-green-500) 15%, transparent); color: var(--p-green-600); }
.gh-action--updated { background: color-mix(in srgb, var(--p-blue-500) 15%, transparent); color: var(--p-blue-600); }
.gh-action--deleted { background: color-mix(in srgb, var(--p-red-500) 15%, transparent); color: var(--p-red-600); }

.gh-main { flex: 1; min-width: 0; }

.gh-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 1rem;
  margin-bottom: 0.5rem;
}

.gh-title { font-weight: 600; color: var(--p-text-color); }

.gh-team, .gh-gamedate {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.8125rem;
  color: var(--p-text-muted-color);
}

.gh-changes {
  list-style: none;
  margin: 0 0 0.5rem;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.gh-change {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8125rem;
}

.gh-field {
  font-weight: 500;
  color: var(--p-text-muted-color);
  min-width: 6rem;
}

.gh-old { color: var(--p-red-600); text-decoration: line-through; }
.gh-new, .gh-value { color: var(--p-text-color); font-weight: 500; }
.gh-arrow { font-size: 0.7rem; color: var(--p-text-muted-color); }

.gh-foot {
  display: flex;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.5rem;
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
}

.gh-actor { display: inline-flex; align-items: center; gap: 0.375rem; }

@media (max-width: 640px) {
  .gh-item { flex-direction: column; gap: 0.625rem; }
  .gh-field { min-width: 5rem; }
}
</style>
