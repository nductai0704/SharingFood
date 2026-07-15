<script setup>
import { ref, onMounted, watch, computed, onUnmounted } from 'vue';
import { Head, Link, usePage, router, useForm } from '@inertiajs/vue3';
import ToastMessage from '@/Components/ToastMessage.vue';
import ReportModal from '@/Components/ReportModal.vue';

const showReportModal = ref(false);
const reportTargetUser = ref(null);
const reportTargetPost = ref(null);
const reportTargetClaim = ref(null);

const openReportModal = (user, post, claim = null) => {
    console.log("Opening report modal with:", { user, post, claim });
    reportTargetUser.value = user;
    reportTargetPost.value = post;
    reportTargetClaim.value = claim;
    showReportModal.value = true;
};

const closeReportModal = () => {
    showReportModal.value = false;
    reportTargetUser.value = null;
    reportTargetPost.value = null;
    reportTargetClaim.value = null;
};

const props = defineProps({
    dbMyClaims: Array,
    dbMyDonations: Array,
    dbActiveCampaigns: Array
});

const isMobileMenuOpen = ref(false);
const page = usePage();
const activeTab = ref('food'); // Tab active: 'food' hoặc 'campaign'

// Tọa độ người dùng (Mặc định ban đầu lấy Chợ Bến Thành làm tâm)
const userLat = ref(10.7719);
const userLng = ref(106.6983);
const userAddress = ref('Đang xác định GPS...');
const savedRadius = sessionStorage.getItem('sf_selectedRadius');
const selectedRadius = ref(savedRadius ? parseInt(savedRadius) : 5); // Bán kính quét mặc định: 5 km
const nearbyPosts = ref([]);
const isRadiusDropdownOpen = ref(false);
const radiusDropdownRef = ref(null);

const currentTime = ref(new Date());
let timeTicker = null;

// Quản lý thông báo nhận thực phẩm
const isNotificationOpen = ref(false);
const dismissedNotifications = ref(JSON.parse(localStorage.getItem('sf_dismissed_notifications') || '[]'));

const lastOpenedNotificationTime = ref(localStorage.getItem('sf_last_opened_notifications') || '0');

const unreadNotificationsCount = computed(() => {
    return allNotifications.value.filter(item => {
        const itemTime = new Date(item.created_at || item.claim?.created_at).getTime();
        const lastOpenedTime = new Date(lastOpenedNotificationTime.value).getTime();
        return itemTime > lastOpenedTime;
    }).length;
});

const toggleNotification = () => {
    isNotificationOpen.value = !isNotificationOpen.value;
    if (isNotificationOpen.value) {
        const now = new Date().toISOString();
        localStorage.setItem('sf_last_opened_notifications', now);
        lastOpenedNotificationTime.value = now;
    }
};

const notificationContainerRef = ref(null);

const handleClickOutside = (event) => {
    if (notificationContainerRef.value && !notificationContainerRef.value.contains(event.target)) {
        isNotificationOpen.value = false;
    }
    if (radiusDropdownRef.value && !radiusDropdownRef.value.contains(event.target)) {
        isRadiusDropdownOpen.value = false;
    }
};

const dismissNotification = (claimId, status) => {
    dismissedNotifications.value.push(claimId + '_' + status);
    localStorage.setItem('sf_dismissed_notifications', JSON.stringify(dismissedNotifications.value));
};

const handleDbNotificationClick = (notification) => {
    isNotificationOpen.value = false;
    router.post(route('notifications.read', notification.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (notification.url) {
                router.visit(notification.url);
            }
        }
    });
};

const handleStorageEvent = (e) => {
    if (e.key === 'sf_last_opened_notifications') {
        lastOpenedNotificationTime.value = e.newValue;
    } else if (e.key === 'sf_dismissed_notifications') {
        dismissedNotifications.value = JSON.parse(e.newValue || '[]');
    }
};

const allNotifications = computed(() => {
    const list = [];
    const userId = page.props.auth.user?.id;
    if (!userId) return [];

    // 1. Từ receivedClaims (Mình là Người Cho)
    if (page.props.auth.receivedClaims) {
        page.props.auth.receivedClaims.forEach(claim => {
            if (dismissedNotifications.value.includes(claim.id + '_' + claim.status)) return;

            if (claim.status === 'pending') {
                list.push({
                    id: claim.id,
                    type: 'incoming_pending',
                    title: 'Yêu cầu nhận mới',
                    message: `👤 ${claim.user?.name} muốn nhận ${claim.quantity} ${claim.food_post?.unit} từ bài viết "${claim.food_post?.title}"` + (claim.message ? `\n💬 Ghi chú: ${claim.message}` : ''),
                    claim: claim,
                    created_at: claim.created_at
                });
            } else if (claim.status === 'cancelled' && claim.cancelled_by != userId) {
                // Người nhận hoặc hệ thống hủy
                const actor = claim.cancelled_by === 'system' ? 'Hệ thống' : (claim.user?.name || 'Người nhận');
                list.push({
                    id: claim.id,
                    type: 'incoming_cancelled',
                    title: 'Yêu cầu bị hủy',
                    message: `❌ ${actor} đã hủy yêu cầu nhận từ bài viết "${claim.food_post?.title}" (Lý do: ${claim.cancel_reason || 'Không có lý do'})`,
                    claim: claim,
                    created_at: claim.updated_at || claim.created_at
                });
            }
        });
    }

    // 2. Từ myClaims (Mình là Người Nhận)
    const myClaimsList = page.props.auth.myClaims || [];
    myClaimsList.forEach(claim => {
        if (dismissedNotifications.value.includes(claim.id + '_' + claim.status)) return;

        if (claim.status === 'approved') {
            list.push({
                id: claim.id,
                type: 'outgoing_approved',
                title: 'Yêu cầu được duyệt',
                message: `✅ Yêu cầu nhận thực phẩm từ bài viết "${claim.food_post?.title}" đã được người cho phê duyệt! Vui lòng đến nhận hàng.`,
                claim: claim,
                created_at: claim.updated_at || claim.created_at
            });
        } else if (claim.status === 'rejected') {
            const penalizedReasons = ['Spam/Phá bĩnh', 'Người nhận không đến lấy đúng hẹn', 'Thông tin người nhận không chính xác'];
            const isPenalized = penalizedReasons.includes(claim.cancel_reason);
            list.push({
                id: claim.id,
                type: isPenalized ? 'trust_score_penalized' : 'outgoing_rejected',
                title: isPenalized ? 'Trừ điểm uy tín' : 'Yêu cầu bị từ chối',
                points: isPenalized ? 20 : 0,
                message: isPenalized 
                    ? `⚠️ Yêu cầu nhận thực phẩm từ bài viết "${claim.food_post?.title}" đã bị từ chối (Lý do: ${claim.cancel_reason}). Bạn bị trừ 20 điểm uy tín.`
                    : `❌ Yêu cầu nhận thực phẩm từ bài viết "${claim.food_post?.title}" đã bị người cho từ chối (Lý do: ${claim.cancel_reason || 'Không có lý do'}).`,
                claim: claim,
                created_at: claim.updated_at || claim.created_at
            });
        } else if (claim.status === 'cancelled' && claim.cancelled_by != userId) {
            // Người cho hoặc hệ thống hủy
            const actor = claim.cancelled_by === 'system' ? 'Hệ thống' : 'Người cho';
            list.push({
                id: claim.id,
                type: 'outgoing_cancelled',
                title: 'Giao dịch bị hủy',
                message: `❌ ${actor} đã hủy giao dịch đối với bài viết "${claim.food_post?.title}" (Lý do: ${claim.cancel_reason || 'Không có lý do'})`,
                claim: claim,
                created_at: claim.updated_at || claim.created_at
            });
        }
    });

    // 3. Database Notifications (ví dụ: NewDonationNotification, TrustScoreRewarded)
    if (page.props.auth.notifications) {
        page.props.auth.notifications.forEach(n => {
            if (n.read_at) return;
            
            let title = 'Thông báo';
            if (n.data.type === 'new_donation') title = 'Có lượt quyên góp mới';
            else if (n.data.type === 'trust_score_rewarded') title = 'Cộng điểm uy tín';
            else if (n.data.type === 'trust_score_penalized') title = 'Trừ điểm uy tín';
            
            list.push({
                id: n.id,
                type: n.data.type,
                title: title,
                message: n.data.message,
                url: n.data.url,
                points: n.data.points,
                created_at: n.created_at,
                is_db_notification: true
            });
        });
    }

    // Sắp xếp theo thời gian mới nhất
    return list.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
});

let notificationPollInterval = null;
const startNotificationPolling = () => {
    if (notificationPollInterval) return;
    notificationPollInterval = setInterval(() => {
        router.reload({
            only: ['auth'],
            preserveState: true,
            preserveScroll: true
        });
    }, 5000); // 5 giây/lần để cập nhật trạng thái nhanh hơn
};

const stopNotificationPolling = () => {
    if (notificationPollInterval) {
        clearInterval(notificationPollInterval);
        notificationPollInterval = null;
    }
};

const handleUpdateClaimStatus = (claimId, status) => {
    router.post(route('food-claims.status', claimId), { status }, {
        preserveScroll: true,
        onSuccess: () => {
            fetchNearbyFood();
        }
    });
};

// Quản lý yêu cầu của bản thân (myClaims)
const myClaims = ref([...(page.props.auth.myClaims || [])]);

watch(() => page.props.auth.myClaims, (newVal) => {
    myClaims.value = [...(newVal || [])];
}, { deep: true, immediate: true });

const activeClaims = computed(() => {
    return myClaims.value.filter(claim => claim.status === 'pending' || claim.status === 'approved');
});

const approvedReceivedClaims = computed(() => {
    return page.props.auth.receivedClaims ? page.props.auth.receivedClaims.filter(c => c.status === 'approved') : [];
});

const receiverCancelReasons = [
    'Đổi phương thức nhận hàng',
    'Bận đột xuất không đến được',
    'Xin nhầm số lượng / vật phẩm',
    'Đã tìm được nguồn hỗ trợ khác',
    'Lý do cá nhân khác'
];

const giverCancelReasons = [
    'Thực phẩm đã hỏng/hết hạn thực tế',
    'Hết hàng/Số lượng thực tế không đủ',
    'Người nhận không đến lấy đúng hẹn',
    'Thông tin người nhận không chính xác',
    'Spam/Phá bĩnh',
    'Lý do khác'
];

const showCancelModal = ref(false);
const selectedCancelClaimId = ref(null);
const isProcessing = ref(false);
const cancelForm = ref({
    reason: 'Đổi phương thức nhận hàng'
});

const openCancelModal = (claimId) => {
    selectedCancelClaimId.value = claimId;
    cancelForm.value.reason = 'Đổi phương thức nhận hàng';
    showCancelModal.value = true;
};

const submitCancelClaim = () => {
    if (selectedCancelClaimId.value) {
        isProcessing.value = true;
        router.post(route('food-claims.cancel', selectedCancelClaimId.value), {
            cancel_reason: cancelForm.value.reason
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showCancelModal.value = false;
                selectedCancelClaimId.value = null;
                fetchNearbyFood();
            },
            onFinish: () => {
                isProcessing.value = false;
            }
        });
    }
};

const showGiverCancelModal = ref(false);
const selectedGiverClaimId = ref(null);
const giverCancelForm = ref({
    reason: 'Thực phẩm đã hỏng/hết hạn thực tế',
    status: 'rejected'
});

const openGiverCancelModal = (claimId, status) => {
    selectedGiverClaimId.value = claimId;
    giverCancelForm.value.reason = 'Thực phẩm đã hỏng/hết hạn thực tế';
    giverCancelForm.value.status = status;
    showGiverCancelModal.value = true;
};

const submitGiverCancel = () => {
    if (selectedGiverClaimId.value) {
        isProcessing.value = true;
        if (giverCancelForm.value.status === 'rejected') {
            router.post(route('food-claims.status', selectedGiverClaimId.value), {
                status: 'rejected',
                cancel_reason: giverCancelForm.value.reason
            }, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    showGiverCancelModal.value = false;
                    selectedGiverClaimId.value = null;
                    fetchNearbyFood();
                },
                onFinish: () => {
                    isProcessing.value = false;
                }
            });
        } else {
            router.post(route('food-claims.cancel', selectedGiverClaimId.value), {
                cancel_reason: giverCancelForm.value.reason
            }, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    showGiverCancelModal.value = false;
                    selectedGiverClaimId.value = null;
                    fetchNearbyFood();
                },
                onFinish: () => {
                    isProcessing.value = false;
                }
            });
        }
    }
};

const handleGetDirections = (claim) => {
    if (!claim.food_post || !claim.food_post.latitude || !claim.food_post.longitude) {
        alert('Không tìm thấy tọa độ định vị của bài viết thực phẩm này.');
        return;
    }
    const origin = `${userLat.value},${userLng.value}`;
    const destination = `${claim.food_post.latitude},${claim.food_post.longitude}`;
    const url = `https://www.google.com/maps/dir/?api=1&origin=${origin}&destination=${destination}&travelmode=driving`;
    window.open(url, '_blank');
};

const handleCompleteClaim = (claimId) => {
    if (confirm('Bạn có chắc chắn muốn xác nhận đã giao xong thực phẩm này cho người nhận?')) {
        router.post(route('food-claims.complete', claimId), {}, {
            preserveScroll: true,
            onSuccess: () => {
                fetchNearbyFood();
            }
        });
    }
};

const savedSearchQuery = sessionStorage.getItem('sf_searchQuery');
const searchQuery = ref(savedSearchQuery || '');

