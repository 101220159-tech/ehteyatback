@extends('emails.layouts.app')

@section('content')
    <h1>{{ __('Account pending review') }}</h1>
    <p>{{ __('Hi :name,', ['name' => $user->name]) }}</p>
    <p>{{ __('Thank you for registering as a provider on :app. Your account has been created and is currently pending admin review.', ['app' => config('app.name')]) }}</p>
    <p>{{ __('You will receive an email notification once your account is verified and you can start accepting bookings.') }}</p>
    <p class="muted">{{ __('If you did not create this account, please ignore this email.') }}</p>
@endsection
