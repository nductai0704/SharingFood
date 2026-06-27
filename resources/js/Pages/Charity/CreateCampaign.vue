<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const form = useForm({
    title: '',
    description: '',
    location_details: '',
    end_date: '',
    items: [
        { item_name: '', target_quantity: 1 }
    ],
});

// Thêm một dòng vật phẩm mới
const addItem = () => {
    form.items.push({
        item_name: '',
        target_quantity: 1
    });
};

// Xóa một dòng vật phẩm
const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const submit = () => {
    form.post(route('charity.campaigns.store'), {
        preserveScroll: true,
        onSuccess: () => {
            // Xử lý sau khi thành công nếu cần
        }
    });
};
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Khởi tạo chiến dịch quyên góp" />

    <div class="min-h-screen bg-gray-50 py-10">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Header -->
            <div class="mb-8">
                <Link :href="route('charity.campaigns')" class="text-sm text-emerald-600 hover:text-emerald-700 font-medium flex items-center gap-1 mb-4">
                    <span>&larr;</span> Quay lại danh sách

                </Link>
                <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Khởi tạo Chiến dịch Quyên góp lớn</h1>
                <p class="mt-2 text-sm text-gray-500">
                    Chiến dịch sẽ cần được quản trị viên phê duyệt trước khi hiển thị công khai tới cộng đồng.
                </p>
            </div>

            <!-- Main Form -->
            <form @submit.prevent="submit" class="bg-white shadow-xl shadow-emerald-100/50 rounded-2xl overflow-hidden">
                <div class="p-8 space-y-8">
                    
                    <!-- Phần 1: Thông tin chung -->
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 border-b border-gray-100 pb-2 mb-4">Thông tin chung</h2>
                        <div class="grid grid-cols-1 gap-6">
                            
                            <!-- Tiêu đề -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Tên chiến dịch <span class="text-red-500">*</span></label>
                                <input v-model="form.title" type="text" class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 shadow-sm" placeholder="VD: Ủng hộ đồng bào lũ lụt miền Trung" required>
                                <div v-if="form.errors.title" class="text-red-500 text-xs mt-1">{{ form.errors.title }}</div>
                            </div>

                            <!-- Địa chỉ tập kết -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Địa điểm tập kết vật lý <span class="text-red-500">*</span></label>
                                <input v-model="form.location_details" type="text" class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 shadow-sm" placeholder="Địa chỉ chi tiết nhận hàng quyên góp..." required>
                                <div v-if="form.errors.location_details" class="text-red-500 text-xs mt-1">{{ form.errors.location_details }}</div>
                            </div>

                            <!-- Thời gian -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Ngày kết thúc quyên góp <span class="text-red-500">*</span></label>
                                <input v-model="form.end_date" type="date" class="w-full md:w-1/2 rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 shadow-sm" required>
                                <div v-if="form.errors.end_date" class="text-red-500 text-xs mt-1">{{ form.errors.end_date }}</div>
                            </div>

                            <!-- Mô tả -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Mô tả hoàn cảnh / Mục đích</label>
                                <textarea v-model="form.description" rows="4" class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 shadow-sm" placeholder="Chia sẻ câu chuyện và lý do để mọi người hiểu rõ hơn về chiến dịch..."></textarea>
                                <div v-if="form.errors.description" class="text-red-500 text-xs mt-1">{{ form.errors.description }}</div>
                            </div>

                        </div>
                    </div>

                    <!-- Phần 2: Danh sách nhu yếu phẩm -->
                    <div>
                        <div class="flex justify-between items-end border-b border-gray-100 pb-2 mb-4">
                            <h2 class="text-lg font-bold text-gray-900">Danh sách nhu yếu phẩm cần gọi</h2>
                            <button type="button" @click="addItem" class="text-sm bg-emerald-100 text-emerald-700 hover:bg-emerald-200 font-bold px-3 py-1.5 rounded-lg transition flex items-center gap-1">
                                ➕ Thêm vật phẩm
                            </button>
                        </div>
                        
                        <div v-if="form.errors.items" class="text-red-500 text-sm mb-4 font-medium p-3 bg-red-50 rounded-xl border border-red-100">
                            {{ form.errors.items }}
                        </div>

                        <div class="space-y-4">
                            <!-- Hiển thị mảng động items -->
                            <div v-for="(item, index) in form.items" :key="index" class="p-4 bg-gray-50 border border-gray-200 rounded-xl relative group transition-all hover:border-emerald-300">
                                <div class="flex flex-col md:flex-row gap-4 items-end">
                                    
                                    <!-- Item Name -->
                                    <div class="flex-1 w-full">
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Tên vật phẩm cần kêu gọi <span class="text-red-500">*</span></label>
                                        <input v-model="item.item_name" type="text" class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 shadow-sm text-sm" placeholder="VD: Gạo tẻ ST25, Sữa Vinamilk..." required>
                                        <div v-if="form.errors[`items.${index}.item_name`]" class="text-red-500 text-xs mt-1">{{ form.errors[`items.${index}.item_name`] }}</div>
                                    </div>

                                    <!-- Target Quantity -->
                                    <div class="w-full md:w-48">
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Số lượng mục tiêu <span class="text-red-500">*</span></label>
                                        <input v-model="item.target_quantity" type="number" min="1" class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 shadow-sm text-sm" required>
                                        <div v-if="form.errors[`items.${index}.target_quantity`]" class="text-red-500 text-xs mt-1">{{ form.errors[`items.${index}.target_quantity`] }}</div>
                                    </div>

                                    <!-- Remove Button -->
                                    <div class="flex-shrink-0">
                                        <button type="button" @click="removeItem(index)" :disabled="form.items.length <= 1" class="text-red-500 hover:text-red-700 p-2 rounded-lg hover:bg-red-50 transition disabled:opacity-30 disabled:cursor-not-allowed font-bold" title="Xóa">
                                            ✕
                                        </button>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="bg-gray-50 px-8 py-5 border-t border-gray-100 flex items-center justify-end gap-3">
                    <Link :href="route('charity.campaigns')" class="text-gray-600 hover:text-gray-900 font-medium text-sm px-4 py-2 transition">Hủy bỏ</Link>
                    <button type="submit" :disabled="form.processing" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-6 rounded-xl shadow-md transition disabled:opacity-50">
                        <span v-if="form.processing">Đang xử lý...</span>
                        <span v-else>Gửi phê duyệt chiến dịch</span>
                    </button>
                </div>
            </form>

        </div>
    </div>
  </AuthenticatedLayout>
</template>
