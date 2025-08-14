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

<div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-blue-100 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
    <!-- AI Character Animation -->
    <div class="absolute top-10 left-10 w-16 h-16 animate-bounce">
        <div class="w-full h-full bg-gradient-to-r from-blue-400 to-purple-500 rounded-full flex items-center justify-center text-white text-2xl animate-pulse">
            🤖
        </div>
    </div>
    
    <div class="absolute top-20 right-16 w-12 h-12 animate-bounce" style="animation-delay: 0.5s;">
        <div class="w-full h-full bg-gradient-to-r from-green-400 to-blue-500 rounded-full flex items-center justify-center text-white text-xl animate-pulse">
            ✨
        </div>
    </div>
    
    <div class="absolute bottom-20 left-20 w-10 h-10 animate-bounce" style="animation-delay: 1s;">
        <div class="w-full h-full bg-gradient-to-r from-purple-400 to-pink-500 rounded-full flex items-center justify-center text-white text-lg animate-pulse">
            💡
        </div>
    </div>

    <div class="max-w-md w-full space-y-8 relative z-10">
        <!-- Logo and Header -->
        <div class="text-center">
            <div class="mx-auto h-20 w-20 flex items-center justify-center rounded-full bg-gradient-to-r from-blue-500 to-blue-600 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                <i class="fab fa-facebook text-white text-3xl"></i>
            </div>
            <h2 class="mt-6 text-3xl font-semibold text-gray-800">
                Tạo tài khoản mới
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                Tham gia cùng chúng tôi và bắt đầu hành trình
            </p>
        </div>

        <!-- Register Form -->
        <div class="bg-white/80 backdrop-blur-sm py-8 px-6 shadow-xl rounded-2xl border border-white/20">
            <!-- Session Status -->
            <x-auth-session-status class="mb-4 text-center" :status="session('status')" />

            <form method="POST" wire:submit="register" class="space-y-6">
                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Họ và tên
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user text-blue-400"></i>
                        </div>
                        <input
                            wire:model="name"
                            id="name"
                            type="text"
                            required
                            autofocus
                            autocomplete="name"
                            placeholder="Nhập họ và tên của bạn"
                            class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                        />
                    </div>
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email Address -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        Địa chỉ email
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-blue-400"></i>
                        </div>
                        <input
                            wire:model="email"
                            id="email"
                            type="email"
                            required
                            autocomplete="email"
                            placeholder="Nhập email của bạn"
                            class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                        />
                    </div>
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        Mật khẩu
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-blue-400"></i>
                        </div>
                        <input
                            wire:model="password"
                            id="password"
                            type="password"
                            required
                            autocomplete="new-password"
                            placeholder="Tạo mật khẩu mới"
                            class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                        />
                    </div>
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Confirm Password -->
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                        Xác nhận mật khẩu
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-check-circle text-blue-400"></i>
                        </div>
                        <input
                            wire:model="password_confirmation"
                            id="password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            placeholder="Xác nhận mật khẩu của bạn"
                            class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                        />
                    </div>
                </div>

                <!-- Terms and Conditions -->
                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <input
                            id="terms"
                            type="checkbox"
                            required
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        />
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="terms" class="text-gray-700">
                            Tôi đồng ý với 
                            <a href="#" class="text-blue-600 hover:text-blue-500 transition-colors duration-200">Điều khoản dịch vụ</a>
                            và
                            <a href="#" class="text-blue-600 hover:text-blue-500 transition-colors duration-200">Chính sách bảo mật</a>
                        </label>
                    </div>
                </div>

                <!-- Submit Button -->
                <div>
                    <button
                        type="submit"
                        class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:scale-105"
                    >
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <i class="fas fa-user-plus text-blue-300 group-hover:text-blue-200 transition-colors duration-200"></i>
                        </span>
                        Tạo tài khoản
                    </button>
                </div>
            </form>

            <!-- Divider -->
            <div class="mt-6">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white/80 text-gray-500">Hoặc đăng ký với</span>
                    </div>
                </div>

                <!-- Social Login Buttons -->
                <div class="mt-6 grid grid-cols-2 gap-3">
                    <button class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 hover:border-blue-300 transition-all duration-200 transform hover:scale-105">
                        <i class="fab fa-google text-red-500"></i>
                        <span class="ml-2">Google</span>
                    </button>

                    <button class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 hover:border-blue-300 transition-all duration-200 transform hover:scale-105">
                        <i class="fab fa-facebook text-blue-600"></i>
                        <span class="ml-2">Facebook</span>
                    </button>
                </div>
            </div>

            <!-- Sign In Link -->
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Đã có tài khoản? 
                    <a href="{{ route('login') }}" class="font-medium text-blue-600 hover:text-blue-500 transition-colors duration-200">
                        Đăng nhập ngay
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-20px); }
}

.animate-float {
    animation: float 3s ease-in-out infinite;
}

.animate-bounce {
    animation: bounce 2s infinite;
}

@keyframes bounce {
    0%, 20%, 53%, 80%, 100% {
        transform: translate3d(0,0,0);
    }
    40%, 43% {
        transform: translate3d(0,-30px,0);
    }
    70% {
        transform: translate3d(0,-15px,0);
    }
    90% {
        transform: translate3d(0,-4px,0);
    }
}
</style>
