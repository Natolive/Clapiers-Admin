<template>
  <section id="horaires" class="schedule-section">
    <div class="container">
      <div class="section-header">
        <span class="section-label">Horaires & Lieu</span>
        <h2 class="section-title">
          Quand et où<br />
          <span class="text-accent">nous retrouver</span>
        </h2>
      </div>

      <div class="schedule-location-grid">
        <div class="schedule-card">
          <div class="schedule-header loisir">
            <i class="pi pi-calendar"></i>
            <span>Créneaux d'entraînement</span>
          </div>
          <div class="schedule-body">
            <div class="schedule-item">
              <div class="day-badge">Lun</div>
              <div class="schedule-details">
                <span class="schedule-day">Lundi</span>
                <span class="schedule-time">20h30 - 22h30</span>
              </div>
            </div>
            <div class="schedule-item">
              <div class="day-badge">Ven</div>
              <div class="schedule-details">
                <span class="schedule-day">Vendredi</span>
                <span class="schedule-time">20h30 - 22h30</span>
              </div>
            </div>
            <div class="schedule-footer">
              <i class="pi pi-info-circle"></i>
              <span>Ouvert à tous niveaux, ambiance conviviale</span>
            </div>
          </div>
        </div>

        <div class="location-card">
          <div class="location-header">
            <i class="pi pi-map-marker"></i>
            <span>Notre gymnase</span>
          </div>
          <div class="location-body">
            <div class="location-main">
              <h3 class="location-name">Gymnase Joël Abati</h3>
              <p class="location-address">
                <i class="pi pi-map"></i>
                Rue du Stade, 34830 Clapiers
              </p>
            </div>

            <div class="location-features">
              <div class="location-feature">
                <i class="pi pi-check-circle"></i>
                <span>Parking gratuit</span>
              </div>
              <div class="location-feature">
                <i class="pi pi-check-circle"></i>
                <span>Vestiaires disponibles</span>
              </div>
            </div>

            <a
              href="https://maps.google.com/?q=Gymnase+Joel+Abati+Clapiers+34830"
              target="_blank"
              rel="noopener"
              class="location-link"
            >
              <i class="pi pi-external-link"></i>
              Ouvrir dans Google Maps
            </a>
          </div>
        </div>
      </div>

    </div>
  </section>
</template>

<style scoped>
.schedule-section {
  padding: 6rem 2rem;
  background: white;
}

.container {
  max-width: 1100px;
  margin: 0 auto;
}

.section-header {
  text-align: center;
  margin-bottom: 3.5rem;
}

.text-accent {
  color: var(--club-secondary);
}

.schedule-location-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
  align-items: stretch;
}

.schedule-card,
.location-card {
  background: var(--club-light);
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid rgba(0, 0, 0, 0.05);
}

.schedule-header,
.location-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1.25rem 1.5rem;
  font-weight: 600;
  color: white;
  font-size: 1rem;
}

.schedule-header {
  background: var(--club-primary);
}

.location-header {
  background: var(--club-secondary);
}

.schedule-header i,
.location-header i {
  font-size: 1.1rem;
}

.schedule-body,
.location-body {
  padding: 1.5rem;
}

.schedule-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: white;
  border-radius: 10px;
  margin-bottom: 0.75rem;
}

.day-badge {
  width: 48px;
  height: 48px;
  background: var(--club-primary);
  color: white;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.85rem;
}

.schedule-details {
  display: flex;
  flex-direction: column;
}

.schedule-day {
  font-weight: 600;
  color: var(--club-dark);
  font-size: 1rem;
}

.schedule-time {
  color: #666;
  font-size: 0.9rem;
  margin-top: 0.15rem;
}

.schedule-footer {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: 0.5rem;
  padding-top: 1rem;
  border-top: 1px dashed rgba(0, 0, 0, 0.1);
  font-size: 0.85rem;
  color: #888;
}

.schedule-footer i {
  color: var(--club-secondary);
}

.location-main {
  margin-bottom: 1.25rem;
}

.location-name {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--club-dark);
  margin: 0 0 0.5rem;
}

.location-address {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #666;
  margin: 0;
  font-size: 0.95rem;
}

.location-address i {
  color: var(--club-secondary);
  font-size: 0.9rem;
}

.location-features {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
}

.location-feature {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
  color: #555;
}

.location-feature i {
  color: #22c55e;
  font-size: 0.9rem;
}

.location-link {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.25rem;
  background: var(--club-secondary);
  color: white;
  text-decoration: none;
  font-weight: 500;
  font-size: 0.9rem;
  border-radius: 8px;
  transition: all 0.2s ease;
}

.location-link:hover {
  background: #c5303c;
  transform: translateY(-2px);
}

.season-notice {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  margin-top: 2.5rem;
  padding: 1rem 1.5rem;
  background: rgba(30, 58, 95, 0.05);
  border-radius: 10px;
  color: var(--club-primary);
  font-size: 0.95rem;
}

.season-notice i {
  font-size: 1.1rem;
}

@media (max-width: 768px) {
  .schedule-location-grid {
    grid-template-columns: 1fr;
  }

  .schedule-section {
    padding: 4rem 1.5rem;
  }

  .season-notice {
    flex-direction: column;
    text-align: center;
    gap: 0.5rem;
  }
}
</style>
