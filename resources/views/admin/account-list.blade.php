<div class="table-responsive">
  <table class="table">
    <thead>
      <tr><th>{{ __('admin.colAccount') }}</th><th>{{ __('admin.colStatus') }}</th><th>{{ __('admin.colMessages') }}</th><th>{{ __('admin.colLastSignIn') }}</th><th class="text-end">{{ __('admin.colActions') }}</th></tr>
    </thead>
    <tbody>
      @include('admin.account-rows')
    </tbody>
  </table>
</div>
@if ($accounts->lastPage() > 1)
  <div class="mt-2">
    @include('admin.pager', ['page' => $accounts])
  </div>
@endif