watch(searchQuery, (newVal) => {
    sessionStorage.setItem('sf_searchQuery', newVal);
    if (typeof updateMarkersOnMap === 'function') {
        updateMarkersOnMap();
    }
});

// Quản lý modal gửi yêu cầu nhận thực phẩm lẻ
const selectedClaimPost = ref(null);
const claimForm = ref({
    quantity: 1,
    message: '',
    shipping_method: 'self_pickup',
    pickup_contact_name: '',
    pickup_contact_phone: '',
    delivery_service_company: '',
    driver_license_plate: ''
});

const openClaimModal = (post) => {
    if (page.props.auth.user && page.props.auth.user.is_locked) {
        alert("Tài khoản của bạn đang bị khóa giao dịch trong 5 ngày do Điểm uy tín dưới 50. Bạn không thể gửi yêu cầu xin thực phẩm lúc này.");
        return;
    }
    selectedClaimPost.value = post;
    claimForm.value = {
        quantity: 1,
        message: '',
        shipping_method: 'self_pickup',
        pickup_contact_name: '',
        pickup_contact_phone: '',
        delivery_service_company: '',
        driver_license_plate: ''
    };
};

const closeClaimModal = () => {
    selectedClaimPost.value = null;
};

const submitClaim = () => {
    if (selectedClaimPost.value) {
        router.post(route('food-posts.claim', selectedClaimPost.value.id), claimForm.value, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeClaimModal();
                fetchNearbyFood();
            }
        });
    }
};

// Bật/Tắt hiển thị bài viết của mình
const savedShowMyPosts = sessionStorage.getItem('sf_showMyPosts');
const showMyPosts = ref(savedShowMyPosts !== null ? savedShowMyPosts === 'true' : true);

const activeNearbyPosts = computed(() => {
    return nearbyPosts.value.filter(post => {
        const isAvailable = post.status === 'available' && new Date(post.expires_at) > currentTime.value;
        if (!isAvailable) return false;
        
        if (!showMyPosts.value && page.props.auth.user) {
            return post.user_id !== page.props.auth.user.id;
        }
        return true;
    });
});

// Khai báo biến giữ các thực thể Leaflet Map
let map = null;
let userMarker = null;
let markersGroup = null;

// Gọi API lấy dữ liệu thức ăn lân cận thực tế
const fetchNearbyFood = async () => {
    try {
        let url = `/api/nearby-food?latitude=${userLat.value}&longitude=${userLng.value}&radius=${selectedRadius.value}`;
        
        if (searchQuery.value) {
            url += `&search=${encodeURIComponent(searchQuery.value)}`;
        }
        
        const response = await fetch(url);
        const result = await response.json();
        if (result.success) {
            nearbyPosts.value = result.data;
            updateMarkersOnMap();
            
            // Tự động chuyển tab nếu tìm kiếm
            if (searchQuery.value) {
                const hasFood = nearbyPosts.value && nearbyPosts.value.length > 0;
                const hasCampaign = filteredCampaigns.value && filteredCampaigns.value.length > 0;
                
                if (activeTab.value === 'food' && !hasFood && hasCampaign) {
                    activeTab.value = 'campaign';
                } else if (activeTab.value === 'campaign' && !hasCampaign && hasFood) {
                    activeTab.value = 'food';
                }
            }
        }
    } catch (error) {
        console.error('Lỗi khi gọi API thức ăn lân cận:', error);
    }
};

// Xin quyền định vị GPS từ trình duyệt hoặc lấy từ Profile nếu đã đăng ký
const getUserLocation = () => {
    const authUser = page.props.auth.user;
    
    // 1. Ưu tiên lấy tọa độ đã được lưu trong Profile thông tin tài khoản
    if (authUser && authUser.latitude && authUser.longitude) {
        userLat.value = parseFloat(authUser.latitude);
        userLng.value = parseFloat(authUser.longitude);
        userAddress.value = authUser.address || `Tọa độ lưu sẵn: ${userLat.value.toFixed(5)}, ${userLng.value.toFixed(5)}`;
        
        initMap();
        fetchNearbyFood();
        return; // Thoát hàm, không cần xin quyền GPS của trình duyệt nữa
    }

    // 2. Nếu tài khoản chưa có tọa độ, xin quyền GPS trình duyệt như cũ
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                userLat.value = position.coords.latitude;
                userLng.value = position.coords.longitude;
                userAddress.value = `Đã định vị GPS: ${userLat.value.toFixed(5)}, ${userLng.value.toFixed(5)}`;
                
                initMap();
                fetchNearbyFood();
            },
            (error) => {
                console.warn("Không thể lấy định vị thực tế (Dùng mặc định Chợ Bến Thành):", error.message);
                userAddress.value = "Chợ Bến Thành, Quận 1, TP.HCM (Mặc định)";
                initMap();
                fetchNearbyFood();
            },
            { enableHighAccuracy: true, timeout: 7000 }
        );
    } else {
        userAddress.value = "Trình duyệt không hỗ trợ GPS (Mặc định Bến Thành)";
        initMap();
        fetchNearbyFood();
    }
};

// ---- THÔNG TIN SHIPPER CỦA QUYÊN GÓP ----
const showShipperModal = ref(false);
const selectedDonationForShipper = ref(null);
const shipperForm = useForm({
    shipper_name: '',
    shipper_license_plate: ''
});

const openShipperModal = (donation) => {
    selectedDonationForShipper.value = donation;
    selectedClaimForShipper.value = null;
    shipperForm.shipper_name = donation.shipper_name || '';
    shipperForm.shipper_license_plate = donation.shipper_license_plate || '';
    showShipperModal.value = true;
};

const selectedClaimForShipper = ref(null);
const openClaimShipperModal = (claim) => {
    selectedClaimForShipper.value = claim;
    selectedDonationForShipper.value = null;
    shipperForm.shipper_name = claim.delivery_service_company || '';
    shipperForm.shipper_license_plate = claim.driver_license_plate || '';
    showShipperModal.value = true;
};

const submitShipperForm = () => {
    if (selectedDonationForShipper.value) {
        shipperForm.patch(route('donations.update_shipper', selectedDonationForShipper.value.donation_code), {
            preserveScroll: true,
            onSuccess: () => {
                showShipperModal.value = false;
            }
        });
    } else if (selectedClaimForShipper.value) {
        shipperForm.patch(route('food-claims.update_shipper', selectedClaimForShipper.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showShipperModal.value = false;
                // Nếu đang dùng computed từ inertia props thì trang sẽ tự reload dữ liệu mới
            }
        });
    }
};

const cancelDonation = (donationCode) => {
    if (confirm('Bạn có chắc chắn muốn hủy đơn quyên góp này không?')) {
        router.post(route('donations.cancel', donationCode), {}, {
            preserveScroll: true
        });
    }
};


const groupedMyDonations = computed(() => {
    if (!props.dbMyDonations) return [];
    
    const groups = {};
    props.dbMyDonations.forEach(donation => {
        const code = donation.donation_code;
        if (!groups[code]) {
            groups[code] = {
                donation_code: code,
                campaign: donation.campaign,
                status: donation.status,
                shipping_method: donation.shipping_method,
                shipper_name: donation.shipper_name,
                shipper_license_plate: donation.shipper_license_plate,
                expires_at: donation.expires_at,
                items: []
            };
        }
        groups[code].items.push({
            id: donation.id,
            item_name: donation.campaign_item?.item_name,
            quantity: donation.donation_quantity,
            unit: donation.campaign_item?.unit || ''
        });
    });
    
    return Object.values(groups);
});

const filteredCampaigns = computed(() => {
    if (!props.dbActiveCampaigns) return [];
    if (!searchQuery.value) return props.dbActiveCampaigns;
    
    const query = searchQuery.value.toLowerCase();
    return props.dbActiveCampaigns.filter(campaign => {
        const titleMatch = campaign.title?.toLowerCase().includes(query);
        const orgMatch = campaign.user?.name?.toLowerCase().includes(query);
        return titleMatch || orgMatch;
    });
});

// Khởi tạo bản đồ Leaflet
const initMap = () => {
    if (typeof L === 'undefined') return;

    if (!map) {
        // Gắn bản đồ vào phần tử có id='map'
        map = L.map('map').setView([userLat.value, userLng.value], 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(map);

        markersGroup = L.layerGroup().addTo(map);
    } else {
        map.setView([userLat.value, userLng.value], 14);
    }

    // Đánh dấu vị trí người dùng bằng Marker đỏ
    const redIcon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    if (userMarker) {
        userMarker.setLatLng([userLat.value, userLng.value]);
    } else {
        userMarker = L.marker([userLat.value, userLng.value], { icon: redIcon })
            .addTo(map)
            .bindPopup('Vị trí của bạn')
            .openPopup();
    }
};

// Đánh dấu các địa điểm có đồ ăn mẫu bằng Marker xanh lá và chiến dịch bằng Marker xanh dương
const updateMarkersOnMap = () => {
    if (typeof L === 'undefined' || !markersGroup) return;

    // Xóa hết marker cũ của lần quét trước
    markersGroup.clearLayers();

    // 1. Marker xanh lá (Thực phẩm lẻ)
    const greenIcon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    activeNearbyPosts.value.forEach(post => {
        L.marker([post.latitude, post.longitude], { icon: greenIcon })
            .addTo(markersGroup)
            .bindPopup(`
                <div style="font-family: sans-serif; width: 170px;">
                    <b style="font-size: 13px; color: #1f2937;">${post.title}</b>
                    <p style="font-size: 11px; color: #059669; margin: 4px 0 0 0; font-weight: bold;">Cách bạn: ${parseFloat(post.distance).toFixed(2)} km</p>
                    <p style="font-size: 10px; color: #6b7280; margin: 4px 0 0 0;">Số lượng: ${post.remain_quantity} ${post.unit}</p>
                </div>
            `);
    });

    // 2. Marker xanh dương (Chiến dịch quyên góp lớn)
    const blueIcon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    if (filteredCampaigns.value && filteredCampaigns.value.length > 0) {
        filteredCampaigns.value.forEach(campaign => {
            if (campaign.latitude && campaign.longitude) {
                L.marker([campaign.latitude, campaign.longitude], { icon: blueIcon })
                    .addTo(markersGroup)
                    .bindPopup(`
                        <div style="font-family: sans-serif; width: 180px;">
                            <b style="font-size: 13px; color: #1d4ed8;">${campaign.title}</b>
                            <p style="font-size: 11px; color: #3b82f6; margin: 4px 0 0 0; font-weight: bold;">Tổ chức: ${campaign.user?.name || 'Tổ chức từ thiện'}</p>
                            <p style="font-size: 10px; color: #6b7280; margin: 4px 0 0 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">${campaign.description}</p>
                        </div>
                    `);
            }
        });
    }
};

// Hàm tính thời gian hết hạn thân thiện
const getExpiryLabel = (expiresAtStr) => {
    const expiresAt = new Date(expiresAtStr);
    const now = new Date();
    const diffMs = expiresAt - now;
    if (diffMs <= 0) return 'Đã hết hạn';
    
    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
    if (diffHours < 24) {
        if (diffHours === 0) {
            const diffMins = Math.floor(diffMs / (1000 * 60));
            return `Còn ${diffMins} phút`;
        }
        return `Còn ${diffHours} giờ`;
    }
    const diffDays = Math.floor(diffHours / 24);
    return `Còn ${diffDays} ngày`;
};

// Hàm tính thời gian đăng bài thân thiện
const getCreatedTimeLabel = (createdAtStr) => {
    const createdAt = new Date(createdAtStr);
    const now = new Date();
    const diffMs = now - createdAt;
    if (diffMs <= 0) return 'Vừa xong';
    
    const diffMins = Math.floor(diffMs / (1000 * 60));
    if (diffMins < 60) {
        return diffMins === 0 ? 'Vừa xong' : `${diffMins} phút trước`;
    }
    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) {
        return `${diffHours} giờ trước`;
    }
    const diffDays = Math.floor(diffHours / 24);
    return `${diffDays} ngày trước`;
};

const countdowns = ref({});
let countdownTicker = null;

const startCountdowns = () => {
    countdownTicker = setInterval(() => {
        const now = new Date().getTime();
        // Lấy danh sách các đơn đã duyệt (bài mình xin và bài người ta xin mình)
        const allApproved = [
            ...(page.props.auth.receivedClaims || []), 
            ...myClaims.value
        ].filter(c => c.status === 'approved' && c.expires_at);

        const newCountdowns = { ...countdowns.value };
        
        allApproved.forEach(claim => {
            if (claim.is_disputed) {
                newCountdowns[claim.id] = 'Tạm dừng';
                return;
            }

            const expireTime = new Date(claim.expires_at).getTime();
            const distance = expireTime - now;

            if (distance < 0) {
                newCountdowns[claim.id] = '00:00';
                // Tự động gọi API hủy (cần flag cờ để tránh gọi liên tục)
                if (!claim._is_auto_cancelling) {
                    claim._is_auto_cancelling = true;
                    router.post(route('food-claims.cancel', claim.id), {
                        cancel_reason: 'Hệ thống tự động hủy do người nhận không đến lấy (Quá hạn Timer)'
                    }, {
                        preserveScroll: true,
                        onSuccess: () => fetchNearbyFood()
                    });
                }
            } else {
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                newCountdowns[claim.id] = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            }
        });
        
        countdowns.value = newCountdowns;
    }, 1000);
};

