@extends('emails.layouts.app')

@section('content')
    <h1>{{ __('Welcome, :name!', ['name' => $user->name]) }}</h1>
    <p>{{ __('Thanks for joining :app. We are glad you are here.', ['app' => config('app.name')]) }}</p>
    <p>{{ __('Please confirm your email address to secure your account and unlock the full experience.') }}</p>
    <div class="btn-wrap">
        <a class="btn" href="{{ $verificationUrl }}">{{ __('Verify email address') }}</a>
    </div>
    <p class="muted">{{ __('If you did not create an account, you can ignore this message.') }}</p>
@endsection
