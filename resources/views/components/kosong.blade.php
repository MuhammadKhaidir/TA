@props(['pesan' => 'Belum ada data.', 'ikon' => 'berkas'])
<div class="flex flex-col items-center px-5 py-9 text-center">
    <svg viewBox="0 0 240 132" class="mb-3 h-32 w-auto" fill="none" aria-hidden="true">
        {{-- Alas dan ranting hias --}}
        <ellipse cx="120" cy="116" rx="92" ry="10" fill="#f1e2c2" opacity=".7" />
        <g stroke="#d9b36b" stroke-width="1.4" stroke-linecap="round">
            <path d="M54 112 C40 96 40 76 52 62" />
            <path d="M186 112 C200 96 200 76 188 62" />
        </g>
        <g fill="#e3c98f" opacity=".85">
            <path d="M52 62c-9-2-13-9-12-17 9 1 14 7 12 17Z" />
            <path d="M44 88c-9 0-15-5-16-13 9-1 15 4 16 13Z" />
            <path d="M188 62c9-2 13-9 12-17-9 1-14 7-12 17Z" />
            <path d="M196 88c9 0 15-5 16-13-9-1-15 4-16 13Z" />
        </g>
        <path d="M30 40l2.4 5.6L38 48l-5.6 2.4L30 56l-2.4-5.6L22 48l5.6-2.4L30 40Z" fill="#d9b36b" />
        <path d="M212 30l1.8 4.2L218 36l-4.2 1.8L212 42l-1.8-4.2L206 36l4.2-1.8L212 30Z" fill="#d9b36b" />

        @if ($ikon === 'clipboard')
            <rect x="82" y="22" width="64" height="84" rx="8" fill="#fbf3e3" stroke="#a8793a" stroke-width="3" />
            <rect x="101" y="14" width="26" height="14" rx="5" fill="#e9d3a8" stroke="#a8793a" stroke-width="3" />
            <g stroke="#a8793a" stroke-width="2.4" stroke-linecap="round">
                <rect x="92" y="44" width="9" height="9" rx="2" />
                <rect x="92" y="62" width="9" height="9" rx="2" />
                <rect x="92" y="80" width="9" height="9" rx="2" />
                <path d="M108 48h24M108 66h24M108 84h16" />
            </g>
            <circle cx="140" cy="84" r="17" fill="#fffdf8" fill-opacity=".85" stroke="#a8793a" stroke-width="4" />
            <path d="M152 97l15 14" stroke="#a8793a" stroke-width="7" stroke-linecap="round" />
        @elseif ($ikon === 'surat')
            <rect x="92" y="26" width="56" height="48" rx="4" fill="#fdf9f0" stroke="#c9a46a" stroke-width="2.4" />
            <path d="M101 38h38M101 47h38M101 56h24" stroke="#d9c39a" stroke-width="2.4" stroke-linecap="round" />
            <rect x="72" y="50" width="96" height="58" rx="7" fill="#eed9ae" stroke="#b58a4a" stroke-width="3" />
            <path d="M74 54l46 34 46-34" stroke="#b58a4a" stroke-width="3" stroke-linejoin="round" />
        @else
            <path d="M88 20h42l22 22v62a5 5 0 0 1-5 5H88a5 5 0 0 1-5-5V25a5 5 0 0 1 5-5Z" fill="#fbf3e3" stroke="#a8793a" stroke-width="3" stroke-linejoin="round" />
            <path d="M130 20v22h22" stroke="#a8793a" stroke-width="3" stroke-linejoin="round" />
            <path d="M96 62h48M96 76h48M96 90h30" stroke="#d9c39a" stroke-width="3" stroke-linecap="round" />
        @endif
    </svg>
    <p class="max-w-md text-base text-ink-600">{{ $pesan }}</p>
</div>
