import { computed, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useReportsStore } from '@/stores/reportsStore.js';
import { useDashboardWindow } from './useDashboardWindow.js';

/**
 * Wrapper sobre `reportsStore` Pinia (já existente desde Fase 8) + persistência
 * de janela via `useDashboardWindow`.
 *
 * Watcher em `window` dispara `refresh()` automaticamente. `exportPdf()`
 * chama o endpoint POST e dispara download via Blob (lógica já implementada
 * no store).
 *
 * Princípio constitucional III (test-first): nenhuma mudança no store —
 * apenas consumo. Gate G8 do contract valida que o store permanece intacto.
 */
export function useExecutiveDashboard() {
    const store = useReportsStore();
    const { window, setWindow } = useDashboardWindow();
    const { executive: executiveState, pdfExport: pdfExportState } = storeToRefs(store);

    const data = computed(() => executiveState.value?.data ?? null);
    const loading = computed(() => Boolean(executiveState.value?.loading));
    const error = computed(() => executiveState.value?.error ?? null);
    const exporting = computed(() => Boolean(pdfExportState.value?.loading));
    const exportError = computed(() => pdfExportState.value?.error ?? null);

    async function refresh() {
        await store.loadExecutive({ preset: window.value });
    }

    async function exportPdf() {
        return store.exportPdf({ preset: window.value });
    }

    // Trocar window dispara refresh imediato.
    watch(window, () => {
        refresh();
    });

    // Carga inicial (após hydrate da window).
    refresh();

    return {
        data,
        loading,
        error,
        exporting,
        exportError,
        window,
        setWindow,
        refresh,
        exportPdf,
    };
}
