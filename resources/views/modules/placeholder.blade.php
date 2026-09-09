@extends('layouts.app', ['title' => $module['label'], 'heading' => $module['label']])

@section('content')
    <section class="flex min-h-[360px] max-w-2xl flex-col justify-center rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-12 sm:px-10" aria-labelledby="module-title">
        <span class="flex size-11 items-center justify-center rounded-xl bg-orange-50 text-xl text-orange-600">·</span>
        <h2 id="module-title" class="mt-6 text-3xl font-semibold tracking-tight">{{ $module['label'] }}</h2>
        <p class="mt-3 max-w-lg text-lg leading-8 text-stone-500">{{ $module['description'] }}</p>
        <p class="mt-8 text-sm font-medium text-stone-400">Este módulo estará disponible en una próxima misión.</p>
    </section>
@endsection
