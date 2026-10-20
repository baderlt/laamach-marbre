<x-filament-widgets::widget>
    <x-filament::section :heading="__('admin.recent_updates')">
        <ul class="admin-recent-list">
            @forelse ($updates as $update)
                <li><a href="{{ $update['url'] }}">{{ $update['type'] }} · {{ $update['title'] }}</a><time datetime="{{ $update['date']->toIso8601String() }}">{{ $update['date']->locale(app()->getLocale())->diffForHumans() }}</time></li>
            @empty
                <li>{{ __('admin.no_updates') }}</li>
            @endforelse
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
