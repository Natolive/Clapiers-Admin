<template>
  <div>
  <button type="button" class="subscribe-btn" @click="visible = true">
    <i class="pi pi-calendar-plus" /> S'abonner
  </button>

  <Dialog v-model:visible="visible" modal header="S'abonner au calendrier" :style="{ width: '36rem' }" :breakpoints="{ '640px': '95vw' }">
    <p class="sub__hint">
      Les matchs s'ajoutent à votre agenda et se mettent à jour tout seuls en cas de changement.
    </p>

    <label class="sub__label" for="sub-team">Équipe</label>
    <Select
      v-model="teamId"
      input-id="sub-team"
      :options="options"
      option-label="name"
      option-value="id"
      class="sub__select"
    />

    <span id="sub-alarm" class="sub__label">Rappel avant le match</span>
    <div class="sub__alarms" role="group" aria-labelledby="sub-alarm">
      <button
        v-for="option in alarmOptions"
        :key="option.minutes"
        type="button"
        :class="['sub__alarm', { 'sub__alarm--on': alarm === option.minutes }]"
        :aria-pressed="alarm === option.minutes"
        @click="alarm = option.minutes"
      >
        <i :class="['pi', option.minutes ? 'pi-bell' : 'pi-bell-slash']" />
        {{ option.label }}
      </button>
    </div>

    <div class="sub__actions">
      <a class="sub__action" :href="googleUrl" target="_blank" rel="noopener">
        <i class="pi pi-google" /> Google Agenda
      </a>
      <a class="sub__action" :href="webcalUrl">
        <i class="pi pi-apple" /> Apple / Outlook bureau
      </a>
      <a class="sub__action" :href="outlookUrl('outlook.live.com')" target="_blank" rel="noopener">
        <i class="pi pi-microsoft" /> Outlook.com
      </a>
      <a class="sub__action" :href="outlookUrl('outlook.office.com')" target="_blank" rel="noopener">
        <i class="pi pi-microsoft" /> Office 365
      </a>
      <button type="button" class="sub__action" @click="copy">
        <i :class="copied ? 'pi pi-check' : 'pi pi-copy'" /> {{ copied ? 'Lien copié' : 'Copier le lien' }}
      </button>
    </div>
  </Dialog>
  </div>
</template>

<script setup lang="ts">
import type { Team } from '~/types/entity/Team';

const props = defineProps<{ teams: Team[] }>();

const config = useRuntimeConfig();
const visible = ref(false);
// 0 = tout le club (PrimeVue's Select treats null as "nothing selected")
const teamId = ref(0);
const copied = ref(false);

// 0 = pas de rappel
const alarm = ref(0);
const alarmOptions = [
  { minutes: 0, label: 'Aucun' },
  { minutes: 30, label: '30 min' },
  { minutes: 60, label: '1 h' },
  { minutes: 120, label: '2 h' },
  { minutes: 180, label: '3 h' },
  { minutes: 1440, label: 'La veille' },
];

const options = computed(() => [{ id: 0, name: 'Tout le club' }, ...props.teams]);

const icsUrl = computed(() => {
  const params = new URLSearchParams();
  if (teamId.value) params.set('teamId', String(teamId.value));
  if (alarm.value) params.set('alarm', String(alarm.value));
  const query = params.toString();
  return `${config.public.apiBase}/public/games.ics${query ? `?${query}` : ''}`;
});
const calendarName = computed(() =>
  `Clapiers VB${teamId.value ? ` - ${props.teams.find(t => t.id === teamId.value)?.name ?? ''}` : ''}`,
);
// webcal:// makes the OS open its calendar app in "subscribe" mode
const webcalUrl = computed(() => icsUrl.value.replace(/^https?:/, 'webcal:'));
// Outlook web needs the https URL (not webcal://)
const outlookUrl = (host: string) =>
  `https://${host}/calendar/0/addfromweb?url=${encodeURIComponent(icsUrl.value)}&name=${encodeURIComponent(calendarName.value)}`;
const googleUrl = computed(() => `https://calendar.google.com/calendar/render?cid=${encodeURIComponent(webcalUrl.value)}`);

const copy = async () => {
  await navigator.clipboard.writeText(icsUrl.value);
  copied.value = true;
  setTimeout(() => { copied.value = false; }, 2000);
};
</script>

<style scoped>
.subscribe-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.35rem 0.9rem;
  border: 1px solid rgba(255, 255, 255, 0.4);
  border-radius: 999px;
  background: transparent;
  color: #fff;
  font: inherit;
  font-size: 0.9rem;
  cursor: pointer;
}
.subscribe-btn:hover {
  background: rgba(255, 255, 255, 0.12);
}

.sub__hint {
  margin: 0 0 1rem;
  color: #6b7280;
  font-size: 0.9rem;
  line-height: 1.5;
}
.sub__label {
  display: block;
  margin-bottom: 0.35rem;
  font-size: 0.85rem;
  font-weight: 600;
}
.sub__select {
  width: 100%;
  margin-bottom: 0.9rem;
}
.sub__alarms {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}
.sub__alarm {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.4rem 0.8rem;
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  background: #fff;
  color: #4b5563;
  font: inherit;
  font-size: 0.85rem;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s, color 0.15s;
}
.sub__alarm i {
  font-size: 0.8rem;
  opacity: 0.5;
}
@media (max-width: 640px) {
  .sub__actions {
    grid-template-columns: 1fr;
  }
}
.sub__alarm:hover {
  border-color: var(--club-primary, #1e3a5f);
}
.sub__alarm--on {
  border-color: var(--club-primary, #1e3a5f);
  background: var(--club-primary, #1e3a5f);
  color: #fff;
  font-weight: 600;
}
.sub__alarm--on i {
  opacity: 1;
}

.sub__actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
  margin-top: 1.25rem;
}
.sub__action {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.65rem 0.9rem;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  color: var(--club-dark, #1a1a2e);
  font: inherit;
  font-size: 0.95rem;
  text-align: left;
  text-decoration: none;
  cursor: pointer;
}
/* "Copier le lien" sur toute la largeur, sous les applications */
.sub__action:last-child {
  grid-column: 1 / -1;
  justify-content: center;
}
.sub__action:hover {
  border-color: var(--club-primary, #1e3a5f);
  background: #f8fafc;
}
</style>
