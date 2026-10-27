<template>
  <div class="season">
    <p v-if="loading" class="season__empty">Chargement…</p>
    <p v-else-if="!weeks.length" class="season__empty">Aucun match à venir.</p>

    <template v-else>
      <MultiSelect
        v-model="focusTeams"
        class="season__teams"
        :options="teams"
        option-label="name"
        option-value="id"
        placeholder="Toutes les équipes"
        size="small"
        display="chip"
        :max-selected-labels="3"
        :selected-items-label="'{0} équipes'"
        show-clear
      />

      <div class="board">
        <div class="board__head">
          <span />
          <span v-for="day in nightDays" :key="day">{{ dayName(day) }} · à domicile</span>
        </div>

        <template v-for="week in weeks" :key="week.key">
          <div v-if="week.month" class="board__month">{{ week.month }}</div>

          <div :class="['board__row', { 'board__row--now': week.isCurrent }]">
            <span class="board__week">
              <span class="board__week-num">S{{ week.number }}</span>
              <span class="board__week-date">{{ week.label }}</span>
            </span>

            <div
              v-for="night in week.nights"
              :key="night.date"
              :class="['night', `night--${night.state}`]"
            >
              <div class="night__head">
                <span class="night__day">{{ shortDate(night.date) }}</span>
                <span class="night__badge">{{ badge(night) }}</span>
              </div>

              <p v-if="night.closure" class="night__closed">
                <i class="pi pi-lock" /> Salle fermée{{ night.closure.reason ? ` — ${night.closure.reason}` : '' }}
                <template v-if="night.games.length"> : matchs à déplacer</template>
              </p>

              <ol v-if="!night.closure || night.games.length" class="night__courts">
                <li
                  v-for="(slot, i) in night.slots"
                  :key="i"
                  :class="['court', {
                    'court--free': !slot,
                    'court--extra': i >= MAX_HOME_GAMES_PER_DAY,
                    'court--focus': slot && isFocused(slot),
                    'court--dim': focusTeams.length > 0 && !(slot && isFocused(slot)),
                  }]"
                >
                  <template v-if="slot">
                    <span class="court__team">{{ slot.team.name }}</span>
                    <span class="court__opp">vs {{ slot.opponent }}</span>
                    <span class="court__time">{{ slot.meetingTime }}</span>
                  </template>
                  <span v-else class="court__team">Libre</span>
                </li>
              </ol>
            </div>

            <ul v-if="week.away.length" class="away">
              <li
                v-for="game in week.away"
                :key="game.id"
                :class="{
                  'away--focus': isFocused(game),
                  'away--dim': focusTeams.length > 0 && !isFocused(game),
                }"
              >
                <span class="away__tag">Ext.</span>
                <span class="away__day">{{ shortDate(game.date) }}</span>
                <span class="away__time">{{ game.meetingTime }}</span>
                <strong>{{ game.team.name }}</strong>
                <span class="away__opp">@ {{ game.opponent }}</span>
                <a
                  v-if="game.location"
                  class="away__loc"
                  :href="mapUrl(game.location)"
                  target="_blank"
                  rel="noopener"
                >{{ game.location }} ↗</a>
              </li>
            </ul>
          </div>
        </template>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { Game } from '~/types/entity/Game';
import type { Team } from '~/types/entity/Team';
import type { SalleClosure } from '~/types/entity/SalleClosure';
import { GameVenue } from '~/types/enum/GameVenue';
import { MAX_HOME_GAMES_PER_DAY, HOME_DAYS, findClosureForDateKey, toDateKey } from '~/utils/calendarRules';

const props = withDefaults(defineProps<{
  games: Game[];
  teams: Team[];
  closures?: SalleClosure[];
  loading?: boolean;
}>(), { closures: () => [] });

type Night = {
  date: string;
  games: Game[];
  /** One entry per court to draw: a game, or null for a free court */
  slots: (Game | null)[];
  closure: SalleClosure | null;
  state: 'free' | 'full' | 'over' | 'closed' | 'conflict' | 'past';
};

/** Highlighted teams (empty = no highlight); the rest is dimmed, never hidden */
const focusTeams = ref<number[]>([]);
const isFocused = (game: Game) => focusTeams.value.includes(game.team.id);

