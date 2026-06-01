<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CharityDocument;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Hiển thị giao diện trang Đăng ký (GET /register).
     * Inertia sẽ render component Vue tại Pages/Auth/Register.vue.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Xử lý yêu cầu đăng ký tài khoản mới (POST /register).
     *
     * LUỒNG XỬ LÝ CHÍNH:
     * 1. Validate dữ liệu cơ bản (name, email, password, role, tọa độ GPS).
     * 2. Nếu role là 'charity' -> Validate thêm 2 file minh chứng bắt buộc.
     * 3. Sử dụng DB::transaction để ghi đồng thời vào bảng `users` và `charity_documents`.
     *    Nếu bất kỳ bước nào lỗi -> toàn bộ dữ liệu sẽ được rollback, không để dữ liệu rác.
     * 4. Đăng nhập tự động và điều hướng theo role (thông qua route 'dashboard').
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // ============================================================
        // BƯỚC 1: VALIDATE DỮ LIỆU CƠ BẢN (áp dụng cho mọi vai trò)
        // ============================================================
        $rules = [
            'name'      => 'required|string|max:255',
            'email'     => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password'  => ['required', 'confirmed', Rules\Password::defaults()],
            'role'      => 'required|string|in:personal,charity,small_business',
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ];

        // ============================================================
        // BƯỚC 2: NẾU LÀ TỔ CHỨC TỪ THIỆN -> YÊU CẦU THÊM FILE
        // ============================================================
        // Chỉ khi role = 'charity', hệ thống mới bắt buộc upload 2 loại minh chứng:
        //   - legal_license: Giấy phép hoạt động (pdf, ảnh scan)
        //   - facility_image: Hình ảnh cơ sở thực tế
        // Giới hạn: mỗi file tối đa 2MB, chấp nhận định dạng png/jpg/jpeg/pdf.
        if ($request->role === 'charity') {
            $rules['legal_license']  = 'required|file|mimes:png,jpg,jpeg,pdf|max:2048';
            $rules['facility_image'] = 'required|file|mimes:png,jpg,jpeg,pdf|max:2048';
        }

        // Gọi validate với thông báo lỗi tiếng Việt tùy chỉnh
        $request->validate($rules, [
            'legal_license.required'  => 'Vui lòng tải lên Giấy phép hoạt động của tổ chức.',
            'legal_license.mimes'     => 'Giấy phép chỉ chấp nhận định dạng: PNG, JPG, JPEG hoặc PDF.',
            'legal_license.max'       => 'Dung lượng Giấy phép không được vượt quá 2MB.',
            'facility_image.required' => 'Vui lòng tải lên Hình ảnh cơ sở của tổ chức.',
            'facility_image.mimes'    => 'Hình ảnh cơ sở chỉ chấp nhận định dạng: PNG, JPG, JPEG hoặc PDF.',
            'facility_image.max'      => 'Dung lượng Hình ảnh cơ sở không được vượt quá 2MB.',
        ]);

        // ============================================================
        // BƯỚC 3: GHI DỮ LIỆU VÀO DATABASE BẰNG TRANSACTION
        // ============================================================
        // DB::transaction đảm bảo tính toàn vẹn dữ liệu (Atomicity):
        //   - Nếu tạo user thành công nhưng lưu file thất bại -> rollback user.
        //   - Nếu tạo user + lưu file OK nhưng ghi charity_documents lỗi -> rollback tất cả.
        // => Không bao giờ xuất hiện tình trạng user tồn tại mà thiếu tài liệu minh chứng.
        $user = DB::transaction(function () use ($request) {

            // 3a. Tạo bản ghi user mới trong bảng `users`
            $user = User::create([
                'name'      => $request->name,
                'email'     => $request->email,
                'password'  => Hash::make($request->password),
                'role'      => $request->role,
                // Cá nhân / Doanh nghiệp nhỏ -> verified (hoạt động ngay)
                // Tổ chức từ thiện -> pending (chờ Admin duyệt hồ sơ)
                'status'    => $request->role === 'charity' ? 'pending' : 'verified',
                'latitude'  => $request->latitude,
                'longitude' => $request->longitude,
            ]);

            // 3b. Nếu là Tổ chức từ thiện -> Lưu file và ghi vào bảng `charity_documents`
            if ($request->role === 'charity') {

                // Lưu file Giấy phép hoạt động vào thư mục storage/app/public/charities/documents/
                $licensePath = $request->file('legal_license')
                    ->store('charities/documents', 'public');

                // Lưu file Hình ảnh cơ sở vào thư mục storage/app/public/charities/images/
                $imagePath = $request->file('facility_image')
                    ->store('charities/images', 'public');

                // Ghi 2 bản ghi tương ứng vào bảng charity_documents
                // Mỗi bản ghi có document_type riêng để phân biệt loại tài liệu
                CharityDocument::insert([
                    [
                        'user_id'       => $user->id,
                        'document_type' => 'legal_license',   // Giấy phép hoạt động
                        'file_path'     => $licensePath,
                        'created_at'    => now(),
                    ],
                    [
                        'user_id'       => $user->id,
                        'document_type' => 'facility_image',  // Hình ảnh cơ sở
                        'file_path'     => $imagePath,
                        'created_at'    => now(),
                    ],
                ]);
            }

            return $user;
        });

        // ============================================================
        // BƯỚC 4: ĐĂNG NHẬP TỰ ĐỘNG VÀ ĐIỀU HƯỚNG
        // ============================================================
        // Phát sự kiện Registered (để Laravel gửi email xác minh nếu cần sau này)
        event(new Registered($user));

        // Đăng nhập tự động ngay sau khi tạo tài khoản thành công
        Auth::login($user);

        // Chuyển hướng về route 'dashboard' -> Bộ điều hướng trung tâm ở web.php
        // sẽ tự động phân làn theo role (admin/charity/personal/small_business)
        return redirect(route('dashboard', absolute: false));
    }
}
