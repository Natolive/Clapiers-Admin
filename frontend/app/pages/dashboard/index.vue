<template>
    <div class="dash">

        <div v-if="pending" class="dash__loading">
            <i class="pi pi-spin pi-spinner" />
        </div>

        <template v-else-if="stats">

            <!-- ── Scoreboard (signature, cohérent avec le rail) ── -->
            <header class="hero">
                <div class="hero__head">
                    <h1 class="hero__title">Tableau de bord</h1>
                    <p class="hero__sub">Vue d’ensemble du club</p>
                </div>

                <div class="hero__board" role="group" aria-label="Chiffres clés du club">
                    <div class="score">
                        <span class="score__num">{{ stats.members.total }}</span>
                        <span class="score__lbl">Licenciés</span>
                    </div>
                    <span class="score__sep" />
                    <div class="score">
                        <span class="score__num">{{ stats.teams.total }}</span>
                        <span class="score__lbl">Équipes</span>
                    </div>
                    <span class="score__sep" />
                    <div class="score">
                        <span class="score__num">{{ stats.games.upcoming }}</span>
                        <span class="score__lbl">Matchs à venir</span>
                    </div>
                </div>
            </header>

            <!-- ── KPI : un par domaine, cliquable ── -->
            <section class="kpi-grid">
                <NuxtLink to="/dashboard/settings/license-requests" class="kpi" :class="{ 'kpi--alert': pendingRequests > 0 }">
                    <div class="kpi__icon kpi__icon--accent"><i class="pi pi-inbox" /></div>
                    <div class="kpi__body">
                        <span class="kpi__value">{{ pendingRequests }}</span>
                        <span class="kpi__label">Demandes à traiter</span>
                    </div>
                    <span class="kpi__sub">{{ stats.licenses.total }} demande{{ stats.licenses.total > 1 ? 's' : '' }} au total</span>
                </NuxtLink>

                <NuxtLink to="/dashboard/settings/members" class="kpi">
                    <div class="kpi__icon kpi__icon--green"><i class="pi pi-check-circle" /></div>
                    <div class="kpi__body">
                        <span class="kpi__value">{{ stats.members.withLicense }}</span>
                        <span class="kpi__label">Licence payée</span>
                    </div>
                    <span class="kpi__sub kpi__sub--green">{{ licenseRate }}% des licenciés</span>
                </NuxtLink>

                <NuxtLink to="/dashboard/settings/members" class="kpi">
                    <div class="kpi__icon kpi__icon--orange"><i class="pi pi-exclamation-triangle" /></div>
                    <div class="kpi__body">
                        <span class="kpi__value">{{ stats.members.withoutLicense }}</span>
                        <span class="kpi__label">Sans licence</span>
                    </div>
                    <span class="kpi__sub kpi__sub--orange">{{ 100 - licenseRate }}% des licenciés</span>
                </NuxtLink>

                <NuxtLink to="/dashboard/settings/users" class="kpi">
                    <div class="kpi__icon kpi__icon--navy"><i class="pi pi-user" /></div>
                    <div class="kpi__body">
                        <span class="kpi__value">{{ stats.users.total }}</span>
                        <span class="kpi__label">Utilisateurs</span>
                    </div>
                    <span class="kpi__sub">accès à l’administration</span>
                </NuxtLink>

                <NuxtLink to="/dashboard/messages" class="kpi">
                    <div class="kpi__icon kpi__icon--blue"><i class="pi pi-envelope" /></div>
                    <div class="kpi__body">
                        <span class="kpi__value">{{ stats.messages.total }}</span>
                        <span class="kpi__label">Messages</span>
                    </div>
                    <span class="kpi__sub">via le formulaire de contact</span>
                </NuxtLink>

                <NuxtLink to="/dashboard/settings/members" class="kpi">
                    <div class="kpi__icon kpi__icon--accent"><i class="pi pi-user-plus" /></div>
                    <div class="kpi__body">
                        <span class="kpi__value">{{ stats.members.createdAt.newThisSeason }}</span>
                        <span class="kpi__label">Nouveaux cette saison</span>
                    </div>
                    <span class="kpi__sub">sur {{ stats.members.total }} licenciés</span>
                </NuxtLink>
            </section>

            <!-- ── Charts ── -->
            <section class="charts">
                <article class="card">
                    <h2 class="card__title">Répartition des licences</h2>
                    <div class="chart chart--donut">
                        <Chart type="doughnut" :data="licenseChartData" :options="donutOptions" />
                    </div>
                    <div class="legend">
                        <span class="legend__item"><span class="dot" style="background:#22c55e" />Payée ({{ stats.members.withLicense }})</span>
                        <span class="legend__item"><span class="dot" style="background:#f97316" />Non payée ({{ stats.members.withoutLicense }})</span>
                    </div>
                </article>

                <article class="card">
                    <h2 class="card__title">Répartition par sexe</h2>
                    <div class="chart chart--donut">
                        <Chart type="doughnut" :data="genderChartData" :options="donutOptions" />
                    </div>
                    <div class="legend">
                        <span class="legend__item"><span class="dot" style="background:#3b82f6" />Hommes ({{ stats.members.byGender.male }})</span>
                        <span class="legend__item"><span class="dot" style="background:#ec4899" />Femmes ({{ stats.members.byGender.female }})</span>
                        <span v-if="stats.members.byGender.other" class="legend__item"><span class="dot" style="background:#a855f7" />Autre ({{ stats.members.byGender.other }})</span>
                    </div>
                </article>

                <!-- ── Cycle de vie des demandes de licence ── -->
                <article class="card">
                    <div class="card__head">
                        <h2 class="card__title">Demandes de licence par statut</h2>
                        <span class="badge">{{ stats.licenses.total }} au total</span>
                    </div>
                    <div class="chart chart--hbar">
                        <Chart type="bar" :data="licenseStatusChartData" :options="hbarOptions" />
                    </div>
                </article>

                <!-- ── Âge ── -->
                <article class="card">
                    <div class="card__head">
                        <h2 class="card__title">Répartition par âge</h2>
                        <div class="badges">
                            <span class="badge">Moy. {{ stats.members.age.average }}</span>
                            <span class="badge">Min. {{ stats.members.age.min }}</span>
                            <span class="badge">Max. {{ stats.members.age.max }}</span>
                        </div>
                    </div>
                    <div class="chart chart--bar">
                        <Chart type="bar" :data="ageChartData" :options="barOptions" />
                    </div>
                </article>

                <!-- ── Inscriptions (pleine largeur) ── -->
                <article class="card card--wide">
                    <h2 class="card__title">Inscriptions — saison {{ season || '—' }}</h2>
                    <div class="chart chart--bar">
                        <Chart type="bar" :data="inscriptionsChartData" :options="barOptions" />
                    </div>
                </article>
            </section>

            <!-- ── Hub : accès à tous les modules ── -->
            <section class="hub">
                <h2 class="section-title">Accès rapide</h2>
                <div class="hub-grid">
                    <NuxtLink v-for="l in quickLinks" :key="l.route" :to="l.route!" class="hub-card">
                        <i :class="l.icon" class="hub-card__icon" />
                        <span class="hub-card__label">{{ l.label }}</span>
                        <i class="pi pi-arrow-right hub-card__go" />
                    </NuxtLink>
                </div>
            </section>

        </template>
    </div>