onMounted(() => {
    getUserLocation();
    startCountdowns();
    startNotificationPolling();
    document.addEventListener('click', handleClickOutside);
    timeTicker = setInterval(() => {
        currentTime.value = new Date();
    }, 10000); // 10 giây cập nhật 1 lần

    // Lắng nghe real-time claim status updates qua Reverb
    const userId = page.props.auth.user?.id;
    if (userId && window.Echo) {
        window.Echo.private(`user.${userId}`)
            .listen('.claim.status.updated', (e) => {
                // Cập nhật ngay lập tức khi nhận được sự kiện
                router.reload({
                    only: ['auth'],
                    preserveState: true,
                    preserveScroll: true
                });
                // Đồng thời fetch lại data bài đăng
                fetchNearbyFood();
            });
    }

    // Lắng nghe thao tác xử lý yêu cầu từ layout để cập nhật giao diện
    window.addEventListener('claim-status-updated', fetchNearbyFood);
    
    // Lắng nghe thay đổi từ các tab khác (đồng bộ chuông)
    window.addEventListener('storage', handleStorageEvent);
});

onUnmounted(() => {
    if (timeTicker) clearInterval(timeTicker);
    if (countdownTicker) clearInterval(countdownTicker);
    stopNotificationPolling();
    document.removeEventListener('click', handleClickOutside);
    window.removeEventListener('claim-status-updated', fetchNearbyFood);
    window.removeEventListener('storage', handleStorageEvent);

    // Dọn dẹp Echo listener
    const userId = page.props.auth.user?.id;
    if (userId && window.Echo) {
        window.Echo.leave(`user.${userId}`);
    }
});

watch(activeNearbyPosts, () => {
    updateMarkersOnMap();
}, { deep: true });

// Lắng nghe thay đổi của biến Bán kính để tự động quét lại dữ liệu
watch(selectedRadius, (newVal) => {
    sessionStorage.setItem('sf_selectedRadius', newVal);
    fetchNearbyFood();
});

// Lắng nghe thay đổi toggle bài viết của tôi
watch(showMyPosts, (newVal) => {
    sessionStorage.setItem('sf_showMyPosts', newVal);
});

// Theo dõi khi thông tin User thay đổi (ví dụ: khi cập nhật Profile và quay lại Trang chủ qua SPA)
watch(() => page.props.auth.user, (newUser) => {
    if (newUser && newUser.latitude && newUser.longitude) {
        const newLat = parseFloat(newUser.latitude);
        const newLng = parseFloat(newUser.longitude);
        
        // Chỉ cập nhật nếu tọa độ thực sự thay đổi khác biệt
        if (Math.abs(userLat.value - newLat) > 0.00001 || Math.abs(userLng.value - newLng) > 0.00001) {
            userLat.value = newLat;
            userLng.value = newLng;
            userAddress.value = newUser.address || `Tọa độ mới: ${userLat.value.toFixed(5)}, ${userLng.value.toFixed(5)}`;
            
            initMap();
            fetchNearbyFood();
        }
    }
}, { deep: true });

// ---- DONATION MODAL LOGIC ----
const showDonationModal = ref(false);
const selectedCampaignForDonation = ref(null);
const donationForm = useForm({
    items: [],
    food_description: '',
    shipping_method: 'self_delivery'
});

const showCampaignDetailModal = ref(false);
const selectedCampaignDetail = ref(null);

const openCampaignDetailModal = (campaign) => {
    selectedCampaignDetail.value = campaign;
    showCampaignDetailModal.value = true;
};

const openDonationModal = (campaign) => {
    if (!page.props.auth.user) {
        router.visit(route('login'));
        return;
    }
    if (page.props.auth.user.is_locked) {
        alert("Tài khoản của bạn đang bị khóa giao dịch trong 5 ngày do Điểm uy tín dưới 50. Bạn không thể quyên góp lúc này.");
        return;
    }
    selectedCampaignForDonation.value = campaign;
    
    // Khởi tạo danh sách các món đồ có thể quyên góp (chỉ hiển thị món chưa đủ số lượng)
    donationForm.items = (campaign.items || []).filter(item => {
        const pending = parseInt(item.pending_quantity || 0, 10);
        const total = item.current_quantity + pending;
        return total < item.target_quantity;
    }).map((item, index) => {
        const pending = parseInt(item.pending_quantity || 0, 10);
        const remaining = item.target_quantity - (item.current_quantity + pending);
        const isFreshLocked = item.item_type === 'fresh' && new Date() < new Date(campaign.web_deadline);
        return {
            campaign_item_id: item.id,
            item_name: item.item_name,
            target_quantity: item.target_quantity,
            current_quantity: item.current_quantity,
            pending_quantity: pending,
            remaining_quantity: remaining,
            donation_quantity: 1,
            unit: item.unit || '',
            item_type: item.item_type || 'dry',
            is_locked_fresh: isFreshLocked,
            web_deadline: campaign.web_deadline, // Để hiển thị ngày trong UI
            selected: index === 0 && !isFreshLocked // Chọn sẵn món đầu tiên nếu không bị khóa
        };
    });
    
    donationForm.food_description = '';
    donationForm.shipping_method = 'self_delivery';
    showDonationModal.value = true;
};

const submitDonation = () => {
    const hasSelected = donationForm.items.some(i => i.selected);
    if (!hasSelected) {
        alert('Vui lòng chọn ít nhất một sản phẩm để quyên góp!');
        return;
    }

    donationForm.transform((data) => ({
        ...data,
        items: data.items.filter(i => i.selected)
    })).post(route('campaign-donations.store', selectedCampaignForDonation.value.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showDonationModal.value = false;
        }
    });
};
</script>


