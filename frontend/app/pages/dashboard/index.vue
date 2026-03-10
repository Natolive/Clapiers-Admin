<template>
    <div class="dashboard">

        <!-- Super Admin only -->
        <template v-if="isSuperAdmin">
            <div v-if="pending" class="dashboard-loading">
                <i class="pi pi-spin pi-spinner" style="font-size:2rem; color: var(--p-primary-color)" />
            </div>

            <template v-else-if="stats">
                <!-- KPI Cards -->
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-icon kpi-icon--blue"><i class="pi pi-users" /></div>
                        <div class="kpi-body">
                            <span class="kpi-value">{{ stats.members.total }}</span>
                            <span class="kpi-label">Licenciés total</span>
                        </div>
                        <div class="kpi-sub">{{ stats.teams.total }} équipe{{ stats.teams.total > 1 ? 's' : '' }}</div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-icon kpi-icon--green"><i class="pi pi-check-circle" /></div>
                        <div class="kpi-body">
                            <span class="kpi-value">{{ stats.members.withLicense }}</span>
                            <span class="kpi-label">Licence payée</span>
                        </div>
                        <div class="kpi-sub kpi-sub--green">{{ licenseRate }}% des membres</div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-icon kpi-icon--orange"><i class="pi pi-exclamation-triangle" /></div>
                        <div class="kpi-body">
                            <span class="kpi-value">{{ stats.members.withoutLicense }}</span>
                            <span class="kpi-label">Sans licence</span>
                        </div>
                        <div class="kpi-sub kpi-sub--orange">{{ 100 - licenseRate }}% des membres</div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-icon kpi-icon--purple"><i class="pi pi-user-plus" /></div>
                        <div class="kpi-body">
                            <span class="kpi-value">{{ stats.members.createdAt.newThisMonth }}</span>
                            <span class="kpi-label">Inscrits ce mois</span>
                        </div>
                        <div class="kpi-sub">{{ stats.members.createdAt.newThisYear }} cette année</div>
                    </div>
                </div>

                <!-- Row 1 : licences + sexe -->
                <div class="charts-grid charts-grid--2">
                    <div class="chart-card">
                        <h3 class="chart-title">Répartition des licences</h3>
                        <div class="chart-wrap chart-wrap--donut">
                            <Chart type="doughnut" :data="licenseChartData" :options="donutOptions" />
                        </div>
                        <div class="chart-legend">
                            <span class="legend-dot legend-dot--green" />
                            <span>Payée ({{ stats.members.withLicense }})</span>
                            <span class="legend-dot legend-dot--orange" style="margin-left:1rem" />
                            <span>Non payée ({{ stats.members.withoutLicense }})</span>
                        </div>
                    </div>

                    <div class="chart-card">
                        <h3 class="chart-title">Répartition par sexe</h3>
                        <div class="chart-wrap chart-wrap--donut">
                            <Chart type="doughnut" :data="genderChartData" :options="donutOptions" />
                        </div>
                        <div class="chart-legend">
                            <span class="legend-dot" style="background:#3b82f6" />
                            <span>Hommes ({{ stats.members.byGender.male }})</span>
                            <span class="legend-dot" style="background:#ec4899; margin-left:1rem" />
                            <span>Femmes ({{ stats.members.byGender.female }})</span>
                            <template v-if="stats.members.byGender.other">
                                <span class="legend-dot" style="background:#a855f7; margin-left:1rem" />
                                <span>Autre ({{ stats.members.byGender.other }})</span>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Row 2 : âge + âge moyen info -->
                <div class="chart-card">
                    <div class="chart-title-row">
                        <h3 class="chart-title">Répartition par âge</h3>
                        <div class="age-badges">
                            <span class="age-badge">Moy. {{ stats.members.age.average }} ans</span>
                            <span class="age-badge">Min. {{ stats.members.age.min }} ans</span>
                            <span class="age-badge">Max. {{ stats.members.age.max }} ans</span>
                        </div>
                    </div>
                    <div class="chart-wrap chart-wrap--bar">
                        <Chart type="bar" :data="ageChartData" :options="ageBarOptions" />
                    </div>
                </div>

                <!-- Row 3 : inscriptions par mois -->
                <div class="chart-card">
                    <h3 class="chart-title">Inscriptions — 12 derniers mois</h3>
                    <div class="chart-wrap chart-wrap--bar">
                        <Chart type="bar" :data="inscriptionsChartData" :options="inscriptionsBarOptions" />
                    </div>
                </div>
            </template>
        </template>

    </div>
</template>

<script setup lang="ts">
import { StatsRepository } from '~/repository/stats-repository';

