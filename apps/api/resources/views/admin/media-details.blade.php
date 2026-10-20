<div class="space-y-4" style="overflow-wrap:anywhere">
    <p>{{ $record->original_filename }} · {{ $record->mime }} · {{ $record->width }} × {{ $record->height }} · {{ number_format($record->bytes) }} {{ __('admin.bytes') }}</p>
    <p>{{ __('admin.source') }}: {{ $record->source_path }}. {{ __('admin.field_client_supplied') }}: {{ __($record->client_supplied ? 'admin.confirmed' : 'admin.unconfirmed') }}.</p>
    <p>{{ __('admin.field_processing_state') }}: {{ __($record->processing_state === 'pending' ? 'admin.uploaded' : 'admin.'.$record->processing_state) }} · {{ __('admin.field_approval_state') }}: {{ __('admin.'.$record->approval_state) }}</p>
    <p>{{ __('admin.imported_at') }}: {{ $record->imported_at }}. {{ __('admin.approved_at') }}: {{ $record->approved_at ?? __('admin.pending') }}.</p>
    <details><summary>{{ __('admin.checksum') }}</summary>{{ $record->checksum }}</details>
    <p>{{ __('admin.publication') }}: {{ __($record->active_revision ? 'admin.published' : 'admin.private') }}</p>
    @if ($record->type === 'image' && $record->variants->isNotEmpty())
        <img src="{{ route('media.preview', $record) }}" alt="" width="160" loading="lazy">
    @endif
    <a href="{{ route('media.original', $record) }}">{{ __('admin.download_original') }}</a>
    <details><summary>{{ __('admin.variants') }}</summary>
    <ul>
        @foreach ($record->variants as $variant)
            <li>{{ $variant->profile }} · {{ $variant->profile_version }} · {{ $variant->format }} · {{ $variant->width }} × {{ $variant->height }} · {{ $variant->bytes }} {{ __('admin.bytes') }} · {{ __('admin.'.$variant->state) }}</li>
        @endforeach
    </ul></details>
    <h3>{{ __('admin.usages') }}</h3>
    <ul>
        @forelse ($record->references as $reference)
            <li>{{ __('admin.owner_'.$reference->owner_type) }} / {{ $reference->owner_type === 'site_contents' ? __('admin.page_'.$reference->owner_key) : ($owners[$reference->owner_type][$reference->owner_key] ?? $reference->owner_key) }} / {{ $reference->slot }} ({{ __($reference->media_variant_id ? 'admin.variant_reference' : 'admin.asset_reference') }})</li>
        @empty
            <li>{{ __('admin.no_records') }}</li>
        @endforelse
        @foreach ($record->imports as $import)
            <li>{{ $import->source }} → {{ $import->mapping['target_type'] }} / {{ $import->mapping['target_key'] }} / {{ $import->mapping['role'] }} ({{ __('admin.staged') }})</li>
        @endforeach
    </ul>
    <p>{{ __('admin.retention_help') }}</p>
</div>
