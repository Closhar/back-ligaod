@extends('layouts.login')

@section('content')
    @if(isset($form))
        {{ $form->render() }}
    @else
        Form is not defined.
    @endif
    <div class="text-center mt-4">
        <x-moonshine::link-native
            :href="route('moonshine.login')"
        >
            Войти
        </x-moonshine::link-native>
    </div>
@endsection
