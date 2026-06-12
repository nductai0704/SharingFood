<script setup>
import { ref, reactive } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

// 1. Trạng thái kiểm soát đóng/mở Modal tạo mới bài đăng
const isCreateModalOpen = ref(false);

// 2. Mảng Mock Data chứa các bài đăng tặng thực phẩm hiện có của tài khoản này
const foodPosts = ref([
    {
        id: 1,
        title: '10 phần bánh bao chay nóng hổi',
        category_id: 2, // Bánh ngọt/chay
        original_quantity: 10,
        remain_quantity: 10,
        unit: 'phần',
        expires_at: '2026-06-12T18:00',
        status: 'active', // active (Đang hiển thị), expired (Hết hạn)
        ai_status: 'verified', // verified (Đã duyệt), pending (Đang duyệt), rejected (Từ chối)
        description: 'Bánh bao chay mới hấp sáng nay phục vụ sự kiện nhưng còn dư.'
    },
    {
        id: 2,
        title: '10 phần cơm chay nóng hổi',
        category_id: 3, // Bánh ngọt/chay
        original_quantity: 10,
        remain_quantity: 10,
        unit: 'phần',
        expires_at: '2026-06-12T18:00',
        status: 'active', // active (Đang hiển thị), expired (Hết hạn)
        ai_status: 'verified', // verified (Đã duyệt), pending (Đang duyệt), rejected (Từ chối)
        description: 'Bánh bao chay mới hấp sáng nay phục vụ sự kiện nhưng còn dư.'
    },
    // Bạn hãy điền thêm ít nhất 1 bài đăng thực phẩm giả lập nữa vào đây để danh sách thêm sinh động...
]);

// 3. Mảng Mock danh mục (sử dụng lại từ phần trước)
const categories = ref([
    { id: 1, name: 'Cơm suất' },
    { id: 2, name: 'Bánh ngọt' },
    { id: 3, name: 'Trái cây' },
    { id: 4, name: 'Thực phẩm đóng hộp' },
]);

// 4. Khai báo biến reactive cho dữ liệu Form tạo mới bài đăng
const form = reactive({
    title: '',
    category_id: '',
    description: '',
    original_quantity: '',
    remain_quantity: '',
    unit: '',
    image_url: '',
    expires_at: '',
});

// Hàm hỗ trợ tìm tên danh mục từ ID
const getCategoryName = (catId) => {
    const category = categories.value.find(c => c.id === Number(catId));
    return category ? category.name : 'Chưa phân loại';
};

const handleSubmit = () => {
    // 1. Tạo bài đăng mới từ dữ liệu form
    const newPost = {
        id: foodPosts.value.length + 1,
        title: form.title,
        category_id: form.category_id,
        original_quantity: form.original_quantity,
        remain_quantity: form.original_quantity, // Khởi đầu lượng còn lại = ban đầu
        unit: form.unit,
        expires_at: form.expires_at,
        status: 'active',
        ai_status: 'pending', // Mặc định chờ AI quét duyệt
        description: form.description,
        image_url: form.image_url
    };

    // 2. Thêm vào danh sách foodPosts
    foodPosts.value.push(newPost);

    // 3. Reset dữ liệu form về trống
    form.title = '';
    form.category_id = '';
    form.description = '';
    form.original_quantity = '';
    form.remain_quantity = '';
    form.unit = '';
    form.image_url = '';
    form.expires_at = '';

    // 4. Đóng Modal
    isCreateModalOpen.value = false;

    // 5. Thông báo cho người dùng
    alert("Đăng tặng thực phẩm thành công! Bài viết mới của bạn đang được AI kiểm duyệt và đã xuất hiện trong danh sách quản lý.");
};

</script>

