<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @php($negocio = \App\Models\Configuracion::actual())
        @if($negocio->logo_url)
        <link rel="icon" href="{{ $negocio->logo_url }}">
        @endif

        <title inertia>{{ $negocio->nombre_negocio }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead

        {{-- Después de @vite a propósito: el :root de app.css trae los colores
             default y tiene la misma especificidad, así que este tiene que
             venir último para ganarle. --}}
        @if($colores = $negocio->coloresAcentoVars())
        <style>:root { @foreach($colores as $variable => $valor){{ $variable }}: {{ $valor }}; @endforeach }</style>
        @endif
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
