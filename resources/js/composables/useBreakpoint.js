import { computed } from 'vue';
import { useMediaQuery } from '@vueuse/core';

/**
 * Reactive viewport breakpoint detection.
 *
 * Breakpoints alinhados com Tailwind defaults:
 *   isMobile  < 768px
 *   isTablet  768–1023px (md)
 *   isDesktop ≥ 1024px (lg)
 *
 * Reage a resize/rotação de dispositivo automaticamente — `useMediaQuery` do
 * @vueuse/core escuta o MediaQueryList nativo do browser.
 */
export function useBreakpoint() {
    const isMobile = useMediaQuery('(max-width: 767.98px)');
    const isTablet = useMediaQuery('(min-width: 768px) and (max-width: 1023.98px)');
    const isDesktop = useMediaQuery('(min-width: 1024px)');

    return {
        isMobile,
        isTablet,
        isDesktop,
        isAtLeastTablet: computed(() => isTablet.value || isDesktop.value),
    };
}
