/**
 * T087 — Hook para reservar slot pessimista soft com heartbeat (clarify nº 2).
 *
 * Mantém a reserva ativa enquanto o form está aberto. Libera explicitamente
 * ao fechar (release_reason=canceled).
 */
import { ref, onUnmounted } from 'vue'
import api from '@/lib/api'

export function useSlotReservation() {
  const reservation = ref(null)
  const error = ref(null)

  async function reserve({ professionalId, appointmentTypeId, startsAt, holderType = 'user', holderId }) {
    error.value = null
    try {
      const { data } = await api.post(`/agenda/slots/${encodeURIComponent(startsAt)}/reservar`, {
        professional_id: professionalId,
        appointment_type_id: appointmentTypeId,
        holder_type: holderType,
        holder_id: holderId,
      })
      reservation.value = data.data
      return reservation.value
    } catch (e) {
      error.value = e.response?.data || { error: 'unknown' }
      throw e
    }
  }

  async function release() {
    if (!reservation.value) return
    try {
      await api.delete(`/agenda/slot-reservations/${reservation.value.id}`)
    } finally {
      reservation.value = null
    }
  }

  onUnmounted(() => {
    if (reservation.value) {
      release()
    }
  })

  return { reservation, error, reserve, release }
}
