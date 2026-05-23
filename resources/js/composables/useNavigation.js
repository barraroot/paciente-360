import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/stores/auth.js';
import { NAVIGATION } from '@/config/navigation.js';

/**
 * Filtra e expõe a árvore de navegação canônica baseado nas permissions
 * do usuário corrente.
 *
 * Contrato detalhado em `specs/009-app-shell/contracts/navigation-tree.md`.
 *
 * Gates de filtragem (G1–G5):
 *   - Item sem `ability`/`anyOf` → sempre visível (autenticado-only)
 *   - Item com `ability` → visível sse permission presente
 *   - Item com `anyOf` → visível sse user tem QUALQUER uma
 *   - Grupo com 0 children visíveis → omitido
 *   - Ordem do array preservada
 */
export function useNavigation() {
    const auth = useAuthStore();
    const { permissions } = storeToRefs(auth);
    const route = useRoute();

    function isItemVisible(item) {
        if (! item.ability && (! item.anyOf || item.anyOf.length === 0)) {
            return true;
        }
        if (item.ability) {
            return permissions.value.includes(item.ability);
        }
        if (Array.isArray(item.anyOf)) {
            return item.anyOf.some((p) => permissions.value.includes(p));
        }
        return true;
    }

    function filterEntry(entry) {
        if (Array.isArray(entry.children)) {
            const visibleChildren = entry.children.filter(isItemVisible);
            if (visibleChildren.length === 0) {
                return null;
            }
            return { ...entry, children: visibleChildren };
        }
        return isItemVisible(entry) ? entry : null;
    }

    const visibleNav = computed(() => {
        return NAVIGATION
            .map(filterEntry)
            .filter((entry) => entry !== null);
    });

    const isEmpty = computed(() => visibleNav.value.length === 0);

    function findCurrentByRoute(name) {
        for (const entry of NAVIGATION) {
            if (Array.isArray(entry.children)) {
                const found = entry.children.find((c) => c.routeName === name);
                if (found) {
                    return { groupKey: entry.key, itemKey: found.key };
                }
            } else if (entry.routeName === name) {
                return { groupKey: null, itemKey: entry.key };
            }
        }
        return { groupKey: null, itemKey: null };
    }

    const currentMatch = computed(() => findCurrentByRoute(route.name));
    const currentGroupKey = computed(() => currentMatch.value.groupKey);
    const currentItemKey = computed(() => currentMatch.value.itemKey);

    /**
     * Lookup do labelKey por routeName — usado por document.title fallback (US-6).
     */
    function labelKeyForRoute(name) {
        for (const entry of NAVIGATION) {
            if (Array.isArray(entry.children)) {
                const found = entry.children.find((c) => c.routeName === name);
                if (found) {
                    return found.labelKey;
                }
            } else if (entry.routeName === name) {
                return entry.labelKey;
            }
        }
        return null;
    }

    return {
        visibleNav,
        isEmpty,
        currentGroupKey,
        currentItemKey,
        labelKeyForRoute,
    };
}
