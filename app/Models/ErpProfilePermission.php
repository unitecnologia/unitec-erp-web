<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpProfilePermission extends Model
{
    protected $fillable = [
        'erp_profile_id',
        'permission_key',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ErpProfile::class, 'erp_profile_id');
    }
}
