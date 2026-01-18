// Import images from assets
import photo1 from '~/assets/images/1CBA0375-0271-46D9-9C96-FFA7CFB05150_1_105_c.jpeg'
import photo2 from '~/assets/images/9EFEFB97-1978-45EF-99E6-B24B6CF9EA65_1_105_c.jpeg'
import photo3 from '~/assets/images/32E60CCD-6CA4-40DE-9023-16DFE21B7300_1_105_c.jpeg'

export interface VitrineConfig {
  logo: string
  heroBackground: string
  clubPhotos: string[]
  brandColors: {
    primary: string
    secondary: string
    accent: string
    dark: string
    light: string
  }
  clubInfo: {
    name: string
    tagline: string
    description: string
    email: string
    phone: string
    address: string
    socialMedia: {
      facebook?: string
      instagram?: string
      twitter?: string
    }
  }
  stats: {
    label: string
    value: string
    icon: string
  }[]
  values: {
    title: string
    description: string
    icon: string
  }[]
}

export const useVitrineConfig = () => {
  const config: VitrineConfig = {
    logo: '/images/vitrine/logo.svg',
    heroBackground: 'https://images.unsplash.com/photo-1612872087720-bb876e2e67d1?w=1920&h=1080&fit=crop',
    clubPhotos: [
      photo1,
      photo2,
      photo3,
    ],
    brandColors: {
      primary: '#FF6B35',    // Volleyball orange
      secondary: '#004E98',  // Deep blue
      accent: '#FFD23F',     // Golden yellow
      dark: '#1A1A2E',       // Dark background
      light: '#FFFFFF',      // White
    },
    clubInfo: {
      name: 'Clapiers Volleyball',
      tagline: 'Passion, Performance, Équipe',
      description: 'Un short, une paire de chaussure et l\'aventure démarre, le reste c\'est nous qui nous en occupons dans la joie et la bonne humeur ! Vous êtes les bienvenues gratuitement les premières séances de découvertes, vous serez guidés et encadrés dans une ambiance conviviale.',
      email: 'contact@clapiers-vb.fr',
      phone: '+33 6 XX XX XX XX',
      address: '123 Avenue du Sport, 34830 Clapiers, France',
      socialMedia: {
        facebook: 'https://facebook.com/clapiers-vb',
        instagram: 'https://instagram.com/clapiers_vb',
      },
    },
    stats: [
      { label: 'Adhérents', value: '150+', icon: 'pi-users' },
      { label: 'Équipes', value: '8', icon: 'pi-sitemap' },
      { label: 'Années d\'expérience', value: '20+', icon: 'pi-calendar' },
      { label: 'Trophées', value: '15', icon: 'pi-star' },
    ],
    values: [
      {
        title: 'Passion',
        description: 'L\'amour du volleyball guide toutes nos actions et rassemble notre communauté.',
        icon: 'pi-heart',
      },
      {
        title: 'Performance',
        description: 'Nous encourageons chaque joueur à se dépasser et atteindre ses objectifs.',
        icon: 'pi-chart-line',
      },
      {
        title: 'Équipe',
        description: 'L\'esprit d\'équipe et la solidarité sont au cœur de notre identité.',
        icon: 'pi-users',
      },
    ],
  }

  // Apply brand colors to CSS custom properties
  const applyBrandColors = () => {
    if (typeof document !== 'undefined') {
      const root = document.documentElement
      root.style.setProperty('--vitrine-primary', config.brandColors.primary)
      root.style.setProperty('--vitrine-secondary', config.brandColors.secondary)
      root.style.setProperty('--vitrine-accent', config.brandColors.accent)
      root.style.setProperty('--vitrine-dark', config.brandColors.dark)
      root.style.setProperty('--vitrine-light', config.brandColors.light)
    }
  }

  return { config, applyBrandColors }
}
