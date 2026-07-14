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
    phone: '',             // Số điện thoại
    address: '',           // Địa chỉ chi tiết (số nhà, tên đường...)
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

// Tìm tọa độ GPS dựa trên địa chỉ chữ (Geocoding)
const geocodeAddress = async () => {
    const query = form.address;
    if (!query || query.trim().length < 6) return;

    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`, {
            headers: {
                'Accept-Language': 'vi' // Ưu tiên kết quả địa danh tiếng Việt
            }
        });
        const data = await response.json();
        
        if (data && data.length > 0) {
            const result = data[0];
            form.latitude = parseFloat(parseFloat(result.lat).toFixed(6));
            form.longitude = parseFloat(parseFloat(result.lon).toFixed(6));
        }
    } catch (error) {
        console.error("Lỗi tìm kiếm tọa độ: ", error);
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

    <div class="h-screen overflow-hidden bg-white text-gray-800 font-sans flex">
        
        <!-- CỘT TRÁI: HÌNH ẢNH & SLOGAN (Chỉ hiện trên PC) -->
        <div class="hidden lg:flex lg:w-1/2 relative bg-emerald-900 overflow-hidden fixed h-screen top-0 left-0">
            <!-- Ảnh nền chất lượng cao (ảnh đồ ăn từ thiện/chia sẻ) -->
            <img src="https://images.pexels.com/photos/6995220/pexels-photo-6995220.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2" alt="ShareFood Join" class="absolute inset-0 w-full h-full object-cover opacity-50 mix-blend-overlay hover:scale-105 transition-transform duration-1000">
            
            <!-- Hiệu ứng chuyển sắc Gradient -->
            <div class="absolute inset-0 bg-gradient-to-t from-emerald-950 via-emerald-900/80 to-transparent"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-emerald-950/80 to-transparent"></div>
            
            <!-- Nội dung overlay -->
            <div class="relative z-10 flex flex-col justify-end p-16 h-full text-white w-full">
                <div class="flex items-center space-x-4 mb-8 animate-fade-in-up">
                    <div class="w-16 h-16 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center text-white font-bold text-4xl border border-white/20 shadow-2xl">S</div>
                    <span class="text-3xl font-bold text-white tracking-tight">ShareFood<span class="text-emerald-400">.vn</span></span>
                </div>
                <h2 class="text-5xl font-black tracking-tight leading-[1.1] mb-5 animate-fade-in-up" style="animation-delay: 0.1s">
                    Trở thành một phần<br>
                    <span class="text-emerald-400">của sự thay đổi.</span>
                </h2>
                <p class="text-lg text-emerald-100/90 max-w-md animate-fade-in-up leading-relaxed" style="animation-delay: 0.2s">
                    Đăng ký tài khoản ngay hôm nay để bắt đầu hành trình sẻ chia thực phẩm, lan tỏa sự ấm áp đến với mọi người.
                </p>
                
                <div class="flex flex-col space-y-4 mt-12 animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="flex items-center space-x-3 text-emerald-200">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span class="text-sm">Giải cứu thực phẩm dư thừa</span>
                    </div>
                    <div class="flex items-center space-x-3 text-emerald-200">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span class="text-sm">Hỗ trợ các tổ chức từ thiện</span>
                    </div>
                    <div class="flex items-center space-x-3 text-emerald-200">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span class="text-sm">Kết nối cộng đồng nhân ái</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- CỘT PHẢI: FORM ĐĂNG KÝ -->
        <!-- Đẩy cột phải sang một nửa trên PC bằng marginLeft -->
        <div class="w-full lg:w-1/2 lg:ml-auto flex flex-col justify-center items-center p-4 sm:p-8 relative bg-gray-50/50 h-screen overflow-hidden">
            <!-- Nút trang chủ góc phải -->
            <Link href="/" class="absolute top-4 right-6 text-[11px] font-semibold text-gray-500 hover:text-emerald-600 flex items-center transition bg-white px-3 py-1.5 rounded-full shadow-sm border border-gray-100 z-20">
                <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                Về trang chủ
            </Link>

            <div class="w-full max-w-[560px] bg-white border border-gray-100 rounded-3xl p-6 shadow-2xl shadow-emerald-900/5 space-y-4 animate-fade-in z-10 relative">
                
                <div class="flex flex-col space-y-1">
                    <div class="lg:hidden flex items-center space-x-2 mb-2 justify-center">
                        <div class="w-8 h-8 bg-emerald-600 rounded-lg flex items-center justify-center text-white font-bold text-lg shadow-md shadow-emerald-200">S</div>
                        <span class="text-lg font-bold text-gray-950 tracking-tight">ShareFood<span class="text-emerald-600">.vn</span></span>
                    </div>
                    <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight text-center lg:text-left">Đăng ký thành viên</h1>
                    <p class="text-[11px] text-gray-500 text-center lg:text-left">Cùng nhau tạo nên những bữa ăn ý nghĩa</p>
                </div>

                <form @submit.prevent="submit" class="space-y-3">
                    
                    <div class="space-y-2">
                        <label class="text-[10px] font-bold text-gray-700 tracking-wide uppercase block">Vai trò tham gia</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label 
                                class="relative flex flex-col items-center justify-center p-3 rounded-2xl border-2 cursor-pointer transition-all duration-200 overflow-visible"
                                :class="form.role === 'personal' ? 'border-emerald-500 bg-emerald-50 shadow-sm shadow-emerald-100' : 'border-gray-100 bg-gray-50/50 hover:bg-gray-100 hover:border-gray-200'"
                            >
                                <input type="radio" v-model="form.role" value="personal" class="sr-only" name="role" />
                                <div class="text-2xl mb-1.5 transition-transform duration-200" :class="form.role === 'personal' ? 'scale-110 drop-shadow-sm' : 'opacity-60 grayscale'">👤</div>
                                <span class="text-[11px] font-bold text-center leading-snug" :class="form.role === 'personal' ? 'text-emerald-700' : 'text-gray-500'">Cá nhân / Hộ KD</span>
                                
                                <div v-if="form.role === 'personal'" class="absolute -top-2 -right-2 w-5 h-5 bg-emerald-500 text-white rounded-full flex items-center justify-center shadow-md animate-in zoom-in duration-200">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                            </label>

                            <label 
                                class="relative flex flex-col items-center justify-center p-3 rounded-2xl border-2 cursor-pointer transition-all duration-200 overflow-visible"
                                :class="form.role === 'charity' ? 'border-amber-500 bg-amber-50 shadow-sm shadow-amber-100' : 'border-gray-100 bg-gray-50/50 hover:bg-gray-100 hover:border-gray-200'"
                            >
                                <input type="radio" v-model="form.role" value="charity" class="sr-only" name="role" />
                                <div class="text-2xl mb-1.5 transition-transform duration-200" :class="form.role === 'charity' ? 'scale-110 drop-shadow-sm' : 'opacity-60 grayscale'">🏢</div>
                                <span class="text-[11px] font-bold text-center leading-snug" :class="form.role === 'charity' ? 'text-amber-700' : 'text-gray-500'">Tổ chức từ thiện</span>
                                
                                <div v-if="form.role === 'charity'" class="absolute -top-2 -right-2 w-5 h-5 bg-amber-500 text-white rounded-full flex items-center justify-center shadow-md animate-in zoom-in duration-200">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                            </label>
                        </div>
                        <p v-if="form.errors.role" class="text-[10px] text-red-500 font-medium mt-1 text-center">{{ form.errors.role }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label for="name" class="text-[10px] font-bold text-gray-700 tracking-wide uppercase">
                                {{ form.role === 'charity' ? 'Tên tổ chức' : 'Họ và tên' }}
                            </label>
                            <input 
                                id="name" 
                                type="text" 
                                v-model="form.name" 
                                required 
                                autocomplete="name"
                                class="w-full bg-gray-50 border border-gray-200 text-xs text-gray-900 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition shadow-sm"
                                :placeholder="form.role === 'charity' ? 'Mái ấm...' : 'Nguyễn Văn A'"
                            />
                            <p v-if="form.errors.name" class="text-[10px] text-red-500">{{ form.errors.name }}</p>
                        </div>

                        <div class="space-y-1">
                            <label for="phone" class="text-[10px] font-bold text-gray-700 tracking-wide uppercase">Số điện thoại</label>
                            <input 
                                id="phone" 
                                type="tel" 
                                v-model="form.phone" 
                                required 
                                autocomplete="tel"
                                class="w-full bg-gray-50 border border-gray-200 text-xs text-gray-900 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition shadow-sm"
                                placeholder="09xx..."
                            />
                            <p v-if="form.errors.phone" class="text-[10px] text-red-500">{{ form.errors.phone }}</p>
                        </div>

                        <div class="space-y-1">
                            <label for="email" class="text-[10px] font-bold text-gray-700 tracking-wide uppercase">Địa chỉ Email</label>
                            <input 
                                id="email" 
                                type="email" 
                                v-model="form.email" 
                                required 
                                autocomplete="username"
                                class="w-full bg-gray-50 border border-gray-200 text-xs text-gray-900 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition shadow-sm"
                                placeholder="contact@domain.com"
                            />
                            <p v-if="form.errors.email" class="text-[10px] text-red-500">{{ form.errors.email }}</p>
                        </div>

                        <div class="space-y-1">
                            <label for="address" class="text-[10px] font-bold text-gray-700 tracking-wide uppercase">Địa chỉ chi tiết</label>
                            <input 
                                id="address" 
                                type="text" 
                                v-model="form.address" 
                                required 
                                class="w-full bg-gray-50 border border-gray-200 text-xs text-gray-900 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition shadow-sm"
                                placeholder="Số nhà, đường, phường..."
                            />
                            <div class="flex justify-between items-center mt-0.5">
                                <p v-if="form.errors.address" class="text-[9px] text-red-500">{{ form.errors.address }}</p>
                                <div v-else></div>
                                <button 
                                    type="button" 
                                    @click="geocodeAddress" 
                                    class="text-[9px] text-emerald-600 hover:text-emerald-700 font-bold focus:outline-none transition"
                                >
                                    📍 Lấy tọa độ
                                </button>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label for="password" class="text-[10px] font-bold text-gray-700 tracking-wide uppercase">Mật khẩu</label>
                            <input 
                                id="password" 
                                type="password" 
                                v-model="form.password" 
                                required 
                                autocomplete="new-password"
                                class="w-full bg-gray-50 border border-gray-200 text-xs text-gray-900 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition shadow-sm"
                                placeholder="••••••••"
                            />
                            <p v-if="form.errors.password" class="text-[10px] text-red-500">{{ form.errors.password }}</p>
                        </div>

                        <div class="space-y-1">
                            <label for="password_confirmation" class="text-[10px] font-bold text-gray-700 tracking-wide uppercase">Xác nhận MK</label>
                            <input 
                                id="password_confirmation" 
                                type="password" 
                                v-model="form.password_confirmation" 
                                required 
                                autocomplete="new-password"
                                class="w-full bg-gray-50 border border-gray-200 text-xs text-gray-900 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition shadow-sm"
                                placeholder="••••••••"
                            />
                            <p v-if="form.errors.password_confirmation" class="text-[10px] text-red-500">{{ form.errors.password_confirmation }}</p>
                        </div>
                    </div>



                    <!-- KHỐI UPLOAD TÀI LIỆU - CHARITY -->
                    <div v-if="form.role === 'charity'" class="grid grid-cols-2 gap-3 bg-gradient-to-r from-amber-50 to-amber-100/50 border border-amber-200 rounded-2xl p-3 shadow-inner">
                        <div class="space-y-1">
                            <label class="text-[9px] font-bold text-gray-700 tracking-wide uppercase">Giấy phép HĐ <span class="text-red-500">*</span></label>
                            <label class="flex items-center justify-center w-full border border-dashed border-amber-300 hover:border-amber-500 rounded-lg py-2 px-2 cursor-pointer transition bg-white/80 group">
                                <div class="flex items-center space-x-1 text-xs">
                                    <span class="text-amber-500 group-hover:scale-110 transition">📄</span>
                                    <span class="text-gray-600 group-hover:text-gray-900 transition text-[9px] font-medium truncate max-w-[120px]">
                                        {{ licenseFileName || 'Chọn file...' }}
                                    </span>
                                </div>
                                <input type="file" class="hidden" accept=".png,.jpg,.jpeg,.pdf" @change="handleLicenseFile" />
                            </label>
                        </div>

                        <div class="space-y-1">
                            <label class="text-[9px] font-bold text-gray-700 tracking-wide uppercase">Ảnh cơ sở <span class="text-red-500">*</span></label>
                            <label class="flex items-center justify-center w-full border border-dashed border-amber-300 hover:border-amber-500 rounded-lg py-2 px-2 cursor-pointer transition bg-white/80 group">
                                <div class="flex items-center space-x-1 text-xs">
                                    <span class="text-amber-500 group-hover:scale-110 transition">🏠</span>
                                    <span class="text-gray-600 group-hover:text-gray-900 transition text-[9px] font-medium truncate max-w-[120px]">
                                        {{ facilityFileName || 'Ảnh thực tế...' }}
                                    </span>
                                </div>
                                <input type="file" class="hidden" accept=".png,.jpg,.jpeg,.pdf" @change="handleFacilityFile" />
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2 text-[9px] font-medium text-gray-500 bg-gray-50 p-2 rounded-lg border border-gray-200 mt-1">
                        <span :class="form.latitude ? 'bg-emerald-500 shadow-sm shadow-emerald-500/50' : 'bg-gray-300'" class="w-1.5 h-1.5 rounded-full transition-colors duration-500 flex-shrink-0"></span>
                        <span>{{ form.latitude ? 'GPS OK' : 'Đang đợi tọa độ GPS...' }}</span>
                    </div>

                    <button 
                        type="submit" 
                        :disabled="form.processing"
                        class="w-full bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-700 text-white font-bold text-xs py-3 rounded-xl shadow-lg shadow-emerald-500/30 transition-all duration-200 mt-2"
                    >
                        <span v-if="form.processing" class="flex items-center justify-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Đang xử lý dữ liệu...
                        </span>
                        <span v-else>Hoàn tất đăng ký tài khoản</span>
                    </button>
                </form>

                <div class="relative py-2">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-200"></div>
                    </div>
                    <div class="relative flex justify-center">
                        <span class="bg-white px-3 text-[10px] text-gray-400 font-medium">HOẶC</span>
                    </div>
                </div>

                <p class="text-center text-xs text-gray-600 font-medium">
                    Đã có tài khoản? 
                    <Link :href="route('login')" class="text-emerald-600 font-bold hover:text-emerald-700 hover:underline transition ml-1">Đăng nhập</Link>
                </p>
            </div>
            
            <!-- Hình tròn trang trí nổi mờ mờ ở góc -->
            <div class="absolute -bottom-32 -left-32 w-64 h-64 bg-emerald-400/20 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -top-32 -right-32 w-64 h-64 bg-teal-400/10 rounded-full blur-3xl pointer-events-none"></div>
        </div>
    </div>
</template>
