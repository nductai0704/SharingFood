<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';

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

const user = usePage().props.auth.user;

const form = useForm({
    _method: 'PATCH', // Dùng phương thức POST với _method PATCH để hỗ trợ gửi File (Inertia)
    name: user.name,
    email: user.email,
    phone: user.phone || '',
    address: user.address || '',
    latitude: user.latitude ? parseFloat(user.latitude) : null,
    longitude: user.longitude ? parseFloat(user.longitude) : null,
    avatar: null,
});

const avatarPreview = ref(null);
const avatarInput = ref(null);

const handleAvatarChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.avatar = file;
        avatarPreview.value = URL.createObjectURL(file);
    }
};

const resetAvatar = () => {
    form.avatar = null;
    avatarPreview.value = null;
    if (avatarInput.value) {
        avatarInput.value.value = '';
    }
};

const submitForm = () => {
    form.post(route('profile.update'), {
        forceFormData: true,
    });
};

const mapContainer = ref(null);
let map = null;
let marker = null;

const loadLeaflet = () => {
    return new Promise((resolve) => {
        if (window.L) {
            resolve(window.L);
            return;
        }

        // Tải CSS
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(link);

        // Tải JS
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.onload = () => resolve(window.L);
        document.head.appendChild(script);
    });
};

const updateMapPosition = (lat, lng, zoom = null) => {
    if (map && marker && lat && lng) {
        marker.setLatLng([lat, lng]);
        if (zoom) {
            map.setView([lat, lng], zoom);
        } else {
            map.panTo([lat, lng]);
        }
    }
};

onMounted(async () => {
    const L = await loadLeaflet();

    // Sửa lỗi hiển thị Marker Icon mặc định của Leaflet khi build từ CDN
    const DefaultIcon = L.icon({
        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });
    L.Marker.prototype.options.icon = DefaultIcon;

    // Tọa độ mặc định (TP.HCM) nếu chưa có tọa độ
    const defaultLat = form.latitude || 10.776889;
    const defaultLng = form.longitude || 106.700806;
    const initialZoom = form.latitude ? 16 : 13;

    // Tạo map
    map = L.map(mapContainer.value).setView([defaultLat, defaultLng], initialZoom);

    // Thêm TileLayer từ OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    // Thêm Marker
    marker = L.marker([defaultLat, defaultLng], {
        draggable: true
    }).addTo(map);

    // Cập nhật tọa độ khi thả Marker
    marker.on('dragend', async () => {
        const position = marker.getLatLng();
        form.latitude = parseFloat(position.lat.toFixed(6));
        form.longitude = parseFloat(position.lng.toFixed(6));
        await reverseGeocode(form.latitude, form.longitude);
    });

    // Cập nhật tọa độ khi click trên bản đồ
    map.on('click', async (e) => {
        const { lat, lng } = e.latlng;
        marker.setLatLng([lat, lng]);
        form.latitude = parseFloat(lat.toFixed(6));
        form.longitude = parseFloat(lng.toFixed(6));
        await reverseGeocode(form.latitude, form.longitude);
    });
});

// Lấy địa chỉ chữ từ tọa độ GPS (Reverse Geocoding)
const reverseGeocode = async (lat, lng) => {
    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`, {
            headers: {
                'Accept-Language': 'vi'
            }
        });
        const data = await response.json();
        if (data && data.display_name) {
            form.address = data.display_name;
        }
    } catch (error) {
        console.error("Lỗi phân tích địa chỉ từ tọa độ: ", error);
    }
};

// Tìm kiếm địa chỉ trực tiếp trên bản đồ (Geocoding từ ô Search)
const mapSearchInput = ref(null);
const searchMapAddress = async () => {
    const query = mapSearchInput.value?.value;
    if (!query || query.trim().length < 3) return;

    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`, {
            headers: {
                'Accept-Language': 'vi'
            }
        });
        const data = await response.json();
        
        if (data && data.length > 0) {
            const result = data[0];
            const lat = parseFloat(parseFloat(result.lat).toFixed(6));
            const lng = parseFloat(parseFloat(result.lon).toFixed(6));
            
            form.latitude = lat;
            form.longitude = lng;
            form.address = result.display_name;
            
            updateMapPosition(lat, lng, 16);
        } else {
            alert("Không tìm thấy địa điểm này trên bản đồ.");
        }
    } catch (error) {
        console.error("Lỗi tìm kiếm bản đồ: ", error);
    }
};

