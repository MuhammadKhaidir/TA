@if (session('sukses'))
    <div class="alert alert-sukses" role="status">{{ session('sukses') }}</div>
@endif

@if (session('galat'))
    <div class="alert alert-galat" role="alert">{{ session('galat') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-galat" role="alert">
        <p class="font-medium">Terdapat isian yang perlu diperbaiki:</p>
        <ul class="mt-1 list-disc space-y-0.5 pl-5">
            @foreach ($errors->all() as $pesan)
                <li>{{ $pesan }}</li>
            @endforeach
        </ul>
    </div>
@endif
