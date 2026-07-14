<script setup>
import { ref, watch, onUnmounted, computed, onMounted } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

// 1. Nhận props từ controller
const props = defineProps({
    dbCategories: Array,
    dbFoodPosts: Array
});

const isCreateModalOpen = ref(false);
const foodPosts = ref(props.dbFoodPosts);
const categories = ref(props.dbCategories);

const activeManageTab = ref('active'); // 'active' hoặc 'inactive'

const currentTime = ref(new Date());
let timeTicker = null;

const isCategoryDropdownOpen = ref(false);
const categoryDropdownRef = ref(null);

const isEditCategoryDropdownOpen = ref(false);
const editCategoryDropdownRef = ref(null);

const handleClickOutsideCategory = (event) => {
    if (categoryDropdownRef.value && !categoryDropdownRef.value.contains(event.target)) {
        isCategoryDropdownOpen.value = false;
    }
    if (editCategoryDropdownRef.value && !editCategoryDropdownRef.value.contains(event.target)) {
        isEditCategoryDropdownOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutsideCategory);
    timeTicker = setInterval(() => {
        currentTime.value = new Date();
    }, 10000); // cập nhật mỗi 10 giây
});

const activePosts = computed(() => {
    return foodPosts.value.filter(post => {
        return post.ai_status !== 'flagged' && post.status === 'available' && new Date(post.expires_at) > currentTime.value;
    });
});

const inactivePosts = computed(() => {
    return foodPosts.value.filter(post => {
        return post.ai_status !== 'flagged' && (post.status !== 'available' || new Date(post.expires_at) <= currentTime.value);
    });
});

const flaggedPosts = computed(() => {
    return foodPosts.value.filter(post => {
        return post.ai_status === 'flagged';
    });
});

const handleToggleStatus = (postId) => {
    router.post(route('food-posts.toggle-status', postId), {}, {
        preserveScroll: true
    });
};

const handleDeletePost = (postId) => {
    if (confirm('Bạn có chắc chắn muốn gỡ bài đăng thực phẩm này không? Hệ thống sẽ tự động hủy các yêu cầu nhận liên quan chưa hoàn thành.')) {
        router.delete(route('food-posts.destroy', postId), {
            preserveScroll: true
        });
    }
};

// Polling tự động làm mới danh sách khi có tin đăng đang chờ AI kiểm duyệt ngầm
let pollInterval = null;

const startPolling = () => {
    if (pollInterval) return;
    pollInterval = setInterval(() => {
        const hasUnchecked = foodPosts.value.some(post => post.ai_status === 'unchecked');
        if (hasUnchecked) {
            router.reload({ 
                only: ['dbFoodPosts'],
                onSuccess: () => {
                    const stillHasUnchecked = foodPosts.value.some(post => post.ai_status === 'unchecked');
                    if (!stillHasUnchecked) {
                        stopPolling();
                    }
                }
            });
        } else {
            stopPolling();
        }
    }, 3000); // 3 giây quét 1 lần
};

const stopPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

// Tự động đồng bộ danh sách bài đăng khi Backend gửi Props mới về
watch(() => props.dbFoodPosts, (newPosts) => {
    foodPosts.value = newPosts;
    const hasUnchecked = newPosts.some(post => post.ai_status === 'unchecked');
    if (hasUnchecked) {
        startPolling();
    } else {
        stopPolling();
    }
}, { immediate: true });

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutsideCategory);
    stopPolling();
    if (timeTicker) clearInterval(timeTicker);
});

// 2. Khai báo form bằng useForm của Inertia
const form = useForm({
    title: '',
    category_id: '',
    description: '',
    original_quantity: '',
    unit: '',
    image: null, // Lưu trữ File object thực tế để upload ảnh
    expires_at: '',
});

