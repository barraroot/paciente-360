import { ref, watch, computed } from 'vue';
import { useAuthStore } from '@/stores/auth.js';

const STORAGE_KEY = 'app-shell:preferences:v1';

/**
 * Preferências de UI do shell persistidas em `localStorage` por tenant + user.
 *
 * Estrutura:
 *   {
 *     [tenantSlug]: {
 *       [userId]: { sidebarMode: 'expanded' | 'compact', expandedGroups: string[] }
 *     }
 *   }
 *
 * Princípio II (multi-tenant): chave escopada por `tenant_slug + user_id` —
 * preferências NUNCA vazam entre tenants ou usuários no mesmo navegador.
 *
 * INV-2: JSON corrompido → defaults silenciosos (sem exceção).
 * INV-3: localStorage indisponível → operações são no-op silenciosas.
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
        // Cota cheia ou storage indisponível — silenciar.
    }
}

function readUserPrefs(tenantSlug, userId) {
    const all = safeReadAll();
    return all?.[tenantSlug]?.[userId] ?? null;
}

function writeUserPrefs(tenantSlug, userId, prefs) {
    const all = safeReadAll();
    if (! all[tenantSlug]) {
        all[tenantSlug] = {};
    }
    all[tenantSlug][userId] = prefs;
    safeWriteAll(all);
}

function defaultMode() {
    if (typeof window === 'undefined') {
        return 'expanded';
    }
    return window.matchMedia('(min-width: 1024px)').matches ? 'expanded' : 'compact';
}

export function useShellPreferences() {
    const auth = useAuthStore();

    const tenantSlug = computed(() => auth.tenant?.slug ?? null);
    const userId = computed(() => auth.user?.id ?? null);

    const sidebarMode = ref('expanded');
    const expandedGroups = ref([]);
    let hydrated = false;

    function hydrate() {
        if (! tenantSlug.value || ! userId.value) {
            return;
        }
        const persisted = readUserPrefs(tenantSlug.value, userId.value);
        if (persisted) {
            sidebarMode.value = persisted.sidebarMode === 'compact' ? 'compact' : 'expanded';
            expandedGroups.value = Array.isArray(persisted.expandedGroups)
                ? [...persisted.expandedGroups]
                : [];
        } else {
            sidebarMode.value = defaultMode();
            expandedGroups.value = [];
        }
        hydrated = true;
    }

    function persist() {
        if (! hydrated || ! tenantSlug.value || ! userId.value) {
            return;
        }
        writeUserPrefs(tenantSlug.value, userId.value, {
            sidebarMode: sidebarMode.value,
            expandedGroups: [...expandedGroups.value],
        });
    }

    // Hidrata na primeira vez que tenant+user ficam disponíveis (após fetchMe).
    watch([tenantSlug, userId], () => {
        if (tenantSlug.value && userId.value) {
            hydrate();
        }
    }, { immediate: true });

    function toggleSidebarMode() {
        sidebarMode.value = sidebarMode.value === 'expanded' ? 'compact' : 'expanded';
        persist();
    }

    function toggleGroup(groupKey) {
        if (! groupKey) {
            return;
        }
        const idx = expandedGroups.value.indexOf(groupKey);
        if (idx === -1) {
            expandedGroups.value = [...expandedGroups.value, groupKey];
        } else {
            const next = [...expandedGroups.value];
            next.splice(idx, 1);
            expandedGroups.value = next;
        }
        persist();
    }

    function isGroupExpanded(groupKey) {
        return expandedGroups.value.includes(groupKey);
    }

    return {
        sidebarMode,
        expandedGroups,
        toggleSidebarMode,
        toggleGroup,
        isGroupExpanded,
    };
}
