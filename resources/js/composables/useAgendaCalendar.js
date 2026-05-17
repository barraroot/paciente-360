/**
 * useAgendaCalendar — wrapper FullCalendar v6 para AgendaPage (US-6.3 / clarify nº 9).
 *
 * Responsabilidades:
 *  - Mapeia consultas da store → eventos FullCalendar.
 *  - Expõe handlers de drag-create (select), drag-to-move (eventChange) e click.
 *  - Retorna `calendarOptions` reativo para ser passado direto ao <FullCalendar>.
 *  - Gerencia TZ via prop `timeZone` do FullCalendar (Luxon-aware).
 *
 * Não faz chamadas de API diretamente — delega para callbacks recebidos
 * como parâmetros, mantendo separação de responsabilidades.
 */
import { computed } from 'vue'
import { useTimezoneRenderer } from '@/composables/useTimezoneRenderer'

/** Mapeamento de status → cor de bloco no calendário. */
const STATUS_COLORS = {
    agendada: { backgroundColor: '#0f766e', borderColor: '#0d6560', textColor: '#fff' },
    confirmada: { backgroundColor: '#0369a1', borderColor: '#025f91', textColor: '#fff' },
    realizada: { backgroundColor: '#4b5563', borderColor: '#374151', textColor: '#fff' },
    nao_realizada: { backgroundColor: '#b91c1c', borderColor: '#991b1b', textColor: '#fff' },
    cancelada: { backgroundColor: '#9ca3af', borderColor: '#6b7280', textColor: '#fff' },
}

/**
 * @param {{
 *   appointments: import('vue').Ref<any[]>,
 *   view: import('vue').Ref<'day'|'week'>,
 *   multiProf: import('vue').Ref<boolean>,
 *   onSlotClick: (startsAt: string, endsAt: string) => void,
 *   onEventClick: (appointment: any) => void,
 *   onEventDrop: (appointment: any, newStartsAt: string, revert: () => void) => void,
 *   timezone?: string,
 * }} options
 */
export function useAgendaCalendar({
    appointments,
    view,
    multiProf,
    onSlotClick,
    onEventClick,
    onEventDrop,
    timezone = 'America/Sao_Paulo',
}) {
    const renderer = useTimezoneRenderer(timezone)

    /** Converte consulta da API em evento FullCalendar. */
    function appointmentToEvent(apt) {
        const colors = STATUS_COLORS[apt.status] ?? STATUS_COLORS.agendada
        return {
            id: String(apt.id),
            title: apt.paciente_nome ?? `Consulta #${apt.id}`,
            start: apt.starts_at,
            end: apt.ends_at,
            extendedProps: { appointment: apt },
            ...colors,
            classNames: ['fc-event-paciente360'],
        }
    }

    /** Lista de eventos mapeados. */
    const events = computed(() => (appointments.value ?? []).map(appointmentToEvent))

    /** View FullCalendar correspondente ao toggle diária/semanal. */
    const fcView = computed(() => {
        // resourceTimeGridWeek requer @fullcalendar/resource (P2 backlog)
        if (view.value === 'day') return 'timeGridDay'
        return 'timeGridWeek'
    })

    /** Handler de seleção de slot vazio (drag-create / click em célula). */
    function handleSelect(selectInfo) {
        selectInfo.view.calendar.unselect()
        if (typeof onSlotClick === 'function') {
            onSlotClick(selectInfo.startStr, selectInfo.endStr)
        }
    }

    /** Handler de click em evento existente. */
    function handleEventClick(clickInfo) {
        const apt = clickInfo.event.extendedProps?.appointment
        if (apt && typeof onEventClick === 'function') {
            onEventClick(apt)
        }
    }

    /**
     * Handler de drag-to-move.
     * Passa `revert` para que o chamador possa snap-back em caso de rejeição.
     */
    function handleEventChange(changeInfo) {
        const apt = changeInfo.event.extendedProps?.appointment
        const newStartsAt = changeInfo.event.startStr
        if (apt && typeof onEventDrop === 'function') {
            onEventDrop(apt, newStartsAt, changeInfo.revert)
        }
    }

    /** Toolbar responsiva: compacta em telas pequenas. */
    const headerToolbar = computed(() => ({
        left: 'prev,next today',
        center: 'title',
        right: '', // view toggle é feito pelos botões customizados da AgendaPage
    }))

    const calendarOptions = computed(() => ({
        plugins: [], // plugins são passados no componente via prop estática
        initialView: fcView.value,
        timeZone: timezone,
        locale: 'pt-br',
        firstDay: 1, // segunda-feira
        slotMinTime: '07:00:00',
        slotMaxTime: '20:00:00',
        slotDuration: '00:30:00',
        snapDuration: '00:15:00',
        allDaySlot: false,
        selectable: true,
        selectMirror: true,
        editable: true,
        eventDurationEditable: false,
        nowIndicator: true,
        height: 'auto',
        headerToolbar: headerToolbar.value,
        buttonText: {
            today: 'Hoje',
            prev: '<',
            next: '>',
        },
        select: handleSelect,
        eventClick: handleEventClick,
        eventChange: handleEventChange,
        events: events.value,
        // Textos pt-BR nos tooltips de slot
        selectConstraint: undefined,
        noEventsText: 'Nenhuma consulta neste período.',
        // a11y: FullCalendar v6 já gera aria-label nos eventos
    }))

    return {
        calendarOptions,
        events,
        fcView,
        renderer,
        appointmentToEvent,
    }
}
