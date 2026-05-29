@extends('errors.layout')

@section('code', '403')
@section('title', __('Access Denied'))
@section('message', __('You don\'t have permission to access this resource. If you believe this is a mistake, contact your administrator.'))
@section('actions')
    <a href="{{ url('/') }}">{{ __('Return home') }}</a>
@endsection
