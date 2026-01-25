<template>
  <section id="club" class="about-section">
    <div class="container">
      <div class="section-header">
        <span class="section-label">Notre club</span>
        <h2 class="section-title">
          Un club pour tous,<br />
          <span class="text-accent">une passion commune</span>
        </h2>
        <p class="section-desc">
          Depuis plus de 25 ans, le VBC Clapiers accueille tous les passionnés
          de volleyball dans une ambiance conviviale et sportive.
        </p>
      </div>

      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon primary">
            <i class="pi pi-users"></i>
          </div>
          <h3 class="feature-title">Tous niveaux</h3>
          <p class="feature-desc">
            Débutant ou joueur confirmé, chacun trouve sa place
            et progresse à son rythme.
          </p>
        </div>

        <div class="feature-card">
          <div class="feature-icon accent">
            <i class="pi pi-heart"></i>
          </div>
          <h3 class="feature-title">100% Loisir</h3>
          <p class="feature-desc">
            Pas de pression, juste du plaisir. Venez vous défouler
            dans une ambiance détendue et bienveillante.
          </p>
        </div>

        <div class="feature-card">
          <div class="feature-icon blue">
            <i class="pi pi-map-marker"></i>
          </div>
          <h3 class="feature-title">Gymnase de qualité</h3>
          <p class="feature-desc">
            Entraînements au gymnase municipal de Clapiers,
            un équipement moderne et bien entretenu.
          </p>
        </div>

        <div class="feature-card">
          <div class="feature-icon primary">
            <i class="pi pi-calendar"></i>
          </div>
          <h3 class="feature-title">Événements réguliers</h3>
          <p class="feature-desc">
            Tournois, repas du club, sorties... La vie associative
            est au coeur de notre fonctionnement.
          </p>
        </div>

        <div class="feature-card">
          <div class="feature-icon accent">
            <i class="pi pi-users"></i>
          </div>
          <h3 class="feature-title">Convivialité</h3>
          <p class="feature-desc">
            Plus qu'un club de sport, une vraie communauté.
            L'entraide et la bonne humeur sont au rendez-vous.
          </p>
        </div>

        <div class="feature-card">
          <div class="feature-icon blue">
            <i class="pi pi-euro"></i>
          </div>
          <h3 class="feature-title">Tarifs accessibles</h3>
          <p class="feature-desc">
            Des cotisations adaptées pour rendre le volleyball
            accessible au plus grand nombre.
          </p>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.about-section {
  padding: 6rem 2rem;
  background: var(--club-light);
}

.container {
  max-width: 1200px;
  margin: 0 auto;
}

.section-header {
  text-align: center;
  margin-bottom: 4rem;
}

.section-desc {
  max-width: 600px;
  margin: 1.5rem auto 0;
  color: #666;
  font-size: 1.1rem;
  line-height: 1.7;
}

.text-accent {
  color: var(--club-secondary);
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.5rem;
}

.feature-title {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--club-dark);
  margin-bottom: 0.75rem;
}

.feature-desc {
  color: #666;
  font-size: 0.95rem;
  line-height: 1.6;
  margin: 0;
}

@media (max-width: 968px) {
  .features-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 600px) {
  .features-grid {
    grid-template-columns: 1fr;
  }

  .about-section {
    padding: 4rem 1.5rem;
  }
}
</style>
