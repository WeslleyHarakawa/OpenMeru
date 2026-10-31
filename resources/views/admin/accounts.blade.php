@extends('admin.layout')

@section('title', __('admin.accounts'))

@section('content')
  <section class="panel">
    <h2><i class="bi bi-person-plus"></i> {{ __('admin.newMailbox') }}</h2>
    <form method="POST" action="{{ route('admin.accounts.store') }}" class="row g-3" autocomplete="off">
      @csrf
      <div class="col-md-4">
        <label class="form-label" for="name">{{ __('name') }}</label>
        <input class="form-control" id="name" name="name" value="{{ old('name') }}" required maxlength="120" placeholder="{{ __('admin.namePh') }}">
      </div>
      <div class="col-md-4">
        <label class="form-label" for="local">{{ __('email') }}</label>
        <div class="input-group">
          <input class="form-control" id="local" name="local" value="{{ old('local') }}" required maxlength="64" pattern="[A-Za-z0-9]([A-Za-z0-9._\-]*[A-Za-z0-9])?" placeholder="{{ __('admin.localPh') }}">
          <span class="input-group-text domain-addon">{{ '@'.$domain }}</span>
        </div>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="password">{{ __('admin.passwordMin') }}</label>
        <div class="pw-wrap"><input class="form-control" id="password" name="password" type="password" required minlength="8" autocomplete="new-password"><button class="eye" type="button" data-toggle-eye aria-label="{{ __('showPw') }}"><i class="bi bi-eye"></i></button></div>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="recovery_email">{{ __('admin.recoveryOptional') }}</label>
        <input class="form-control" id="recovery_email" name="recovery_email" type="email" value="{{ old('recovery_email') }}" placeholder="{{ __('admin.recoveryPh') }}">
        <div class="hint mt-1">{{ __('recoveryHint') }}</div>
      </div>
      <div class="col-md-3 d-flex align-items-end">
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="is_admin" name="is_admin" value="1">
          <label class="form-check-label" for="is_admin">{{ __('admin.adminAccess') }}</label>
        </div>
      </div>
      <div class="col-md-3 d-flex align-items-end">
        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-plus-lg"></i> {{ __('admin.create') }}</button>
      </div>
    </form>
  </section>

  <section class="panel">
    <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
      <h2 class="mb-0"><i class="bi bi-people"></i> {{ __('admin.accounts') }} (<span id="accountCount">{{ $accounts->total() }}</span>)</h2>
      <div class="input-group" style="max-width:520px;flex:1">
        <span class="input-group-text domain-addon"><i class="bi bi-search"></i></span>
        <input class="form-control" id="accountSearch" type="search" value="{{ $q }}" placeholder="{{ __('admin.searchPh') }}" autocomplete="off">
      </div>
    </div>
    <div id="accountList">
      @include('admin.account-list')
    </div>
  </section>

  <script>
    (function () {
      var input = document.getElementById("accountSearch");
      var list = document.getElementById("accountList");
      var count = document.getElementById("accountCount");
      var timer = null, controller = null;

      function load(url) {
        if (controller) controller.abort();
        controller = new AbortController();
        fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest", "Accept": "text/html" }, signal: controller.signal, credentials: "same-origin" })
          .then(function (res) {
            if (!res.ok) throw new Error(res.status);
            count.textContent = res.headers.get("X-Total-Count") || "0";
            return res.text();
          })
          .then(function (html) {
            list.innerHTML = html;
            history.replaceState(null, "", url);
          })
          .catch(function () {});
      }

      input.addEventListener("input", function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
          var q = input.value.trim();
          load("/admin" + (q ? "?q=" + encodeURIComponent(q) : ""));
        }, 250);
      });

      // Page links load in place and keep the search.
      list.addEventListener("click", function (event) {
        var link = event.target.closest(".pagination a");
        if (!link) return;
        event.preventDefault();
        load(link.getAttribute("href"));
        list.scrollIntoView({ behavior: "smooth", block: "start" });
      });
    })();
  </script>
@endsection
