@php
    $progressService = app(\App\Services\ApplicationProgressService::class);
    $progress = $record instanceof \App\Models\Application
        ? $progressService->build($record)
        : $progressService->forChild($record);

    $colors = [
        'done' => '#10b981',
        'current' => '#3b82f6',
        'pending' => '#9ca3af',
        'revision' => '#f59e0b',
        'rejected' => '#f43f5e',
    ];
    $stateText = [
        'done' => 'Selesai',
        'current' => 'Sedang berjalan',
        'pending' => 'Belum',
        'revision' => 'Perlu revisi',
        'rejected' => 'Ditolak',
    ];
    $icons = ['done' => '✓', 'current' => '●', 'pending' => '', 'revision' => '!', 'rejected' => '✕'];
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">

<style>
    .ap-steps { display: flex; align-items: flex-start; gap: 0; margin: .25rem 0 1rem; }
    .ap-step { flex: 1; text-align: center; position: relative; padding: 0 .5rem; }
    .ap-step:not(:last-child)::after {
        content: ''; position: absolute; top: 1.1rem; left: calc(50% + 1.4rem); right: calc(-50% + 1.4rem);
        height: 3px; background: var(--ap-line, rgba(128,128,128,.35));
    }
    .ap-step.is-done:not(:last-child)::after { background: #10b981; }
    .ap-dot {
        width: 2.2rem; height: 2.2rem; border-radius: 9999px; margin: 0 auto .4rem;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 700; font-size: .95rem;
    }
    .ap-label { font-weight: 600; font-size: .85rem; }
    .ap-sub { font-size: .75rem; opacity: .7; }
    .ap-banner { border-left: 4px solid; border-radius: .5rem; padding: .75rem 1rem; margin-bottom: 1rem; background: rgba(128,128,128,.08); }
    .ap-banner strong { display: block; }
    .ap-hist { list-style: none; margin: 0; padding: 0; }
    .ap-hist li { display: flex; gap: .75rem; padding: .5rem 0; border-top: 1px solid rgba(128,128,128,.2); font-size: .85rem; }
    .ap-hist time { min-width: 9.5rem; opacity: .7; }
    @media (max-width: 640px) {
        .ap-steps { flex-direction: column; gap: .75rem; }
        .ap-step { display: flex; align-items: center; gap: .75rem; text-align: left; padding: 0; }
        .ap-step::after { display: none; }
        .ap-dot { margin: 0; flex-shrink: 0; }
        .ap-hist li { flex-direction: column; gap: .1rem; }
    }
</style>

@if (! $progress)
    <p class="ap-sub">Belum ada permohonan untuk anak ini.</p>
@else
    <div>
        <p class="ap-sub" style="margin-bottom:.5rem">
            Periode {{ $progress['period_year'] }} · Status: <strong>{{ $progress['status']->label() }}</strong>
        </p>

        <div class="ap-steps">
            @foreach ($progress['steps'] as $i => $step)
                <div class="ap-step is-{{ $step['state'] }}">
                    <div class="ap-dot" style="background: {{ $colors[$step['state']] }}">{{ $icons[$step['state']] !== '' ? $icons[$step['state']] : $i + 1 }}</div>
                    <div>
                        <div class="ap-label">{{ $step['label'] }}</div>
                        <div class="ap-sub">
                            {{ $stateText[$step['state']] }}@if ($step['at']) · {{ $step['at']->translatedFormat('d M Y') }}@endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($progress['banner'])
            @php $tone = $progress['banner']['tone'] === 'danger' ? $colors['rejected'] : $colors['revision']; @endphp
            <div class="ap-banner" style="border-color: {{ $tone }}">
                <strong>{{ $progress['banner']['title'] }}</strong>
                @if ($progress['banner']['note'])
                    <span>{{ $progress['banner']['note'] }}</span>
                @endif
                <span class="ap-sub">
                    @if ($progress['banner']['by']) {{ $progress['banner']['by'] }} · @endif
                    {{ $progress['banner']['at']?->translatedFormat('d M Y H:i') }}
                </span>
            </div>
        @endif

        @if (count($progress['history']))
            <div class="ap-label" style="margin-bottom:.25rem">Riwayat tindakan</div>
            <ul class="ap-hist">
                @foreach ($progress['history'] as $item)
                    <li>
                        <time>{{ $item['at']?->translatedFormat('d M Y H:i') }}</time>
                        <div>
                            <strong>{{ $item['label'] }}</strong>
                            @if ($item['by']) <span class="ap-sub"> · {{ $item['by'] }}</span> @endif
                            @if ($item['note']) <div>{{ $item['note'] }}</div> @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
</x-dynamic-component>
