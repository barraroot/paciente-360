<x-filament-panels::page>
    @php
        $data = $this->getViewData();
        $anomalies = $data['anomalies'];
        $counts = $data['counts'];
        $onlyOpen = $data['only_open'];
    @endphp

    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded border bg-white p-4 dark:bg-gray-800">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Abertas</h3>
                <p class="mt-2 text-3xl font-bold">{{ $counts['open'] }}</p>
            </div>
            <div class="rounded border bg-rose-50 border-rose-200 p-4 dark:bg-rose-950 dark:border-rose-900">
                <h3 class="text-sm font-medium text-rose-700 dark:text-rose-300">Críticas abertas</h3>
                <p class="mt-2 text-3xl font-bold text-rose-700 dark:text-rose-200">{{ $counts['critical_open'] }}</p>
            </div>
            <div class="rounded border bg-white p-4 dark:bg-gray-800">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Detectadas (últimas 24h)</h3>
                <p class="mt-2 text-3xl font-bold">{{ $counts['last_24h'] }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded border bg-white dark:bg-gray-800">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-2">Categoria</th>
                        <th class="px-4 py-2">Tenant</th>
                        <th class="px-4 py-2">Severidade</th>
                        <th class="px-4 py-2">Detectada</th>
                        <th class="px-4 py-2">Notificada via</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    @forelse ($anomalies as $a)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 py-2">
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs dark:bg-gray-700">
                                    {{ $a->categoria->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-2 font-mono text-xs">
                                {{ $a->tenant_id !== null ? '#' . $a->tenant_id : 'Global' }}
                            </td>
                            <td class="px-4 py-2">
                                @if ($a->severity->isCritical())
                                    <span class="rounded border border-rose-200 bg-rose-50 px-2 py-0.5 text-xs text-rose-800">Critical</span>
                                @else
                                    <span class="rounded border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs text-amber-800">Warning</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-xs">{{ $a->detected_at->diffForHumans() }}</td>
                            <td class="px-4 py-2 text-xs">{{ implode(', ', $a->notified_via ?? []) }}</td>
                            <td class="px-4 py-2 text-xs">
                                @if ($a->resolved_at)
                                    <span class="text-emerald-700">✓ Resolvida</span>
                                @elseif ($a->acknowledged_at)
                                    <span class="text-blue-700">Reconhecida</span>
                                @else
                                    <span class="text-amber-700">Aberta</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right text-xs">
                                @if (! $a->acknowledged_at)
                                    <button wire:click="acknowledgeAnomaly({{ $a->id }})"
                                            class="mr-2 text-blue-700 underline hover:text-blue-900">Reconhecer</button>
                                @endif
                                @if (! $a->resolved_at)
                                    <button wire:click="resolveAnomaly({{ $a->id }})"
                                            class="text-emerald-700 underline hover:text-emerald-900">Resolver</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">
                                @if ($onlyOpen)
                                    Nenhuma anomalia aberta. 🎉
                                @else
                                    Nenhuma anomalia registrada.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
