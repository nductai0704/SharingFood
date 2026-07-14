<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    campaign: Object
});

const form = useForm({
    title: props.campaign.title,
    description: props.campaign.description || '',
    location_details: props.campaign.location_details,
    latitude: props.campaign.latitude,
    longitude: props.campaign.longitude,
    web_deadline: props.campaign.web_deadline ? props.campaign.web_deadline.substring(0, 16) : '',
    event_date: props.campaign.event_date ? props.campaign.event_date.substring(0, 16) : '',
    items: props.campaign.items.length > 0 ? props.campaign.items.map(i => ({
        id: i.id, // Keep track of existing items
        item_name: i.item_name,
        target_quantity: i.target_quantity,
        unit: i.unit || '',
        item_type: i.item_type || 'dry'
    })) : [
        { item_name: '', target_quantity: 1, unit: '', item_type: 'dry' }
    ],
});

const isCategorizing = ref(false);

const categorizeByAi = async () => {
    if (form.items.length === 0) return;
    
    // Check if names are filled
    const itemNames = form.items.map(i => i.item_name).filter(n => n.trim() !== '');
    if (itemNames.length === 0) {
        alert("Vui lòng nhập ít nhất một tên vật phẩm để AI phân loại.");
        return;
    }

    isCategorizing.value = true;
    try {
        const response = await fetch('/api/ai/categorize-food', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify({ items: itemNames })
        });
        const result = await response.json();
        
        if (result.success && result.data) {
            // Update items
            form.items.forEach(item => {
                if (result.data.fresh.includes(item.item_name)) {
                    item.item_type = 'fresh';
                } else {
                    item.item_type = 'dry';
                }
            });
        } else {
            alert("Không thể phân loại tự động lúc này.");
        }
    } catch (error) {
        console.error("Lỗi gọi AI: ", error);
        alert("Có lỗi kết nối AI.");
    } finally {
        isCategorizing.value = false;
    }
};

// Hàm tải động thư viện Leaflet (Map)
const loadLeaflet = () => {
    return new Promise((resolve) => {
        if (window.L) {
            resolve(window.L);
            return;
        }
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(link);

        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.onload = () => resolve(window.L);
        document.head.appendChild(script);
    });
};

const mapInstance = ref(null);
const markerInstance = ref(null);

onMounted(async () => {
    const L = await loadLeaflet();
    
    // Khởi tạo bản đồ tại tâm Việt Nam nếu chưa có tọa độ
    const initialLat = props.campaign.latitude || 16.047079;
    const initialLng = props.campaign.longitude || 108.206230;
    
    mapInstance.value = L.map('campaign-map').setView([initialLat, initialLng], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap'
    }).addTo(mapInstance.value);

    // Xử lý icon của Leaflet (Sửa lỗi đường dẫn ảnh mặc định)
    const DefaultIcon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });
    L.Marker.prototype.options.icon = DefaultIcon;

    if (props.campaign.latitude && props.campaign.longitude) {
        markerInstance.value = L.marker([props.campaign.latitude, props.campaign.longitude], { draggable: true }).addTo(mapInstance.value);
        markerInstance.value.bindPopup("<b>Vị trí tập kết quyên góp</b>").openPopup();
        
        markerInstance.value.on('dragend', async () => {
            const position = markerInstance.value.getLatLng();
            form.latitude = position.lat;
            form.longitude = position.lng;
            await reverseGeocode(position.lat, position.lng);
        });
    }

    // Lắng nghe sự kiện click trên bản đồ để ghim tọa độ
    mapInstance.value.on('click', async function(e) {
        const lat = e.latlng.lat;
        const lng = e.latlng.lng;
        
        // Cập nhật form
        form.latitude = lat;
        form.longitude = lng;

        // Xóa marker cũ nếu có
        if (markerInstance.value) {
            mapInstance.value.removeLayer(markerInstance.value);
        }

        // Tạo marker mới và cho phép kéo thả
        markerInstance.value = L.marker([lat, lng], { draggable: true }).addTo(mapInstance.value);
        markerInstance.value.bindPopup("<b>Vị trí tập kết quyên góp</b>").openPopup();
        
        // Cập nhật địa chỉ chữ từ tọa độ
        await reverseGeocode(lat, lng);
        
        // Bắt sự kiện kéo thả
        markerInstance.value.on('dragend', async () => {
            const position = markerInstance.value.getLatLng();
            form.latitude = position.lat;
            form.longitude = position.lng;
            await reverseGeocode(position.lat, position.lng);
        });
    });
});

