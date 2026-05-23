import { ref, computed, watch } from 'vue';
import { useAuthStore } from '@/stores/auth.js';

const STORAGE_KEY = 'executive_dashboard:window:v1';
const VALID_WINDOWS = ['24h', '7d', '30d', '90d'];
const DEFAULT_WINDOW = '7d';

/**
 * Preferência de janela analítica do Dashboard Executivo persistida em
 * `localStorage` por tenant + user. Chave SEPARADA de:
 *   - `app-shell:preferences:v1` (spec 009)
 *   - `panel_home:scope:v1` (spec 010)
 *
 * Princípio II (multi-tenant): chave escopada — nunca vaza entre tenants
 * ou usuários no mesmo navegador.
 *
 * INV-1..4 (data-model.md § 2.5):
 *   - INV-1 chave usa auth.tenant.slug + auth.user.id
 *   - INV-2 JSON corrompido → default + sobrescrita silenciosa
 *   - INV-3 localStorage indisponível → operações são no-ops
 *   - INV-4 valor inválido → default '7d'
 */

function safeReadAll() {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        if (! raw) {
            return {};
        }
        const parsed = JSON.parse(raw);
        return typeof parsed === 'object' && parsed !== null ? parsed : {};
    } catch {
        return {};
    }
}

function safeWriteAll(value) {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(value));
    } catch {
        // Silencioso.
    }
}

function sanitize(value) {
    return VALID_WINDOWS.includes(value) ? value : DEFAULT_WINDOW;
}

export function useDashboardWindow() {
    const auth = useAuthStore();
    const tenantSlug = computed(() => auth.tenant?.slug ?? null);
    const userId = computed(() => auth.user?.id ?? null);

    const window_ = ref(DEFAULT_WINDOW);
    let hydrated = false;

    function hydrate() {
        if (! tenantSlug.value || ! userId.value) {
            return;
        }
        const all = safeReadAll();
        const persisted = all?.[tenantSlug.value]?.[String(userId.value)];
        window_.value = sanitize(persisted);
        hydrated = true;
    }

    function persist() {
        if (! hydrated || ! tenantSlug.value || ! userId.value) {
            return;
        }
        const all = safeReadAll();
        if (! all[tenantSlug.value]) {
            all[tenantSlug.value] = {};
        }
        all[tenantSlug.value][String(userId.value)] = window_.value;
        safeWriteAll(all);
    }

    function setWindow(value) {
        window_.value = sanitize(value);
        persist();
    }

    watch([tenantSlug, userId], () => {
        if (tenantSlug.value && userId.value) {
            hydrate();
        }
    }, { immediate: true });

    return { window: window_, setWindow };
}
