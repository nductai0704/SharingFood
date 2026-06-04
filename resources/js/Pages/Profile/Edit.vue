<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    charityDocuments: {
        type: Object,
        default: () => ({}),
    },
});
</script>

<template>
    <Head title="Cài đặt tài khoản - ShareFood" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold text-gray-950 tracking-tight">
                Cài đặt & Cấu hình tài khoản
            </h2>
        </template>

        <div class="py-10 bg-gray-50/50">
            <div class="mx-auto max-w-3xl space-y-8 px-4 sm:px-6 lg:px-8">
                <!-- Cập nhật thông tin -->
                <div class="bg-white p-6 sm:p-8 rounded-3xl border border-gray-100 shadow-sm">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        :charity-documents="charityDocuments"
                        class="w-full"
                    />
                </div>

                <!-- Đổi mật khẩu -->
                <div class="bg-white p-6 sm:p-8 rounded-3xl border border-gray-100 shadow-sm">
                    <UpdatePasswordForm class="w-full" />
                </div>

                <!-- Xóa tài khoản -->
                <div class="bg-white p-6 sm:p-8 rounded-3xl border border-gray-100 shadow-sm">
                    <DeleteUserForm class="w-full" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
