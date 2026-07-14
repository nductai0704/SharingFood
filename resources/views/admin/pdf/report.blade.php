<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Báo Cáo Thống Kê SharingFood</title>
    <style>
        /* BẮT BUỘC SỬ DỤNG FONT DEJAVU SANS ĐỂ KHÔNG BỊ LỖI TIẾNG VIỆT TRONG DOMPDF */
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 14px;
            color: #333;
            line-height: 1.5;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #10b981;
            padding-bottom: 10px;
        }
        .header h1 {
            color: #10b981;
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0 0 0;
            font-size: 12px;
            color: #666;
        }
        .section-title {
            background-color: #f3f4f6;
            padding: 8px 12px;
            font-weight: bold;
            font-size: 16px;
            margin-top: 20px;
            margin-bottom: 10px;
            border-left: 4px solid #10b981;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table th, table td {
            border: 1px solid #e5e7eb;
            padding: 10px;
            text-align: left;
        }
        table th {
            background-color: #f9fafb;
            font-weight: bold;
            color: #4b5563;
        }
        .value {
            font-weight: bold;
            color: #111827;
            text-align: right;
            width: 150px;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>BÁO CÁO TỔNG QUAN HỆ THỐNG SHARINGFOOD</h1>
        <p>Khoảng thời gian thống kê: 
            @if($period === 'today') Hôm nay
            @elseif($period === '7days') 7 Ngày Qua
            @elseif($period === '30days') 30 Ngày Qua
            @elseif($period === 'custom') Tùy chỉnh ({{ request('start_date') }} - {{ request('end_date') }})
            @else Tất cả thời gian
            @endif
        </p>
        <p>Ngày xuất báo cáo: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <div class="section-title">1. TỔNG QUAN CHUNG</div>
    <table>
        <tbody>
            <tr>
                <td>Tổng số người dùng tham gia hệ thống</td>
                <td class="value">{{ number_format($stats['total_users']) }}</td>
            </tr>
            <tr>
                <td>Tổng số bài đăng chia sẻ thực phẩm</td>
                <td class="value">{{ number_format($stats['total_food_posts']) }}</td>
            </tr>
            <tr>
                <td>Tổng số chiến dịch quyên góp đã tạo</td>
                <td class="value">{{ number_format($stats['total_campaigns']) }}</td>
            </tr>
            <tr>
                <td>Tổng sản lượng thực phẩm đã giải cứu / quyên góp</td>
                <td class="value">{{ number_format($stats['rescued_volume']) }} KG</td>
            </tr>
            <tr>
                <td>Tỷ lệ giao dịch hoàn thành (Thành công)</td>
                <td class="value">{{ $stats['success_rate'] }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">2. CHI TIẾT YÊU CẦU NHẬN THỰC PHẨM LẺ</div>
    <table>
        <thead>
            <tr>
                <th>Trạng thái</th>
                <th style="text-align: right">Số lượng</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Đã hoàn thành</td>
                <td class="value">{{ number_format($stats['claims_breakdown']->completed_count ?? 0) }}</td>
            </tr>
            <tr>
                <td>Đang chờ xử lý / Chờ xác nhận</td>
                <td class="value">{{ number_format($stats['claims_breakdown']->pending_count ?? 0) }}</td>
            </tr>
            <tr>
                <td>Đã hủy / Thất bại</td>
                <td class="value">{{ number_format($stats['claims_breakdown']->cancelled_count ?? 0) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">3. CHI TIẾT QUYÊN GÓP VÀO CHIẾN DỊCH</div>
    <table>
        <thead>
            <tr>
                <th>Trạng thái quyên góp</th>
                <th style="text-align: right">Khối lượng (KG)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Khối lượng đã hoàn thành (Nhận thành công)</td>
                <td class="value">{{ number_format($stats['donations_breakdown']->completed_quantity ?? 0) }}</td>
            </tr>
            <tr>
                <td>Khối lượng đang chờ xử lý</td>
                <td class="value">{{ number_format($stats['donations_breakdown']->pending_quantity ?? 0) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Tài liệu được xuất tự động từ Hệ thống Quản trị SharingFood.
    </div>

</body>
</html>
