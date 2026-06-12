<script setup>
import { ref, onMounted, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const isMobileMenuOpen = ref(false);
const page = usePage();

// Tọa độ người dùng (Mặc định ban đầu lấy Chợ Bến Thành làm tâm)
const userLat = ref(10.7719);
const userLng = ref(106.6983);
const userAddress = ref('Đang xác định GPS...');
const selectedRadius = ref(5); // Bán kính quét mặc định: 5 km
const nearbyPosts = ref([]);

// Khai báo biến giữ các thực thể Leaflet Map
let map = null;
let userMarker = null;
let markersGroup = null;

// Gọi API lấy dữ liệu thức ăn lân cận thực tế
const fetchNearbyFood = async () => {
    try {
        const response = await fetch(`/api/nearby-food?latitude=${userLat.value}&longitude=${userLng.value}&radius=${selectedRadius.value}`);
        const result = await response.json();
        if (result.success) {
            nearbyPosts.value = result.data;
            updateMarkersOnMap();
        }
    } catch (error) {
        console.error('Lỗi khi gọi API thức ăn lân cận:', error);
    }
};

// Xin quyền định vị GPS từ trình duyệt hoặc lấy từ Profile nếu đã đăng ký
const getUserLocation = () => {
    const authUser = page.props.auth.user;
    
    // 1. Ưu tiên lấy tọa độ đã được lưu trong Profile thông tin tài khoản
    if (authUser && authUser.latitude && authUser.longitude) {
        userLat.value = parseFloat(authUser.latitude);
        userLng.value = parseFloat(authUser.longitude);
        userAddress.value = authUser.address || `Tọa độ lưu sẵn: ${userLat.value.toFixed(5)}, ${userLng.value.toFixed(5)}`;
        
        initMap();
        fetchNearbyFood();
        return; // Đã cấu hình xong, không cần xin quyền GPS trình duyệt nữa
    }

    // 2. Fallback: Nếu tài khoản chưa cập nhật tọa độ, dùng GPS của trình duyệt
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                userLat.value = position.coords.latitude;
                userLng.value = position.coords.longitude;
                userAddress.value = `Đã định vị GPS: ${userLat.value.toFixed(5)}, ${userLng.value.toFixed(5)}`;
                
                initMap();
                fetchNearbyFood();
            },
            (error) => {
                console.warn("Không thể lấy định vị thực tế (Dùng mặc định Chợ Bến Thành):", error.message);
                userAddress.value = "Chợ Bến Thành, Quận 1, TP.HCM (Mặc định)";
                initMap();
                fetchNearbyFood();
            },
            { enableHighAccuracy: true, timeout: 7000 }
        );
    } else {
        userAddress.value = "Trình duyệt không hỗ trợ GPS (Mặc định Bến Thành)";
        initMap();
        fetchNearbyFood();
    }
};

