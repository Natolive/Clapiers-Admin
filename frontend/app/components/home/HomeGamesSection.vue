<template>
  <section id="matchs" class="games-section">
    <div class="games-container">

      <div class="section-header">
        <span class="section-badge">🏠 Domicile</span>
        <h2 class="section-title">Prochains matchs</h2>
        <p class="section-sub">Venez supporter nos équipes au gymnase !</p>
      </div>

      <!-- Loading -->
      <div v-if="pending" class="games-loading">
        <div class="loading-dot" v-for="n in 3" :key="n"></div>
      </div>

      <!-- Empty -->
      <div v-else-if="!games.length" class="games-empty">
        <span class="empty-icon">📅</span>
        <p>Aucun match à domicile de prévu pour le moment.</p>
      </div>

      <!-- List -->
      <div v-else class="games-grid">
        <div v-for="game in games" :key="game.id" class="game-card">
          <div class="game-date">
            <span class="game-day">{{ formatDay(game.date) }}</span>
            <span class="game-month">{{ formatMonth(game.date) }}</span>
          </div>
          <div class="game-info">
            <span class="game-team">{{ game.team.name }}</span>
            <span class="game-vs">vs <strong>{{ game.opponent }}</strong></span>
            <div class="game-meta">
              <span v-if="game.meetingTime" class="game-meta-item">
                <i class="pi pi-clock"></i> {{ game.meetingTime }}
              </span>
              <span v-if="game.location" class="game-meta-item">
                <i class="pi pi-map-marker"></i> {{ game.location }}
              </span>
            </div>
          </div>
          <div class="game-badge">🏠</div>
        </div>
      </div>

    </div>
  </section>
</template>

<script setup lang="ts">
const { data: games, pending } = await useFetch<any[]>(
  `${useRuntimeConfig().public.apiBase}/public/home-games`,
  { default: () => [] }
);

const formatDay = (dateStr: string) =>
  new Date(dateStr + 'T00:00:00').toLocaleDateString('fr-FR', { day: 'numeric' });

const formatMonth = (dateStr: string) =>
  new Date(dateStr + 'T00:00:00').toLocaleDateString('fr-FR', { month: 'short' }).replace('.', '');
</script>

<style scoped>
.games-section {
  padding: 5rem 2rem;
  background: var(--club-light, #f8fafc);
}

.games-container {
  max-width: 900px;
  margin: 0 auto;
}

/* Header */
.section-header {
  text-align: center;
  margin-bottom: 3rem;
}

.section-badge {
  display: inline-block;
  background: var(--club-primary, #1e3a5f);
  color: white;
  font-size: 0.8rem;
  font-weight: 600;
  padding: 0.35rem 0.9rem;
  border-radius: 50px;
  letter-spacing: 0.04em;
  margin-bottom: 1rem;
}

.section-title {
  font-size: 2rem;
  font-weight: 800;
  color: var(--club-dark, #0f1e35);
  margin: 0 0 0.5rem;
}

.section-sub {
  color: var(--club-gray, #64748b);
  font-size: 1rem;
  margin: 0;
}

/* Loading */
.games-loading {
  display: flex;
  justify-content: center;
  gap: 0.5rem;
  padding: 3rem;
}

.loading-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--club-primary, #1e3a5f);
  opacity: 0.3;
  animation: dot-pulse 1.2s ease-in-out infinite;
}

.loading-dot:nth-child(2) { animation-delay: 0.2s; }
.loading-dot:nth-child(3) { animation-delay: 0.4s; }

@keyframes dot-pulse {
  0%, 80%, 100% { opacity: 0.3; transform: scale(1); }
  40% { opacity: 1; transform: scale(1.3); }
}

/* Empty */
.games-empty {
  text-align: center;
  padding: 3rem;
  color: var(--club-gray, #64748b);
}

.empty-icon { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; }

/* Grid */
.games-grid {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.game-card {
  display: flex;
  align-items: center;
  gap: 1.25rem;
  background: white;
  border-radius: 14px;
  padding: 1.25rem 1.5rem;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
  border: 1px solid rgba(0, 0, 0, 0.05);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.game-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.09);
}

.game-date {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-width: 3rem;
  background: var(--club-primary, #1e3a5f);
  color: white;
  border-radius: 10px;
  padding: 0.5rem 0.75rem;
  text-align: center;
}

.game-day {
  font-size: 1.5rem;
  font-weight: 800;
  line-height: 1;
}

.game-month {
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  opacity: 0.8;
  margin-top: 2px;
}

.game-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  min-width: 0;
}

.game-team {
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--club-secondary, #d23c3c);
}

.game-vs {
  font-size: 0.95rem;
  color: var(--club-dark, #0f1e35);
}

.game-meta {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-top: 0.15rem;
}

.game-meta-item {
  font-size: 0.78rem;
  color: var(--club-gray, #64748b);
  display: flex;
  align-items: center;
  gap: 0.3rem;
}

.game-meta-item i { font-size: 0.7rem; }

.game-badge {
  font-size: 1.4rem;
  flex-shrink: 0;
}

@media (max-width: 480px) {
  .games-section { padding: 3.5rem 1rem; }
  .game-card { padding: 1rem; gap: 1rem; }
  .section-title { font-size: 1.5rem; }
}
</style>