// Hàm lấy địa chỉ chữ từ tọa độ (Reverse Geocoding)
const reverseGeocode = async (lat, lng) => {
    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`, {
            headers: { 'Accept-Language': 'vi' }
        });
        const data = await response.json();
        if (data && data.display_name) {
            form.location_details = data.display_name;
        }
    } catch (error) {
        console.error("Lỗi phân tích địa chỉ từ tọa độ: ", error);
    }
};

// Hàm lấy tọa độ từ địa chỉ đã nhập (Geocoding)
const geocodeAddress = async () => {
    const query = form.location_details;
    if (!query || query.trim().length < 5) return;

    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`, {
            headers: { 'Accept-Language': 'vi' }
        });
        const data = await response.json();
        
        if (data && data.length > 0) {
            const result = data[0];
            form.latitude = parseFloat(parseFloat(result.lat).toFixed(6));
            form.longitude = parseFloat(parseFloat(result.lon).toFixed(6));
            
            if (mapInstance.value) {
                mapInstance.value.setView([form.latitude, form.longitude], 16);
                
                if (markerInstance.value) {
                    markerInstance.value.setLatLng([form.latitude, form.longitude]);
                } else {
                    markerInstance.value = window.L.marker([form.latitude, form.longitude], { draggable: true }).addTo(mapInstance.value);
                    
                    markerInstance.value.on('dragend', async () => {
                        const position = markerInstance.value.getLatLng();
                        form.latitude = position.lat;
                        form.longitude = position.lng;
                        await reverseGeocode(position.lat, position.lng);
                    });
                }
                markerInstance.value.bindPopup("<b>Vị trí tập kết quyên góp</b>").openPopup();
            }
        } else {
            alert("Không tìm thấy địa điểm này trên bản đồ. Vui lòng ghi chi tiết hơn hoặc ghim bằng tay trên bản đồ.");
        }
    } catch (error) {
        console.error("Lỗi tìm kiếm tọa độ từ địa chỉ: ", error);
    }
};

// Thêm một dòng vật phẩm mới
const addItem = (type = 'dry') => {
    form.items.push({
        item_name: '',
        target_quantity: 1,
        unit: '',
        item_type: type
    });
};