// 'YYYY-MM-DD' parsed as a local date (new Date('YYYY-MM-DD') would be UTC)
const toDate = (key: string) => {
  const [y, m, d] = key.split('-').map(Number);
  return new Date(y!, m! - 1, d!);
};
const addDays = (key: string, n: number) => {
  const date = toDate(key);
  date.setDate(date.getDate() + n);
  return toDateKey(date);
};
const mondayOf = (key: string) => addDays(key, -((toDate(key).getDay() + 6) % 7));
const fmt = (key: string, opts: Intl.DateTimeFormatOptions) => toDate(key).toLocaleDateString('fr-FR', opts);
const mapUrl = (location: string) => `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(location)}`;
const shortDate = (key: string) => fmt(key, { weekday: 'short', day: 'numeric', month: 'short' });

/** ISO week number, the one printed on club schedules */
const isoWeek = (key: string) => {
  const d = toDate(key);
  d.setDate(d.getDate() + 3 - ((d.getDay() + 6) % 7));
  const firstThursday = new Date(d.getFullYear(), 0, 4);
  return 1 + Math.round(((d.getTime() - firstThursday.getTime()) / 86400000 - 3 + ((firstThursday.getDay() + 6) % 7)) / 7);
};

// Columns: the regular home nights, Monday-first order
const nightDays = [...HOME_DAYS].sort((a, b) => ((a + 6) % 7) - ((b + 6) % 7));
const dayName = (day: number) => ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'][day];

const weeks = computed(() => {
  const today = toDateKey(new Date());
  const homeByDate = new Map<string, Game[]>();
  const away: Game[] = [];
  let last = today;
  for (const g of props.games) {
    if (g.date > last) last = g.date;
    if (g.venue === GameVenue.HOME) homeByDate.set(g.date, [...(homeByDate.get(g.date) ?? []), g]);
    else away.push(g);
  }
  away.sort((a, b) => a.date.localeCompare(b.date) || (a.meetingTime ?? '').localeCompare(b.meetingTime ?? ''));
  for (const list of homeByDate.values()) list.sort((a, b) => (a.meetingTime ?? '').localeCompare(b.meetingTime ?? ''));
  if (!props.games.length) return [];

  const rows = [];
  let previousMonth = '';
  // Every week up to the last game, empty ones included: holidays and gaps must show
  for (let monday = mondayOf(today); monday <= last; monday = addDays(monday, 7)) {
    const nights: Night[] = nightDays.map(day => {
      const date = addDays(monday, (day + 6) % 7);
      const games = homeByDate.get(date) ?? [];
      const closure = findClosureForDateKey(props.closures, date);
      const state = date < today ? 'past'
        : closure ? (games.length ? 'conflict' : 'closed')
        : games.length > MAX_HOME_GAMES_PER_DAY ? 'over'
        : games.length === MAX_HOME_GAMES_PER_DAY ? 'full' : 'free';
      const slots: (Game | null)[] = [...games];
      while (slots.length < MAX_HOME_GAMES_PER_DAY) slots.push(null);
      return { date, games, slots, closure, state };
    });

    const month = fmt(monday, { month: 'long', year: 'numeric' });
    rows.push({
      key: monday,
      number: isoWeek(monday),
      label: `${fmt(monday, { day: 'numeric', month: 'short' })} – ${fmt(addDays(monday, 6), { day: 'numeric', month: 'short' })}`,
      month: month !== previousMonth ? month : null,
      isCurrent: monday === mondayOf(today),
      nights,
      away: away.filter(g => g.date >= monday && g.date <= addDays(monday, 6)),
    });
    previousMonth = month;
  }
  return rows;
});

const plural = (n: number, one: string, many: string) => (n > 1 ? many : one);

const badge = (night: Night) => {
  const free = MAX_HOME_GAMES_PER_DAY - night.games.length;
  return {
    past: 'Passé',
    closed: 'Fermée',
    conflict: 'Conflit',
    over: `Surbooké ${night.games.length}/${MAX_HOME_GAMES_PER_DAY}`,
    full: 'Complet',
    free: `${free} ${plural(free, 'libre', 'libres')}`,
  }[night.state];
};
</script>

<style scoped>
.season {
  --free: #16a34a;
  --full: #d97706;
  --over: #dc2626;
  --closed: #6b7280;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.season__empty {
  color: #6b7280;
  text-align: center;
  padding: 2rem 0;
}

/* ── Team focus ──────────────────────────────── */
.season__teams {
  align-self: flex-start;
  min-width: 16rem;
  max-width: 100%;
}

/* ── Board ───────────────────────────────────── */
.board {
  --cols: 8.5rem repeat(2, minmax(0, 1fr));
  display: flex;
  flex-direction: column;
}
.board__head,
.board__row {
  display: grid;
  grid-template-columns: var(--cols);
  gap: 0.75rem;
}
.board__head {
  padding: 0.5rem 0;
  background: #fff;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--club-accent);
}

