@extends('layouts.app')
@section('content')
<div class="container-v flex justify-center py-16">
    <div class="card w-full max-w-md">
        <h1 class="text-2xl font-bold tracking-tight">@yield('heading')</h1>
        <p class="mt-1 text-sm text-muted">@yield('sub')</p>
        <div class="mt-6">@yield('form')</div>
    </div>
</div>
@endsection