definePageMeta({ middleware: 'auth-middleware', layout: 'dashboard' });
useHead({ title: 'Tableau de bord' });

const { isSuperAdmin, isAdmin } = useUserRole();

if (isAdmin.value && !isSuperAdmin.value) {
    await navigateTo('/dashboard/calendar');
}

const statsRepository = new StatsRepository();
const { data: stats, pending } = await useAsyncData('dashboard-stats', () =>
    isSuperAdmin.value ? statsRepository.getDashboard() : Promise.resolve(null)
);

// ── Computed ──────────────────────────────────────────

const licenseRate = computed(() => {
    if (!stats.value || stats.value.members.total === 0) return 0;
    return Math.round((stats.value.members.withLicense / stats.value.members.total) * 100);
});

// ── Chart data ────────────────────────────────────────

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

const ageChartData = computed(() => {
    const byRange = stats.value?.members.age.byRange ?? {};
    return {
        labels: Object.keys(byRange),
        datasets: [{
            label: 'Membres',
            data: Object.values(byRange),
            backgroundColor: '#3b82f6',
            hoverBackgroundColor: '#2563eb',
            borderRadius: 6,
        }],
    };
});

const inscriptionsChartData = computed(() => {
    // Générer les 12 derniers mois
    const months: string[] = [];
    const now = new Date();
    for (let i = 11; i >= 0; i--) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
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
            backgroundColor: 'color-mix(in srgb, var(--p-primary-color) 20%, transparent)',
            borderColor: 'var(--p-primary-color)',
            borderWidth: 2,
            borderRadius: 6,
        }],
    };
});

// ── Chart options ─────────────────────────────────────

const donutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (ctx: any) => ` ${ctx.label} : ${ctx.parsed}` } },
    },
    cutout: '70%',
};

const ageBarOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, ticks: { precision: 0 } },
    },
};

const inscriptionsBarOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, ticks: { precision: 0 } },
    },
};
</script>

<style scoped>
.dashboard {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.dashboard-loading {
    display: flex;
    justify-content: center;
    padding: 4rem;
}

/* KPI */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}

.kpi-card {
    background: var(--p-surface-card);
    border: 1px solid var(--p-surface-border);
    border-radius: 12px;
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.kpi-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    margin-bottom: 0.25rem;
}

.kpi-icon--blue   { background: color-mix(in srgb, #3b82f6 12%, transparent); color: #3b82f6; }
.kpi-icon--green  { background: color-mix(in srgb, #22c55e 12%, transparent); color: #22c55e; }
.kpi-icon--orange { background: color-mix(in srgb, #f97316 12%, transparent); color: #f97316; }
.kpi-icon--purple { background: color-mix(in srgb, var(--p-primary-color) 12%, transparent); color: var(--p-primary-color); }

.kpi-body { display: flex; flex-direction: column; gap: 0.15rem; }

.kpi-value {
    font-size: 2rem;
    font-weight: 800;
    line-height: 1;
    color: var(--p-text-color);
}

.kpi-label {
    font-size: 0.8rem;
    color: var(--p-text-muted-color);
    font-weight: 500;
}

.kpi-sub { font-size: 0.75rem; color: var(--p-text-muted-color); margin-top: 0.25rem; }
.kpi-sub--green  { color: #22c55e; }
.kpi-sub--orange { color: #f97316; }

/* Charts */
.charts-grid--2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.chart-card {
    background: var(--p-surface-card);
    border: 1px solid var(--p-surface-border);
    border-radius: 12px;
    padding: 1.25rem;
}

.chart-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.chart-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--p-text-color);
    margin: 0 0 1rem;
}

.chart-title-row .chart-title { margin: 0; }

.age-badges {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.age-badge {
    font-size: 0.75rem;
    font-weight: 500;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    background: color-mix(in srgb, var(--p-primary-color) 10%, transparent);
    color: var(--p-primary-color);
}

.chart-wrap { height: 220px; position: relative; }
.chart-wrap--donut { height: 180px; display: flex; justify-content: center; }
.chart-wrap--bar   { height: 200px; }

.chart-legend {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-top: 0.75rem;
    font-size: 0.78rem;
    color: var(--p-text-muted-color);
    justify-content: center;
    flex-wrap: wrap;
}

.legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.legend-dot--green  { background: #22c55e; }
.legend-dot--orange { background: #f97316; }

/* Responsive */
@media (max-width: 1100px) {
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .kpi-grid { grid-template-columns: 1fr 1fr; gap: 0.75rem; }
    .charts-grid--2 { grid-template-columns: 1fr; }
    .kpi-value { font-size: 1.5rem; }
}

@media (max-width: 480px) {
    .kpi-grid { grid-template-columns: 1fr; }
}
</style>
