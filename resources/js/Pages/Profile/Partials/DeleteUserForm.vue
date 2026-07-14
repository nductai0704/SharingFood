<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-lg font-bold text-gray-950">
                Xóa tài khoản vĩnh viễn
            </h2>

            <p class="mt-1 text-xs text-gray-500">
                Khi tài khoản bị xóa, toàn bộ dữ liệu, lịch sử quyên góp và các bài đăng liên quan sẽ bị xóa vĩnh viễn khỏi cơ sở dữ liệu. Hãy cân nhắc kỹ trước khi thực hiện.
            </p>
        </header>

        <DangerButton @click="confirmUserDeletion" class="bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-xl px-4 py-2.5 shadow-md shadow-red-100 transition duration-200">
            Yêu cầu xóa tài khoản
        </DangerButton>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-950">
                    Bạn có chắc chắn muốn xóa tài khoản này không?
                </h2>

                <p class="mt-2 text-xs text-gray-500 leading-relaxed">
                    Hành động này không thể hoàn tác. Vui lòng nhập mật khẩu hiện tại của bạn để xác nhận yêu cầu xóa tài khoản vĩnh viễn.
                </p>

                <div class="mt-4">
                    <InputLabel
                        for="password"
                        value="Mật khẩu hiện tại"
                        class="sr-only"
                    />

                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-3/4"
                        placeholder="Nhập mật khẩu để xác nhận"
                        @keyup.enter="deleteUser"
                    />

                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeModal" class="rounded-xl text-xs font-semibold px-4 py-2">
                        Hủy bỏ
                    </SecondaryButton>

                    <DangerButton
                        class="bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-semibold px-4 py-2 shadow-md shadow-red-100 transition"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        Xác nhận xóa
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
