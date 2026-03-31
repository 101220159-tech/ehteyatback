@extends('emails.layouts.app')

@section('content')
    <h1>{{ __('Confirm your email') }}</h1>
    <p>{{ __('Hi :name,', ['name' => $user->name]) }}</p>
    <p>{{ __('Click the button below to verify your email address for :app.', ['app' => config('app.name')]) }}</p>
    <div class="btn-wrap">
        <a class="btn" href="{{ $verificationUrl }}">{{ __('Verify email address') }}</a>
    </div>
    <p class="muted">{{ __('This link expires after a limited time.') }}</p>
@endsection
