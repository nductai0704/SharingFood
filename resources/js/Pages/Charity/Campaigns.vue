<script setup>
import { ref, reactive } from 'vue';
const isCreateModalOpen = ref(false);

// 1. Định nghĩa mảng mock data chứa danh sách các chiến dịch hiện có
const campaigns = ref([
    {
        id: 1,
        title: 'Quyên góp gạo tẻ cho trẻ em vùng cao',
        target_item: 'Gạo',
        current_quantity: 350,
        target_quantity: 500,
        end_date: '2026-07-01',
        status: 'active', // 'active' (đang hoạt động), 'pending' (chờ duyệt)
        description: 'Chương trình gom gạo hỗ trợ các em nhỏ tại điểm trường vùng cao Hà Giang.'
    },
    {
        id: 2,
        title: 'Quyên góp sữa cho trẻ em',
        target_item: 'Sữa',
        current_quantity: 350,
        target_quantity: 500,
        end_date: '2026-07-01',
        status: 'active', // 'active' (đang hoạt động), 'pending' (chờ duyệt)
        description: 'Chương trình gom sữa hỗ trợ các em nhỏ tại điểm trường vùng cao Hà Giang.'
    },
]);

// 2. Khai báo biến reactive cho dữ liệu Form tạo mới chiến dịch
const form = reactive({
    title: '',
    target_item: '',
    target_quantity: '',
    location_details: '',
    end_date: '',
    description: '',
});

const getProgressPercent = (current, target) => {
    if (!target) return 0;
    // Hãy tính phần trăm đóng góp thực tế (làm tròn số và giới hạn tối đa 100%)
    return Math.min(Math.round((current / target) * 100), 100);
};

const handleSubmit = () => {
    // 1. Tạo object chiến dịch mới
    const newCampaign = {
        id: campaigns.value.length + 1,
        title: form.title,
        target_item: form.target_item,
        current_quantity: 0, // Bắt đầu từ 0
        target_quantity: form.target_quantity,
        end_date: form.end_date,
        status: 'pending',   // Chiến dịch mới tạo để trạng thái Chờ duyệt
        description: form.description,
    };

    // 2. Thêm vào mảng campaigns (Gợi ý: dùng campaigns.value.push)
    campaigns.value.push(newCampaign);

    // 3. Reset các ô nhập liệu trong form về rỗng để nhập tiếp
    form.title = '';
    form.target_item = '';
    form.target_quantity = '';
    form.location_details = '';
    form.end_date = '';
    form.description = '';

    // 4. Đóng Modal
    isCreateModalOpen.value = false;

    // 5. Hiển thị thông báo thành công
    alert("Khởi tạo chiến dịch ảo thành công! Bạn có thể xem kết quả trực quan ngay ở danh sách phía dưới.");

};


</script>

