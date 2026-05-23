import { ref, onMounted, onBeforeUnmount } from 'vue';

/**
 * Auto-refresh com Page Visibility API.
 *
 * - Roda `setInterval` apenas quando `document.visibilityState === 'visible'`
 * - Pausa quando aba some (SC-009: 0 requests com aba oculta)
 * - Re-cria interval quando aba volta
 * - Trigger imediato se retornou após mais de `intervalMs / 2` em background
 *
 * @param {() => Promise<void> | void} callback
 * @param {number} intervalMs
 */
export function useAutoRefresh(callback, intervalMs) {
    const isRunning = ref(false);
    let timerId = null;
    let lastVisibleAt = Date.now();

    function start() {
        stop();
        timerId = setInterval(() => {
            if (document.visibilityState === 'visible') {
                Promise.resolve(callback()).catch(() => {});
            }
        }, intervalMs);
        isRunning.value = true;
    }

    function stop() {
        if (timerId) {
            clearInterval(timerId);
            timerId = null;
        }
        isRunning.value = false;
    }

    function onVisibilityChange() {
        if (document.visibilityState === 'visible') {
            const elapsed = Date.now() - lastVisibleAt;
            if (elapsed > intervalMs / 2) {
                Promise.resolve(callback()).catch(() => {});
            }
            lastVisibleAt = Date.now();
            start();
        } else {
            lastVisibleAt = Date.now();
            stop();
        }
    }

    onMounted(() => {
        document.addEventListener('visibilitychange', onVisibilityChange);
        if (document.visibilityState === 'visible') {
            start();
        }
    });

    onBeforeUnmount(() => {
        stop();
        document.removeEventListener('visibilitychange', onVisibilityChange);
    });

    return { isRunning, pause: stop, resume: start };
}
