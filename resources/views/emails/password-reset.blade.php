@extends('emails.layouts.app')

@section('content')
    <h1>{{ __('Reset your password') }}</h1>
    <p>{{ __('Hi :name,', ['name' => $user->name]) }}</p>
    <p>{{ __('We received a request to reset the password for your account. Click the button below to choose a new password.') }}</p>
    <div class="btn-wrap">
        <a class="btn" href="{{ $resetUrl }}">{{ __('Reset password') }}</a>
    </div>
    <p class="muted">{{ __('If you did not request a reset, you can ignore this email. Your password will stay the same.') }}</p>
@endsection