// Lấy vị trí GPS của thiết bị hiện tại
const getGeolocation = () => {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            async (position) => {
                form.latitude = parseFloat(position.coords.latitude.toFixed(6));
                form.longitude = parseFloat(position.coords.longitude.toFixed(6));
                updateMapPosition(form.latitude, form.longitude, 16);
                await reverseGeocode(form.latitude, form.longitude);
            },
            (error) => {
                console.error("Lỗi định vị GPS: ", error);
                alert("Không thể truy cập vị trí hiện tại. Vui lòng cấp quyền định vị cho trình duyệt.");
            }
        );
    } else {
        alert("Trình duyệt của bạn không hỗ trợ định vị vị trí.");
    }
};

// Lấy tọa độ GPS từ địa chỉ chữ (Geocoding)
const geocodeAddress = async () => {
    const query = form.address;
    if (!query || query.trim().length < 6) return;

    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`, {
            headers: {
                'Accept-Language': 'vi' // Ưu tiên kết quả tiếng Việt
            }
        });
        const data = await response.json();
        
        if (data && data.length > 0) {
            const result = data[0];
            form.latitude = parseFloat(parseFloat(result.lat).toFixed(6));
            form.longitude = parseFloat(parseFloat(result.lon).toFixed(6));
            updateMapPosition(form.latitude, form.longitude, 16);
        }
    } catch (error) {
        console.error("Lỗi tìm kiếm tọa độ từ địa chỉ: ", error);
    }
};

// Đồng bộ vị trí bản đồ khi người dùng nhập số thủ công
watch(() => [form.latitude, form.longitude], ([newLat, newLng]) => {
    if (newLat && newLng && map && marker) {
        const currentMarkerLatLng = marker.getLatLng();
        if (Math.abs(currentMarkerLatLng.lat - parseFloat(newLat)) > 0.0001 || 
            Math.abs(currentMarkerLatLng.lng - parseFloat(newLng)) > 0.0001) {
            updateMapPosition(parseFloat(newLat), parseFloat(newLng));
        }
    }
});

const getRoleLabel = (role) => {
    const labels = {
        admin: 'Quản trị viên (Admin)',
        charity: 'Tổ chức từ thiện (Mái ấm)',
        small_business: 'Doanh nghiệp nhỏ / Hộ kinh doanh',
        personal: 'Cá nhân',
    };
    return labels[role] || role;
};

const getStatusLabel = (status) => {
    const labels = {
        pending: 'Đang chờ phê duyệt',
        verified: 'Đã duyệt hoạt động',
        rejected: 'Từ chối phê duyệt',
        banned: 'Tài khoản bị khóa',
    };
    return labels[status] || status;
};
</script>

<template>
    <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8 space-y-8">
        <!-- Tiêu đề & Mô tả -->
        <div class="border-b border-gray-50 pb-5">
            <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <span class="w-2 h-6 bg-emerald-600 rounded-full"></span>
                Thông tin hồ sơ của bạn
            </h2>
            <p class="mt-1.5 text-xs text-gray-500 leading-relaxed">
                Xem và cập nhật thông tin cá nhân, hình ảnh đại diện, số điện thoại liên lạc cũng như thiết lập tọa độ địa lý GPS để chia sẻ hoặc nhận thực phẩm chính xác nhất.
            </p>
        </div>

        <form @submit.prevent="submitForm" class="space-y-8">
            <!-- 1. Ảnh đại diện & Thông tin hệ thống (Hàng trên cùng) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                
                <!-- Khung Avatar cao cấp -->
                <div class="bg-gray-50/50 border border-gray-100 rounded-2xl p-5 flex flex-col items-center text-center space-y-4 lg:col-span-1 shadow-sm">
                    <div class="relative group">
                        <img 
                            v-if="avatarPreview || user.avatar" 
                            :src="avatarPreview || user.avatar" 
                            class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-md group-hover:opacity-90 transition duration-200"
                            alt="Ảnh đại diện"
                        />
                        <div 
                            v-else 
                            class="w-24 h-24 rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center text-3xl font-bold shadow-md"
                        >
                            {{ user.name.charAt(0).toUpperCase() }}
                        </div>
                        <!-- Nút kích hoạt upload ảnh nhanh -->
                        <button
                            type="button"
                            @click="$refs.avatarInput.click()"
                            class="absolute bottom-0 right-0 bg-emerald-600 hover:bg-emerald-700 text-white p-2 rounded-full shadow-md transition duration-200 cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-1 w-full">
                        <h4 class="text-sm font-bold text-gray-900">Ảnh đại diện</h4>
                        <p class="text-[10px] text-gray-400">Hỗ trợ JPG, PNG tối đa 2MB.</p>
                        
                        <input 
                            id="avatar" 
                            type="file" 
                            ref="avatarInput"
                            class="hidden" 
                            accept="image/*"
                            @change="handleAvatarChange"
                        />
                        <div class="flex justify-center gap-2 pt-2">
                            <button 
                                type="button" 
                                @click="$refs.avatarInput.click()"
                                class="bg-gray-100 hover:bg-emerald-50 text-gray-700 hover:text-emerald-700 text-[11px] font-bold py-1.5 px-3 rounded-lg border border-gray-200 hover:border-emerald-200 transition cursor-pointer"
                            >
                                Thay đổi
                            </button>
                            <button 
                                v-if="form.avatar || avatarPreview"
                                type="button" 
                                @click="resetAvatar"
                                class="bg-red-50 hover:bg-red-100 text-red-600 text-[11px] font-bold py-1.5 px-3 rounded-lg border border-red-200 transition cursor-pointer"
                            >
                                Hủy
                            </button>
                        </div>
                        <InputError :message="form.errors.avatar" class="mt-1" />
                    </div>
                </div>

                <!-- Thẻ trạng thái & Vai trò cao cấp -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 lg:col-span-2">
                    <div class="bg-emerald-50/30 border border-emerald-100/50 rounded-2xl p-5 flex items-start gap-4 shadow-sm">
                        <div class="p-3 bg-emerald-100/60 rounded-xl text-emerald-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <div class="space-y-1">
                            <span class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider block">Vai trò thành viên</span>
                            <span class="text-sm font-bold text-gray-900 block">{{ getRoleLabel(user.role) }}</span>
                        </div>
                    </div>

                    <div class="bg-blue-50/30 border border-blue-100/50 rounded-2xl p-5 flex items-start gap-4 shadow-sm">
                        <div class="p-3 bg-blue-100/60 rounded-xl text-blue-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div class="space-y-1">
                            <span class="text-[10px] font-bold text-blue-800 uppercase tracking-wider block">Trạng thái phê duyệt</span>
                            <div class="flex items-center gap-1.5">
                                <span class="relative flex h-2 w-2" v-if="user.status === 'verified'">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                </span>
                                <span :class="{
                                    'text-emerald-700 font-bold': user.status === 'verified',
                                    'text-amber-700 font-bold': user.status === 'pending',
                                    'text-red-700 font-bold': user.status === 'banned' || user.status === 'rejected'
                                }" class="text-sm block">
                                    {{ getStatusLabel(user.status) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Điểm uy tín -->
                    <div :class="user.trust_score < 70 ? 'bg-red-50/30 border-red-100/50' : 'bg-amber-50/30 border-amber-100/50'" class="border rounded-2xl p-5 flex items-start gap-4 shadow-sm sm:col-span-2 md:col-span-1">
                        <div :class="user.trust_score < 70 ? 'bg-red-100/60 text-red-700' : 'bg-amber-100/60 text-amber-700'" class="p-3 rounded-xl">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                            </svg>
                        </div>
                        <div class="space-y-1">
                            <span :class="user.trust_score < 70 ? 'text-red-800' : 'text-amber-800'" class="text-[10px] font-bold uppercase tracking-wider block">Điểm uy tín</span>
                            <div class="flex items-center gap-1.5">
                                <span :class="user.trust_score < 70 ? 'text-red-700 font-bold' : 'text-amber-700 font-bold'" class="text-sm block">
                                    {{ user.trust_score }} / 100
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Minh chứng hoạt động (Chỉ hiển thị cho Charity) -->
            <div v-if="user.role === 'charity' && Object.keys(charityDocuments).length > 0" class="bg-slate-50/50 border border-slate-100 p-5 rounded-2xl space-y-4 shadow-sm">
                <span class="text-xs font-bold text-gray-500 uppercase tracking-wider flex items-center gap-1.5">
                    📄 Tài liệu minh chứng pháp lý mái ấm
                </span>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Giấy phép hoạt động -->
                    <div v-if="charityDocuments.legal_license" class="space-y-1.5">
                        <span class="text-xs font-medium text-gray-500 block">Quyết định thành lập / Giấy phép hoạt động:</span>
                        <div class="border border-gray-200/80 rounded-xl overflow-hidden bg-white shadow-sm flex flex-col items-center p-4 relative group hover:border-emerald-500 transition duration-200">
                            <template v-if="charityDocuments.legal_license.toLowerCase().endsWith('.pdf')">
                                <div class="w-full py-4 flex flex-col items-center justify-center text-red-500">
                                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                    <span class="text-xs text-gray-600 font-bold mt-2">Tài liệu pháp lý (PDF)</span>
                                </div>
                            </template>
                            <template v-else>
                                <img :src="charityDocuments.legal_license" alt="Giấy phép hoạt động" class="max-h-28 object-contain rounded-lg shadow-sm" />
                            </template>
                            <a :href="charityDocuments.legal_license" target="_blank" class="mt-3 text-xs font-bold text-emerald-600 hover:text-emerald-700 flex items-center gap-1">
                                🔗 Xem tài liệu gốc
                            </a>
                        </div>
                    </div>

                    <!-- Hình ảnh cơ sở thực tế -->
                    <div v-if="charityDocuments.facility_image" class="space-y-1.5">
                        <span class="text-xs font-medium text-gray-500 block">Hình ảnh cơ sở / mái ấm thực tế:</span>
                        <div class="border border-gray-200/80 rounded-xl overflow-hidden bg-white shadow-sm flex flex-col items-center p-4 relative group hover:border-emerald-500 transition duration-200">
                            <template v-if="charityDocuments.facility_image.toLowerCase().endsWith('.pdf')">
                                <div class="w-full py-4 flex flex-col items-center justify-center text-red-500">
                                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                    <span class="text-xs text-gray-600 font-bold mt-2">Hình ảnh cơ sở (PDF)</span>
                                </div>
                            </template>
                            <template v-else>
                                <img :src="charityDocuments.facility_image" alt="Hình ảnh cơ sở thực tế" class="max-h-28 object-contain rounded-lg shadow-sm" />
                            </template>
                            <a :href="charityDocuments.facility_image" target="_blank" class="mt-3 text-xs font-bold text-emerald-600 hover:text-emerald-700 flex items-center gap-1">
                                🔗 Xem tài liệu gốc
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Nhóm thông tin chi tiết (2 Cột) -->
            <div class="space-y-4">
                <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block">
                    👤 Thông tin liên hệ cơ bản
                </span>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <InputLabel for="name" value="Họ và tên / Tên tổ chức" class="text-xs font-semibold text-gray-700" />
                        <TextInput
                            id="name"
                            type="text"
                            class="mt-1.5 block w-full border-gray-200 focus:border-transparent focus:ring-2 focus:ring-emerald-500 rounded-xl bg-gray-50/30 text-sm py-2.5 px-4"
                            v-model="form.name"
                            required
                            autocomplete="name"
                            placeholder="Nhập họ tên hoặc tên mái ấm"
                        />
                        <InputError class="mt-1.5" :message="form.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="phone" value="Số điện thoại liên lạc" class="text-xs font-semibold text-gray-700" />
                        <TextInput
                            id="phone"
                            type="text"
                            class="mt-1.5 block w-full border-gray-200 focus:border-transparent focus:ring-2 focus:ring-emerald-500 rounded-xl bg-gray-50/30 text-sm py-2.5 px-4"
                            v-model="form.phone"
                            required
                            placeholder="Nhập số điện thoại liên lạc"
                        />
                        <InputError class="mt-1.5" :message="form.errors.phone" />
                    </div>

                    <div class="md:col-span-2">
                        <InputLabel for="email" value="Địa chỉ Email" class="text-xs font-semibold text-gray-700" />
                        <TextInput
                            id="email"
                            type="email"
                            class="mt-1.5 block w-full border-gray-200 bg-gray-100 text-gray-500 rounded-xl text-sm py-2.5 px-4 cursor-not-allowed"
                            v-model="form.email"
                            required
                            disabled
                            autocomplete="username"
                        />
                        <InputError class="mt-1.5" :message="form.errors.email" />
                    </div>
                </div>
            </div>

            <!-- 3. Địa chỉ & Vị trí GPS -->
            <div class="space-y-4 border-t border-gray-50 pt-6">
                <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block">
                    📍 Địa chỉ & Bản đồ định vị GPS
                </span>

                <div>
                    <InputLabel for="address" value="Địa chỉ chi tiết (Số nhà, tên đường, khu vực...)" class="text-xs font-semibold text-gray-700" />
                    <TextInput
                        id="address"
                        type="text"
                        class="mt-1.5 block w-full border-gray-200 focus:border-transparent focus:ring-2 focus:ring-emerald-500 rounded-xl bg-gray-50/30 text-sm py-2.5 px-4"
                        v-model="form.address"
                        required
                        placeholder="Ví dụ: 123 Đường Nguyễn Trãi, Phường 2, Quận 5..."
                        @blur="geocodeAddress"
                    />
                    <InputError class="mt-1.5" :message="form.errors.address" />
                </div>

                <!-- Bản đồ tương tác ghim GPS -->
                <div class="space-y-3">
                    <div class="flex flex-col sm:flex-row gap-3">
                        <input
                            type="text"
                            ref="mapSearchInput"
                            class="flex-1 bg-white border border-gray-200 text-xs text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                            placeholder="Nhập địa điểm cần tìm nhanh (Chợ Bến Thành, Công viên Gia Định...)"
                            @keyup.enter="searchMapAddress"
                        />
                        <div class="flex gap-2">
                            <button
                                type="button"
                                @click="searchMapAddress"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl px-4 py-2.5 shadow-sm transition duration-200 cursor-pointer"
                            >
                                Tìm vị trí
                            </button>
                            <button
                                type="button"
                                @click="getGeolocation"
                                class="bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-700 font-bold text-xs rounded-xl px-4 py-2.5 transition duration-200 flex items-center gap-1.5 cursor-pointer"
                            >
                                🎯 Định vị GPS của tôi
                            </button>
                        </div>
                    </div>

                    <!-- Khung bản đồ -->
                    <div ref="mapContainer" class="w-full h-80 rounded-2xl border border-gray-200 shadow-inner z-10"></div>
                    <p class="text-[10.5px] text-gray-400 leading-normal">
                        💡 Mẹo: Bạn có thể nhập địa điểm vào ô tìm kiếm hoặc kéo thả biểu tượng ghim màu xanh trên bản đồ để tự động cập nhật tọa độ chính xác.
                    </p>
                </div>
            </div>

            <!-- Email verification notice -->
            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="bg-amber-50 border border-amber-100 rounded-2xl p-4 text-xs text-amber-800 leading-normal">
                <p>
                    Địa chỉ email của bạn chưa được xác thực.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="font-bold underline text-amber-900 hover:text-amber-950 focus:outline-none ml-1 cursor-pointer"
                    >
                        Bấm vào đây để gửi lại mã xác thực email.
                    </Link>
                </p>
                <div v-show="status === 'verification-link-sent'" class="mt-2 font-bold text-emerald-700">
                    Mã xác thực mới đã được gửi tới hòm thư của bạn.
                </div>
            </div>

            <!-- Nút Lưu thông tin -->
            <div class="flex items-center gap-4 pt-3 border-t border-gray-50">
                <button 
                    type="submit"
                    :disabled="form.processing"
                    class="bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-bold rounded-xl text-xs py-3 px-6 shadow-md shadow-emerald-100 hover:shadow-emerald-200 transition duration-200 cursor-pointer flex items-center gap-2"
                >
                    <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Lưu thay đổi
                </button>

                <Transition
                    enter-active-class="transition ease-in-out duration-300"
                    enter-from-class="opacity-0 translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition ease-in-out duration-300"
                    leave-to-class="opacity-0 translate-y-1"
                >
                    <p v-if="form.recentlySuccessful" class="text-xs text-emerald-600 font-bold flex items-center gap-1">
                        ✨ Cập nhật hồ sơ thành công!
                    </p>
                </Transition>
            </div>
        </form>
    </div>
</template>
