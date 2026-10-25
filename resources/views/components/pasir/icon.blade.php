{{--
    Ikon garis tipis untuk tema pasir (login dan seluruh halaman setelah login).
    Pakai: <x-pasir.icon name="doc" class="h-5 w-5" />  (ukuran wajib diberikan lewat class)
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
        @case('clipboard')
            <rect x="6" y="4.5" width="12" height="16.5" rx="2.5" />
            <path d="M9.5 4.5V4a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 .5.5v.5M9.3 13.2l2 2 3.6-3.9" />
            @break
        @case('users')
            <circle cx="9" cy="8.5" r="3" />
            <path d="M3.5 19.5c0-3 2.5-5 5.5-5s5.5 2 5.5 5M16 5.8a3 3 0 0 1 0 5.4M17.5 14.7c2 .5 3.5 2.2 3.5 4.8" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="8.5" />
            <path d="M12 7.5V12l3 2" />
            @break
        @case('shield')
            <path d="M12 3 5 6v5.5c0 4.2 2.9 7.6 7 9 4.1-1.4 7-4.8 7-9V6l-7-3Z" />
            <path d="m9.2 12 2 2 3.6-4" />
            @break
        @case('inbox')
            <path d="M4 13.5 6.2 5.5h11.6L20 13.5" />
            <path d="M4 13.5V18a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4.5h-5l-1 2h-4l-1-2H4Z" />
            @break
        @case('document')
            <path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
            <path d="M14 3v4h4M9 12h6M9 16h6" />
            @break
        @case('archive')
            <rect x="3.5" y="4.5" width="17" height="4" rx="1" />
            <path d="M5 8.5V19a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8.5M10 12.5h4" />
            @break
        @case('database')
            <ellipse cx="12" cy="6" rx="7.5" ry="3" />
            <path d="M4.5 6v6c0 1.7 3.4 3 7.5 3s7.5-1.3 7.5-3V6M4.5 12v6c0 1.7 3.4 3 7.5 3s7.5-1.3 7.5-3v-6" />
            @break
        @case('sliders')
            <path d="M4 7h9M17 7h3M4 17h3M11 17h9" />
            <circle cx="15" cy="7" r="2" />
            <circle cx="9" cy="17" r="2" />
            @break
        @case('activity')
            <path d="M3 12h4l3-8 4 16 3-8h4" />
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
