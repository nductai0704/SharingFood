<script setup>
import { ref, computed } from 'vue';
import { Link, Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    dbCampaigns: Array,
    dbPendingDonations: Array
});

const searchDonationCode = ref('');
const activeCampaignTab = ref(props.dbPendingDonations && props.dbPendingDonations.length > 0 ? 'donations' : 'active'); // 'donations', 'active', or 'closed'

const activeCampaigns = computed(() => {
    if (!props.dbCampaigns) return [];
    return props.dbCampaigns.filter(c => !['closed', 'completed'].includes(c.status));
});

const closedCampaigns = computed(() => {
    if (!props.dbCampaigns) return [];
    return props.dbCampaigns.filter(c => ['closed', 'completed'].includes(c.status));
});

const currentCampaigns = computed(() => {
    return activeCampaignTab.value === 'active' ? activeCampaigns.value : closedCampaigns.value;
});

const groupedPendingDonations = computed(() => {
    if (!props.dbPendingDonations) return [];
    
    let source = props.dbPendingDonations;
    if (searchDonationCode.value.trim()) {
        const query = searchDonationCode.value.toLowerCase().trim();
        source = source.filter(d => 
            (d.donation_code && d.donation_code.toLowerCase().includes(query)) ||
            (d.user && d.user.phone && d.user.phone.includes(query))
        );
    }
    
    const groups = {};
    source.forEach(d => {
        if (!groups[d.donation_code]) {
            groups[d.donation_code] = {
                donation_code: d.donation_code,
                user: d.user,
                campaign: d.campaign,
                shipping_method: d.shipping_method,
                shipper_name: d.shipper_name,
                shipper_license_plate: d.shipper_license_plate,
                items: []
            };
        }
        groups[d.donation_code].items.push(d);
    });
    
    return Object.values(groups).sort((a, b) => {
        // Sort by first item's created_at descending
        return new Date(b.items[0].created_at) - new Date(a.items[0].created_at);
    });
});

const verifyDonation = (donationCode) => {
    if (confirm('Xác nhận đã nhận ĐỦ số lượng các món trong đơn quyên góp này?')) {
        router.post(route('charity.donations.verify', donationCode), {}, {
            preserveScroll: true
        });
    }
};

const getProgressPercent = (current, target) => {
    if (!target) return 0;
    return Math.min(Math.round((current / target) * 100), 100);
};

const showRejectModal = ref(false);
const selectedRejectDonationCode = ref(null);
const isProcessing = ref(false);
const rejectForm = ref({
    reason: 'Spam/Phá bĩnh'
});

const rejectReasons = [
    'Spam/Phá bĩnh',
    'Không giao hàng',
    'Liên lạc không được',
    'Lý do khác'
];

const openRejectModal = (donationCode) => {
    selectedRejectDonationCode.value = donationCode;
    rejectForm.value.reason = 'Spam/Phá bĩnh';
    showRejectModal.value = true;
};

