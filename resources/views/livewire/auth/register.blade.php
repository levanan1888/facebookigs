<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered(($user = User::create($validated))));

        Auth::login($user);

        $this->redirectIntended(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="min-h-screen bg-gray-50 flex flex-col">
    <!-- Main Content -->
    <div class="flex flex-1">
        <!-- Left Section - Branding -->
        <div class="flex-1 flex items-center justify-center px-8 py-12">
            <div class="max-w-md">
                <!-- Facebook Logo -->
                <div class="text-center mb-8">
                    <div class="text-6xl font-bold text-blue-600 mb-4">facebook</div>
                </div>
                
                <!-- Slogan -->
                <h2 class="text-2xl leading-8 text-gray-900 font-normal">
                    Facebook giúp bạn kết nối và chia sẻ với mọi người trong cuộc sống của mình.
                </h2>
            </div>
        </div>

        <!-- Right Section - Register Form -->
        <div class="flex-1 flex items-center justify-center px-8 py-12">
            <div class="w-full max-w-md">
                <!-- Register Form Card -->
                <div class="bg-white rounded-lg shadow-lg p-8 border border-gray-200">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">Tạo tài khoản mới</h2>
                    
                    <form method="POST" wire:submit="register" class="space-y-4">
                        <!-- Name Input -->
                        <div>
                            <input
                                wire:model="name"
                                id="name"
                                type="text"
                                required
                                autofocus
                                autocomplete="name"
                                placeholder="Họ và tên"
                                class="block w-full px-4 py-3 border border-gray-300 rounded-lg text-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Email Input -->
                        <div>
                            <input
                                wire:model="email"
                                id="email"
                                type="email"
                                required
                                autocomplete="email"
                                placeholder="Email"
                                class="block w-full px-4 py-3 border border-gray-300 rounded-lg text-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Password Input -->
                        <div>
                            <input
                                wire:model="password"
                                id="password"
                                type="password"
                                required
                                autocomplete="new-password"
                                placeholder="Mật khẩu mới"
                                class="block w-full px-4 py-3 border border-gray-300 rounded-lg text-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                            @error('password')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Confirm Password Input -->
                        <div>
                            <input
                                wire:model="password_confirmation"
                                id="password_confirmation"
                                type="password"
                                required
                                autocomplete="new-password"
                                placeholder="Nhập lại mật khẩu"
                                class="block w-full px-4 py-3 border border-gray-300 rounded-lg text-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>

                        <!-- Terms and Conditions -->
                        <div class="text-sm text-gray-600">
                            Bằng cách nhấp vào Đăng ký, bạn đồng ý với 
                            <a href="#" class="text-blue-600 hover:text-blue-700 font-semibold">Điều khoản</a>, 
                            <a href="#" class="text-blue-600 hover:text-blue-700 font-semibold">Chính sách quyền riêng tư</a> và 
                            <a href="#" class="text-blue-600 hover:text-blue-700 font-semibold">Chính sách cookie</a> của chúng tôi.
                        </div>

                        <!-- Register Button -->
                        <div>
                            <button
                                type="submit"
                                class="w-full bg-green-500 hover:bg-green-600 text-white font-bold text-xl py-3 px-4 rounded-lg transition-colors duration-200"
                            >
                                Đăng ký
                            </button>
                        </div>
                    </form>

                    <!-- Divider -->
                    <div class="mt-6">
                        <div class="relative">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-gray-300"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Login Link -->
                    <div class="mt-6 text-center">
                        <a href="{{ route('login') }}" 
                           class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-xl py-3 px-4 rounded-lg transition-colors duration-200 inline-block text-center">
                            Đăng nhập
                        </a>
                    </div>
                </div>

                <!-- Create Page Text -->
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        <a href="#" class="font-semibold text-gray-900 hover:underline">Tạo Trang</a> dành cho người nổi tiếng, thương hiệu hoặc doanh nghiệp.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="bg-gray-50 border-t border-gray-200 py-4 px-8">
        <div class="max-w-6xl mx-auto">
            <!-- Language Row -->
            <div class="flex flex-wrap items-center gap-4 mb-4 text-sm">
                <span class="text-gray-600">Tiếng Việt</span>
                <span class="text-gray-400">English (UK)</span>
                <span class="text-gray-400">中文(台灣)</span>
                <span class="text-gray-400">日本語</span>
                <span class="text-gray-400">Français (France)</span>
                <span class="text-gray-400">ภาษาไทย</span>
                <span class="text-gray-400">Español</span>
                <span class="text-gray-400">Português (Brasil)</span>
                <span class="text-gray-400">Deutsch</span>
                <span class="text-gray-400">Italiano</span>
                <button class="text-gray-400 hover:text-gray-600">+</button>
            </div>

            <!-- Links Row -->
            <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
                <a href="#" class="hover:underline">Đăng ký</a>
                <a href="#" class="hover:underline">Instagram</a>
                <a href="#" class="hover:underline">Tuyển dụng</a>
                <a href="#" class="hover:underline">Đăng nhập</a>
                <a href="#" class="hover:underline">Threads</a>
                <a href="#" class="hover:underline">Cookie</a>
                <a href="#" class="hover:underline">Messenger</a>
                <a href="#" class="hover:underline">Facebook Lite</a>
                <a href="#" class="hover:underline">Video</a>
                <a href="#" class="hover:underline">Meta Pay</a>
                <a href="#" class="hover:underline">Cửa hàng trên Meta</a>
                <a href="#" class="hover:underline">Meta Quest</a>
                <a href="#" class="hover:underline">Ray-Ban Meta</a>
                <a href="#" class="hover:underline">Meta AI</a>
                <a href="#" class="hover:underline">Trung tâm thông tin bỏ phiếu</a>
                <a href="#" class="hover:underline">Chính sách quyền riêng tư</a>
                <a href="#" class="hover:underline">Trung tâm quyền riêng tư</a>
                <a href="#" class="hover:underline">Giới thiệu</a>
                <a href="#" class="hover:underline">Tạo quảng cáo</a>
                <a href="#" class="hover:underline">Lựa chọn quảng cáo</a>
                <a href="#" class="hover:underline">Điều khoản</a>
                <a href="#" class="hover:underline">Trợ giúp</a>
                <a href="#" class="hover:underline">Tải thông tin liên hệ lên & đối tượng không phải người dùng</a>
                <a href="#" class="hover:underline">Nội dung khác do Meta AI tạo</a>
                <a href="#" class="hover:underline">Tạo Trang</a>
                <a href="#" class="hover:underline">Nhà phát triển</a>
            </div>

            <!-- Copyright -->
            <div class="mt-4 text-sm text-gray-600">
                Meta © 2025
            </div>
        </div>
    </div>
</div>
