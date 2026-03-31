@extends('emails.layouts.app')

@section('content')
    <h1>{{ __('You are verified') }}</h1>
    <p>{{ __('Hi :name,', ['name' => $providerName]) }}</p>
    <p>{{ __('Great news — your provider profile on :app has been verified. Customers can now book you with confidence.', ['app' => config('app.name')]) }}</p>
    <div class="btn-wrap">
        <a class="btn" href="{{ $actionUrl }}">{{ __('Open provider dashboard') }}</a>
    </div>
@endsection
