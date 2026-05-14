<?php

namespace App\Http\Controllers\Api\V1\Agenda;

use App\Http\Controllers\Controller;
use App\Http\Resources\Agenda\CalendarSyncAccountResource;
use App\Models\Agenda\CalendarSyncAccount;
use App\Models\Professional;
use App\Services\Agenda\Calendar\GoogleCalendarOAuthService;
use App\Services\Agenda\Calendar\GoogleCalendarWatchService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * T167 — CalendarSyncController (US-6.7 — clarify nº 10/15 + R5).
 *
 * Outlook rejeitado com `provider_not_yet_supported` (clarify nº 11).
 */
class CalendarSyncController extends Controller
{
    public function __construct(
        private readonly GoogleCalendarOAuthService $oauth,
        private readonly GoogleCalendarWatchService $watch,
    ) {}

    public function connect(Request $request)
    {
        $request->validate([
            'professional_id' => ['required', 'integer', 'exists:professionals,id'],
            'provider' => ['required', 'in:google,outlook'],
        ]);

        if ($request->input('provider') === 'outlook') {
            return response()->json(['error' => 'provider_not_yet_supported'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $professional = Professional::findOrFail($request->input('professional_id'));

        if (! $request->user()->can('calendar_sync.configure')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return response()->json($this->oauth->buildAuthorizeUrl($professional));
    }

    public function callback(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
            'professional_id' => ['required', 'integer', 'exists:professionals,id'],
        ]);

        $professional = Professional::findOrFail($request->input('professional_id'));

        try {
            $account = $this->oauth->handleCallback(
                $professional,
                $request->input('code'),
                $request->input('state'),
            );

            // Subscribe watch channel (push notifications) após criar sub-cal
            $this->watch->subscribe($account);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new CalendarSyncAccountResource($account->fresh());
    }

    public function disconnect(Request $request, CalendarSyncAccount $calendarSyncAccount)
    {
        if (! $request->user()->can('calendar_sync.configure')
            || $request->user()->tenant_id !== $calendarSyncAccount->tenant_id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $this->oauth->disconnect($calendarSyncAccount);

        return new CalendarSyncAccountResource($calendarSyncAccount->fresh());
    }

    public function show(Request $request)
    {
        $account = CalendarSyncAccount::query()
            ->where('professional_id', $request->user()->professional?->id ?? 0)
            ->first();

        return response()->json([
            'data' => $account ? new CalendarSyncAccountResource($account) : null,
        ]);
    }
}
