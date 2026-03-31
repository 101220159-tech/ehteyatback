@extends('emails.layouts.app')

@section('content')
    <h1>{{ __('Reminder: appointment tomorrow') }}</h1>
    <p>{{ __('Hi :name,', ['name' => $recipientName]) }}</p>
    <p>{{ __('This is a friendly reminder about your upcoming booking #:id.', ['id' => $booking->id]) }}</p>
    <p><strong>{{ __('Date & time') }}:</strong> {{ $booking->booking_date->timezone(config('app.timezone'))->format('l, F j, Y \a\t g:i A') }}</p>
    @if($booking->provider)
        <p><strong>{{ __('Provider') }}:</strong> {{ $booking->provider?->user?->name ?? __('Your provider') }}</p>
    @endif
    <div class="btn-wrap">
        <a class="btn" href="{{ $actionUrl }}">{{ __('Open details') }}</a>
    </div>
@endsection
