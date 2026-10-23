@props(['status'])
<span {{ $attributes->merge(['class' => 'badge '.$status->badge()]) }}>{{ $status->label() }}</span>
