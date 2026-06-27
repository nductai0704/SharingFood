<script setup>
import { Link, Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    dbCampaigns: Array
});

const getProgressPercent = (current, target) => {
    if (!target) return 0;
    return Math.min(Math.round((current / target) * 100), 100);
};
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Chiến dịch quyên góp - Tổ chức" />
    <div class="min-h-screen bg-gray-50/50 py-8 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto space-y-6">
            
          <!-- Tiêu đề trang & Nút hành động -->
        <div class="flex justify-between items-center border-b border-gray-100 pb-5">
          <div>
            <h1 class="text-2xl font-bold text-gray-950 tracking-tight">Chiến dịch Quyên góp của Tổ chức</h1>
            <p class="text-xs text-gray-500 mt-1">Khởi tạo và quản lý tiến độ quyên góp nhu yếu phẩm của Mái ấm.</p>
          </div>
          
          <div class="flex items-center gap-3">
            <Link 
              :href="route('charity.campaigns.create')"
              class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs py-2.5 px-4 rounded-xl transition duration-200 shadow-sm flex items-center gap-1.5"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
              Tạo chiến dịch mới
            </Link>
          </div>
        </div>

        <!-- Danh sách chiến dịch hiện có (Dạng Grid 3 cột toàn chiều rộng) -->
        <div class="space-y-4">
          <h2 class="text-lg font-bold text-gray-900 tracking-tight">Danh sách chiến dịch của tôi</h2>
          
          <div v-if="!props.dbCampaigns || props.dbCampaigns.length === 0" class="text-center py-10 bg-white rounded-3xl border border-gray-100 shadow-sm">
             <p class="text-gray-500 text-sm">Chưa có chiến dịch nào được tạo.</p>
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Thẻ Card Chiến dịch -->
            <div 
              v-for="campaign in props.dbCampaigns" 
              :key="campaign.id" 
              class="bg-white border border-gray-100 rounded-3xl p-6 shadow-sm space-y-4 hover:shadow-md transition duration-200 flex flex-col justify-between"
            >
              <!-- Tiêu đề chiến dịch & Badge Trạng thái -->
              <div class="space-y-3">
                <div class="flex justify-between items-start gap-4">
                  <h3 class="font-bold text-gray-950 text-sm leading-snug line-clamp-2">{{ campaign.title }}</h3>
                  
                  <!-- Badge Trạng thái -->
                  <span 
                    :class="campaign.status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 
                            (campaign.status === 'pending' ? 'bg-amber-50 text-amber-700 border-amber-100' : 'bg-gray-100 text-gray-600 border-gray-200')"
                    class="text-[9px] uppercase tracking-wider font-bold px-2 py-0.5 rounded-full border flex-shrink-0"
                  >
                    {{ campaign.status === 'active' ? 'Đang chạy' : (campaign.status === 'pending' ? 'Chờ duyệt' : campaign.status) }}
                  </span>
                </div>

                <!-- Chi tiết thông tin -->
                <div class="space-y-1 text-xs text-gray-500">
                  <p>📅 Hạn cuối: <strong class="text-gray-800">{{ new Date(campaign.end_date).toLocaleDateString('vi-VN') }}</strong></p>
                  <p>📍 Địa điểm: <strong class="text-gray-800 line-clamp-1">{{ campaign.location_details }}</strong></p>
                  <p v-if="campaign.description" class="line-clamp-2 mt-1">{{ campaign.description }}</p>
                </div>
              </div>

              <!-- Danh sách Nhu yếu phẩm (Items) -->
              <div class="pt-4 border-t border-gray-50 space-y-3">
                <p class="text-xs font-bold text-gray-700">📦 Nhu yếu phẩm cần gọi:</p>
                <div v-if="campaign.items && campaign.items.length > 0" class="space-y-2.5">
                  <div v-for="item in campaign.items" :key="item.id" class="space-y-1">
                    <div class="flex justify-between text-[11px] font-semibold">
                      <span class="text-gray-700 truncate w-32" :title="item.item_name">{{ item.item_name }}</span>
                      <span class="text-emerald-600 font-bold">
                        {{ item.current_quantity }} / {{ item.target_quantity }} 
                        ({{ getProgressPercent(item.current_quantity, item.target_quantity) }}%)
                      </span>
                    </div>
                    <!-- Thanh tiến độ vật lý của từng item -->
                    <div class="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden">
                      <div 
                        class="bg-emerald-500 h-full rounded-full transition-all duration-500"
                        :style="{ width: getProgressPercent(item.current_quantity, item.target_quantity) + '%' }"
                      ></div>
                    </div>
                  </div>
                </div>
                <div v-else class="text-xs text-gray-400 italic">Không có nhu yếu phẩm nào.</div>
              </div>

            </div>
          </div>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
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

