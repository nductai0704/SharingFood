<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    receivingClaims: {
        type: Array,
        default: () => [],
    },
    givingClaims: {
        type: Array,
        default: () => [],
    },
});

const activeSubTab = ref('receiving'); // 'receiving' or 'giving'

const getStatusBadgeClass = (status) => {
    switch (status) {
        case 'completed':
            return 'bg-emerald-50 text-emerald-700 border-emerald-100';
        case 'pending':
            return 'bg-amber-50 text-amber-700 border-amber-100';
        case 'approved':
            return 'bg-blue-50 text-blue-700 border-blue-100';
        case 'rejected':
            return 'bg-red-50 text-red-700 border-red-100';
        case 'cancelled':
        default:
            return 'bg-gray-50 text-gray-600 border-gray-100';
    }
};

const getStatusLabel = (status) => {
    switch (status) {
        case 'completed':
            return 'Đã hoàn thành';
        case 'pending':
            return 'Chờ phê duyệt';
        case 'approved':
            return 'Đã duyệt (Chờ lấy)';
        case 'rejected':
            return 'Từ chối';
        case 'cancelled':
            return 'Đã hủy';
        default:
            return status;
    }
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-lg font-bold text-gray-950">
                Lịch sử giao dịch
            </h2>
            <p class="mt-1 text-xs text-gray-500">
                Theo dõi toàn bộ lịch sử xin nhận thực phẩm từ cộng đồng hoặc đóng góp chia sẻ cho người khác.
            </p>
        </header>

        <!-- Sub-tabs -->
        <div class="flex border border-gray-100 bg-gray-50/50 rounded-2xl p-1 shadow-sm">
            <button
                @click="activeSubTab = 'receiving'"
                :class="[
                    activeSubTab === 'receiving'
                        ? 'bg-white text-emerald-700 font-bold shadow-sm'
                        : 'text-gray-600 hover:text-emerald-600'
                ]"
                class="flex-1 py-2 px-3 text-center rounded-xl font-semibold text-xs transition duration-200 cursor-pointer flex items-center justify-center gap-1.5"
            >
                📥 Lịch sử nhận thực phẩm ({{ receivingClaims.length }})
            </button>
            <button
                @click="activeSubTab = 'giving'"
                :class="[
                    activeSubTab === 'giving'
                        ? 'bg-white text-emerald-700 font-bold shadow-sm'
                        : 'text-gray-600 hover:text-emerald-600'
                ]"
                class="flex-1 py-2 px-3 text-center rounded-xl font-semibold text-xs transition duration-200 cursor-pointer flex items-center justify-center gap-1.5"
            >
                📤 Lịch sử cho thực phẩm ({{ givingClaims.length }})
            </button>
        </div>

        <!-- Content list -->
        <div class="space-y-3 max-h-[400px] overflow-y-auto pr-1">
            <!-- Tab 1: Receiving Claims -->
            <div v-if="activeSubTab === 'receiving'" class="space-y-3">
                <div v-if="receivingClaims.length === 0" class="text-center py-8 text-gray-400 text-xs italic">
                    Bạn chưa có lịch sử nhận thực phẩm nào.
                </div>
                <div
                    v-for="claim in receivingClaims"
                    :key="'rec-' + claim.id"
                    class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm hover:shadow transition duration-200 space-y-3"
                >
                    <div class="flex justify-between items-start gap-4">
                        <div>
                            <h4 class="font-bold text-gray-900 text-sm leading-snug">
                                {{ claim.food_post?.title || 'Thực phẩm' }}
                            </h4>
                            <p class="text-[11px] text-gray-500 mt-1">
                                Người cho: <span class="font-semibold text-gray-700">{{ claim.food_post?.user?.name || 'Cộng đồng' }}</span>
                            </p>
                        </div>
                        <span
                            :class="getStatusBadgeClass(claim.status)"
                            class="text-[9px] uppercase tracking-wider font-bold px-2 py-0.5 rounded-full border shrink-0"
                        >
                            {{ getStatusLabel(claim.status) }}
                        </span>
                    </div>

                    <div class="flex justify-between items-center text-xs text-gray-500 pt-2 border-t border-gray-50">
                        <span>Số lượng: <strong class="text-gray-800">{{ claim.quantity }} {{ claim.food_post?.unit }}</strong></span>
                        <span class="text-[10px]">{{ formatDate(claim.created_at) }}</span>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Giving Claims -->
            <div v-if="activeSubTab === 'giving'" class="space-y-3">
                <div v-if="givingClaims.length === 0" class="text-center py-8 text-gray-400 text-xs italic">
                    Bạn chưa chia sẻ thực phẩm nào được người khác yêu cầu nhận.
                </div>
                <div
                    v-for="claim in givingClaims"
                    :key="'giv-' + claim.id"
                    class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm hover:shadow transition duration-200 space-y-3"
                >
                    <div class="flex justify-between items-start gap-4">
                        <div>
                            <h4 class="font-bold text-gray-900 text-sm leading-snug">
                                {{ claim.food_post?.title || 'Thực phẩm' }}
                            </h4>
                            <p class="text-[11px] text-gray-500 mt-1">
                                Người xin nhận: <span class="font-semibold text-gray-700">{{ claim.user?.name || 'Người dùng' }}</span>
                            </p>
                        </div>
                        <span
                            :class="getStatusBadgeClass(claim.status)"
                            class="text-[9px] uppercase tracking-wider font-bold px-2 py-0.5 rounded-full border shrink-0"
                        >
                            {{ getStatusLabel(claim.status) }}
                        </span>
                    </div>

                    <div class="flex justify-between items-center text-xs text-gray-500 pt-2 border-t border-gray-50">
                        <span>Số lượng: <strong class="text-gray-800">{{ claim.quantity }} {{ claim.food_post?.unit }}</strong></span>
                        <span class="text-[10px]">{{ formatDate(claim.created_at) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
