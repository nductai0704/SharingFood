<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import ToastMessage from '@/Components/ToastMessage.vue';

const isMobileMenuOpen = ref(false);
const isNotificationOpen = ref(false);
const page = usePage();

const dismissedNotifications = ref(JSON.parse(localStorage.getItem('sf_dismissed_notifications') || '[]'));

const lastOpenedNotificationTime = ref(localStorage.getItem('sf_last_opened_notifications') || '0');

const unreadNotificationsCount = computed(() => {
    return allNotifications.value.filter(item => {
        const itemTime = new Date(item.created_at || item.claim?.created_at).getTime();
        const lastOpenedTime = new Date(lastOpenedNotificationTime.value).getTime();
        return itemTime > lastOpenedTime;
    }).length;
});

const toggleNotification = () => {
    isNotificationOpen.value = !isNotificationOpen.value;
    if (isNotificationOpen.value) {
        const now = new Date().toISOString();
        localStorage.setItem('sf_last_opened_notifications', now);
        lastOpenedNotificationTime.value = now;
    }
};

const notificationContainerRef = ref(null);
const mobileNotificationContainerRef = ref(null);

const handleClickOutside = (event) => {
    if (
        (notificationContainerRef.value && !notificationContainerRef.value.contains(event.target)) &&
        (!mobileNotificationContainerRef.value || !mobileNotificationContainerRef.value.contains(event.target))
    ) {
        isNotificationOpen.value = false;
    }
};

const dismissNotification = (claimId, status) => {
    dismissedNotifications.value.push(claimId + '_' + status);
    localStorage.setItem('sf_dismissed_notifications', JSON.stringify(dismissedNotifications.value));
};

const allNotifications = computed(() => {
    const list = [];
    const userId = page.props.auth.user?.id;
    if (!userId) return [];

    // 1. Từ receivedClaims (Mình là Người Cho)
    if (page.props.auth.receivedClaims) {
        page.props.auth.receivedClaims.forEach(claim => {
            if (dismissedNotifications.value.includes(claim.id + '_' + claim.status)) return;

            if (claim.status === 'pending') {
                list.push({
                    id: claim.id,
                    type: 'incoming_pending',
                    title: 'Yêu cầu nhận mới',
                    message: `👤 ${claim.user?.name} muốn nhận ${claim.quantity} ${claim.food_post?.unit} từ bài viết "${claim.food_post?.title}"`,
                    claim: claim,
                    created_at: claim.created_at
                });
            } else if (claim.status === 'cancelled' && claim.cancelled_by != userId) {
                const actor = claim.cancelled_by === 'system' ? 'Hệ thống' : (claim.user?.name || 'Người nhận');
                list.push({
                    id: claim.id,
                    type: 'incoming_cancelled',
                    title: 'Yêu cầu bị hủy',
                    message: `❌ ${actor} đã hủy yêu cầu nhận từ bài viết "${claim.food_post?.title}" (Lý do: ${claim.cancel_reason || 'Không có lý do'})`,
                    claim: claim,
                    created_at: claim.updated_at || claim.created_at
                });
            }
        });
    }

    // 2. Từ myClaims (Mình là Người Nhận)
    if (page.props.auth.myClaims) {
        page.props.auth.myClaims.forEach(claim => {
            if (dismissedNotifications.value.includes(claim.id + '_' + claim.status)) return;

            if (claim.status === 'approved') {
                list.push({
                    id: claim.id,
                    type: 'outgoing_approved',
                    title: 'Yêu cầu được duyệt',
                    message: `✅ Yêu cầu nhận thực phẩm từ bài viết "${claim.food_post?.title}" đã được người cho phê duyệt! Vui lòng đến nhận hàng.`,
                    claim: claim,
                    created_at: claim.updated_at || claim.created_at
                });
            } else if (claim.status === 'rejected') {
                list.push({
                    id: claim.id,
                    type: 'outgoing_rejected',
                    title: 'Yêu cầu bị từ chối',
                    message: `❌ Yêu cầu nhận thực phẩm từ bài viết "${claim.food_post?.title}" đã bị người cho từ chối (Lý do: ${claim.cancel_reason || 'Không có lý do'}).`,
                    claim: claim,
                    created_at: claim.updated_at || claim.created_at
                });
            } else if (claim.status === 'cancelled' && claim.cancelled_by != userId) {
                const actor = claim.cancelled_by === 'system' ? 'Hệ thống' : 'Người cho';
                list.push({
                    id: claim.id,
                    type: 'outgoing_cancelled',
                    title: 'Giao dịch bị hủy',
                    message: `❌ ${actor} đã hủy giao dịch đối với bài viết "${claim.food_post?.title}" (Lý do: ${claim.cancel_reason || 'Không có lý do'})`,
                    claim: claim,
                    created_at: claim.updated_at || claim.created_at
                });
            }
        });
    }

    // 3. Database Notifications (ví dụ: NewDonationNotification, TrustScoreRewarded)
    if (page.props.auth.notifications) {
        page.props.auth.notifications.forEach(n => {
            if (n.read_at) return; // Hide read notifications, or keep them if you want. Let's keep unread only.
            
            let title = 'Thông báo';
            if (n.data.type === 'new_donation') title = 'Có lượt quyên góp mới';
            else if (n.data.type === 'trust_score_rewarded') title = 'Cộng điểm uy tín';
            
            list.push({
                id: n.id,
                type: n.data.type,
                title: title,
                message: n.data.message,
                url: n.data.url,
                created_at: n.created_at,
                is_db_notification: true
            });
        });
    }

    return list.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
});

