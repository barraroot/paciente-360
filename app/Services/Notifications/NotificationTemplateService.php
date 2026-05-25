<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Domain\Messaging\Notification\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Collection;

/**
 * Feature 013 — Regras de negócio do catálogo de templates (US5).
 *
 * Todas as operações são escopadas ao tenant ativo via global scope
 * (`BelongsToTenant`) — Princípio II.
 */
final class NotificationTemplateService
{
    /**
     * @return Collection<int, NotificationTemplate>
     */
    public function list(): Collection
    {
        return NotificationTemplate::query()->orderBy('notification_type')->get();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): NotificationTemplate
    {
        return NotificationTemplate::create([
            'notification_type' => $data['notification_type'],
            'channel_type' => $data['channel_type'],
            'provider_template_id' => $data['provider_template_id'],
            'language' => $data['language'],
            'variables_map' => $data['variables_map'] ?? [],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(NotificationTemplate $template, array $data): NotificationTemplate
    {
        $template->fill(array_filter(
            $data,
            fn (string $key) => in_array($key, ['provider_template_id', 'language', 'variables_map', 'is_active'], true),
            ARRAY_FILTER_USE_KEY,
        ));
        $template->save();

        return $template->refresh();
    }

    public function delete(NotificationTemplate $template): void
    {
        $template->delete();
    }
}
