@extends('legal.layout')

@section('title', __('terms'))

@section('content')
  <p>{{ __('legal.t.intro') }}</p>

  <h2>{{ __('legal.t.accountTitle') }}</h2>
  <ul>
    <li>{{ __('legal.t.account1') }}</li>
    <li>{!! __('legal.t.account2') !!}</li>
  </ul>

  <h2>{{ __('legal.t.useTitle') }}</h2>
  <p>{{ __('legal.t.useLead') }}</p>
  <ul>
    <li>{{ __('legal.t.use1') }}</li>
    <li>{{ __('legal.t.use2') }}</li>
    <li>{{ __('legal.t.use3') }}</li>
    <li>{{ __('legal.t.use4') }}</li>
  </ul>
  <p>{{ __('legal.t.useEnd') }}</p>

  <h2>{{ __('legal.t.serviceTitle') }}</h2>
  <p>{{ __('legal.t.service') }}</p>

  <h2>{{ __('legal.t.endTitle') }}</h2>
  <p>{{ __('legal.t.end') }}</p>

  <h2>{{ __('legal.t.changesTitle') }}</h2>
  <p>{{ __('legal.t.changes') }}</p>

  <h2>{{ __('legal.contactTitle') }}</h2>
  <p><a href="mailto:hello@{{ config('inbound.domain') }}">hello@{{ config('inbound.domain') }}</a></p>
@endsection
