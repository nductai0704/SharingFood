<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const inputToken = ref('');

const submitMockScan = () => {
    // Gọi API Backend tại đây để báo là mã đã được quét
    // Đảm bảo là route này đã được định nghĩa bên Backend.
    router.post(route('qr.verify'), { token: inputToken.value });
};
</script>

<template>
    <Head title="Quét mã QR - ShareFood Mobile" />
    <div class="min-h-screen bg-gray-900 flex flex-col items-center justify-center p-4">
        
        <!-- Giao diện giả lập khung Camera -->
        <div class="w-full max-w-sm aspect-square bg-gray-800 rounded-3xl border-4 border-gray-700 relative overflow-hidden flex items-center justify-center mb-8 shadow-2xl">
            <div class="absolute inset-0 bg-emerald-500/10 animate-pulse"></div>
            <div class="w-2/3 h-2/3 border-2 border-emerald-500/50 rounded-xl"></div>
            <span class="absolute bottom-6 text-gray-400 text-sm">Giao diện Camera giả lập</span>
        </div>

        <!-- Khung nhập Token thủ công -->
        <div class="w-full max-w-sm bg-white p-6 rounded-3xl space-y-4">
            <h2 class="text-xl font-bold text-gray-900">Mô phỏng quét mã</h2>
            <p class="text-sm text-gray-500">Dán chuỗi Token đối soát từ máy tính vào đây để mô phỏng hành động quét QR thành công.</p>
            
            <input 
                v-model="inputToken"
                type="text" 
                placeholder="Nhập mã token..."
                class="w-full bg-gray-50 border border-gray-200 text-gray-900 rounded-xl px-4 py-3 focus:ring-2 focus:ring-emerald-500"
            />
            
            <button 
                @click="submitMockScan"
                class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl transition shadow-lg shadow-emerald-200"
            >
                Xác nhận Quét ảo
            </button>
        </div>
    </div>
</template>