const submitRejectDonation = () => {
    if (selectedRejectDonationCode.value) {
        isProcessing.value = true;
        router.post(route('charity.donations.reject', selectedRejectDonationCode.value), {
            reason: rejectForm.value.reason
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showRejectModal.value = false;
                selectedRejectDonationCode.value = null;
            },
            onFinish: () => {
                isProcessing.value = false;
            }
        });
    }
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

        <!-- Tabs Điều hướng Chính -->
        <div class="flex space-x-1 bg-white border border-gray-100 p-1.5 rounded-2xl shadow-sm mb-6 w-max mx-auto md:mx-0">
            <button 
                v-if="dbPendingDonations && dbPendingDonations.length > 0"
                @click="activeCampaignTab = 'donations'"
                :class="[
                  activeCampaignTab === 'donations' 
                    ? 'bg-emerald-600 text-white shadow-sm font-bold' 
                    : 'text-gray-500 hover:text-emerald-700 hover:bg-emerald-50',
                  'px-4 py-2 text-sm rounded-xl transition-all duration-200 flex items-center gap-2'
                ]"
              >
                <span>🎁 Đối soát quyên góp</span>
                <span class="bg-white text-emerald-700 text-[10px] font-black px-1.5 py-0.5 rounded-md" v-if="activeCampaignTab === 'donations'">{{ dbPendingDonations.length }}</span>
                <span class="bg-red-500 text-white text-[10px] font-black px-1.5 py-0.5 rounded-md animate-pulse" v-else>{{ dbPendingDonations.length }}</span>
            </button>
            <button 
                @click="activeCampaignTab = 'active'"
                :class="[
                  activeCampaignTab === 'active' 
                    ? 'bg-emerald-600 text-white shadow-sm font-bold' 
                    : 'text-gray-500 hover:text-emerald-700 hover:bg-emerald-50',
                  'px-4 py-2 text-sm rounded-xl transition-all duration-200'
                ]"
            >
                🚀 Đang hoạt động ({{ activeCampaigns.length }})
            </button>
            <button 
                @click="activeCampaignTab = 'closed'"
                :class="[
                  activeCampaignTab === 'closed' 
                    ? 'bg-emerald-600 text-white shadow-sm font-bold' 
                    : 'text-gray-500 hover:text-emerald-700 hover:bg-emerald-50',
                  'px-4 py-2 text-sm rounded-xl transition-all duration-200'
                ]"
            >
                🔒 Đã đóng ({{ closedCampaigns.length }})
            </button>
        </div>

        <!-- BỘ LỌC VÀ ĐỐI SOÁT ĐƠN QUYÊN GÓP -->
        <div v-if="activeCampaignTab === 'donations' && dbPendingDonations && dbPendingDonations.length > 0" class="bg-white border border-gray-100 rounded-3xl p-6 shadow-sm space-y-4">
          <div class="flex justify-between items-center border-b border-gray-50 pb-3">
            <h2 class="text-lg font-bold text-emerald-700 tracking-tight flex items-center gap-2">
              Đối soát Đơn quyên góp chờ xác nhận
            </h2>
          </div>
          
          <div class="max-w-md">
            <input 
                type="text" 
                v-model="searchDonationCode" 
                placeholder="🔍 Nhập Mã đơn hoặc Số điện thoại để tìm nhanh..." 
                class="w-full rounded-xl border-gray-200 bg-gray-50 text-sm px-4 py-2.5 focus:border-emerald-500 focus:ring-emerald-500 shadow-inner text-gray-800"
            >
          </div>

          <div v-if="groupedPendingDonations.length === 0" class="text-center text-sm text-gray-400 py-6 bg-gray-50 rounded-2xl">
              Không tìm thấy đơn nào khớp với từ khóa.
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
            <div 
              v-for="donation in groupedPendingDonations" 
              :key="donation.donation_code" 
              class="bg-emerald-50/50 p-4 rounded-2xl border border-emerald-100/50 flex flex-col gap-3 hover:bg-emerald-50 transition shadow-sm"
            >
              <div class="flex justify-between items-start">
                <div>
                  <p class="text-[13px] font-extrabold text-gray-900 bg-emerald-100 px-2 py-0.5 rounded-md inline-block mb-1.5">{{ donation.donation_code }}</p>
                  <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
                    <p class="text-sm font-bold text-gray-900">{{ donation.user?.name || 'Nhà hảo tâm' }} <span v-if="donation.user?.phone">({{ donation.user.phone }})</span></p>
                    <span v-if="donation.user" :class="donation.user.trust_score < 70 ? 'text-red-600 bg-red-50 border-red-200' : 'text-amber-600 bg-amber-50 border-amber-200'" class="text-[10px] font-bold px-1.5 py-0.5 rounded-full border flex items-center gap-0.5" title="Điểm uy tín">
                      ⭐ {{ donation.user.trust_score }}
                    </span>
                  </div>
                  <p class="text-xs text-gray-500 mt-0.5 line-clamp-1">Chiến dịch: <span class="font-medium text-emerald-700">{{ donation.campaign?.title }}</span></p>
                </div>
                <span class="bg-yellow-100 text-yellow-700 text-[10px] font-bold px-2 py-0.5 rounded uppercase shrink-0">Đang chờ</span>
              </div>
              
              <div class="bg-white p-3 rounded-xl border border-gray-100 text-xs text-gray-600 space-y-3">
                <div v-for="(item, idx) in donation.items" :key="item.id" :class="{'border-b border-gray-100 pb-3': idx < donation.items.length - 1}">
                    <div class="flex justify-between mb-1.5">
                      <span class="font-medium">Món đồ:</span>
                      <span class="font-bold text-gray-900">{{ item.campaign_item?.item_name }}</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="font-medium">Số lượng:</span>
                      <span class="font-bold text-emerald-600 text-sm">+{{ item.donation_quantity }} {{ item.unit || '' }}</span>
                    </div>
                    <div v-if="item.food_description" class="mt-2 pt-2 border-t border-gray-50 text-[11px] italic text-gray-500">
                      "{{ item.food_description }}"
                    </div>
                </div>

                <div class="mt-2 pt-2 border-t border-gray-100/80 text-[11px] bg-gray-50 -mx-3 -mb-3 p-3 rounded-b-xl">
                    <p v-if="donation.shipping_method === 'self_delivery'" class="text-blue-700 font-semibold flex items-center gap-1">🚶 Tự mang tới</p>
                    <div v-else-if="donation.shipping_method === 'delivery_service'" class="text-orange-700">
                        <p class="font-semibold mb-1 flex items-center gap-1">🚚 Giao hàng qua Shipper</p>
                        <p>Tài xế: <span class="font-bold">{{ donation.shipper_name || 'Chưa cập nhật' }}</span></p>
                        <p>Biển số: <span class="font-bold">{{ donation.shipper_license_plate || 'Chưa cập nhật' }}</span></p>
                    </div>
                </div>
              </div>

              <div class="mt-auto pt-1">
                <button 
                  v-if="donation.shipping_method === 'delivery_service' && !donation.shipper_name"
                  disabled
                  class="w-full bg-gray-300 text-gray-500 cursor-not-allowed font-bold text-xs py-2 rounded-xl shadow-sm"
                >
                  Chờ cập nhật Shipper
                </button>
                <div v-else class="flex gap-2">
                  <button 
                    @click="verifyDonation(donation.donation_code)"
                    class="w-2/3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs py-2 rounded-xl transition shadow-sm"
                  >
                    Đã nhận đủ {{ donation.items.length }} món
                  </button>
                  <button 
                    @click="openRejectModal(donation.donation_code)"
                    class="w-1/3 bg-red-100 hover:bg-red-200 text-red-700 font-bold text-[11px] py-2 rounded-xl transition shadow-sm border border-red-200"
                  >
                    Từ chối
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Danh sách chiến dịch hiện có -->
        <div v-else-if="activeCampaignTab === 'active' || activeCampaignTab === 'closed'" class="space-y-4">
          
          <div v-if="!currentCampaigns || currentCampaigns.length === 0" class="text-center py-10 bg-white rounded-3xl border border-gray-100 shadow-sm">
             <p class="text-gray-500 text-sm">Chưa có chiến dịch nào trong mục này.</p>
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Thẻ Card Chiến dịch -->
            <div 
              v-for="campaign in currentCampaigns" 
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
                    {{ campaign.status === 'active' ? 'Đang chạy' : (campaign.status === 'pending' ? 'Chờ duyệt' : (campaign.status === 'closed' ? 'Đã chốt' : campaign.status)) }}
                  </span>
                </div>

                <!-- Chi tiết thông tin -->
                <div class="space-y-1 text-xs text-gray-500">
                  <p>📅 Hạn đóng cổng: <strong class="text-gray-800">{{ new Date(campaign.end_date).toLocaleDateString('vi-VN') }}</strong></p>
                  <p v-if="campaign.execution_date">🚀 Ngày đi phát: <strong class="text-gray-800">{{ new Date(campaign.execution_date).toLocaleDateString('vi-VN') }}</strong></p>
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

              <!-- Nút hành động -->
              <div class="pt-4 mt-auto border-t border-gray-50 flex flex-wrap gap-2 justify-end">
                  <a v-if="campaign.status === 'closed' || campaign.status === 'completed'" :href="route('charity.campaigns.export', campaign.id)" target="_blank" class="text-xs font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1">
                      📄 Báo cáo
                  </a>
                  
                  <Link v-if="campaign.status === 'active' || campaign.status === 'pending'" :href="route('charity.campaigns.close', campaign.id)" method="post" as="button" class="text-xs font-semibold text-amber-600 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1" onclick="return confirm('Xác nhận CHỐT chiến dịch? Các đơn đang chờ sẽ bị hủy.')">
                      🔒 Chốt
                  </Link>

                  <Link :href="route('charity.campaigns.edit', campaign.id)" class="text-xs font-semibold text-emerald-600 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                      Sửa
                  </Link>
              </div>

            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- MODAL TỪ CHỐI ĐƠN QUYÊN GÓP -->
    <div 
      v-if="showRejectModal" 
      class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-in fade-in duration-200"
    >
        <div class="bg-white rounded-3xl w-full max-w-sm p-6 shadow-2xl animate-in zoom-in-95 duration-200 border border-gray-100 text-left">
            <h3 class="text-lg font-extrabold text-gray-900 mb-2">Từ chối quyên góp</h3>
            <p class="text-xs text-gray-500 mb-4">Vui lòng chọn lý do từ chối đơn này:</p>
            
            <div class="space-y-2 mb-6">
                <div 
                    v-for="opt in rejectReasons" 
                    :key="opt"
                    @click="rejectForm.reason = opt"
                    class="p-3.5 rounded-2xl border text-xs font-semibold transition cursor-pointer flex items-center justify-between"
                    :class="rejectForm.reason === opt ? 'border-red-500 bg-red-50/50 text-red-700 shadow-sm shadow-red-100' : 'border-gray-100 bg-gray-50 hover:bg-gray-100 text-gray-700'"
                >
                    <span>{{ opt }}</span>
                    <span v-if="rejectForm.reason === opt" class="text-red-500 font-bold">✓</span>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                <button @click="showRejectModal = false" :disabled="isProcessing" class="flex-1 px-4 py-2.5 text-xs font-bold text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition text-center disabled:opacity-50">Đóng</button>
                <button @click="submitRejectDonation" :disabled="isProcessing" class="flex-1 px-4 py-2.5 text-xs font-bold text-white bg-red-600 rounded-xl hover:bg-red-700 shadow-md shadow-red-500/30 transition text-center disabled:opacity-50 flex justify-center items-center gap-2">
                  <svg v-if="isProcessing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                  <span>{{ isProcessing ? 'Đang xử lý...' : 'Xác nhận' }}</span>
                </button>
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

