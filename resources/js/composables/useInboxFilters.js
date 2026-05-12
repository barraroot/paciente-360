import { reactive, computed, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';

/**
 * Composable para sincronizar filtros da inbox com a query string da URL (AC-4.4.8).
 *
 * - Multi-select arrays via repetição: `?status[]=aberta&status[]=pendente`.
 * - Cada mudança em `filters` atualiza a URL via `router.replace`.
 * - `onMounted` parseia a URL para restaurar filtros ao navegar.
 * - Todos os filtros válidos para a URL são preservados ao recarregar.
 *
 * @returns {{
 *   filters: Object,
 *   clearFilters: () => void,
 *   applyFilter: (key: string, value: any) => void,
 *   toggleFilter: (key: string, value: string) => void,
 *   filtersActive: import('vue').ComputedRef<number>,
 * }}
 */
export function useInboxFilters() {
    const route = useRoute();
    const router = useRouter();

    /** @type {string[]} chaves que são multi-select (arrays) */
    const ARRAY_KEYS = ['status', 'channel_type'];

    /**
     * Estado reativo dos filtros.
     * Espelha exatamente o shape do store inbox.filters.
     */
    const filters = reactive({
        status: [],
        channel_type: [],
        channel_id: null,
        assigned_user_id: null,
        patient_id: null,
        has_media: null,
        age: null,
        q: '',
        last_activity_from: null,
        last_activity_to: null,
        ai_paused: null,
    });

    /**
     * Parseia a query string da rota atual e atualiza `filters`.
     */
    function parseUrlToFilters() {
        const q = route.query;

        // Arrays
        for (const key of ARRAY_KEYS) {
            const raw = q[key] ?? q[`${key}[]`];
            if (Array.isArray(raw)) {
                filters[key] = raw;
            } else if (typeof raw === 'string' && raw) {
                filters[key] = [raw];
            } else {
                filters[key] = [];
            }
        }

        // Strings simples
        filters.channel_id = q.channel_id ?? null;
        filters.assigned_user_id = q.assigned_user_id ?? null;
        filters.patient_id = q.patient_id ?? null;
        filters.age = q.age ?? null;
        filters.q = typeof q.q === 'string' ? q.q : '';
        filters.last_activity_from = q.last_activity_from ?? null;
        filters.last_activity_to = q.last_activity_to ?? null;

        // Booleanos
        if (q.has_media === 'true') {
            filters.has_media = true;
        } else if (q.has_media === 'false') {
            filters.has_media = false;
        } else {
            filters.has_media = null;
        }

        if (q.ai_paused === 'true') {
            filters.ai_paused = true;
        } else if (q.ai_paused === 'false') {
            filters.ai_paused = false;
        } else {
            filters.ai_paused = null;
        }
    }

    /**
     * Serializa `filters` para um objeto de query string compatível com vue-router.
     * @returns {Record<string, any>}
     */
    function filtersToQuery() {
        const q = {};

        for (const key of ARRAY_KEYS) {
            if (filters[key] && filters[key].length > 0) {
                q[key] = filters[key];
            }
        }

        if (filters.channel_id) { q.channel_id = filters.channel_id; }
        if (filters.assigned_user_id) { q.assigned_user_id = filters.assigned_user_id; }
        if (filters.patient_id) { q.patient_id = filters.patient_id; }
        if (filters.age) { q.age = filters.age; }
        if (filters.q && filters.q.length >= 2) { q.q = filters.q; }
        if (filters.last_activity_from) { q.last_activity_from = filters.last_activity_from; }
        if (filters.last_activity_to) { q.last_activity_to = filters.last_activity_to; }
        if (filters.has_media !== null) { q.has_media = String(filters.has_media); }
        if (filters.ai_paused !== null) { q.ai_paused = String(filters.ai_paused); }

        return q;
    }

    /**
     * Atualiza a URL sem navegar (share-friendly).
     */
    function syncUrl() {
        const query = filtersToQuery();

        // Preserva params que não são filtros (ex.: id de conversa)
        const preserved = {};
        if (route.query.id) { preserved.id = route.query.id; }

        router.replace({ query: { ...preserved, ...query } }).catch(() => {
            // Ignora NavigationDuplicated
        });
    }

    /**
     * Reseta todos os filtros para o estado inicial.
     */
    function clearFilters() {
        filters.status = [];
        filters.channel_type = [];
        filters.channel_id = null;
        filters.assigned_user_id = null;
        filters.patient_id = null;
        filters.has_media = null;
        filters.age = null;
        filters.q = '';
        filters.last_activity_from = null;
        filters.last_activity_to = null;
        filters.ai_paused = null;
    }

    /**
     * Define um filtro simples (scalar).
     * @param {string} key
     * @param {any} value — null para remover
     */
    function applyFilter(key, value) {
        filters[key] = value ?? null;
    }

    /**
     * Alterna um valor em um filtro multi-select (array).
     * @param {string} key — deve ser um dos ARRAY_KEYS
     * @param {string} value
     */
    function toggleFilter(key, value) {
        if (!ARRAY_KEYS.includes(key)) {
            applyFilter(key, value);
            return;
        }

        const arr = filters[key];
        const idx = arr.indexOf(value);
        if (idx !== -1) {
            arr.splice(idx, 1);
        } else {
            arr.push(value);
        }
    }

    /**
     * Número de filtros ativos.
     * @type {import('vue').ComputedRef<number>}
     */
    const filtersActive = computed(() => {
        let count = 0;
        if (filters.status.length > 0) { count++; }
        if (filters.channel_type.length > 0) { count++; }
        if (filters.channel_id) { count++; }
        if (filters.assigned_user_id) { count++; }
        if (filters.patient_id) { count++; }
        if (filters.has_media !== null) { count++; }
        if (filters.age) { count++; }
        if (filters.q && filters.q.length >= 2) { count++; }
        return count;
    });

    // Sincroniza URL → filtros na montagem
    onMounted(parseUrlToFilters);

    // Sincroniza filtros → URL a cada mudança
    watch(filters, syncUrl, { deep: true });

    return {
        filters,
        clearFilters,
        applyFilter,
        toggleFilter,
        filtersActive,
    };
}