// Xóa một dòng vật phẩm
const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const submit = () => {
    if (!form.latitude || !form.longitude) {
        alert('Vui lòng ghim một vị trí tập kết trên bản đồ.');
        return;
    }
    form.post(route('charity.campaigns.update', props.campaign.id), {
        preserveScroll: true,
        onSuccess: () => {
            // Xử lý sau khi thành công nếu cần
        }
    });
};
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Cập nhật chiến dịch quyên góp" />

    <div class="min-h-screen bg-gray-50 py-10">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Header -->
            <div class="mb-8">
                <Link :href="route('charity.campaigns')" class="text-sm text-emerald-600 hover:text-emerald-700 font-medium flex items-center gap-1 mb-4">
                    <span>&larr;</span> Quay lại danh sách

                </Link>
                <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Cập nhật Chiến dịch Quyên góp</h1>
                <p class="mt-2 text-sm text-gray-500">
                    Sửa thông tin hoặc bổ sung thêm vật phẩm cần gọi.
                </p>
            </div>

            <div v-if="$page.props.flash?.error" class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-xl shadow-sm flex items-start">
                <span class="text-red-500 mr-3">⚠️</span>
                <div>
                    <h3 class="text-sm font-bold text-red-800">Lỗi hệ thống</h3>
                    <p class="text-xs text-red-600 mt-1">{{ $page.props.flash.error }}</p>
                </div>
            </div>

            <!-- Main Form -->
            <form @submit.prevent="submit" class="bg-white shadow-xl shadow-emerald-100/50 rounded-2xl overflow-hidden">
                <div class="p-8 space-y-8">
                    
                    <!-- Phần 1: Thông tin chung -->
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 border-b border-gray-100 pb-2 mb-4">Thông tin chung</h2>
                        <div class="grid grid-cols-1 gap-6">
                            
                            <!-- Tiêu đề -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Tên chiến dịch <span class="text-red-500">*</span></label>
                                <input v-model="form.title" type="text" class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 shadow-sm" placeholder="VD: Ủng hộ đồng bào lũ lụt miền Trung" required>
                                <div v-if="form.errors.title" class="text-red-500 text-xs mt-1">{{ form.errors.title }}</div>
                            </div>

                            <!-- Địa chỉ tập kết -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Địa điểm tập kết vật lý <span class="text-red-500">*</span></label>
                                <div class="flex gap-2">
                                    <input v-model="form.location_details" type="text" class="flex-1 w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 shadow-sm" placeholder="VD: 123 Đường A, Quận B, TP..." required>
                                    <button type="button" @click="geocodeAddress" class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 rounded-xl text-sm font-bold shadow-md transition whitespace-nowrap">
                                        📍 Tìm trên bản đồ
                                    </button>
                                </div>
                                <div v-if="form.errors.location_details" class="text-red-500 text-xs mt-1">{{ form.errors.location_details }}</div>
                                
                                <p class="text-xs text-gray-500 mt-3 mb-2 font-medium">Bản đồ định vị: Bạn có thể kéo thả ghim đỏ hoặc bấm vào bản đồ để chọn tọa độ chính xác. Địa chỉ bên trên sẽ tự động cập nhật, bạn có thể tự chỉnh sửa lại số nhà nếu bản đồ nhận diện chưa chính xác.</p>
                                <div id="campaign-map" class="w-full h-72 rounded-xl border-2 border-gray-200 z-10 shadow-inner"></div>
                                <div v-if="!form.latitude" class="text-red-500 text-xs mt-2 italic">* Vui lòng chọn một điểm trên bản đồ.</div>
                            </div>

                            <!-- Thời gian -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Mốc 1: Hạn chót Đồ Khô (Web Deadline) <span class="text-red-500">*</span></label>
                                    <input v-model="form.web_deadline" type="datetime-local" class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 shadow-sm" required>
                                    <p class="text-[10px] text-gray-500 mt-1">Sau mốc này, chỉ nhận Đồ Tươi. Đồ Khô bị khóa.</p>
                                    <div v-if="form.errors.web_deadline" class="text-red-500 text-xs mt-1">{{ form.errors.web_deadline }}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Mốc 2: Ngày đi phát (Event Date) <span class="text-red-500">*</span></label>
                                    <input v-model="form.event_date" type="datetime-local" class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 shadow-sm" required>
                                    <p class="text-[10px] text-gray-500 mt-1">Cách Mốc 1 không quá 36 giờ.</p>
                                    <div v-if="form.errors.event_date" class="text-red-500 text-xs mt-1">{{ form.errors.event_date }}</div>
                                </div>
                            </div>

                            <!-- Mô tả -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Mô tả hoàn cảnh / Mục đích</label>
                                <textarea v-model="form.description" rows="4" class="w-full rounded-xl border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 shadow-sm" placeholder="Chia sẻ câu chuyện và lý do để mọi người hiểu rõ hơn về chiến dịch..."></textarea>
                                <div v-if="form.errors.description" class="text-red-500 text-xs mt-1">{{ form.errors.description }}</div>
                            </div>

                        </div>
                    </div>

                    <!-- Phần 2: Danh sách nhu yếu phẩm -->
                    <div>
                        <div class="flex justify-between items-end border-b border-gray-100 pb-2 mb-4">
                            <h2 class="text-lg font-bold text-gray-900">Danh sách nhu yếu phẩm cần gọi</h2>
                            <button type="button" @click="categorizeByAi" :disabled="isCategorizing" class="text-sm bg-purple-100 text-purple-700 hover:bg-purple-200 font-bold px-4 py-2 rounded-lg transition flex items-center gap-2">
                                <span v-if="isCategorizing" class="animate-spin">🔄</span>
                                <span v-else>🪄</span>
                                {{ isCategorizing ? 'Đang phân loại...' : 'Gợi ý phân loại bằng AI' }}
                            </button>
                        </div>
                        
                        <div v-if="form.errors.items" class="text-red-500 text-sm mb-4 font-medium p-3 bg-red-50 rounded-xl border border-red-100">
                            {{ form.errors.items }}
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Cột Đồ Khô -->
                            <div class="space-y-4">
                                <div class="bg-emerald-50 p-3 rounded-lg border border-emerald-100">
                                    <h3 class="font-bold text-emerald-800 text-sm flex items-center gap-2">
                                        <span>📦</span> Đồ khô / Nhu yếu phẩm (Mốc 1)
                                    </h3>
                                    <p class="text-xs text-emerald-600 mt-1">Nhận trước hạn chót Mốc 1</p>
                                </div>
                                
                                <template v-for="(item, index) in form.items" :key="index">
                                    <div v-if="item.item_type === 'dry'" class="p-4 bg-white border border-gray-200 shadow-sm rounded-xl relative group transition-all hover:border-emerald-300">
                                        <div class="flex flex-col gap-3">
                                            <div class="flex justify-between items-center">
                                                <button type="button" @click="item.item_type = 'fresh'" class="text-[10px] bg-gray-100 hover:bg-amber-100 text-gray-600 font-semibold px-2 py-1 rounded transition">
                                                    Chuyển qua Đồ tươi ➔
                                                </button>
                                                <button type="button" @click="removeItem(index)" class="text-red-400 hover:text-red-600 text-sm font-bold" title="Xóa">✕</button>
                                            </div>
                                            
                                            <div>
                                                <input v-model="item.item_name" type="text" class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 shadow-sm text-sm" placeholder="VD: Gạo tẻ ST25..." required>
                                                <div v-if="form.errors[`items.${index}.item_name`]" class="text-red-500 text-xs mt-1">{{ form.errors[`items.${index}.item_name`] }}</div>
                                            </div>

                                            <div class="flex gap-2">
                                                <div class="w-1/2">
                                                    <input v-model="item.target_quantity" type="number" min="1" class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 shadow-sm text-sm" placeholder="Số lượng" required>
                                                </div>
                                                <div class="w-1/2">
                                                    <input v-model="item.unit" type="text" class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 shadow-sm text-sm" placeholder="Đơn vị" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                
                                <button type="button" @click="addItem('dry')" class="w-full text-sm border-2 border-dashed border-gray-300 text-gray-500 hover:text-emerald-600 hover:border-emerald-400 hover:bg-emerald-50 font-semibold py-2 rounded-xl transition">
                                    + Thêm đồ khô
                                </button>
                            </div>

                            <!-- Cột Đồ Tươi -->
                            <div class="space-y-4">
                                <div class="bg-amber-50 p-3 rounded-lg border border-amber-100">
                                    <h3 class="font-bold text-amber-800 text-sm flex items-center gap-2">
                                        <span>🥬</span> Đồ tươi sống (Mốc 2)
                                    </h3>
                                    <p class="text-xs text-amber-600 mt-1">Chỉ nhận từ Mốc 1 đến Mốc 2</p>
                                </div>
                                
                                <template v-for="(item, index) in form.items" :key="index">
                                    <div v-if="item.item_type === 'fresh'" class="p-4 bg-white border border-gray-200 shadow-sm rounded-xl relative group transition-all hover:border-amber-300">
                                        <div class="flex flex-col gap-3">
                                            <div class="flex justify-between items-center">
                                                <button type="button" @click="item.item_type = 'dry'" class="text-[10px] bg-gray-100 hover:bg-emerald-100 text-gray-600 font-semibold px-2 py-1 rounded transition">
                                                    🡄 Chuyển qua Đồ khô
                                                </button>
                                                <button type="button" @click="removeItem(index)" class="text-red-400 hover:text-red-600 text-sm font-bold" title="Xóa">✕</button>
                                            </div>
                                            
                                            <div>
                                                <input v-model="item.item_name" type="text" class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 shadow-sm text-sm" placeholder="VD: Suất cơm gà..." required>
                                                <div v-if="form.errors[`items.${index}.item_name`]" class="text-red-500 text-xs mt-1">{{ form.errors[`items.${index}.item_name`] }}</div>
                                            </div>

                                            <div class="flex gap-2">
                                                <div class="w-1/2">
                                                    <input v-model="item.target_quantity" type="number" min="1" class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 shadow-sm text-sm" placeholder="Số lượng" required>
                                                </div>
                                                <div class="w-1/2">
                                                    <input v-model="item.unit" type="text" class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 shadow-sm text-sm" placeholder="Đơn vị" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                
                                <button type="button" @click="addItem('fresh')" class="w-full text-sm border-2 border-dashed border-gray-300 text-gray-500 hover:text-amber-600 hover:border-amber-400 hover:bg-amber-50 font-semibold py-2 rounded-xl transition">
                                    + Thêm đồ tươi
                                </button>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="bg-gray-50 px-8 py-5 border-t border-gray-100 flex items-center justify-end gap-3">
                    <Link :href="route('charity.campaigns')" class="text-gray-600 hover:text-gray-900 font-medium text-sm px-4 py-2 transition">Hủy bỏ</Link>
                    <button type="submit" :disabled="form.processing" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-6 rounded-xl shadow-md transition disabled:opacity-50">
                        <span v-if="form.processing">Đang xử lý...</span>
                        <span v-else>Cập nhật chiến dịch</span>
                    </button>
                </div>
            </form>

        </div>
    </div>
  </AuthenticatedLayout>
</template>
