<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 sm:p-0">
      <div class="w-full max-w-md bg-white rounded-2xl shadow-xl relative animate-in zoom-in-95 overflow-hidden flex flex-col max-h-[90vh]">
        
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50 shrink-0">
          <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
            <span class="text-red-500">🚩</span> Báo cáo vi phạm
          </h3>
          <button @click="close" class="text-gray-400 hover:text-gray-600 focus:outline-none p-1 rounded-full hover:bg-gray-200 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <!-- Body -->
        <div class="p-6 overflow-y-auto flex-1 custom-scrollbar">
          <form @submit.prevent="submitReport" class="space-y-4">
            
            <div class="bg-red-50 text-red-700 text-xs p-3 rounded-xl border border-red-100 flex items-start gap-2">
              <span class="shrink-0 mt-0.5">⚠️</span>
              <p><strong>Lưu ý:</strong> Việc báo cáo vi phạm sẽ được quản trị viên xem xét. Nếu bạn cố tình báo cáo sai sự thật hoặc spam, tài khoản của bạn sẽ bị trừ 20 điểm uy tín.</p>
            </div>

            <div v-if="targetUser" class="bg-gray-50 p-3 rounded-xl border border-gray-100 space-y-1">
              <p class="text-xs text-gray-500">Đối tượng bị báo cáo:</p>
              <p class="text-sm font-bold text-gray-800">{{ targetUser.name }}</p>
              <p v-if="targetPost" class="text-xs text-gray-600 line-clamp-1 mt-1">Bài viết: <span class="font-semibold">{{ targetPost.title }}</span></p>
              <p v-else-if="targetCampaign" class="text-xs text-gray-600 line-clamp-1 mt-1">Chiến dịch: <span class="font-semibold">{{ targetCampaign.title }}</span></p>
              <p v-if="targetDonation" class="text-xs text-gray-600 line-clamp-1 mt-1">Mã đơn đóng góp: <span class="font-semibold">{{ targetDonation.donation_code }}</span></p>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Lý do báo cáo <span class="text-red-500">*</span></label>
              <div class="space-y-2">
                <label v-for="reason in currentReasons" :key="reason" class="flex items-center p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-red-50/30 transition" :class="{'bg-red-50 border-red-200 ring-1 ring-red-500': form.reason === reason}">
                  <input type="radio" v-model="form.reason" :value="reason" class="w-4 h-4 text-red-600 bg-gray-100 border-gray-300 focus:ring-red-500" required>
                  <span class="ml-3 text-sm font-medium text-gray-700">{{ reason }}</span>
                </label>
              </div>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Mô tả chi tiết <span class="text-gray-400 font-normal text-xs">(Không bắt buộc)</span></label>
              <textarea v-model="form.details" rows="3" class="w-full rounded-xl border-gray-200 focus:border-red-500 focus:ring-red-500 text-sm resize-none" placeholder="Hãy mô tả chi tiết thêm về vi phạm..."></textarea>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">
                Ảnh bằng chứng 
                <span v-if="isImageRequired" class="text-red-500">*</span>
                <span v-else class="text-gray-400 font-normal text-xs ml-1">(Không bắt buộc)</span>
              </label>
              <p v-if="isImageRequired" class="text-[11px] text-gray-500 mb-2">Vui lòng tải lên ảnh chụp màn hình tin nhắn, cuộc gọi hoặc tình trạng thực tế để chứng minh (Tối đa 5 ảnh).</p>
              <p v-else class="text-[11px] text-gray-500 mb-2">Bạn có thể tải lên ảnh chụp màn hình minh chứng vi phạm (Tối đa 5 ảnh).</p>
              
              <div class="space-y-3">
                  <!-- Lưới hiển thị ảnh đã chọn -->
                  <div v-if="imagePreviewUrls.length > 0" class="grid grid-cols-3 gap-2">
                      <div v-for="(url, index) in imagePreviewUrls" :key="index" class="relative aspect-square rounded-xl overflow-hidden border border-gray-200 group">
                          <img :src="url" class="w-full h-full object-cover" />
                          <button type="button" @click.prevent="removeImage(index)" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity z-10 hover:bg-red-600">
                              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                          </button>
                      </div>
                      
                      <!-- Nút thêm ảnh phụ -->
                      <label v-if="imagePreviewUrls.length < 5" class="flex flex-col items-center justify-center aspect-square border-2 border-gray-300 border-dashed rounded-xl cursor-pointer bg-gray-50 hover:bg-gray-100 transition">
                          <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                          <span class="text-[10px] text-gray-500 font-semibold mt-1">Thêm ảnh</span>
                          <input type="file" class="hidden" accept="image/*" multiple @change="handleImageUpload" />
                      </label>
                  </div>

                  <!-- Nút tải ảnh ban đầu -->
                  <label v-else class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-xl cursor-pointer bg-gray-50 hover:bg-gray-100 transition">
                      <div class="flex flex-col items-center justify-center pt-5 pb-6">
                          <svg class="w-8 h-8 mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                          <p class="mb-1 text-sm text-gray-500 font-semibold">Bấm để chọn nhiều ảnh</p>
                          <p class="text-xs text-gray-500">PNG, JPG, JPEG (Max 5MB/ảnh)</p>
                      </div>
                      <input type="file" class="hidden" accept="image/*" multiple @change="handleImageUpload" />
                  </label>
              </div>
              <p v-if="imageError" class="text-red-500 text-xs mt-1">{{ imageError }}</p>
            </div>

          </form>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-gray-50/50 border-t border-gray-100 flex justify-end gap-3 shrink-0">
          <button type="button" @click="close" class="px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 focus:outline-none transition shadow-sm">
            Hủy
          </button>
          <button 
            @click="submitReport" 
            :disabled="form.processing || !form.reason || (isImageRequired && form.proof_images.length === 0)" 
            class="px-5 py-2 text-sm font-bold text-white bg-red-600 rounded-xl hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none transition shadow-sm flex items-center gap-2"
          >
            <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            Gửi báo cáo
          </button>
        </div>
        
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    show: Boolean,
    targetUser: Object,
    targetPost: Object,
    targetClaim: Object,
    targetCampaign: Object,
    targetDonation: Object
});

