<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const isMobileMenuOpen = ref(false);
</script>

<template>
  <div class="min-h-screen bg-gray-50 text-gray-800 font-sans">
    <!-- Custom Premium Navbar -->
    <nav class="bg-white border-b border-gray-100 sticky top-0 z-50 shadow-sm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <!-- Logo -->
          <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-md shadow-emerald-200">S</div>
            <span class="text-xl font-bold text-gray-950 tracking-tight">ShareFood<span class="text-emerald-600">.vn</span></span>
          </div>

          <!-- Desktop menu -->
          <div class="hidden md:flex items-center space-x-6">
            <Link href="/" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Trang chủ</Link>
            <Link :href="route('dashboard')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Bảng điều khiển</Link>
            <div class="h-8 w-px bg-gray-200"></div>
            
            <div v-if="$page.props.auth.user" class="flex items-center space-x-4">
              <Link :href="route('profile.edit')" class="flex items-center space-x-2 text-gray-700 hover:text-emerald-600 transition">
                <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 font-semibold text-sm">
                  {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                </div>
                <span class="text-sm font-medium">{{ $page.props.auth.user.name }}</span>
              </Link>
              <Link :href="route('logout')" method="post" as="button" class="text-xs text-red-600 hover:text-red-700 font-semibold transition">
                Đăng xuất
              </Link>
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
          <Link href="/" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Trang chủ</Link>
          <Link :href="route('dashboard')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Bảng điều khiển</Link>
        </div>
        
        <div class="h-px bg-gray-100"></div>
        
        <div v-if="$page.props.auth.user" class="flex flex-col space-y-3">
          <Link :href="route('profile.edit')" class="flex items-center space-x-2 text-gray-700 hover:text-emerald-600 transition">
            <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 font-semibold text-sm">
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
