<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    public const KINDS = ['courier' => 'Curier la adresă', 'locker' => 'Locker / easybox', 'pickup' => 'Ridicare personală'];

    public const CARRIERS = ['sameday' => 'Sameday', 'fan' => 'FAN Courier', 'cargus' => 'Cargus', 'dpd' => 'DPD', 'gls' => 'GLS'];

    protected $guarded = ['id'];

    protected $casts = ['cod_allowed' => 'boolean', 'is_active' => 'boolean', 'counties' => 'array'];
}