<template>
  <div class="min-h-screen bg-gray-50/50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-6">
          
        <!-- Tiêu đề trang & Nút hành động -->
      <div class="flex justify-between items-center border-b border-gray-100 pb-5">
        <div>
          <h1 class="text-2xl font-bold text-gray-950 tracking-tight">Chiến dịch Quyên góp của Tổ chức</h1>
          <p class="text-xs text-gray-500 mt-1">Khởi tạo và quản lý tiến độ quyên góp nhu yếu phẩm của Mái ấm.</p>
        </div>
        
        <div class="flex items-center gap-3">
          <a href="/charity/dashboard" class="text-xs font-semibold text-gray-500 hover:text-gray-700">
            ← Quay lại Bảng điều khiển
          </a>
          <button 
            @click="isCreateModalOpen = true"
            class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs py-2.5 px-4 rounded-xl transition duration-200 shadow-sm flex items-center gap-1.5"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Tạo chiến dịch mới
          </button>
        </div>
      </div>

      <!-- Danh sách chiến dịch hiện có (Dạng Grid 3 cột toàn chiều rộng) -->
      <div class="space-y-4">
        <h2 class="text-lg font-bold text-gray-900 tracking-tight">Danh sách chiến dịch của tôi</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <!-- Thẻ Card Chiến dịch -->
          <div 
            v-for="campaign in campaigns" 
            :key="campaign.id" 
            class="bg-white border border-gray-100 rounded-3xl p-6 shadow-sm space-y-4 hover:shadow-md transition duration-200 flex flex-col justify-between"
          >
            <!-- Tiêu đề chiến dịch & Badge Trạng thái -->
            <div class="space-y-3">
              <div class="flex justify-between items-start gap-4">
                <h3 class="font-bold text-gray-950 text-sm leading-snug line-clamp-2">{{ campaign.title }}</h3>
                
                <!-- Badge Trạng thái -->
                <span 
                  :class="campaign.status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-amber-50 text-amber-700 border-amber-100'"
                  class="text-[9px] uppercase tracking-wider font-bold px-2 py-0.5 rounded-full border flex-shrink-0"
                >
                  {{ campaign.status === 'active' ? 'Đang chạy' : 'Chờ duyệt' }}
                </span>
              </div>

              <!-- Chi tiết thông tin -->
              <div class="space-y-1 text-xs text-gray-500">
                <p>📦 Nhu yếu phẩm: <strong class="text-gray-800">{{ campaign.target_item }}</strong></p>
                <p>📅 Hạn cuối: <strong class="text-gray-800">{{ campaign.end_date }}</strong></p>
                <p class="line-clamp-2 mt-1">{{ campaign.description }}</p>
              </div>
            </div>

            <!-- Thanh tiến độ Quyên góp (Progress Bar) -->
            <div class="space-y-1.5 pt-4 border-t border-gray-50">
              <div class="flex justify-between text-xs font-semibold">
                <span class="text-gray-500">Tiến độ:</span>
                <span class="text-emerald-600 font-bold">
                  {{ campaign.current_quantity }} / {{ campaign.target_quantity }} ({{ getProgressPercent(campaign.current_quantity, campaign.target_quantity) }}%)
                </span>
              </div>
              
              <!-- Thanh tiến độ vật lý -->
              <div class="w-full bg-gray-100 h-2.5 rounded-full overflow-hidden">
                <div 
                  class="bg-emerald-500 h-full rounded-full transition-all duration-500"
                  :style="{ width: getProgressPercent(campaign.current_quantity, campaign.target_quantity) + '%' }"
                ></div>
              </div>
            </div>

          </div>
        </div>
      </div>
      <!-- MODAL TẠO CHIẾN DỊCH MỚI -->
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
            <h2 class="text-xl font-bold text-gray-950 tracking-tight">Khởi tạo chiến dịch mới</h2>
            <p class="text-xs text-gray-500 mt-1">Kêu gọi quyên góp nhu yếu phẩm quy mô lớn hỗ trợ những hoàn cảnh khó khăn.</p>
          </div>

          <form @submit.prevent="handleSubmit" class="space-y-5">
            <!-- 1. Tên chiến dịch -->
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-700">Tên chiến dịch kêu gọi</label>
              <input 
                type="text" 
                v-model="form.title" 
                required
                class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition" 
                placeholder="Ví dụ: Gom gạo tẻ cho các em nhỏ vùng cao..." 
              />
            </div>

            <!-- Grid 2 cột -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- 2. Loại nhu yếu phẩm -->
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-700">Nhu yếu phẩm kêu gọi</label>
                <select 
                  v-model="form.target_item" 
                  required
                  class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                >
                  <option value="" disabled>-- Chọn loại nhu yếu phẩm --</option>
                  <option value="Gạo">Gạo (kg)</option>
                  <option value="Mì gói">Mì gói (thùng)</option>
                  <option value="Sữa">Sữa (thùng/hộp)</option>
                  <option value="Nước suối">Nước suối (chai)</option>
                  <option value="Khác">Khác (Nhu yếu phẩm tổng hợp)</option>
                </select>
              </div>

              <!-- 3. Số lượng mục tiêu -->
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-700">Số lượng mục tiêu cần nhận</label>
                <input 
                  type="number" 
                  v-model.number="form.target_quantity" 
                  min="1"
                  required
                  class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition" 
                  placeholder="Ví dụ: 500, 1000..." 
                />
              </div>
            </div>

            <!-- Grid 2 cột thứ hai -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- 4. Ngày kết thúc -->
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-700">Ngày kết thúc quyên góp</label>
                <input 
                  type="date" 
                  v-model="form.end_date" 
                  required
                  class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition" 
                />
              </div>

              <!-- 5. Địa điểm tập kết hàng hóa -->
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-700">Địa chỉ tập kết hàng hóa</label>
                <input 
                  type="text"
                  v-model="form.location_details" 
                  required
                  class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition" 
                  placeholder="Ví dụ: 123 Nguyễn Trãi, Quận 5..."
                />
              </div>
            </div>

            <!-- 6. Hoàn cảnh khó khăn cần giúp đỡ -->
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-700">Mô tả hoàn cảnh / Mục đích</label>
              <textarea 
                v-model="form.description" 
                rows="3"
                required
                class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition" 
                placeholder="Chia sẻ hoàn cảnh cụ thể và mục đích của chiến dịch này..."
              ></textarea>
            </div>

            <!-- Hàng nút bấm -->
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
                Khởi tạo chiến dịch
              </button>
            </div>

          </form>
        </div>
      </div>
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
  margin-top: 16px;
  margin-bottom: 16px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 9999px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background-color: #94a3b8;
}
</style>

