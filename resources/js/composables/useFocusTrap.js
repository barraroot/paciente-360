import { watch, nextTick } from 'vue';

const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

/**
 * Focus trap controlado por flag reativa (sem deps externas).
 *
 * Quando `activeRef.value === true`, captura Tab/Shift+Tab dentro do
 * `targetRef` e cicla o foco. Ao desativar, retorna foco para o elemento que
 * tinha foco antes de ativar.
 *
 * Princípio V/A11y: cumpre FR-021 (drawer mobile) e FR-026 (modal a11y).
 *
 * @param {import('vue').Ref<HTMLElement | null>} targetRef
 * @param {import('vue').Ref<boolean>} activeRef
 */
export function useShellFocusTrap(targetRef, activeRef) {
    let previouslyFocused = null;

    function getFocusable() {
        if (! targetRef.value) {
            return [];
        }
        return Array.from(targetRef.value.querySelectorAll(FOCUSABLE_SELECTOR))
            .filter((el) => ! el.hasAttribute('disabled') && el.offsetParent !== null);
    }

    function onKeydown(event) {
        if (event.key !== 'Tab') {
            return;
        }
        const focusable = getFocusable();
        if (focusable.length === 0) {
            event.preventDefault();
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (! event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    async function activate() {
        previouslyFocused = document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;
        await nextTick();
        document.addEventListener('keydown', onKeydown);
        const focusable = getFocusable();
        if (focusable.length > 0) {
            focusable[0].focus();
        }
    }

    function deactivate() {
        document.removeEventListener('keydown', onKeydown);
        if (previouslyFocused && typeof previouslyFocused.focus === 'function') {
            previouslyFocused.focus();
        }
        previouslyFocused = null;
    }

    watch(activeRef, (isActive) => {
        if (isActive) {
            activate();
        } else {
            deactivate();
        }
    });
}
