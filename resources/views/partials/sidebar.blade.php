<aside class="sidebar">
  <a class="side-brand" href="{{ route('login') }}">
    @include('partials.logo', ['class' => 'sm'])
    <span><strong>{{ \App\Support\Brand::wordmark() }}</strong>@if (\App\Support\Brand::product())<span>{{ \App\Support\Brand::product() }}</span>@endif</span>
  </a>
  <div class="side-account" title="{{ $user->email }}"><i class="bi bi-person-circle"></i> {{ $user->email }}</div>
  @if ($withFolders)
    <button class="compose" type="button" data-action="compose"><i class="bi bi-pencil-square"></i> <span data-i18n="compose">{{ __('compose') }}</span></button>
    <div class="nav-scroll" id="nav"></div>
  @else
    <a class="compose" href="{{ route('login') }}" style="text-decoration:none"><i class="bi bi-arrow-left"></i> <span data-i18n="backToInbox">{{ __('backToInbox') }}</span></a>
    <div class="nav-scroll"></div>
  @endif
  <div class="side-bottom">
    <a class="nav-item" href="/settings#profile"><i class="bi bi-gear"></i><span data-i18n="settings">{{ __('settings') }}</span></a>
    <form method="POST" action="/logout">
      @csrf
      <button class="nav-item" type="submit"><i class="bi bi-box-arrow-right"></i><span data-i18n="signOut">{{ __('signOut') }}</span></button>
    </form>
  </div>
</aside>
