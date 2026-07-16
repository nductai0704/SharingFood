<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminCharts from './Partials/AdminCharts.vue';
import ToastMessage from '@/Components/ToastMessage.vue';

// Nhận dữ liệu truyền từ Laravel Controller sang thông qua Props
const props = defineProps({
    stats: Object,              // Chứa các số liệu tổng hợp thống kê
    chartData: Object,          // Dữ liệu cho các biểu đồ
    recentActivities: Array,    // Danh sách 5 hoạt động gần nhất
    users: Array,              // Danh sách toàn bộ người dùng và tài liệu minh chứng
    activeCampaigns: Array,    // Danh sách chiến dịch từ thiện đã duyệt 'active'
    pendingCampaigns: Array,   // Danh sách chiến dịch từ thiện chờ duyệt 'pending'
    systemLogs: Array,         // Danh sách nhật ký hệ thống
    reports: Array,            // Danh sách báo cáo vi phạm
});

// State quản lý Tab đang hiển thị tích cực
const activeTab = ref('overview'); 
const activeReportTab = ref('personal');

// State quản lý bộ lọc khoảng thời gian
const filterPeriod = ref(new URLSearchParams(window.location.search).get('period') || '7days');
const customStartDate = ref(new URLSearchParams(window.location.search).get('start_date') || '');
const customEndDate = ref(new URLSearchParams(window.location.search).get('end_date') || '');
const showFilterDropdown = ref(false);

const periodLabels = {
    'today': 'Hôm nay',
    '7days': '7 ngày qua',
    '30days': '30 ngày qua',
    'all': 'Tất cả thời gian',
    'custom': 'Tùy chỉnh khoảng thời gian...'
};

const selectPeriod = (val) => {
    filterPeriod.value = val;
    showFilterDropdown.value = false;
    updateFilter();
};

// Cập nhật URL và lấy dữ liệu mới khi thay đổi bộ lọc
const updateFilter = () => {
    // Không tự động gửi nếu chọn custom mà chưa điền ngày
    if (filterPeriod.value === 'custom' && (!customStartDate.value || !customEndDate.value)) {
        return; 
    }

    let params = { period: filterPeriod.value };
    if (filterPeriod.value === 'custom') {
        params.start_date = customStartDate.value;
        params.end_date = customEndDate.value;
    }

    router.get(route('admin.dashboard'), params, { 
        preserveState: true, 
        replace: true 
    });
};

// State hiển thị Dropdown thông báo
const showNotifications = ref(false);

// State lưu thông tin Tổ chức đang được chọn để xem hồ sơ pháp lý (Modal)
const selectedCharity = ref(null);

// State lưu thông tin Chiến dịch đang được chọn để xem chi tiết (Modal)
const selectedCampaign = ref(null);

// Lấy danh sách các Tổ chức từ thiện đang chờ duyệt
const pendingCharities = computed(() => {
    return props.users.filter(u => u.role === 'charity' && u.status === 'pending');
});

const searchReportQuery = ref('');

const removeAccents = (str) => {
    return str ? str.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase() : '';
};

const personalReports = computed(() => {
    let reports = props.reports?.filter(r => r.reported_user && r.reported_user.role !== 'charity') || [];
    if (searchReportQuery.value) {
        const query = removeAccents(searchReportQuery.value);
        reports = reports.filter(r => 
            removeAccents(r.reporter?.name).includes(query) ||
            removeAccents(r.reported_user?.name).includes(query) ||
            removeAccents(r.reason).includes(query) ||
            removeAccents(r.details).includes(query)
        );
    }
    return reports;
});

const charityReports = computed(() => {
    let reports = props.reports?.filter(r => r.reported_user && r.reported_user.role === 'charity') || [];
    if (searchReportQuery.value) {
        const query = removeAccents(searchReportQuery.value);
        reports = reports.filter(r => 
            removeAccents(r.reporter?.name).includes(query) ||
            removeAccents(r.reported_user?.name).includes(query) ||
            removeAccents(r.reason).includes(query) ||
            removeAccents(r.details).includes(query)
        );
    }
    return reports;
});

watch(searchReportQuery, () => {
    if (searchReportQuery.value) {
        // Nếu đang ở tab cá nhân nhưng không có kết quả, trong khi tab tổ chức có kết quả
        if (activeReportTab.value === 'personal' && personalReports.value.length === 0 && charityReports.value.length > 0) {
            activeReportTab.value = 'charity';
        } 
        // Ngược lại, nếu đang ở tab tổ chức nhưng không có kết quả, trong khi tab cá nhân có kết quả
        else if (activeReportTab.value === 'charity' && charityReports.value.length === 0 && personalReports.value.length > 0) {
            activeReportTab.value = 'personal';
        }
    }
});

const searchQuery = ref('');

const filteredUsers = computed(() => {
    let result = props.users.filter(u => !(u.role === 'charity' && u.status === 'pending'));
    
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(u => 
            u.name.toLowerCase().includes(query) || 
            u.email.toLowerCase().includes(query) ||
            (u.phone && u.phone.toLowerCase().includes(query))
        );
    }
    return result;
});

// Tổng số lượng thông báo
const totalNotifications = computed(() => {
    return pendingCharities.value.length + (props.pendingCampaigns?.length || 0);
});

// Hàm chuyển sang trang Người dùng khi bấm vào thông báo
const goToUsersTab = () => {
    activeTab.value = 'users';
    showNotifications.value = false; // Ẩn dropdown đi
};

// Hàm chuyển sang trang Kiểm duyệt chiến dịch
const goToModerationTab = () => {
    activeTab.value = 'moderation';
    showNotifications.value = false; // Ẩn dropdown đi
};

// --- CÁC HÀM XỬ LÝ SỰ KIỆN TƯƠNG TÁC QUA INERTIA ROUTER ---

// 1. Phê duyệt hoặc từ chối cấp quyền hoạt động cho Tổ chức từ thiện
const verifyCharity = (userId, status) => {
    if (confirm(`Bạn có chắc chắn muốn chuyển trạng thái tài khoản này thành ${status === 'verified' ? 'Đã xác thực' : 'Từ chối'}?`)) {
        router.post(route('admin.users.verify', userId), { status }, {
            onSuccess: () => selectedCharity.value = null
        });
    }
};

// 2. Khóa hoặc Mở khóa tài khoản người dùng vi phạm tiêu chuẩn
const toggleUserStatus = (userId, currentStatus) => {
    const nextStatus = currentStatus === 'banned' ? 'verified' : 'banned';
    if (confirm(`Bạn có chắc chắn muốn ${nextStatus === 'banned' ? 'KHÓA' : 'MỞ KHÓA'} tài khoản này?`)) {
        router.post(route('admin.users.toggle-ban', userId), { status: nextStatus });
    }
};

// 3. Xử lý bài viết bị AI gắn cờ (Giữ lại hoặc Ẩn đi)
const moderatePost = (postId, action) => {
    // action: 'safe' (Bỏ gắn cờ, cho hiển thị) hoặc 'hidden' (Ẩn bài viết)
    if (confirm(`Xác nhận xử lý bài đăng: ${action === 'safe' ? 'An toàn' : 'Ẩn nội dung'}?`)) {
        router.post(route('admin.posts.moderate', postId), { action });
    }
};

// 4. Phê duyệt hoặc hủy bỏ chiến dịch quyên góp lớn trước khi xuất bản
const moderateCampaign = (campaignId, status) => {
    // status: 'active' (Cho phép chạy) hoặc 'rejected' (Từ chối)
    if (confirm(`Xác nhận thay đổi trạng thái chiến dịch thành: ${status === 'active' ? 'Hoạt động' : 'Từ chối'}?`)) {
        router.post(route('admin.campaigns.moderate', campaignId), { status });
    }
};

const getRoleLabel = (role) => {
    const labels = {
        admin: 'Admin',
        charity: 'Tổ chức từ thiện',
        personal: 'Cá nhân / Hộ kinh doanh',
    };
    return labels[role] || role;
};

const getStatusLabel = (status) => {
    const labels = {
        pending: 'Chờ duyệt',
        verified: 'Đã duyệt',
        rejected: 'Từ chối',
        banned: 'Bị khóa',
    };
    return labels[status] || status;
};

const selectedReport = ref(null);

const getPendingReportCountForCampaign = (campaignId) => {
    if (!campaignId || !props.reports) return 0;
    return props.reports.filter(r => r.campaign_id === campaignId && r.status === 'pending').length;
};

