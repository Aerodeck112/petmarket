@extends('layouts.site')

@section('content')
@include('site._head', ['title' => $page->title])
<section style="padding-bottom: var(--section)">
    <div class="wrap">
        <div class="legal">
            @if ($page->document_version)<p class="badge">@if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $page->document_version)){{ __('În vigoare din :date', ['date' => \Illuminate\Support\Carbon::parse($page->document_version)->format('d.m.Y')]) }}@else{{ __('Versiune document: :version', ['version' => $page->document_version]) }}@endif</p>@endif
            <div class="prose" style="max-width:78ch">{!! \App\Support\Html::headingsFrom2(\App\Support\Html::clean((string) $page->body)) !!}</div>
        </div>
        <p class="no-print" style="margin-top:1.5rem"><button class="btn btn-ghost btn-sm" type="button" onclick="window.print()">{{ __('Tipărește documentul') }}</button></p>
    </div>
</section>
@endsection
