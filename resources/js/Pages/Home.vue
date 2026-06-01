<script setup>
import { Head, Link } from '@inertiajs/vue3';

// Có thể truyền props từ Controller vào đây sau này
// const props = defineProps({
//     foodPosts: Array,
//     campaigns: Array
// });
</script>

<template>
  <Head title="Giao diện Người dùng - ShareFood" />

  <div class="min-h-screen bg-gray-50 text-gray-800 font-sans">
    <nav class="bg-white border-b border-gray-100 sticky top-0 z-50 shadow-sm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-md shadow-emerald-200">S</div>
            <span class="text-xl font-bold text-gray-950 tracking-tight">ShareFood<span class="text-emerald-600">.vn</span></span>
          </div>
          <div class="flex items-center space-x-6">
            <Link href="/" class="text-emerald-600 font-medium text-sm">Trang chủ</Link>
            <Link href="#" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Đăng tặng phẩm</Link>
            <Link href="#" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Chiến dịch từ thiện</Link>
            <div class="h-8 w-px bg-gray-200"></div>
            
            <!-- Đã đăng nhập -->
            <div v-if="$page.props.auth.user" class="flex items-center space-x-4">
              <div class="flex items-center space-x-2">
                <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 font-semibold text-sm">
                  {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                </div>
                <span class="text-sm font-medium text-gray-700">{{ $page.props.auth.user.name }}</span>
              </div>
              <Link :href="route('logout')" method="post" as="button" class="text-xs text-red-600 hover:text-red-700 font-semibold transition">
                Đăng xuất
              </Link>
            </div>

            <!-- Chưa đăng nhập -->
            <div v-else class="flex items-center space-x-4">
              <Link :href="route('login')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Đăng nhập</Link>
              <Link :href="route('register')" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-sm px-4 py-2 rounded-xl transition shadow-sm">Đăng ký</Link>
            </div>
          </div>
        </div>
      </div>
    </nav>
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-10">
      <div class="bg-gradient-to-br from-emerald-600 to-teal-700 rounded-3xl p-8 text-white shadow-xl shadow-emerald-100 relative overflow-hidden">
        <div class="relative z-10 max-w-2xl space-y-4">
          <span class="bg-emerald-500/30 text-emerald-100 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider">Định vị không gian GPS</span>
          <h1 class="text-3xl font-extrabold tracking-tight md:text-4xl">Tìm kiếm Thực phẩm Khả dụng Lân cận</h1>
          <p class="text-emerald-100/90 leading-relaxed text-sm md:text-base">Hệ thống đang áp dụng thuật toán <code class="bg-black/20 px-1.5 py-0.5 rounded font-mono text-xs">Haversine</code> để quét các nguồn thực phẩm dư thừa và các chiến dịch quyên góp trong bán kính của bạn.</p>
          <div class="flex flex-wrap gap-3 pt-2">
            <div class="bg-white/10 backdrop-blur-md border border-white/10 rounded-xl px-4 py-2.5 flex items-center space-x-2 text-sm">
              <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
              <span>Vị trí: Q. Bình Thạnh, TP.HCM</span>
            </div>
            <select class="bg-white text-gray-800 rounded-xl pl-4 pr-10 py-2.5 text-sm font-medium border-0 focus:ring-2 focus:ring-emerald-400 cursor-pointer">
              <option>Bán kính: 2 km</option>
              <option>Bán kính: 5 km</option>
              <option>Bán kính: 10 km</option>
            </select>
          </div>
        </div>
      </div>
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-6">
          <div class="flex justify-between items-center">
            <div class="space-y-1">
              <h2 class="text-xl font-bold text-gray-900 tracking-tight">Thực phẩm cộng đồng chia sẻ lẻ</h2>
              <p class="text-xs text-gray-500">Tin đăng tặng thực phẩm từ cá nhân/hộ kinh doanh nhỏ xung quanh bạn</p>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition duration-200 flex flex-col group">
              <div class="relative bg-gray-100 h-44 overflow-hidden flex items-center justify-center text-emerald-600 text-sm font-medium">
                [Hình ảnh bánh bao được AI phê duyệt]
              </div>
              <div class="p-4 flex-1 flex flex-col justify-between space-y-4">
                <div class="space-y-1">
                  <h3 class="font-bold text-gray-900 group-hover:text-emerald-600 transition">10 phần Bánh bao chay nóng</h3>
                  <p class="text-xs text-gray-500 line-clamp-2">Bánh bao chuẩn bị cho sự kiện sáng nay nhưng dư ra, còn nguyên vẹn trong tủ hấp ủ ấm.</p>
                </div>
                <div class="space-y-3">
                  <div class="bg-amber-50 text-amber-800 text-xs px-3 py-2 rounded-xl font-medium flex items-center justify-between">
                    <span>Hạn dùng còn lại:</span>
                    <span class="font-bold text-amber-700">4 giờ nữa (Hết hạn 21:00)</span>
                  </div>
                  <button class="w-full bg-emerald-600 text-white font-semibold text-sm py-2.5 px-4 rounded-xl">Gửi yêu cầu nhận (Khóa dòng)</button>
                </div>
              </div>
            </div>
            <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition duration-200 flex flex-col group">
              <div class="relative bg-gray-100 h-44 overflow-hidden flex items-center justify-center text-emerald-600 text-sm font-medium">
                [Hình ảnh bánh mì được AI phê duyệt]
              </div>
              <div class="p-4 flex-1 flex flex-col justify-between space-y-4">
                <div class="space-y-1">
                  <h3 class="font-bold text-gray-900 group-hover:text-emerald-600 transition">Bánh mì ngọt, Croissant trong ngày</h3>
                  <p class="text-xs text-gray-500 line-clamp-2">Các loại bánh nướng trong ngày tại cửa hàng không bán hết, cam kết chất lượng sạch sẽ thơm ngon.</p>
                </div>
                <div class="space-y-3">
                  <div class="bg-amber-50 text-amber-800 text-xs px-3 py-2 rounded-xl font-medium flex items-center justify-between">
                    <span>Hạn dùng còn lại:</span>
                    <span class="font-bold text-amber-700">12 giờ nữa</span>
                  </div>
                  <button class="w-full bg-emerald-600 text-white font-semibold text-sm py-2.5 px-4 rounded-xl">Gửi yêu cầu nhận (Khóa dòng)</button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="space-y-6">
          <h2 class="text-xl font-bold text-gray-900 tracking-tight">Chiến dịch quyên góp lớn</h2>
          <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
            <span class="bg-blue-50 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded border border-blue-100">Mái Ấm Q.Bình Thạnh</span>
            <h3 class="font-bold text-sm text-gray-900">Chiến dịch quyên góp 500kg Gạo tẻ</h3>
            <div class="space-y-1.5">
              <div class="flex justify-between text-xs font-semibold">
                <span class="text-gray-500">Đã cam kết:</span>
                <span class="text-emerald-600">350kg / 500kg (70%)</span>
              </div>
              <div class="w-full bg-gray-100 h-2.5 rounded-full overflow-hidden">
                <div class="bg-emerald-500 h-full" style="width: 70%"></div>
              </div>
            </div>
            <button class="w-full bg-gray-900 text-white font-medium text-xs py-2 rounded-xl">Đóng góp ngay (Sinh mã QR)</button>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>