const resolveReport = (reportId, action) => {
    const actionLabels = {
        dismiss: 'Bỏ qua báo cáo',
        penalize_reporter: 'Phạt người báo cáo (Vu khống)',
        penalize_20: 'Phạt người bị báo cáo -20 điểm',
        penalize_50: 'Phạt người bị báo cáo -50 điểm',
        ban_user: 'Khóa vĩnh viễn người bị báo cáo'
    };
    if (confirm(`Bạn có chắc chắn muốn thực hiện hành động: ${actionLabels[action]}?`)) {
        router.post(route('admin.reports.resolve', reportId), { action }, {
            onSuccess: () => selectedReport.value = null
        });
    }
};
</script>

<template>
    <Head title="Admin Control Panel - ShareFood" />

    <div class="min-h-screen bg-gray-50 text-gray-800 font-sans flex flex-col">
        <nav class="bg-slate-900 text-white shadow-md sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16 items-center">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-emerald-500 rounded-lg flex items-center justify-center text-white font-bold">S</div>
                        <span class="text-lg font-bold tracking-tight">ShareFood <span class="text-emerald-400">AdminPanel</span></span>
                    </div>
                    <div class="flex items-center space-x-4">
                        <!-- Nút quét QR Mobile giả lập -->
                        <Link v-if="$page.props.auth.user" :href="route('qr.scanner')" class="flex items-center md:hidden text-emerald-400 hover:text-emerald-300 bg-slate-800 focus:outline-none p-2 rounded-lg border border-slate-700 transition shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                        </Link>
                        
                        <Link :href="route('profile.edit')" class="flex flex-col text-right hidden sm:flex hover:text-emerald-400 text-left transition">
                            <span class="text-sm font-semibold text-white">Ban Quản Trị</span>
                            <span class="text-[10px] text-slate-400">Hồ sơ: {{ $page.props.auth.user.name }}</span>
                        </Link>
                        
                        <!-- Nút Chuông Thông Báo -->
                        <div class="relative">
                            <button @click="showNotifications = !showNotifications" class="relative p-2 text-slate-300 hover:text-white transition rounded-full hover:bg-slate-800 focus:outline-none">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                                
                                <!-- Chấm đỏ Badge đếm số lượng -->
                                <span v-if="totalNotifications > 0" class="absolute top-1 right-1 flex items-center justify-center w-4 h-4 text-[10px] font-bold text-white bg-red-500 border-2 border-slate-900 rounded-full">
                                    {{ totalNotifications }}
                                </span>
                            </button>

                            <!-- Lớp Overlay trong suốt để bắt sự kiện click ra ngoài -->
                            <div v-if="showNotifications" @click="showNotifications = false" class="fixed inset-0 z-40"></div>

                            <!-- Dropdown thông báo chi tiết khi click -->
                            <div v-if="showNotifications && totalNotifications > 0" class="absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden transition-all z-50">
                                <div class="bg-gray-50 px-4 py-3 border-b border-gray-100 flex justify-between items-center">
                                    <h3 class="text-sm font-bold text-gray-900">Thông báo mới</h3>
                                    <span class="text-xs bg-red-100 text-red-600 font-bold px-2 py-0.5 rounded-full">{{ totalNotifications }}</span>
                                </div>
                                <div class="max-h-64 overflow-y-auto">
                                    
                                    <!-- Thông báo tổ chức mới -->
                                    <div 
                                        v-for="charity in pendingCharities" 
                                        :key="'charity-' + charity.id" 
                                        @click="goToUsersTab"
                                        class="px-4 py-3 border-b border-gray-50 hover:bg-emerald-50 cursor-pointer transition flex gap-3 items-start"
                                    >
                                        <div class="w-8 h-8 rounded-full bg-emerald-100 flex-shrink-0 flex items-center justify-center text-emerald-600 font-bold text-xs mt-0.5">
                                            TC
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-800 font-medium leading-relaxed">
                                                Tổ chức <span class="font-bold text-emerald-600">{{ charity.name }}</span> vừa đăng ký tài khoản và đang chờ xác thực pháp lý.
                                            </p>
                                            <p class="text-[10px] text-gray-400 mt-1">{{ new Date(charity.created_at).toLocaleDateString('vi-VN') }}</p>
                                        </div>
                                    </div>

                                    <!-- Thông báo chiến dịch mới -->
                                    <div 
                                        v-for="cam in pendingCampaigns" 
                                        :key="'cam-' + cam.id" 
                                        @click="goToModerationTab"
                                        class="px-4 py-3 border-b border-gray-50 hover:bg-amber-50 cursor-pointer transition flex gap-3 items-start"
                                    >
                                        <div class="w-8 h-8 rounded-full bg-amber-100 flex-shrink-0 flex items-center justify-center text-amber-600 font-bold text-xs mt-0.5">
                                            CD
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-800 font-medium leading-relaxed">
                                                Chiến dịch <span class="font-bold text-amber-600">{{ cam.title }}</span> đang chờ duyệt xuất bản đại chúng.
                                            </p>
                                            <p class="text-[10px] text-gray-400 mt-1">{{ new Date(cam.created_at).toLocaleDateString('vi-VN') }}</p>
                                        </div>
                                    </div>
                                    
                                </div>
                            </div>
                            
                            <!-- Nếu click vào chuông nhưng không có thông báo -->
                            <div v-if="showNotifications && totalNotifications === 0" class="absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden transition-all z-50">
                                <div class="p-6 text-center text-gray-400 text-sm">
                                    Không có thông báo mới nào.
                                </div>
                            </div>
                        </div>

                        <Link :href="route('logout')" method="post" as="button" class="text-xs bg-slate-800 hover:bg-red-600 px-3 py-2 rounded-xl transition duration-200">Đăng xuất</Link>
                    </div>
                </div>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 flex-1 space-y-6">
            
            <div class="bg-white p-2 rounded-2xl shadow-sm border border-gray-200/60 flex flex-wrap gap-2">
                <button 
                    @click="activeTab = 'overview'" 
                    :class="activeTab === 'overview' ? 'bg-slate-900 text-white' : 'text-gray-600 hover:bg-gray-100'"
                    class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200"
                >
                    📊 Thống kê & Tổng quan
                </button>
                <button 
                    @click="activeTab = 'users'" 
                    :class="activeTab === 'users' ? 'bg-slate-900 text-white' : 'text-gray-600 hover:bg-gray-100'"
                    class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200"
                >
                    👥 Người dùng & Xác thực
                </button>
                <button 
                    @click="activeTab = 'moderation'" 
                    :class="activeTab === 'moderation' ? 'bg-slate-900 text-white' : 'text-gray-600 hover:bg-gray-100'"
                    class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200"
                >
                    🛡️ Kiểm duyệt Nội dung
                </button>
                <button 
                    @click="activeTab = 'reports'" 
                    :class="activeTab === 'reports' ? 'bg-slate-900 text-white' : 'text-gray-600 hover:bg-gray-100'"
                    class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200 flex items-center gap-1"
                >
                    🚩 Báo cáo vi phạm
                    <span v-if="reports?.filter(r => r.status === 'pending').length > 0" class="bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full">{{ reports?.filter(r => r.status === 'pending').length }}</span>
                </button>
                <button 
                    @click="activeTab = 'logs'" 
                    :class="activeTab === 'logs' ? 'bg-slate-900 text-white' : 'text-gray-600 hover:bg-gray-100'"
                    class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200"
                >
                    📜 Nhật ký Hệ thống
                </button>
            </div>

            <div v-if="activeTab === 'overview'" class="space-y-6 animate-fade-in">
                
                <!-- BỘ LỌC THỜI GIAN & XUẤT FILE -->
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4 bg-white p-4 rounded-2xl shadow-sm border border-gray-200 mb-2">
                    <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                        <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Dữ liệu hiển thị:</span>
                        
                        <!-- CUSTOM DROPDOWN SELECT -->
                        <div class="relative">
                            <!-- Nút Trigger -->
                            <button @click="showFilterDropdown = !showFilterDropdown" 
                                    class="flex items-center justify-between w-full sm:w-auto bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 text-xs font-bold rounded-xl focus:ring-2 focus:ring-emerald-500 block py-2.5 pl-4 pr-3 min-w-[220px] outline-none transition-all duration-200 shadow-sm">
                                <span>{{ periodLabels[filterPeriod] }}</span>
                                <svg class="w-4 h-4 text-gray-500 transition-transform duration-200" :class="{ 'rotate-180': showFilterDropdown }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </button>
                            
                            <!-- Menu Overlay đóng khi click ngoài -->
                            <div v-if="showFilterDropdown" @click="showFilterDropdown = false" class="fixed inset-0 z-40"></div>
                            
                            <!-- Bảng Menu Dropdown -->
                            <transition enter-active-class="transition ease-out duration-100" enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100" leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
                                <div v-if="showFilterDropdown" class="absolute z-50 mt-2 w-[220px] rounded-xl bg-white shadow-xl border border-gray-100 py-1 origin-top-left">
                                    <button @click="selectPeriod('today')" :class="{'bg-emerald-50 text-emerald-700': filterPeriod === 'today'}" class="w-full text-left px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-emerald-50 hover:text-emerald-700 transition">Hôm nay</button>
                                    <button @click="selectPeriod('7days')" :class="{'bg-emerald-50 text-emerald-700': filterPeriod === '7days'}" class="w-full text-left px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-emerald-50 hover:text-emerald-700 transition">7 ngày qua</button>
                                    <button @click="selectPeriod('30days')" :class="{'bg-emerald-50 text-emerald-700': filterPeriod === '30days'}" class="w-full text-left px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-emerald-50 hover:text-emerald-700 transition">30 ngày qua</button>
                                    <button @click="selectPeriod('all')" :class="{'bg-emerald-50 text-emerald-700': filterPeriod === 'all'}" class="w-full text-left px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-emerald-50 hover:text-emerald-700 transition">Tất cả thời gian</button>
                                    <div class="border-t border-gray-100 my-1"></div>
                                    <button @click="selectPeriod('custom')" :class="{'bg-emerald-50 text-emerald-700': filterPeriod === 'custom'}" class="w-full text-left px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-emerald-50 hover:text-emerald-700 transition">Tùy chỉnh khoảng thời gian...</button>
                                </div>
                            </transition>
                        </div>
                        
                        <!-- Khung chọn ngày tùy chỉnh (Hiện khi chọn Tùy chỉnh) -->
                        <div v-if="filterPeriod === 'custom'" class="flex items-center gap-2 bg-gray-50 p-1.5 rounded-lg border border-gray-200 animate-fade-in shadow-sm">
                            <input type="date" v-model="customStartDate" class="bg-white border border-gray-200 text-gray-700 text-xs font-medium rounded-md focus:ring-emerald-500 focus:border-emerald-500 p-1.5 outline-none" />
                            <span class="text-xs text-gray-400 font-medium">-</span>
                            <input type="date" v-model="customEndDate" class="bg-white border border-gray-200 text-gray-700 text-xs font-medium rounded-md focus:ring-emerald-500 focus:border-emerald-500 p-1.5 outline-none" />
                            <button @click="updateFilter" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-md text-xs font-bold transition shadow-sm ml-1">Áp dụng</button>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <a :href="route('admin.export.pdf', { period: filterPeriod, start_date: filterPeriod === 'custom' ? customStartDate : null, end_date: filterPeriod === 'custom' ? customEndDate : null })" target="_blank" class="flex-1 sm:flex-none flex items-center justify-center gap-2 bg-white border border-gray-200 hover:bg-red-50 hover:border-red-200 hover:text-red-700 text-gray-600 px-4 py-2 rounded-lg text-xs font-bold transition">
                            <svg class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 24 24"><path d="M8.25 10.875a1.406 1.406 0 011.406-1.406h2.25c.775 0 1.406.63 1.406 1.406v2.25c0 .775-.63 1.406-1.406 1.406h-2.25a1.406 1.406 0 01-1.406-1.406v-2.25zM12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zM9.656 7.969h2.25a2.906 2.906 0 012.906 2.906v2.25a2.906 2.906 0 01-2.906 2.906h-2.25a2.906 2.906 0 01-2.906-2.906v-2.25a2.906 2.906 0 012.906-2.906z"></path></svg>
                            Xuất PDF
                        </a>
                        <a :href="route('admin.export.excel', { period: filterPeriod, start_date: filterPeriod === 'custom' ? customStartDate : null, end_date: filterPeriod === 'custom' ? customEndDate : null })" target="_blank" class="flex-1 sm:flex-none flex items-center justify-center gap-2 bg-white border border-gray-200 hover:bg-emerald-50 hover:border-emerald-200 hover:text-emerald-700 text-gray-600 px-4 py-2 rounded-lg text-xs font-bold transition">
                            <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.103 0-2 .897-2 2v14c0 1.103.897 2 2 2h14c1.103 0 2-.897 2-2V5c0-1.103-.897-2-2-2zM9.555 17h-2.11l1.89-4.882L7.545 7h2.11l1.315 3.52c.22.587.394 1.146.52 1.677h.04c.127-.531.32-1.108.58-1.73l1.435-3.467h1.99l-2.07 5.093L15.42 17h-2.09l-1.52-3.84c-.2-.514-.367-1.026-.5-1.536h-.04c-.113.486-.273.993-.48 1.523L9.555 17z"></path></svg>
                            Xuất Excel
                        </a>
                    </div>
                </div>

                <!-- ⚡ CẦN XỬ LÝ GẤP (Chỉ hiện khi có việc) -->
                <div v-if="(pendingCampaigns?.length > 0) || (users?.filter(u => u.trust_score < 40).length > 0) || (stats?.reports_breakdown?.pending_count > 0)" class="mb-4">
                    <h3 class="text-sm font-bold text-gray-900 mb-3 flex items-center gap-2">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                        </span>
                        Cần xử lý gấp
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        
                        <!-- Cảnh báo 1: Báo cáo vi phạm -->
                        <div v-if="stats?.reports_breakdown?.pending_count > 0" class="bg-red-50 border border-red-200 rounded-2xl p-4 shadow-sm relative overflow-hidden group">
                            <div class="absolute top-0 right-0 p-2 opacity-10 group-hover:scale-110 transition duration-300">
                                <svg class="w-16 h-16 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                            </div>
                            <div class="relative z-10 flex justify-between items-start">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <div class="bg-red-100 p-2 rounded-lg border border-red-200 shadow-inner">
                                            <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                        </div>
                                        <h4 class="text-sm font-bold text-gray-900">Báo cáo vi phạm</h4>
                                    </div>
                                    <p class="text-[11px] text-gray-600 mt-2 leading-relaxed max-w-[80%]">Có <span class="font-bold text-red-600">{{ stats?.reports_breakdown?.pending_count }}</span> báo cáo về người dùng/bài đăng cần được giải quyết.</p>
                                </div>
                                <button @click="activeTab = 'reports'" class="bg-white border border-red-200 hover:bg-red-600 hover:text-white text-red-700 text-xs font-bold py-1.5 px-3 rounded-lg transition shadow-sm whitespace-nowrap">
                                    Xử lý
                                </button>
                            </div>
                        </div>

                        <!-- Cảnh báo 2: Tài khoản uy tín thấp -->
                        <div v-if="users?.filter(u => u.trust_score < 40).length > 0" class="bg-orange-50 border border-orange-200 rounded-2xl p-4 shadow-sm relative overflow-hidden group">
                            <div class="absolute top-0 right-0 p-2 opacity-10 group-hover:scale-110 transition duration-300">
                                <svg class="w-16 h-16 text-orange-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg>
                            </div>
                            <div class="relative z-10 flex justify-between items-start">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <div class="bg-orange-100 p-2 rounded-lg border border-orange-200 shadow-inner">
                                            <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                        </div>
                                        <h4 class="text-sm font-bold text-gray-900">Tài khoản xấu</h4>
                                    </div>
                                    <p class="text-[11px] text-gray-600 mt-2 leading-relaxed max-w-[80%]">Có <span class="font-bold text-orange-600">{{ users?.filter(u => u.trust_score < 40).length }}</span> tài khoản uy tín thấp (< 40) có dấu hiệu spam.</p>
                                </div>
                                <button @click="activeTab = 'users'" class="bg-white border border-orange-200 hover:bg-orange-600 hover:text-white text-orange-700 text-xs font-bold py-1.5 px-3 rounded-lg transition shadow-sm whitespace-nowrap">
                                    Xem
                                </button>
                            </div>
                        </div>

                        <!-- Cảnh báo 3: Chiến dịch chờ duyệt -->
                        <div v-if="pendingCampaigns?.length > 0" class="bg-amber-50 border border-amber-200 rounded-2xl p-4 shadow-sm relative overflow-hidden group">
                            <div class="absolute top-0 right-0 p-2 opacity-10 group-hover:scale-110 transition duration-300">
                                <svg class="w-16 h-16 text-amber-600" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path></svg>
                            </div>
                            <div class="relative z-10 flex justify-between items-start">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <div class="bg-amber-100 p-2 rounded-lg border border-amber-200 shadow-inner">
                                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        </div>
                                        <h4 class="text-sm font-bold text-gray-900">Chiến dịch mới</h4>
                                    </div>
                                    <p class="text-[11px] text-gray-600 mt-2 leading-relaxed max-w-[80%]">Có <span class="font-bold text-amber-600">{{ pendingCampaigns?.length }}</span> chiến dịch quyên góp đang chờ BQT phê duyệt.</p>
                                </div>
                                <button @click="activeTab = 'moderation'" class="bg-white border border-amber-200 hover:bg-amber-600 hover:text-white text-amber-700 text-xs font-bold py-1.5 px-3 rounded-lg transition shadow-sm whitespace-nowrap">
                                    Duyệt
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-5">
                    <!-- Box 1: Tổng người dùng -->
                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Tổng người dùng</span>
                            <div class="p-2 bg-gray-50 rounded-lg">
                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            </div>
                        </div>
                        <div class="mt-4">
                            <span class="text-3xl font-bold text-gray-900">{{ stats?.total_users ?? 0 }}</span>
                        </div>
                    </div>
                    
                    <!-- Box 2: Giao dịch lẻ -->
                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Yêu cầu nhận</span>
                            <div class="p-2 bg-emerald-50 rounded-lg">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="text-3xl font-bold text-gray-900">{{ stats?.claims_breakdown?.total ?? 0 }}</span>
                        </div>
                        <div class="grid grid-cols-3 gap-2 mt-3 text-[10px] text-gray-500">
                            <div><span class="block text-emerald-600 font-semibold">{{ stats?.claims_breakdown?.completed_count ?? 0 }}</span> Hoàn tất</div>
                            <div><span class="block text-amber-500 font-semibold">{{ stats?.claims_breakdown?.pending_count ?? 0 }}</span> Chờ xử lý</div>
                            <div><span class="block text-gray-400 font-semibold">{{ stats?.claims_breakdown?.cancelled_count ?? 0 }}</span> Đã hủy</div>
                        </div>
                    </div>

                    <!-- Box 3: Chiến dịch -->
                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Quyên góp CD</span>
                            <div class="p-2 bg-amber-50 rounded-lg">
                                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="text-3xl font-bold text-gray-900">{{ stats?.donations_breakdown?.total_donations ?? 0 }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mt-3 text-[10px] text-gray-500">
                            <div><span class="block text-emerald-600 font-semibold">{{ stats?.donations_breakdown?.completed_quantity ?? 0 }} đv</span> Thực nhận</div>
                            <div><span class="block text-amber-500 font-semibold">{{ stats?.donations_breakdown?.pending_quantity ?? 0 }} đv</span> Chờ vận chuyển</div>
                        </div>
                    </div>
                    
                    <!-- Box 4: Sản lượng giải cứu -->
                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Lượng giải cứu</span>
                            <div class="p-2 bg-blue-50 rounded-lg">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                        </div>
                        <div class="mt-4">
                            <span class="text-3xl font-bold text-gray-900">{{ stats?.rescued_volume ?? 0 }}</span>
                        </div>
                        <div class="mt-3 text-[10px] text-gray-400">
                            Đơn vị thực phẩm tích lũy
                        </div>
                    </div>

                    <!-- Box 5: Tỷ lệ thành công -->
                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Tỷ lệ thành công</span>
                            <div class="p-2 bg-purple-50 rounded-lg">
                                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                            </div>
                        </div>
                        <div class="mt-4">
                            <span class="text-3xl font-bold text-gray-900">{{ stats?.success_rate ?? '0%' }}</span>
                        </div>
                        <div class="mt-3">
                            <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                <div class="bg-gray-800 h-full rounded-full transition-all duration-1000" :style="`width: ${stats?.success_rate ?? '0%'}`"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Box 6: Báo cáo vi phạm -->
                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 flex flex-col justify-between cursor-pointer hover:border-red-200 transition" @click="activeTab = 'reports'">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Báo cáo vi phạm</span>
                            <div class="p-2 bg-red-50 rounded-lg">
                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            </div>
                        </div>
                        <div class="mt-4">
                            <span class="text-3xl font-bold text-gray-900">{{ stats?.reports_breakdown?.total ?? 0 }}</span>
                        </div>
                        <div class="mt-3 text-[10px] text-gray-400">
                            <span class="text-red-600 font-semibold">{{ stats?.reports_breakdown?.pending_count ?? 0 }}</span> đang chờ xử lý
                        </div>
                    </div>
                </div>

                <!-- Biểu đồ -->
                <AdminCharts :chart-data="chartData" />

                <!-- Nửa dưới: Bảng nhật ký -->
                <div class="mt-8">
                    <!-- Bảng Nhật ký hoạt động gần đây (Full width) -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden flex flex-col">
                        <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">📋 Nhật ký hoạt động gần đây</h3>
                                <p class="text-[10px] text-gray-500 mt-1">Danh sách các thao tác và sự kiện mới nhất trên hệ thống.</p>
                            </div>
                            <button @click="activeTab = 'logs'" class="text-xs text-gray-600 hover:text-emerald-700 font-semibold bg-white hover:bg-emerald-50 px-3 py-1.5 rounded-lg border border-gray-200 hover:border-emerald-200 transition shadow-sm">Xem tất cả</button>
                        </div>
                        <div class="overflow-x-auto flex-1">
                            <table class="w-full text-left text-sm h-full">
                                <thead class="bg-gray-50/80 text-gray-500 text-[10px] font-bold uppercase tracking-wider border-b border-gray-100">
                                    <tr>
                                        <th class="px-5 py-3">Thời gian</th>
                                        <th class="px-5 py-3">Loại sự kiện</th>
                                        <th class="px-5 py-3">Chi tiết</th>
                                        <th class="px-5 py-3">Thực hiện bởi</th>
                                        <th class="px-5 py-3 text-center">Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <tr v-for="log in recentActivities" :key="log.id" class="hover:bg-gray-50/50 transition">
                                        <td class="px-5 py-3 whitespace-nowrap text-xs text-gray-500">
                                            {{ log.time }}
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap">
                                            <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-[10px] font-bold tracking-wider border border-gray-200">
                                                {{ log.type }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-xs text-gray-800">
                                            <div class="line-clamp-2 max-w-[500px]" :title="log.details">{{ log.details }}</div>
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap text-xs font-semibold text-gray-700">
                                            {{ log.status }}
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap text-center">
                                            <span v-if="log.type.includes('VERIFICATION') || log.type.includes('MODERATION')" class="bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-md text-[9px] font-bold tracking-wider border border-emerald-200">COMPLETED</span>
                                            <span v-else-if="log.type.includes('BAN')" class="bg-red-50 text-red-700 px-2.5 py-1 rounded-md text-[9px] font-bold tracking-wider border border-red-200">RESTRICTED</span>
                                            <span v-else class="bg-blue-50 text-blue-700 px-2.5 py-1 rounded-md text-[9px] font-bold tracking-wider border border-blue-200">AUTO-LOG</span>
                                        </td>
                                    </tr>
                                    <tr v-if="!recentActivities || recentActivities.length === 0">
                                        <td colspan="5" class="px-6 py-8 text-center text-gray-400 text-xs">
                                            Chưa có hoạt động nào gần đây.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'users'" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden animate-fade-in flex flex-col gap-6">
                
                <!-- Bảng 1: Tổ chức chờ duyệt -->
                <div v-if="pendingCharities.length > 0" class="border-b border-gray-100 pb-6">
                    <div class="p-6 pb-2 border-b border-gray-100 bg-amber-50/30">
                        <h2 class="text-lg font-bold text-amber-800 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            Tổ chức đang chờ duyệt
                        </h2>
                        <p class="text-xs text-amber-700 mt-1">Danh sách các tổ chức từ thiện cần xác thực hồ sơ pháp lý.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-amber-100/50 text-amber-800 text-xs font-bold uppercase tracking-wider border-b border-amber-100">
                                <tr>
                                    <th class="px-6 py-4">Tên tổ chức</th>
                                    <th class="px-6 py-4">Email / SĐT</th>
                                    <th class="px-6 py-4">Vai trò</th>
                                    <th class="px-6 py-4 text-right">Hành động điều khiển</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-amber-50">
                                <tr v-for="user in pendingCharities" :key="user.id" class="hover:bg-amber-50/50 transition">
                                    <td class="px-6 py-4 font-semibold text-gray-900">{{ user.name }}</td>
                                    <td class="px-6 py-4">
                                        <div class="text-gray-900">{{ user.email }}</div>
                                        <div class="text-xs text-gray-400">{{ user.phone ?? 'Chưa cập nhật' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="bg-purple-50 text-purple-700 text-xs font-bold px-2.5 py-1 rounded-md">
                                            {{ getRoleLabel(user.role) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right space-x-2">
                                        <button 
                                            @click="selectedCharity = user"
                                            class="text-xs font-semibold bg-amber-500 hover:bg-amber-600 text-white px-3 py-1.5 rounded-lg shadow-sm transition"
                                        >
                                            📄 Duyệt hồ sơ pháp lý
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Bảng 2: Tất cả tài khoản -->
                <div>
                    <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-gray-950">👥 Tất cả tài khoản hệ thống</h2>
                            <p class="text-xs text-gray-500 mt-1">Danh sách người dùng đã đăng ký. Bạn có thể tìm kiếm và khóa tài khoản vi phạm.</p>
                        </div>
                        
                        <!-- Thanh tìm kiếm -->
                        <div class="relative w-full sm:w-64">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>
                            <input 
                                type="text" 
                                v-model="searchQuery" 
                                placeholder="Tìm tên, email, sđt..." 
                                class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-xs rounded-xl focus:ring-emerald-500 focus:border-emerald-500 block pl-9 pr-3 py-2.5 outline-none transition"
                            />
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-xs font-bold uppercase tracking-wider border-b border-gray-100">
                                <tr>
                                    <th class="px-6 py-4">Tên chủ tài khoản</th>
                                    <th class="px-6 py-4">Email / SĐT</th>
                                    <th class="px-6 py-4">Vai trò</th>
                                    <th class="px-6 py-4">Trạng thái</th>
                                    <th class="px-6 py-4 text-right">Hành động điều khiển</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-if="filteredUsers.length === 0">
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-400 text-sm">
                                        Không tìm thấy tài khoản nào phù hợp với từ khóa "{{ searchQuery }}".
                                    </td>
                                </tr>
                                <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-gray-50/70 transition">
                                    <td class="px-6 py-4 font-semibold text-gray-900">{{ user.name }}</td>
                                    <td class="px-6 py-4">
                                        <div class="text-gray-900">{{ user.email }}</div>
                                        <div class="text-xs text-gray-400">{{ user.phone ?? 'Chưa cập nhật' }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span :class="{
                                            'bg-purple-50 text-purple-700': user.role === 'charity',
                                            'bg-blue-50 text-blue-700': user.role === 'personal',
                                            'bg-red-50 text-red-700': user.role === 'admin'
                                        }" class="text-xs font-bold px-2.5 py-1 rounded-md">
                                            {{ getRoleLabel(user.role) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span :class="{
                                            'bg-emerald-50 text-emerald-700': user.status === 'verified',
                                            'bg-amber-50 text-amber-700': user.status === 'pending',
                                            'bg-red-50 text-red-700': user.status === 'banned' || user.status === 'rejected'
                                        }" class="text-xs font-bold px-2.5 py-1 rounded-md">
                                            {{ getStatusLabel(user.status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right space-x-2">
                                        <button 
                                            v-if="user.role !== 'admin'"
                                            @click="toggleUserStatus(user.id, user.status)"
                                            :class="user.status === 'banned' ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100' : 'bg-red-50 text-red-600 hover:bg-red-100'"
                                            class="text-xs font-bold px-3 py-1.5 rounded-lg transition"
                                        >
                                            {{ user.status === 'banned' ? 'Mở khóa' : 'Khóa tài khoản' }}
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'moderation'" class="grid grid-cols-1 lg:grid-cols-2 gap-6 animate-fade-in">
                
                <!-- Cột trái: Chiến dịch đã duyệt / Đang hoạt động -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
                    <div class="border-b border-gray-100 pb-3">
                        <h3 class="text-base font-bold text-gray-950 flex items-center gap-2">✅ Chiến dịch đang hoạt động</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Danh sách các chiến dịch từ thiện đã được Ban quản trị phê duyệt và đang chạy.</p>
                    </div>
                    <div v-if="!activeCampaigns || activeCampaigns.length === 0" class="text-sm text-gray-400 py-6 text-center">
                        Hiện tại không có chiến dịch nào đang hoạt động.
                    </div>
                    <div v-else class="space-y-3 max-h-[500px] overflow-y-auto pr-1">
                        <div v-for="cam in activeCampaigns" :key="cam.id" class="p-4 bg-emerald-50/30 border border-emerald-100 rounded-xl flex flex-col justify-between space-y-3">
                            <div>
                                <h4 class="text-sm font-bold text-gray-950">{{ cam.title }}</h4>
                                <p class="text-xs text-gray-600 mt-1 line-clamp-2">{{ cam.description }}</p>
                                <div class="mt-2 text-xs text-gray-500 flex justify-between">
                                    <span><span class="font-semibold text-gray-800">Tổ chức:</span> {{ cam.user?.name }}</span>
                                </div>
                            </div>
                            <div class="flex justify-end space-x-2">
                                <button @click="selectedCampaign = cam" class="text-xs bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold px-3 py-1.5 rounded-lg transition">Xem chi tiết</button>
                                <button @click="moderateCampaign(cam.id, 'rejected')" class="text-xs bg-red-100 hover:bg-red-200 text-red-700 font-semibold px-3 py-1.5 rounded-lg transition">Gỡ chiến dịch</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cột phải: Chiến dịch chờ duyệt -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
                    <div class="border-b border-gray-100 pb-3">
                        <h3 class="text-base font-bold text-gray-950 flex items-center gap-2">📋 Chiến dịch chờ duyệt</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Kiểm soát nội dung, thời gian và mục tiêu của chiến dịch trước khi hiển thị đại chúng.</p>
                    </div>
                    <div v-if="!pendingCampaigns || pendingCampaigns.length === 0" class="text-sm text-gray-400 py-6 text-center">
                        Không có chiến dịch quyên góp lớn nào đang chờ duyệt.
                    </div>
                    <div v-else class="space-y-3 max-h-[500px] overflow-y-auto pr-1">
                        <div v-for="cam in pendingCampaigns" :key="cam.id" class="p-4 bg-amber-50/50 border border-amber-100 rounded-xl flex flex-col justify-between space-y-3">
                            <div>
                                <h4 class="text-sm font-bold text-gray-950">{{ cam.title }}</h4>
                                <p class="text-xs text-gray-600 mt-1 line-clamp-2">{{ cam.description }}</p>
                                <div class="mt-2 text-xs text-gray-500">
                                    <span class="font-semibold text-gray-800">Tổ chức:</span> {{ cam.user?.name }}
                                </div>
                            </div>
                            <div class="flex justify-end space-x-2">
                                <button @click="selectedCampaign = cam" class="text-xs bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold px-3 py-1.5 rounded-lg transition">Xem chi tiết</button>
                                <button @click="moderateCampaign(cam.id, 'active')" class="text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-3 py-1.5 rounded-lg shadow-sm transition">Duyệt</button>
                                <button @click="moderateCampaign(cam.id, 'rejected')" class="text-xs bg-red-100 hover:bg-red-200 text-red-700 font-semibold px-3 py-1.5 rounded-lg transition">Từ chối</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 4: NHẬT KÝ HỆ THỐNG -->
            <div v-if="activeTab === 'logs'" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden animate-fade-in">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center flex-wrap gap-2">
                    <div>
                        <h2 class="text-lg font-bold text-gray-950">📜 Nhật ký hoạt động & Giám sát hệ thống</h2>
                        <p class="text-xs text-gray-500 mt-1">Lịch sử các sự kiện kiểm duyệt tự động bằng AI, hoạt động quản trị của Admin và thay đổi tài khoản.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs font-bold uppercase tracking-wider border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-4">Thời gian</th>
                                <th class="px-6 py-4">Hành động</th>
                                <th class="px-6 py-4">Nội dung chi tiết</th>
                                <th class="px-6 py-4">Người thực hiện / Đối tượng</th>
                                <th class="px-6 py-4">Địa chỉ IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="log in systemLogs" :key="log.id" class="hover:bg-gray-50/70 transition">
                                <td class="px-6 py-4 text-xs font-medium text-gray-500 whitespace-nowrap">
                                    {{ new Date(log.created_at).toLocaleString('vi-VN', {hour: '2-digit', minute:'2-digit', day:'2-digit', month:'2-digit', year:'numeric'}) }}
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="{
                                        'bg-emerald-50 text-emerald-700 border-emerald-100': log.action === 'AI_MODERATION' && log.description.includes('approved'),
                                        'bg-red-50 text-red-700 border-red-100': log.action === 'AI_MODERATION' && log.description.includes('flagged'),
                                        'bg-purple-50 text-purple-700 border-purple-100': log.action === 'CHARITY_VERIFICATION',
                                        'bg-amber-50 text-amber-700 border-amber-100': log.action === 'USER_BAN_TOGGLE',
                                    }" class="text-[10px] font-bold px-2 py-0.5 rounded-md border whitespace-nowrap">
                                        {{ log.action }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-700 leading-relaxed text-xs">
                                    {{ log.description }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-xs text-gray-900 font-semibold" v-if="log.user">
                                        {{ log.user.name }} 
                                        <span class="text-[10px] text-gray-400 font-normal">({{ log.user.email }})</span>
                                    </div>
                                    <div class="text-xs text-gray-400 italic" v-else>Hệ thống tự động</div>
                                </td>
                                <td class="px-6 py-4 text-xs font-mono text-gray-400">
                                    {{ log.ip_address }}
                                </td>
                            </tr>
                            <tr v-if="!systemLogs || systemLogs.length === 0">
                                <td colspan="5" class="text-center py-8 text-sm text-gray-400 italic">
                                    Chưa có nhật ký hoạt động nào được ghi lại.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- TAB 5: BÁO CÁO VI PHẠM -->
            <div v-if="activeTab === 'reports'" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden animate-fade-in">
                <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-gray-950">🚩 Quản lý Báo cáo vi phạm</h2>
                        <p class="text-xs text-gray-500 mt-1">Danh sách các báo cáo từ người dùng về thực phẩm hỏng, lừa đảo, hoặc hành vi không chuẩn mực.</p>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row items-center gap-3 w-full sm:w-auto">
                        <!-- CUSTOM DROPDOWN SELECT CHO BÁO CÁO -->
                        <div class="relative w-full sm:w-auto">
                            <!-- Nút Trigger -->
                            <button @click="showFilterDropdown = !showFilterDropdown" 
                                    class="flex items-center justify-between w-full sm:w-auto bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 text-xs font-bold rounded-xl focus:ring-2 focus:ring-blue-500 block py-2.5 pl-4 pr-3 sm:min-w-[160px] outline-none transition-all duration-200 shadow-sm">
                                <span>{{ periodLabels[filterPeriod] }}</span>
                                <svg class="w-4 h-4 text-gray-500 transition-transform duration-200" :class="{ 'rotate-180': showFilterDropdown }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </button>
                            
                            <!-- Bảng Menu Dropdown -->
                            <transition enter-active-class="transition ease-out duration-100" enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100" leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
                                <div v-if="showFilterDropdown" class="absolute right-0 z-50 mt-2 w-[220px] rounded-xl bg-white shadow-xl border border-gray-100 py-1 origin-top-right">
                                    <button @click="selectPeriod('today')" :class="{'bg-blue-50 text-blue-700': filterPeriod === 'today'}" class="w-full text-left px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition">Hôm nay</button>
                                    <button @click="selectPeriod('7days')" :class="{'bg-blue-50 text-blue-700': filterPeriod === '7days'}" class="w-full text-left px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition">7 ngày qua</button>
                                    <button @click="selectPeriod('30days')" :class="{'bg-blue-50 text-blue-700': filterPeriod === '30days'}" class="w-full text-left px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition">30 ngày qua</button>
                                    <button @click="selectPeriod('all')" :class="{'bg-blue-50 text-blue-700': filterPeriod === 'all'}" class="w-full text-left px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition">Tất cả thời gian</button>
                                    <div class="border-t border-gray-100 my-1"></div>
                                    <button @click="selectPeriod('custom')" :class="{'bg-blue-50 text-blue-700': filterPeriod === 'custom'}" class="w-full text-left px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition">Tùy chỉnh khoảng thời gian...</button>
                                </div>
                            </transition>
                        </div>
                        
                        <!-- Thanh tìm kiếm báo cáo -->
                        <div class="relative w-full sm:w-64">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>
                            <input 
                                type="text" 
                                v-model="searchReportQuery" 
                                placeholder="Tìm kiếm báo cáo..." 
                                class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-xs rounded-xl focus:ring-blue-500 focus:border-blue-500 block pl-9 pr-3 py-2.5 outline-none transition"
                            />
                        </div>
                    </div>
                </div>
                
                <!-- Khung chọn ngày tùy chỉnh cho Báo cáo (Hiện khi chọn Tùy chỉnh) -->
                <div v-if="filterPeriod === 'custom'" class="px-6 pb-2 border-b border-gray-100 flex justify-end">
                    <div class="flex items-center gap-2 bg-gray-50 p-1.5 rounded-lg border border-gray-200 shadow-sm">
                        <input type="date" v-model="customStartDate" class="bg-white border border-gray-200 text-gray-700 text-xs font-medium rounded-md focus:ring-blue-500 focus:border-blue-500 p-1.5 outline-none w-28 sm:w-auto" />
                        <span class="text-xs text-gray-400 font-medium">-</span>
                        <input type="date" v-model="customEndDate" class="bg-white border border-gray-200 text-gray-700 text-xs font-medium rounded-md focus:ring-blue-500 focus:border-blue-500 p-1.5 outline-none w-28 sm:w-auto" />
                        <button @click="updateFilter" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-md text-xs font-bold transition shadow-sm ml-1">Áp dụng</button>
                    </div>
                </div>
                <div class="px-6 flex gap-4 border-b border-gray-100 mt-2">
                    <button 
                        @click="activeReportTab = 'personal'"
                        :class="activeReportTab === 'personal' ? 'border-b-2 border-blue-600 text-blue-700 font-bold' : 'text-gray-500 hover:text-gray-700 font-medium'"
                        class="px-2 py-3 text-sm transition flex items-center gap-2"
                    >
                        <span>👤 Cá nhân / Doanh nghiệp</span>
                        <span :class="activeReportTab === 'personal' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'" class="text-[10px] px-2 py-0.5 rounded-full">{{ personalReports.length }}</span>
                    </button>
                    <button 
                        @click="activeReportTab = 'charity'"
                        :class="activeReportTab === 'charity' ? 'border-b-2 border-blue-600 text-blue-700 font-bold' : 'text-gray-500 hover:text-gray-700 font-medium'"
                        class="px-2 py-3 text-sm transition flex items-center gap-2"
                    >
                        <span>🏢 Tổ chức từ thiện</span>
                        <span :class="activeReportTab === 'charity' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'" class="text-[10px] px-2 py-0.5 rounded-full">{{ charityReports.length }}</span>
                    </button>
                </div>

                <div class="flex flex-col gap-6 p-6 pb-12">
                    
                    <!-- Tab 1: Cá nhân / Doanh nghiệp -->
                    <div v-if="activeReportTab === 'personal'" class="border border-gray-100 rounded-xl overflow-hidden shadow-sm animate-fade-in">
                        <div class="bg-gray-50/80 px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-gray-800">Báo cáo về Cá nhân / Doanh nghiệp</h3>
                                <p class="text-[10px] text-gray-500 mt-0.5">Các vi phạm liên quan đến bài đăng tặng thực phẩm, lừa đảo, bom hàng.</p>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-white text-gray-400 text-[10px] font-bold uppercase tracking-wider border-b border-gray-50">
                                    <tr>
                                        <th class="px-5 py-3">Thời gian</th>
                                        <th class="px-5 py-3">Người báo cáo</th>
                                        <th class="px-5 py-3 text-blue-700">Người bị báo cáo (Cá nhân)</th>
                                        <th class="px-5 py-3">Lý do & Chi tiết</th>
                                        <th class="px-5 py-3">Trạng thái</th>
                                        <th class="px-5 py-3 text-right">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 bg-white">
                                    <tr v-for="report in personalReports" :key="report.id" class="hover:bg-gray-50/50 transition">
                                        <td class="px-5 py-4 text-[11px] font-medium text-gray-500 whitespace-nowrap">
                                            {{ new Date(report.created_at).toLocaleString('vi-VN', {hour: '2-digit', minute:'2-digit', day:'2-digit', month:'2-digit', year:'numeric'}) }}
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="text-[11px] text-gray-700 font-semibold" v-if="report.reporter">{{ report.reporter.name }}</div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="text-[11px] text-blue-700 font-bold" v-if="report.reported_user">{{ report.reported_user.name }}</div>
                                        </td>
                                        <td class="px-5 py-4 text-gray-700 leading-relaxed text-[11px] max-w-[250px]">
                                            <span class="font-bold text-red-600 block mb-0.5">{{ report.reason }}</span>
                                            <span class="line-clamp-2 text-gray-500" :title="report.details">{{ report.details }}</span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span :class="{
                                                'bg-amber-50 text-amber-700 border-amber-100': report.status === 'pending',
                                                'bg-emerald-50 text-emerald-700 border-emerald-100': report.status === 'resolved',
                                                'bg-gray-50 text-gray-700 border-gray-100': report.status === 'dismissed',
                                            }" class="text-[9px] font-bold px-2 py-0.5 rounded border whitespace-nowrap">
                                                {{ report.status === 'pending' ? 'Chờ xử lý' : (report.status === 'resolved' ? 'Đã xử lý' : 'Đã bỏ qua') }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4 text-right">
                                            <button @click="selectedReport = report" class="text-[11px] font-semibold bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 px-3 py-1.5 rounded-lg transition">Chi tiết</button>
                                        </td>
                                    </tr>
                                    <tr v-if="personalReports.length === 0">
                                        <td colspan="6" class="text-center py-8 text-[11px] text-gray-400 italic">Không có báo cáo nào khớp với tìm kiếm của bạn.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab 2: Tổ chức từ thiện -->
                    <div v-if="activeReportTab === 'charity'" class="border border-gray-100 rounded-xl overflow-hidden shadow-sm animate-fade-in">
                        <div class="bg-gray-50/80 px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-gray-800">Báo cáo về Tổ chức từ thiện</h3>
                                <p class="text-[10px] text-gray-500 mt-0.5">Các vi phạm nghiêm trọng liên quan đến chiến dịch quyên góp, trục lợi hoặc sai mục đích.</p>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-white text-gray-400 text-[10px] font-bold uppercase tracking-wider border-b border-gray-50">
                                    <tr>
                                        <th class="px-5 py-3">Thời gian</th>
                                        <th class="px-5 py-3">Người báo cáo</th>
                                        <th class="px-5 py-3 text-blue-700">Tổ chức bị báo cáo</th>
                                        <th class="px-5 py-3">Lý do & Chi tiết</th>
                                        <th class="px-5 py-3">Trạng thái</th>
                                        <th class="px-5 py-3 text-right">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 bg-white">
                                    <tr v-for="report in charityReports" :key="report.id" class="hover:bg-gray-50/50 transition">
                                        <td class="px-5 py-4 text-[11px] font-medium text-gray-500 whitespace-nowrap">
                                            {{ new Date(report.created_at).toLocaleString('vi-VN', {hour: '2-digit', minute:'2-digit', day:'2-digit', month:'2-digit', year:'numeric'}) }}
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="text-[11px] text-gray-700 font-semibold" v-if="report.reporter">{{ report.reporter.name }}</div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="text-[11px] text-blue-700 font-bold" v-if="report.reported_user">{{ report.reported_user.name }}</div>
                                        </td>
                                        <td class="px-5 py-4 text-gray-700 leading-relaxed text-[11px] max-w-[250px]">
                                            <span class="font-bold text-red-600 block mb-0.5">{{ report.reason }}</span>
                                            <span class="line-clamp-2 text-gray-500" :title="report.details">{{ report.details }}</span>
                                            <span v-if="report.campaign_id && getPendingReportCountForCampaign(report.campaign_id) >= 3 && report.status === 'pending'" class="inline-flex items-center gap-1 mt-1.5 bg-red-600 text-white text-[9px] font-bold px-2 py-0.5 rounded shadow-sm">
                                                🔥 Ưu tiên xử lý
                                            </span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span :class="{
                                                'bg-amber-50 text-amber-700 border-amber-100': report.status === 'pending',
                                                'bg-emerald-50 text-emerald-700 border-emerald-100': report.status === 'resolved',
                                                'bg-gray-50 text-gray-700 border-gray-100': report.status === 'dismissed',
                                            }" class="text-[9px] font-bold px-2 py-0.5 rounded border whitespace-nowrap">
                                                {{ report.status === 'pending' ? 'Chờ xử lý' : (report.status === 'resolved' ? 'Đã xử lý' : 'Đã bỏ qua') }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4 text-right">
                                            <button @click="selectedReport = report" class="text-[11px] font-semibold bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 px-3 py-1.5 rounded-lg transition">Chi tiết</button>
                                        </td>
                                    </tr>
                                    <tr v-if="charityReports.length === 0">
                                        <td colspan="6" class="text-center py-8 text-[11px] text-gray-400 italic">Không có báo cáo nào khớp với tìm kiếm của bạn.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <div v-if="selectedCharity" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex justify-center items-center p-4 z-50 animate-fade-in">
            <div class="bg-white w-full max-w-2xl rounded-3xl shadow-xl border border-gray-100 overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-6 bg-slate-50 border-b border-gray-100 flex justify-between items-center">
                    <div>
                        <h3 class="text-base font-bold text-gray-950">Thẩm định hồ sơ pháp nhân</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Tổ chức: {{ selectedCharity.name }}</p>
                    </div>
                    <button @click="selectedCharity = null" class="text-gray-400 hover:text-gray-600 text-xl font-bold p-1">×</button>
                </div>
                
                <div class="p-6 overflow-y-auto space-y-6 flex-1">
                    <div v-if="selectedCharity.documents && selectedCharity.documents.length > 0" class="space-y-4">
                        <div v-for="doc in selectedCharity.documents" :key="doc.id" class="space-y-2 border border-gray-100 p-4 rounded-xl bg-gray-50">
                            <span class="text-xs font-bold text-gray-500 uppercase tracking-wide">
                                📂 Loại tài liệu: {{ doc.document_type === 'legal_license' ? 'Giấy phép hoạt động pháp lý' : 'Hình ảnh thực tế cơ sở mái ấm' }}
                            </span>
                            
                            <div class="mt-2 rounded-lg overflow-hidden border border-gray-200 bg-white">
                                <img 
                                    v-if="doc.file_path.match(/\.(jpeg|jpg|png)$/i)" 
                                    :src="`/storage/${doc.file_path}`" 
                                    alt="Minh chứng pháp lý" 
                                    class="w-full h-auto max-h-64 object-contain mx-auto"
                                />
                                <div v-else class="p-4 flex items-center justify-between text-sm">
                                    <span class="text-gray-600 font-medium">Tệp tài liệu văn bản pháp quy (PDF)</span>
                                    <a :href="`/storage/${doc.file_path}`" target="_blank" class="text-xs bg-slate-900 text-white font-semibold px-3 py-1.5 rounded-lg hover:bg-slate-800 transition">Mở xem tab mới</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-sm text-gray-400">
                        ⚠️ Không tìm thấy tệp tài liệu chứng minh nào được đăng tải từ tài khoản này.
                    </div>
                </div>

                <div class="p-6 bg-slate-50 border-t border-gray-100 flex justify-end space-x-3">
                    <button @click="verifyCharity(selectedCharity.id, 'rejected')" class="text-xs bg-red-100 hover:bg-red-200 text-red-700 font-bold px-4 py-2.5 rounded-xl transition">Từ chối cấp quyền</button>
                    <button @click="verifyCharity(selectedCharity.id, 'verified')" class="text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2.5 rounded-xl shadow-md shadow-emerald-100 transition">Phê duyệt hoạt động</button>
                </div>
            </div>
        </div>

        <!-- Modal Xem chi tiết Chiến dịch -->
        <div v-if="selectedCampaign" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex justify-center items-center p-4 z-50 animate-fade-in">
            <div class="bg-white w-full max-w-3xl rounded-3xl shadow-xl border border-gray-100 overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-6 bg-slate-50 border-b border-gray-100 flex justify-between items-center">
                    <div>
                        <h3 class="text-base font-bold text-gray-950">Chi tiết chiến dịch</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Tổ chức: {{ selectedCampaign.user?.name || 'Không rõ' }}</p>
                    </div>
                    <button @click="selectedCampaign = null" class="text-gray-400 hover:text-gray-600 text-xl font-bold p-1">×</button>
                </div>
                
                <div class="p-6 overflow-y-auto space-y-6 flex-1">
                    <div class="space-y-4">
                        <h4 class="text-lg font-bold text-gray-900">{{ selectedCampaign.title }}</h4>
                        <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ selectedCampaign.description }}</p>
                        
                        <div class="grid grid-cols-2 gap-4 text-sm bg-gray-50 p-4 rounded-xl border border-gray-100">
                            <div>
                                <span class="text-gray-500 font-semibold text-xs block mb-1">📅 Ngày tạo</span>
                                <span class="text-gray-900 font-medium">{{ new Date(selectedCampaign.created_at).toLocaleDateString('vi-VN') }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 font-semibold text-xs block mb-1">📦 Mốc 1 (Đóng đồ khô)</span>
                                <span class="text-gray-900 font-medium">{{ new Date(selectedCampaign.web_deadline).toLocaleString('vi-VN', {hour: '2-digit', minute:'2-digit', day:'2-digit', month:'2-digit', year:'numeric'}) }}</span>
                            </div>
                            <div v-if="selectedCampaign.event_date">
                                <span class="text-gray-500 font-semibold text-xs block mb-1">🚀 Mốc 2 (Ngày đi phát)</span>
                                <span class="text-gray-900 font-medium">{{ new Date(selectedCampaign.event_date).toLocaleString('vi-VN', {hour: '2-digit', minute:'2-digit', day:'2-digit', month:'2-digit', year:'numeric'}) }}</span>
                            </div>
                            <div class="col-span-2">
                                <span class="text-gray-500 font-semibold text-xs block mb-1">📍 Địa điểm nhận quyên góp</span>
                                <span class="text-gray-900 font-medium">{{ selectedCampaign.location_details }}</span>
                            </div>
                        </div>

                        <div v-if="selectedCampaign.items && selectedCampaign.items.length > 0">
                            <h5 class="text-sm font-bold text-gray-900 mb-3 border-b pb-2">📦 Nhu yếu phẩm kêu gọi</h5>
                            <ul class="space-y-3">
                                <li v-for="item in selectedCampaign.items" :key="item.id" class="flex justify-between items-center bg-gray-50 p-3 rounded-lg border border-gray-100">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold text-gray-800">{{ item.item_name }}</span>
                                        <span v-if="item.item_type === 'fresh'" class="text-[9px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-bold">Đồ Tươi</span>
                                        <span v-else class="text-[9px] bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded font-bold">Đồ Khô</span>
                                    </div>
                                    <span class="text-xs font-bold bg-white border border-gray-200 px-2.5 py-1 rounded-md text-emerald-600">
                                        Thực nhận: {{ item.current_quantity }} / {{ item.target_quantity }} {{ item.unit }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-slate-50 border-t border-gray-100 flex justify-end gap-3">
                    <!-- Nút Đóng dùng chung -->
                    <button @click="selectedCampaign = null" class="bg-gray-100 text-gray-700 font-bold px-4 py-2 rounded-xl hover:bg-gray-200 transition">Đóng</button>
                    
                    <!-- Nút hành động cho chiến dịch chờ duyệt -->
                    <button v-if="selectedCampaign.status === 'pending'" @click="moderateCampaign(selectedCampaign.id, 'rejected'); selectedCampaign = null" class="bg-red-50 text-red-600 font-bold px-4 py-2 rounded-xl hover:bg-red-100 transition">Từ chối</button>
                    <button v-if="selectedCampaign.status === 'pending'" @click="moderateCampaign(selectedCampaign.id, 'active'); selectedCampaign = null" class="bg-emerald-600 text-white font-bold px-5 py-2 rounded-xl shadow-lg hover:bg-emerald-700 hover:shadow-xl transition transform hover:-translate-y-0.5">Duyệt chiến dịch</button>

                    <!-- Nút hành động cho chiến dịch đã duyệt -->
                    <button v-if="selectedCampaign.status === 'active'" @click="moderateCampaign(selectedCampaign.id, 'rejected'); selectedCampaign = null" class="bg-red-100 hover:bg-red-200 text-red-700 font-bold px-4 py-2 rounded-xl transition">Gỡ chiến dịch</button>
                </div>
            </div>
        </div>
        <!-- Modal Chi tiết Báo cáo -->
        <div v-if="selectedReport" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex justify-center items-center p-4 z-50 animate-fade-in">
            <div class="bg-white w-full max-w-2xl rounded-3xl shadow-xl border border-gray-100 overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-6 bg-slate-50 border-b border-gray-100 flex justify-between items-center shrink-0">
                    <div>
                        <h3 class="text-base font-bold text-gray-950 flex items-center gap-2">
                            <span class="text-red-500">🚩</span> Chi tiết Báo cáo
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">Ngày báo cáo: {{ new Date(selectedReport.created_at).toLocaleString('vi-VN') }}</p>
                    </div>
                    <button @click="selectedReport = null" class="text-gray-400 hover:text-gray-600 text-xl font-bold p-1">×</button>
                </div>
                
                <div class="p-6 overflow-y-auto space-y-6 flex-1 custom-scrollbar">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Người báo cáo</span>
                            <div class="font-bold text-gray-900 text-sm">{{ selectedReport.reporter?.name }}</div>
                            <div class="text-xs text-gray-500 mt-0.5">{{ selectedReport.reporter?.email }}</div>
                        </div>
                        <div class="bg-red-50 p-4 rounded-xl border border-red-100">
                            <span class="text-[10px] font-bold text-red-500 uppercase tracking-wider block mb-1">Người bị báo cáo</span>
                            <div class="font-bold text-red-900 text-sm">{{ selectedReport.reported_user?.name }}</div>
                            <div class="text-xs text-red-700 mt-0.5">{{ selectedReport.reported_user?.email }}</div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <span class="text-sm font-bold text-gray-900">Nội dung tố cáo:</span>
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                            <p class="font-bold text-red-600 text-sm">{{ selectedReport.reason }}</p>
                            <p class="text-sm text-gray-700 mt-2 whitespace-pre-line">{{ selectedReport.details || 'Không có mô tả chi tiết.' }}</p>
                        </div>
                    </div>

                    <div v-if="selectedReport.proof_image && selectedReport.proof_image.length > 0" class="space-y-2">
                        <span class="text-sm font-bold text-gray-900">Ảnh bằng chứng đính kèm:</span>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 bg-gray-50 p-2 rounded-xl border border-gray-200">
                            <img v-for="(img, idx) in selectedReport.proof_image" :key="idx" :src="img.startsWith('/storage') ? img : '/storage/' + img" class="w-full aspect-square object-cover rounded-lg shadow-sm" alt="Ảnh bằng chứng" />
                        </div>
                    </div>

                    <!-- Thông tin Bài viết / Đơn hàng liên quan (nếu có) -->
                    <div v-if="selectedReport.food_post" class="space-y-2">
                        <span class="text-sm font-bold text-gray-900">Bài đăng liên quan:</span>
                        <div class="bg-blue-50 p-3 rounded-xl border border-blue-100 flex items-start gap-3">
                            <img :src="selectedReport.food_post.image_url ? (selectedReport.food_post.image_url.startsWith('/storage') ? selectedReport.food_post.image_url : '/storage/' + selectedReport.food_post.image_url) : 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=600&q=80'" class="w-12 h-12 rounded-lg object-cover" />
                            <div>
                                <p class="font-bold text-blue-900 text-sm">{{ selectedReport.food_post.title }}</p>
                                <p class="text-xs text-blue-700 mt-0.5">Tạo ngày: {{ new Date(selectedReport.food_post.created_at).toLocaleDateString('vi-VN') }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="selectedReport.campaign" class="space-y-2">
                        <span class="text-sm font-bold text-gray-900">Chiến dịch liên quan:</span>
                        <div class="bg-amber-50 p-3 rounded-xl border border-amber-100 flex items-start gap-3">
                            <div class="w-12 h-12 rounded-lg bg-amber-200 flex items-center justify-center text-xl font-bold text-amber-700 shrink-0">CD</div>
                            <div>
                                <p class="font-bold text-amber-900 text-sm">{{ selectedReport.campaign.title }}</p>
                                <p class="text-xs text-amber-700 mt-0.5">Tạo ngày: {{ new Date(selectedReport.campaign.created_at).toLocaleDateString('vi-VN') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="selectedReport.status === 'pending'" class="p-6 bg-slate-50 border-t border-gray-100 flex flex-wrap justify-end gap-2 shrink-0">
                    <button @click="resolveReport(selectedReport.id, 'dismiss')" class="text-xs bg-white hover:bg-gray-100 text-gray-700 font-bold px-4 py-2.5 rounded-xl border border-gray-200 transition">Bỏ qua báo cáo</button>
                    <button @click="resolveReport(selectedReport.id, 'penalize_reporter')" class="text-xs bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold px-4 py-2.5 rounded-xl transition">Phạt người tố cáo (Vu khống)</button>
                    <button @click="resolveReport(selectedReport.id, 'penalize_20')" class="text-xs bg-amber-100 hover:bg-amber-200 text-amber-800 font-bold px-4 py-2.5 rounded-xl transition">Phạt -20 Uy tín</button>
                    <button @click="resolveReport(selectedReport.id, 'penalize_50')" class="text-xs bg-orange-100 hover:bg-orange-200 text-orange-800 font-bold px-4 py-2.5 rounded-xl transition">Phạt -50 Uy tín</button>
                    <button @click="resolveReport(selectedReport.id, 'ban_user')" class="text-xs bg-red-600 hover:bg-red-700 text-white font-bold px-4 py-2.5 rounded-xl shadow-sm transition">Khóa Vĩnh viễn</button>
                </div>
                <div v-else class="p-6 bg-slate-50 border-t border-gray-100 flex justify-end shrink-0">
                    <div class="px-4 py-2 bg-emerald-50 text-emerald-700 text-sm font-bold rounded-xl border border-emerald-200 flex items-center gap-2">
                        <span>✅</span> Đã xử lý xong
                    </div>
                </div>
            </div>
        </div>
        
        <ToastMessage />

    </div>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 0.25s ease-out forwards;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
