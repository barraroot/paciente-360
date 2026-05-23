import { ref, computed, watch } from 'vue';
import { useAuthStore } from '@/stores/auth.js';

const STORAGE_KEY = 'panel_home:scope:v1';

/**
 * Preferência de scope (Minha visão × Visão da clínica) persistida em
 * localStorage por tenant + user. Chave SEPARADA do app-shell para evitar
 * acoplamento de schemas (research R11).
 *
 * Princípio II (multi-tenant): chave escopada — nunca vaza entre tenants
 * ou usuários no mesmo navegador.
 *
 * INV-2: JSON corrompido → default 'user' + sobrescrita silenciosa.
 * INV-3: localStorage indisponível → operações são no-ops (escolha volátil
 * em memória).
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

export function usePanelHomeScope() {
    const auth = useAuthStore();

    const tenantSlug = computed(() => auth.tenant?.slug ?? null);
    const userId = computed(() => auth.user?.id ?? null);
    const scope = ref('user');
    let hydrated = false;

    function hydrate() {
        if (! tenantSlug.value || ! userId.value) {
            return;
        }
        const all = safeReadAll();
        const persisted = all?.[tenantSlug.value]?.[String(userId.value)];
        scope.value = persisted === 'clinic' ? 'clinic' : 'user';
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
        all[tenantSlug.value][String(userId.value)] = scope.value;
        safeWriteAll(all);
    }

    function setScope(value) {
        scope.value = value === 'clinic' ? 'clinic' : 'user';
        persist();
    }

    watch([tenantSlug, userId], () => {
        if (tenantSlug.value && userId.value) {
            hydrate();
        }
    }, { immediate: true });

    return { scope, setScope };
}
