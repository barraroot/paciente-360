import { ref, watch } from 'vue';
import api from '@/lib/api.js';
import { usePanelHomeScope } from './usePanelHomeScope.js';
import { useAutoRefresh } from './useAutoRefresh.js';

const AUTOREFRESH_MS = 120_000; // 2 min (FR-027)

/**
 * Fetch + estado + auto-refresh do Dashboard Home.
 *
 * Encapsula:
 *   - chamada `GET /api/v1/panel/home?scope=...`
 *   - gerenciamento de loading / error
 *   - retry manual e auto-refresh (Page Visibility API via useAutoRefresh)
 *   - reconciliação de scope local com `can_toggle_scope` da response
 *     (caso usuário perca permissão de admin)
 */
export function usePanelHome() {
    const { scope, setScope } = usePanelHomeScope();
    const data = ref(null);
    const loading = ref(true);
    const error = ref(null);
    let abortController = null;

    async function refresh() {
        if (abortController) {
            abortController.abort();
        }
        abortController = new AbortController();

        loading.value = true;
        error.value = null;

        try {
            const response = await api.get('/panel/home', {
                params: { scope: scope.value },
                signal: abortController.signal,
            });
            data.value = response.data.data ?? response.data;

            // Reconciliar scope local se backend forçou downgrade.
            if (data.value?.scope_applied && data.value.scope_applied !== scope.value) {
                setScope(data.value.scope_applied);
            }
        } catch (err) {
            if (err.name === 'CanceledError' || err.code === 'ERR_CANCELED') {
                return;
            }
            error.value = err?.response?.data?.message ?? 'network_error';
        } finally {
            loading.value = false;
        }
    }

    // Auto-refresh: chama refresh a cada 2 min com aba visível, pausa em background.
    useAutoRefresh(refresh, AUTOREFRESH_MS);

    // Quando scope muda manualmente, refresh imediato.
    watch(scope, () => {
        refresh();
    });

    // Carga inicial.
    refresh();

    return {
        data,
        loading,
        error,
        scope,
        setScope,
        refresh,
    };
}
