@extends('legal.layout')

@section('title', __('privacy'))

@section('content')
  <p>{{ __('legal.p.intro') }}</p>

  <h2>{{ __('legal.p.storeTitle') }}</h2>
  <ul>
    <li>{!! __('legal.p.store1') !!}</li>
    <li>{!! __('legal.p.store2') !!}</li>
    <li>{!! __('legal.p.store3') !!}</li>
  </ul>

  <h2>{{ __('legal.p.useTitle') }}</h2>
  <p>{{ __('legal.p.use') }}</p>

  <h2>{{ __('legal.p.whoTitle') }}</h2>
  <ul>
    <li>{{ __('legal.p.who1') }}</li>
    <li>{{ __('legal.p.who2') }}</li>
    <li>{{ __('legal.p.who3') }}</li>
    <li>{{ __('legal.p.who4') }}</li>
  </ul>

  <h2>{{ __('legal.p.privateTitle') }}</h2>
  <p>{{ __('legal.p.private') }}</p>

  <h2>{{ __('legal.p.keepTitle') }}</h2>
  <p>{{ __('legal.p.keep') }}</p>

  <h2>{{ __('legal.p.choicesTitle') }}</h2>
  <ul>
    <li>{!! __('legal.p.choice1') !!}</li>
    <li>{{ __('legal.p.choice2') }}</li>
    <li>{{ __('legal.p.choice3') }}</li>
  </ul>

  <h2>{{ __('legal.contactTitle') }}</h2>
  <p>{!! __('legal.p.contact') !!}</p>
@endsection
