@php
    $checklist = app(\App\Services\DocumentChecklistService::class)->forApplication($record);
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <style>
        .dc-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .dc-table th { text-align: left; font-weight: 600; padding: .4rem .5rem; border-bottom: 2px solid rgba(128,128,128,.3); }
        .dc-table td { padding: .45rem .5rem; border-bottom: 1px solid rgba(128,128,128,.2); vertical-align: top; }
        .dc-ok { color: #10b981; font-weight: 700; }
        .dc-miss { color: #f43f5e; font-weight: 700; }
        .dc-opt { opacity: .6; }
        .dc-note { margin-bottom: .6rem; padding: .5rem .75rem; border-left: 4px solid #f43f5e; border-radius: .4rem; background: rgba(244,63,94,.08); font-size: .85rem; }
        .dc-wrap { overflow-x: auto; }
    </style>

    @if ($checklist['missing_required'] > 0)
        <div class="dc-note">{{ $checklist['missing_required'] }} dokumen wajib belum diunggah.</div>
    @endif

    <div class="dc-wrap">
        <table class="dc-table">
            <thead>
                <tr><th>Dokumen</th><th>Status</th><th>Berkas</th><th>Diunggah</th></tr>
            </thead>
            <tbody>
                @foreach ($checklist['items'] as $item)
                    <tr>
                        <td>{{ $item['category'] }}@unless ($item['required']) <span class="dc-opt">(opsional)</span>@endunless</td>
                        <td>
                            @if ($item['version_id'])
                                <span class="dc-ok">✓ Ada</span>
                            @elseif ($item['required'])
                                <span class="dc-miss">✕ Belum diunggah</span>
                            @else
                                <span class="dc-opt">Tidak ada</span>
                            @endif
                        </td>
                        <td>
                            @if ($item['version_id'])
                                <a href="{{ route('documents.download', ['version' => $item['version_id']]) }}" target="_blank" rel="noopener" style="text-decoration: underline">
                                    {{ $item['filename'] }}
                                </a>
                                <span class="dc-opt">· {{ $item['size'] }}@if (($item['version_number'] ?? 1) > 1) · versi {{ $item['version_number'] }}@endif</span>
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $item['uploaded_at']?->translatedFormat('d M Y H:i') ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-dynamic-component>
