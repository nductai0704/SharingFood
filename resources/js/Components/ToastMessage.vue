<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const show = ref(false);
const message = ref('');
const type = ref(''); // 'success' or 'error'
let timeoutId = null;

const displayToast = (newSuccess, newError) => {
    let triggered = false;
    
    if (newSuccess) {
        message.value = newSuccess;
        type.value = 'success';
        show.value = true;
        page.props.flash.success = null; // Clear to prevent spam
        triggered = true;
    } else if (newError) {
        message.value = newError;
        type.value = 'error';
        show.value = true;
        page.props.flash.error = null; // Clear to prevent spam
        triggered = true;
    }

    if (triggered) {
        if (timeoutId) clearTimeout(timeoutId);
        timeoutId = setTimeout(() => {
            show.value = false;
        }, 3000); // Ẩn nhanh hơn (3 giây)
    }
};

// Theo dõi sự thay đổi của flash messages
watch(() => page.props.flash, (flash) => {
    if (flash) {
        displayToast(flash.success, flash.error);
    }
}, { deep: true });

onMounted(() => {
    if (page.props.flash) {
        displayToast(page.props.flash.success, page.props.flash.error);
    }
});

onUnmounted(() => {
    if (timeoutId) clearTimeout(timeoutId);
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="transform translate-y-10 opacity-0"
        enter-to-class="transform translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="transform translate-y-0 opacity-100"
        leave-to-class="transform translate-y-10 opacity-0"
    >
        <div v-if="show" class="fixed bottom-10 left-1/2 -translate-x-1/2 z-[100] flex flex-col items-center pointer-events-none">
            <div 
                v-if="type === 'success'" 
                class="bg-gray-900/90 text-white backdrop-blur-md px-6 py-3 rounded-full shadow-2xl pointer-events-auto flex items-center gap-3 border border-gray-700/50" 
                role="alert"
            >
                <div class="bg-emerald-500 rounded-full w-6 h-6 flex items-center justify-center text-xs shrink-0">✓</div>
                <span class="block font-medium text-sm">{{ message }}</span>
            </div>
            
            <div 
                v-if="type === 'error'" 
                class="bg-gray-900/90 text-white backdrop-blur-md px-6 py-3 rounded-full shadow-2xl pointer-events-auto flex items-center gap-3 border border-red-500/30" 
                role="alert"
            >
                <div class="bg-red-500 rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold shrink-0">!</div>
                <span class="block font-medium text-sm">{{ message }}</span>
            </div>
        </div>
    </Transition>
</template>
