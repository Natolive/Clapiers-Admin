<template>
  <footer class="footer">
    <div class="container">
      <div class="footer-content">
        <div class="footer-brand">
          <div class="logo">
            <span class="logo-icon">🏐</span>
            <span class="logo-text">
              <span class="logo-main">VBC Clapiers</span>
              <span class="logo-sub">Volleyball Club</span>
            </span>
          </div>
          <p class="footer-tagline">
            Depuis plus de 25 ans, le volleyball à Clapiers.
          </p>
        </div>

        <div class="footer-links">
          <div class="footer-col">
            <h4 class="footer-title">Navigation</h4>
            <ul class="footer-list">
              <li><a href="#accueil">Accueil</a></li>
              <li><a href="#club">Le Club</a></li>
              <li><a href="#horaires">Horaires</a></li>
              <li><a href="#contact">Contact</a></li>
            </ul>
          </div>

          <div class="footer-col">
            <h4 class="footer-title">Informations</h4>
            <ul class="footer-list">
              <li><a href="#">Inscriptions</a></li>
              <li><a href="#">Tarifs</a></li>
              <li><a href="#">Documents</a></li>
              <li><NuxtLink to="/dashboard">Espace membre</NuxtLink></li>
            </ul>
          </div>

          <div class="footer-col">
            <h4 class="footer-title">Contact</h4>
            <ul class="footer-list contact-list">
              <li>
                <i class="pi pi-envelope"></i>
                <a href="mailto:contact@vbc-clapiers.fr">contact@vbc-clapiers.fr</a>
              </li>
              <li>
                <i class="pi pi-map-marker"></i>
                <span>Gymnase de Clapiers, 34830</span>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <div class="footer-bottom">
        <p class="copyright">
          &copy; {{ currentYear }} VBC Clapiers. Tous droits réservés.
        </p>
        <div class="footer-badges">
          <span class="badge">FSGT</span>
        </div>
      </div>
    </div>
  </footer>
</template>

<script setup lang="ts">
const currentYear = new Date().getFullYear()
</script>

<style scoped>
.footer {
  background: var(--club-dark);
  color: white;
  padding: 4rem 2rem 2rem;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
}

.footer-content {
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 4rem;
  padding-bottom: 3rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.logo {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.logo-icon {
  font-size: 2.5rem;
}

.logo-text {
  display: flex;
  flex-direction: column;
  line-height: 1.2;
}

.logo-main {
  font-size: 1.25rem;
  font-weight: 700;
}

.logo-sub {
  font-size: 0.75rem;
  opacity: 0.7;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.footer-tagline {
  color: rgba(255, 255, 255, 0.6);
  font-size: 0.95rem;
  margin: 0;
  max-width: 250px;
  line-height: 1.5;
}

.footer-links {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 2rem;
}

.footer-title {
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: rgba(255, 255, 255, 0.5);
  margin: 0 0 1.25rem;
}

.footer-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.footer-list li {
  margin-bottom: 0.75rem;
}

.footer-list a,
.footer-list span {
  color: rgba(255, 255, 255, 0.8);
  text-decoration: none;
  font-size: 0.95rem;
  transition: color 0.2s ease;
}

.footer-list a:hover {
  color: var(--club-accent);
}

.contact-list li {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.contact-list i {
  color: var(--club-accent);
  font-size: 0.9rem;
}

.footer-bottom {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 2rem;
}

.copyright {
  color: rgba(255, 255, 255, 0.5);
  font-size: 0.85rem;
  margin: 0;
}

.footer-badges {
  display: flex;
  gap: 0.75rem;
}

.badge {
  background: rgba(255, 255, 255, 0.1);
  padding: 0.4rem 0.75rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.7);
}

@media (max-width: 968px) {
  .footer-content {
    grid-template-columns: 1fr;
    gap: 3rem;
  }

  .footer-links {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 600px) {
  .footer {
    padding: 3rem 1.5rem 1.5rem;
  }

  .footer-links {
    grid-template-columns: 1fr;
    gap: 2rem;
  }

  .footer-bottom {
    flex-direction: column;
    gap: 1rem;
    text-align: center;
  }
}
</style>
