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
    name: user.name,
    email: user.email,
    phone: user.phone || '',
    address: user.address || '',
    latitude: user.latitude ? parseFloat(user.latitude) : null,
    longitude: user.longitude ? parseFloat(user.longitude) : null,
});

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
        admin: 'admin',
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
    <section class="space-y-6">
        <header>
            <h2 class="text-lg font-bold text-gray-950">
                Thông tin tài khoản
            </h2>

            <p class="mt-1 text-xs text-gray-500">
                Xem và cập nhật thông tin cá nhân, số điện thoại liên lạc cũng như tọa độ GPS của bạn.
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="space-y-5"
        >
            <!-- Thẻ thông tin hệ thống (Đọc) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-gray-50 p-4 rounded-2xl border border-gray-200/60 text-xs">
                <div class="space-y-1">
                    <span class="font-semibold text-gray-500 uppercase tracking-wider block">Vai trò trong hệ thống</span>
                    <span class="font-bold text-gray-900 text-sm block">{{ getRoleLabel(user.role) }}</span>
                </div>
                <div class="space-y-1">
                    <span class="font-semibold text-gray-500 uppercase tracking-wider block">Trạng thái xác minh</span>
                    <span :class="{
                        'text-emerald-700': user.status === 'verified',
                        'text-amber-700': user.status === 'pending',
                        'text-red-700': user.status === 'banned' || user.status === 'rejected'
                    }" class="font-bold text-sm block">
                        {{ getStatusLabel(user.status) }}
                    </span>
                </div>
            </div>

            <!-- Minh chứng Mái ấm (chỉ hiển thị với vai trò charity) -->
            <div v-if="user.role === 'charity' && Object.keys(charityDocuments).length > 0" class="space-y-3 bg-gray-50 p-4 rounded-2xl border border-gray-200/60">
                <span class="font-semibold text-gray-500 uppercase tracking-wider text-xs block">Giấy tờ & Hình ảnh minh chứng hoạt động</span>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Giấy phép hoạt động -->
                    <div v-if="charityDocuments.legal_license" class="space-y-1">
                        <span class="text-[11px] font-medium text-gray-500 block">Giấy phép hoạt động pháp lý:</span>
                        <div class="border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm flex flex-col items-center p-3 relative group">
                            <template v-if="charityDocuments.legal_license.toLowerCase().endsWith('.pdf')">
                                <div class="w-full py-6 flex flex-col items-center justify-center text-red-500">
                                    <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                    <span class="text-xs text-gray-600 font-semibold mt-2">Tài liệu Giấy phép (PDF)</span>
                                </div>
                            </template>
                            <template v-else>
                                <img :src="charityDocuments.legal_license" alt="Giấy phép hoạt động" class="max-h-36 object-contain rounded-lg" />
                            </template>
                            <a :href="charityDocuments.legal_license" target="_blank" class="mt-2 text-xs font-semibold text-emerald-600 hover:text-emerald-700 flex items-center gap-1">
                                🔗 Xem tài liệu gốc
                            </a>
                        </div>
                    </div>

                    <!-- Hình ảnh cơ sở thực tế -->
                    <div v-if="charityDocuments.facility_image" class="space-y-1">
                        <span class="text-[11px] font-medium text-gray-500 block">Hình ảnh cơ sở thực tế:</span>
                        <div class="border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm flex flex-col items-center p-3 relative group">
                            <template v-if="charityDocuments.facility_image.toLowerCase().endsWith('.pdf')">
                                <div class="w-full py-6 flex flex-col items-center justify-center text-red-500">
                                    <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                    <span class="text-xs text-gray-600 font-semibold mt-2">Hình ảnh cơ sở (PDF)</span>
                                </div>
                            </template>
                            <template v-else>
                                <img :src="charityDocuments.facility_image" alt="Hình ảnh cơ sở thực tế" class="max-h-36 object-contain rounded-lg" />
                            </template>
                            <a :href="charityDocuments.facility_image" target="_blank" class="mt-2 text-xs font-semibold text-emerald-600 hover:text-emerald-700 flex items-center gap-1">
                                🔗 Xem tài liệu gốc
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Họ và tên -->
            <div>
                <InputLabel for="name" value="Họ và tên / Tên tổ chức" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <!-- Email -->
            <div>
                <InputLabel for="email" value="Địa chỉ Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full bg-gray-50/50"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <!-- Số điện thoại -->
            <div>
                <InputLabel for="phone" value="Số điện thoại" />

                <TextInput
                    id="phone"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.phone"
                    required
                    placeholder="Nhập số điện thoại liên lạc"
                />

                <InputError class="mt-2" :message="form.errors.phone" />
            </div>

            <!-- Địa chỉ chi tiết -->
            <div>
                <InputLabel for="address" value="Địa chỉ chi tiết (Số nhà, tên đường...)" />

                <TextInput
                    id="address"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.address"
                    required
                    placeholder="Ví dụ: 123 Đường Nguyễn Trãi, Phường 2, Quận 5..."
                />

                <InputError class="mt-2" :message="form.errors.address" />
            </div>

            <!-- Tọa độ GPS -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <InputLabel for="latitude" value="Vĩ độ (Latitude)" />
                    <TextInput
                        id="latitude"
                        type="number"
                        step="any"
                        class="mt-1 block w-full"
                        v-model="form.latitude"
                        placeholder="Vĩ độ"
                    />
                    <InputError class="mt-2" :message="form.errors.latitude" />
                </div>
                <div>
                    <InputLabel for="longitude" value="Kinh độ (Longitude)" />
                    <TextInput
                        id="longitude"
                        type="number"
                        step="any"
                        class="mt-1 block w-full"
                        v-model="form.longitude"
                        placeholder="Kinh độ"
                    />
                    <InputError class="mt-2" :message="form.errors.longitude" />
                </div>
            </div>

            <!-- Bản đồ tương tác -->
            <div class="space-y-2">
                <span class="text-xs font-semibold text-gray-700 tracking-wide block">Bản đồ định vị vị trí</span>
                
                <!-- Thanh tìm kiếm nhanh địa điểm trên bản đồ -->
                <div class="flex gap-2">
                    <input
                        type="text"
                        ref="mapSearchInput"
                        class="flex-1 bg-white border border-gray-300 text-xs text-gray-900 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                        placeholder="Tìm kiếm địa điểm nhanh (Ví dụ: Chợ Bến Thành, Landmark 81...)"
                        @keyup.enter="searchMapAddress"
                    />
                    <button
                        type="button"
                        @click="searchMapAddress"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs rounded-xl px-4 py-2.5 shadow-sm transition"
                    >
                        Tìm vị trí
                    </button>
                </div>

                <div ref="mapContainer" class="w-full h-80 rounded-2xl border border-gray-200 shadow-inner z-10"></div>
                <p class="text-[10px] text-gray-400">Bạn có thể phóng to, thu nhỏ, kéo thả biểu tượng ghim hoặc click chuột bất kỳ đâu trên bản đồ để chọn tọa độ chính xác.</p>
            </div>

            <div>
                <button
                    type="button"
                    @click="getGeolocation"
                    class="text-xs bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-700 px-4 py-2 rounded-xl transition duration-200 font-semibold flex items-center gap-1.5"
                >
                    📍 Định vị lại tọa độ GPS hiện tại
                </button>
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800">
                    Địa chỉ email của bạn chưa được xác thực.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Bấm vào đây để gửi lại mã xác thực email.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    Mã xác thực mới đã được gửi tới hòm thư của bạn.
                </div>
            </div>

            <div class="flex items-center gap-4 pt-2">
                <PrimaryButton :disabled="form.processing" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-xs py-2.5 px-4 shadow-md shadow-emerald-100 transition">Lưu thông tin</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-xs text-emerald-600 font-semibold"
                    >
                        Lưu thông tin thành công.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
