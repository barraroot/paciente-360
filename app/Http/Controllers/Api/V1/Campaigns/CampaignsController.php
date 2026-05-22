<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Campaigns;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Services\CampaignBuilder;
use App\Domain\Campaigns\Services\CampaignDispatcher;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaigns\CancelCampaignRequest;
use App\Http\Requests\Campaigns\CreateCampaignRequest;
use App\Http\Requests\Campaigns\DispatchCampaignRequest;
use App\Http\Requests\Campaigns\UpdateCampaignRequest;
use App\Http\Resources\Campaigns\CampaignReportResource;
use App\Http\Resources\Campaigns\CampaignResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * **T165 (Fase 8 — Lote C US-9.1)** — API REST de campanhas.
 *
 * Endpoints (group `/api/v1/campaigns`):
 *   - GET    /campaigns                         → index (paginated + filters)
 *   - POST   /campaigns                         → store
 *   - GET    /campaigns/{campaign}              → show
 *   - PATCH  /campaigns/{campaign}              → update (draft/scheduled only)
 *   - POST   /campaigns/{campaign}/preview      → preview audience + warnings
 *   - POST   /campaigns/{campaign}/dispatch     → dispatch (enfileira pipeline)
 *   - POST   /campaigns/{campaign}/cancel       → cancel
 *   - GET    /campaigns/{campaign}/report       → relatório agregado
 */
class CampaignsController extends Controller
{
    public function __construct(
        private readonly CampaignBuilder $builder,
        private readonly CampaignDispatcher $dispatcher,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Campaign::class);

        $query = Campaign::query()->orderByDesc('created_at');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($channel = $request->string('channel')->toString()) {
            $query->where('channel', $channel);
        }

        $perPage = min((int) $request->integer('per_page', 25), 100);

        return CampaignResource::collection($query->paginate($perPage));
    }

    public function show(Campaign $campaign): CampaignResource
    {
        Gate::authorize('view', $campaign);

        return new CampaignResource($campaign);
    }

    public function store(CreateCampaignRequest $request): JsonResponse
    {
        Gate::authorize('create', Campaign::class);

        $tenant = app('tenant');
        $campaign = $this->builder->create($tenant, $request->user(), $request->validated());

        return (new CampaignResource($campaign))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): CampaignResource
    {
        // Authorize já feito no FormRequest via Policy.
        $data = $request->validated();

        $campaign->update($data);

        return new CampaignResource($campaign->refresh());
    }

    public function preview(Campaign $campaign): JsonResponse
    {
        Gate::authorize('view', $campaign);

        $preview = $this->builder->preview($campaign);

        return response()->json([
            'campaign_id' => $campaign->id,
            'eligible_count' => $preview['eligible_count'],
            'warnings' => $preview['warnings'],
        ]);
    }

    public function dispatchCampaign(DispatchCampaignRequest $request, Campaign $campaign): CampaignResource
    {
        $this->dispatcher->dispatch($campaign);

        return new CampaignResource($campaign->refresh());
    }

    public function cancel(CancelCampaignRequest $request, Campaign $campaign): CampaignResource
    {
        $reason = $request->string('reason')->toString() ?: null;

        $this->builder->cancel($campaign, $request->user(), $reason);

        return new CampaignResource($campaign->refresh());
    }

    public function report(Campaign $campaign): CampaignReportResource
    {
        Gate::authorize('view', $campaign);

        return new CampaignReportResource($campaign);
    }
}
