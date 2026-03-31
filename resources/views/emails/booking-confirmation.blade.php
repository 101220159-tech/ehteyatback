@extends('emails.layouts.app')

@section('content')
    <h1>{{ __('Booking confirmed') }}</h1>
    <p>{{ __('Hi :name,', ['name' => $recipientName]) }}</p>
    <p>{{ __('Your booking #:id is confirmed.', ['id' => $booking->id]) }}</p>
    <p><strong>{{ __('When') }}:</strong> {{ $booking->booking_date->timezone(config('app.timezone'))->format('l, F j, Y \a\t g:i A') }}</p>
    @if($booking->service)
        <p><strong>{{ __('Service') }}:</strong> {{ $booking->service->name }}</p>
    @endif
    <p><strong>{{ __('Provider') }}:</strong> {{ $booking->provider?->user?->name ?? __('Your provider') }}</p>
    <div class="btn-wrap">
        <a class="btn" href="{{ $actionUrl }}">{{ __('View booking') }}</a>
    </div>
@endsection
