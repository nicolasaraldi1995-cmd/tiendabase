@php($negocio = \App\Models\Configuracion::actual())
@php($datos = $pedido->datos_cliente ?? [])
<div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:0 auto;padding:30px;background:#f4f2ee;color:#1a1d21;">
    <h2 style="color:{{ $negocio->colorAcento() }};margin:0 0 20px;">{{ $negocio->nombre_negocio }}</h2>

    <p style="font-size:16px;margin:0 0 6px;"><strong>Entró un pedido nuevo</strong></p>
    <p style="color:#5a5e66;margin:0 0 20px;">Pedido <strong>#{{ $pedido->id }}</strong> · {{ $pedido->created_at->format('d/m/Y H:i') }}</p>

    <div style="background:#fff;border-radius:12px;padding:20px;margin-bottom:16px;border:1px solid rgba(0,0,0,0.08);">
        <p style="margin:0 0 10px;font-weight:600;">Cliente</p>
        <p style="margin:0 0 4px;">{{ $datos['nombre'] ?? '—' }}@if(! empty($datos['negocio'])) · {{ $datos['negocio'] }}@endif</p>
        @if(! empty($datos['celular']))
            <p style="margin:0 0 4px;color:#5a5e66;">Cel: {{ $datos['celular'] }}</p>
        @endif
        @if(! empty($datos['email']))
            <p style="margin:0 0 4px;color:#5a5e66;">{{ $datos['email'] }}</p>
        @endif
        <p style="margin:8px 0 0;color:#5a5e66;">
            {{ ($datos['entrega'] ?? 'envio') === 'retiro' ? 'Retira en el local' : 'Envío a domicilio' }}
            @if(($datos['entrega'] ?? 'envio') !== 'retiro' && ! empty($datos['direccion']))
                — {{ $datos['direccion'] }}@if(! empty($datos['ciudad'])), {{ $datos['ciudad'] }}@endif
            @endif
        </p>
        @if(! empty($datos['notas']))
            <p style="margin:10px 0 0;padding:10px;background:#fff8e6;border-radius:8px;font-size:14px;">
                <strong>Notas:</strong> {{ $datos['notas'] }}
            </p>
        @endif
    </div>

    <div style="background:#fff;border-radius:12px;padding:20px;border:1px solid rgba(0,0,0,0.08);">
        <p style="margin:0 0 12px;font-weight:600;">Productos</p>
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            @foreach($pedido->items as $item)
                <tr>
                    <td style="padding:6px 0;border-bottom:1px solid #f0eeea;">
                        {{ $item->cantidad }}× {{ $item->presentacion?->producto?->nombre ?? 'Producto' }}
                        <span style="color:#8e919a;">{{ $item->presentacion?->unidad }}</span>
                    </td>
                    <td style="padding:6px 0;border-bottom:1px solid #f0eeea;text-align:right;white-space:nowrap;">
                        ${{ number_format($item->subtotal, 2, ',', '.') }}
                    </td>
                </tr>
            @endforeach
            <tr>
                <td style="padding:10px 0 0;font-weight:700;">Total</td>
                <td style="padding:10px 0 0;text-align:right;font-weight:700;">${{ number_format($pedido->total, 2, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <p style="margin:22px 0 0;">
        <a href="{{ url('/admin/pedidos/'.$pedido->id) }}"
           style="display:inline-block;background:{{ $negocio->colorAcento() }};color:#fff;padding:11px 22px;border-radius:10px;text-decoration:none;font-weight:500;">
            Ver el pedido en el panel
        </a>
    </p>
</div>
