@if (session('impersonator'))
  <form method="POST" action="{{ route('impersonate.stop') }}" class="impersonating-bar">
    @csrf
    <i class="bi bi-person-badge"></i> {{ __('admin.viewing', ['email' => auth()->user()->email]) }}
    <button type="submit">{{ __('admin.backToAdmin') }}</button>
  </form>
@endif
