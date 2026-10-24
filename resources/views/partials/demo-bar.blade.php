@if (auth()->user()?->is_demo)
  <div class="demo-bar">
    <span><i class="bi bi-eye"></i> {{ __('demoBar') }}</span>
    <span class="demo-bar-links">
      <a href="{{ auth()->user()->is_admin ? '/demo' : '/demo/admin' }}">{{ auth()->user()->is_admin ? __('demoTryMailbox') : __('demoTryAdmin') }}</a>
      <a href="/#install">{{ __('demoGetIt') }}</a>
      <form method="POST" action="/logout">@csrf<input type="hidden" name="next" value="/"><button type="submit">{{ __('demoExit') }}</button></form>
    </span>
  </div>
@endif
