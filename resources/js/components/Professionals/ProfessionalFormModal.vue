<script setup>
import { ref, reactive, computed, watch, useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { useProfessionalsStore } from '@/stores/professionalsStore.js';
import { useShellFocusTrap } from '@/composables/useFocusTrap.js';
import HeroIcon from '@/components/layout/icons/HeroIcon.vue';
import CouncilTypeSelect from './CouncilTypeSelect.vue';
import EmailAlreadyUserModal from './EmailAlreadyUserModal.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    professional: { type: Object, default: null }, // null = create; objeto = edit
});

const emit = defineEmits(['update:open', 'saved']);

const { t } = useI18n();
const store = useProfessionalsStore();
const modalEl = useTemplateRef('modalEl');
const openRef = computed(() => props.open);
useShellFocusTrap(modalEl, openRef);

const UFS = ['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'];

const form = reactive({
    name: '',
    council_type: 'CRM',
    council_type_other: '',
    council_number: '',
    council_state: 'SP',
    especialidade: '',
    link_mode: 'existing', // 'existing' | 'invite'
    user_id: null,
    email: '',
    confirmed_existing_user: false,
});

const errors = ref({});
const saving = ref(false);
const emailAlreadyUser = ref(null); // { id, name } quando 409 Q2 exige confirmação
const isEdit = computed(() => props.professional !== null);

watch(() => props.professional, (p) => {
    if (p) {
        Object.assign(form, {
            name: p.name,
            council_type: p.council_type ?? 'CRM',
            council_type_other: p.council_type_other ?? '',
            council_number: p.council_number ?? '',
            council_state: p.council_state ?? 'SP',
            especialidade: p.especialidade ?? '',
        });
    } else {
        Object.assign(form, {
            name: '',
            council_type: 'CRM',
            council_type_other: '',
            council_number: '',
            council_state: 'SP',
            especialidade: '',
            link_mode: 'existing',
            user_id: null,
            email: '',
            confirmed_existing_user: false,
        });
    }
    errors.value = {};
    emailAlreadyUser.value = null;
}, { immediate: true });

function close() {
    emit('update:open', false);
}

function onKeydown(e) {
    if (e.key === 'Escape' && props.open) {
        e.preventDefault();
        close();
    }
}

async function submit() {
    saving.value = true;
    errors.value = {};

    const payload = {
        name: form.name,
        council_type: form.council_type,
        council_number: form.council_number,
        council_state: form.council_state,
        especialidade: form.especialidade || null,
    };
    if (form.council_type === 'OUTRO') {
        payload.council_type_other = form.council_type_other;
    }
    if (! isEdit.value) {
        if (form.link_mode === 'existing') {
            payload.user_id = Number(form.user_id);
        } else {
            payload.email = form.email;
            if (form.confirmed_existing_user) {
                payload.confirmed_existing_user = true;
            }
        }
    }

    try {
        const saved = isEdit.value
            ? await store.update(props.professional.id, payload)
            : await store.create(payload);
        emit('saved', saved);
        close();
    } catch (e) {
        const status = e?.response?.status;
        const body = e?.response?.data;
        if (status === 422 && body?.errors) {
            errors.value = body.errors;
        } else if (status === 409 && body?.code === 'email_already_user_requires_confirmation') {
            // Q2 / FR-005a — abre modal de confirmação explícita (a11y).
            emailAlreadyUser.value = body.existing_user ?? { id: null, name: '?' };
        } else {
            errors.value = { _global: [body?.message ?? t('professionals.errors.save_failed')] };
        }
    } finally {
        saving.value = false;
    }
}

async function confirmEmailLink() {
    form.confirmed_existing_user = true;
    emailAlreadyUser.value = null;
    await submit();
}

