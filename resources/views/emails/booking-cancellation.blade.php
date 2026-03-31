@extends('emails.layouts.app')

@section('content')
    <h1>{{ __('Booking cancelled') }}</h1>
    <p>{{ __('Hi :name,', ['name' => $recipientName]) }}</p>
    <p>{{ __('Booking #:id has been cancelled.', ['id' => $booking->id]) }}</p>
    <p><strong>{{ __('Was scheduled for') }}:</strong> {{ $booking->booking_date->timezone(config('app.timezone'))->format('l, F j, Y \a\t g:i A') }}</p>
    @if($cancellationNote)
        <p><strong>{{ __('Note') }}:</strong> {{ $cancellationNote }}</p>
    @endif
    <div class="btn-wrap">
        <a class="btn" href="{{ $actionUrl }}">{{ __('Go to dashboard') }}</a>
    </div>
@endsection
