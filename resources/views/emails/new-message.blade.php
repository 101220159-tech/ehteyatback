@extends('emails.layouts.app')

@section('content')
    <h1>{{ __('New message') }}</h1>
    <p>{{ __('Hi :name,', ['name' => $recipientName]) }}</p>
    <p>{{ __(':sender sent you a message:', ['sender' => $senderLabel]) }}</p>
    <p style="background:#f1f5f9;padding:16px;border-radius:8px;border-left:4px solid #2563eb;">{{ \Illuminate\Support\Str::limit(strip_tags($messagePreview), 280) }}</p>
    <div class="btn-wrap">
        <a class="btn" href="{{ $chatUrl }}">{{ __('Open conversation') }}</a>
    </div>
@endsection