let notificationPollInterval = null;
const startNotificationPolling = () => {
    if (notificationPollInterval) return;
    notificationPollInterval = setInterval(() => {
        router.reload({
            only: ['auth'],
            preserveState: true,
            preserveScroll: true
        });
    }, 5000); // 5 giây/lần
};

const stopNotificationPolling = () => {
    if (notificationPollInterval) {
        clearInterval(notificationPollInterval);
        notificationPollInterval = null;
    }
};

onMounted(() => {
    startNotificationPolling();
    document.addEventListener('click', handleClickOutside);

    // Lắng nghe thay đổi từ các tab khác (đồng bộ chuông)
    window.addEventListener('storage', handleStorageEvent);

    // Lắng nghe real-time claim status updates qua Reverb
    const userId = page.props.auth.user?.id;
    if (userId && window.Echo) {
        window.Echo.private(`user.${userId}`)
            .listen('.claim.status.updated', () => {
                router.reload({
                    only: ['auth'],
                    preserveState: true,
                    preserveScroll: true
                });
            });
    }
});

onUnmounted(() => {
    stopNotificationPolling();
    document.removeEventListener('click', handleClickOutside);
    window.removeEventListener('storage', handleStorageEvent);

    const userId = page.props.auth.user?.id;
    if (userId && window.Echo) {
        window.Echo.leave(`user.${userId}`);
    }
});

const giverCancelReasons = [
    'Thực phẩm đã hỏng/hết hạn thực tế',
    'Hết hàng/Số lượng thực tế không đủ',
    'Người nhận không đến lấy đúng hẹn',
    'Thông tin người nhận không chính xác',
    'Lý do khác'
];

const showGiverCancelModal = ref(false);
const selectedGiverClaimId = ref(null);
const isProcessing = ref(false);
const giverCancelForm = ref({
    reason: 'Thực phẩm đã hỏng/hết hạn thực tế',
    status: 'rejected'
});

const openGiverCancelModal = (claimId, status) => {
    selectedGiverClaimId.value = claimId;
    giverCancelForm.value.reason = 'Thực phẩm đã hỏng/hết hạn thực tế';
    giverCancelForm.value.status = status;
    showGiverCancelModal.value = true;
};