// Chức năng Chỉnh sửa & Gia hạn bài đăng
const isEditModalOpen = ref(false);
const editForm = useForm({
    id: '',
    title: '',
    category_id: '',
    description: '',
    original_quantity: '',
    unit: '',
    image: null,
    expires_at: '',
});

const openEditModal = (post) => {
    editForm.id = post.id;
    editForm.title = post.title;
    editForm.category_id = post.category_id;
    editForm.description = post.description;
    editForm.original_quantity = post.original_quantity;
    editForm.unit = post.unit;
    editForm.image = null; // Giữ trống trừ khi tải ảnh mới
    if (post.expires_at) {
        const date = new Date(post.expires_at);
        const localISO = new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
        editForm.expires_at = localISO;
    } else {
        editForm.expires_at = '';
    }
    isEditModalOpen.value = true;
};

const handleUpdate = () => {
    editForm.post(route('food-posts.update', editForm.id), {
        forceFormData: true,
        onSuccess: () => {
            isEditModalOpen.value = false;
        },
    });
};

// Hàm hỗ trợ tìm tên danh mục từ ID
const getCategoryName = (catId) => {
    const category = categories.value.find(c => c.id === Number(catId));
    return category ? category.name : 'Chưa phân loại';
};


// 3. Hàm xử lý gửi form lên Backend
const handleSubmit = () => {
    form.post(route('food-posts.store'), {
        forceFormData: true, // Bắt buộc gửi dạng FormData để upload file ảnh
        onSuccess: () => {
            // Khi đăng thành công: reset form và đóng modal
            form.reset();
            isCreateModalOpen.value = false;
        },
    });
};



</script>

