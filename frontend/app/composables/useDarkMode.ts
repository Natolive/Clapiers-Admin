/**
 * Admin theme (dark by default), preference persisted in localStorage and shared
 * across pages via useState. Applies the `.app-dark` class to <html> so PrimeVue's
 * dark tokens (and portalled overlays) follow it; the class is dropped when the
 * last consumer component unmounts (i.e. when leaving the admin for the public site).
 */
const THEME_KEY = 'dashboard.darkMode';

export const useDarkMode = () => {
    const isDark = useState('darkMode', () =>
        import.meta.client ? localStorage.getItem(THEME_KEY) !== 'false' : true
    );

    watch(isDark, (v) => {
        if (import.meta.client) localStorage.setItem(THEME_KEY, String(v));
    });

    useHead({ htmlAttrs: { class: computed(() => (isDark.value ? 'app-dark' : '')) } });

    const toggleDark = () => { isDark.value = !isDark.value; };

    return { isDark, toggleDark };
};