</template>

<script setup lang="ts">
import { StatsRepository } from '~/repository/stats-repository';
import { AppUserRole } from '~/types/entity/AppUser';

definePageMeta({
    middleware: 'auth-middleware',
    layout: 'dashboard',
    requiredRoles: [AppUserRole.SUPER_ADMIN],
    redirectTo: '/dashboard/calendar'
});
useHead({ title: 'Tableau de bord' });

const { isSuperAdmin } = useUserRole();
const { selected: season, load: loadSeasons } = useSeasonFilter();
onMounted(loadSeasons);

// Hub « Accès rapide » : réutilise la nav (rôles + icônes + routes) pour couvrir
// chaque module, aplatie en liens simples (hors auto-lien vers ce tableau).
const { navigationItems } = useDashboardNav();
const quickLinks = computed(() =>
    navigationItems.value
        .flatMap(i => i.items ?? [i])
        .filter(i => i.route && i.route !== '/dashboard')
);

const statsRepository = new StatsRepository();
const { data: stats, pending } = await useAsyncData('dashboard-stats', () =>
    isSuperAdmin.value ? statsRepository.getDashboard(season.value || undefined) : Promise.resolve(null),
    { watch: [season] }
);

const licenseRate = computed(() => {
    if (!stats.value || stats.value.members.total === 0) return 0;
    return Math.round((stats.value.members.withLicense / stats.value.members.total) * 100);
});

// Demandes en attente d'action admin (soumises, pas encore validées/refusées).
const pendingRequests = computed(() => stats.value?.licenses.byStatus.soumise ?? 0);

// ── Chart data (couleurs validées : voir palette du club) ──
const DATA_ORANGE = '#dd7233'; // orange club profond — série unique, PASS clair + sombre
const TICK = '#94a3b8';               // gris moyen lisible sur fond clair ET sombre
const GRID = 'rgba(148,163,184,0.18)';

// Cycle de vie d'une demande, dans l'ordre.
const LICENSE_STATUSES: { key: string; label: string }[] = [
    { key: 'soumise', label: 'Soumises' },
    { key: 'validee', label: 'Validées' },
    { key: 'en_paiement', label: 'En paiement' },
    { key: 'payee', label: 'Payées' },
    { key: 'refusee', label: 'Refusées' },
    { key: 'remboursee', label: 'Remboursées' },
];