<template>
  <AuthenticatedLayout>
    <div class="min-h-screen bg-gray-50/50 py-8 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto space-y-6">
        
        <!-- Tiêu đề trang & Nút hành động -->
        <div class="flex justify-between items-center border-b border-gray-100 pb-5">
          <div>
            <h1 class="text-2xl font-bold text-gray-950 tracking-tight">Quản lý thực phẩm quyên tặng</h1>
            <p class="text-xs text-gray-500 mt-1">Danh sách thực phẩm lẻ cá nhân/doanh nghiệp bạn đang chia sẻ.</p>
          </div>
          
          <div class="flex items-center gap-3">
            <button 
              @click="isCreateModalOpen = true"
              class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs py-2.5 px-4 rounded-xl transition duration-200 shadow-sm flex items-center gap-1.5"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
              Đăng tặng thực phẩm mới
            </button>
          </div>
        </div>

      <!-- Bộ chọn Tab Quản lý Thực phẩm -->
      <div class="flex border-b border-gray-100 pb-px gap-6">
        <button 
          @click="activeManageTab = 'active'"
          :class="[
            activeManageTab === 'active' 
              ? 'border-emerald-600 text-emerald-600 font-bold' 
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
          ]"
          class="pb-4 border-b-2 text-sm font-semibold transition duration-200 cursor-pointer flex items-center gap-2"
        >
          🥗 Thực phẩm đang chia sẻ
          <span 
            :class="activeManageTab === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'" 
            class="text-[10px] px-2 py-0.5 rounded-full font-bold"
          >
            {{ activePosts.length }}
          </span>
        </button>
        <button 
          @click="activeManageTab = 'inactive'"
          :class="[
            activeManageTab === 'inactive' 
              ? 'border-emerald-600 text-emerald-600 font-bold' 
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
          ]"
          class="pb-4 border-b-2 text-sm font-semibold transition duration-200 cursor-pointer flex items-center gap-2"
        >
          🛑 Hết hạn & Tạm ẩn
          <span 
            :class="activeManageTab === 'inactive' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'" 
            class="text-[10px] px-2 py-0.5 rounded-full font-bold"
          >
            {{ inactivePosts.length }}
          </span>
        </button>
        <button 
          @click="activeManageTab = 'flagged'"
          :class="[
            activeManageTab === 'flagged' 
              ? 'border-red-500 text-red-600 font-bold' 
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
          ]"
          class="pb-4 border-b-2 text-sm font-semibold transition duration-200 cursor-pointer flex items-center gap-2"
        >
          ⚠️ Vi phạm kiểm duyệt
          <span 
            :class="activeManageTab === 'flagged' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-600'" 
            class="text-[10px] px-2 py-0.5 rounded-full font-bold"
          >
            {{ flaggedPosts.length }}
          </span>
        </button>
      </div>

      <!-- Danh sách bài đăng dạng lưới thẻ (Grid Cards) -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 items-start">
        <div 
          v-for="post in (activeManageTab === 'active' ? activePosts : (activeManageTab === 'inactive' ? inactivePosts : flaggedPosts))" 
          :key="post.id" 
          class="bg-white border border-gray-100 rounded-3xl overflow-hidden shadow-sm hover:shadow-md transition duration-200 flex flex-col group"
        >
          <!-- 1. Hình ảnh thực tế & Ghim danh mục -->
          <div class="relative bg-gray-50 h-48 overflow-hidden flex items-center justify-center text-gray-400 text-sm font-medium border-b border-gray-100">
            <!-- Nếu có tên ảnh mock từ input file, ta dùng ảnh minh họa từ Unsplash tương ứng, nếu không có thì hiện ảnh mặc định -->
            <img 
              :src="post.image_url ? post.image_url : 'https://images.unsplash.com/photo-1488459718432-36c55e7946d5?auto=format&fit=crop&w=600&q=80'" 
              class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
              alt="Hình ảnh thực phẩm"
            />
            
            <!-- Danh mục ghim góc trên trái -->
            <span class="absolute top-3 left-3 bg-white/90 backdrop-blur-md text-emerald-800 text-[10px] font-bold px-2.5 py-1.5 rounded-xl border border-emerald-100 shadow-sm">
              📂 {{ getCategoryName(post.category_id) }}
            </span>

            <!-- Nhãn AI kiểm duyệt ở góc trên phải -->
            <span v-if="post.ai_status === 'safe'" class="absolute top-3 right-3 bg-emerald-600/90 backdrop-blur-md text-white text-[9px] font-bold px-2 py-1 rounded-lg shadow-sm flex items-center gap-1">
              🛡️ Đã kiểm duyệt bởi AI
            </span>
          </div>

          <!-- 2. Nội dung chi tiết -->
          <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
            <div class="space-y-2">
              <h3 class="font-bold text-gray-950 text-base leading-snug group-hover:text-emerald-600 transition duration-200 line-clamp-1">
                {{ post.title }}
              </h3>
              <p class="text-[11px] text-gray-400">
                Đăng lúc: {{ new Date(post.created_at).toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' }) }}
              </p>
              <p class="text-xs text-gray-500 line-clamp-2 h-8 leading-relaxed">
                {{ post.description }}
              </p>

              <!-- Hiển thị lý do vi phạm nếu bài viết bị gắn cờ -->
              <div v-if="post.ai_status === 'flagged'" class="mt-2 p-3 bg-red-50 border border-red-100 rounded-2xl text-[11px] text-red-700 space-y-1">
                <span class="font-extrabold uppercase text-[9px] tracking-wider block text-red-800">⚠️ Lý do vi phạm:</span>
                <p class="leading-relaxed font-medium">{{ post.moderation_reason || 'Hình ảnh không phù hợp với tiêu chuẩn an toàn thực phẩm.' }}</p>
              </div>

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

            <!-- 3. Huy hệ trạng thái và Nút đóng/mở ẩn tin -->
            <div class="space-y-3 pt-2">
              <div class="flex justify-between items-center gap-2 border-t border-gray-50 pt-3">
                <!-- Trạng thái hoạt động -->
                <div class="flex items-center gap-1">
                  <span class="text-[10px] text-gray-400">Tin đăng:</span>
                  <span 
                    :class="(post.status === 'available' && new Date(post.expires_at) > currentTime) ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-red-50 text-red-700 border-red-100'"
                    class="text-[10px] font-bold px-2 py-0.5 rounded-md border"
                  >
                    {{ (post.status === 'available' && new Date(post.expires_at) > currentTime) ? 'Đang chạy' : 'Đã dừng / Hết hạn' }}
                  </span>
                </div>
                
                <!-- Trạng thái AI kiểm duyệt -->
                <div class="flex items-center gap-1">
                  <span class="text-[10px] text-gray-400">AI duyệt:</span>
                  <span 
                    :class="{
                      'bg-emerald-50 text-emerald-700 border-emerald-100': post.ai_status === 'safe',
                      'bg-amber-50 text-amber-700 border-amber-100': post.ai_status === 'unchecked',
                      'bg-red-50 text-red-700 border-red-100': post.ai_status === 'flagged'
                    }"
                    class="text-[10px] font-bold px-2 py-0.5 rounded-md border"
                  >
                    {{ post.ai_status === 'safe' ? 'Đã duyệt' : (post.ai_status === 'unchecked' ? 'Đang duyệt' : 'Từ chối') }}
                  </span>
                </div>
              </div>

              <!-- Nhóm nút quản lý bài đăng -->
              <div class="space-y-2 w-full pt-1">
                <!-- Dòng 1: Nút hành động chính và Chỉnh sửa -->
                <div class="flex gap-2 w-full">
                  <!-- Nếu bài viết an toàn & đang hoạt động -->
                  <template v-if="post.ai_status === 'safe' && post.status === 'available' && new Date(post.expires_at) > currentTime">
                    <button 
                      @click="handleToggleStatus(post.id)"
                      class="flex-1 bg-gray-50 hover:bg-red-50 text-gray-600 hover:text-red-600 border border-gray-200 hover:border-red-200 font-bold text-xs py-2.5 px-3 rounded-xl transition text-center cursor-pointer"
                    >
                      🛑 Tạm ẩn
                    </button>
                    <button 
                      @click="openEditModal(post)"
                      class="flex-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 font-bold text-xs py-2.5 px-3 rounded-xl transition text-center cursor-pointer"
                    >
                      ✏️ Sửa tin
                    </button>
                  </template>

                  <!-- Nếu bài viết an toàn & hết hạn / đã tạm ẩn -->
                  <template v-else-if="post.ai_status === 'safe'">
                    <button 
                      @click="openEditModal(post)"
                      class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs py-2.5 px-4 rounded-xl transition text-center cursor-pointer flex items-center justify-center gap-1.5 shadow-sm"
                    >
                      ✅ Gia hạn & Chỉnh sửa
                    </button>
                  </template>

                  <!-- Nếu bài viết vi phạm kiểm duyệt (Flagged) -->
                  <template v-else-if="post.ai_status === 'flagged'">
                    <button 
                      @click="openEditModal(post)"
                      class="w-full bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 font-bold text-xs py-2.5 px-4 rounded-xl transition text-center cursor-pointer flex items-center justify-center gap-1.5"
                    >
                      ✏️ Sửa & Gửi duyệt lại
                    </button>
                  </template>
                </div>

                <!-- Dòng 2: Nút Gỡ tin (Xóa) -->
                <button 
                  @click="handleDeletePost(post.id)"
                  class="w-full bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 font-bold text-xs py-2.5 rounded-xl transition text-center cursor-pointer flex items-center justify-center gap-1"
                  title="Gỡ bài đăng hoàn toàn"
                >
                  🗑️ Gỡ tin đăng
                </button>
              </div>

              <!-- Danh sách người đăng ký nhận -->
              <div v-if="post.claims && post.claims.length > 0" class="mt-4 pt-4 border-t border-gray-100 space-y-3">
                <div class="flex justify-between items-center">
                  <span class="text-xs font-extrabold text-gray-900 uppercase tracking-wider">Danh sách đăng ký nhận:</span>
                  <span class="bg-amber-50 text-amber-800 text-[10px] px-2 py-0.5 rounded-full font-bold">
                    {{ post.claims.length }} lượt
                  </span>
                </div>
                
                <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                  <div 
                    v-for="claim in post.claims" 
                    :key="claim.id"
                    class="p-2.5 bg-gray-50 rounded-xl border border-gray-100 space-y-2 text-xs"
                  >
                    <div class="flex justify-between items-center gap-2">
                      <div class="font-semibold text-gray-800 flex items-center gap-1">
                        👤 {{ claim.user?.name || 'Người dùng' }}
                      </div>
                      <span 
                        :class="{
                          'bg-amber-50 text-amber-700 border-amber-100': claim.status === 'pending',
                          'bg-emerald-50 text-emerald-700 border-emerald-100': claim.status === 'approved',
                          'bg-red-50 text-red-700 border-red-100': claim.status === 'rejected'
                        }"
                        class="text-[9px] font-bold px-1.5 py-0.5 rounded border shrink-0"
                      >
                        {{ claim.status === 'pending' ? 'Chờ duyệt' : (claim.status === 'approved' ? 'Đã duyệt' : 'Từ chối') }}
                      </span>
                    </div>
                    
                    <div class="flex justify-between items-center text-[11px] text-gray-500">
                      <span>Đăng ký xin: <b>{{ claim.quantity }} {{ post.unit }}</b></span>
                      <span class="text-[10px] text-gray-400">{{ new Date(claim.created_at).toLocaleDateString('vi-VN') }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Trống dữ liệu cho Tab Đang chia sẻ -->
        <div v-if="activeManageTab === 'active' && activePosts.length === 0" class="col-span-full bg-white border border-dashed border-gray-300 rounded-3xl p-12 text-center text-gray-400 text-sm">
          Không có thực phẩm nào đang chia sẻ. Hãy bấm "Đăng tặng thực phẩm mới" để bắt đầu!
        </div>

        <!-- Trống dữ liệu cho Tab Hết hạn & Tạm ẩn -->
        <div v-if="activeManageTab === 'inactive' && inactivePosts.length === 0" class="col-span-full bg-white border border-dashed border-gray-300 rounded-3xl p-12 text-center text-gray-400 text-sm">
          Không có thực phẩm nào đã hết hạn hoặc tạm ẩn.
        </div>

        <!-- Trống dữ liệu cho Tab Vi phạm kiểm duyệt -->
        <div v-if="activeManageTab === 'flagged' && flaggedPosts.length === 0" class="col-span-full bg-white border border-dashed border-gray-300 rounded-3xl p-12 text-center text-gray-400 text-sm">
          Không có bài đăng nào bị vi phạm kiểm duyệt hình ảnh.
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
              <label class="text-xs font-semibold text-gray-700">Tên món ăn / Thực phẩm <span class="text-red-500">*</span></label>
              <input 
                type="text" 
                v-model="form.title"
                :class="form.errors.title ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-emerald-500'"
                class="w-full bg-gray-50 border text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:border-transparent transition"
                placeholder="Ví dụ: Bánh bao chay, cơm gà xối mỡ..."
              />
              <p v-if="form.errors.title" class="text-red-500 text-[11px] font-semibold mt-1">{{ form.errors.title }}</p>
            </div>

            <!-- Grid 2 cột -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- 2. Danh mục thực phẩm (Custom Dropdown) -->
              <div class="space-y-1.5 relative" ref="categoryDropdownRef">
                <label class="text-xs font-semibold text-gray-700">Danh mục <span class="text-red-500">*</span></label>
                <div class="relative">
                  <button 
                    type="button"
                    @click="isCategoryDropdownOpen = !isCategoryDropdownOpen"
                    :class="form.errors.category_id ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-emerald-500'"
                    class="w-full flex items-center justify-between bg-gray-50 border text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:border-transparent transition"
                  >
                    <span :class="!form.category_id ? 'text-gray-500' : 'text-gray-900'">
                      {{ form.category_id ? getCategoryName(form.category_id) : '-- Chọn danh mục --' }}
                    </span>
                    <svg :class="isCategoryDropdownOpen ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                  </button>

                  <transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="transform scale-95 opacity-0"
                    enter-to-class="transform scale-100 opacity-100"
                    leave-active-class="transition duration-75 ease-in"
                    leave-from-class="transform scale-100 opacity-100"
                    leave-to-class="transform scale-95 opacity-0"
                  >
                    <div 
                      v-if="isCategoryDropdownOpen" 
                      class="absolute top-full left-0 mt-1 w-full bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden z-50 origin-top max-h-48 overflow-y-auto custom-scrollbar"
                    >
                      <ul class="py-1">
                        <li 
                          v-for="cat in categories" 
                          :key="cat.id"
                          @click="form.category_id = cat.id; isCategoryDropdownOpen = false"
                          class="px-4 py-2 text-sm text-gray-700 hover:bg-emerald-50 hover:text-emerald-700 cursor-pointer transition-colors duration-150 flex items-center justify-between"
                        >
                          <span :class="{'font-bold text-emerald-600': form.category_id === cat.id}">{{ cat.name }}</span>
                          <svg v-if="form.category_id === cat.id" class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        </li>
                      </ul>
                    </div>
                  </transition>
                </div>
                <p v-if="form.errors.category_id" class="text-red-500 text-[11px] font-semibold mt-1">{{ form.errors.category_id }}</p>
              </div>

              <!-- 3. Hạn sử dụng -->
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-700">Hạn sử dụng <span class="text-red-500">*</span></label>
                <input 
                    type="datetime-local" 
                    v-model="form.expires_at"
                    :class="form.errors.expires_at ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-emerald-500'"
                    class="w-full bg-gray-50 border text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:border-transparent transition"
                />
                <p v-if="form.errors.expires_at" class="text-red-500 text-[11px] font-semibold mt-1">{{ form.errors.expires_at }}</p>
              </div>

              <!-- 4. Số lượng ban đầu -->
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-700">Số lượng <span class="text-red-500">*</span></label>
                <input 
                  type="number" 
                  v-model.number="form.original_quantity"
                  min="1"
                  :class="form.errors.original_quantity ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-emerald-500'"
                  class="w-full bg-gray-50 border text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:border-transparent transition"
                  placeholder="Ví dụ: 5, 10..."
                />
                <p v-if="form.errors.original_quantity" class="text-red-500 text-[11px] font-semibold mt-1">{{ form.errors.original_quantity }}</p>
              </div>

              <!-- 5. Đơn vị tính -->
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-700">Đơn vị tính <span class="text-red-500">*</span></label>
                <input 
                  type="text" 
                  v-model="form.unit"
                  :class="form.errors.unit ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-emerald-500'"
                  class="w-full bg-gray-50 border text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:border-transparent transition"
                  placeholder="Ví dụ: suất, hộp, cái, kg..."
                />
                <p v-if="form.errors.unit" class="text-red-500 text-[11px] font-semibold mt-1">{{ form.errors.unit }}</p>
              </div>
            </div>

            <!-- 6. Mô tả chi tiết -->
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-700">Mô tả chi tiết <span class="text-red-500">*</span></label>
              <textarea 
                v-model="form.description"
                rows="3"
                :class="form.errors.description ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-emerald-500'"
                class="w-full bg-gray-50 border text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:border-transparent transition"
                placeholder="Mô tả tình trạng thực phẩm (Ví dụ: Mới nấu trưa nay, đóng gói sạch sẽ...)"
              ></textarea>
              <p v-if="form.errors.description" class="text-red-500 text-[11px] font-semibold mt-1">{{ form.errors.description }}</p>
            </div>

            <!-- 7. Hình ảnh minh họa -->
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-700">Hình ảnh thực tế</label>
              <input 
                type="file" 
                accept="image/*"
                @change="(e) => form.image = e.target.files[0]"
                class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 transition cursor-pointer"
              />
              <p v-if="form.errors.image" class="text-red-500 text-[11px] font-semibold mt-1">{{ form.errors.image }}</p>
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

      <!-- MODAL CHỈNH SỬA & GIA HẠN THỰC PHẨM -->
      <div 
        v-if="isEditModalOpen" 
        class="fixed inset-0 bg-gray-950/40 backdrop-blur-sm z-50 flex items-center justify-center p-4"
      >
        <!-- Khung Form nổi -->
        <div class="bg-white rounded-3xl border border-gray-100 shadow-xl p-6 sm:p-8 space-y-6 max-w-2xl w-full relative max-h-[90vh] overflow-y-auto custom-scrollbar">
          
          <!-- Nút Đóng góc trên bên phải -->
          <button 
            @click="isEditModalOpen = false" 
            class="absolute top-5 right-5 text-gray-400 hover:text-gray-600 transition"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <div>
            <h2 class="text-xl font-bold text-gray-950 tracking-tight">Chỉnh sửa & Gia hạn bài đăng</h2>
            <p class="text-xs text-gray-500 mt-1">Cập nhật thông tin thực phẩm và thiết lập hạn sử dụng mới để kích hoạt lại bài đăng.</p>
          </div>

          <form @submit.prevent="handleUpdate" class="space-y-6">

            <!-- 1. Tiêu đề bài đăng (Tên món ăn) -->
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-700">Tên món ăn / Thực phẩm <span class="text-red-500">*</span></label>
              <input 
                type="text" 
                v-model="editForm.title"
                :class="editForm.errors.title ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-emerald-500'"
                class="w-full bg-gray-50 border text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:border-transparent transition"
                placeholder="Ví dụ: Bánh bao chay, cơm gà xối mỡ..."
              />
              <p v-if="editForm.errors.title" class="text-red-500 text-[11px] font-semibold mt-1">{{ editForm.errors.title }}</p>
            </div>

            <!-- Grid 2 cột -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- 2. Danh mục thực phẩm (Custom Dropdown) -->
              <div class="space-y-1.5 relative" ref="editCategoryDropdownRef">
                <label class="text-xs font-semibold text-gray-700">Danh mục <span class="text-red-500">*</span></label>
                <div class="relative">
                  <button 
                    type="button"
                    @click="isEditCategoryDropdownOpen = !isEditCategoryDropdownOpen"
                    :class="editForm.errors.category_id ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-emerald-500'"
                    class="w-full flex items-center justify-between bg-gray-50 border text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:border-transparent transition"
                  >
                    <span :class="!editForm.category_id ? 'text-gray-500' : 'text-gray-900'">
                      {{ editForm.category_id ? getCategoryName(editForm.category_id) : '-- Chọn danh mục --' }}
                    </span>
                    <svg :class="isEditCategoryDropdownOpen ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                  </button>

                  <transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="transform scale-95 opacity-0"
                    enter-to-class="transform scale-100 opacity-100"
                    leave-active-class="transition duration-75 ease-in"
                    leave-from-class="transform scale-100 opacity-100"
                    leave-to-class="transform scale-95 opacity-0"
                  >
                    <div 
                      v-if="isEditCategoryDropdownOpen" 
                      class="absolute top-full left-0 mt-1 w-full bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden z-50 origin-top max-h-48 overflow-y-auto custom-scrollbar"
                    >
                      <ul class="py-1">
                        <li 
                          v-for="cat in categories" 
                          :key="cat.id"
                          @click="editForm.category_id = cat.id; isEditCategoryDropdownOpen = false"
                          class="px-4 py-2 text-sm text-gray-700 hover:bg-emerald-50 hover:text-emerald-700 cursor-pointer transition-colors duration-150 flex items-center justify-between"
                        >
                          <span :class="{'font-bold text-emerald-600': editForm.category_id === cat.id}">{{ cat.name }}</span>
                          <svg v-if="editForm.category_id === cat.id" class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        </li>
                      </ul>
                    </div>
                  </transition>
                </div>
                <p v-if="editForm.errors.category_id" class="text-red-500 text-[11px] font-semibold mt-1">{{ editForm.errors.category_id }}</p>
              </div>

              <!-- 3. Hạn sử dụng -->
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-700">Hạn sử dụng mới <span class="text-red-500">*</span></label>
                <input 
                    type="datetime-local" 
                    v-model="editForm.expires_at"
                    :class="editForm.errors.expires_at ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-emerald-500'"
                    class="w-full bg-gray-50 border text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:border-transparent transition"
                />
                <p v-if="editForm.errors.expires_at" class="text-red-500 text-[11px] font-semibold mt-1">{{ editForm.errors.expires_at }}</p>
              </div>

              <!-- 4. Số lượng ban đầu -->
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-700">Số lượng <span class="text-red-500">*</span></label>
                <input 
                  type="number" 
                  v-model.number="editForm.original_quantity"
                  min="1"
                  :class="editForm.errors.original_quantity ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-emerald-500'"
                  class="w-full bg-gray-50 border text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:border-transparent transition"
                  placeholder="Ví dụ: 5, 10..."
                />
                <p v-if="editForm.errors.original_quantity" class="text-red-500 text-[11px] font-semibold mt-1">{{ editForm.errors.original_quantity }}</p>
              </div>

              <!-- 5. Đơn vị tính -->
              <div class="space-y-1.5">
                <label class="text-xs font-semibold text-gray-700">Đơn vị tính <span class="text-red-500">*</span></label>
                <input 
                  type="text" 
                  v-model="editForm.unit"
                  :class="editForm.errors.unit ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-emerald-500'"
                  class="w-full bg-gray-50 border text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:border-transparent transition"
                  placeholder="Ví dụ: suất, hộp, cái, kg..."
                />
                <p v-if="editForm.errors.unit" class="text-red-500 text-[11px] font-semibold mt-1">{{ editForm.errors.unit }}</p>
              </div>
            </div>

            <!-- 6. Mô tả chi tiết -->
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-700">Mô tả chi tiết <span class="text-red-500">*</span></label>
              <textarea 
                v-model="editForm.description"
                rows="3"
                :class="editForm.errors.description ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-emerald-500'"
                class="w-full bg-gray-50 border text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:border-transparent transition"
                placeholder="Mô tả tình trạng thực phẩm..."
              ></textarea>
              <p v-if="editForm.errors.description" class="text-red-500 text-[11px] font-semibold mt-1">{{ editForm.errors.description }}</p>
            </div>

            <!-- 7. Hình ảnh minh họa -->
            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-700">Thay đổi hình ảnh thực tế (Để trống nếu giữ nguyên ảnh cũ)</label>
              <input 
                type="file" 
                accept="image/*"
                @change="(e) => editForm.image = e.target.files[0]"
                class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 transition cursor-pointer"
              />
              <p v-if="editForm.errors.image" class="text-red-500 text-[11px] font-semibold mt-1">{{ editForm.errors.image }}</p>
            </div>

            <!-- Nút gửi -->
            <div class="pt-4 flex gap-3">
              <button 
                type="button" 
                @click="isEditModalOpen = false" 
                class="w-1/3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold text-sm py-3 px-4 rounded-xl transition duration-200"
              >
                Hủy bỏ
              </button>
              <button 
                type="submit" 
                class="w-2/3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm py-3 px-4 rounded-xl transition duration-200 shadow-sm shadow-emerald-100"
              >
                Lưu & Kích hoạt tin
              </button>
            </div>

          </form>
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

