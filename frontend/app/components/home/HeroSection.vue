<template>
  <section id="accueil" class="hero">
    <div class="hero-bg">
      <div class="bg-shape shape-1"></div>
      <div class="bg-shape shape-2"></div>
      <div class="bg-pattern"></div>
    </div>

    <div class="hero-content">
      <div class="hero-text">
        <span class="hero-badge">
          <span class="badge-dot"></span>
          Inscriptions ouvertes
        </span>

        <h1 class="home-title">
          Volleyball Club
          <span class="text-accent">Clapiers</span>
        </h1>

        <p class="home-subtitle">
          Rejoignez notre club convivial au coeur de l'Hérault.
          Dans une ambiance détendue, débutant ou confirmé,
          venez partager notre passion du volleyball loisir.
        </p>

        <div class="hero-actions">
          <a href="#contact" class="btn-primary">
            <i class="pi pi-send"></i>
            Nous rejoindre
          </a>
          <a href="#horaires" class="btn-secondary">
            <i class="pi pi-calendar"></i>
            Voir les horaires
          </a>
        </div>

        <div class="hero-stats">
          <div class="stat-item">
            <span class="stat-number">2</span>
            <span class="stat-label">Séances / semaine</span>
          </div>
          <div class="stat-divider"></div>
          <div class="stat-item">
            <span class="stat-number">2h</span>
            <span class="stat-label">De jeu</span>
          </div>
          <div class="stat-divider"></div>
          <div class="stat-item">
            <span class="stat-number">100%</span>
            <span class="stat-label">Plaisir</span>
          </div>
        </div>
      </div>

      <div class="hero-visual">
        <div class="visual-container">
          <div class="volleyball-wrapper">
            <div class="volleyball animate-float">🏐</div>
          </div>
          <div class="visual-card card-1">
            <i class="pi pi-users"></i>
            <span>Esprit d'équipe</span>
          </div>
          <div class="visual-card card-2">
            <i class="pi pi-heart"></i>
            <span>Passion</span>
          </div>
          <div class="visual-card card-3">
            <i class="pi pi-sun"></i>
            <span>Détente</span>
          </div>
        </div>
      </div>
    </div>

    <div class="scroll-indicator">
      <div class="scroll-line"></div>
      <span>Découvrir</span>
    </div>
  </section>
</template>

<style scoped>
.hero {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  justify-content: center;
  position: relative;
  overflow: hidden;
  background: var(--club-gradient);
  padding: 6rem 2rem 4rem;
}

.hero-bg {
  position: absolute;
  inset: 0;
  overflow: hidden;
}

.bg-shape {
  position: absolute;
  border-radius: 50%;
  opacity: 0.1;
}

.shape-1 {
  width: 600px;
  height: 600px;
  background: var(--club-secondary);
  top: -200px;
  right: -100px;
}

.shape-2 {
  width: 400px;
  height: 400px;
  background: var(--club-accent);
  bottom: -100px;
  left: -100px;
}

.bg-pattern {
  position: absolute;
  inset: 0;
  background-image: radial-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px);
  background-size: 40px 40px;
}

.hero-content {
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4rem;
  align-items: center;
  position: relative;
  z-index: 1;
}

.hero-text {
  color: white;
}

.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: rgba(255, 255, 255, 0.1);
  padding: 0.5rem 1rem;
  border-radius: 50px;
  font-size: 0.85rem;
  font-weight: 500;
  margin-bottom: 1.5rem;
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.badge-dot {
  width: 8px;
  height: 8px;
  background: #4ade80;
  border-radius: 50%;
  animation: pulse-soft 2s ease-in-out infinite;
}

.text-accent {
  display: block;
  color: var(--club-accent);
}

.hero-actions {
  display: flex;
  gap: 1rem;
  margin-top: 2.5rem;
  flex-wrap: wrap;
}

.hero-stats {
  display: flex;
  align-items: center;
  gap: 2rem;
  margin-top: 3rem;
  padding-top: 2rem;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.hero-stats .stat-item {
  text-align: left;
}

.hero-stats .stat-number {
  font-size: 2rem;
  font-weight: 800;
  color: white;
}

.hero-stats .stat-label {
  font-size: 0.85rem;
  color: rgba(255, 255, 255, 0.7);
  margin-top: 0.25rem;
}

.stat-divider {
  width: 1px;
  height: 40px;
  background: rgba(255, 255, 255, 0.2);
}

.hero-visual {
  display: flex;
  justify-content: center;
  align-items: center;
}

.visual-container {
  position: relative;
  width: 100%;
  max-width: 400px;
  aspect-ratio: 1;
}

.volleyball-wrapper {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.volleyball {
  font-size: 12rem;
  filter: drop-shadow(0 20px 40px rgba(0, 0, 0, 0.3));
}

.visual-card {
  position: absolute;
  background: rgba(255, 255, 255, 0.95);
  padding: 0.75rem 1rem;
  border-radius: 12px;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 600;
  font-size: 0.85rem;
  color: var(--club-dark);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
  backdrop-filter: blur(10px);
}

.visual-card i {
  color: var(--club-secondary);
}

.card-1 {
  top: 15%;
  left: 0;
  transform: rotate(-3deg);
}

.card-2 {
  top: 50%;
  right: -10%;
  transform: rotate(3deg);
}

.card-3 {
  bottom: 15%;
  left: 10%;
  transform: rotate(-2deg);
}

.scroll-indicator {
  position: absolute;
  bottom: 2rem;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  color: rgba(255, 255, 255, 0.6);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.1em;
}

.scroll-line {
  width: 1px;
  height: 40px;
  background: linear-gradient(to bottom, transparent, rgba(255, 255, 255, 0.5));
  animation: scroll-bounce 2s ease-in-out infinite;
}

@keyframes scroll-bounce {
  0%, 100% { transform: scaleY(1); opacity: 1; }
  50% { transform: scaleY(0.6); opacity: 0.5; }
}

@media (max-width: 968px) {
  .hero-content {
    grid-template-columns: 1fr;
    text-align: center;
  }

  .hero-visual {
    display: none;
  }

  .hero-stats {
    justify-content: center;
  }

  .hero-actions {
    justify-content: center;
  }
}

@media (max-width: 480px) {
  .hero-stats {
    flex-direction: column;
    gap: 1.5rem;
  }

  .stat-divider {
    width: 60px;
    height: 1px;
  }

  .hero-stats .stat-item {
    text-align: center;
  }
}
</style>