const licenseChartData = computed(() => ({
    labels: ['Licence payée', 'Sans licence'],
    datasets: [{
        data: [stats.value?.members.withLicense ?? 0, stats.value?.members.withoutLicense ?? 0],
        backgroundColor: ['#22c55e', '#f97316'],
        hoverBackgroundColor: ['#16a34a', '#ea580c'],
        borderWidth: 0,
    }],
}));

const genderChartData = computed(() => {
    const g = stats.value?.members.byGender;
    const labels = ['Hommes', 'Femmes'];
    const data = [g?.male ?? 0, g?.female ?? 0];
    const bg = ['#3b82f6', '#ec4899'];
    if (g?.other) { labels.push('Autre'); data.push(g.other); bg.push('#a855f7'); }
    return { labels, datasets: [{ data, backgroundColor: bg, hoverBackgroundColor: bg, borderWidth: 0 }] };
});

const licenseStatusChartData = computed(() => {
    const by = stats.value?.licenses.byStatus ?? {};
    return {
        labels: LICENSE_STATUSES.map(s => s.label),
        datasets: [{
            label: 'Demandes',
            data: LICENSE_STATUSES.map(s => by[s.key] ?? 0),
            backgroundColor: DATA_ORANGE,
            borderRadius: 4,
        }],
    };
});

const ageChartData = computed(() => {
    const byRange = stats.value?.members.age.byRange ?? {};
    return {
        labels: Object.keys(byRange),
        datasets: [{
            label: 'Licenciés',
            data: Object.values(byRange),
            backgroundColor: DATA_ORANGE,
            borderRadius: 4,
        }],
    };
});

const inscriptionsChartData = computed(() => {
    // Les 12 mois de la saison : septembre (année de début) → août.
    const startYear = Number((season.value || '').split('-')[0]) || new Date().getFullYear();
    const months: string[] = [];
    for (let i = 0; i < 12; i++) {
        const d = new Date(startYear, 8 + i, 1);
        months.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
    }
    const map = new Map((stats.value?.members.createdAt.byMonth ?? []).map(r => [r.month, r.total]));
    return {
        labels: months.map(m => {
            const [y, mo] = m.split('-');
            return new Date(Number(y), Number(mo) - 1, 1).toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' });
        }),
        datasets: [{
            label: 'Inscrits',
            data: months.map(m => map.get(m) ?? 0),
            backgroundColor: DATA_ORANGE,
            borderRadius: 4,
        }],
    };
});

// ── Chart options ──
const donutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (ctx: any) => ` ${ctx.label} : ${ctx.parsed}` } },
    },
    cutout: '70%',
};

const barOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        x: { grid: { display: false }, ticks: { color: TICK } },
        y: { beginAtZero: true, ticks: { precision: 0, color: TICK }, grid: { color: GRID } },
    },
};

// Barres horizontales (statuts) : catégories en Y, magnitude en X.
const hbarOptions = {
    indexAxis: 'y' as const,
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        x: { beginAtZero: true, ticks: { precision: 0, color: TICK }, grid: { color: GRID } },
        y: { grid: { display: false }, ticks: { color: TICK } },
    },
};
</script>

<style scoped>
.dash {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.dash__loading {
    display: flex;
    justify-content: center;
    padding: 4rem;
    font-size: 2rem;
    color: var(--p-primary-color);
}

/* ── Scoreboard hero ─────────────────────────────── */
.hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    flex-wrap: wrap;
    padding: 1.5rem 1.75rem;
    border-radius: 16px;
    color: #fff;
    background:
        radial-gradient(120% 90% at 100% 0%, rgba(244, 162, 97, 0.20) 0%, transparent 55%),
        linear-gradient(135deg, #1e3a5f 0%, #142942 100%);
    box-shadow: 0 10px 30px -12px rgba(20, 41, 66, 0.55);
    overflow: hidden;
}

.hero__title {
    margin: 0.35rem 0 0.15rem;
    font-size: 1.6rem;
    font-weight: 700;
    letter-spacing: -0.02em;
}

.hero__sub {
    margin: 0;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.62);
}

.hero__board {
    display: flex;
    align-items: center;
    gap: 1.25rem;
}

.score {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 4.5rem;
}

.score__num {
    font-family: 'JetBrains Mono', monospace;
    font-size: 2.1rem;
    font-weight: 700;
    line-height: 1;
    color: #fff;
}

.score__lbl {
    margin-top: 0.35rem;
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: rgba(255, 255, 255, 0.55);
    white-space: nowrap;
}

.score__sep {
    width: 1px;
    align-self: stretch;
    background: rgba(255, 255, 255, 0.14);
}

