<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';

// Nhận dữ liệu truyền từ Laravel Controller sang thông qua Props
const props = defineProps({
    stats: Object,              // Chứa các số liệu tổng hợp thống kê
    users: Array,              // Danh sách toàn bộ người dùng và tài liệu minh chứng
    flaggedPosts: Array,       // Danh sách bài đăng bị AI gắn cờ 'flagged'
    pendingCampaigns: Array,   // Danh sách chiến dịch từ thiện chờ duyệt 'pending'
});

// State quản lý Tab đang hiển thị tích cực
const activeTab = ref('overview'); 

// State lưu thông tin Tổ chức đang được chọn để xem hồ sơ pháp lý (Modal)
const selectedCharity = ref(null);

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
        admin: 'admin',
        charity: 'Tổ chức từ thiện',
        small_business: 'Doanh nghiệp nhỏ',
        personal: 'Cá nhân',
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
                        <Link :href="route('profile.edit')" class="flex flex-col text-right hidden sm:flex hover:text-emerald-400 text-left transition">
                            <span class="text-sm font-semibold text-white">Ban Quản Trị</span>
                            <span class="text-[10px] text-slate-400">Hồ sơ: {{ $page.props.auth.user.name }}</span>
                        </Link>
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
            </div>

            <div v-if="activeTab === 'overview'" class="space-y-6 animate-fade-in">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Tài khoản mới tháng này</span>
                        <div class="flex items-baseline mt-4 space-x-2">
                            <span class="text-3xl font-bold text-gray-950">{{ stats?.new_users_count ?? 24 }}</span>
                            <span class="text-xs text-emerald-600 font-semibold">↑ Mới</span>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Giải cứu trong ngày</span>
                        <div class="flex items-baseline mt-4 space-x-2">
                            <span class="text-3xl font-bold text-emerald-600">{{ stats?.posts_today ?? 42 }}</span>
                            <span class="text-xs text-gray-500">Bài đăng lẻ</span>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Tỷ lệ giao dịch thành công</span>
                        <div class="flex items-baseline mt-4 space-x-2">
                            <span class="text-3xl font-bold text-blue-600">{{ stats?.success_rate ?? '87.5%' }}</span>
                            <span class="text-xs text-blue-500 font-medium">Hoàn thành</span>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Chiến dịch quyên góp chạy</span>
                        <div class="flex items-baseline mt-4 space-x-2">
                            <span class="text-3xl font-bold text-amber-600">{{ stats?.active_campaigns ?? 5 }}</span>
                            <span class="text-xs text-amber-600 font-medium">Tổ chức quản lý</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 space-y-4">
                    <h3 class="text-base font-bold text-gray-950">🎯 Theo dõi tiến độ gom hiện vật thực tế</h3>
                    <div class="space-y-4">
                        <div class="space-y-2">
                            <div class="flex justify-between text-xs font-semibold">
                                <span class="text-gray-700">Gom 500kg Gạo tẻ - Mái Ấm Tình Thương</span>
                                <span class="text-emerald-600">70% (350kg / 500kg)</span>
                            </div>
                            <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                                <div class="bg-emerald-500 h-full rounded-full" style="width: 70%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'users'" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden animate-fade-in">
                <div class="p-6 border-b border-gray-100">
                    <h2 class="text-lg font-bold text-gray-950">👥 Danh sách người dùng & Phân cấp vai trò</h2>
                    <p class="text-xs text-gray-500 mt-1">Quản lý trạng thái xác minh, xử lý khóa tài khoản vi phạm hoặc duyệt hồ sơ Mái ấm.</p>
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
                            <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50/70 transition">
                                <td class="px-6 py-4 font-semibold text-gray-900">{{ user.name }}</td>
                                <td class="px-6 py-4">
                                    <div class="text-gray-900">{{ user.email }}</div>
                                    <div class="text-xs text-gray-400">{{ user.phone ?? 'Chưa cập nhật' }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="{
                                        'bg-purple-50 text-purple-700': user.role === 'charity',
                                        'bg-blue-50 text-blue-700': user.role === 'small_business',
                                        'bg-gray-100 text-gray-700': user.role === 'personal',
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
                                        v-if="user.role === 'charity' && user.status === 'pending'"
                                        @click="selectedCharity = user"
                                        class="text-xs font-semibold bg-amber-500 hover:bg-amber-600 text-white px-3 py-1.5 rounded-lg shadow-sm transition"
                                    >
                                        📄 Duyệt hồ sơ pháp lý
                                    </button>
                                    
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

            <div v-if="activeTab === 'moderation'" class="grid grid-cols-1 lg:grid-cols-2 gap-6 animate-fade-in">
                
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
                    <div class="border-b border-gray-100 pb-3">
                        <h3 class="text-base font-bold text-gray-950 flex items-center gap-2">🚨 Bài đăng lẻ bị AI gắn cờ nghi ngờ</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Các bài viết chứa từ khóa độc hại, hết hạn hoặc hình ảnh rủi ro VSATTP.</p>
                    </div>
                    <div v-if="!flaggedPosts || flaggedPosts.length === 0" class="text-sm text-gray-400 py-6 text-center">
                        Tuyệt vời! Hiện tại không có bài viết nào bị hệ thống gắn cờ.
                    </div>
                    <div v-else class="space-y-3 max-h-[500px] overflow-y-auto pr-1">
                        <div v-for="post in flaggedPosts" :key="post.id" class="p-4 bg-red-50/40 border border-red-100 rounded-xl flex flex-col justify-between space-y-3">
                            <div>
                                <h4 class="text-sm font-bold text-gray-950">{{ post.title }}</h4>
                                <p class="text-xs text-gray-600 mt-1 line-clamp-2">{{ post.description }}</p>
                                <div class="text-[11px] text-red-600 font-semibold mt-1">Lý do: AI phát hiện hình ảnh hoặc nội dung sai quy chuẩn</div>
                            </div>
                            <div class="flex justify-end space-x-2">
                                <button @click="moderatePost(post.id, 'safe')" class="text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-3 py-1.5 rounded-lg shadow-sm transition">Xác nhận An toàn</button>
                                <button @click="moderatePost(post.id, 'hidden')" class="text-xs bg-red-100 hover:bg-red-200 text-red-700 font-semibold px-3 py-1.5 rounded-lg transition">Ẩn/Xóa bài</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
                    <div class="border-b border-gray-100 pb-3">
                        <h3 class="text-base font-bold text-gray-950 flex items-center gap-2">📋 Chiến dịch gom quyên góp chờ duyệt</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Kiểm soát nội dung, thời gian và mục tiêu của chiến dịch trước khi hiển thị đại chúng.</p>
                    </div>
                    <div v-if="!pendingCampaigns || pendingCampaigns.length === 0" class="text-sm text-gray-400 py-6 text-center">
                        Không có chiến dịch quyên góp lớn nào đang chờ duyệt.
                    </div>
                    <div v-else class="space-y-3 max-h-[500px] overflow-y-auto pr-1">
                        <div v-for="cam in pendingCampaigns" :key="cam.id" class="p-4 bg-gray-50 border border-gray-200/70 rounded-xl flex flex-col justify-between space-y-3">
                            <div>
                                <h4 class="text-sm font-bold text-gray-950">{{ cam.title }}</h4>
                                <p class="text-xs text-gray-600 mt-1 line-clamp-2">{{ cam.description }}</p>
                                <div class="mt-2 grid grid-cols-2 gap-1 text-xs text-gray-500">
                                    <div>📦 Mục tiêu: <span class="font-semibold text-gray-800">{{ cam.target_quantity }} {{ cam.target_item }}</span></div>
                                    <div>📍 Vị trí nhận: <span class="font-semibold text-gray-800 line-clamp-1">{{ cam.location_details }}</span></div>
                                </div>
                            </div>
                            <div class="flex justify-end space-x-2">
                                <button @click="moderateCampaign(cam.id, 'active')" class="text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-3 py-1.5 rounded-lg shadow-sm transition">Duyệt xuất bản</button>
                                <button @click="moderateCampaign(cam.id, 'rejected')" class="text-xs bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold px-3 py-1.5 rounded-lg transition">Từ chối</button>
                            </div>
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