const emit = defineEmits(['close', 'success']);

const isImageRequired = computed(() => !!props.targetClaim || !!props.targetDonation);

const reportContext = computed(() => {
    if (props.targetClaim) {
        return 'transaction';
    } else if (props.targetCampaign || props.targetDonation) {
        return 'campaign';
    } else {
        return 'post';
    }
});

const reasonsPost = [
    'Hình ảnh giả mạo / Lấy từ mạng',
    'Spam / Quảng cáo bán hàng',
    'Nội dung phản cảm / Thô tục',
    'Bài đăng không phải là thực phẩm',
    'Dấu hiệu lừa đảo / Chứa link lạ',
    'Lý do khác'
];

const reasonsTransaction = [
    'Địa chỉ giả mạo / Không tồn tại',
    'Không liên lạc được với người cho',
    'Thực phẩm bị hỏng / Hết hạn / Không như mô tả',
    'Thái độ xúc phạm / Đe dọa',
    'Lừa đảo / Yêu cầu chuyển tiền',
    'Lý do khác'
];

const reasonsCampaign = [
    'Giả mạo tổ chức / Cá nhân uy tín',
    'Kêu gọi chuyển tiền mặt / Sai mục đích',
    'Dấu hiệu trục lợi / Thông tin sai sự thật',
    'Nhận hàng nhưng bặt vô âm tín (Không xác nhận đơn)',
    'Nội dung độc hại / Phản cảm',
    'Lý do khác'
];

const currentReasons = computed(() => {
    if (reportContext.value === 'transaction') return reasonsTransaction;
    if (reportContext.value === 'campaign') return reasonsCampaign;
    return reasonsPost;
});

const form = useForm({
    reported_user_id: '',
    food_post_id: '',
    food_claim_id: '',
    campaign_id: '',
    campaign_donation_id: '',
    reason: '',
    details: '',
    proof_images: [],
});

const imagePreviewUrls = ref([]);
const imageError = ref('');

watch(() => props.show, (newVal) => {
    if (newVal) {
        form.reset();
        form.clearErrors();
        imagePreviewUrls.value = [];
        imageError.value = '';
        
        if (props.targetUser) form.reported_user_id = props.targetUser.id;
        if (props.targetPost) form.food_post_id = props.targetPost.id;
        if (props.targetClaim) form.food_claim_id = props.targetClaim.id;
        if (props.targetCampaign) form.campaign_id = props.targetCampaign.id;
        if (props.targetDonation) form.campaign_donation_id = props.targetDonation.id;
    }
});

const handleImageUpload = (event) => {
    const files = Array.from(event.target.files);
    imageError.value = '';
    
    if (!files.length) return;
    
    if (form.proof_images.length + files.length > 5) {
        imageError.value = 'Chỉ được tải lên tối đa 5 ảnh.';
        return;
    }
    
    for (const file of files) {
        if (file.size > 5 * 1024 * 1024) {
            imageError.value = 'Kích thước ảnh không được vượt quá 5MB/ảnh.';
            continue;
        }
        
        if (!['image/jpeg', 'image/png', 'image/jpg'].includes(file.type)) {
            imageError.value = 'Chỉ chấp nhận file ảnh (PNG, JPG, JPEG).';
            continue;
        }
        
        form.proof_images.push(file);
        
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreviewUrls.value.push(e.target.result);
        };
        reader.readAsDataURL(file);
    }
    
    // Reset file input so same file can be selected again if needed
    event.target.value = '';
};

const removeImage = (index) => {
    form.proof_images.splice(index, 1);
    imagePreviewUrls.value.splice(index, 1);
};

const close = () => {
    emit('close');
};

const submitReport = () => {
    if (!form.reason || (isImageRequired.value && form.proof_images.length === 0)) return;
    
    form.post(route('reports.store'), {
        preserveScroll: true,
        onSuccess: () => {
            close();
            emit('success');
        }
    });
};
</script>
