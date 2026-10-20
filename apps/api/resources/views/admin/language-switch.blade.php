<nav aria-label="{{ __('admin.interface_language') }}" class="admin-language-switch">
    @foreach (['fr' => 'Français', 'ar' => 'العربية'] as $code => $label)
        <form method="post" action="{{ route('admin.language') }}">
            @csrf
            <input type="hidden" name="locale" value="{{ $code }}">
            <button type="submit" lang="{{ $code }}" dir="{{ $code === 'ar' ? 'rtl' : 'ltr' }}" aria-current="{{ app()->getLocale() === $code ? 'true' : 'false' }}" aria-label="{{ __('admin.switch_language', ['language' => $label]) }}">{{ $label }}</button>
        </form>
    @endforeach
</nav>
