<!DOCTYPE html>
@php($negocio = \App\Models\Configuracion::actual())
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #1a1d21; }
        .page { padding: 20px 25px; }

        .header { border-bottom: 3px solid {{ $negocio->colorAcento() }}; padding-bottom: 12px; margin-bottom: 15px; }
        .header-flex { display: table; width: 100%; }
        .header-left { display: table-cell; vertical-align: middle; }
        .header-right { display: table-cell; vertical-align: middle; text-align: right; }
        .brand { font-size: 22px; font-weight: 700; color: {{ $negocio->colorAcento() }}; letter-spacing: -0.5px; }
        .brand-sub { font-size: 9px; color: #9a9da5; letter-spacing: 2px; text-transform: uppercase; }
        .fecha { font-size: 9px; color: #5a5e66; }
        .contacto { font-size: 9px; color: #5a5e66; margin-top: 3px; }

        .categoria-header { background: {{ $negocio->colorAcento() }}; color: #fff; padding: 5px 10px; font-size: 11px; font-weight: 700; margin: 12px 0 4px; border-radius: 3px; page-break-after: avoid; }

        table { width: 100%; border-collapse: collapse; }
        th { background: #f0eeea; text-align: left; padding: 4px 8px; font-size: 9px; font-weight: 600; color: #5a5e66; text-transform: uppercase; letter-spacing: 0.5px; }
        th.text-right, td.text-right { text-align: right; }
        td { padding: 3px 8px; border-bottom: 1px solid #f0eeea; font-size: 9.5px; }
        tr:nth-child(even) td { background: #fafaf8; }

        .marca-col { color: #5a5e66; font-size: 9px; }
        .precio-col { font-weight: 600; }
        .oferta { color: #ef4444; }
        .badge { display: inline-block; font-size: 7px; font-weight: 700; padding: 1px 4px; border-radius: 3px; margin-left: 3px; vertical-align: middle; }
        .badge-tacc { background: #d1fae5; color: #065f46; }

        .footer { margin-top: 15px; padding-top: 8px; border-top: 1px solid #e6e4df; text-align: center; font-size: 8px; color: #9a9da5; }
        .totals { margin-top: 10px; text-align: right; font-size: 9px; color: #5a5e66; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="header-flex">
                <div class="header-left">
                    <div class="brand">{{ $negocio->nombre_negocio }}</div>
                    @if($negocio->eslogan)<div class="brand-sub">{{ $negocio->eslogan }}</div>@endif
                </div>
                <div class="header-right">
                    <div class="fecha">Lista de precios — {{ now()->format('d/m/Y') }}</div>
                    @if($negocio->ciudad || $negocio->whatsapp)
                    <div class="contacto">{{ collect([$negocio->ciudad, $negocio->whatsapp ? 'WhatsApp: '.$negocio->whatsapp : null])->filter()->join(' · ') }}</div>
                    @endif
                    <div class="contacto">{{ $totalProductos }} productos · {{ $totalPresentaciones }} presentaciones</div>
                </div>
            </div>
        </div>

        @foreach($categorias as $categoria)
            <div class="categoria-header">{{ $categoria->nombre }} ({{ $categoria->productos->count() }})</div>
            <table>
                <thead>
                    <tr>
                        <th style="width:35%">Producto</th>
                        <th style="width:18%">Marca</th>
                        <th style="width:15%">Presentación</th>
                        <th class="text-right" style="width:12%">Precio</th>
                        <th class="text-right" style="width:12%">Oferta</th>
                        <th class="text-right" style="width:8%">Stock</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($categoria->productos as $producto)
                        @foreach($producto->presentaciones as $i => $pres)
                            <tr>
                                @if($i === 0)
                                    <td rowspan="{{ $producto->presentaciones->count() }}">
                                        {{ $producto->nombre }}
                                        @foreach($producto->etiquetas->where('activo', true) as $etiqueta)<span class="badge" style="background:{{ $etiqueta->color ?? '#e5e7eb' }};color:#fff">{{ mb_strtoupper($etiqueta->nombre) }}</span>@endforeach
                                    </td>
                                    <td rowspan="{{ $producto->presentaciones->count() }}" class="marca-col">{{ $producto->marca->nombre ?? '—' }}</td>
                                @endif
                                <td>{{ $pres->unidad }}</td>
                                <td class="text-right precio-col">${{ number_format($pres->precio, 2, ',', '.') }}</td>
                                <td class="text-right">
                                    @if($pres->estaEnOferta())
                                        <span class="oferta">${{ number_format($pres->precio_final, 2, ',', '.') }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-right">{{ $pres->stock > 0 ? $pres->stock : '—' }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        @endforeach

        <div class="footer">
            {{ $negocio->nombre_negocio }}@if($negocio->ciudad) · {{ $negocio->ciudad }}@endif · Los precios pueden variar sin previo aviso · {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>
