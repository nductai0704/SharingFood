const fs = require('fs');
const path = require('path');

// --- UPDATE Login.vue ---
const loginPath = path.join(__dirname, 'resources/js/Pages/Auth/Login.vue');
let loginContent = fs.readFileSync(loginPath, 'utf8');

const echoCode = `onMounted(() => {
    if (window.Echo) {
        window.Echo.channel(\`qr-login.\${qrToken.value}\`)
            .listen('QrLoginSuccessful', (e) => {
                // Tín hiệu đã nhận! Gọi API callback để login thực tế
                useForm({ user_id: e.userId }).post(route('qr.login.callback'));
            });
    }
});`;

loginContent = loginContent.replace(
    /onMounted\(\(\) => \{\n\s*\/\/ Chúng ta sẽ setup window\.Echo\.channel\(\.\.\.\) ở đây\n\s*\}\);/g,
    echoCode
);

fs.writeFileSync(loginPath, loginContent, 'utf8');

// --- UPDATE web.php ---
const webPath = path.join(__dirname, 'routes/web.php');
let webContent = fs.readFileSync(webPath, 'utf8');

const routesToAdd = `
// --- QR Login Mock ---
Route::get('/qr-scanner-mock', function () {
    return Inertia\\Inertia::render('Auth/QrScannerMock');
})->middleware('auth')->name('qr.scanner');

Route::post('/qr-verify', function (\\Illuminate\\Http\\Request $request) {
    $request->validate(['token' => 'required|string']);
    
    // Broadcast sự kiện
    broadcast(new \\App\\Events\\QrLoginSuccessful($request->token, auth()->id()));
    
    return back()->with('status', 'Quét mã thành công! Trình duyệt bên kia đang đăng nhập...');
})->middleware('auth')->name('qr.verify');

// Bí mật: route để login nhanh từ QR (chỉ dùng cho Demo trên cùng 1 máy tính)
Route::post('/qr-login-callback', function (\\Illuminate\\Http\\Request $request) {
    $request->validate(['user_id' => 'required|exists:users,id']);
    
    // Đăng nhập User ID này
    auth()->loginUsingId($request->user_id);
    
    return redirect()->intended(route('dashboard'));
})->name('qr.login.callback');
`;

if (!webContent.includes('qr-scanner-mock')) {
    webContent += routesToAdd;
    fs.writeFileSync(webPath, webContent, 'utf8');
}

console.log("Updated Login.vue and web.php for QR Mock successfully.");
