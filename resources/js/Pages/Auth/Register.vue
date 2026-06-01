<script setup>
import { onMounted, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

// Biến reactive hiển thị tên file đã chọn trên giao diện
const licenseFileName = ref('');
const facilityFileName = ref('');

// Khởi tạo form đồng bộ với Database và giao diện mới
// Hai trường legal_license và facility_image khởi tạo = null,
// chỉ được gán giá trị File Object khi người dùng chọn role = 'charity'
const form = useForm({
    name: '',
    email: '',
    role: 'personal', // Mặc định chọn vai trò Cá nhân ban đầu
    latitude: null,
    longitude: null,
    password: '',
    password_confirmation: '',
    legal_license: null,   // File Object: Giấy phép hoạt động (chỉ cho charity)
    facility_image: null,  // File Object: Hình ảnh cơ sở (chỉ cho charity)
});

/**
 * Hàm bắt sự kiện @change trên <input type="file">.
 * Khi người dùng chọn file, trình duyệt trả về một FileList.
 * Ta lấy phần tử đầu tiên (files[0]) gán vào form của Inertia.
 * Inertia sẽ tự động chuyển request sang dạng multipart/form-data
 * khi phát hiện có File Object trong payload.
 */
const handleLicenseFile = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.legal_license = file;
        licenseFileName.value = file.name;
    }
};

const handleFacilityFile = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.facility_image = file;
        facilityFileName.value = file.name;
    }
};

