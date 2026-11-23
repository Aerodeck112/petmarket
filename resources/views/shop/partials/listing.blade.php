{{-- Raspunsul partial la schimbarea filtrelor: rezultatele si etichetele filtrelor active. --}}
<template data-part="results">@include('shop.partials.grid')</template>
<template data-part="chips">@include('shop.partials.chips')</template>
<template data-part="count">{{ $products->total() }} {{ $products->total() === 1 ? 'produs' : 'produse' }}</template>
