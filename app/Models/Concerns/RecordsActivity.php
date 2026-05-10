<?php

namespace App\Models\Concerns;

use App\Events\ModelLifecycleEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Trait que adiciona hooks Eloquent para disparar `ModelLifecycleEvent`
 * automaticamente nos ciclos `created`, `updated` e `deleted`.
 *
 * A `action` segue o padrão `<model_singular>.<lifecycle>` (snake_case).
 * Ex.: classe `User` → `user.created`, `user.updated`, `user.deleted`.
 *
 * Override opcional: implemente `auditActionFor(string $lifecycle): string`
 * na classe que usa o trait para customizar a action por lifecycle.
 *
 * Uso:
 *
 *     class User extends Model
 *     {
 *         use RecordsActivity;
 *     }
 *
 * NÃO está aplicado em nenhum Model nesta fase (Fase 0). Será ativado
 * nas user stories de domínio (Fases 2+).
 *
 * @mixin Model
 *
 * @see App\Events\ModelLifecycleEvent
 * @see specs/001-fundacao-multitenant/data-model.md § 9
 */
trait RecordsActivity
{
    /**
     * Hook automático do Laravel: chamado pelo `bootTraits()` do Model.
     */
    public static function bootRecordsActivity(): void
    {
        static::created(static function (Model $model): void {
            static::dispatchLifecycleEvent($model, 'created');
        });

        static::updated(static function (Model $model): void {
            static::dispatchLifecycleEvent($model, 'updated');
        });

        static::deleted(static function (Model $model): void {
            static::dispatchLifecycleEvent($model, 'deleted');
        });
    }

    /**
     * Resolve a action string e monta o payload antes de disparar o evento.
     */
    private static function dispatchLifecycleEvent(Model $model, string $lifecycle): void
    {
        $action = method_exists($model, 'auditActionFor')
            ? $model->auditActionFor($lifecycle)
            : static::buildDefaultAction($model, $lifecycle);

        $payload = static::buildLifecyclePayload($model, $lifecycle);

        event(new ModelLifecycleEvent($action, $payload, $model));
    }

    /**
     * Gera a action default: `<model_singular>.<lifecycle>`.
     * Ex.: `App\Models\User` → `user.created`.
     */
    private static function buildDefaultAction(Model $model, string $lifecycle): string
    {
        $singular = Str::snake(class_basename($model));

        return "{$singular}.{$lifecycle}";
    }

    /**
     * Monta o payload do snapshot.
     *
     * - `created`: `['new' => $attributes]` — sem `old` (era inexistente).
     * - `updated`: `['old' => $original, 'new' => $dirty]` — apenas diff.
     * - `deleted`: `['old' => $attributes]` — sem `new` (foi removido).
     *
     * @return array<string, mixed>
     */
    private static function buildLifecyclePayload(Model $model, string $lifecycle): array
    {
        return match ($lifecycle) {
            'created' => ['new' => $model->getAttributes()],
            'updated' => [
                'old' => $model->getOriginal(),
                'new' => $model->getDirty(),
            ],
            'deleted' => ['old' => $model->getAttributes()],
            default => [],
        };
    }
}
