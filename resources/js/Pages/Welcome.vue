<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

// Hình ảnh chất lượng cao (Pexels)
const heroImages = {
  mainFood: 'https://images.pexels.com/photos/1640777/pexels-photo-1640777.jpeg?auto=compress&cs=tinysrgb&w=800',
  community: 'https://images.pexels.com/photos/6995201/pexels-photo-6995201.jpeg?auto=compress&cs=tinysrgb&w=600',
  step1: 'https://images.pexels.com/photos/1640772/pexels-photo-1640772.jpeg?auto=compress&cs=tinysrgb&w=600',
  step2: 'https://images.pexels.com/photos/376464/pexels-photo-376464.jpeg?auto=compress&cs=tinysrgb&w=600',
  step3: 'https://images.pexels.com/photos/1099680/pexels-photo-1099680.jpeg?auto=compress&cs=tinysrgb&w=600'
};

// ...


</script>

<template>
    <Head title="ShareFood - Cứu thực phẩm, Lan tỏa yêu thương" />

    <div class="min-h-screen bg-transparent font-sans text-gray-900 selection:bg-emerald-100 selection:text-emerald-900 pointer-events-none relative z-10">
        
        <!-- HEADER / NAVBAR -->
        <nav class="pointer-events-auto border-b border-gray-100 py-4 px-6 md:px-12 flex justify-between items-center sticky top-0 bg-white/90 backdrop-blur-md z-50 transition-all duration-300">
            <div class="flex items-center space-x-3 group cursor-pointer">
                <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-md shadow-emerald-200 group-hover:scale-105 group-hover:rotate-3 transition-transform duration-300">S</div>
                <span class="text-xl font-bold text-gray-950 tracking-tight transition-colors duration-200">ShareFood<span class="text-emerald-600">.vn</span></span>
            </div>
            
            <div v-if="canLogin" class="space-x-4 flex items-center">
                <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="text-sm font-bold bg-gray-900 text-white px-6 py-2.5 rounded-full hover:bg-emerald-600 hover:scale-105 hover:shadow-lg transition-all duration-300">
                    Vào ứng dụng
                </Link>
                <template v-else>
                    <Link :href="route('login')" class="text-sm font-bold text-gray-600 hover:text-[#006F60] transition-colors">Đăng nhập</Link>
                    <Link v-if="canRegister" :href="route('register')" class="text-sm font-bold bg-emerald-600 text-white px-6 py-2.5 rounded-full hover:bg-[#0c4a34] hover:scale-105 hover:shadow-lg hover:shadow-emerald-200 transition-all duration-300 hidden sm:inline-block">
                        Tham gia ngay
                    </Link>
                </template>
            </div>
        </nav>

        <!-- 1. HERO SECTION -->
        <section class="pointer-events-auto relative z-10 bg-white pt-20 pb-32 overflow-hidden">
            <!-- Nền màu nhẹ thay cho bg-emerald-50/30 -->
            <div class="absolute inset-0 bg-emerald-50/30 z-0"></div>
            <!-- Nền không gian -->
            <div class="absolute inset-0 z-0 pointer-events-none">
                <div class="absolute top-10 left-10 w-[400px] h-[400px] bg-emerald-100 rounded-full mix-blend-multiply filter blur-3xl opacity-60 animate-pulse"></div>
                <div class="absolute bottom-10 right-10 w-[500px] h-[500px] bg-amber-50 rounded-full mix-blend-multiply filter blur-3xl opacity-60" style="animation: pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite;"></div>
            </div>

            <div class="max-w-7xl mx-auto px-6 lg:px-8 relative z-10">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                    
                    <!-- Text & CTA -->
                    <div class="space-y-8 relative z-20">
                        <span class="inline-flex items-center gap-2 bg-white text-emerald-700 text-xs font-bold px-4 py-2 rounded-full tracking-wide shadow-sm border border-emerald-100">
                            <span class="relative flex h-2.5 w-2.5">
                              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                              <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                            </span>
                            Nền tảng chia sẻ thực phẩm
                        </span>
                        
                        <h1 class="text-5xl md:text-[4rem] font-extrabold text-gray-900 tracking-tight leading-[1.1]">
                            Thực phẩm ngon không đáng bị bỏ phí. <br />
                            <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-teal-500">Hãy giải cứu!</span>
                        </h1>
                        
                        <p class="text-lg md:text-xl text-gray-600 max-w-lg leading-relaxed font-medium">
                            Tham gia cùng ShareFood kết nối hàng ngàn bữa ăn dư thừa hoàn toàn miễn phí tới các mái ấm và những người cần chúng.
                        </p>
                        
                        <div class="flex flex-col sm:flex-row gap-4 pt-4">
                            <Link :href="route('register')" class="w-full sm:w-auto flex items-center justify-center gap-2 text-base font-bold bg-emerald-600 text-white px-8 py-4 rounded-full transition-all duration-300 hover:scale-105 hover:bg-[#0c4a34] hover:shadow-xl hover:shadow-emerald-200/50">
                                <span>Bắt đầu giải cứu</span>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                            </Link>
                            <Link :href="route('dashboard')" class="w-full sm:w-auto text-base font-bold bg-white text-gray-900 px-8 py-4 rounded-full border-2 border-gray-100 transition-all duration-300 hover:scale-105 hover:border-emerald-200 hover:shadow-lg text-center">
                                Khám phá lân cận
                            </Link>
                        </div>
                    </div>

                    <!-- Hình ảnh Asymmetric TGTG Style -->
                    <div class="relative group h-[550px] w-full hidden md:block">
                        <!-- Ảnh chính -->
                        <img 
                            :src="heroImages.mainFood" 
                            alt="Thực phẩm ngon" 
                            class="absolute right-0 top-0 w-[85%] h-[480px] object-cover rounded-[2.5rem] shadow-2xl shadow-emerald-900/10 transform rotate-3 transition-all duration-500 group-hover:scale-[1.03] group-hover:rotate-1 z-10"
                        />
                        <!-- Ảnh phụ -->
                        <img 
                            :src="heroImages.community" 
                            alt="Cộng đồng chia sẻ" 
                            class="absolute left-0 bottom-0 w-[60%] h-[320px] object-cover rounded-3xl border-[6px] border-white shadow-2xl shadow-gray-900/15 transform -rotate-3 transition-all duration-700 group-hover:-translate-y-6 group-hover:rotate-0 z-20"
                        />
                    </div>
                </div>
            </div>
        </section>

        <!-- 2. TẠI SAO LẠI SỬ DỤNG SHAREFOOD.VN (CENTER-FOCUSED LAYOUT) -->
        <section class="pointer-events-auto py-32 bg-[#006F60] overflow-hidden relative z-10">
            <div class="max-w-7xl mx-auto px-6 lg:px-8 relative z-10">
                
                <!-- 1. HEADER (Tiêu đề căn giữa) -->
                <div class="text-center mb-24 max-w-2xl mx-auto">
                    <h3 class="text-emerald-400 font-extrabold uppercase tracking-[0.1em] text-2xl md:text-3xl mb-4">
                        Tại sao lại sử dụng
                    </h3>
                    <h2 class="text-5xl md:text-6xl font-black text-white uppercase tracking-tighter drop-shadow-sm">
                        ShareFood.vn
                    </h2>
                </div>

                <!-- 2. MAIN GRID 3 CỘT -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-12 lg:gap-8 items-center">
                    
                    <!-- CỘT TRÁI (Hiển thị thứ 2 trên mobile, thứ 1 trên desktop) -->
                    <div class="order-2 lg:order-1 space-y-16 text-center lg:text-right">
                        
                        <!-- Mục 1: Đồ ngon miễn phí -->
                        <div class="group flex flex-col lg:flex-row-reverse items-center lg:items-start gap-5">
                            <div class="w-16 h-16 shrink-0 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl shadow-sm group-hover:scale-110 group-hover:rotate-6 group-hover:bg-emerald-600 group-hover:text-white transition-all duration-300">
                                🍔
                            </div>
                            <div>
                                <h4 class="text-2xl font-black text-white mb-2 group-hover:text-[#006F60] transition-colors">Thưởng thức đồ ngon miễn phí</h4>
                                <p class="text-emerald-100/80 leading-relaxed font-medium">Khám phá và nhận ngay những phần ăn hấp dẫn, bánh ngọt hay thực phẩm tươi sạch hoàn toàn miễn phí từ cộng đồng và các cửa hàng xung quanh bạn.</p>
                            </div>
                        </div>

                        <!-- Mục 2: Bảo vệ môi trường -->
                        <div class="group flex flex-col lg:flex-row-reverse items-center lg:items-start gap-5">
                            <div class="w-16 h-16 shrink-0 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-2xl shadow-sm group-hover:scale-110 group-hover:rotate-6 group-hover:bg-blue-600 group-hover:text-white transition-all duration-300">
                                🌍
                            </div>
                            <div>
                                <h4 class="text-2xl font-black text-white mb-2 group-hover:text-blue-600 transition-colors">Bảo vệ môi trường xanh</h4>
                                <p class="text-emerald-100/80 leading-relaxed font-medium">Mỗi phần ăn được giải cứu là bạn đang trực tiếp giúp giảm thiểu lượng khí thải nhà kính (CO2) và ngăn chặn tình trạng lãng phí rác thải thực phẩm.</p>
                            </div>
                        </div>

                    </div>

                    <!-- CỘT GIỮA: TRỌNG TÂM THỊ GIÁC -->
                    <div class="order-1 lg:order-2 flex justify-center relative">
                        <!-- Hiệu ứng vầng sáng trang trí -->
                        <div class="absolute inset-0 bg-gradient-to-tr from-emerald-100 to-rose-100 rounded-full filter blur-[60px] scale-90 opacity-80 animate-pulse"></div>
                        
                        <!-- Hình ảnh trung tâm (Sử dụng ảnh hình tròn với viền dày để tạo điểm nhấn Center-focused) -->
                        <div class="relative z-10 w-full max-w-[340px] aspect-square rounded-full border-[12px] border-white shadow-2xl overflow-hidden group hover:-translate-y-4 transition-all duration-700 ease-out">
                            <img 
                                src="https://images.pexels.com/photos/1132047/pexels-photo-1132047.jpeg?auto=compress&cs=tinysrgb&w=800" 
                                alt="Túi thực phẩm tươi" 
                                class="w-full h-full object-cover transform transition-transform duration-700 group-hover:scale-110"
                            />
                            <!-- Lớp phủ bóng kính -->
                            <div class="absolute inset-0 rounded-full shadow-[inset_0_0_20px_rgba(0,0,0,0.1)] pointer-events-none"></div>
                        </div>
                    </div>

                    <!-- CỘT PHẢI (Luôn hiển thị thứ 3) -->
                    <div class="order-3 lg:order-3 space-y-16 text-center lg:text-left">
                        
                        <!-- Mục 3: Cứu trợ lân cận -->
                        <div class="group flex flex-col lg:flex-row items-center lg:items-start gap-5">
                            <div class="w-16 h-16 shrink-0 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center text-2xl shadow-sm group-hover:scale-110 group-hover:-rotate-6 group-hover:bg-rose-600 group-hover:text-white transition-all duration-300">
                                ❤️
                            </div>
                            <div>
                                <h4 class="text-2xl font-black text-white mb-2 group-hover:text-rose-600 transition-colors">Cứu trợ thực phẩm lân cận</h4>
                                <p class="text-emerald-100/80 leading-relaxed font-medium">Hệ thống định vị thông minh giúp kết nối các mái ấm, những người có hoàn cảnh khó khăn tới các điểm tặng đồ ăn gần nhất trong bán kính khu vực.</p>
                            </div>
                        </div>

                        <!-- Mục 4: Khám phá mới lạ -->
                        <div class="group flex flex-col lg:flex-row items-center lg:items-start gap-5">
                            <div class="w-16 h-16 shrink-0 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center text-2xl shadow-sm group-hover:scale-110 group-hover:-rotate-6 group-hover:bg-amber-600 group-hover:text-white transition-all duration-300">
                                ✨
                            </div>
                            <div>
                                <h4 class="text-2xl font-black text-white mb-2 group-hover:text-amber-600 transition-colors">Khám phá tiệm bánh, nhà hàng</h4>
                                <p class="text-emerald-100/80 leading-relaxed font-medium">Được thưởng thức những món ăn mới lạ, cao cấp từ các nhà hàng, quán cafe đối tác với mức giá siêu tiết kiệm vào mỗi cuối ngày giao dịch.</p>
                            </div>
                        </div>

                    </div>

                </div>
            </div>
        </section>

        <!-- 3. QUY TRÌNH HOẠT ĐỘNG (OVERLAPPING & ASYMMETRIC LAYOUT) -->
        <section class="pointer-events-auto py-32 bg-white relative overflow-hidden z-10">
            <div class="max-w-7xl mx-auto px-6 lg:px-8 relative z-10">
                
                <!-- Header (Tiêu đề căn giữa) -->
                <div class="text-center mb-24 max-w-2xl mx-auto space-y-4">
                    <h2 class="text-4xl md:text-5xl font-black tracking-tight text-emerald-900">
                        Làm thế nào để chia sẻ?
                    </h2>
                    <p class="text-emerald-700 text-lg md:text-xl font-medium leading-relaxed">
                        Chỉ với 3 bước đơn giản, bạn đã có thể biến đồ ăn dư thừa thành món quà ý nghĩa.
                    </p>
                </div>

                <!-- Main Grid 2 Cột -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 lg:gap-20 items-center">
                    
                    <!-- CỘT TRÁI: HÌNH ẢNH XẾP CHỒNG (OVERLAPPING) -->
                    <div class="relative w-full max-w-md mx-auto lg:max-w-none aspect-[4/5] lg:aspect-square flex items-center justify-center">
                        
                        <!-- Ảnh 1: Chính giữa (Nền phía dưới) -->
                        <div class="w-4/5 h-4/5 relative z-10 rounded-3xl overflow-hidden shadow-2xl shadow-emerald-950/30 group hover:z-30 transition-all duration-500 hover:scale-105 border-[10px] border-emerald-600">
                            <img :src="heroImages.step1" class="w-full h-full object-cover" alt="Thực phẩm dư" />
                        </div>
                        
                        <!-- Ảnh 2: Đè góc trên phải -->
                        <div class="absolute top-[-5%] right-[-5%] w-2/5 h-2/5 z-20 rounded-[2rem] overflow-hidden shadow-2xl shadow-emerald-950/30 border-8 border-white group hover:z-30 transition-all duration-500 hover:scale-110">
                            <img :src="heroImages.step2" class="w-full h-full object-cover" alt="Hệ thống kết nối" />
                        </div>
                        
                        <!-- Ảnh 3: Đè góc dưới trái -->
                        <div class="absolute bottom-[5%] left-[-5%] w-2/5 h-2/5 z-20 rounded-[2rem] overflow-hidden shadow-2xl shadow-emerald-950/30 border-8 border-white group hover:z-30 transition-all duration-500 hover:scale-110">
                            <img :src="heroImages.step3" class="w-full h-full object-cover" alt="Trao nhận lan tỏa" />
                        </div>

                    </div>

                    <!-- CỘT PHẢI: KHỐI CHỮ MASONRY (SO LE) -->
                    <div class="space-y-8 lg:pl-12 flex flex-col justify-center">
                        
                        <!-- Ô 1 -->
                        <div class="bg-white rounded-3xl p-8 shadow-xl shadow-emerald-800/20 w-full lg:w-[85%] hover:-translate-y-2 hover:shadow-2xl transition-all duration-300">
                            <h3 class="text-xl font-black text-emerald-700 mb-3">01. BẠN ĐĂNG THỰC PHẨM DƯ</h3>
                            <p class="text-gray-600 font-medium leading-relaxed">Chỉ vài thao tác đơn giản, chia sẻ các phần ăn ngon, sạch lên hệ thống.</p>
                        </div>
                        
                        <!-- Ô 2: So le đẩy sang phải -->
                        <div class="bg-white rounded-3xl p-8 shadow-xl shadow-emerald-800/20 w-full lg:w-[85%] ml-auto hover:-translate-y-2 hover:shadow-2xl transition-all duration-300">
                            <h3 class="text-xl font-black text-emerald-700 mb-3">02. HỆ THỐNG KẾT NỐI TỰ ĐỘNG</h3>
                            <p class="text-gray-600 font-medium leading-relaxed">AI kiểm duyệt chất lượng, cảnh báo người dùng xung quanh qua định vị.</p>
                        </div>
                        
                        <!-- Ô 3: So le lùi lại -->
                        <div class="bg-white rounded-3xl p-8 shadow-xl shadow-emerald-800/20 w-full lg:w-[85%] hover:-translate-y-2 hover:shadow-2xl transition-all duration-300">
                            <h3 class="text-xl font-black text-emerald-700 mb-3">03. TRAO NHẬN & LAN TỎA</h3>
                            <p class="text-gray-600 font-medium leading-relaxed">Người dùng đến trực tiếp địa điểm để nhận thực phẩm miễn phí.</p>
                        </div>

                    </div>
                </div>

            </div>
        </section>


        <!-- KHỐI MENU & APP STORE (CUỘN CÙNG NỘI DUNG CHÍNH) -->
        <!-- Đặt ngay dưới section 'Làm thế nào để chia sẻ?' để dính liền và cuộn cùng -->
        <div class="pointer-events-auto w-full relative z-30 bg-[#006F60] py-6 shadow-[0_20px_40px_rgba(0,0,0,0.3)]">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                <div class="flex flex-col items-center text-center space-y-3">
                    <!-- Nav Links -->
                    <ul class="flex flex-wrap justify-center gap-4 md:gap-8 text-sm md:text-base font-bold tracking-wider uppercase text-white">
                        <li><a href="#" class="hover:text-emerald-200 transition-colors">Nghề nghiệp</a></li>
                        <li><a href="#" class="hover:text-emerald-200 transition-colors">Nhãn</a></li>
                        <li><a href="#" class="hover:text-emerald-200 transition-colors">Ủng hộ</a></li>
                        <li><a href="#" class="hover:text-emerald-200 transition-colors">Cửa hàng của tôi</a></li>
                    </ul>
                    
                    <!-- Social Icons (6 icons, centered) -->
                    <div class="flex flex-wrap justify-center items-center gap-4">
                        <a href="#" class="w-10 h-10 rounded-full bg-[#006F60] flex items-center justify-center hover:bg-white hover:text-[#006F60] hover:-translate-y-1 hover:shadow-lg transition-all duration-300 text-white">
                            <!-- Facebook -->
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-[#006F60] flex items-center justify-center hover:bg-white hover:text-[#006F60] hover:-translate-y-1 hover:shadow-lg transition-all duration-300 text-white">
                            <!-- Instagram -->
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.88z"/></svg>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-[#006F60] flex items-center justify-center hover:bg-white hover:text-[#006F60] hover:-translate-y-1 hover:shadow-lg transition-all duration-300 text-white">
                            <!-- LinkedIn -->
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-[#006F60] flex items-center justify-center hover:bg-white hover:text-[#006F60] hover:-translate-y-1 hover:shadow-lg transition-all duration-300 text-white">
                            <!-- Twitter -->
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/></svg>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-[#006F60] flex items-center justify-center hover:bg-white hover:text-[#006F60] hover:-translate-y-1 hover:shadow-lg transition-all duration-300 text-white">
                            <!-- YouTube -->
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-[#006F60] flex items-center justify-center hover:bg-white hover:text-[#006F60] hover:-translate-y-1 hover:shadow-lg transition-all duration-300 text-white">
                            <!-- TikTok -->
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 2.22-1.15 4.38-2.9 5.84-1.74 1.45-4.14 2.21-6.4 1.95-2.25-.26-4.35-1.52-5.63-3.32-1.29-1.8-1.71-4.2-.99-6.32.72-2.12 2.45-3.86 4.54-4.57 2.08-.7 4.5-.47 6.35.63v4.11c-1.14-.62-2.6-.74-3.83-.34-1.22.39-2.22 1.4-2.65 2.59-.44 1.19-.24 2.6.49 3.63.74 1.02 1.97 1.59 3.2 1.55 1.22-.03 2.37-.62 3.09-1.55.72-.94 1.1-2.18 1.06-3.43-.05-4.22-.03-8.44-.03-12.65h-4.04z"/></svg>
                        </a>
                    </div>
                    
                    <!-- Badge tải App (dưới 6 icon) -->
                    <div class="flex items-center justify-center gap-4">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/3/3c/Download_on_the_App_Store_Badge.svg" alt="App Store" class="h-[2.5rem] hover:scale-105 transition-transform duration-300 cursor-pointer" />
                        <img src="https://upload.wikimedia.org/wikipedia/commons/7/78/Google_Play_Store_badge_EN.svg" alt="Google Play" class="h-[2.5rem] hover:scale-105 transition-transform duration-300 cursor-pointer" />
                    </div>
                </div>
            </div>
        </div>
        
        <!-- GHOST FOOTER (Tạo không gian cuộn chuẩn xác) -->
        <footer class="relative z-0 opacity-0 pointer-events-none bg-[#005F52] select-none">
            <div class="w-full flex flex-col justify-end pt-4">
                <h1 class="font-black text-[14vw] md:text-[12vw] text-center text-white leading-none tracking-tighter w-full mb-4">
                    SHAREFOOD
                </h1>
                <div class="w-full px-6 lg:px-8 pb-4 pt-4 border-t border-emerald-900/50">
                    <div class="flex flex-col items-center gap-6 text-xs md:text-sm text-white font-medium pt-2">
                        <ul class="flex flex-wrap justify-center gap-x-8 gap-y-3 w-full">
                            <li><a href="#">Hợp pháp</a></li>
                            <li><a href="#">Chính sách bảo mật</a></li>
                            <li><a href="#">Chính sách cookie</a></li>
                            <li><a href="#">Điều khoản và điều kiện</a></li>
                            <li><a href="#">Liên hệ với chúng tôi</a></li>
                            <li><a href="#">Nguồn gốc chất thải thực phẩm</a></li>
                            <li><a href="#">Trạng thái</a></li>
                        </ul>
                        <div class="text-center opacity-80 pb-2">Bản quyền &copy; 2026 ShareFood.vn.</div>
                    </div>
                </div>
            </div>
            <div class="w-full h-[5vh] min-h-[50px]"></div>
        </footer>


        <!-- FOOTER THẬT (FIXED - NẰM YÊN Ở ĐÁY, BỊ LỘ RA KHI CUỘN) -->
        <footer class="fixed bottom-0 left-0 w-full z-0 bg-[#005F52]">

            <!-- 2. DÒNG CHỮ KHỔNG LỒ & BẢN QUYỀN (CHUNG NỀN) -->
            <div class="w-full flex flex-col justify-end pt-4">
                <!-- Chữ SHAREFOOD -->
                <h1 class="font-black text-[14vw] md:text-[12vw] text-center text-white leading-none tracking-tighter select-none w-full pointer-events-none mb-4">
                    SHAREFOOD
                </h1>
                
                <!-- Thanh Bản Quyền (Nằm chung nền, ngay dưới chữ) -->
                <div class="w-full px-6 lg:px-8 pb-4 pointer-events-auto">
                    <div class="max-w-7xl mx-auto flex flex-col items-center gap-6 text-xs md:text-sm text-white font-medium border-t border-emerald-900/50 pt-6">
                        <ul class="flex flex-wrap justify-center gap-x-8 gap-y-3 w-full">
                            <li><a href="#" class="hover:text-emerald-400 hover:underline transition-all underline-offset-4">Hợp pháp</a></li>
                            <li><a href="#" class="hover:text-emerald-400 hover:underline transition-all underline-offset-4">Chính sách bảo mật</a></li>
                            <li><a href="#" class="hover:text-emerald-400 hover:underline transition-all underline-offset-4">Chính sách cookie</a></li>
                            <li><a href="#" class="hover:text-emerald-400 hover:underline transition-all underline-offset-4">Điều khoản và điều kiện</a></li>
                            <li><a href="#" class="hover:text-emerald-400 hover:underline transition-all underline-offset-4">Liên hệ với chúng tôi</a></li>
                            <li><a href="#" class="hover:text-emerald-400 hover:underline transition-all underline-offset-4">Nguồn gốc chất thải thực phẩm</a></li>
                            <li><a href="#" class="hover:text-emerald-400 hover:underline transition-all underline-offset-4">Trạng thái</a></li>
                        </ul>
                        <div class="text-center opacity-80 pb-2">Bản quyền &copy; 2026 ShareFood.vn.</div>
                    </div>
                </div>
            </div>
            
            <!-- 3. KHOẢNG TRỐNG CUỘN -->
            <div class="w-full h-[5vh] min-h-[50px]"></div>

        </footer>

    </div>
</template>
