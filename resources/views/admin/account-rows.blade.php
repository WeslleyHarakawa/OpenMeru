@forelse ($accounts as $account)
  <tr>
    <td>
      <strong>{{ $account->name }}</strong><br>
      <span class="hint">{{ $account->email }}</span>
      @if ($account->is_admin) <span class="pill admin">admin</span> @endif
    </td>
    <td><span class="pill {{ $account->is_active ? 'on' : 'off' }}">{{ $account->is_active ? __('admin.active') : __('admin.disabled') }}</span></td>
    <td>{{ $account->messages_count }} @if ($account->unread_count) <span class="hint">({{ __('admin.unread', ['count' => $account->unread_count]) }})</span> @endif</td>
    <td class="hint">{{ $account->last_login_at ? $account->last_login_at->timezone('Europe/Lisbon')->format('d/m/Y H:i') : __('admin.never') }}</td>
    <td class="text-end text-nowrap">
      @php($self = $account->is(auth()->user()))
      <form method="POST" action="{{ route('admin.accounts.impersonate', $account) }}" class="d-inline">
        @csrf
        <button class="btn btn-sm btn-outline-secondary" type="submit" title="{{ $self ? __('admin.thisIsYou') : __('admin.signInAs', ['email' => $account->email]) }}" @disabled($self || ! $account->is_active)><i class="bi bi-person-badge"></i></button>
      </form>
      <form method="POST" action="{{ route('admin.accounts.destroy', $account) }}" class="d-inline" onsubmit="return confirm(@js(__('admin.deleteConfirm', ['email' => $account->email])));">
        @csrf @method('DELETE')
        <button class="btn btn-sm btn-outline-danger" type="submit" title="{{ $self ? __('admin.cannotDeleteSelf') : __('admin.deleteTitle', ['email' => $account->email]) }}" @disabled($self)><i class="bi bi-trash"></i></button>
      </form>
      <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#edit-{{ $account->id }}"><i class="bi bi-pencil"></i> {{ __('admin.edit') }}</button>
    </td>
  </tr>
  <tr class="collapse" id="edit-{{ $account->id }}">
    <td colspan="5">
      <form method="POST" action="{{ route('admin.accounts.update', $account) }}" class="row g-3" autocomplete="off">
        @csrf @method('PUT')
        <div class="col-md-4">
          <label class="form-label">{{ __('name') }}</label>
          <input class="form-control" name="name" value="{{ $account->name }}" required maxlength="120">
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('admin.newPasswordKeep') }}</label>
          <input class="form-control" name="password" type="text" minlength="8" autocomplete="new-password">
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('recoveryEmail') }}</label>
          <input class="form-control" name="recovery_email" type="email" value="{{ $account->recovery_email }}">
        </div>
        <div class="col-md-8">
          <label class="form-label">{{ __('signature') }}</label>
          <textarea class="form-control" name="signature" rows="2" maxlength="2000">{{ $account->signature }}</textarea>
        </div>
        <div class="col-md-4 d-flex flex-column justify-content-end gap-1">
          @if (! $self)
            <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act-{{ $account->id }}" @checked($account->is_active)><label class="form-check-label" for="act-{{ $account->id }}">{{ __('admin.accountActive') }}</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="is_admin" value="1" id="adm-{{ $account->id }}" @checked($account->is_admin)><label class="form-check-label" for="adm-{{ $account->id }}">{{ __('admin.adminAccess') }}</label></div>
          @else
            <span class="hint">{{ __('admin.selfNote') }}</span>
          @endif
        </div>
        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> {{ __('save') }}</button>
        </div>
      </form>
    </td>
  </tr>
@empty
  <tr><td colspan="5" class="hint">{{ $q !== '' ? __('admin.noneFound', ['q' => $q]) : __('admin.noneYet') }}</td></tr>
@endforelse
