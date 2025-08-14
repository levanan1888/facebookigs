<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Facebook Insgin - Giải pháp quản lý Facebook chuyên nghiệp</title>
    <meta name="description" content="Facebook Insgin - Nền tảng quản lý Facebook hiệu quả, giúp tối ưu hóa chiến dịch marketing và tăng tương tác với khách hàng">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 1rem 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        
        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
        }
        
        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .nav-links a:hover {
            color: #fbbf24;
        }
        
        .auth-buttons {
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-primary {
            background: #fbbf24;
            color: #1f2937;
        }
        
        .btn-primary:hover {
            background: #f59e0b;
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background: transparent;
            color: white;
            border: 2px solid white;
        }
        
        .btn-outline:hover {
            background: white;
            color: #1f2937;
        }
        
        /* Hero Section */
        .hero {
            padding: 8rem 0 4rem;
            text-align: center;
            color: white;
        }
        
        .hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }
        
        .hero p {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-lg {
            padding: 1rem 2rem;
            font-size: 1.1rem;
        }
        
        /* Features Section */
        .features {
            padding: 5rem 0;
            background: white;
        }
        
        .section-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 3rem;
            color: #1f2937;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }
        
        .feature-card {
            background: #f8fafc;
            padding: 2rem;
            border-radius: 16px;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2rem;
        }
        
        .feature-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #1f2937;
        }
        
        .feature-card p {
            color: #6b7280;
            line-height: 1.6;
        }
        
        /* CTA Section */
        .cta {
            padding: 5rem 0;
            background: linear-gradient(135deg, #1f2937, #374151);
            color: white;
            text-align: center;
        }
        
        .cta h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }
        
        .cta p {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        /* Footer */
        .footer {
            background: #111827;
            color: white;
            padding: 3rem 0 2rem;
            text-align: center;
        }
        
        .footer p {
            opacity: 0.7;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <a href="#" class="logo">
                    <i class="fab fa-facebook"></i> Facebook Insgin
                </a>
                <ul class="nav-links">
                    <li><a href="#features">Tính năng</a></li>
                    <li><a href="#pricing">Giá cả</a></li>
                    <li><a href="#contact">Liên hệ</a></li>
                </ul>
                <div class="auth-buttons">
                    <a href="{{ route('login') }}" class="btn btn-outline">Đăng nhập</a>
                    <a href="{{ route('register') }}" class="btn btn-primary">Đăng ký</a>
                </div>
            </nav>
        </div>
    </header>

    <main>
        <!-- Hero Section -->
        <section class="hero">
            <div class="container">
                <h1>Quản lý Facebook hiệu quả hơn bao giờ hết</h1>
                <p>Facebook Insgin cung cấp các công cụ mạnh mẽ để quản lý trang Facebook, tối ưu hóa nội dung và tăng tương tác với khách hàng một cách chuyên nghiệp.</p>
                <div class="hero-buttons">
                    <a href="{{ route('register') }}" class="btn btn-primary btn-lg">Bắt đầu miễn phí</a>
                    <a href="#features" class="btn btn-outline btn-lg">Tìm hiểu thêm</a>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="features" id="features">
            <div class="container">
                <h2 class="section-title">Tính năng nổi bật</h2>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>Phân tích hiệu suất</h3>
                        <p>Theo dõi và phân tích hiệu suất của các bài đăng, giúp bạn hiểu rõ hơn về đối tượng khách hàng và tối ưu hóa chiến lược marketing.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3>Lên lịch bài đăng</h3>
                        <p>Lên lịch trước các bài đăng để duy trì hoạt động liên tục trên trang Facebook, tiết kiệm thời gian và công sức.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Quản lý tương tác</h3>
                        <p>Quản lý hiệu quả các bình luận, tin nhắn và tương tác với khách hàng, xây dựng mối quan hệ bền vững.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <h3>Quảng cáo thông minh</h3>
                        <p>Tối ưu hóa chiến dịch quảng cáo Facebook với các công cụ phân tích và đề xuất thông minh.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h3>Responsive Design</h3>
                        <p>Giao diện thân thiện với mọi thiết bị, giúp bạn quản lý Facebook mọi lúc, mọi nơi.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3>Bảo mật cao</h3>
                        <p>Đảm bảo an toàn thông tin và dữ liệu của bạn với các biện pháp bảo mật tiên tiến.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="cta">
            <div class="container">
                <h2>Sẵn sàng nâng tầm Facebook của bạn?</h2>
                <p>Tham gia cùng hàng nghìn doanh nghiệp đã thành công với Facebook Insgin</p>
                <a href="{{ route('register') }}" class="btn btn-primary btn-lg">Đăng ký ngay hôm nay</a>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; {{ date('Y') }} Facebook Insgin. Tất cả quyền được bảo lưu.</p>
        </div>
    </footer>
</body>
</html> 