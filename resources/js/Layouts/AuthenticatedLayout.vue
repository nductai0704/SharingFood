<script setup>
import { ref, computed } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';

const isMobileMenuOpen = ref(false);
const isNotificationOpen = ref(false);
const page = usePage();

const pendingNotifications = computed(() => {
    return page.props.auth.receivedClaims ? page.props.auth.receivedClaims.filter(c => c.status === 'pending') : [];
});

const handleUpdateClaimStatus = (claimId, status) => {
    router.post(route('food-claims.status', claimId), { status }, {
        preserveScroll: true
    });
};
</script>

<template>
  <div class="min-h-screen bg-gray-50 text-gray-800 font-sans">
    <!-- Custom Premium Navbar -->
    <nav class="bg-white border-b border-gray-100 sticky top-0 z-50 shadow-sm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <!-- Logo -->
          <Link :href="$page.props.auth.user && $page.props.auth.user.role === 'charity' ? route('charity.dashboard') : '/'" class="flex items-center space-x-3 group">
            <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-md shadow-emerald-200 group-hover:scale-105 transition-transform duration-200">S</div>
            <span class="text-xl font-bold text-gray-950 tracking-tight group-hover:text-emerald-600 transition-colors duration-200">ShareFood<span class="text-emerald-600">.vn</span></span>
            <span v-if="$page.props.auth.user && $page.props.auth.user.role === 'charity'" class="hidden sm:inline-block bg-emerald-100 text-emerald-800 text-[10px] uppercase tracking-wider px-2.5 py-1 rounded-full font-bold border border-emerald-200">
              Tổ Chức Từ Thiện
            </span>
          </Link>

          <!-- Desktop menu -->
          <div class="hidden md:flex items-center space-x-6">
            <Link :href="route('dashboard')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Trang chủ</Link>
            <Link :href="route('food-posts.index')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Thực phẩm</Link>
            <Link v-if="$page.props.auth.user && $page.props.auth.user.role === 'charity'" :href="route('charity.campaigns')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Chiến dịch từ thiện</Link>
            <div class="h-8 w-px bg-gray-200"></div>
            
            <div v-if="$page.props.auth.user" class="flex items-center space-x-4">
              <Link :href="route('profile.edit')" class="flex items-center space-x-2 text-gray-700 hover:text-emerald-600 transition">
                <img 
                  v-if="$page.props.auth.user.avatar" 
                  :src="$page.props.auth.user.avatar" 
                  class="w-8 h-8 rounded-full object-cover border border-emerald-100 shadow-sm"
                  alt="Avatar"
                />
                <div v-else class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 font-semibold text-sm">
                  {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                </div>
                <span class="text-sm font-medium">{{ $page.props.auth.user.name }}</span>
              </Link>
              <Link :href="route('logout')" method="post" as="button" class="text-sm text-red-600 hover:text-red-700 font-semibold transition">
                Đăng xuất
              </Link>

              <!-- Notification Bell Icon (Next to logout) -->
              <div class="relative">
                <button 
                  @click="isNotificationOpen = !isNotificationOpen" 
                  class="relative p-2 text-gray-500 hover:text-emerald-600 focus:outline-none transition rounded-full hover:bg-gray-50 cursor-pointer"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                  </svg>
                  <!-- Badge số lượng thông báo chưa duyệt -->
                  <span 
                    v-if="pendingNotifications.length > 0" 
                    class="absolute top-1 right-1 bg-red-500 text-white text-[8px] font-bold w-4 h-4 rounded-full flex items-center justify-center border border-white"
                  >
                    {{ pendingNotifications.length }}
                  </span>
                </button>

                <!-- Dropdown thông báo -->
                <div 
                  v-if="isNotificationOpen" 
                  class="absolute right-0 mt-2 w-80 bg-white border border-gray-100 rounded-2xl shadow-xl z-50 overflow-hidden animate-in fade-in slide-in-from-top-2 duration-150"
                >
                  <div class="p-3 border-b border-gray-50 flex justify-between items-center bg-gray-50/50">
                    <span class="text-xs font-bold text-gray-800">Thông báo mới</span>
                    <span class="text-[10px] text-gray-400 font-medium">{{ pendingNotifications.length }} yêu cầu chưa duyệt</span>
                  </div>
                  <div class="max-h-72 overflow-y-auto divide-y divide-gray-50">
                    <div v-if="pendingNotifications.length === 0" class="p-4 text-center text-gray-400 text-xs">
                      Không có thông báo nào mới.
                    </div>
                    <div 
                      v-else 
                      v-for="claim in pendingNotifications" 
                      :key="claim.id" 
                      class="p-3 hover:bg-gray-50/50 space-y-2 transition text-xs"
                    >
                      <div class="flex justify-between items-start gap-1">
                        <p class="text-gray-700 leading-normal text-left">
                          👤 <span class="font-bold text-gray-900">{{ claim.user?.name }}</span> đã xin nhận <span class="font-bold text-emerald-600">{{ claim.quantity }} {{ claim.food_post?.unit }}</span> từ bài viết <b>"{{ claim.food_post?.title }}"</b>
                        </p>
                      </div>
                      <div class="flex justify-between items-center text-[10px] text-gray-400">
                        <span>{{ new Date(claim.created_at).toLocaleString('vi-VN') }}</span>
                      </div>
                      <!-- Các nút thao tác khi ở trạng thái chờ duyệt -->
                      <div class="flex items-center gap-2 pt-1">
                        <button 
                          @click="handleUpdateClaimStatus(claim.id, 'approved')" 
                          class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] py-1 rounded-lg transition text-center cursor-pointer shadow-sm shadow-emerald-100 hover:shadow-emerald-200"
                        >
                          Duyệt
                        </button>
                        <button 
                          @click="handleUpdateClaimStatus(claim.id, 'rejected')" 
                          class="flex-1 bg-gray-50 hover:bg-red-50 text-gray-600 hover:text-red-600 font-semibold text-[10px] py-1 rounded-lg border border-gray-200 hover:border-red-100 transition text-center cursor-pointer"
                        >
                          Từ chối
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Mobile menu button -->
          <div class="flex items-center md:hidden">
            <button @click="isMobileMenuOpen = !isMobileMenuOpen" class="text-gray-500 hover:text-emerald-600 focus:outline-none p-2 rounded-lg bg-gray-50 border border-gray-100">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path v-if="!isMobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Mobile dropdown menu -->
      <div v-if="isMobileMenuOpen" class="md:hidden bg-white border-t border-gray-100 p-4 space-y-4 shadow-inner">
        <div class="flex flex-col space-y-3">
          <Link :href="route('dashboard')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Trang chủ</Link>
          <Link :href="route('food-posts.index')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Thực phẩm</Link>
          <Link v-if="$page.props.auth.user && $page.props.auth.user.role === 'charity'" :href="route('charity.campaigns')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Chiến dịch từ thiện</Link>
        </div>
        
        <div class="h-px bg-gray-100"></div>
        
        <div v-if="$page.props.auth.user" class="flex flex-col space-y-3">
          <Link :href="route('profile.edit')" class="flex items-center space-x-2 text-gray-700 hover:text-emerald-600 transition">
            <img 
              v-if="$page.props.auth.user.avatar" 
              :src="$page.props.auth.user.avatar" 
              class="w-8 h-8 rounded-full object-cover border border-emerald-100 shadow-sm"
              alt="Avatar"
            />
            <div v-else class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 font-semibold text-sm">
              {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
            </div>
            <span class="text-sm font-medium">{{ $page.props.auth.user.name }}</span>
          </Link>
          <Link :href="route('logout')" method="post" as="button" class="text-sm text-left text-red-600 hover:text-red-700 font-semibold transition">
            Đăng xuất
          </Link>
        </div>
      </div>
    </nav>

    <!-- Page Heading -->
    <header class="bg-white border-b border-gray-100 shadow-sm" v-if="$slots.header">
      <div class="max-w-7xl mx-auto px-4 py-5 sm:px-6 lg:px-8">
        <slot name="header" />
      </div>
    </header>

    <!-- Page Content -->
    <main>
      <slot />
    </main>
  </div>
</template>
