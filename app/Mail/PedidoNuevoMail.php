<?php

namespace App\Mail;

use App\Models\Pedido;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso al dueño del negocio: le avisa que entró un pedido por la web, para
 * que no dependa de estar mirando el panel.
 */
class PedidoNuevoMail extends Mailable
{
    public function __construct(public Pedido $pedido) {}

    public function envelope(): Envelope
    {
        $cliente = $this->pedido->datos_cliente['negocio']
            ?: ($this->pedido->datos_cliente['nombre'] ?? 'un cliente');

        return new Envelope(subject: "Pedido nuevo #{$this->pedido->id} — {$cliente}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.pedido-nuevo');
    }
}
