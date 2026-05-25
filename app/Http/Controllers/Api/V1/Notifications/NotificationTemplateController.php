<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Domain\Messaging\Notification\Models\NotificationTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\StoreNotificationTemplateRequest;
use App\Http\Requests\Notifications\UpdateNotificationTemplateRequest;
use App\Http\Resources\Notifications\NotificationTemplateResource;
use App\Services\Notifications\NotificationTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Feature 013 — CRUD do catálogo de templates de notificação (US5).
 *
 * Permission-gated por `channel.connect` (Form Requests + checagem explícita
 * no destroy). Isolamento por tenant via global scope do model.
 */
class NotificationTemplateController extends Controller
{
    public function __construct(
        private readonly NotificationTemplateService $service,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('channel.connect');

        return NotificationTemplateResource::collection($this->service->list());
    }

    public function store(StoreNotificationTemplateRequest $request): JsonResponse
    {
        $template = $this->service->create($request->validated());

        return NotificationTemplateResource::make($template)
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateNotificationTemplateRequest $request,
        NotificationTemplate $notificationTemplate,
    ): NotificationTemplateResource {
        $template = $this->service->update($notificationTemplate, $request->validated());

        return NotificationTemplateResource::make($template);
    }

    public function destroy(NotificationTemplate $notificationTemplate): JsonResponse
    {
        Gate::authorize('channel.connect');

        $this->service->delete($notificationTemplate);

        return response()->json(status: 204);
    }
}
