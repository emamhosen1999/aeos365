@extends('errors.layout')

@section('code', '429')
@section('title', __('Too Many Requests'))
@section('message', __('You\'ve made too many requests in a short window. Please wait a moment and try again.'))
@section('actions')
    <a href="{{ url('/') }}">{{ __('Return home') }}</a>
@endsection
