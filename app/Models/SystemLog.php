<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    // Bảng này chỉ lưu log theo thời gian thực nên không cần cột updated_at
    public $timestamps = false;

    protected $table = 'system_logs';

    protected $fillable = [
        'user_id',
        'action',
        'description',
        'ip_address',
        'created_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Mối quan hệ với bảng Users (Người thực hiện hành động nếu có)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
