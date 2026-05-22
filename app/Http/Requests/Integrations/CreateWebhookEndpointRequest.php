<?php

declare(strict_types=1);

namespace App\Http\Requests\Integrations;

use App\Domain\Integrations\Models\WebhookEndpoint;
use App\Support\Url\UrlGuard;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * **T197 (Fase 8 — Lote D US-11.1)** — Validação do create webhook.
 *
 * Combina: validações Laravel padrão + UrlGuard SSRF (T007) + verificação
 * de limite do plano (`plans.webhook_max_endpoints`).
 */
class CreateWebhookEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('webhook.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url:http,https', 'max:2048'],
            'events_subscribed' => ['required', 'array', 'min:1'],
            'events_subscribed.*' => ['string', 'max:120', Rule::in($this->allowedEvents())],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function passedValidation(): void
    {
        // SSRF defense.
        UrlGuard::assertSafeOutboundUrl($this->input('url'));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $tenant = app()->bound('tenant') ? app('tenant') : null;
            if ($tenant === null) {
                return;
            }

            $plan = $tenant->currentPlan ?? null;
            $max = (int) ($plan?->webhook_max_endpoints
                ?? config('finalization.webhook_max_endpoints_default', 5));

            $current = WebhookEndpoint::query()
                ->where('tenant_id', $tenant->id)
                ->count();

            if (! $this->isMethod('PUT') && ! $this->isMethod('PATCH') && $current >= $max) {
                $v->errors()->add('plan', "Limite de {$max} endpoints atingido para o plano atual.");
            }
        });
    }

    /**
     * @return array<int, string>
     */
    public function allowedEvents(): array
    {
        return [
            '*',
            'agendamento.criado', 'agendamento.confirmado', 'agendamento.cancelado', 'agendamento.reagendado',
            'paciente.criado', 'paciente.atualizado',
            'mensagem.recebida', 'mensagem.enviada',
            'prescricao.criada', 'prescricao.renovada',
            'campanha.disparada',
            'consentimento.registrado', 'consentimento.revogado',
        ];
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Sem permissão para gerenciar webhooks.',
        ], 403));
    }
}