/* ── KPI ─────────────────────────────────────────── */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    gap: 1rem;
}

.kpi {
    background: var(--p-surface-card);
    border: 1px solid var(--p-surface-border);
    border-radius: 14px;
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    color: inherit;
    text-decoration: none;
    transition: border-color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
}

.kpi:hover {
    border-color: color-mix(in srgb, #f4a261 55%, var(--p-surface-border));
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -12px rgba(20, 41, 66, 0.4);
}

.kpi--alert {
    border-color: color-mix(in srgb, #dd7233 45%, var(--p-surface-border));
    background:
        radial-gradient(120% 100% at 100% 0%, color-mix(in srgb, #dd7233 8%, transparent) 0%, transparent 60%),
        var(--p-surface-card);
}

.kpi__icon {
    width: 42px;
    height: 42px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    margin-bottom: 0.15rem;
}

.kpi__icon--blue   { background: color-mix(in srgb, #3b82f6 14%, transparent); color: #3b82f6; }
.kpi__icon--green  { background: color-mix(in srgb, #22c55e 14%, transparent); color: #16a34a; }
.kpi__icon--orange { background: color-mix(in srgb, #f97316 14%, transparent); color: #ea580c; }
.kpi__icon--accent { background: color-mix(in srgb, #dd7233 16%, transparent); color: #dd7233; }
.kpi__icon--navy   { background: color-mix(in srgb, #1e3a5f 14%, transparent); color: #1e3a5f; }

:root[data-theme="dark"] .kpi__icon--navy { color: #7ea6d6; }

.kpi__body { display: flex; flex-direction: column; gap: 0.1rem; }

.kpi__value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 1.9rem;
    font-weight: 700;
    line-height: 1;
    color: var(--p-text-color);
}

.kpi__label {
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--p-text-muted-color);
}

.kpi__sub { font-size: 0.74rem; color: var(--p-text-muted-color); }
.kpi__sub--green  { color: #16a34a; }
.kpi__sub--orange { color: #ea580c; }

/* ── Cards / charts ──────────────────────────────── */
.charts {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.card {
    background: var(--p-surface-card);
    border: 1px solid var(--p-surface-border);
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
}

.card--wide { grid-column: 1 / -1; }

.card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 0.75rem;
}

.card__title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--p-text-color);
    margin: 0 0 0.9rem;
}

.card__head .card__title { margin: 0; }

.badges { display: flex; gap: 0.4rem; flex-wrap: wrap; }

.badge {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.72rem;
    font-weight: 500;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    background: color-mix(in srgb, #dd7233 12%, transparent);
    color: #dd7233;
}

.chart { position: relative; }
.chart--donut { height: 150px; display: flex; justify-content: center; }
.chart--bar { height: 160px; }
.chart--hbar { height: 170px; }
.card--wide .chart--bar { height: 180px; }

.legend {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
    margin-top: 0.75rem;
    font-size: 0.78rem;
    color: var(--p-text-muted-color);
}

.legend__item { display: inline-flex; align-items: center; gap: 0.4rem; }
.dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }

/* ── Hub « Accès rapide » ────────────────────────── */
.section-title {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--p-text-muted-color);
    margin: 0.5rem 0 0.9rem;
}

.hub-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.75rem;
}

.hub-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border-radius: 12px;
    background: var(--p-surface-card);
    border: 1px solid var(--p-surface-border);
    color: var(--p-text-color);
    text-decoration: none;
    transition: border-color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
}

.hub-card:hover {
    border-color: color-mix(in srgb, #f4a261 55%, var(--p-surface-border));
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -12px rgba(20, 41, 66, 0.4);
}

.hub-card__icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.95rem;
    color: #1e3a5f;
    background: color-mix(in srgb, #1e3a5f 10%, transparent);
}

:root[data-theme="dark"] .hub-card__icon { color: #7ea6d6; }

.hub-card__label {
    flex: 1;
    font-size: 0.86rem;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hub-card__go {
    font-size: 0.75rem;
    color: var(--p-text-muted-color);
    opacity: 0;
    transform: translateX(-4px);
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.hub-card:hover .hub-card__go {
    opacity: 1;
    transform: translateX(0);
    color: #f4a261;
}

/* ── Responsive ──────────────────────────────────── */
@media (max-width: 768px) {
    .hero { padding: 1.25rem; }
    .hero__board { width: 100%; justify-content: space-between; gap: 0.5rem; }
    .charts { grid-template-columns: 1fr; }
    .score__num { font-size: 1.7rem; }
    .kpi__value { font-size: 1.5rem; }
}

@media (max-width: 480px) {
    .kpi-grid { grid-template-columns: 1fr; }
}

@media (prefers-reduced-motion: reduce) {
    .hub-card, .hub-card__go { transition: none; }
    .hub-card:hover { transform: none; }
}
</style>
