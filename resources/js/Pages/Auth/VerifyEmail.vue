<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
    email: {
        type: String,
    }
});

const form = useForm({
    otp: '',
});

const formResend = useForm({});

const submit = () => {
    form.post(route('verification.verify.otp'));
};

const resendOTP = () => {
    formResend.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <Head title="Xác minh Email - ShareFood" />

    <div class="min-h-screen bg-gray-50 text-gray-800 font-sans flex flex-col justify-center items-center p-4">
        <div class="w-full max-w-md bg-white border border-gray-100 rounded-3xl p-8 shadow-xl shadow-gray-100/70 space-y-6">
            
            <div class="flex flex-col items-center space-y-4 text-center">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-md shadow-emerald-200">S</div>
                    <span class="text-xl font-bold text-gray-950 tracking-tight">ShareFood<span class="text-emerald-600">.vn</span></span>
                </div>
                <div class="space-y-1">
                    <h1 class="text-2xl font-bold text-gray-950 tracking-tight">Xác thực tài khoản</h1>
                    <p class="text-xs text-gray-500">Mã xác thực OTP đã được gửi đến email của bạn</p>
                </div>
            </div>

            <div class="text-sm text-gray-600 text-center leading-relaxed">
                Cảm ơn bạn đã đăng ký thành viên! Vui lòng kiểm tra hộp thư điện tử 
                <strong v-if="email" class="text-emerald-700 block mt-1 underline">{{ email }}</strong>
                và nhập mã OTP 6 số để kích hoạt tài khoản của bạn.
            </div>

            <div
                class="bg-emerald-50 border border-emerald-100 text-emerald-800 text-xs font-semibold p-3.5 rounded-xl text-center"
                v-if="verificationLinkSent"
            >
                Mã OTP mới đã được gửi lại vào email của bạn thành công.
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-1.5">
                    <label for="otp" class="text-xs font-semibold text-gray-700 tracking-wide block text-center">Mã xác thực OTP (6 số)</label>
                    <input 
                        id="otp" 
                        type="text" 
                        v-model="form.otp" 
                        required 
                        maxlength="6"
                        pattern="[0-9]{6}"
                        class="w-full bg-gray-50 border border-gray-200 text-center tracking-widest text-lg font-bold text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                        placeholder="123456"
                    />
                    <p v-if="form.errors.otp" class="text-xs text-red-500 font-medium mt-1 text-center">{{ form.errors.otp }}</p>
                </div>

                <button 
                    type="submit" 
                    :disabled="form.processing"
                    class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm py-3 px-4 rounded-xl shadow-md shadow-emerald-100 transition duration-200 disabled:opacity-50"
                >
                    <span v-if="form.processing">Đang xác thực tài khoản...</span>
                    <span v-else>Xác nhận mã OTP</span>
                </button>
            </form>

            <div class="flex items-center justify-between text-xs pt-2">
                <button
                    @click="resendOTP"
                    :disabled="formResend.processing"
                    class="text-emerald-600 hover:underline font-semibold disabled:opacity-50"
                >
                    Gửi lại mã OTP
                </button>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="text-gray-500 hover:text-red-600 font-semibold transition"
                >
                    Đăng xuất
                </Link>
            </div>
        </div>
    </div>
</template>
