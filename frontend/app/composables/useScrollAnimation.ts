import { onMounted, onUnmounted, ref } from 'vue'

export type AnimationType =
  | 'fade-up'
  | 'fade-in'
  | 'scale-in'
  | 'slide-in-left'
  | 'slide-in-right'

export const useScrollAnimation = (animationType: AnimationType = 'fade-up') => {
  const observers = ref<IntersectionObserver[]>([])

  const observe = (element: HTMLElement | null, delay: number = 0) => {
    if (!element || typeof IntersectionObserver === 'undefined') {
      return
    }

    // Add the enter class immediately
    element.classList.add(`${animationType}-enter`)

    // Apply delay if specified
    if (delay > 0) {
      element.style.transitionDelay = `${delay}ms`
    }

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            // Element is visible, trigger animation
            entry.target.classList.remove(`${animationType}-enter`)
            entry.target.classList.add(`${animationType}-active`)

            // Stop observing after animation triggers (one-time animation)
            observer.unobserve(entry.target)
          }
        })
      },
      {
        threshold: 0.2, // Trigger when 20% of element is visible
        rootMargin: '0px 0px -50px 0px', // Trigger slightly before element enters viewport
      }
    )

    observer.observe(element)
    observers.value.push(observer)
  }

  const observeMultiple = (
    elements: HTMLElement[] | NodeListOf<Element>,
    staggerDelay: number = 100
  ) => {
    Array.from(elements).forEach((element, index) => {
      observe(element as HTMLElement, index * staggerDelay)
    })
  }

  const cleanup = () => {
    observers.value.forEach((observer) => observer.disconnect())
    observers.value = []
  }

  onUnmounted(() => {
    cleanup()
  })

  return {
    observe,
    observeMultiple,
    cleanup,
  }
}
