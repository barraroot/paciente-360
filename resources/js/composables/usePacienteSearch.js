import { ref, watch } from 'vue';
import api from '@/lib/api.js';

/**
 * Composable de busca/filtragem de pacientes.
 *
 * Recebe uma ref de filtros (objeto reativo) e retorna:
 * - results: { data: PacienteResource[], meta: PaginationMeta }
 * - loading: boolean
 * - error: string | null
 * - refetch(): força fetch imediato sem debounce
 *
 * Debounce de 350ms em qualquer mudança nos filtros.
 * Cancela requisições anteriores via AbortController para evitar race conditions.
 *
 * @param {import('vue').Ref<object>} filtersRef
 */
export function usePacienteSearch(filtersRef) {
    const results = ref({ data: [], meta: {} });
    const loading = ref(false);
    const error = ref(null);

    let abortController = null;
    let debounceTimer = null;

    const fetchPacientes = async () => {
        if (abortController) {
            abortController.abort();
        }
        abortController = new AbortController();
        loading.value = true;
        error.value = null;

        try {
            const { data } = await api.get('/pacientes', {
                params: filtersRef.value,
                signal: abortController.signal,
            });
            results.value = data;
        } catch (e) {
            // CanceledError é lançado pelo Axios quando o AbortController aborta.
            if (e.name !== 'AbortError' && e.name !== 'CanceledError') {
                error.value = e.response?.data?.message ?? 'Erro ao buscar pacientes.';
            }
        } finally {
            loading.value = false;
        }
    };

    watch(
        filtersRef,
        () => {
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }
            debounceTimer = setTimeout(fetchPacientes, 350);
        },
        { deep: true },
    );

    // Carrega imediatamente na montagem.
    fetchPacientes();

    return { results, loading, error, refetch: fetchPacientes };
}
