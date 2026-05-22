<x-filament-panels::page>
    @php
        $data = $this->getViewData();
        $audits = $data['audits'];
        $latestStatic = $data['latest_static'];
        $latestReplay = $data['latest_replay'];
    @endphp

    <div class="space-y-6">
        {{-- Status cards --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="rounded border bg-white p-4 dark:bg-gray-800">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Última auditoria estática</h3>
                @if ($latestStatic)
                    <p class="mt-2 text-2xl font-semibold">
                        {{ $latestStatic->isCompliant() ? '✅ COMPLIANT' : '❌ ' . $latestStatic->non_conformant_events . ' não-conformidade(s)' }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500">{{ $latestStatic->audited_at->diffForHumans() }} — {{ $latestStatic->total_events_scanned }} eventos varridos</p>
                @else
                    <p class="mt-2 text-sm text-gray-500">Nenhuma auditoria estática executada ainda.</p>
                @endif
            </div>

            <div class="rounded border bg-white p-4 dark:bg-gray-800">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Última auditoria runtime (replay)</h3>
                @if ($latestReplay)
                    <p class="mt-2 text-2xl font-semibold">
                        {{ $latestReplay->isCompliant() ? '✅ COMPLIANT' : '❌ ' . $latestReplay->non_conformant_events . ' finding(s)' }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ $latestReplay->audited_at->diffForHumans() }} — amostra {{ $latestReplay->sample_size }} / scanned {{ $latestReplay->total_events_scanned }}
                    </p>
                @else
                    <p class="mt-2 text-sm text-gray-500">Nenhuma auditoria runtime executada ainda — cron `privacy:audit-pseudonymization-weekly` segundas 04:00 BRT.</p>
                @endif
            </div>
        </div>

        {{-- Histórico --}}
        <div class="overflow-hidden rounded border bg-white dark:bg-gray-800">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-2">Data</th>
                        <th class="px-4 py-2">Modo</th>
                        <th class="px-4 py-2">Auditado por</th>
                        <th class="px-4 py-2 text-right">Escaneados</th>
                        <th class="px-4 py-2 text-right">Findings</th>
                        <th class="px-4 py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    @forelse ($audits as $audit)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 py-2 text-xs">{{ $audit->audited_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs dark:bg-gray-700">{{ $audit->mode->label() }}</span>
                            </td>
                            <td class="px-4 py-2 text-xs">{{ $audit->audited_by_user_id ? '#' . $audit->audited_by_user_id : 'Automático (cron)' }}</td>
                            <td class="px-4 py-2 text-right font-mono text-xs">{{ $audit->total_events_scanned }}</td>
                            <td class="px-4 py-2 text-right font-mono text-xs">{{ $audit->non_conformant_events }}</td>
                            <td class="px-4 py-2">
                                @if ($audit->isCompliant())
                                    <span class="rounded border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs text-emerald-800">Compliant</span>
                                @else
                                    <span class="rounded border border-rose-200 bg-rose-50 px-2 py-0.5 text-xs text-rose-800">Non-compliant</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">
                                Nenhuma auditoria registrada. Use o botão acima para disparar uma auditoria estática ad-hoc.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Findings da última runtime --}}
        @if ($latestReplay && $latestReplay->hasFindings())
            <div class="rounded border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200">
                <h3 class="font-semibold">Findings da última auditoria runtime</h3>
                <p class="mt-1 text-xs">Cada finding aponta um payload de audit_log com PII em texto plano. Investigar e mascarar no design do evento.</p>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-xs">
                    @foreach (array_slice($latestReplay->findings ?? [], 0, 10) as $f)
                        <li>
                            <code>{{ $f['action'] ?? '?' }}</code> em
                            <code>{{ $f['field_path'] ?? '?' }}</code> —
                            pattern <strong>{{ $f['pattern'] ?? '?' }}</strong>
                            (audit_log #{{ $f['audit_log_id'] ?? '?' }})
                        </li>
                    @endforeach
                </ul>
                @if (count($latestReplay->findings ?? []) > 10)
                    <p class="mt-2 text-xs text-rose-700">+ {{ count($latestReplay->findings) - 10 }} finding(s) adicional(is) ocultos.</p>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
