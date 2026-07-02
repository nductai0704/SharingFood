<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

import { ref, onMounted } from 'vue';
// State quản lý Tab hiện tại
const activeTab = ref('traditional'); // 'traditional' hoặc 'qr'
import QrcodeVue from 'qrcode.vue';

// Tạo token ngẫu nhiên gồm 10 ký tự
const generateToken = () => Math.random().toString(36).substring(2, 12).toUpperCase();

// Token dùng để đối soát QR
const qrToken = ref(generateToken()); 
const isScanning = ref(false);

// Vị trí để lắng nghe Laravel Echo sau này
onMounted(() => {
    if (window.Echo) {
        window.Echo.channel(`qr-login.${qrToken.value}`)
            .listen('.qr.login', (e) => {
                isScanning.value = true;
                // Tín hiệu đã nhận! Gọi API callback để login thực tế
                import('@inertiajs/vue3').then(({ router }) => {
                    setTimeout(() => {
                        router.post(route('qr.login.callback'), { user_id: e.userId });
                    }, 1000); // Thêm delay nhỏ để thấy hiệu ứng loading
                });
            });
    }
});
defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Đăng nhập - ShareFood" />

    <div class="h-screen overflow-hidden bg-white text-gray-800 font-sans flex">
        
        <!-- CỘT TRÁI: HÌNH ẢNH & SLOGAN (Chỉ hiện trên PC) -->
        <div class="hidden lg:flex lg:w-1/2 relative bg-emerald-900 overflow-hidden">
            <!-- Ảnh nền chất lượng cao -->
            <img src="https://images.pexels.com/photos/6995201/pexels-photo-6995201.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2" alt="ShareFood" class="absolute inset-0 w-full h-full object-cover opacity-50 mix-blend-overlay hover:scale-105 transition-transform duration-1000">
            
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
                    Cùng nhau lan tỏa<br>
                    <span class="text-emerald-400">hương vị yêu thương.</span>
                </h2>
                <p class="text-lg text-emerald-100/90 max-w-md animate-fade-in-up leading-relaxed mb-12" style="animation-delay: 0.2s">
                    Tham gia mạng lưới ShareFood để giảm thiểu lãng phí thực phẩm và mang đến những bữa ăn ấm áp cho cộng đồng.
                </p>
            </div>
        </div>

        <!-- CỘT PHẢI: FORM ĐĂNG NHẬP -->
        <div class="w-full lg:w-1/2 flex flex-col justify-center items-center p-6 sm:p-12 relative bg-gray-50/50 h-screen overflow-hidden">
            <!-- Nút trang chủ góc phải -->
            <Link href="/" class="absolute top-8 right-8 text-sm font-semibold text-gray-500 hover:text-emerald-600 flex items-center transition bg-white px-4 py-2 rounded-full shadow-sm border border-gray-100">
                <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                Về trang chủ
            </Link>

            <div class="w-full max-w-[420px] bg-white border border-gray-100 rounded-[2rem] p-8 sm:p-10 shadow-2xl shadow-emerald-900/5 space-y-6 animate-fade-in z-10 relative">
                
                <div class="flex flex-col space-y-2">
                    <div class="lg:hidden flex items-center space-x-3 mb-4 justify-center">
                        <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-md shadow-emerald-200">S</div>
                        <span class="text-xl font-bold text-gray-950 tracking-tight">ShareFood<span class="text-emerald-600">.vn</span></span>
                    </div>
                    <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight text-center lg:text-left">Đăng nhập</h1>
                    <p class="text-sm text-gray-500 text-center lg:text-left">Chào mừng bạn quay trở lại với cộng đồng</p>
                </div>

                <!-- THANH CHUYỂN TAB ĐĂNG NHẬP -->
                <div class="flex p-1 bg-gray-100/80 backdrop-blur-sm rounded-xl mb-6 border border-gray-200/50">
                    <!-- Nút Đăng nhập Truyền thống -->
                    <button 
                        @click="activeTab = 'traditional'"
                        :class="activeTab === 'traditional' ? 'bg-white text-gray-900 shadow-sm border-gray-200/50' : 'text-gray-500 hover:text-gray-700'"
                        class="flex-1 py-2 text-sm font-semibold rounded-lg transition-all border border-transparent"
                    >
                        Mật khẩu
                    </button>

                    <!-- Nút Đăng nhập QR -->
                    <button 
                        @click="activeTab = 'qr'"
                        :class="activeTab === 'qr' ? 'bg-white text-gray-900 shadow-sm border-gray-200/50' : 'text-gray-500 hover:text-gray-700'"
                        class="flex-1 py-2 text-sm font-semibold rounded-lg transition-all border border-transparent flex items-center justify-center gap-1.5"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
                        Mã QR
                    </button>
                </div>

                <div v-if="status" class="bg-emerald-50 border border-emerald-100 text-emerald-800 text-xs font-medium p-3 rounded-xl animate-fade-in flex items-start gap-2">
                    <svg class="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>{{ status }}</span>
                </div>

                <div class="mt-4">
                    <form v-if="activeTab === 'traditional'" @submit.prevent="submit" class="space-y-4 animate-fade-in">
                        <div class="space-y-1.5">
                            <label for="email" class="text-xs font-bold text-gray-700 tracking-wide uppercase">Địa chỉ Email</label>
                            <input 
                                id="email" 
                                type="email" 
                                v-model="form.email" 
                                required 
                                autofocus 
                                autocomplete="username"
                                class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition shadow-sm"
                                placeholder="ten@viethan.com"
                            />
                            <p v-if="form.errors.email" class="text-xs text-red-500 font-medium mt-1">{{ form.errors.email }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <div class="flex justify-between items-center">
                                <label for="password" class="text-xs font-bold text-gray-700 tracking-wide uppercase">Mật khẩu</label>
                                <Link 
                                    v-if="canResetPassword" 
                                    :href="route('password.request')" 
                                    class="text-xs text-emerald-600 hover:text-emerald-700 hover:underline font-semibold transition"
                                >
                                    Quên mật khẩu?
                                </Link>
                            </div>
                            <input 
                                id="password" 
                                type="password" 
                                v-model="form.password" 
                                required 
                                autocomplete="current-password"
                                class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition shadow-sm"
                                placeholder="••••••••"
                            />
                            <p v-if="form.errors.password" class="text-xs text-red-500 font-medium mt-1">{{ form.errors.password }}</p>
                        </div>

                        <div class="flex items-center pb-2">
                            <input 
                                id="remember" 
                                type="checkbox" 
                                v-model="form.remember" 
                                class="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500 focus:ring-offset-0 cursor-pointer transition"
                            />
                            <label for="remember" class="ml-2.5 text-sm font-medium text-gray-600 cursor-pointer select-none">Duy trì đăng nhập</label>
                        </div>

                        <button 
                            type="submit" 
                            :disabled="form.processing"
                            class="w-full bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-700 hover:to-emerald-600 text-white font-bold text-sm py-3.5 px-4 rounded-xl shadow-lg shadow-emerald-500/30 transition-all duration-200 disabled:opacity-50 hover:-translate-y-0.5 active:translate-y-0"
                        >
                            <span v-if="form.processing" class="flex items-center justify-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Đang xác thực...
                            </span>
                            <span v-else>Đăng nhập hệ thống</span>
                        </button>
                    </form>

                    <div v-else class="flex flex-col items-center space-y-4 animate-fade-in py-1">
                        <div class="p-3 bg-white border border-gray-100 rounded-3xl shadow-xl shadow-emerald-900/5 relative group">
                            <!-- Hiệu ứng viền phát sáng -->
                            <div class="absolute -inset-0.5 bg-gradient-to-r from-emerald-400 to-teal-500 rounded-[1.4rem] blur opacity-20 group-hover:opacity-40 transition duration-500"></div>
                            
                            <div class="w-40 h-40 bg-white flex flex-col items-center justify-center rounded-2xl relative z-10 border border-gray-50 overflow-hidden">
                                <qrcode-vue v-if="!isScanning" :value="qrToken" :size="130" level="H" foreground="#047857" class="transition-transform duration-300 hover:scale-105" />
                                
                                <div v-else class="absolute inset-0 flex flex-col items-center justify-center bg-white/95 backdrop-blur-sm">
                                    <div class="relative">
                                        <div class="w-10 h-10 border-4 border-emerald-100 border-t-emerald-600 rounded-full animate-spin"></div>
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                        </div>
                                    </div>
                                    <span class="text-emerald-700 font-bold text-xs mt-3 animate-pulse">Đang kết nối...</span>
                                </div>
                            </div>
                        </div>

                        <div class="text-center space-y-2">
                            <h3 class="text-sm font-bold text-gray-800">Quét mã bằng Điện thoại</h3>
                            <p class="text-xs text-gray-500 max-w-[240px] mx-auto leading-relaxed">Sử dụng ứng dụng giả lập hoặc Camera để đăng nhập siêu tốc.</p>
                            <div class="mt-4 px-4 py-2 bg-gray-50 border border-gray-100 rounded-lg inline-block hover:bg-emerald-50 transition cursor-text group">
                                <span class="text-[9px] uppercase font-bold text-gray-400 block mb-0.5 group-hover:text-emerald-500 transition">Mã Token đối soát</span>
                                <code class="text-sm font-mono text-emerald-700 select-all">{{ qrToken }}</code>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative py-2">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-200"></div>
                    </div>
                    <div class="relative flex justify-center">
                        <span class="bg-white px-4 text-xs text-gray-400 font-medium">HOẶC</span>
                    </div>
                </div>

                <p class="text-center text-sm text-gray-600 font-medium pb-2">
                    Chưa có tài khoản? 
                    <Link :href="route('register')" class="text-emerald-600 font-bold hover:text-emerald-700 hover:underline transition ml-1">Đăng ký ngay</Link>
                </p>
            </div>
            
            <!-- Hình tròn trang trí nổi mờ mờ ở góc -->
            <div class="absolute -bottom-32 -left-32 w-64 h-64 bg-emerald-400/20 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -top-32 -right-32 w-64 h-64 bg-teal-400/10 rounded-full blur-3xl pointer-events-none"></div>
        </div>
    </div>
</template>