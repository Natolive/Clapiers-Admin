/**
 * Live clock (fr-FR, HH:mm:ss), updated every second.
 */
export const useClock = () => {
    const currentTime = ref('');
    let interval: ReturnType<typeof setInterval> | null = null;

    const updateTime = () => {
        currentTime.value = new Date().toLocaleTimeString('fr-FR', {
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        });
    };

    onMounted(() => {
        updateTime();
        interval = setInterval(updateTime, 1000);
    });

    onUnmounted(() => {
        if (interval) clearInterval(interval);
    });

    return { currentTime };
};
