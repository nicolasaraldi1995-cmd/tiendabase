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
        <link href="{{ $negocio->fuenteUrl() }}" rel="stylesheet" />

        {{-- La plantilla tiene que estar antes que app.js: el resolver la lee
             para saber en qué carpeta buscar cada pantalla. --}}
        <script>window.__plantilla = @json($negocio->plantilla());</script>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', $negocio->vistaDeLaPagina($page['component'])])
        @inertiaHead

        {{-- Después de @vite a propósito: el :root de app.css trae los valores
             default y tiene la misma especificidad, así que este tiene que
             venir último para ganarle. --}}
        <style>:root { --fuente: '{{ $negocio->fuenteFamilia() }}'; @if($colores = $negocio->coloresAcentoVars()) @foreach($colores as $variable => $valor){{ $variable }}: {{ $valor }}; @endforeach @endif }</style>
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