// Hàm tự động gọi quyền định vị của trình duyệt khi mở trang
onMounted(() => {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                form.latitude = position.coords.latitude;
                form.longitude = position.coords.longitude;
            },
            (error) => {
                console.warn("Không thể lấy vị trí GPS, hệ thống gán mặc định TP.HCM: ", error.message);
                // Tọa độ dự phòng trung tâm TP.HCM nếu người dùng từ chối cấp quyền
                form.latitude = 10.762622;
                form.longitude = 106.660172;
            }
        );
    }
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Đăng ký tài khoản - ShareFood" />

    <div class="min-h-screen bg-gray-50 text-gray-800 font-sans flex flex-col justify-center items-center p-4 py-10">
        <div class="w-full max-w-md bg-white border border-gray-100 rounded-3xl p-8 shadow-xl shadow-gray-100/70 space-y-6">
            
            <div class="flex flex-col items-center space-y-4 text-center">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-md shadow-emerald-200">S</div>
                    <span class="text-xl font-bold text-gray-950 tracking-tight">ShareFood<span class="text-emerald-600">.vn</span></span>
                </div>
                <div class="space-y-1">
                    <h1 class="text-2xl font-bold text-gray-950 tracking-tight">Đăng ký thành viên</h1>
                    <p class="text-xs text-gray-500">Tham gia mạng lưới thực phẩm nhân ái không lãng phí</p>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                
                <div class="space-y-1.5">
                    <label for="role" class="text-xs font-semibold text-gray-700 tracking-wide">Bạn tham gia với tư cách nào?</label>
                    <select 
                        id="role" 
                        v-model="form.role"
                        required
                        class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent cursor-pointer font-medium"
                    >
                        <option value="personal">Cá nhân (Nhận thực phẩm lẻ / Tặng đồ ăn lẻ)</option>
                        <option value="charity">Tổ chức từ thiện (Cần duyệt minh chứng pháp lý)</option>
                        <option value="small_business">Hộ kinh doanh nhỏ (Cửa hàng bánh, quán ăn...)</option>
                    </select>
                    <p v-if="form.errors.role" class="text-xs text-red-500 font-medium mt-1">{{ form.errors.role }}</p>
                </div>

                <div class="space-y-1.5">
                    <label for="name" class="text-xs font-semibold text-gray-700 tracking-wide">
                        {{ form.role === 'charity' ? 'Tên tổ chức từ thiện' : 'Họ và tên' }}
                    </label>
                    <input 
                        id="name" 
                        type="text" 
                        v-model="form.name" 
                        required 
                        autocomplete="name"
                        class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                        :placeholder="form.role === 'charity' ? 'Mái ấm tình thương Bình Thạnh...' : 'Nguyễn Văn A'"
                    />
                    <p v-if="form.errors.name" class="text-xs text-red-500 font-medium mt-1">{{ form.errors.name }}</p>
                </div>

                <div class="space-y-1.5">
                    <label for="email" class="text-xs font-semibold text-gray-700 tracking-wide">Địa chỉ Email</label>
                    <input 
                        id="email" 
                        type="email" 
                        v-model="form.email" 
                        required 
                        autocomplete="username"
                        class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                        placeholder="contact@domain.com"
                    />
                    <p v-if="form.errors.email" class="text-xs text-red-500 font-medium mt-1">{{ form.errors.email }}</p>
                </div>

                <div class="space-y-1.5">
                    <label for="password" class="text-xs font-semibold text-gray-700 tracking-wide">Mật khẩu bảo mật</label>
                    <input 
                        id="password" 
                        type="password" 
                        v-model="form.password" 
                        required 
                        autocomplete="new-password"
                        class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                        placeholder="Tối thiểu 8 ký tự"
                    />
                    <p v-if="form.errors.password" class="text-xs text-red-500 font-medium mt-1">{{ form.errors.password }}</p>
                </div>

                <div class="space-y-1.5">
                    <label for="password_confirmation" class="text-xs font-semibold text-gray-700 tracking-wide">Xác nhận lại mật khẩu</label>
                    <input 
                        id="password_confirmation" 
                        type="password" 
                        v-model="form.password_confirmation" 
                        required 
                        autocomplete="new-password"
                        class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                        placeholder="Nhập lại mật khẩu"
                    />
                    <p v-if="form.errors.password_confirmation" class="text-xs text-red-500 font-medium mt-1">{{ form.errors.password_confirmation }}</p>
                </div>

                <!-- ============================================= -->
                <!-- KHỐI UPLOAD TÀI LIỆU - CHỈ HIỆN KHI ROLE = CHARITY -->
                <!-- v-if kiểm tra điều kiện: chỉ khi người dùng chọn -->
                <!-- vai trò "Tổ chức từ thiện" thì 2 ô upload mới xuất hiện -->
                <!-- ============================================= -->
                <div v-if="form.role === 'charity'" class="space-y-4 bg-amber-50/60 border border-amber-200/60 rounded-2xl p-4">
                    <div class="flex items-start space-x-2">
                        <span class="text-amber-600 text-lg leading-none">📋</span>
                        <div class="space-y-0.5">
                            <p class="text-xs font-bold text-amber-800">Minh chứng pháp lý bắt buộc</p>
                            <p class="text-[11px] text-amber-700/80">Tài khoản từ thiện cần được Admin xác minh trước khi tạo chiến dịch. Vui lòng nộp đầy đủ 2 loại tài liệu dưới đây.</p>
                        </div>
                    </div>

                    <!-- Upload: Giấy phép hoạt động -->
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 tracking-wide">Giấy phép hoạt động <span class="text-red-500">*</span></label>
                        <label class="flex items-center justify-center w-full border-2 border-dashed border-gray-300 hover:border-emerald-400 rounded-xl py-3 px-4 cursor-pointer transition bg-white group">
                            <div class="flex items-center space-x-2 text-sm">
                                <span class="text-gray-400 group-hover:text-emerald-500 transition">📄</span>
                                <span class="text-gray-500 group-hover:text-gray-700 transition text-xs">
                                    {{ licenseFileName || 'Chọn file PDF hoặc ảnh scan (tối đa 2MB)' }}
                                </span>
                            </div>
                            <input 
                                type="file" 
                                class="hidden" 
                                accept=".png,.jpg,.jpeg,.pdf"
                                @change="handleLicenseFile"
                            />
                        </label>
                        <p v-if="form.errors.legal_license" class="text-xs text-red-500 font-medium">{{ form.errors.legal_license }}</p>
                    </div>

                    <!-- Upload: Hình ảnh cơ sở -->
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-gray-700 tracking-wide">Hình ảnh cơ sở thực tế <span class="text-red-500">*</span></label>
                        <label class="flex items-center justify-center w-full border-2 border-dashed border-gray-300 hover:border-emerald-400 rounded-xl py-3 px-4 cursor-pointer transition bg-white group">
                            <div class="flex items-center space-x-2 text-sm">
                                <span class="text-gray-400 group-hover:text-emerald-500 transition">🏠</span>
                                <span class="text-gray-500 group-hover:text-gray-700 transition text-xs">
                                    {{ facilityFileName || 'Chọn ảnh chụp mặt tiền, phòng bếp... (tối đa 2MB)' }}
                                </span>
                            </div>
                            <input 
                                type="file" 
                                class="hidden" 
                                accept=".png,.jpg,.jpeg,.pdf"
                                @change="handleFacilityFile"
                            />
                        </label>
                        <p v-if="form.errors.facility_image" class="text-xs text-red-500 font-medium">{{ form.errors.facility_image }}</p>
                    </div>
                </div>

                <div class="flex items-center space-x-2 text-[11px] text-gray-400 bg-gray-50 p-2 rounded-xl border border-dashed border-gray-200">
                    <span :class="form.latitude ? 'bg-emerald-500 animate-pulse' : 'bg-gray-300'" class="w-1.5 h-1.5 rounded-full"></span>
                    <span>{{ form.latitude ? 'Đã liên kết vị trí GPS thành công' : 'Đang yêu cầu tọa độ định vị...' }}</span>
                </div>

                <button 
                    type="submit" 
                    :disabled="form.processing"
                    class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm py-3 px-4 rounded-xl shadow-md shadow-emerald-100 transition duration-200 disabled:opacity-50"
                >
                    <span v-if="form.processing">Đang tạo tài khoản...</span>
                    <span v-else>Hoàn tất đăng ký</span>
                </button>
            </form>

            <div class="h-px bg-gray-100 my-4"></div>

            <p class="text-center text-xs text-gray-500">
                Đã có tài khoản từ trước? 
                <Link :href="route('login')" class="text-emerald-600 font-semibold hover:underline">Đăng nhập</Link>
            </p>
        </div>
    </div>
</template>