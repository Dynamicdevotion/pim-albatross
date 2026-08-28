@php
    use Illuminate\Support\Str;

    $fieldOrder = ['sku', 'name', 'description', 'price', 'stock', 'weight', 'length', 'width', 'height', 'status'];
    $used = collect($rows)
        ->flatMap(fn ($r) => array_keys($r['values']))
        ->unique()
        ->sortBy(fn ($f) => array_search($f, $fieldOrder, true))
        ->values()
        ->all();
@endphp

<div class="rounded-xl bg-white p-4 text-sm shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
    <p class="mb-3 text-gray-600 dark:text-gray-400">
        {{ __('pim.import.preview.intro', ['shown' => count($rows), 'total' => $total ?? count($rows)]) }}
        —
        <span class="font-medium">
            {{ $updateExisting ? __('pim.import.preview.update_on') : __('pim.import.preview.update_off') }}
        </span>
    </p>

    @if (count($rows) === 0)
        <p class="text-gray-500">{{ __('pim.import.preview.empty') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                        <th class="py-2 pe-3">#</th>
                        @foreach ($used as $field)
                            <th class="py-2 pe-3">{{ __('pim.import.field.' . $field) }}</th>
                        @endforeach
                        <th class="py-2 ps-3">{{ __('pim.import.preview.outcome') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php $outcome = $row['outcome']; @endphp
                        <tr class="border-b border-gray-100 last:border-0 dark:border-white/5">
                            <td class="py-2 pe-3 tabular-nums text-gray-400">{{ $row['line'] }}</td>
                            @foreach ($used as $field)
                                <td class="py-2 pe-3">{{ Str::limit($row['values'][$field] ?? '', 40) ?: '—' }}</td>
                            @endforeach
                            <td class="py-2 ps-3">
                                @if ($outcome->action === 'created')
                                    <x-filament::badge color="success">{{ __('pim.import.preview.will_create') }}</x-filament::badge>
                                @elseif ($outcome->action === 'updated')
                                    <x-filament::badge color="warning">{{ __('pim.import.preview.will_update') }}</x-filament::badge>
                                @else
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <x-filament::badge color="danger">{{ __('pim.import.preview.will_skip') }}</x-filament::badge>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $outcome->reason }}</span>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