<template>
  <div class="min-h-screen bg-gray-50/50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-6">
      
      <!-- Tiêu đề trang & Nút hành động -->
      <div class="flex justify-between items-center border-b border-gray-100 pb-5">
        <div>
          <h1 class="text-2xl font-bold text-gray-950 tracking-tight">Quản lý thực phẩm quyên tặng</h1>
          <p class="text-xs text-gray-500 mt-1">Danh sách thực phẩm lẻ cá nhân/doanh nghiệp bạn đang chia sẻ.</p>
        </div>
        
        <div class="flex items-center gap-3">
        <Link 
            :href="$page.props.auth.user?.role === 'charity' ? route('charity.dashboard') : '/'" 
            class="text-xs font-semibold text-gray-500 hover:text-gray-700"
          >
            ← Quay lại Trang chủ
          </Link>

          <button 
            @click="isCreateModalOpen = true"
            class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs py-2.5 px-4 rounded-xl transition duration-200 shadow-sm flex items-center gap-1.5"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Đăng tặng thực phẩm mới
          </button>
        </div>
      </div>

            <!-- Danh sách bài đăng dạng lưới thẻ (Grid Cards) -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div 
          v-for="post in foodPosts" 
          :key="post.id" 
          class="bg-white border border-gray-100 rounded-3xl overflow-hidden shadow-sm hover:shadow-md transition duration-200 flex flex-col group"
        >
          <!-- 1. Hình ảnh thực tế & Ghim danh mục -->
          <div class="relative bg-gray-50 h-48 overflow-hidden flex items-center justify-center text-gray-400 text-sm font-medium border-b border-gray-100">
            <!-- Nếu có tên ảnh mock từ input file, ta dùng ảnh minh họa từ Unsplash tương ứng, nếu không có thì hiện ảnh mặc định -->
            <img 
              :src="post.image_url ? 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=600&q=80' : 'https://images.unsplash.com/photo-1488459718432-36c55e7946d5?auto=format&fit=crop&w=600&q=80'" 
              class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
              alt="Hình ảnh thực phẩm"
            />
            
            <!-- Danh mục ghim góc trên trái -->
            <span class="absolute top-3 left-3 bg-white/90 backdrop-blur-md text-emerald-800 text-[10px] font-bold px-2.5 py-1.5 rounded-xl border border-emerald-100 shadow-sm">
              📂 {{ getCategoryName(post.category_id) }}
            </span>
          </div>

          <!-- 2. Nội dung chi tiết -->
          <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
            <div class="space-y-2">
              <h3 class="font-bold text-gray-950 text-base leading-snug group-hover:text-emerald-600 transition duration-200 line-clamp-1">
                {{ post.title }}
              </h3>
              <p class="text-xs text-gray-500 line-clamp-2 h-8 leading-relaxed">
                {{ post.description }}
              </p>

              <!-- Grid thông tin Số lượng & Hạn dùng -->
              <div class="pt-2 grid grid-cols-2 gap-3 border-t border-gray-50">
                <!-- Cột Số lượng -->
                <div class="space-y-0.5">
                  <span class="text-[9px] text-gray-400 font-bold uppercase tracking-wider">Số lượng còn lại</span>
                  <p class="text-xs font-extrabold text-gray-900">
                    {{ post.remain_quantity }} / {{ post.original_quantity }} <span class="text-gray-500 font-normal text-[10px]">{{ post.unit }}</span>
                  </p>
                </div>
                <!-- Cột Hạn dùng -->
                <div class="space-y-0.5">
                  <span class="text-[9px] text-gray-400 font-bold uppercase tracking-wider">Hạn sử dụng</span>
                  <p class="text-xs font-bold text-amber-600">
                    {{ new Date(post.expires_at).toLocaleDateString('vi-VN') }}
                  </p>
                </div>
              </div>
            </div>

            <!-- 3. Huy hiệu trạng thái và Nút đóng/mở ẩn tin -->
            <div class="space-y-3 pt-2">
              <div class="flex justify-between items-center gap-2 border-t border-gray-50 pt-3">
                <!-- Trạng thái hoạt động -->
                <div class="flex items-center gap-1">
                  <span class="text-[10px] text-gray-400">Tin đăng:</span>
                  <span 
                    :class="post.status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-red-50 text-red-700 border-red-100'"
                    class="text-[10px] font-bold px-2 py-0.5 rounded-md border"
                  >
                    {{ post.status === 'active' ? 'Đang chạy' : 'Đã dừng' }}
                  </span>
                </div>
                
                <!-- Trạng thái AI kiểm duyệt -->
                <div class="flex items-center gap-1">
                  <span class="text-[10px] text-gray-400">AI duyệt:</span>
                  <span 
                    :class="{
                      'bg-emerald-50 text-emerald-700 border-emerald-100': post.ai_status === 'verified',
                      'bg-amber-50 text-amber-700 border-amber-100': post.ai_status === 'pending',
                      'bg-red-50 text-red-700 border-red-100': post.ai_status === 'rejected'
                    }"
                    class="text-[10px] font-bold px-2 py-0.5 rounded-md border"
                  >
                    {{ post.ai_status === 'verified' ? 'Đã duyệt' : (post.ai_status === 'pending' ? 'Đang duyệt' : 'Từ chối') }}
                  </span>
                </div>
              </div>

              <!-- Nút chuyển đổi trạng thái hiển thị của bài đăng (Giả lập tương tác) -->
              <button 
                @click="post.status = post.status === 'active' ? 'expired' : 'active'"
                :class="post.status === 'active' ? 'bg-gray-50 hover:bg-red-50 text-gray-600 hover:text-red-600 border-gray-200 hover:border-red-200' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border-emerald-200'"
                class="w-full font-bold text-xs py-2.5 px-4 rounded-xl transition border text-center"
              >
                {{ post.status === 'active' ? '🛑 Tạm dừng hiển thị' : '✅ Kích hoạt lại' }}
              </button>
            </div>
          </div>
        </div>

        <!-- Trống dữ liệu -->
        <div v-if="foodPosts.length === 0" class="col-span-full bg-white border border-dashed border-gray-300 rounded-3xl p-12 text-center text-gray-400 text-sm">
          Bạn chưa đăng tặng thực phẩm nào. Hãy bấm "Đăng tặng thực phẩm mới" để bắt đầu!
        </div>
      </div>


    </div>
  </div>

        <!-- MODAL ĐĂNG TẶNG THỰC PHẨM MỚI -->
      <div 
        v-if="isCreateModalOpen" 
        class="fixed inset-0 bg-gray-950/40 backdrop-blur-sm z-50 flex items-center justify-center p-4"
      >
               <!-- Khung Form nổi -->
        <div class="bg-white rounded-3xl border border-gray-100 shadow-xl p-6 sm:p-8 space-y-6 max-w-2xl w-full relative max-h-[90vh] overflow-y-auto custom-scrollbar">
          
          <!-- Nút Đóng góc trên bên phải -->
          <button 
            @click="isCreateModalOpen = false" 
            class="absolute top-5 right-5 text-gray-400 hover:text-gray-600 transition"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <div>
            <h2 class="text-xl font-bold text-gray-950 tracking-tight">Đăng tặng thực phẩm lẻ</h2>
            <p class="text-xs text-gray-500 mt-1">Chia sẻ thực phẩm dư thừa của bạn để giúp đỡ những người xung quanh.</p>
          </div>

          <form @submit.prevent="handleSubmit" class="space-y-6">

            
            <!-- 1. Tiêu đề bài đăng (Tên món ăn) -->
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-700">Tên món ăn / Thực phẩm</label>
              <input 
                type="text" 
                v-model="form.title"
                required
                class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                placeholder="Ví dụ: Bánh bao chay, cơm gà xối mỡ..."
            />
            </div>

            <!-- Grid 2 cột -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- 2. Danh mục thực phẩm -->
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-700">Danh mục</label>
                <select 
            v-model="form.category_id"
            required
            class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
            >
            <option value="" disabled>-- Chọn danh mục --</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                {{ cat.name }}
            </option>
            </select>
              </div>

              <!-- 3. Hạn sử dụng -->
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-700">Hạn sử dụng</label>
                <input 
                    type="datetime-local" 
                    v-model="form.expires_at"
                    required
                    class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
              />
              </div>

              <!-- 4. Số lượng ban đầu -->
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-700">Số lượng</label>
                <input 
                type="number" 
                v-model.number="form.original_quantity"
                min="1"
                required
                class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                placeholder="Ví dụ: 5, 10..."
                />
              </div>

              <!-- 5. Đơn vị tính -->
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-700">Đơn vị tính</label>
                <input 
                type="text" 
                v-model="form.unit"
                required
                class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                placeholder="Ví dụ: suất, hộp, cái, kg..."
                />
              </div>
            </div>

            <!-- 6. Mô tả chi tiết -->
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-700">Mô tả chi tiết</label>
              <textarea 
            v-model="form.description"
            rows="3"
            required
            class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
            placeholder="Mô tả tình trạng thực phẩm (Ví dụ: Mới nấu trưa nay, đóng gói sạch sẽ...)"
            ></textarea>
            </div>

            <!-- 7. Hình ảnh minh họa -->
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-700">Hình ảnh thực tế</label>
              <label class="text-xs font-semibold text-gray-700">Hình ảnh thực tế</label>
            <input 
              type="file" 
              accept="image/*"
              @change="(e) => form.image_url = e.target.files[0]?.name || ''"
              class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 transition cursor-pointer"
              />
            </div>

            <!-- Nút gửi -->
            <div class="pt-4 flex gap-3">
              <button 
                type="button" 
                @click="isCreateModalOpen = false" 
                class="w-1/3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold text-sm py-3 px-4 rounded-xl transition duration-200"
              >
                Hủy bỏ
              </button>
              <button 
                type="submit" 
                class="w-2/3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm py-3 px-4 rounded-xl transition duration-200 shadow-sm shadow-emerald-100"
              >
                Đăng tặng thực phẩm
              </button>
            </div>

          </form>
        </div>
      </div>
</template>
<style scoped>
/* Tùy chỉnh thanh cuộn của modal cho mượt và bo tròn */
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
  margin-top: 16px;    /* Đẩy đầu thanh cuộn xuống dưới, tránh chạm góc bo tròn */
  margin-bottom: 16px; /* Đẩy chân thanh cuộn lên trên */
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1; /* Màu xám nhạt tinh tế (Tailwind Slate-300) */
  border-radius: 9999px;     /* Bo tròn hoàn toàn hai đầu */
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background-color: #94a3b8; /* Tối hơn chút khi hover (Slate-400) */
}
</style>

