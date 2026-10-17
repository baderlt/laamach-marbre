<div class="space-y-4">
    <p>{{ $record->original_filename }} · {{ $record->mime }} · {{ $record->width }} × {{ $record->height }} · {{ number_format($record->bytes) }} bytes</p>
    <p>Source: {{ $record->source_path }}. Client supplied: {{ $record->client_supplied ? 'confirmed' : 'unconfirmed' }}. Usage: {{ $record->approval_state }}.</p>
    <p>Imported: {{ $record->imported_at }}. Approved: {{ $record->approved_at ?? 'Pending' }}.</p>
    <p>Checksum: {{ $record->checksum }}</p>
    <p>Active revision: {{ $record->active_revision ?? 'Private' }}</p>
    @if ($record->variants->isNotEmpty())
        <img src="{{ route('media.preview', $record) }}" alt="" width="160" loading="lazy">
    @endif
    <a href="{{ route('media.original', $record) }}">Download private original (authorized administrators only)</a>
    <h3>Generated variants</h3>
    <ul>
        @foreach ($record->variants as $variant)
            <li>{{ $variant->profile }} · {{ $variant->profile_version }} · {{ $variant->format }} · {{ $variant->width }} × {{ $variant->height }} · {{ $variant->bytes }} bytes · {{ $variant->state }} · {{ $variant->checksum }}</li>
        @endforeach
    </ul>
    <h3>Usage / staged mappings</h3>
    <ul>
        @foreach ($record->references as $reference)
            <li>{{ $reference->owner_type }} / {{ $reference->owner_key }} / {{ $reference->slot }} ({{ $reference->media_variant_id ? 'variant reference' : 'asset reference' }})</li>
        @endforeach
        @foreach ($record->imports as $import)
            <li>{{ $import->source }} → {{ $import->mapping['target_type'] }} / {{ $import->mapping['target_key'] }} / {{ $import->mapping['role'] }} (staged, no business record attached)</li>
        @endforeach
    </ul>
    <p>Deletion is protected for references, staged imports and retained published variants.</p>
</div>
