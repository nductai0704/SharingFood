<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import TransactionHistory from './Partials/TransactionHistory.vue';
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
    receivingClaims: {
        type: Array,
        default: () => [],
    },
    givingClaims: {
        type: Array,
        default: () => [],
    },
});

const activeTab = ref('info'); // 'info', 'history', 'security'

const activeTabTitle = computed(() => {
    switch (activeTab.value) {
        case 'info':
            return 'Thông tin cá nhân';
        case 'history':
            return 'Lịch sử giao dịch';
        case 'security':
            return 'Đổi mật khẩu & Bảo mật';
        default:
            return 'Cài đặt tài khoản';
    }
});
</script>

<template>
    <Head :title="activeTabTitle + ' - ShareFood'" />

    <AuthenticatedLayout>

        <div class="py-10 bg-gray-50/50">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <!-- Left Tab Selector Sidebar Menu -->
                    <div class="md:col-span-1">
                        <div class="flex flex-col gap-2 bg-white rounded-3xl p-3 border border-gray-150/60 shadow-sm md:sticky md:top-20">
                            <button
                                @click="activeTab = 'info'"
                                :class="[
                                    activeTab === 'info'
                                        ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-100'
                                        : 'text-gray-600 hover:text-emerald-600 hover:bg-emerald-50/50'
                                ]"
                                class="w-full py-3 px-4 text-left rounded-2xl font-semibold text-xs transition duration-200 cursor-pointer flex items-center gap-2"
                            >
                                👤 Thông tin cá nhân
                            </button>
                            <button
                                @click="activeTab = 'history'"
                                :class="[
                                    activeTab === 'history'
                                        ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-100'
                                        : 'text-gray-600 hover:text-emerald-600 hover:bg-emerald-50/50'
                                ]"
                                class="w-full py-3 px-4 text-left rounded-2xl font-semibold text-xs transition duration-200 cursor-pointer flex items-center gap-2"
                            >
                                📜 Lịch sử giao dịch
                            </button>
                            <button
                                @click="activeTab = 'security'"
                                :class="[
                                    activeTab === 'security'
                                        ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-100'
                                        : 'text-gray-600 hover:text-emerald-600 hover:bg-emerald-50/50'
                                ]"
                                class="w-full py-3 px-4 text-left rounded-2xl font-semibold text-xs transition duration-200 cursor-pointer flex items-center gap-2"
                            >
                                🔑 Mật khẩu & Bảo mật
                            </button>
                        </div>
                    </div>

                    <!-- Right Tab content container -->
                    <div class="md:col-span-3 space-y-6">
                        <!-- Tab 1: Personal Info -->
                        <div v-if="activeTab === 'info'" class="bg-white p-6 sm:p-8 rounded-3xl border border-gray-100 shadow-sm animate-in fade-in duration-200">
                            <UpdateProfileInformationForm
                                :must-verify-email="mustVerifyEmail"
                                :status="status"
                                :charity-documents="charityDocuments"
                                class="w-full"
                            />
                        </div>

                        <!-- Tab 2: Transaction History -->
                        <div v-if="activeTab === 'history'" class="bg-white p-6 sm:p-8 rounded-3xl border border-gray-100 shadow-sm animate-in fade-in duration-200">
                            <TransactionHistory
                                :receiving-claims="receivingClaims"
                                :giving-claims="givingClaims"
                                class="w-full"
                            />
                        </div>

                        <!-- Tab 3: Security & Password / Delete -->
                        <div v-if="activeTab === 'security'" class="space-y-6 animate-in fade-in duration-200">
                            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-gray-100 shadow-sm">
                                <UpdatePasswordForm class="w-full" />
                            </div>

                            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-gray-100 shadow-sm">
                                <DeleteUserForm class="w-full" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
