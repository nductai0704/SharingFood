<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'categories';
    protected $guarded = [];

    // Tắt timestamps nếu bảng này do admin quản lý thủ công (hoặc tùy cấu hình)
    public $timestamps = false;
}
