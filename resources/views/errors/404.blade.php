@extends('errors.layout')

@section('code', '404')
@section('title', __('Page Not Found'))
@section('message', __('The page you\'re looking for doesn\'t exist or has been moved.'))
@section('actions')
    <a href="{{ url('/') }}">{{ __('Return home') }}</a>
@endsection
