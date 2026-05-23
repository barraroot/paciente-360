<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import KpiCard from './KpiCard.vue';

const props = defineProps({
    section: { type: Object, default: null }, // { data, error }
    loading: { type: Boolean, default: false },
});

const { t } = useI18n();

const kpis = computed(() => props.section?.data ?? null);
const hasError = computed(() => Boolean(props.section?.error));

const cards = computed(() => {
    if (! kpis.value) {
        return [
            { icon: 'calendar', label: t('panel_home.kpis.appointments_today.label'), total: 0, subInfo: '', link: '/panel/agenda', ariaLabel: '' },
            { icon: 'inbox', label: t('panel_home.kpis.conversations_pending.label'), total: 0, subInfo: '', link: '/panel/inbox', ariaLabel: '' },
            { icon: 'users', label: t('panel_home.kpis.leads_new_7d.label'), total: 0, subInfo: '', link: '/panel/pacientes/funil', ariaLabel: '' },
            { icon: 'clipboard', label: t('panel_home.kpis.prescriptions_expiring_30d.label'), total: 0, subInfo: '', link: '/panel/receituarios', ariaLabel: '' },
        ];
    }

    const k = kpis.value;

    return [
        {
            icon: 'calendar',
            label: t('panel_home.kpis.appointments_today.label'),
            total: k.appointments_today?.total ?? 0,
            subInfo: (k.appointments_today?.total ?? 0) === 0
                ? t('panel_home.kpis.appointments_today.sub_info_empty')
                : t('panel_home.kpis.appointments_today.sub_info_split', {
                      confirmed: k.appointments_today?.confirmed ?? 0,
                      pending: k.appointments_today?.pending ?? 0,
                  }),
            link: '/panel/agenda',
            ariaLabel: t('panel_home.kpis.appointments_today.aria', {
                total: k.appointments_today?.total ?? 0,
                confirmed: k.appointments_today?.confirmed ?? 0,
            }),
        },
        {
            icon: 'inbox',
            label: t('panel_home.kpis.conversations_pending.label'),
            total: k.conversations_pending?.total ?? 0,
            subInfo: (k.conversations_pending?.total ?? 0) === 0
                ? t('panel_home.kpis.conversations_pending.sub_info_empty')
                : t('panel_home.kpis.conversations_pending.sub_info_split', {
                      unassigned: k.conversations_pending?.unassigned ?? 0,
                  }),
            link: '/panel/inbox',
            ariaLabel: t('panel_home.kpis.conversations_pending.aria', {
                total: k.conversations_pending?.total ?? 0,
                unassigned: k.conversations_pending?.unassigned ?? 0,
            }),
        },
        {
            icon: 'users',
            label: t('panel_home.kpis.leads_new_7d.label'),
            total: k.leads_new_7d?.total ?? 0,
            subInfo: (k.leads_new_7d?.total ?? 0) === 0
                ? t('panel_home.kpis.leads_new_7d.sub_info_empty')
                : t('panel_home.kpis.leads_new_7d.sub_info'),
            link: '/panel/pacientes/funil',
            ariaLabel: t('panel_home.kpis.leads_new_7d.aria', { total: k.leads_new_7d?.total ?? 0 }),
        },
        {
            icon: 'clipboard',
            label: t('panel_home.kpis.prescriptions_expiring_30d.label'),
            total: k.prescriptions_expiring_30d?.total ?? 0,
            subInfo: (k.prescriptions_expiring_30d?.total ?? 0) === 0
                ? t('panel_home.kpis.prescriptions_expiring_30d.sub_info_empty')
                : t('panel_home.kpis.prescriptions_expiring_30d.sub_info'),
            link: '/panel/receituarios',
            ariaLabel: t('panel_home.kpis.prescriptions_expiring_30d.aria', {
                total: k.prescriptions_expiring_30d?.total ?? 0,
            }),
        },
    ];
});
</script>

<template>
    <section aria-label="KPIs operacionais">
        <ul role="list" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <li v-for="card in cards" :key="card.label">
                <KpiCard
                    :icon="card.icon"
                    :label="card.label"
                    :total="card.total"
                    :sub-info="card.subInfo"
                    :link="card.link"
                    :aria-label="card.ariaLabel"
                    :loading="loading"
                    :error="hasError"
                />
            </li>
        </ul>
    </section>
</template>