<template>
  <Head title="Giao diện Người dùng - ShareFood" />

  <div class="min-h-screen bg-gray-50 text-gray-800 font-sans">
    <nav class="bg-white border-b border-gray-100 sticky top-0 z-50 shadow-sm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <Link href="/" class="flex items-center space-x-3 group">
            <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-md shadow-emerald-200 group-hover:scale-105 transition-transform duration-200">S</div>
            <span class="text-xl font-bold text-gray-950 tracking-tight group-hover:text-emerald-600 transition-colors duration-200">ShareFood<span class="text-emerald-600">.vn</span></span>
          </Link>
          <!-- Desktop menu (hidden on mobile) -->
          <div class="hidden md:flex items-center space-x-6">
            <Link href="/" class="text-emerald-600 font-medium text-sm">Trang chủ</Link>
            <Link :href="route('food-posts.index')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Thực phẩm</Link>
            <Link v-if="$page.props.auth.user && $page.props.auth.user.role === 'charity'" :href="route('charity.campaigns')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Chiến dịch từ thiện</Link>
            <div class="h-8 w-px bg-gray-200"></div>
            
            <!-- Đã đăng nhập -->
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
                <span :class="$page.props.auth.user.trust_score < 70 ? 'text-red-600 bg-red-50 border-red-200' : 'text-amber-600 bg-amber-50 border-amber-200'" class="ml-1 text-xs font-bold px-2 py-0.5 rounded-full border shadow-sm flex items-center gap-1" title="Điểm uy tín">
                  <svg :class="$page.props.auth.user.trust_score < 70 ? 'text-red-500' : 'text-amber-500'" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                  {{ $page.props.auth.user.trust_score }}
                </span>
              </Link>
              <Link :href="route('logout')" method="post" as="button" class="text-sm text-red-600 hover:text-red-700 font-semibold transition">
                Đăng xuất
              </Link>
              
              <!-- Notification Bell Icon (Next to logout) -->
              <div class="relative" ref="notificationContainerRef">
                <button 
                  @click="toggleNotification" 
                  class="relative p-2 text-gray-500 hover:text-emerald-600 focus:outline-none transition rounded-full hover:bg-gray-50 cursor-pointer"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                  </svg>
                  <!-- Badge số lượng thông báo chưa xem -->
                  <span 
                    v-if="unreadNotificationsCount > 0" 
                    class="absolute top-1 right-1 bg-red-500 text-white text-[8px] font-bold w-4 h-4 rounded-full flex items-center justify-center border border-white"
                  >
                    {{ unreadNotificationsCount }}
                  </span>
                </button>

                <!-- Dropdown thông báo -->
                <div 
                  v-if="isNotificationOpen" 
                  class="absolute right-0 mt-2 w-80 bg-white border border-gray-100 rounded-2xl shadow-xl z-50 overflow-hidden animate-in fade-in slide-in-from-top-2 duration-150"
                >
                  <div class="p-3 border-b border-gray-50 flex justify-between items-center bg-gray-50/50">
                    <span class="text-xs font-bold text-gray-800">Thông báo hệ thống</span>
                    <span class="text-[10px] text-gray-400 font-medium">{{ allNotifications.length }} thông báo mới</span>
                  </div>
                  <div class="max-h-72 overflow-y-auto divide-y divide-gray-50">
                    <div v-if="allNotifications.length === 0" class="p-4 text-center text-gray-400 text-xs">
                      Không có thông báo nào.
                    </div>
                    <div 
                      v-else 
                      v-for="item in allNotifications" 
                      :key="item.type + '-' + item.id" 
                      class="p-3 hover:bg-gray-50/50 space-y-2 transition text-xs"
                    >
                      <div class="flex justify-between items-start gap-1">
                        <div class="text-gray-700 leading-normal text-left space-y-1 w-full">
                          <!-- Tiêu đề & Loại thông báo -->
                          <div class="flex justify-between items-center">
                            <span class="font-bold text-[10px] uppercase tracking-wider text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-100" v-if="item.type === 'incoming_pending'">Yêu cầu mới</span>
                            <span class="font-bold text-[10px] uppercase tracking-wider text-red-700 bg-red-50 px-1.5 py-0.5 rounded border border-red-100" v-else-if="item.type === 'incoming_cancelled' || item.type === 'outgoing_cancelled'">Đã huỷ</span>
                            <span class="font-bold text-[10px] uppercase tracking-wider text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-100" v-else-if="item.type === 'outgoing_approved'">Đã duyệt</span>
                            <span class="font-bold text-[10px] uppercase tracking-wider text-red-700 bg-red-50 px-1.5 py-0.5 rounded border border-red-100" v-else-if="item.type === 'outgoing_rejected'">Từ chối</span>
                            <span class="font-bold text-[10px] uppercase tracking-wider text-purple-700 bg-purple-50 px-1.5 py-0.5 rounded border border-purple-100" v-else-if="item.type === 'new_donation'">Quyên góp mới</span>
                            <span class="font-bold text-[10px] uppercase tracking-wider text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-100" v-else-if="item.type === 'trust_score_rewarded'">⭐ +10 Uy tín</span>
                            <span class="font-bold text-[10px] uppercase tracking-wider text-red-700 bg-red-50 px-1.5 py-0.5 rounded border border-red-100" v-else-if="item.type === 'trust_score_penalized'">⚠️ -{{ item.points || 10 }} Uy tín</span>
                          </div>

                          <p v-if="item.type === 'incoming_pending'" class="text-gray-800 text-[11px] leading-relaxed">
                              Muốn nhận <span class="font-bold text-emerald-600 bg-emerald-50 px-1 py-0.5 rounded border border-emerald-100">{{ item.claim.quantity }} {{ item.claim.food_post?.unit }}</span> từ bài viết <span class="font-bold text-blue-700 bg-blue-50 px-1 py-0.5 rounded border border-blue-100">"{{ item.claim.food_post?.title }}"</span>
                          </p>
                          <p v-else class="text-gray-800 text-xs">{{ item.message }}</p>

                          <!-- Hiển thị phương thức lấy hàng cho Incoming Pending -->
                          <div v-if="item.type === 'incoming_pending'" class="text-[10px] text-gray-500 bg-gray-50 p-1.5 rounded border border-gray-100/50 mt-1">
                            <div class="flex items-center gap-1 mb-1">
                                <span class="font-bold text-gray-700">Người xin: {{ item.claim.user?.name }}</span>
                                <span v-if="item.claim.user" :class="item.claim.user.trust_score < 70 ? 'text-red-600 bg-red-50 border-red-200' : 'text-amber-600 bg-amber-50 border-amber-200'" class="text-[9px] font-bold px-1.5 py-0.5 rounded-full border flex items-center gap-0.5" title="Điểm uy tín">
                                  ⭐ {{ item.claim.user.trust_score }}
                                </span>
                            </div>
                            <div class="mb-1">
                                🚚 <b>Cách nhận:</b>
                                <span v-if="item.claim.shipping_method === 'self_pickup'" class="text-blue-600 font-semibold ml-1">Tự đến lấy</span>
                                <span v-else-if="item.claim.shipping_method === 'relative_pickup'" class="text-indigo-600 font-semibold ml-1">Nhờ người thân lấy ({{ item.claim.pickup_contact_name }})</span>
                                <span v-else-if="item.claim.shipping_method === 'delivery_service'" class="text-orange-600 font-semibold ml-1">Giao hàng ({{ item.claim.delivery_service_company }})</span>
                            </div>
                            <div v-if="item.claim?.message" class="pt-1.5 border-t border-gray-200 text-gray-600 italic">
                                💬 <b>Ghi chú:</b> {{ item.claim.message }}
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="flex justify-between items-center text-[10px] text-gray-400">
                        <span>{{ new Date(item.created_at).toLocaleString('vi-VN', {hour: '2-digit', minute:'2-digit', day:'2-digit', month:'2-digit', year:'numeric'}) }}</span>
                      </div>
                      <!-- Các nút thao tác -->
                      <div class="flex items-center gap-2 pt-1">
                        <!-- Yêu cầu mới cần Duyệt / Từ chối -->
                        <template v-if="item.type === 'incoming_pending'">
                          <button 
                            @click.stop="handleUpdateClaimStatus(item.id, 'approved')" 
                            class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] py-1 rounded-lg transition text-center cursor-pointer shadow-sm shadow-emerald-100 hover:shadow-emerald-200"
                          >
                            Duyệt
                          </button>
                          <button 
                            @click.stop="openGiverCancelModal(item.id, 'rejected')" 
                            class="flex-1 bg-gray-50 hover:bg-red-50 text-gray-600 hover:text-red-600 font-semibold text-[10px] py-1 rounded-lg border border-gray-200 hover:border-red-100 transition text-center cursor-pointer"
                          >
                            Từ chối
                          </button>
                        </template>
                        <!-- Database Notifications -->
                        <template v-else-if="item.is_db_notification">
                          <button 
                            @click.stop="handleDbNotificationClick(item)" 
                            :class="item.url ? 'bg-purple-600 hover:bg-purple-700 text-white shadow-sm' : 'bg-gray-100 hover:bg-gray-200 text-gray-700'"
                            class="w-full font-bold text-[10px] py-1.5 rounded-lg transition text-center cursor-pointer"
                          >
                            {{ item.url ? 'Xem chi tiết' : 'Đóng thông báo' }}
                          </button>
                        </template>
                        <!-- Các thông báo khác chỉ cần nút Đóng (Dismiss) -->
                        <template v-else>
                          <button 
                            @click.stop="dismissNotification(item.id, item.claim.status)" 
                            class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-[10px] py-1.5 rounded-lg transition text-center cursor-pointer"
                          >
                            Đóng thông báo
                          </button>
                        </template>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Chưa đăng nhập -->
            <div v-else class="flex items-center space-x-4">
              <Link :href="route('login')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Đăng nhập</Link>
              <Link :href="route('register')" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-sm px-4 py-2 rounded-xl transition shadow-sm">Đăng ký</Link>
            </div>
          </div>

          <!-- Mobile menu button & QR Scan -->
          <div class="flex items-center md:hidden space-x-2">
            <!-- Nút quét QR Mobile giả lập -->
            <Link v-if="$page.props.auth.user" :href="route('qr.scanner')" class="text-emerald-600 hover:text-emerald-700 bg-emerald-50 focus:outline-none p-2 rounded-lg border border-emerald-100 transition shadow-sm">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
              </svg>
            </Link>

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
          <Link href="/" class="text-emerald-600 font-medium text-sm">Trang chủ</Link>
          <Link :href="route('food-posts.index')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Thực phẩm</Link>
          <Link v-if="$page.props.auth.user && $page.props.auth.user.role === 'charity'" :href="route('charity.campaigns')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition">Chiến dịch từ thiện</Link>
        </div>
        
        <div class="h-px bg-gray-100"></div>
        
        <!-- Đã đăng nhập (Mobile) -->
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
            <span :class="$page.props.auth.user.trust_score < 70 ? 'text-red-600 bg-red-50 border-red-200' : 'text-amber-600 bg-amber-50 border-amber-200'" class="ml-1 text-xs font-bold px-2 py-0.5 rounded-full border shadow-sm flex items-center gap-1" title="Điểm uy tín">
              <svg :class="$page.props.auth.user.trust_score < 70 ? 'text-red-500' : 'text-amber-500'" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
              {{ $page.props.auth.user.trust_score }}
            </span>
          </Link>
          <Link :href="route('logout')" method="post" as="button" class="text-sm text-left text-red-600 hover:text-red-700 font-semibold transition">
            Đăng xuất
          </Link>
        </div>

        <!-- Chưa đăng nhập (Mobile) -->
        <div v-else class="flex flex-col space-y-3 pt-1">
          <Link :href="route('login')" class="text-gray-600 hover:text-emerald-600 font-medium text-sm transition block">Đăng nhập</Link>
          <Link :href="route('register')" class="bg-emerald-600 hover:bg-emerald-700 text-white text-center font-medium text-sm px-4 py-2.5 rounded-xl transition shadow-sm block">Đăng ký thành viên</Link>
        </div>
      </div>
    </nav>
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- CỘT TRÁI (2/3): Khung tìm kiếm xanh + Nội dung chính phân Tab -->
        <div class="lg:col-span-2 space-y-6">
          <!-- KHUNG XANH: Tìm kiếm Thực phẩm Khả dụng Lân cận -->
          <div class="bg-gradient-to-br from-emerald-600 to-teal-700 rounded-3xl p-8 text-white shadow-xl shadow-emerald-100 relative flex flex-col justify-center h-auto lg:h-[350px]">
            <!-- Decorative background wrapper -->
            <div class="absolute inset-0 overflow-hidden rounded-3xl pointer-events-none">
              <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
              <div class="absolute -left-10 -top-10 w-40 h-40 bg-emerald-500/20 rounded-full blur-2xl"></div>
            </div>
            <div class="relative z-10 max-w-2xl space-y-4">
              <span class="bg-emerald-500/30 text-emerald-100 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider">Định vị không gian GPS</span>
              <h1 class="text-3xl font-extrabold tracking-tight md:text-4xl">Tìm kiếm Thực phẩm Khả dụng Lân cận</h1>
              <p class="text-emerald-100/90 leading-relaxed text-sm md:text-base">Hệ thống tự động xác định khoảng cách địa lý để kết nối và hiển thị chính xác các nguồn thực phẩm dư thừa cùng các chiến dịch quyên góp trong phạm vi lân cận của bạn.</p>
              
              <div class="flex flex-col gap-3 pt-3 max-w-2xl">
                <!-- Hàng 1: Vị trí và Bán kính -->
                <div class="flex items-center gap-3 w-full">
                  <!-- Hiển thị GPS động -->
                  <div class="flex-1 bg-white/10 backdrop-blur-md border border-white/10 rounded-xl px-4 py-2.5 flex items-center space-x-2 text-sm overflow-hidden">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse flex-shrink-0"></span>
                    <span class="truncate">
                      📍 Vị trí: {{ userAddress }}
                    </span>
                  </div>
                  
                  <!-- Chọn bán kính lọc kết nối với biến selectedRadius (Custom Dropdown) -->
                  <div class="relative flex-shrink-0 w-44" ref="radiusDropdownRef">
                    <button 
                      @click="isRadiusDropdownOpen = !isRadiusDropdownOpen"
                      class="w-full flex items-center justify-between bg-white/10 backdrop-blur-md border border-white/10 text-white rounded-xl pl-4 pr-3 py-2.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-emerald-400 shadow-sm transition-all duration-300 hover:bg-white/20"
                    >
                      <span>Bán kính: {{ selectedRadius }} km</span>
                      <svg :class="isRadiusDropdownOpen ? 'rotate-180' : ''" class="w-4 h-4 text-white/70 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                      </svg>
                    </button>

                    <!-- Dropdown List -->
                    <transition
                      enter-active-class="transition duration-200 ease-out"
                      enter-from-class="transform scale-95 opacity-0"
                      enter-to-class="transform scale-100 opacity-100"
                      leave-active-class="transition duration-75 ease-in"
                      leave-from-class="transform scale-100 opacity-100"
                      leave-to-class="transform scale-95 opacity-0"
                    >
                      <div 
                        v-if="isRadiusDropdownOpen" 
                        class="absolute top-full left-0 mt-2 w-full bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-50 origin-top"
                      >
                        <ul class="py-1">
                          <li 
                            v-for="val in [2, 5, 10, 15]" 
                            :key="val"
                            @click="selectedRadius = val; isRadiusDropdownOpen = false; fetchNearbyFood()"
                            class="px-4 py-2.5 text-sm text-gray-700 hover:bg-emerald-50 hover:text-emerald-700 cursor-pointer transition-colors duration-150 flex items-center justify-between"
                          >
                            <span :class="{'font-bold text-emerald-600': selectedRadius === val}">Bán kính: {{ val }} km</span>
                            <svg v-if="selectedRadius === val" class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                          </li>
                        </ul>
                      </div>
                    </transition>
                  </div>
                </div>

                <!-- Hàng 2: Ô TÌM KIẾM THEO TÊN / DANH MỤC THỰC PHẨM (Premium UI) -->
                <div class="relative w-full group">
                  <!-- Icon kính lúp sang trọng -->
                  <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none transition-transform group-focus-within:scale-110">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                  </div>
                  
                  <!-- Ô nhập liệu bo tròn lớn, bóng đổ nổi bật -->
                  <input 
                    type="text" 
                    v-model="searchQuery"
                    @keyup.enter="fetchNearbyFood"
                    class="w-full bg-white text-gray-900 rounded-2xl pl-12 pr-28 py-3.5 text-sm font-semibold border-0 focus:ring-4 focus:ring-emerald-400/50 shadow-lg placeholder-gray-400 transition-all duration-300"
                    placeholder="Nhập tên thực phẩm, chiến dịch hoặc mái ấm..."
                  />
                  
                  <!-- Nút bấm nhúng thẳng vào trong ô nhập -->
                  <button 
                    @click="fetchNearbyFood"
                    class="absolute right-1.5 top-1.5 bottom-1.5 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-bold text-xs px-5 rounded-xl transition-all shadow-md shadow-emerald-500/30 whitespace-nowrap flex items-center"
                  >
                    Tìm kiếm
                  </button>
                </div>
              </div>
            </div>
          </div>
          <!-- Thanh Tabs chuyển đổi -->
          <div class="flex border border-gray-100 bg-white rounded-2xl p-1 shadow-sm">
            <button 
              @click="activeTab = 'food'"
              :class="[
                activeTab === 'food' 
                  ? 'bg-emerald-600 text-white font-bold shadow-sm' 
                  : 'text-gray-600 hover:text-emerald-600 hover:bg-emerald-50/50'
              ]"
              class="flex-1 py-3 px-4 text-center rounded-xl font-semibold text-sm transition duration-200 cursor-pointer flex items-center justify-center gap-2"
            >
              🥗 Thực phẩm cộng đồng
            </button>
            <button 
              @click="activeTab = 'campaign'"
              :class="[
                activeTab === 'campaign' 
                  ? 'bg-emerald-600 text-white font-bold shadow-sm' 
                  : 'text-gray-600 hover:text-emerald-600 hover:bg-emerald-50/50'
              ]"
              class="flex-1 py-3 px-4 text-center rounded-xl font-semibold text-sm transition duration-200 cursor-pointer flex items-center justify-center gap-2"
            >
              🎗️ Chiến dịch quyên góp
            </button>
          </div>

          <!-- TAB 1: THỰC PHẨM CỘNG ĐỒNG -->
          <div v-if="activeTab === 'food'" class="space-y-6">
            <div class="flex justify-between items-center">
              <div class="space-y-1">
                <h2 class="text-xl font-bold text-gray-900 tracking-tight">Thực phẩm cộng đồng chia sẻ lẻ</h2>
                <p class="text-xs text-gray-500">Tin đăng tặng thực phẩm từ cá nhân/hộ kinh doanh nhỏ xung quanh bạn trong vòng {{ selectedRadius }} km</p>
              </div>
              <!-- Nút Toggle bật/tắt bài đăng của tôi (Chỉ hiện khi đã đăng nhập) -->
              <div v-if="$page.props.auth.user" class="flex items-center space-x-2 bg-gray-50 border border-gray-100 px-3 py-1.5 rounded-full shadow-sm">
                <span class="text-xs text-gray-600 font-medium select-none">Bài viết của tôi</span>
                <button 
                  type="button"
                  @click="showMyPosts = !showMyPosts"
                  :class="showMyPosts ? 'bg-emerald-600' : 'bg-gray-300'"
                  class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none">
                  <span 
                    :class="showMyPosts ? 'translate-x-4' : 'translate-x-0'"
                    class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"/>
                </button>
              </div>
            </div>
            
            <!-- Grid thẻ thực phẩm -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
              <div 
                v-for="post in activeNearbyPosts" 
                :key="post.id" 
                class="bg-white border border-gray-100/80 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col group"
              >
                <!-- Header: Người đăng & Khoảng cách (Bố cục chuyên nghiệp như mạng xã hội) -->
                <div class="p-3.5 flex items-center justify-between border-b border-gray-50">
                  <div class="flex items-center space-x-2.5 min-w-0">
                    <!-- Avatar tròn -->
                    <img 
                      v-if="post.user && post.user.avatar" 
                      :src="post.user.avatar" 
                      class="w-9 h-9 rounded-full object-cover border border-emerald-100 shadow-sm"
                      alt="Avatar"
                    />
                    <!-- Avatar Gradient bắt mắt làm Fallback -->
                    <div v-else class="w-9 h-9 rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 text-white flex items-center justify-center text-xs font-bold uppercase shadow-sm">
                      {{ post.user ? post.user.name.charAt(0) : 'U' }}
                    </div>
                    <div class="min-w-0">
                      <p class="text-[13px] font-bold text-gray-800 truncate leading-tight pb-0.5">
                        {{ post.user ? post.user.name : 'Người dùng' }}
                      </p>
                      <p class="text-[10px] text-gray-400 mt-0.5 leading-tight">
                        {{ getCreatedTimeLabel(post.created_at) }}
                      </p>
                    </div>
                  </div>
                  <!-- Khoảng cách nhỏ gọn -->
                  <div class="px-2 py-0.5 bg-emerald-50 border border-emerald-100 text-emerald-700 rounded-full text-[9px] font-bold flex items-center gap-1 shrink-0">
                    📍 {{ parseFloat(post.distance).toFixed(1) }} km
                  </div>
                </div>

                <!-- Ảnh thực phẩm -->
                <div class="relative bg-gray-100 h-44 overflow-hidden flex items-center justify-center text-emerald-600 text-sm font-medium">
                  <img 
                    :src="post.image_url ? (post.image_url.startsWith('/storage') ? post.image_url : '/storage/' + post.image_url) : 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=600&q=80'" 
                    class="w-full h-full object-cover group-hover:scale-105 transition-all duration-500"
                    alt="Hình ảnh thực phẩm"
                  />
                </div>
                
                <!-- Nội dung thông tin -->
                <div class="p-4 flex-1 flex flex-col justify-between space-y-4">
                  <div class="space-y-1.5">
                    <h3 class="font-bold text-sm text-gray-900 group-hover:text-emerald-600 transition-colors duration-150 line-clamp-1">
                      {{ post.title }}
                    </h3>
                    <p class="text-[11px] text-gray-500 leading-relaxed line-clamp-2 min-h-[2rem]">
                      {{ post.description }}
                    </p>
                  </div>

                  <div class="space-y-3">
                    <!-- Thông số: Số lượng & Hạn dùng chia làm 2 ô đối xứng -->
                    <div class="grid grid-cols-2 gap-2">
                      <div class="bg-amber-50/60 border border-amber-100/60 p-2 rounded-xl text-center flex flex-col justify-center">
                        <span class="text-[9px] text-amber-700 font-semibold uppercase tracking-wider">Còn lại</span>
                        <span class="font-bold text-[11px] text-amber-800 mt-0.5">{{ post.remain_quantity }}/{{ post.original_quantity }} {{ post.unit }}</span>
                      </div>
                      
                      <div class="bg-red-50/60 border border-red-100/60 p-2 rounded-xl text-center flex flex-col justify-center">
                        <span class="text-[9px] text-red-700 font-semibold uppercase tracking-wider">Hạn dùng</span>
                        <span class="font-bold text-[11px] text-red-800 mt-0.5">{{ getExpiryLabel(post.expires_at) }}</span>
                      </div>
                    </div>

                    <!-- Button gửi yêu cầu nhận (Gradient Premium) -->
                    <div v-if="$page.props.auth.user && post.user_id === $page.props.auth.user.id" class="w-full bg-gray-50 border border-gray-200/80 text-gray-400 font-bold text-xs py-2.5 px-4 rounded-xl text-center select-none">
                      👤 Bài đăng của bạn
                    </div>
                    <div v-else class="flex gap-2 w-full">
                      <button 
                        @click="$page.props.auth.user ? openClaimModal(post) : router.visit(route('login'))" 
                        class="flex-1 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-bold text-xs py-2.5 px-4 rounded-xl shadow-md shadow-emerald-100 hover:shadow-emerald-200 hover:-translate-y-[1px] active:translate-y-0 transition-all duration-150 cursor-pointer"
                      >
                        Gửi yêu cầu nhận
                      </button>
                      <button 
                        @click="openReportModal(post.user, post)" 
                        class="bg-white hover:bg-red-50 text-red-500 border border-red-200 hover:border-red-300 font-bold px-3 rounded-xl transition cursor-pointer flex items-center justify-center shrink-0 shadow-sm text-sm"
                        title="Báo cáo bài viết này"
                      >
                        🚩
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Trống dữ liệu -->
              <div v-if="activeNearbyPosts.length === 0" class="col-span-full bg-white border border-dashed border-gray-200 rounded-3xl p-10 text-center text-gray-400 text-sm">
                Không tìm thấy thực phẩm nào trong bán kính {{ selectedRadius }} km xung quanh vị trí của bạn.
              </div>
            </div>
          </div>

          <!-- TAB 2: CHIẾN DỊCH QUYÊN GÓP -->
          <div v-if="activeTab === 'campaign'" class="space-y-6">
            <div class="flex justify-between items-center">
              <div class="space-y-1">
                <h2 class="text-xl font-bold text-gray-900 tracking-tight">Chiến dịch quyên góp cứu trợ</h2>
                <p class="text-xs text-gray-500">Các chương trình quyên góp thực phẩm quy mô lớn từ các mái ấm, tổ chức từ thiện</p>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
              <div v-if="!filteredCampaigns || filteredCampaigns.length === 0" class="col-span-full bg-white border border-dashed border-gray-200 rounded-3xl p-10 text-center text-gray-400 text-sm">
                Hiện tại chưa có chiến dịch quyên góp nào đang diễn ra phù hợp với tìm kiếm của bạn.
              </div>
              <div 
                v-for="campaign in filteredCampaigns" 
                :key="campaign.id" 
                class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4 flex flex-col justify-between group hover:shadow-xl hover:-translate-y-1 transition-all duration-300"
              >
                <div class="space-y-4">
                  <!-- Header: Tổ chức & Trạng thái -->
                  <div class="flex justify-between items-start gap-2 border-b border-gray-50 pb-3">
                    <div class="flex items-center gap-2">
                      <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-xs uppercase shadow-sm">
                        {{ campaign.user?.name ? campaign.user.name.charAt(0) : 'T' }}
                      </div>
                      <div>
                        <p class="text-[10px] text-gray-400 font-medium leading-tight mb-0.5">Tổ chức từ thiện</p>
                        <p class="text-[12px] font-bold text-blue-700 leading-tight pb-0.5 truncate max-w-[120px]" :title="campaign.user?.name">{{ campaign.user?.name || 'Chưa rõ' }}</p>
                      </div>
                    </div>
                    <span class="text-[9px] text-emerald-700 font-bold bg-emerald-50 border border-emerald-100 px-2 py-1 rounded-lg shrink-0 flex items-center gap-1 shadow-sm">
                      <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                      Đang diễn ra
                    </span>
                  </div>
                  
                  <!-- Title & Description -->
                  <div class="space-y-2">
                    <h3 class="font-bold text-[15px] text-gray-900 group-hover:text-emerald-600 transition-colors line-clamp-2 leading-snug" :title="campaign.title">{{ campaign.title }}</h3>
                    <p class="text-[11px] text-gray-500 line-clamp-2 leading-relaxed">{{ campaign.description }}</p>
                  </div>

                  <!-- Metadata Box (Date & Location) -->
                  <div class="bg-gray-50/80 p-3 rounded-xl border border-gray-100/80 space-y-2 text-[11px] text-gray-600">
                     <div class="flex flex-col gap-1.5 border-b border-gray-200/50 pb-2 mb-2">
                         <div class="flex items-start gap-2">
                           <span class="shrink-0 text-emerald-500">📦</span>
                           <span class="font-medium">Mốc 1 (Đồ khô): <span class="text-gray-900 font-bold">{{ new Date(campaign.web_deadline).toLocaleString('vi-VN', {hour: '2-digit', minute:'2-digit', day:'2-digit', month:'2-digit', year:'numeric'}) }}</span></span>
                         </div>
                         <div class="flex items-start gap-2">
                           <span class="shrink-0 text-amber-500">🥬</span>
                           <span class="font-medium">Mốc 2 (Sự kiện): <span class="text-gray-900 font-bold">{{ new Date(campaign.event_date).toLocaleString('vi-VN', {hour: '2-digit', minute:'2-digit', day:'2-digit', month:'2-digit', year:'numeric'}) }}</span></span>
                         </div>
                     </div>
                     <div class="flex items-start gap-2">
                       <span class="shrink-0 text-gray-400">📍</span>
                       <span class="font-medium line-clamp-2">Tập kết tại: <span class="text-gray-900">{{ campaign.location_details || 'Đang cập nhật' }}</span></span>
                     </div>
                  </div>
                </div>
                
                <!-- Items Progress -->
                <div class="space-y-3 pt-3 border-t border-gray-50 mt-auto">
                  <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Tiến độ quyên góp</p>
                  <div class="space-y-2.5 max-h-[120px] overflow-y-auto pr-1 custom-scrollbar">
                    <div class="space-y-1.5" v-for="item in (campaign.items || [])" :key="item.id">
                      <div class="flex justify-between items-start text-[11px] font-semibold">
                        <span class="text-gray-700 truncate mr-2" :title="item.item_name">{{ item.item_name }}</span>
                        <div class="flex flex-col items-end shrink-0">
                            <span class="text-emerald-600 font-bold">
                              {{ item.current_quantity }} / {{ item.target_quantity }} {{ item.unit || '' }} ({{ Math.round((item.current_quantity / Math.max(item.target_quantity, 1)) * 100) }}%)
                            </span>
                            <span v-if="item.pending_quantity > 0" class="text-amber-500 text-[10px] font-bold mt-0.5">
                              + {{ item.pending_quantity }} Đang chuyển tới
                            </span>
                        </div>
                      </div>
                      <div class="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden shadow-inner">
                        <div class="bg-gradient-to-r from-emerald-400 to-teal-500 h-full rounded-full transition-all duration-700" :style="{ width: Math.round((item.current_quantity / Math.max(item.target_quantity, 1)) * 100) + '%' }"></div>
                      </div>
                    </div>
                  </div>
                  <button v-if="campaign.status === 'active' && new Date(campaign.event_date) >= new Date(new Date().setHours(0,0,0,0))" @click="openDonationModal(campaign)" class="w-full bg-gray-900 hover:bg-gray-800 text-white font-bold text-xs py-3 rounded-xl transition shadow-md hover:shadow-lg mt-3 flex items-center justify-center gap-2">
                    <span>❤️</span> Đóng góp ngay
                  </button>
                  <button v-else disabled class="w-full bg-gray-100 text-gray-500 border border-gray-200 cursor-not-allowed font-bold text-xs py-3 rounded-xl transition mt-3 flex items-center justify-center gap-2">
                    <span>🔒</span> {{ campaign.status === 'closed' ? 'Đã chốt đi phát' : (campaign.status === 'completed' ? 'Đã hoàn thành' : 'Đã đóng cổng quyên góp') }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- CỘT PHẢI (1/3): Bản đồ Leaflet tương tác -->
        <div class="space-y-6 lg:sticky lg:top-24 lg:self-start">
          <!-- Bản đồ định vị lân cận -->
          <div class="bg-white border border-gray-100 rounded-3xl p-5 shadow-sm space-y-3 flex flex-col justify-between h-auto lg:h-[350px]">
            <div class="flex justify-between items-center">
              <h3 class="font-bold text-gray-950 text-sm">Bản đồ thực phẩm lân cận</h3>
              <span class="text-[10px] text-gray-400">Định vị thời gian thực</span>
            </div>
            <!-- Box gắn Map (z-10 để không bị đè menu) -->
            <div id="map" class="w-full flex-1 min-h-[250px] rounded-2xl border border-gray-100 z-10"></div>
          </div>

          <!-- Yêu cầu & Giao nhận (Gồm cả bài cho & bài nhận đang hoạt động) -->
          <div v-if="$page.props.auth.user" class="bg-white border border-gray-100 rounded-3xl p-5 shadow-sm space-y-4">
            <div class="flex justify-between items-center border-b border-gray-50 pb-3">
              <h3 class="font-bold text-gray-950 text-sm">Yêu cầu & Giao nhận</h3>
              <span class="text-[10px] bg-emerald-50 text-emerald-700 font-bold px-2 py-0.5 rounded border border-emerald-100">
                {{ activeClaims.length + approvedReceivedClaims.length + (groupedMyDonations ? groupedMyDonations.length : 0) }} giao dịch
              </span>
            </div>
            
            <div v-if="activeClaims.length === 0 && approvedReceivedClaims.length === 0 && (!groupedMyDonations || groupedMyDonations.length === 0)" class="text-center py-6 text-gray-400 text-xs">
              Không có giao dịch nào đang xử lý.
            </div>
            
            <div v-else class="space-y-4 max-h-[500px] overflow-y-auto pr-1">
              <!-- PHẦN 1: BÀI NHẬN (Yêu cầu bạn gửi đi xin người khác) -->
              <div v-if="activeClaims.length > 0" class="space-y-2">
                <div class="text-[10px] font-bold text-emerald-700 tracking-wider uppercase mb-1 flex items-center gap-1">
                  📥 Yêu cầu xin nhận ({{ activeClaims.length }})
                </div>
                <div 
                  v-for="claim in activeClaims" 
                  :key="'outgoing-' + claim.id" 
                  class="p-3 bg-emerald-50/20 rounded-2xl border border-emerald-100/30 space-y-2 text-xs"
                >
                  <div class="flex justify-between items-start gap-2">
                    <div class="font-bold text-gray-900 line-clamp-1 flex-1 text-left">
                      {{ claim.food_post?.title || 'Thực phẩm' }}
                    </div>
                    <span class="bg-emerald-50 text-emerald-700 border-emerald-100 text-[9px] px-1.5 py-0.5 rounded border font-bold shrink-0">
                      {{ claim.status === 'pending' ? 'Chờ duyệt' : 'Đã duyệt' }}
                    </span>
                  </div>
                  
                  <div class="flex justify-between text-[11px] text-gray-500">
                    <span>Số lượng yêu cầu:</span>
                    <span class="font-semibold text-gray-800">{{ claim.quantity }} {{ claim.food_post?.unit }}</span>
                  </div>
                  
                  <div class="flex justify-between text-[11px] text-gray-500">
                    <span>Chủ bài đăng:</span>
                    <span class="font-semibold text-gray-800">{{ claim.food_post?.user?.name || 'Đang cập nhật' }}</span>
                  </div>

                  <div v-if="claim.message" class="bg-gray-100 p-2 rounded-lg text-gray-700 text-[11px] italic mt-1 border-l-2 border-emerald-400">
                    💬 Lời nhắn: {{ claim.message }}
                  </div>

                  <div class="mt-2 pt-2 border-t border-gray-100/50 text-[11px] space-y-1 text-left">
                    <div v-if="claim.shipping_method === 'delivery_service'" class="bg-orange-50/50 p-2.5 rounded-2xl text-gray-600 space-y-1 relative mb-2 border border-orange-100/50">
                        <p>🚚 <b>Dịch vụ giao hàng:</b></p>
                        <p>Tên tài xế: <span class="font-bold">{{ claim.delivery_service_company || 'Chưa cập nhật' }}</span></p>
                        <p>Biển số xe: <span class="font-bold">{{ claim.driver_license_plate || 'Chưa cập nhật' }}</span></p>
                        <div class="pt-1.5 border-t border-orange-100/50 mt-1.5">
                            <button @click="openClaimShipperModal(claim)" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold text-[10px] py-1.5 px-3 rounded-lg transition text-center cursor-pointer shadow-sm">
                                Cập nhật thông tin Shipper
                            </button>
                        </div>
                    </div>

                    <!-- Trạng thái Chờ duyệt -->
                    <div v-if="claim.status === 'pending'" class="text-amber-600 bg-amber-50/50 p-1.5 rounded-lg border border-amber-100/50 flex items-start gap-1">
                      <span class="shrink-0 mt-0.5">⚠️</span>
                      <span>Thông tin liên hệ sẽ hiển thị sau khi chủ nhà phê duyệt.</span>
                    </div>
                    <!-- Trạng thái Đã duyệt -->
                    <div v-else-if="claim.status === 'approved'" class="bg-emerald-50/50 p-2.5 rounded-2xl text-gray-600 space-y-1 relative">
                      <!-- Badge Countdown -->
                      <div v-if="countdowns[claim.id]" class="absolute top-2 right-2 bg-red-100/90 text-red-600 border border-red-200 font-bold px-2 py-1 rounded-lg text-[10px] animate-pulse shadow-sm flex items-center gap-1">
                          <span>⏳</span><span>{{ countdowns[claim.id] }}</span>
                      </div>
                      
                      <p>👤 <b>Người cho:</b> <span class="font-semibold text-gray-800">{{ claim.food_post?.user?.name }}</span></p>
                      <p>📞 <b>SĐT người cho:</b> <a :href="'tel:' + claim.food_post?.user?.phone" class="text-emerald-600 font-bold hover:underline">{{ claim.food_post?.user?.phone || 'Chưa cập nhật' }}</a></p>
                      <p>📍 <b>Địa chỉ lấy đồ:</b> <span class="font-semibold text-gray-800">{{ claim.food_post?.user?.address || 'Chưa cập nhật' }}</span></p>
                      <div class="pt-1.5 border-t border-emerald-100/50 mt-1.5">
                        <button 
                          @click="handleGetDirections(claim)"
                          class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] py-1.5 px-3 rounded-lg transition text-center cursor-pointer flex items-center justify-center gap-1 shadow-sm"
                        >
                          🗺️ Chỉ đường đến vị trí lấy đồ
                        </button>
                      </div>
                    </div>
                  </div>

                  <!-- Nút Báo cáo và Hủy giao dịch (Cancel Claim) - Dành cho người nhận -->
                  <div class="flex justify-end gap-2 pt-1">
                    <button 
                      v-if="claim.status === 'approved'"
                      @click="openReportModal(claim.food_post?.user, claim.food_post, claim)"
                      class="bg-white hover:bg-red-50 text-red-600 font-bold text-[10px] py-1.5 px-3 rounded-lg border border-red-200 hover:border-red-300 transition text-center cursor-pointer shadow-sm flex items-center gap-1"
                      title="Báo cáo người này"
                    >
                      🚩 Báo cáo
                    </button>
                    <button 
                      v-if="!claim.is_disputed"
                      @click="openCancelModal(claim.id)" 
                      class="bg-white hover:bg-red-50 text-red-600 hover:text-red-700 font-bold text-[10px] px-3 py-1.5 rounded-lg border border-gray-200 hover:border-red-100 transition cursor-pointer shadow-sm"
                    >
                      Hủy yêu cầu
                    </button>
                  </div>
                </div>
              </div>

              <!-- PHẦN 2: BÀI CHO (Yêu cầu nhận từ người khác mà bạn đã duyệt) -->
              <div v-if="approvedReceivedClaims.length > 0" class="space-y-2 pt-2 border-t border-gray-100/50">
                <div class="text-[10px] font-bold text-blue-700 tracking-wider uppercase mb-1 flex items-center gap-1">
                  📤 Bài đăng cho đi (Đã duyệt) ({{ approvedReceivedClaims.length }})
                </div>
                <div 
                  v-for="claim in approvedReceivedClaims" 
                  :key="'incoming-' + claim.id" 
                  class="p-3 bg-blue-50/20 rounded-2xl border border-blue-100/30 space-y-2 text-xs"
                >
                  <div class="flex justify-between items-start gap-2 relative">
                    <div class="font-bold text-gray-900 line-clamp-1 flex-1 text-left pr-16">
                      {{ claim.food_post?.title || 'Thực phẩm' }}
                    </div>
                    
                    <!-- Countdown cho người duyệt (Người cho) -->
                    <div v-if="countdowns[claim.id]" class="absolute top-0 right-0 bg-red-100/90 text-red-600 border border-red-200 font-bold px-2 py-1 rounded-lg text-[10px] animate-pulse shadow-sm flex items-center gap-1">
                        <span>⏳</span><span>{{ countdowns[claim.id] }}</span>
                    </div>
                  </div>

                  <p class="text-gray-700 leading-normal text-left text-[11px] mt-2 flex items-center gap-1 flex-wrap">
                    👤 Người xin: <span class="font-bold text-gray-900">{{ claim.user?.name }}</span>
                    <span v-if="claim.user" :class="claim.user.trust_score < 70 ? 'text-red-600 bg-red-50 border-red-200' : 'text-amber-600 bg-amber-50 border-amber-200'" class="text-[9px] font-bold px-1.5 py-0.5 rounded-full border flex items-center gap-0.5" title="Điểm uy tín">
                      ⭐ {{ claim.user.trust_score }}
                    </span>
                    <span>xin</span> <span class="font-bold text-emerald-600">{{ claim.quantity }} {{ claim.food_post?.unit }}</span>
                  </p>
                  
                  <div v-if="claim.message" class="bg-blue-100/50 p-2 rounded-lg text-gray-700 text-[11px] italic mt-1 border-l-2 border-blue-400">
                    💬 Ghi chú: {{ claim.message }}
                  </div>
                  
                  <div class="bg-gray-50 p-2 rounded-xl text-gray-600 text-[11px] space-y-0.5 text-left mt-1.5">
                    <p>📞 <b>SĐT người xin:</b> <a :href="'tel:' + claim.user?.phone" class="text-emerald-600 font-bold hover:underline">{{ claim.user?.phone || 'Chưa cập nhật' }}</a></p>
                    
                    <!-- Nhãn Phương thức nhận hàng trực quan cho Người Cho đối soát -->
                    <div class="pt-1 mt-1 border-t border-gray-100">
                      <p>🚚 <b>Phương thức lấy đồ:</b></p>
                      <div class="mt-1 flex flex-col gap-1 text-[10px]">
                        <span v-if="claim.shipping_method === 'self_pickup'" class="inline-block px-2 py-0.5 bg-blue-100 text-blue-700 rounded-md font-semibold w-fit">Tự đến lấy</span>
                        <div v-else-if="claim.shipping_method === 'relative_pickup'" class="bg-indigo-50 text-indigo-700 p-1.5 rounded-md border border-indigo-100">
                          <span class="font-semibold block mb-0.5">Nhờ người thân lấy hộ</span>
                          Tên: <b>{{ claim.pickup_contact_name }}</b> - SĐT: <b>{{ claim.pickup_contact_phone }}</b>
                        </div>
                        <div v-else-if="claim.shipping_method === 'delivery_service'" class="bg-orange-50 text-orange-700 p-1.5 rounded-md border border-orange-100">
                          <span class="font-semibold block mb-0.5">Dịch vụ Giao hàng</span>
                          Hãng: <b>{{ claim.delivery_service_company }}</b> - Biển số: <b>{{ claim.driver_license_plate }}</b>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Các nút thao tác dành cho người cho để kết thúc giao dịch -->
                  <div v-if="!claim.is_disputed" class="flex items-center gap-2 pt-1">

                    <button 
                      @click="handleCompleteClaim(claim.id)" 
                      class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] py-1.5 rounded-lg transition text-center cursor-pointer shadow-sm shadow-emerald-100"
                    >
                      Đã lấy đồ
                    </button>
                    <button 
                      @click="openGiverCancelModal(claim.id, 'cancelled')" 
                      class="flex-1 bg-white hover:bg-red-50 text-red-600 hover:text-red-700 font-semibold text-[10px] py-1.5 rounded-lg border border-gray-200 hover:border-red-100 transition text-center cursor-pointer"
                    >
                      Hủy giao dịch
                    </button>
                  </div>
                </div>
              </div>

              <!-- PHẦN 3: BÀI QUYÊN GÓP (campaign_donations) -->
              <div v-if="groupedMyDonations && groupedMyDonations.length > 0" class="space-y-2 pt-2 border-t border-gray-100/50">
                <div class="text-[10px] font-bold text-orange-700 tracking-wider uppercase mb-1 flex items-center gap-1">
                  📦 Đơn quyên góp của bạn ({{ groupedMyDonations.length }})
                </div>
                <div 
                  v-for="group in groupedMyDonations" 
                  :key="'group-' + group.donation_code" 
                  class="p-3 bg-orange-50/20 rounded-2xl border border-orange-100/30 space-y-2 text-xs"
                >
                  <div class="flex justify-between items-start gap-2">
                    <div class="font-bold text-gray-900 line-clamp-1 flex-1 text-left">
                      {{ group.campaign?.title || 'Chiến dịch' }}
                    </div>
                    <span :class="group.status === 'pending' ? 'bg-amber-50 text-amber-700 border-amber-100' : 'bg-emerald-50 text-emerald-700 border-emerald-100'" class="text-[9px] px-1.5 py-0.5 rounded border font-bold shrink-0">
                      {{ group.status === 'pending' ? 'Đang chuyển' : 'Đã nhận' }}
                    </span>
                  </div>
                  
                  <div class="flex justify-between items-center text-[11px]">
                    <span class="text-gray-500">Mã đơn:</span>
                    <span class="font-extrabold text-gray-900 bg-orange-100 px-1.5 py-0.5 rounded-md">{{ group.donation_code }}</span>
                  </div>

                  <div v-if="group.status === 'pending' && group.expires_at" class="bg-red-50 p-2 rounded-xl border border-red-100 text-[11px] text-red-700 font-medium flex items-start gap-1.5 mt-1">
                    <span class="shrink-0 mt-0.5">⏱️</span>
                    <span>Bạn cần giao hàng đến điểm tập kết trước <span class="font-bold">{{ new Date(group.expires_at).toLocaleString('vi-VN', {hour: '2-digit', minute:'2-digit', day:'2-digit', month:'2-digit', year:'numeric'}) }}</span>. Quá hạn đơn sẽ bị hủy và trừ điểm uy tín.</span>
                  </div>

                  <div class="bg-white p-2 rounded-xl border border-gray-100 text-[11px] text-gray-600 mt-1">
                    <p class="font-semibold text-gray-800 mb-1">Sản phẩm quyên góp:</p>
                    <ul class="space-y-1">
                      <li v-for="item in group.items" :key="item.id" class="flex justify-between">
                        <span>- {{ item.item_name }}</span>
                        <span class="font-bold text-emerald-600">+{{ item.quantity }} {{ item.unit }}</span>
                      </li>
                    </ul>
                  </div>

                  <div class="mt-2 pt-2 border-t border-gray-100/50 text-[11px] space-y-1 text-left">
                    <div v-if="group.shipping_method === 'self_delivery'" class="text-blue-600 bg-blue-50/50 p-1.5 rounded-lg border border-blue-100/50 flex items-start gap-1">
                      <span class="shrink-0 mt-0.5">🚶</span>
                      <span>Bạn tự mang tới điểm tập kết.</span>
                    </div>
                    <div v-else-if="group.shipping_method === 'delivery_service'" class="bg-orange-50/50 p-2.5 rounded-2xl text-gray-600 space-y-1 relative">
                        <p>🚚 <b>Dịch vụ giao hàng:</b></p>
                        <p>Tên tài xế: <span class="font-bold">{{ group.shipper_name || 'Chưa cập nhật' }}</span></p>
                        <p>Biển số xe: <span class="font-bold">{{ group.shipper_license_plate || 'Chưa cập nhật' }}</span></p>
                        <div v-if="group.status === 'pending'" class="pt-1.5 border-t border-orange-100/50 mt-1.5">
                            <button @click="openShipperModal(group)" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold text-[10px] py-1.5 px-3 rounded-lg transition text-center cursor-pointer shadow-sm">
                                Cập nhật thông tin Shipper
                            </button>
                        </div>
                    </div>
                  </div>
                  
                  <div class="mt-2 pt-2 border-t border-gray-100/50 flex justify-between items-center">
                      <button @click="openCampaignDetailModal(group.campaign)" class="text-blue-600 hover:text-blue-700 font-semibold underline text-[10px] transition cursor-pointer">
                          Xem chi tiết chiến dịch
                      </button>
                      <button v-if="group.status === 'pending'" @click="cancelDonation(group.donation_code)" class="text-red-500 hover:text-red-700 font-semibold underline text-[10px] transition cursor-pointer">
                          Hủy đóng góp
                      </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </main>

    <!-- MODAL CHI TIẾT CHIẾN DỊCH -->
    <div v-if="showCampaignDetailModal && selectedCampaignDetail" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex justify-center items-center p-4 z-[60] animate-fade-in">
      <div class="bg-white rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl relative text-left flex flex-col max-h-[90vh]">
        <div class="p-6 border-b border-gray-100 flex justify-between items-start shrink-0">
          <div>
            <h3 class="text-lg font-extrabold text-gray-900 line-clamp-2">{{ selectedCampaignDetail.title }}</h3>
            <div class="flex items-center gap-2 mt-2 flex-wrap">
              <span class="text-[9px] font-bold px-2 py-1 rounded-lg shrink-0 flex items-center gap-1 shadow-sm" :class="selectedCampaignDetail.status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-gray-100 text-gray-600 border border-gray-200'">
                <span v-if="selectedCampaignDetail.status === 'active'" class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                {{ selectedCampaignDetail.status === 'active' ? 'Đang diễn ra' : (selectedCampaignDetail.status === 'closed' ? 'Đã chốt đi phát' : 'Đã hoàn thành') }}
              </span>
              <div class="flex items-center gap-1.5 ml-2">
                <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-[10px] uppercase shadow-sm shrink-0">
                  {{ selectedCampaignDetail.user?.name ? selectedCampaignDetail.user.name.charAt(0) : 'T' }}
                </span>
                <p class="text-[11px] text-gray-600 font-semibold truncate max-w-[150px]">{{ selectedCampaignDetail.user?.name || 'Chưa rõ' }}</p>
              </div>
            </div>
          </div>
          <button @click="showCampaignDetailModal = false" class="text-gray-400 hover:text-gray-600 transition p-1 bg-gray-50 hover:bg-gray-100 rounded-full shrink-0 ml-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>
        
        <div class="p-6 overflow-y-auto space-y-5 custom-scrollbar">
          <div>
            <p class="text-sm text-gray-600 whitespace-pre-wrap leading-relaxed">{{ selectedCampaignDetail.description }}</p>
          </div>
          
          <div class="space-y-4">
              <div class="bg-gray-50/80 p-4 rounded-2xl border border-gray-100/80 space-y-3 text-xs text-gray-700">
                 <div class="flex items-start gap-3">
                   <span class="shrink-0 text-gray-400 text-base">📞</span>
                   <div class="flex flex-col">
                     <span class="font-medium text-gray-500">SĐT Ban tổ chức</span>
                     <span class="text-gray-900 font-bold mt-0.5"><a :href="'tel:' + selectedCampaignDetail.user?.phone" class="text-blue-600 hover:underline">{{ selectedCampaignDetail.user?.phone || 'Chưa cập nhật' }}</a></span>
                   </div>
                 </div>
                 
                 <div class="flex items-start gap-3">
                   <span class="shrink-0 text-gray-400 text-base">📦</span>
                   <div class="flex flex-col">
                     <span class="font-medium text-gray-500">Mốc 1 (Đóng đồ khô)</span>
                     <span class="text-gray-900 font-bold mt-0.5">{{ new Date(selectedCampaignDetail.web_deadline).toLocaleString('vi-VN', {hour: '2-digit', minute:'2-digit', day:'2-digit', month:'2-digit', year:'numeric'}) }}</span>
                   </div>
                 </div>
                 
                 <div v-if="selectedCampaignDetail.event_date" class="flex items-start gap-3">
                   <span class="shrink-0 text-gray-400 text-base">🚀</span>
                   <div class="flex flex-col">
                     <span class="font-medium text-gray-500">Mốc 2 (Ngày đi phát)</span>
                     <span class="text-emerald-700 font-bold mt-0.5">{{ new Date(selectedCampaignDetail.event_date).toLocaleString('vi-VN', {hour: '2-digit', minute:'2-digit', day:'2-digit', month:'2-digit', year:'numeric'}) }}</span>
                   </div>
                 </div>

                 <div class="flex items-start gap-3">
                   <span class="shrink-0 text-gray-400 text-base">📍</span>
                   <div class="flex flex-col">
                     <span class="font-medium text-gray-500">Địa điểm tập kết</span>
                     <span class="text-gray-900 font-bold mt-0.5 leading-snug">{{ selectedCampaignDetail.location_details || 'Đang cập nhật' }}</span>
                   </div>
                 </div>
              </div>

              <!-- Items Progress -->
              <div class="space-y-3 pt-4 border-t border-gray-100">
                <p class="text-[11px] font-bold text-gray-900 uppercase tracking-wider">Tiến độ gom vật phẩm</p>
                <div class="space-y-3">
                  <div class="space-y-1.5" v-for="item in (selectedCampaignDetail.items || [])" :key="item.id">
                    <div class="flex justify-between items-start text-[11px] font-semibold">
                      <span class="text-gray-700 truncate mr-2">{{ item.item_name }}</span>
                      <div class="flex flex-col items-end shrink-0">
                          <span class="text-emerald-600 font-bold">
                            {{ item.current_quantity }} / {{ item.target_quantity }} {{ item.unit || '' }} ({{ Math.round((item.current_quantity / Math.max(item.target_quantity, 1)) * 100) }}%)
                          </span>
                          <span v-if="item.pending_quantity > 0" class="text-amber-500 text-[10px] font-bold mt-0.5">
                            + {{ item.pending_quantity }} Đang chuyển tới
                          </span>
                      </div>
                    </div>
                    <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden shadow-inner">
                      <div class="bg-gradient-to-r from-emerald-400 to-teal-500 h-full rounded-full transition-all duration-700" :style="{ width: Math.min(100, Math.round((item.current_quantity / Math.max(item.target_quantity, 1)) * 100)) + '%' }"></div>
                    </div>
                  </div>
                </div>
              </div>
          </div>
        </div>
        
        <div class="p-4 border-t border-gray-100 shrink-0 bg-gray-50">
           <button @click="showCampaignDetailModal = false" class="w-full px-4 py-2.5 text-sm font-bold text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 shadow-sm transition">
               Đóng
           </button>
        </div>
      </div>
    </div>

    <!-- MODAL CẬP NHẬT SHIPPER (CHO DONATIONS) -->
    <div v-if="showShipperModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex justify-center items-center p-4 z-[60] animate-fade-in">
      <div class="bg-white rounded-3xl w-full max-w-sm overflow-hidden shadow-2xl relative text-left">
        <div class="p-6">
          <div class="flex justify-between items-start mb-4">
            <h3 class="text-lg font-extrabold text-gray-900">Cập nhật Shipper</h3>
            <button @click="showShipperModal = false" class="text-gray-400 hover:text-gray-600 transition p-1">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>
          <p class="text-xs text-gray-500 mb-5">Vui lòng điền thông tin tài xế để Ban tổ chức đối soát khi nhận hàng.</p>
          
          <form @submit.prevent="submitShipperForm" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Tên tài xế <span class="text-red-500">*</span></label>
              <input v-model="shipperForm.shipper_name" type="text" class="w-full rounded-xl border-gray-200 focus:border-orange-600 focus:ring-orange-600 text-sm" placeholder="Nhập tên tài xế Grab, XanhSM..." required>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Biển số xe <span class="text-red-500">*</span></label>
              <input v-model="shipperForm.shipper_license_plate" type="text" class="w-full rounded-xl border-gray-200 focus:border-orange-600 focus:ring-orange-600 text-sm" placeholder="Ví dụ: 59A-123.45" required>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" @click="showShipperModal = false" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200">
                    Hủy
                </button>
                <button type="submit" :disabled="shipperForm.processing" class="px-5 py-2 text-sm font-bold text-white bg-orange-600 rounded-xl hover:bg-orange-700 disabled:opacity-50">
                    Cập nhật
                </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- MODAL GỬI YÊU CẦU NHẬN THỰC PHẨM LẺ -->
    <div 
      v-if="selectedClaimPost" 
      class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-in fade-in duration-200"
    >
      <div 
        class="bg-white rounded-3xl w-full max-w-md overflow-hidden shadow-2xl border border-gray-100/50 animate-in zoom-in-95 duration-200"
      >
        <div class="bg-gradient-to-r from-emerald-600 to-teal-700 p-6 text-white relative">
          <h3 class="font-extrabold text-lg">Đăng ký nhận thực phẩm</h3>
          <p class="text-emerald-100/90 text-xs mt-1">Vui lòng nhập số lượng bạn mong muốn nhận</p>

          <button 
            @click="closeClaimModal" 
            class="absolute top-5 right-5 text-emerald-100 hover:text-white transition bg-white/10 hover:bg-white/20 w-8 h-8 rounded-full flex items-center justify-center cursor-pointer"
          >
            ✕
          </button>
        </div>
        
        <div class="p-6 space-y-5">
          <div class="bg-emerald-50/50 border border-emerald-100/50 rounded-2xl p-4 flex items-center gap-3">
            <img 
              :src="selectedClaimPost.image_url ? (selectedClaimPost.image_url.startsWith('/storage') ? selectedClaimPost.image_url : '/storage/' + selectedClaimPost.image_url) : 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=600&q=80'" 
              class="w-12 h-12 rounded-xl object-cover border border-emerald-100"
              alt="Món ăn"
            />
            <div class="text-left">
              <h4 class="font-bold text-sm text-gray-900">{{ selectedClaimPost.title }}</h4>
              <p class="text-[11px] text-gray-500 mt-0.5">Còn lại: <span class="text-emerald-600 font-bold">{{ selectedClaimPost.remain_quantity }} {{ selectedClaimPost.unit }}</span></p>
            </div>
          </div>

          <div class="space-y-2 text-left">
            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">Số lượng muốn nhận:</label>
            <div class="flex items-center gap-3">
              <input 
                type="number" 
                v-model.number="claimForm.quantity" 
                min="1" 
                :max="selectedClaimPost.remain_quantity"
                class="flex-1 bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-800 focus:outline-none focus:border-emerald-500 transition"
              />
              <span class="text-xs font-bold text-gray-500 uppercase">{{ selectedClaimPost.unit }}</span>
            </div>
          </div>

          <!-- Bổ sung phần Phương thức nhận hàng -->
          <div class="space-y-2 text-left pt-2">
            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">Phương thức nhận hàng:</label>
            <div class="grid gap-2 mt-2">
                <!-- Tự đến lấy -->
                <div 
                    @click="claimForm.shipping_method = 'self_pickup'"
                    class="p-3.5 rounded-2xl border text-xs font-semibold transition cursor-pointer flex items-center gap-3"
                    :class="claimForm.shipping_method === 'self_pickup' ? 'border-emerald-500 bg-emerald-50 text-emerald-800 shadow-sm ring-1 ring-emerald-500' : 'border-gray-200 bg-gray-50 text-gray-600 hover:bg-gray-100 hover:border-emerald-300'"
                >
                    <div class="w-4 h-4 rounded-full border flex items-center justify-center shrink-0 transition" :class="claimForm.shipping_method === 'self_pickup' ? 'border-emerald-500 bg-emerald-500' : 'border-gray-400 bg-white'">
                        <div v-if="claimForm.shipping_method === 'self_pickup'" class="w-1.5 h-1.5 bg-white rounded-full animate-in zoom-in"></div>
                    </div>
                    <span class="flex-1">Tôi sẽ tự đến lấy</span>
                    <svg v-if="claimForm.shipping_method === 'self_pickup'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                </div>
                
                <!-- Nhờ lấy hộ -->
                <div 
                    @click="claimForm.shipping_method = 'relative_pickup'"
                    class="p-3.5 rounded-2xl border text-xs font-semibold transition cursor-pointer flex items-center gap-3"
                    :class="claimForm.shipping_method === 'relative_pickup' ? 'border-indigo-500 bg-indigo-50 text-indigo-800 shadow-sm ring-1 ring-indigo-500' : 'border-gray-200 bg-gray-50 text-gray-600 hover:bg-gray-100 hover:border-indigo-300'"
                >
                    <div class="w-4 h-4 rounded-full border flex items-center justify-center shrink-0 transition" :class="claimForm.shipping_method === 'relative_pickup' ? 'border-indigo-500 bg-indigo-500' : 'border-gray-400 bg-white'">
                        <div v-if="claimForm.shipping_method === 'relative_pickup'" class="w-1.5 h-1.5 bg-white rounded-full animate-in zoom-in"></div>
                    </div>
                    <span class="flex-1">Nhờ người thân / bạn bè lấy hộ</span>
                    <svg v-if="claimForm.shipping_method === 'relative_pickup'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                </div>

                <!-- Giao hàng -->
                <div 
                    @click="claimForm.shipping_method = 'delivery_service'"
                    class="p-3.5 rounded-2xl border text-xs font-semibold transition cursor-pointer flex items-center gap-3"
                    :class="claimForm.shipping_method === 'delivery_service' ? 'border-orange-500 bg-orange-50 text-orange-800 shadow-sm ring-1 ring-orange-500' : 'border-gray-200 bg-gray-50 text-gray-600 hover:bg-gray-100 hover:border-orange-300'"
                >
                    <div class="w-4 h-4 rounded-full border flex items-center justify-center shrink-0 transition" :class="claimForm.shipping_method === 'delivery_service' ? 'border-orange-500 bg-orange-500' : 'border-gray-400 bg-white'">
                        <div v-if="claimForm.shipping_method === 'delivery_service'" class="w-1.5 h-1.5 bg-white rounded-full animate-in zoom-in"></div>
                    </div>
                    <span class="flex-1">Gọi dịch vụ giao hàng (Grab, XanhSM...)</span>
                    <svg v-if="claimForm.shipping_method === 'delivery_service'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-orange-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                </div>
            </div>
          </div>

          <!-- Ghi chú / Lời nhắn -->
          <div class="mt-4">
              <label class="block text-[11px] font-bold text-gray-700 uppercase tracking-wider mb-1.5 ml-1">Lời nhắn cho người chia sẻ</label>
              <textarea v-model="claimForm.message" rows="2" placeholder="VD: Cô cho cháu xin nhé ạ, cháu cảm ơn cô nhiều..." class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition text-gray-800 resize-none"></textarea>
          </div>

          <!-- Các ô nhập liệu mở rộng (hiển thị động theo phương thức) -->
          <div v-if="claimForm.shipping_method === 'relative_pickup'" class="grid grid-cols-2 gap-3 mt-2">
              <input type="text" v-model="claimForm.pickup_contact_name" placeholder="Tên người lấy hộ" class="bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-emerald-500 transition text-gray-800">
              <input type="text" v-model="claimForm.pickup_contact_phone" placeholder="SĐT liên hệ" class="bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-emerald-500 transition text-gray-800">
          </div>

          <div v-if="claimForm.shipping_method === 'delivery_service'" class="mt-2 text-[11px] text-gray-500 italic px-1">
              Bạn có thể cập nhật thông tin tài xế và biển số xe sau khi yêu cầu được duyệt.
          </div>

          <div class="flex items-center gap-3 pt-2">
            <button 
              @click="closeClaimModal" 
              class="flex-1 bg-gray-50 hover:bg-gray-100 text-gray-600 font-semibold text-xs py-3 rounded-xl border border-gray-200 transition text-center cursor-pointer"
            >
              Hủy bỏ
            </button>
            <button 
              @click="submitClaim" 
              class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs py-3 rounded-xl transition text-center cursor-pointer shadow-md shadow-emerald-100"
            >
              Xác nhận gửi
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- MODAL HỦY YÊU CẦU -->
  <div 
    v-if="showCancelModal" 
    class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-in fade-in duration-200"
  >
      <div class="bg-white rounded-3xl w-full max-w-sm p-6 shadow-2xl animate-in zoom-in-95 duration-200 border border-gray-100">
          <h3 class="text-lg font-extrabold text-gray-900 mb-2">Hủy yêu cầu</h3>
          <p class="text-xs text-gray-500 mb-4">Vui lòng chọn lý do hủy để chúng tôi hỗ trợ tốt hơn:</p>
          
          <div class="space-y-2 mb-6">
              <div 
                  v-for="opt in receiverCancelReasons" 
                  :key="opt"
                  @click="cancelForm.reason = opt"
                  class="p-3.5 rounded-2xl border text-xs font-semibold transition cursor-pointer flex items-center justify-between"
                  :class="cancelForm.reason === opt ? 'border-red-500 bg-red-50/50 text-red-700 shadow-sm shadow-red-100' : 'border-gray-100 bg-gray-50 hover:bg-gray-100 text-gray-700'"
              >
                  <span>{{ opt }}</span>
                  <span v-if="cancelForm.reason === opt" class="text-red-500 font-bold">✓</span>
              </div>
          </div>
          
          <div class="flex items-center gap-3">
              <button @click="showCancelModal = false" :disabled="isProcessing" class="flex-1 px-4 py-2.5 text-xs font-bold text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition disabled:opacity-50">Đóng</button>
              <button @click="submitCancelClaim" :disabled="isProcessing" class="flex-1 px-4 py-2.5 text-xs font-bold text-white bg-red-600 rounded-xl hover:bg-red-700 shadow-md shadow-red-500/30 transition disabled:opacity-50 flex justify-center items-center gap-2">
                <svg v-if="isProcessing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span>{{ isProcessing ? 'Đang xử lý...' : 'Xác nhận Hủy' }}</span>
              </button>
          </div>
      </div>
  </div>
  <!-- MODAL TỪ CHỐI / HỦY YÊU CẦU (DÀNH CHO NGƯỜI CHO) -->
  <div 
    v-if="showGiverCancelModal" 
    class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-in fade-in duration-200"
  >
      <div class="bg-white rounded-3xl w-full max-w-sm p-6 shadow-2xl animate-in zoom-in-95 duration-200 border border-gray-100 text-left">
          <h3 class="text-lg font-extrabold text-gray-900 mb-2">
            {{ giverCancelForm.status === 'rejected' ? 'Từ chối yêu cầu' : 'Hủy giao dịch' }}
          </h3>
          <p class="text-xs text-gray-500 mb-4">Vui lòng chọn lý do để người nhận biết thông tin:</p>
          
          <div class="space-y-2 mb-6">
              <div 
                  v-for="opt in giverCancelReasons" 
                  :key="opt"
                  @click="giverCancelForm.reason = opt"
                  class="p-3.5 rounded-2xl border text-xs font-semibold transition cursor-pointer flex items-center justify-between"
                  :class="giverCancelForm.reason === opt ? 'border-red-500 bg-red-50/50 text-red-700 shadow-sm shadow-red-100' : 'border-gray-100 bg-gray-50 hover:bg-gray-100 text-gray-700'"
              >
                  <span>{{ opt }}</span>
                  <span v-if="giverCancelForm.reason === opt" class="text-red-500 font-bold">✓</span>
              </div>
          </div>
          
          <div class="flex items-center gap-3">
              <button @click="showGiverCancelModal = false" :disabled="isProcessing" class="flex-1 px-4 py-2.5 text-xs font-bold text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition text-center disabled:opacity-50">Đóng</button>
              <button @click="submitGiverCancel" :disabled="isProcessing" class="flex-1 px-4 py-2.5 text-xs font-bold text-white bg-red-600 rounded-xl hover:bg-red-700 shadow-md shadow-red-500/30 transition text-center disabled:opacity-50 flex justify-center items-center gap-2">
                <svg v-if="isProcessing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span>{{ isProcessing ? 'Đang xử lý...' : 'Xác nhận' }}</span>
              </button>
          </div>
      </div>
  </div>

  <div v-if="showDonationModal && selectedCampaignForDonation" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="w-full max-w-md p-6 bg-white rounded-2xl shadow-xl relative animate-in zoom-in-95">
            <h3 class="mb-4 text-xl font-bold text-gray-900">Gửi quyên góp - {{ selectedCampaignForDonation.title }}</h3>
            <form @submit.prevent="submitDonation" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">Chọn các món đồ bạn muốn quyên góp</label>
                    <div v-if="donationForm.items.length === 0" class="text-sm text-amber-700 bg-amber-50 p-4 rounded-xl border border-amber-200 shadow-sm text-center font-medium">
                        Cảm ơn tấm lòng của bạn! Hiện tại tất cả các món đồ của chiến dịch này đều đã được quyên góp đủ (bao gồm cả các đơn đang chờ giao). Vui lòng chọn chiến dịch khác nhé!
                    </div>
                    <div v-else class="space-y-3 max-h-64 overflow-y-auto pr-2">
                        <div v-for="(item, index) in donationForm.items" :key="item.campaign_item_id" 
                             class="p-4 rounded-xl border transition-all duration-200"
                             :class="item.selected ? 'border-emerald-500 bg-emerald-50/30 shadow-sm' : 'border-gray-200 bg-gray-50/50 hover:bg-gray-50'">
                            
                            <label class="flex items-start gap-3" :class="item.is_locked_fresh ? 'cursor-not-allowed opacity-75' : 'cursor-pointer'">
                                <input type="checkbox" :disabled="item.is_locked_fresh" v-model="item.selected" class="mt-1 w-4 h-4 text-emerald-600 rounded border-gray-300 focus:ring-emerald-500 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                <div class="flex-1">
                                    <h4 class="font-bold text-gray-900 text-sm flex items-center gap-2">
                                        {{ item.item_name }}
                                        <span v-if="item.item_type === 'fresh'" class="text-[9px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-bold">Đồ Tươi</span>
                                        <span v-else class="text-[9px] bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded font-bold">Đồ Khô</span>
                                    </h4>
                                    <p class="text-xs text-gray-500 mt-0.5">Tiến độ hiện tại: {{ item.current_quantity + item.pending_quantity }} / {{ item.target_quantity }} {{ item.unit || '' }} <span class="text-orange-600 font-medium">(Cần thêm {{ item.remaining_quantity }})</span></p>
                                    <div v-if="item.is_locked_fresh" class="mt-1.5 text-[10.5px] text-red-600 font-semibold bg-red-50 p-2 rounded-lg border border-red-100 flex items-start gap-1.5">
                                        <span class="shrink-0 mt-0.5 text-xs">🔒</span>
                                        <span>Chỉ mở nhận quyên góp ở Mốc 2 (Giai đoạn tập kết đồ tươi) từ lúc {{ new Date(item.web_deadline).toLocaleString('vi-VN', {hour: '2-digit', minute:'2-digit', day:'2-digit', month:'2-digit', year:'numeric'}) }}</span>
                                    </div>
                                </div>
                            </label>

                            <div v-if="item.selected" class="mt-4 flex gap-3 animate-in fade-in slide-in-from-top-1 duration-200">
                                <div class="w-1/2">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Số lượng</label>
                                    <input type="number" min="1" :max="item.remaining_quantity" v-model="item.donation_quantity" class="w-full rounded-lg border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 text-sm py-2 bg-white" required>
                                </div>
                                <div class="w-1/2">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Đơn vị</label>
                                    <input type="text" v-model="item.unit" placeholder="VD: kg, thùng, hộp..." class="w-full rounded-lg border-gray-200 focus:border-emerald-500 focus:ring-emerald-500 text-sm py-2 bg-white" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mô tả (Tuỳ chọn)</label>
                    <textarea v-model="donationForm.food_description" class="w-full rounded-xl border-gray-200 focus:border-emerald-600 focus:ring-emerald-600 text-sm" rows="2" placeholder="Tình trạng, quy cách đóng gói..."></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Phương thức giao hàng</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div
                            @click="donationForm.shipping_method = 'self_delivery'"
                            class="relative flex items-start gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all duration-200"
                            :class="donationForm.shipping_method === 'self_delivery' ? 'border-emerald-500 bg-emerald-50 text-emerald-800 shadow-sm ring-1 ring-emerald-500' : 'border-gray-200 bg-gray-50 text-gray-600 hover:bg-gray-100 hover:border-emerald-300'"
                        >
                            <div class="w-4 h-4 mt-0.5 rounded-full border flex items-center justify-center shrink-0 transition" :class="donationForm.shipping_method === 'self_delivery' ? 'border-emerald-500 bg-emerald-500' : 'border-gray-400 bg-white'">
                                <div v-if="donationForm.shipping_method === 'self_delivery'" class="w-1.5 h-1.5 bg-white rounded-full animate-in zoom-in"></div>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-sm font-bold" :class="donationForm.shipping_method === 'self_delivery' ? 'text-emerald-800' : 'text-gray-700'">Tự mang đến</h4>
                                <p class="text-[11px] mt-0.5 leading-tight" :class="donationForm.shipping_method === 'self_delivery' ? 'text-emerald-600' : 'text-gray-500'">Tôi sẽ trực tiếp mang đến điểm tập kết của chiến dịch</p>
                            </div>
                            <svg v-if="donationForm.shipping_method === 'self_delivery'" xmlns="http://www.w3.org/2000/svg" class="absolute top-3 right-3 h-4 w-4 text-emerald-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                        </div>

                        <div
                            @click="donationForm.shipping_method = 'delivery_service'"
                            class="relative flex items-start gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all duration-200"
                            :class="donationForm.shipping_method === 'delivery_service' ? 'border-orange-500 bg-orange-50 text-orange-800 shadow-sm ring-1 ring-orange-500' : 'border-gray-200 bg-gray-50 text-gray-600 hover:bg-gray-100 hover:border-orange-300'"
                        >
                            <div class="w-4 h-4 mt-0.5 rounded-full border flex items-center justify-center shrink-0 transition" :class="donationForm.shipping_method === 'delivery_service' ? 'border-orange-500 bg-orange-500' : 'border-gray-400 bg-white'">
                                <div v-if="donationForm.shipping_method === 'delivery_service'" class="w-1.5 h-1.5 bg-white rounded-full animate-in zoom-in"></div>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-sm font-bold" :class="donationForm.shipping_method === 'delivery_service' ? 'text-orange-800' : 'text-gray-700'">Dịch vụ giao hàng</h4>
                                <p class="text-[11px] mt-0.5 leading-tight" :class="donationForm.shipping_method === 'delivery_service' ? 'text-orange-600' : 'text-gray-500'">Gửi qua Grab, XanhSM, Ahamove...</p>
                            </div>
                            <svg v-if="donationForm.shipping_method === 'delivery_service'" xmlns="http://www.w3.org/2000/svg" class="absolute top-3 right-3 h-4 w-4 text-orange-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                        </div>
                    </div>
                    <div v-if="donationForm.shipping_method === 'delivery_service'" class="mt-2 text-[11px] text-gray-500 italic px-1">
                        * Bạn có thể bổ sung thông tin tài xế (tên, biển số xe, SĐT) sau khi tạo đơn quyên góp để dễ dàng xác nhận.
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" @click="showDonationModal = false" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200">
                        Hủy
                    </button>
                    <button type="submit" class="px-5 py-2 text-sm font-bold text-white bg-[#006F60] rounded-xl hover:bg-[#005a4e] shadow-md transition-colors disabled:opacity-50" :disabled="donationForm.processing || donationForm.items.length === 0">
                        <span v-if="donationForm.processing">Đang xử lý...</span>
                        <span v-else>Xác nhận gửi</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <ReportModal
        :show="showReportModal"
        :target-user="reportTargetUser"
        :target-post="reportTargetPost"
        :target-claim="reportTargetClaim"
        @close="closeReportModal"
        @success="fetchNearbyFood"
    />

    <ToastMessage />
</template>
