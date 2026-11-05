@props(['pesan' => 'Belum ada data.'])
<div class="empty-state">
    <span class="empty-state-icon" aria-hidden="true">
        <x-pasir.icon name="clipboard" class="h-9 w-9" />
    </span>
    <p>{{ $pesan }}</p>
</div>
