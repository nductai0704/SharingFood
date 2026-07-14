<script setup>
import { ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
    email: {
        type: String,
    }
});

const step = ref(1);

const formEmail = useForm({
    email: '',
});

const formReset = useForm({
    email: '',
    otp: '',
    password: '',
    password_confirmation: '',
});

watch(() => props.status, (newStatus) => {
    if (newStatus === 'otp-sent') {
        step.value = 2;
        if (props.email) {
            formReset.email = props.email;
        } else {
            formReset.email = formEmail.email;
        }
    }
}, { immediate: true });

const submitEmail = () => {
    formEmail.post(route('password.email'), {
        onSuccess: () => {
            // will toggle step.value to 2 via watcher
        }
    });
};

const submitReset = () => {
    formReset.post(route('password.store'), {
        onFinish: () => formReset.reset('password', 'password_confirmation'),
        onError: () => {
            // keep on step 2 if errors exist
            step.value = 2;
        }
    });
};

const backToStep1 = () => {
    step.value = 1;
    formEmail.email = formReset.email;
};
</script>

<template>
    <Head title="Quên mật khẩu - ShareFood" />

    <div class="min-h-screen bg-gray-50 text-gray-800 font-sans flex flex-col justify-center items-center p-4">
        <div class="w-full max-w-md bg-white border border-gray-100 rounded-3xl p-8 shadow-xl shadow-gray-100/70 space-y-6">
            
            <div class="flex flex-col items-center space-y-4 text-center">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-md shadow-emerald-200">S</div>
                    <span class="text-xl font-bold text-gray-950 tracking-tight">ShareFood<span class="text-emerald-600">.vn</span></span>
                </div>
                <div class="space-y-1">
                    <h1 class="text-2xl font-bold text-gray-950 tracking-tight">Quên mật khẩu?</h1>
                    <p class="text-xs text-gray-500">
                        {{ step === 1 ? 'Nhập email để nhận mã OTP khôi phục mật khẩu' : 'Nhập mã OTP 6 số và mật khẩu mới' }}
                    </p>
                </div>
            </div>

            <!-- Step 1: Request OTP -->
            <form v-if="step === 1" @submit.prevent="submitEmail" class="space-y-4">
                <div class="space-y-1.5">
                    <label for="email" class="text-xs font-semibold text-gray-700 tracking-wide">Địa chỉ Email của bạn</label>
                    <input 
                        id="email" 
                        type="email" 
                        v-model="formEmail.email" 
                        required 
                        autofocus 
                        class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                        placeholder="ten@viethan.com"
                    />
                    <p v-if="formEmail.errors.email" class="text-xs text-red-500 font-medium mt-1">{{ formEmail.errors.email }}</p>
                </div>

                <button 
                    type="submit" 
                    :disabled="formEmail.processing"
                    class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm py-3 px-4 rounded-xl shadow-md shadow-emerald-100 transition duration-200 disabled:opacity-50"
                >
                    <span v-if="formEmail.processing">Đang gửi OTP...</span>
                    <span v-else>Gửi mã xác thực OTP</span>
                </button>
            </form>

            <!-- Step 2: Input OTP & New Password -->
            <form v-else @submit.prevent="submitReset" class="space-y-4">
                <div class="bg-emerald-50 border border-emerald-100 text-emerald-800 text-xs font-medium p-3 rounded-xl">
                    Mã xác thực OTP đã được gửi đến email <strong class="underline">{{ formReset.email }}</strong>.
                </div>

                <div class="space-y-1.5">
                    <label for="otp" class="text-xs font-semibold text-gray-700 tracking-wide">Mã xác thực OTP (6 số)</label>
                    <input 
                        id="otp" 
                        type="text" 
                        v-model="formReset.otp" 
                        required 
                        maxlength="6"
                        pattern="[0-9]{6}"
                        class="w-full bg-gray-50 border border-gray-200 text-center tracking-widest text-lg font-bold text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                        placeholder="123456"
                    />
                    <p v-if="formReset.errors.otp" class="text-xs text-red-500 font-medium mt-1">{{ formReset.errors.otp }}</p>
                </div>

                <div class="space-y-1.5">
                    <label for="password" class="text-xs font-semibold text-gray-700 tracking-wide">Mật khẩu mới</label>
                    <input 
                        id="password" 
                        type="password" 
                        v-model="formReset.password" 
                        required 
                        class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                        placeholder="••••••••"
                    />
                    <p v-if="formReset.errors.password" class="text-xs text-red-500 font-medium mt-1">{{ formReset.errors.password }}</p>
                </div>

                <div class="space-y-1.5">
                    <label for="password_confirmation" class="text-xs font-semibold text-gray-700 tracking-wide">Xác nhận mật khẩu mới</label>
                    <input 
                        id="password_confirmation" 
                        type="password" 
                        v-model="formReset.password_confirmation" 
                        required 
                        class="w-full bg-gray-50 border border-gray-200 text-sm text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                        placeholder="••••••••"
                    />
                    <p v-if="formReset.errors.password_confirmation" class="text-xs text-red-500 font-medium mt-1">{{ formReset.errors.password_confirmation }}</p>
                </div>

                <button 
                    type="submit" 
                    :disabled="formReset.processing"
                    class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm py-3 px-4 rounded-xl shadow-md shadow-emerald-100 transition duration-200 disabled:opacity-50"
                >
                    <span v-if="formReset.processing">Đang xác thực và đổi mật khẩu...</span>
                    <span v-else>Xác nhận đổi mật khẩu</span>
                </button>

                <button 
                    type="button" 
                    @click="backToStep1"
                    class="w-full text-center text-xs text-gray-500 hover:underline font-semibold"
                >
                    Quay lại bước nhập email
                </button>
            </form>

            <div class="h-px bg-gray-100 my-4"></div>

            <p class="text-center text-xs text-gray-500">
                Quay lại 
                <Link :href="route('login')" class="text-emerald-600 font-semibold hover:underline">Đăng nhập</Link>
            </p>
        </div>
    </div>
</template>
