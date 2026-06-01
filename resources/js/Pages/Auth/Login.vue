<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Đăng nhập - ShareFood" />

    <div class="min-h-screen bg-gray-50 text-gray-800 font-sans flex flex-col justify-center items-center p-4">
        <div class="w-full max-w-md bg-white border border-gray-100 rounded-3xl p-8 shadow-xl shadow-gray-100/70 space-y-6">
            
            <div class="flex flex-col items-center space-y-4 text-center">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-md shadow-emerald-200">S</div>
                    <span class="text-xl font-bold text-gray-950 tracking-tight">ShareFood<span class="text-emerald-600">.vn</span></span>
                </div>
                <div class="space-y-1">
                    <h1 class="text-2xl font-bold text-gray-950 tracking-tight">Chào mừng trở lại</h1>
                    <p class="text-xs text-gray-500">Đăng nhập để tiếp tục chia sẻ và nhận thực phẩm</p>
                </div>
            </div>

            <div v-if="status" class="bg-emerald-50 border border-emerald-100 text-emerald-800 text-xs font-medium p-3 rounded-xl animate-fade-in">
                {{ status }}
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-1.5">
                    <label for="email" class="text-xs font-semibold text-gray-700 tracking-wide">Địa chỉ Email</label>
                    <input 
                        id="email" 
                        type="email" 
                        v-model="form.email" 
                        required 
                        autofocus 
                        autocomplete="username"
                        class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                        placeholder="ten@viethan.com"
                    />
                    <p v-if="form.errors.email" class="text-xs text-red-500 font-medium mt-1">{{ form.errors.email }}</p>
                </div>

                <div class="space-y-1.5">
                    <div class="flex justify-between items-center">
                        <label for="password" class="text-xs font-semibold text-gray-700 tracking-wide">Mật khẩu</label>
                        <Link 
                            v-if="canResetPassword" 
                            :href="route('password.request')" 
                            class="text-xs text-emerald-600 hover:underline font-medium"
                        >
                            Quên mật khẩu?
                        </Link>
                    </div>
                    <input 
                        id="password" 
                        type="password" 
                        v-model="form.password" 
                        required 
                        autocomplete="current-password"
                        class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                        placeholder="••••••••"
                    />
                    <p v-if="form.errors.password" class="text-xs text-red-500 font-medium mt-1">{{ form.errors.password }}</p>
                </div>

                <div class="flex items-center">
                    <input 
                        id="remember" 
                        type="checkbox" 
                        v-model="form.remember" 
                        class="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500 cursor-pointer"
                    />
                    <label for="remember" class="ml-2 text-xs font-medium text-gray-600 cursor-pointer select-none">Duy trì đăng nhập</label>
                </div>

                <button 
                    type="submit" 
                    :disabled="form.processing"
                    class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm py-3 px-4 rounded-xl shadow-md shadow-emerald-100 transition duration-200 disabled:opacity-50"
                >
                    <span v-if="form.processing">Đang xác thực dữ liệu...</span>
                    <span v-else>Đăng nhập hệ thống</span>
                </button>
            </form>

            <div class="h-px bg-gray-100 my-4"></div>

            <p class="text-center text-xs text-gray-500">
                Chưa có tài khoản thành viên? 
                <Link :href="route('register')" class="text-emerald-600 font-semibold hover:underline">Đăng ký ngay</Link>
            </p>
        </div>
    </div>
</template>