@extends('errors.layout')

@section('code', '500')
@section('title', __('Something Went Wrong'))
@section('message', __('An unexpected error occurred. Our team has been notified and is investigating.'))
@section('actions')
    <a href="{{ url('/') }}">{{ __('Return home') }}</a>
@endsection
