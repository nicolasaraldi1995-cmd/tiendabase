<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un comprobante que ARCA autorizó de verdad.
 *
 * Solo se guardan los autorizados. Un intento fallido no deja fila porque el
 * número que se iba a usar no se consumió del lado de ARCA: anotarlo acá haría
 * que nuestro registro dejara de coincidir con el de ellos, que es el único que
 * vale a la hora de rendir.
 */
class Comprobante extends Model
{
    protected $fillable = [
        'pedido_id', 'tipo', 'punto_venta', 'numero',
        'cae', 'cae_vencimiento', 'fecha',
        'neto', 'iva', 'total',
        'receptor_doc_tipo', 'receptor_doc_nro', 'respuesta',
    ];

    protected $casts = [
        'tipo' => 'integer',
        'punto_venta' => 'integer',
        'numero' => 'integer',
        'cae_vencimiento' => 'date',
        'fecha' => 'date',
        'neto' => 'decimal:2',
        'iva' => 'decimal:2',
        'total' => 'decimal:2',
        'receptor_doc_tipo' => 'integer',
        'respuesta' => 'array',
    ];

    /**
     * Los códigos de comprobante de ARCA que emite este motor.
     *
     * Un monotributista emite siempre C. Un responsable inscripto emite A
     * cuando le vende a otro responsable inscripto, y B a consumidor final o
     * monotributista. Las notas de crédito acompañan a la factura que anulan:
     * una nota A anula una factura A.
     */
    public const FACTURA_A = 1;

    public const FACTURA_B = 6;

    public const FACTURA_C = 11;

    public const NOTA_CREDITO_A = 3;

    public const NOTA_CREDITO_B = 8;

    public const NOTA_CREDITO_C = 13;

    public const NOMBRES = [
        self::FACTURA_A => 'Factura A',
        self::FACTURA_B => 'Factura B',
        self::FACTURA_C => 'Factura C',
        self::NOTA_CREDITO_A => 'Nota de crédito A',
        self::NOTA_CREDITO_B => 'Nota de crédito B',
        self::NOTA_CREDITO_C => 'Nota de crédito C',
    ];

    /** Qué nota de crédito le corresponde a cada factura. */
    public const NOTA_DE_CREDITO_DE = [
        self::FACTURA_A => self::NOTA_CREDITO_A,
        self::FACTURA_B => self::NOTA_CREDITO_B,
        self::FACTURA_C => self::NOTA_CREDITO_C,
    ];

    public const TIPOS_DE_FACTURA = [self::FACTURA_A, self::FACTURA_B, self::FACTURA_C];

    public const TIPOS_DE_NOTA_DE_CREDITO = [self::NOTA_CREDITO_A, self::NOTA_CREDITO_B, self::NOTA_CREDITO_C];

    /** Tipos de documento del receptor, según la tabla de ARCA. */
    public const DOC_CUIT = 80;

    public const DOC_DNI = 96;

    public const DOC_CONSUMIDOR_FINAL = 99;

    public function esNotaDeCredito(): bool
    {
        return in_array($this->tipo, self::TIPOS_DE_NOTA_DE_CREDITO, true);
    }

    public function nombre(): string
    {
        return self::NOMBRES[$this->tipo] ?? 'Comprobante';
    }

    /** "0003-00000127", como se escribe en el papel. */
    public function numeroCompleto(): string
    {
        return sprintf('%04d-%08d', $this->punto_venta, $this->numero);
    }

    /**
     * @return BelongsTo<Pedido, $this>
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }
}
