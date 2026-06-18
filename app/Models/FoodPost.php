<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FoodPost extends Model
{
    // Cho phép ghi tất cả các trường dữ liệu (Mass Assignment)
    protected $guarded = [];

    /**
     * Mối quan hệ với người dùng (người đăng bài)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mối quan hệ với các yêu cầu nhận thực phẩm lẻ
     */
    public function claims()
    {
        return $this->hasMany(FoodClaim::class);
    }
}
