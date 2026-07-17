<template>
  <PublicPage :label="seasonLabel" title="Documents &amp; démarches" subtitle="Comment obtenir votre licence, et tous les documents à télécharger.">
    <!-- Le process d'inscription -->
    <section class="pp-block">
      <h2 class="pp-block__title">La démarche d'inscription</h2>
      <ol class="steps">
        <li>
          <span class="step-num">1</span>
          <div>
            <strong>Préparez vos pièces</strong>
            <p>Une photo d'identité et une pièce d'identité (recto), au format PDF, PNG ou JPG.</p>
          </div>
        </li>
        <li>
          <span class="step-num">2</span>
          <div>
            <strong>Remplissez le questionnaire de santé FSGT</strong>
            <p>
              À télécharger ci-dessous, à remplir vous-même et à <strong>conserver</strong>
              (il n'est pas transmis au club). Selon vos réponses&nbsp;:
              si tout est «&nbsp;non&nbsp;», joignez l'<strong>attestation sur l'honneur</strong>&nbsp;;
              si au moins un «&nbsp;oui&nbsp;», joignez un <strong>certificat médical</strong> de non
              contre-indication de moins de 6 mois.
            </p>
          </div>
        </li>
        <li>
          <span class="step-num">3</span>
          <div>
            <strong>Complétez le formulaire en ligne</strong>
            <p>Identité, représentant légal (pour les mineurs), santé et pièces à joindre.</p>
          </div>
        </li>
        <li>
          <span class="step-num">4</span>
          <div>
            <strong>Validation par le club</strong>
            <p>Un responsable vérifie votre demande.</p>
          </div>
        </li>
        <li>
          <span class="step-num">5</span>
          <div>
            <strong>Paiement en ligne</strong>
            <p>Vous recevez un lien par e-mail pour régler votre licence et finaliser l'adhésion.</p>
          </div>
        </li>
      </ol>
      <NuxtLink to="/inscriptions" class="pp-cta">
        Faire ma demande de licence <i class="pi pi-arrow-right"></i>
      </NuxtLink>
    </section>

    <!-- Documents à télécharger -->
    <section class="pp-block">
      <h2 class="pp-block__title">Documents à télécharger</h2>
      <p class="pp-block__hint">Choisissez la version correspondant à l'âge du licencié au moment de l'inscription.</p>
      <div class="docs-groups">
        <div v-for="group in docGroups" :key="group.title" class="docs-group">
          <h3>{{ group.title }}</h3>
          <a v-for="doc in group.docs" :key="doc.url" :href="doc.url" target="_blank" rel="noopener" class="doc-link">
            <i class="pi pi-file-pdf"></i> {{ doc.label }}
          </a>
        </div>
      </div>
    </section>
  </PublicPage>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'public' })
useSeoMeta({
  title: 'Documents & démarches - Clapiers Volley Ball',
  description: "La démarche d'inscription et les documents FSGT à télécharger (questionnaire de santé, attestation sur l'honneur) pour votre licence au Clapiers Volley Ball.",
  ogTitle: 'Documents & démarches - Clapiers Volley Ball',
  ogDescription: "La démarche d'inscription et les documents FSGT à télécharger pour votre licence au Clapiers Volley Ball.",
  ogUrl: 'https://clapiersvb.fr/documents',
  twitterTitle: 'Documents & démarches - Clapiers Volley Ball',
  twitterDescription: "La démarche d'inscription et les documents FSGT à télécharger pour votre licence.",
})
useHead({
  link: [{ rel: 'canonical', href: 'https://clapiersvb.fr/documents' }],
})

const { season } = useCurrentSeason()
const seasonLabel = computed(() => (season.value ? `Adhésion · Saison ${season.value}` : 'Adhésion'))

// Mêmes fichiers que le formulaire d'inscription (public/documents/fsgt).
const docGroups = [
  {
    title: 'Majeur',
    docs: [
      { label: 'Questionnaire de santé FSGT', url: '/documents/fsgt/questionnaire-sante-majeur.pdf' },
      { label: "Attestation sur l'honneur FSGT", url: '/documents/fsgt/attestation-majeur.pdf' },
    ],
  },
  {
    title: 'Mineur',
    docs: [
      { label: 'Questionnaire de santé FSGT', url: '/documents/fsgt/questionnaire-sante-mineur.pdf' },
      { label: "Attestation sur l'honneur FSGT", url: '/documents/fsgt/attestation-mineur.pdf' },
    ],
  },
]
</script>

<style scoped>
.steps {
  list-style: none;
  margin: 0 0 1.5rem;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}
.steps li {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}
.step-num {
  flex-shrink: 0;
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  background: var(--club-primary);
  color: #fff;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}
.steps strong { color: var(--club-dark); }
.steps p {
  margin: 0.25rem 0 0;
  color: #6b7280;
  font-size: 0.95rem;
  line-height: 1.5;
}

.docs-groups {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1.5rem;
}
.docs-group h3 {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #9ca3af;
  margin: 0 0 0.75rem;
}
.doc-link {
  display: flex;
  width: fit-content;
  align-items: center;
  gap: 0.5rem;
  color: var(--club-primary);
  text-decoration: none;
  font-size: 0.95rem;
  font-weight: 500;
}
.doc-link + .doc-link { margin-top: 0.5rem; }
.doc-link:hover { color: var(--club-accent); }
</style>