.board__month {
  margin: 1.25rem 0 0.5rem;
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--club-primary);
  text-transform: capitalize;
}

.board__row {
  padding: 0.3rem 0;
}
.board__row--now .board__week-num {
  background: var(--club-secondary);
  color: #fff;
}

.board__week {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  padding-top: 0.5rem;
}
.board__week-num {
  align-self: flex-start;
  padding: 0.05rem 0.4rem;
  border-radius: 6px;
  background: #f3f4f6;
  color: #374151;
  font-size: 0.75rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}
.board__week-date {
  font-size: 0.8rem;
  color: #6b7280;
  white-space: nowrap;
}

/* ── Night: a gym evening, one line per court ── */
.night {
  --state: var(--free);
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  padding: 0.5rem 0.6rem;
  border: 1px solid #e5e7eb;
  border-top: 3px solid var(--state);
  border-radius: 8px;
  background: #fff;
}
.night--full { --state: var(--full); }
.night--over,
.night--conflict {
  --state: var(--over);
  background: color-mix(in srgb, var(--over) 5%, #fff);
}
.night--closed {
  --state: var(--closed);
  background: repeating-linear-gradient(-45deg, #f9fafb 0 6px, #f3f4f6 6px 12px);
}
.night--past {
  --state: #d1d5db;
  opacity: 0.45;
}

.night__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.night__day {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--club-dark);
}
.night__badge {
  padding: 0.05rem 0.5rem;
  border-radius: 999px;
  background: color-mix(in srgb, var(--state) 12%, #fff);
  color: var(--state);
  font-size: 0.72rem;
  font-weight: 700;
  white-space: nowrap;
}
.night__closed {
  margin: 0;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--state);
}

.night__courts {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}
.court {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: baseline;
  gap: 0.4rem;
  padding: 0.2rem 0.4rem;
  border-radius: 5px;
  background: #f8fafc;
  font-size: 0.85rem;
  transition: opacity 0.15s;
}
.court__team {
  font-weight: 600;
  color: var(--club-dark);
  white-space: nowrap;
}
.court__opp {
  color: #6b7280;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.court__time {
  font-size: 0.75rem;
  color: #94a3b8;
  font-variant-numeric: tabular-nums;
}
.court--free {
  background: none;
  border: 1px dashed #86efac;
}
.court--free .court__team {
  font-weight: 500;
  color: var(--free);
}
.court--extra {
  background: color-mix(in srgb, var(--over) 12%, #fff);
}
.court--focus {
  background: var(--club-primary);
}
.court--focus .court__team,
.court--focus .court__opp,
.court--focus .court__time {
  color: #fff;
}
.court--dim {
  opacity: 0.35;
}

/* ── Away games: secondary, under the home nights ── */
.away {
  grid-column: 2 / -1;
  list-style: none;
  margin: 0;
  padding: 0.35rem 0.6rem;
  border-radius: 8px;
  background: #f8fafc;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}
.away li {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0 0.5rem;
  font-size: 0.8rem;
  color: #6b7280;
  transition: opacity 0.15s;
}
.away strong {
  color: #374151;
  font-weight: 600;
}
.away__tag {
  padding: 0 0.35rem;
  border-radius: 4px;
  background: #e0e7ff;
  color: #3730a3;
  font-size: 0.68rem;
  font-weight: 700;
}
.away__day {
  min-width: 5.5rem;
  font-variant-numeric: tabular-nums;
}
.away__time {
  min-width: 2.6rem;
  font-variant-numeric: tabular-nums;
}
.away__loc {
  color: inherit;
  font-size: 0.75rem;
}
.away--focus {
  font-weight: 600;
}
.away--focus strong {
  color: var(--club-primary);
}
.away--dim {
  opacity: 0.35;
}

@media (max-width: 640px) {
  .away {
    grid-column: 1 / -1;
  }
  .away__loc {
    flex-basis: 100%;
  }
  .board {
    --cols: minmax(0, 1fr) minmax(0, 1fr);
  }
  .board__head > span:first-child {
    display: none;
  }
  .board__week {
    grid-column: 1 / -1;
    flex-direction: row;
    align-items: baseline;
    gap: 0.5rem;
    padding-top: 0;
  }
  .court {
    grid-template-columns: minmax(0, 1fr);
  }
  .court__team {
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .court__opp,
  .court__time {
    display: none;
  }
}
</style>