const submitGiverCancel = () => {
    if (selectedGiverClaimId.value) {
        isProcessing.value = true;
        if (giverCancelForm.value.status === 'rejected') {
            router.post(route('food-claims.status', selectedGiverClaimId.value), {
                status: 'rejected',
                cancel_reason: giverCancelForm.value.reason
            }, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    showGiverCancelModal.value = false;
                    selectedGiverClaimId.value = null;
                    window.dispatchEvent(new CustomEvent('claim-status-updated'));
                },
                onFinish: () => {
                    isProcessing.value = false;
                }
            });
        } else {
            router.post(route('food-claims.cancel', selectedGiverClaimId.value), {
                cancel_reason: giverCancelForm.value.reason
            }, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    showGiverCancelModal.value = false;
                    selectedGiverClaimId.value = null;
                    window.dispatchEvent(new CustomEvent('claim-status-updated'));
                },
                onFinish: () => {
                    isProcessing.value = false;
                }
            });
        }
    }
};

const handleUpdateClaimStatus = (claimId, status) => {
    router.post(route('food-claims.status', claimId), { status }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            window.dispatchEvent(new CustomEvent('claim-status-updated'));
        }
    });
};

const handleStorageEvent = (e) => {
    if (e.key === 'sf_last_opened_notifications') {
        lastOpenedNotificationTime.value = e.newValue;
    } else if (e.key === 'sf_dismissed_notifications') {
        dismissedNotifications.value = JSON.parse(e.newValue || '[]');
    }
};

const handleDbNotificationClick = (notification) => {
    isNotificationOpen.value = false;
    router.post(route('notifications.read', notification.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (notification.url) {
                router.visit(notification.url);
            }
        }
    });
};
</script>

