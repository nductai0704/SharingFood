<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Charity Dashboard - ShareFood" />

    <div class="min-h-screen bg-gray-50 text-gray-800 font-sans">
        <nav class="bg-white border-b border-gray-100 sticky top-0 z-50 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16 items-center">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-md shadow-emerald-200">S</div>
                        <span class="text-xl font-bold text-gray-950 tracking-tight">ShareFood<span class="text-emerald-600">.vn</span></span>
                        <span class="bg-emerald-100 text-emerald-800 text-xs px-2 py-0.5 rounded-full font-medium">Tổ Chức Từ Thiện</span>
                    </div>
                    <div class="flex items-center space-x-6">
                        <Link :href="route('logout')" method="post" as="button" class="text-sm text-gray-600 hover:text-gray-900 transition font-medium">Đăng xuất</Link>
                    </div>
                </div>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
            <div class="bg-emerald-600 p-8 rounded-3xl text-white shadow-xl shadow-emerald-100">
                <h1 class="text-2xl font-bold">Khu Vực Tổ Chức Từ Thiện</h1>
                <p class="text-emerald-100 mt-2">Nơi khởi tạo chiến dịch quyên góp thực phẩm lớn và quản lý các lượt phân phối.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Campaigns block -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 space-y-4">
                    <h2 class="text-lg font-bold text-gray-900">Chiến dịch của bạn</h2>
                    <p class="text-sm text-gray-500">Các chiến dịch tiếp nhận gạo, đồ hộp, mì gói... quy mô lớn đang hoạt động.</p>
                    <button class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm py-2 px-4 rounded-xl transition">Tạo chiến dịch mới</button>
                </div>

                <!-- History log -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 space-y-4">
                    <h2 class="text-lg font-bold text-gray-900">Lịch sử tiếp nhận</h2>
                    <p class="text-sm text-gray-500">Danh sách các cam kết đóng góp hiện vật từ nhà hảo tâm.</p>
                </div>
            </div>
        </main>
    </div>
</template>
