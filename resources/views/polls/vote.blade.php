<x-default-layout>
    <x-slot:scripts>
        @vite(['resources/js/poll-vote.js'])
    </x-slot>

    <x-slot:title>
        Sondage
    </x-slot>

    @php
        $voteProps = [
            'token'         => $token,
            'authenticated' => $authenticated,
            'loginUrl'      => $loginUrl,
        ];
    @endphp

    <div id="app-vote" data-props='@json($voteProps)'></div>
</x-default-layout>