function cancelEmailLink() {
    emailAlreadyUser.value = null;
    form.confirmed_existing_user = false;
}
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center px-4" @keydown="onKeydown">
            <div class="absolute inset-0 bg-black/40" @click="close"></div>
            <div
                ref="modalEl"
                role="dialog"
                aria-modal="true"
                :aria-label="isEdit ? t('professionals.form.title_edit') : t('professionals.form.title_create')"
                class="relative bg-surface-elevated rounded-xl shadow-popover w-full max-w-xl max-h-[90vh] overflow-y-auto"
            >
                <header class="flex items-center justify-between px-5 py-4 border-b border-border">
                    <h2 class="text-lg font-semibold text-foreground">
                        {{ isEdit ? t('professionals.form.title_edit') : t('professionals.form.title_create') }}
                    </h2>
                    <button type="button" :aria-label="t('professionals.form.cancel')" class="text-foreground-muted hover:text-foreground" @click="close">
                        <HeroIcon name="close" class="w-5 h-5" />
                    </button>
                </header>

                <form @submit.prevent="submit" class="p-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-foreground mb-1">
                            {{ t('professionals.form.name_label') }} <span class="text-danger-500">*</span>
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            maxlength="150"
                            :placeholder="t('professionals.form.name_placeholder')"
                            class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none"
                            :class="{ 'border-danger-500': errors.name }"
                        />
                        <p v-if="errors.name" class="mt-1 text-xs text-danger-500">{{ errors.name[0] }}</p>
                    </div>

                    <CouncilTypeSelect
                        v-model="form.council_type"
                        v-model:other-value="form.council_type_other"
                        :error="errors.council_type?.[0]"
                        :error-other="errors.council_type_other?.[0]"
                    />

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-1">
                                {{ t('professionals.form.council_number_label') }} <span class="text-danger-500">*</span>
                            </label>
                            <input
                                v-model="form.council_number"
                                type="text"
                                required
                                minlength="5"
                                maxlength="20"
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none"
                                :class="{ 'border-danger-500': errors.council_number }"
                            />
                            <p v-if="errors.council_number" class="mt-1 text-xs text-danger-500">{{ errors.council_number[0] }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-1">
                                {{ t('professionals.form.council_state_label') }} <span class="text-danger-500">*</span>
                            </label>
                            <select
                                v-model="form.council_state"
                                required
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none"
                                :class="{ 'border-danger-500': errors.council_state }"
                            >
                                <option v-for="uf in UFS" :key="uf" :value="uf">{{ uf }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-foreground mb-1">
                            {{ t('professionals.form.especialidade_label') }}
                        </label>
                        <input
                            v-model="form.especialidade"
                            type="text"
                            maxlength="100"
                            :placeholder="t('professionals.form.especialidade_placeholder')"
                            list="especialidades-list"
                            class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none"
                            @focus="store.fetchEspecialidades()"
                        />
                        <datalist id="especialidades-list">
                            <option v-for="esp in store.especialidadesSuggestions" :key="esp" :value="esp" />
                        </datalist>
                    </div>

                    <!-- Modo de vínculo (apenas em create) -->
                    <div v-if="!isEdit" class="space-y-2 pt-2 border-t border-border">
                        <label class="block text-sm font-medium text-foreground">
                            {{ t('professionals.form.link_mode_label') }} <span class="text-danger-500">*</span>
                        </label>
                        <div class="flex gap-3">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="radio" v-model="form.link_mode" value="existing" /> {{ t('professionals.form.link_mode_existing') }}
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="radio" v-model="form.link_mode" value="invite" /> {{ t('professionals.form.link_mode_invite') }}
                            </label>
                        </div>

                        <div v-if="form.link_mode === 'existing'">
                            <label class="block text-xs font-medium text-foreground-muted mb-1">{{ t('professionals.form.user_id_label') }}</label>
                            <input
                                v-model="form.user_id"
                                type="number"
                                :placeholder="t('professionals.form.user_id_placeholder')"
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none"
                                :class="{ 'border-danger-500': errors.user_id }"
                            />
                            <p v-if="errors.user_id" class="mt-1 text-xs text-danger-500">{{ errors.user_id[0] }}</p>
                        </div>

                        <div v-else>
                            <label class="block text-xs font-medium text-foreground-muted mb-1">{{ t('professionals.form.email_label') }}</label>
                            <input
                                v-model="form.email"
                                type="email"
                                :placeholder="t('professionals.form.email_placeholder')"
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none"
                                :class="{ 'border-danger-500': errors.email }"
                            />
                            <p v-if="errors.email" class="mt-1 text-xs text-danger-500">{{ errors.email[0] }}</p>
                        </div>
                    </div>

                    <div v-else class="text-xs text-foreground-muted italic">
                        {{ t('professionals.form.user_link_immutable_note') }}
                    </div>

                    <div v-if="errors._global" role="alert" class="text-xs text-danger-500">
                        {{ errors._global[0] }}
                    </div>

                    <footer class="flex justify-end gap-2 pt-4 border-t border-border">
                        <button type="button" class="px-4 py-2 rounded-lg border border-border text-sm text-foreground hover:bg-surface-muted" @click="close">
                            {{ t('professionals.form.cancel') }}
                        </button>
                        <button type="submit" :disabled="saving" class="px-4 py-2 rounded-lg bg-primary-700 text-white text-sm font-semibold hover:bg-primary-800 disabled:opacity-60">
                            {{ saving ? '...' : t('professionals.form.save') }}
                        </button>
                    </footer>
                </form>
            </div>

            <!-- Q2 / FR-005a — confirmação explícita de email já-é-user -->
            <EmailAlreadyUserModal
                :open="emailAlreadyUser !== null"
                :existing-user="emailAlreadyUser"
                :loading="saving"
                @confirm="confirmEmailLink"
                @cancel="cancelEmailLink"
            />
        </div>
    </Teleport>
</template>
