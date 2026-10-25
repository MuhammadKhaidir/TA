{{--
    Ikon garis tipis untuk tema pasir (login dan area mahasiswa).
    Pakai: <x-mhs.icon name="doc" class="h-5 w-5" />  (ukuran wajib diberikan lewat class)
--}}
@props(['name'])

<svg {{ $attributes }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('doc')
            <path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
            <path d="M14 3v4h4M9 12h6M9 16h6" />
            @break
        @case('calendar')
            <rect x="3.5" y="5" width="17" height="15" rx="2.5" />
            <path d="M8 3v4M16 3v4M3.5 10h17" />
            @break
        @case('history')
            <path d="M3.5 12a8.5 8.5 0 1 0 2.6-6.1L3.5 8.5" />
            <path d="M3.5 4v4.5H8M12 8v4.5l3 1.5" />
            @break
        @case('home')
            <path d="M3.5 11 12 4l8.5 7M5.5 9.8V20h13V9.8M10 20v-5.5h4V20" />
            @break
        @case('briefcase')
            <rect x="3.5" y="7.5" width="17" height="12" rx="2.5" />
            <path d="M9 7.5V6a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v1.5M3.5 13h17" />
            @break
        @case('academic')
            <path d="M2.5 9.5 12 5l9.5 4.5L12 14 2.5 9.5Z" />
            <path d="M6.5 11.8V16c0 1.4 2.5 2.8 5.5 2.8s5.5-1.4 5.5-2.8v-4.2M21.5 9.5V15" />
            @break
        @case('list')
            <path d="M9 6.5h11M9 12h11M9 17.5h11" />
            <circle cx="4.8" cy="6.5" r=".9" fill="currentColor" />
            <circle cx="4.8" cy="12" r=".9" fill="currentColor" />
            <circle cx="4.8" cy="17.5" r=".9" fill="currentColor" />
            @break
        @case('folder')
            <path d="M3.5 7.5a2 2 0 0 1 2-2h4l2 2h7a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2v-10Z" />
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14" />
            @break
        @case('user')
            <circle cx="12" cy="8.5" r="3.5" fill="currentColor" stroke="none" />
            <path d="M5 20c0-3.6 3.1-6 7-6s7 2.4 7 6Z" fill="currentColor" stroke="none" />
            @break
        @case('alert')
            <path d="M12 4 2.8 19.5h18.4L12 4Z" />
            <path d="M12 10v4.5M12 17.2v.1" />
            @break
        @case('chevron-right')
            <path d="m9 6 6 6-6 6" />
            @break
        @case('chevron-down')
            <path d="m6 9 6 6 6-6" />
            @break
        @case('logout')
            <path d="M14 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 8l-4 4 4 4M6 12h9" />
            @break
    @endswitch
</svg>
