<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'negocio', 'tipo_cliente', 'email', 'celular', 'direccion', 'ciudad', 'provincia', 'password', 'role', 'omite_avisos'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'omite_avisos' => 'boolean',
        ];
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    /**
     * "pendiente" es el que pidió precio mayorista al registrarse y todavía no
     * lo habilitó el negocio: para los precios cuenta como particular.
     */
    public const TIPOS_DE_CLIENTE = [
        'particular' => 'Particular',
        'pendiente' => 'Pidió precio mayorista',
        'negocio' => 'Negocio (precio mayorista)',
    ];

    /**
     * El único lugar que decide si a este cliente le corren los precios por
     * mayor. Antes la comparación estaba escrita a mano en dos archivos.
     */
    public function compraPorMayor(): bool
    {
        return $this->tipo_cliente === 'negocio';
    }

    public function esperaAprobacion(): bool
    {
        return $this->tipo_cliente === 'pendiente';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isOperador(): bool
    {
        return $this->role === 'operador';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, ['admin', 'operador']);
    }
}