<template>
  <div class="min-h-screen bg-gray-50 text-gray-800 font-sans">
    
    <!-- Admin Top Navbar (Only for Admin Role) -->
    <nav v-if="$page.props.auth.user && $page.props.auth.user.role === 'admin'" class="bg-slate-900 text-white shadow-md sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <Link :href="route('dashboard')" class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-emerald-500 rounded-lg flex items-center justify-center text-white font-bold">S</div>
                    <span class="text-lg font-bold tracking-tight">ShareFood <span class="text-emerald-400">AdminPanel</span></span>
                </Link>
                <div class="flex items-center space-x-4">
                    <Link :href="route('profile.edit')" class="flex flex-col text-right hidden sm:flex hover:text-emerald-400 text-left transition">
                        <span class="text-sm font-semibold text-white">Ban Quản Trị</span>
                        <span class="text-[10px] text-slate-400">Hồ sơ: {{ $page.props.auth.user.name }}</span>
                    </Link>
                    
                    <!-- Nút Chuông Thông Báo (Shortcut về Dashboard) -->
                    <Link :href="route('dashboard')" class="relative p-2 text-slate-300 hover:text-white transition rounded-full hover:bg-slate-800 focus:outline-none" title="Về Dashboard xem thông báo">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    </Link>
                    
                    <Link :href="route('logout')" method="post" as="button" class="text-xs bg-slate-800 hover:bg-red-600 px-3 py-2 rounded-xl transition duration-200">Đăng xuất</Link>
                </div>
            </div>
        </div>
    </nav>

    <!-- Custom Premium Navbar (For normal users) -->
    <nav v-else class="bg-white border-b border-gray-100 sticky top-0 z-50 shadow-sm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <!-- Logo -->
          <Link :href="$page.props.auth.user && $page.props.auth.user.role === 'charity' ? route('charity.dashboard') : '/'" class="flex items-center space-x-3 group">
            <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-md shadow-emerald-200 group-hover:scale-105 transition-transform duration-200">S</div>
            <span class="text-xl font-bold text-gray-950 tracking-tight group-hover:text-emerald-600 transition-colors duration-200">ShareFood<span class="text-emerald-600">.vn</span></span>
            <span v-if="$page.props.auth.user && $page.props.auth.user.role === 'charity'" class="hidden sm:inline-block bg-emerald-100 text-emerald-800 text-[10px] uppercase tracking-wider px-2.5 py-1 rounded-full font-bold border border-emerald-200">
              Tổ Chức Từ Thiện
            </span>
          </Link>

          <!-- Desktop menu -->
          <div class="hidden md:flex items-center space-x-6">
            <Link :href="route('dashboard')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Trang chủ</Link>
            <Link :href="route('food-posts.index')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Thực phẩm</Link>
            <Link v-if="$page.props.auth.user && $page.props.auth.user.role === 'charity'" :href="route('charity.campaigns')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Chiến dịch từ thiện</Link>
            <div class="h-8 w-px bg-gray-200"></div>
            
            <div v-if="$page.props.auth.user" class="flex items-center space-x-4">
              
              <Link :href="route('profile.edit')" class="flex items-center space-x-2 text-gray-700 hover:text-emerald-600 transition">
                <img 
                  v-if="$page.props.auth.user.avatar" 
                  :src="$page.props.auth.user.avatar" 
                  class="w-8 h-8 rounded-full object-cover border border-emerald-100 shadow-sm"
                  alt="Avatar"
                />
                <div v-else class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 font-semibold text-sm">
                  {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                </div>
                <span class="text-sm font-medium flex items-center gap-2">
                  {{ $page.props.auth.user.name }}
                  <span :class="$page.props.auth.user.trust_score < 70 ? 'text-red-600 bg-red-50 border-red-200' : 'text-amber-600 bg-amber-50 border-amber-200'" class="text-[10px] font-bold px-1.5 py-0.5 rounded-full border shadow-sm flex items-center gap-1" title="Điểm uy tín">
                    <svg :class="$page.props.auth.user.trust_score < 70 ? 'text-red-500' : 'text-amber-500'" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                    {{ $page.props.auth.user.trust_score }}
                  </span>
                </span>
              </Link>
              <Link :href="route('logout')" method="post" as="button" class="text-sm text-red-600 hover:text-red-700 font-semibold transition">
                Đăng xuất
              </Link>

              <!-- Notification Bell Icon (Next to logout) -->
              <div class="relative" ref="notificationContainerRef">
                <button 
                  @click="toggleNotification" 
                  class="relative p-2 text-gray-500 hover:text-emerald-600 focus:outline-none transition rounded-full hover:bg-gray-50 cursor-pointer"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                  </svg>
                  <!-- Badge số lượng thông báo chưa xem -->
                  <span 
                    v-if="unreadNotificationsCount > 0" 
                    class="absolute top-1 right-1 bg-red-500 text-white text-[8px] font-bold w-4 h-4 rounded-full flex items-center justify-center border border-white"
                  >
                    {{ unreadNotificationsCount }}
                  </span>
                </button>

                <!-- Dropdown thông báo -->
                <div 
                  v-if="isNotificationOpen" 
                  class="absolute right-0 mt-2 w-80 bg-white border border-gray-100 rounded-2xl shadow-xl z-50 overflow-hidden animate-in fade-in slide-in-from-top-2 duration-150"
                >
                  <div class="p-3 border-b border-gray-50 flex justify-between items-center bg-gray-50/50">
                    <span class="text-xs font-bold text-gray-800">Thông báo hệ thống</span>
                    <span class="text-[10px] text-gray-400 font-medium">{{ allNotifications.length }} thông báo mới</span>
                  </div>
                  <div class="max-h-72 overflow-y-auto divide-y divide-gray-50">
                    <div v-if="allNotifications.length === 0" class="p-4 text-center text-gray-400 text-xs">
                      Không có thông báo nào.
                    </div>
                    <div 
                      v-else 
                      v-for="item in allNotifications" 
                      :key="item.type + '-' + item.id" 
                      class="p-3 hover:bg-gray-50/50 space-y-2 transition text-xs"
                    >
                      <div class="flex justify-between items-start gap-1">
                        <div class="text-gray-700 leading-normal text-left space-y-1 w-full">
                          <!-- Tiêu đề & Loại thông báo -->
                          <div class="flex justify-between items-center">
                            <span class="font-bold text-[10px] uppercase tracking-wider text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-100" v-if="item.type === 'incoming_pending'">Yêu cầu mới</span>
                            <span class="font-bold text-[10px] uppercase tracking-wider text-red-700 bg-red-50 px-1.5 py-0.5 rounded border border-red-100" v-else-if="item.type === 'incoming_cancelled' || item.type === 'outgoing_cancelled'">Đã huỷ</span>
                            <span class="font-bold text-[10px] uppercase tracking-wider text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-100" v-else-if="item.type === 'outgoing_approved'">Đã duyệt</span>
                            <span class="font-bold text-[10px] uppercase tracking-wider text-red-700 bg-red-50 px-1.5 py-0.5 rounded border border-red-100" v-else-if="item.type === 'outgoing_rejected'">Từ chối</span>
                            <span class="font-bold text-[10px] uppercase tracking-wider text-purple-700 bg-purple-50 px-1.5 py-0.5 rounded border border-purple-100" v-else-if="item.type === 'new_donation'">Quyên góp mới</span>
                            <span class="font-bold text-[10px] uppercase tracking-wider text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-100" v-else-if="item.type === 'trust_score_rewarded'">⭐ +10 Uy tín</span>
                          </div>

                          <p v-if="item.type === 'incoming_pending'" class="text-gray-800 text-[11px] leading-relaxed">
                              Muốn nhận <span class="font-bold text-emerald-600 bg-emerald-50 px-1 py-0.5 rounded border border-emerald-100">{{ item.claim.quantity }} {{ item.claim.food_post?.unit }}</span> từ bài viết <span class="font-bold text-blue-700 bg-blue-50 px-1 py-0.5 rounded border border-blue-100">"{{ item.claim.food_post?.title }}"</span>
                          </p>
                          <p v-else class="text-gray-800 text-xs">{{ item.message }}</p>

                          <!-- Hiển thị phương thức lấy hàng cho Incoming Pending -->
                          <div v-if="item.type === 'incoming_pending'" class="text-[10px] text-gray-500 bg-gray-50 p-1.5 rounded border border-gray-100/50 mt-1">
                            <div class="flex items-center gap-1 mb-1">
                                <span class="font-bold text-gray-700">Người xin: {{ item.claim.user?.name }}</span>
                                <span v-if="item.claim.user" :class="item.claim.user.trust_score < 70 ? 'text-red-600 bg-red-50 border-red-200' : 'text-amber-600 bg-amber-50 border-amber-200'" class="text-[9px] font-bold px-1.5 py-0.5 rounded-full border flex items-center gap-0.5" title="Điểm uy tín">
                                  ⭐ {{ item.claim.user.trust_score }}
                                </span>
                            </div>
                            📦 <b>Cách nhận:</b>
                            <span v-if="item.claim.shipping_method === 'self_pickup'" class="text-blue-600 font-semibold ml-1">Tự đến lấy</span>
                            <span v-else-if="item.claim.shipping_method === 'relative_pickup'" class="text-indigo-600 font-semibold ml-1">Nhờ người thân lấy ({{ item.claim.pickup_contact_name }})</span>
                            <span v-else-if="item.claim.shipping_method === 'delivery_service'" class="text-orange-600 font-semibold ml-1">Giao hàng ({{ item.claim.delivery_service_company }})</span>
                          </div>
                        </div>
                      </div>
                      <div class="flex justify-between items-center text-[10px] text-gray-400">
                        <span>{{ new Date(item.created_at).toLocaleString('vi-VN', {hour: '2-digit', minute:'2-digit', day:'2-digit', month:'2-digit', year:'numeric'}) }}</span>
                      </div>
                      <!-- Các nút thao tác -->
                      <div class="flex items-center gap-2 pt-1">
                        <!-- Yêu cầu mới cần Duyệt / Từ chối -->
                        <template v-if="item.type === 'incoming_pending'">
                          <button 
                            @click.stop="handleUpdateClaimStatus(item.id, 'approved')" 
                            class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] py-1 rounded-lg transition text-center cursor-pointer shadow-sm shadow-emerald-100 hover:shadow-emerald-200"
                          >
                            Duyệt
                          </button>
                          <button 
                            @click.stop="openGiverCancelModal(item.id, 'rejected')" 
                            class="flex-1 bg-gray-50 hover:bg-red-50 text-gray-600 hover:text-red-600 font-semibold text-[10px] py-1 rounded-lg border border-gray-200 hover:border-red-100 transition text-center cursor-pointer"
                          >
                            Từ chối
                          </button>
                        </template>
                        <!-- Database Notifications -->
                        <template v-else-if="item.is_db_notification">
                          <button 
                            @click.stop="handleDbNotificationClick(item)" 
                            class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold text-[10px] py-1.5 rounded-lg transition text-center cursor-pointer shadow-sm"
                          >
                            {{ item.url ? 'Xem chi tiết' : 'Đã hiểu' }}
                          </button>
                        </template>
                        <!-- Các thông báo khác chỉ cần nút Đóng (Dismiss) -->
                        <template v-else>
                          <button 
                            @click.stop="dismissNotification(item.id, item.claim.status)" 
                            class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-[10px] py-1.5 rounded-lg transition text-center cursor-pointer"
                          >
                            Đóng thông báo
                          </button>
                        </template>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Mobile menu button & QR Scan -->
          <div class="flex items-center md:hidden space-x-2">
            
            <!-- Mobile Notification Bell -->
            <div v-if="$page.props.auth.user" class="relative" ref="mobileNotificationContainerRef">
                <button 
                  @click="toggleNotification" 
                  class="relative p-2 text-gray-500 hover:text-emerald-600 focus:outline-none transition rounded-full hover:bg-gray-50 cursor-pointer"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                  </svg>
                  <span 
                    v-if="unreadNotificationsCount > 0" 
                    class="absolute top-1 right-1 bg-red-500 text-white text-[8px] font-bold w-4 h-4 rounded-full flex items-center justify-center border border-white"
                  >
                    {{ unreadNotificationsCount }}
                  </span>
                </button>
                <!-- Mobile Dropdown thông báo -->
                <div 
                  v-if="isNotificationOpen" 
                  class="absolute right-0 mt-2 w-72 bg-white border border-gray-100 rounded-2xl shadow-xl z-50 overflow-hidden"
                >
                  <div class="p-3 border-b border-gray-50 flex justify-between items-center bg-gray-50/50">
                    <span class="text-xs font-bold text-gray-800">Thông báo hệ thống</span>
                    <span class="text-[10px] text-gray-400 font-medium">{{ allNotifications.length }} thông báo mới</span>
                  </div>
                  <div class="max-h-60 overflow-y-auto divide-y divide-gray-50">
                    <div v-if="allNotifications.length === 0" class="p-4 text-center text-gray-400 text-xs">
                      Không có thông báo nào.
                    </div>
                    <div v-else v-for="item in allNotifications" :key="item.type + '-' + item.id" class="p-3 hover:bg-gray-50/50 space-y-2 transition text-xs">
                      <div class="text-gray-700 leading-normal text-left space-y-1">
                        <div class="flex justify-between items-center mb-1">
                          <span class="font-bold text-[8px] uppercase tracking-wider text-emerald-700 bg-emerald-50 px-1 py-0.5 rounded" v-if="item.type === 'incoming_pending'">Yêu cầu mới</span>
                          <span class="font-bold text-[8px] uppercase tracking-wider text-red-700 bg-red-50 px-1 py-0.5 rounded" v-else-if="item.type === 'incoming_cancelled' || item.type === 'outgoing_cancelled'">Đã huỷ</span>
                          <span class="font-bold text-[8px] uppercase tracking-wider text-emerald-700 bg-emerald-50 px-1 py-0.5 rounded" v-else-if="item.type === 'outgoing_approved'">Đã duyệt</span>
                          <span class="font-bold text-[8px] uppercase tracking-wider text-red-700 bg-red-50 px-1 py-0.5 rounded" v-else-if="item.type === 'outgoing_rejected'">Từ chối</span>
                          <span class="font-bold text-[8px] uppercase tracking-wider text-purple-700 bg-purple-50 px-1 py-0.5 rounded" v-else-if="item.type === 'new_donation'">Quyên góp mới</span>
                          <span class="font-bold text-[8px] uppercase tracking-wider text-amber-700 bg-amber-50 px-1 py-0.5 rounded" v-else-if="item.type === 'trust_score_rewarded'">⭐ +10 Uy tín</span>
                        </div>
                        <p v-if="item.type === 'incoming_pending'" class="text-gray-800 text-[11px] leading-relaxed">
                            Muốn nhận <span class="font-bold text-emerald-600 bg-emerald-50 px-1 py-0.5 rounded border border-emerald-100">{{ item.claim.quantity }} {{ item.claim.food_post?.unit }}</span> từ bài viết <span class="font-bold text-blue-700 bg-blue-50 px-1 py-0.5 rounded border border-blue-100">"{{ item.claim.food_post?.title }}"</span>
                        </p>
                        <p v-else class="text-gray-800 text-[11px]">{{ item.message }}</p>
                        <!-- Hiển thị phương thức lấy hàng cho Incoming Pending -->
                        <div v-if="item.type === 'incoming_pending'" class="text-[10px] text-gray-500 bg-gray-50 p-1.5 rounded border border-gray-100/50 mt-1">
                          <div class="flex items-center gap-1 mb-1">
                              <span class="font-bold text-gray-700">Người xin: {{ item.claim.user?.name }}</span>
                              <span v-if="item.claim.user" :class="item.claim.user.trust_score < 70 ? 'text-red-600 bg-red-50 border-red-200' : 'text-amber-600 bg-amber-50 border-amber-200'" class="text-[9px] font-bold px-1.5 py-0.5 rounded-full border flex items-center gap-0.5" title="Điểm uy tín">
                                ⭐ {{ item.claim.user.trust_score }}
                              </span>
                          </div>
                          📦 <b>Cách nhận:</b>
                          <span v-if="item.claim.shipping_method === 'self_pickup'" class="text-blue-600 font-semibold ml-1">Tự đến lấy</span>
                          <span v-else-if="item.claim.shipping_method === 'relative_pickup'" class="text-indigo-600 font-semibold ml-1">Nhờ người thân lấy ({{ item.claim.pickup_contact_name }})</span>
                          <span v-else-if="item.claim.shipping_method === 'delivery_service'" class="text-orange-600 font-semibold ml-1">Giao hàng ({{ item.claim.delivery_service_company }})</span>
                        </div>
                      </div>
                      <div class="flex items-center gap-2 pt-1">
                        <!-- Yêu cầu mới cần Duyệt / Từ chối -->
                        <template v-if="item.type === 'incoming_pending'">
                          <button @click.stop="handleUpdateClaimStatus(item.id, 'approved')" class="flex-1 bg-emerald-600 text-white font-bold text-[10px] py-1 rounded-lg">Duyệt</button>
                          <button @click.stop="openGiverCancelModal(item.id, 'rejected')" class="flex-1 bg-red-50 text-red-600 font-bold text-[10px] py-1 rounded-lg border border-red-100">Từ chối</button>
                        </template>
                        <!-- Database Notifications -->
                        <template v-else-if="item.is_db_notification">
                          <button @click.stop="handleDbNotificationClick(item)" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold text-[10px] py-1 rounded-lg">{{ item.url ? 'Xem chi tiết' : 'Đã hiểu' }}</button>
                        </template>
                        <!-- Các thông báo khác chỉ cần nút Đóng (Dismiss) -->
                        <template v-else>
                          <button @click.stop="dismissNotification(item.id, item.claim.status)" class="w-full bg-gray-100 text-gray-700 font-bold text-[10px] py-1 rounded-lg">Đóng</button>
                        </template>
                      </div>
                    </div>
                  </div>
                </div>
            </div>

            <!-- Nút quét QR Mobile giả lập -->
            <Link v-if="$page.props.auth.user" :href="route('qr.scanner')" class="text-emerald-600 hover:text-emerald-700 bg-emerald-50 focus:outline-none p-2 rounded-lg border border-emerald-100 transition shadow-sm">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
              </svg>
            </Link>

            <button @click="isMobileMenuOpen = !isMobileMenuOpen" class="text-gray-500 hover:text-emerald-600 focus:outline-none p-2 rounded-lg bg-gray-50 border border-gray-100">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path v-if="!isMobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Mobile dropdown menu -->
      <div v-if="isMobileMenuOpen" class="md:hidden bg-white border-t border-gray-100 p-4 space-y-4 shadow-inner">
        <div class="flex flex-col space-y-3">
          <Link :href="route('dashboard')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Trang chủ</Link>
          <Link :href="route('food-posts.index')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Thực phẩm</Link>
          <Link v-if="$page.props.auth.user && $page.props.auth.user.role === 'charity'" :href="route('charity.campaigns')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Chiến dịch từ thiện</Link>
        </div>
        
        <div class="h-px bg-gray-100"></div>
        
        <div v-if="$page.props.auth.user" class="flex flex-col space-y-3">
          <Link :href="route('profile.edit')" class="flex items-center space-x-2 text-gray-700 hover:text-emerald-600 transition">
            <img 
              v-if="$page.props.auth.user.avatar" 
              :src="$page.props.auth.user.avatar" 
              class="w-8 h-8 rounded-full object-cover border border-emerald-100 shadow-sm"
              alt="Avatar"
            />
            <div v-else class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 font-semibold text-sm">
              {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
            </div>
            <span class="text-sm font-medium flex items-center gap-2">
              {{ $page.props.auth.user.name }}
              <span :class="$page.props.auth.user.trust_score < 70 ? 'text-red-600 bg-red-50 border-red-200' : 'text-amber-600 bg-amber-50 border-amber-200'" class="text-[10px] font-bold px-1.5 py-0.5 rounded-full border shadow-sm flex items-center gap-1" title="Điểm uy tín">
                <svg :class="$page.props.auth.user.trust_score < 70 ? 'text-red-500' : 'text-amber-500'" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                {{ $page.props.auth.user.trust_score }}
              </span>
            </span>
          </Link>
          <Link :href="route('logout')" method="post" as="button" class="text-sm text-left text-red-600 hover:text-red-700 font-semibold transition">
            Đăng xuất
          </Link>
        </div>
      </div>
    </nav>

    <!-- Page Heading -->
    <header class="bg-white border-b border-gray-100 shadow-sm" v-if="$slots.header">
      <div class="max-w-7xl mx-auto px-4 py-5 sm:px-6 lg:px-8">
        <slot name="header" />
      </div>
    </header>

    <!-- Page Content -->
    <main>
      <slot />
    </main>
  </div>

  <!-- MODAL TỪ CHỐI / HỦY YÊU CẦU (DÀNH CHO NGƯỜI CHO) -->
  <div 
    v-if="showGiverCancelModal" 
    class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-in fade-in duration-200"
  >
      <div class="bg-white rounded-3xl w-full max-w-sm p-6 shadow-2xl animate-in zoom-in-95 duration-200 border border-gray-100 text-left">
          <h3 class="text-lg font-extrabold text-gray-900 mb-2">
            {{ giverCancelForm.status === 'rejected' ? 'Từ chối yêu cầu' : 'Hủy giao dịch' }}
          </h3>
          <p class="text-xs text-gray-500 mb-4">Vui lòng chọn lý do để người nhận biết thông tin:</p>
          
          <div class="space-y-2 mb-6">
              <div 
                  v-for="opt in giverCancelReasons" 
                  :key="opt"
                  @click="giverCancelForm.reason = opt"
                  class="p-3.5 rounded-2xl border text-xs font-semibold transition cursor-pointer flex items-center justify-between"
                  :class="giverCancelForm.reason === opt ? 'border-red-500 bg-red-50/50 text-red-700 shadow-sm shadow-red-100' : 'border-gray-100 bg-gray-50 hover:bg-gray-100 text-gray-700'"
              >
                  <span>{{ opt }}</span>
                  <span v-if="giverCancelForm.reason === opt" class="text-red-500 font-bold">✓</span>
              </div>
          </div>
          
          <div class="flex items-center gap-3">
              <button @click="showGiverCancelModal = false" :disabled="isProcessing" class="flex-1 px-4 py-2.5 text-xs font-bold text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition text-center disabled:opacity-50">Đóng</button>
              <button @click="submitGiverCancel" :disabled="isProcessing" class="flex-1 px-4 py-2.5 text-xs font-bold text-white bg-red-600 rounded-xl hover:bg-red-700 shadow-md shadow-red-500/30 transition text-center disabled:opacity-50 flex justify-center items-center gap-2">
                <svg v-if="isProcessing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span>{{ isProcessing ? 'Đang xử lý...' : 'Xác nhận' }}</span>
              </button>
          </div>
      </div>
  </div>
  
  <ToastMessage />
</template>
