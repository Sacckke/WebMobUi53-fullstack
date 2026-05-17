<x-default-layout>
    <x-slot:scripts>
        @vite(['resources/js/poll-dashboard.js'])
    </x-slot>

    <x-slot:title>
        Sondages
    </x-slot>

    @php
        $dashProps = [
            'polls'    => $polls,
            'loginUrl' => route('login'),
            'username' => auth()->user()->username ?? auth()->user()->name ?? '',
        ];
    @endphp

    <div id="app" data-props='@json($dashProps)'></div>
</x-default-layout>
