<x-moonshine::layout>
    <x-moonshine::layout.html :with-alpine-js="true" :with-themes="false">
        <x-moonshine::layout.head>
            <x-moonshine::layout.meta name="csrf-token" :content="csrf_token()"/>
            <x-moonshine::layout.favicon/>
            <x-moonshine::layout.assets>
                @vite([
                    'resources/css/main.css',
                    'resources/js/app.js',
                ], 'vendor/moonshine')
                @vite([
                    'resources/css/app.css',
                ])
            </x-moonshine::layout.assets>
        </x-moonshine::layout.head>
        <x-moonshine::layout.body>
            <x-moonshine::layout.div class="authentication">
                <x-moonshine::layout.div class="authentication-logo">
                    <x-moonshine::layout.logo href="/" logo="{{ asset('logo.svg') }}"/>
                </x-moonshine::layout.div>

                <x-moonshine::layout.div class="authentication-content">
                    <x-moonshine::layout.content>
                        <x-moonshine::layout.flash/>
                        @yield('content')
                    </x-moonshine::layout.content>
                </x-moonshine::layout.div>

            </x-moonshine::layout.div>
        </x-moonshine::layout.body>
    </x-moonshine::layout.html>
</x-moonshine::layout>
