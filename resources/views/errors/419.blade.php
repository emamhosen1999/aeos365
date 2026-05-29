@extends('errors.layout')

@section('code', '419')
@section('title', __('Page Expired'))
@section('message', __('Your session has expired due to inactivity. Please refresh the page and try again.'))
@section('actions')
    <a href="javascript:window.location.reload()">{{ __('Refresh page') }}</a>
@endsection
