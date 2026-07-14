<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Chờ Xác Minh - ShareFood" />

    <div class="min-h-screen bg-gray-50 flex flex-col justify-center items-center px-4 font-sans text-gray-800">
        <div class="max-w-md w-full bg-white border border-gray-100 p-8 rounded-3xl shadow-lg shadow-gray-100 text-center space-y-6">
            <div class="w-16 h-16 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center text-3xl mx-auto shadow-inner">
                ⏳
            </div>
            
            <div class="space-y-2">
                <h1 class="text-xl font-bold text-gray-950">Đang Chờ Ban Quản Trị Xác Minh</h1>
                <p class="text-sm text-gray-500 leading-relaxed">
                    Tài khoản Tổ chức từ thiện của bạn hiện đang chờ Admin phê duyệt hồ sơ pháp lý. Việc này thường diễn ra trong vòng 24 giờ.
                </p>
            </div>

            <div class="bg-amber-50 border border-amber-100 p-4 rounded-2xl text-left text-xs text-amber-800 space-y-1">
                <div class="font-bold">Lưu ý quan trọng:</div>
                <p>Bạn chưa thể tạo chiến dịch quyên góp lớn cho đến khi tài khoản được xác minh (status: verified).</p>
            </div>

            <div class="pt-4 border-t border-gray-100 flex justify-between items-center">
                <Link href="/" class="text-sm text-gray-500 hover:text-gray-950 transition font-medium">Quay lại trang chủ</Link>
                <Link :href="route('logout')" method="post" as="button" class="text-sm text-red-600 hover:text-red-700 transition font-semibold">Đăng xuất</Link>
            </div>
        </div>
    </div>
</template>
