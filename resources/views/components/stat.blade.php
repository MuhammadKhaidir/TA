@props(['label', 'nilai', 'hint' => null, 'href' => null])
@php
    $teksLabel = strtolower($label);
    $ikon = match (true) {
        str_contains($teksLabel, 'pembimbing') => 'academic',
        str_contains($teksLabel, 'verifikasi') => 'clipboard',
        str_contains($teksLabel, 'surat') => 'document',
        str_contains($teksLabel, 'diperiksa') => 'shield',
        str_contains($teksLabel, 'revisi') => 'history',
        str_contains($teksLabel, 'arsip') => 'archive',
        str_contains($teksLabel, 'mahasiswa'), str_contains($teksLabel, 'dosen'), str_contains($teksLabel, 'admin') => 'users',
        str_contains($teksLabel, 'program studi') => 'database',
        str_contains($teksLabel, 'persyaratan') => 'list',
        default => 'activity',
    };
@endphp

@if ($href)
    <a href="{{ $href }}" class="card stat-card group">
@else
    <div class="card stat-card">
@endif
        <span class="stat-icon" aria-hidden="true">
            <x-pasir.icon :name="$ikon" class="h-7 w-7" />
        </span>
        <div class="stat-copy">
            <div class="stat-label">{{ $label }}</div>
            <div class="stat-angka">{{ $nilai }}</div>
            @if ($hint)
                <div class="stat-hint">{{ $hint }}</div>
            @endif
        </div>
@if ($href)
    </a>
@else
    </div>
@endif
