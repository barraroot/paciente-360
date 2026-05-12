<?php

namespace App\Http\Requests\Inbox;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T117 — FormRequest para listagem de conversas com filtros.
 *
 * Valida filtros de status, canal, atendente, paciente, IA pausada,
 * datas de atividade e query de busca (mínimo 2 chars).
 */
final class ListConversationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'array'],
            'status.*' => ['string', 'in:aberta,pendente,resolvida,reaberta'],
            'channel_id' => ['sometimes', 'nullable', 'integer'],
            'channel_type' => ['sometimes', 'nullable', 'string', 'in:whatsapp,instagram,web'],
            'assigned_user_id' => ['sometimes', 'nullable', 'string'],
            'patient_id' => ['sometimes', 'nullable', 'integer'],
            'ai_paused' => ['sometimes', 'nullable', 'in:true,false,1,0'],
            'last_activity_from' => ['sometimes', 'nullable'],
            'last_activity_to' => ['sometimes', 'nullable'],
            'q' => ['sometimes', 'nullable', 'string', 'min:2'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
