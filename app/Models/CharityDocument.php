<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model CharityDocument - Quản lý tài liệu minh chứng pháp lý của Tổ chức từ thiện.
 *
 * Mỗi tổ chức từ thiện khi đăng ký phải nộp 2 loại tài liệu:
 *   - legal_license: Giấy phép hoạt động (scan PDF hoặc ảnh chụp)
 *   - facility_image: Hình ảnh thực tế cơ sở (ảnh chụp mặt tiền, phòng bếp...)
 *
 * Quan hệ: Mỗi tài liệu thuộc về 1 user (N-1 với bảng users).
 */
class CharityDocument extends Model
{
    /**
     * Tên bảng tương ứng trong Database MySQL.
     */
    protected $table = 'charity_documents';

    /**
     * Tắt tự động quản lý cột updated_at
     * (bảng charity_documents chỉ có created_at, không có updated_at).
     */
    public $timestamps = false;

    /**
     * Danh sách các cột được phép gán dữ liệu hàng loạt (Mass Assignment).
     * Đây là lớp bảo vệ chống lỗ hổng ghi dữ liệu trái phép.
     */
    protected $fillable = [
        'user_id',
        'document_type',
        'file_path',
        'created_at',
    ];

    /**
     * Quan hệ: Tài liệu này thuộc về User nào.
     * Ví dụ: $document->user sẽ trả về đối tượng User sở hữu tài liệu.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