// Khởi tạo bản đồ Leaflet
const initMap = () => {
    if (typeof L === 'undefined') return;

    if (!map) {
        // Gắn bản đồ vào phần tử có id='map'
        map = L.map('map').setView([userLat.value, userLng.value], 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(map);

        markersGroup = L.layerGroup().addTo(map);
    } else {
        map.setView([userLat.value, userLng.value], 14);
    }

    // Đánh dấu vị trí người dùng bằng Marker đỏ
    const redIcon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    if (userMarker) {
        userMarker.setLatLng([userLat.value, userLng.value]);
    } else {
        userMarker = L.marker([userLat.value, userLng.value], { icon: redIcon })
            .addTo(map)
            .bindPopup('Vị trí của Mái ấm')
            .openPopup();
    }
};

// Đánh dấu các địa điểm có đồ ăn mẫu bằng Marker xanh lá
const updateMarkersOnMap = () => {
    if (typeof L === 'undefined' || !markersGroup) return;

    // Xóa hết marker cũ của lần quét trước
    markersGroup.clearLayers();

    const greenIcon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    nearbyPosts.value.forEach(post => {
        L.marker([post.latitude, post.longitude], { icon: greenIcon })
            .addTo(markersGroup)
            .bindPopup(`
                <div style="font-family: sans-serif; width: 170px;">
                    <b style="font-size: 13px; color: #1f2937;">${post.title}</b>
                    <p style="font-size: 11px; color: #059669; margin: 4px 0 0 0; font-weight: bold;">Cách bạn: ${parseFloat(post.distance).toFixed(2)} km</p>
                    <p style="font-size: 10px; color: #6b7280; margin: 4px 0 0 0;">Số lượng: ${post.remain_quantity} ${post.unit}</p>
                </div>
            `);
    });
};

// Hàm tính thời gian hết hạn thân thiện
const getExpiryLabel = (expiresAtStr) => {
    const expiresAt = new Date(expiresAtStr);
    const now = new Date();
    const diffMs = expiresAt - now;
    if (diffMs <= 0) return 'Đã hết hạn';
    
    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
    if (diffHours < 24) {
        if (diffHours === 0) {
            const diffMins = Math.floor(diffMs / (1000 * 60));
            return `Còn ${diffMins} phút`;
        }
        return `Còn ${diffHours} giờ`;
    }
    const diffDays = Math.floor(diffHours / 24);
    return `Còn ${diffDays} ngày`;
};

onMounted(() => {
    getUserLocation();
});

// Lắng nghe thay đổi của biến Bán kính để tự động quét lại dữ liệu
watch(selectedRadius, () => {
    fetchNearbyFood();
});

// Theo dõi khi thông tin User thay đổi (ví dụ: khi cập nhật Profile và quay lại Trang chủ qua SPA)
watch(() => page.props.auth.user, (newUser) => {
    if (newUser && newUser.latitude && newUser.longitude) {
        const newLat = parseFloat(newUser.latitude);
        const newLng = parseFloat(newUser.longitude);
        
        // Chỉ cập nhật nếu tọa độ thực sự thay đổi khác biệt
        if (Math.abs(userLat.value - newLat) > 0.00001 || Math.abs(userLng.value - newLng) > 0.00001) {
            userLat.value = newLat;
            userLng.value = newLng;
            userAddress.value = newUser.address || `Tọa độ mới: ${userLat.value.toFixed(5)}, ${userLng.value.toFixed(5)}`;
            
            initMap();
            fetchNearbyFood();
        }
    }
}, { deep: true });
</script>

<template>
  <Head title="Charity Dashboard - ShareFood" />

  <div class="min-h-screen bg-gray-50 text-gray-800 font-sans">
    
    <nav class="bg-white border-b border-gray-100 sticky top-0 z-50 shadow-sm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-md shadow-emerald-200">S</div>
            <span class="text-xl font-bold text-gray-950 tracking-tight">ShareFood<span class="text-emerald-600">.vn</span></span>
            <span class="hidden sm:inline-block bg-emerald-100 text-emerald-800 text-[10px] uppercase tracking-wider px-2.5 py-1 rounded-full font-bold border border-emerald-200">
              Tổ Chức Từ Thiện
            </span>
          </div>

          <div class="hidden md:flex items-center space-x-6">
            <Link :href="route('charity.dashboard')" class="text-emerald-600 font-medium text-sm">Trang chủ</Link>
            <Link :href="route('food-posts.index')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Thực phẩm</Link> 
            <Link :href="route('charity.campaigns')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Chiến dịch từ thiện</Link>
            <div class="h-8 w-px bg-gray-200"></div>
            
            <div v-if="$page.props.auth.user" class="flex items-center space-x-4">
              <Link :href="route('profile.edit')" class="flex items-center space-x-2 text-gray-700 hover:text-emerald-600 transition">
                <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 font-semibold text-sm">
                  {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                </div>
                <span class="text-sm font-medium">{{ $page.props.auth.user.name }}</span>
              </Link>
              <Link :href="route('logout')" method="post" as="button" class="text-sm text-red-600 hover:text-red-700 font-semibold transition">
                Đăng xuất
              </Link>
            </div>
          </div>

          <div class="flex items-center md:hidden">
            <button @click="isMobileMenuOpen = !isMobileMenuOpen" class="text-gray-500 hover:text-emerald-600 focus:outline-none p-2 rounded-lg bg-gray-50 border border-gray-100">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path v-if="!isMobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <div v-if="isMobileMenuOpen" class="md:hidden bg-white border-t border-gray-100 p-4 space-y-4 shadow-inner">
        <div class="flex flex-col space-y-3">
          <Link :href="route('charity.dashboard')" class="text-emerald-600 font-medium text-sm">Bảng điều khiển</Link>
          <Link :href="route('charity.campaigns')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Quản lý chiến dịch</Link>
        </div>
        <div class="h-px bg-gray-100"></div>
        <div v-if="$page.props.auth.user" class="flex flex-col space-y-3">
          <Link :href="route('profile.edit')" class="flex items-center space-x-2 text-gray-700 hover:text-emerald-600 transition">
            <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 font-semibold text-sm">
              {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
            </div>
            <span class="text-sm font-medium">{{ $page.props.auth.user.name }}</span>
          </Link>
          <Link :href="route('logout')" method="post" as="button" class="text-sm text-left text-red-600 hover:text-red-700 font-semibold transition">
            Đăng xuất
          </Link>
        </div>
      </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-10">
      
      <div class="bg-gradient-to-br from-emerald-600 to-teal-700 rounded-3xl p-8 text-white shadow-xl shadow-emerald-100 relative overflow-hidden">
        <div class="relative z-10 max-w-2xl space-y-4">
          <span class="bg-emerald-500/30 text-emerald-100 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider">Định vị không gian GPS</span>
          <h1 class="text-3xl font-extrabold tracking-tight md:text-4xl">Tìm kiếm Thực phẩm Lân cận</h1>
          <p class="text-emerald-100/90 leading-relaxed text-sm md:text-base">Hệ thống đang áp dụng thuật toán <code class="bg-black/20 px-1.5 py-0.5 rounded font-mono text-xs">Haversine</code> để quét các nguồn thực phẩm dư thừa trong bán kính của Mái ấm.</p>
          <div class="flex flex-wrap gap-3 pt-2">
            <!-- Hiển thị GPS động của Mái ấm -->
            <div class="bg-white/10 backdrop-blur-md border border-white/10 rounded-xl px-4 py-2.5 flex items-center space-x-2 text-sm max-w-xs md:max-w-md">
              <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse flex-shrink-0"></span>
              <span class="truncate">
                📍 Vị trí: {{ userAddress }}
              </span>
            </div>
            
            <!-- Chọn bán kính lọc -->
            <select 
              v-model="selectedRadius"
              class="bg-white text-gray-800 rounded-xl pl-4 pr-10 py-2.5 text-sm font-medium border-0 focus:ring-2 focus:ring-emerald-400 cursor-pointer"
            >
              <option :value="2">Bán kính: 2 km</option>
              <option :value="5">Bán kính: 5 km</option>
              <option :value="10">Bán kính: 10 km</option>
              <option :value="15">Bán kính: 15 km</option>
            </select>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- CỘT TRÁI (2/3): Danh sách thực phẩm quét được -->
        <div class="lg:col-span-2 space-y-6">
          <div class="flex justify-between items-center">
            <div class="space-y-1">
              <h2 class="text-xl font-bold text-gray-900 tracking-tight">Thực phẩm cộng đồng chia sẻ lẻ</h2>
              <p class="text-xs text-gray-500">Tin đăng tặng thực phẩm từ cá nhân/hộ kinh doanh nhỏ xung quanh Mái ấm trong vòng {{ selectedRadius }} km</p>
            </div>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div 
              v-for="post in nearbyPosts" 
              :key="post.id" 
              class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition duration-200 flex flex-col group"
            >
              <!-- Ảnh thực phẩm -->
              <div class="relative bg-gray-100 h-44 overflow-hidden flex items-center justify-center text-emerald-600 text-sm font-medium">
                <img 
                  :src="post.image_url ? '/storage/' + post.image_url : 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=600&q=80'" 
                  class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                  alt="Hình ảnh thực phẩm"
                />
              </div>
              
              <!-- Nội dung thông tin -->
              <div class="p-4 flex-1 flex flex-col justify-between space-y-4">
                <div class="space-y-1">
                  <h3 class="font-bold text-gray-900 group-hover:text-emerald-600 transition line-clamp-1">
                    {{ post.title }}
                  </h3>
                  <p class="text-xs text-gray-500 line-clamp-2 min-h-[2rem]">
                    {{ post.description }}
                  </p>
                </div>
                <div class="space-y-3">
                  <!-- Thông số: Số lượng & Hạn dùng -->
                  <div class="space-y-1.5">
                    <div class="bg-amber-50 text-amber-800 text-[11px] px-3 py-2 rounded-xl font-medium flex items-center justify-between">
                      <span>Số lượng còn lại:</span>
                      <span class="font-bold text-amber-700">{{ post.remain_quantity }} / {{ post.original_quantity }} {{ post.unit }}</span>
                    </div>
                    <!-- Hạn dùng thức ăn -->
                    <div class="bg-red-50 text-red-800 text-[11px] px-3 py-2 rounded-xl font-medium flex items-center justify-between">
                      <span>Hạn dùng còn lại:</span>
                      <span class="font-bold text-red-700">{{ getExpiryLabel(post.expires_at) }}</span>
                    </div>
                  </div>

                  <!-- Khoảng cách Haversine -->
                  <div class="flex items-center justify-between text-xs font-semibold text-emerald-800 px-1 pt-1">
                    <span>🚗 Vị trí cách bạn:</span>
                    <span class="font-bold text-emerald-600">{{ parseFloat(post.distance).toFixed(2) }} km</span>
                  </div>

                  <button class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm py-2.5 px-4 rounded-xl transition">
                    Gửi yêu cầu nhận
                  </button>
                </div>
              </div>
            </div>

            <!-- Trống dữ liệu -->
            <div v-if="nearbyPosts.length === 0" class="col-span-full bg-white border border-dashed border-gray-200 rounded-3xl p-10 text-center text-gray-400 text-sm">
              Không tìm thấy thực phẩm nào trong bán kính {{ selectedRadius }} km xung quanh vị trí của bạn.
            </div>
          </div>
        </div>

        <!-- CỘT PHẢI (1/3): Bản đồ tương tác & Quản lý chiến dịch -->
        <div class="space-y-6">
          <!-- Bản đồ định vị lân cận -->
          <div class="bg-white border border-gray-100 rounded-3xl p-5 shadow-sm space-y-3">
            <div class="flex justify-between items-center">
              <h3 class="font-bold text-gray-950 text-sm">Bản đồ thực phẩm lân cận</h3>
              <span class="text-[10px] text-gray-400">Định vị thời gian thực</span>
            </div>
            <!-- Box gắn Map (z-10 để không bị đè menu) -->
            <div id="map" class="h-64 rounded-2xl border border-gray-100 z-10"></div>
          </div>

          <!-- Chiến dịch của Tổ chức -->
          <div class="space-y-4">
            <div class="space-y-1">
              <h2 class="text-xl font-bold text-gray-900 tracking-tight">Chiến dịch của Tổ chức</h2>
              <p class="text-xs text-gray-500">Quản lý tiến độ quyên góp hiện vật</p>
            </div>

            <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
              <span class="bg-blue-50 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded border border-blue-100">Đang hoạt động</span>
              <h3 class="font-bold text-sm text-gray-900">Chiến dịch quyên góp 500kg Gạo tẻ</h3>
              <div class="space-y-1.5">
                <div class="flex justify-between text-xs font-semibold">
                  <span class="text-gray-500">Đã nhận thực tế:</span>
                  <span class="text-emerald-600">350kg / 500kg (70%)</span>
                </div>
                <div class="w-full bg-gray-100 h-2.5 rounded-full overflow-hidden">
                  <div class="bg-emerald-500 h-full" style="width: 70%"></div>
                </div>
              </div>
              <button class="w-full bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200 font-medium text-xs py-2 rounded-xl transition">Xem danh sách đóng góp</button>
            </div>

            <Link 
              :href="route('charity.campaigns')"
              class="block border-2 border-dashed border-gray-200 rounded-2xl p-6 text-center hover:bg-gray-50 transition cursor-pointer"
            >
                <div class="w-10 h-10 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-2 text-emerald-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                </div>
                <span class="text-sm font-semibold text-gray-500">Khởi tạo chiến dịch mới</span>
            </Link>
          </div>
        </div>

      </div>
    </main>
  </div>
</template>