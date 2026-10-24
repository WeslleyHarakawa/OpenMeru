@if ($user->avatar_path)
  <img class="avatar-img" src="{{ route('account.avatar') }}?v={{ optional($user->updated_at)->timestamp }}" alt="">
@else
  <span class="avatar-initial">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
@endif
