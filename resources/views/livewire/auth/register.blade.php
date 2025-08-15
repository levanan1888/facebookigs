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

<div>
    <!-- Background with AI Style -->
    <div class="fixed inset-0 z-0 overflow-hidden">
        <!-- Animated Background Elements -->
        <div class="absolute inset-0">
            <div class="ai-particles">
                <div class="particle particle-1"></div>
                <div class="particle particle-2"></div>
                <div class="particle particle-3"></div>
                <div class="particle particle-4"></div>
                <div class="particle particle-5"></div>
            </div>
        </div>
        
        <!-- Gradient Background -->
        <div class="absolute inset-0 bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50"></div>
    </div>

    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="max-w-md w-full space-y-8">
            <!-- Logo and Header -->
            <div class="text-center">
                <div class="mx-auto h-20 w-20 flex items-center justify-center rounded-2xl bg-gradient-to-r from-sky-500 to-blue-600 shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:scale-105">
                    <i class="fab fa-facebook text-white text-3xl"></i>
                </div>
                <h2 class="mt-6 text-3xl font-semibold text-slate-900">
                    Tạo tài khoản mới
                </h2>
                <p class="mt-2 text-lg text-slate-700 font-normal">
                    Tham gia cùng chúng tôi và bắt đầu hành trình
                </p>
            </div>

            <!-- Register Form -->
            <div class="bg-white py-8 px-6 shadow-2xl rounded-2xl border border-slate-200">
                <!-- Session Status -->
                <x-auth-session-status class="mb-4 text-center" :status="session('status')" />

                <form method="POST" wire:submit="register" class="space-y-6">
                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-800 mb-2">
                            Họ và tên
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-sky-600"></i>
                            </div>
                            <input
                                wire:model="name"
                                id="name"
                                type="text"
                                required
                                autofocus
                                autocomplete="name"
                                placeholder="Nhập họ và tên của bạn"
                                class="block w-full pl-10 pr-3 py-3 border border-slate-400 rounded-xl shadow-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-sky-600 transition-all duration-200 bg-white text-slate-900 font-normal"
                            />
                        </div>
                        @error('name')
                            <p class="mt-1 text-sm text-red-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email Address -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-800 mb-2">
                            Địa chỉ email
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-envelope text-sky-600"></i>
                            </div>
                            <input
                                wire:model="email"
                                id="email"
                                type="email"
                                required
                                autocomplete="email"
                                placeholder="Nhập email của bạn"
                                class="block w-full pl-10 pr-3 py-3 border border-slate-400 rounded-xl shadow-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-sky-600 transition-all duration-200 bg-white text-slate-900 font-normal"
                            />
                        </div>
                        @error('email')
                            <p class="mt-1 text-sm text-red-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-800 mb-2">
                            Mật khẩu
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-sky-600"></i>
                            </div>
                            <input
                                wire:model="password"
                                id="password"
                                type="password"
                                required
                                autocomplete="new-password"
                                placeholder="Tạo mật khẩu mới"
                                class="block w-full pl-10 pr-3 py-3 border border-slate-400 rounded-xl shadow-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-sky-600 transition-all duration-200 bg-white text-slate-900 font-normal"
                            />
                        </div>
                        @error('password')
                            <p class="mt-1 text-sm text-red-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-slate-800 mb-2">
                            Xác nhận mật khẩu
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-check-circle text-sky-600"></i>
                            </div>
                            <input
                                wire:model="password_confirmation"
                                id="password_confirmation"
                                type="password"
                                required
                                autocomplete="new-password"
                                placeholder="Xác nhận mật khẩu của bạn"
                                class="block w-full pl-10 pr-3 py-3 border border-slate-400 rounded-xl shadow-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-sky-600 transition-all duration-200 bg-white text-slate-900 font-normal"
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
                                class="h-4 w-4 text-sky-600 focus:ring-sky-600 border-slate-400 rounded"
                            />
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="terms" class="text-slate-700 font-normal">
                                Tôi đồng ý với 
                                <a href="#" class="text-sky-700 hover:text-sky-800 font-medium underline transition-colors duration-200">Điều khoản dịch vụ</a>
                                và
                                <a href="#" class="text-sky-700 hover:text-sky-800 font-medium underline transition-colors duration-200">Chính sách bảo mật</a>
                            </label>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div>
                        <button
                            type="submit"
                            class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-xl text-white bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-600 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-600 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:scale-105"
                        >
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i class="fas fa-user-plus text-sky-200 group-hover:text-sky-100 transition-colors duration-200"></i>
                            </span>
                            Tạo tài khoản
                        </button>
                    </div>
                </form>

                <!-- Divider -->
                <div class="mt-6">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-slate-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-slate-600 font-medium">Hoặc đăng ký với</span>
                        </div>
                    </div>

                    <!-- Social Login Buttons -->
                    <div class="mt-6 grid grid-cols-2 gap-3">
                        <button class="w-full inline-flex justify-center py-2 px-4 border border-slate-300 rounded-xl shadow-sm bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 hover:border-sky-500 transition-all duration-200 transform hover:scale-105">
                            <i class="fab fa-google text-red-500"></i>
                            <span class="ml-2">Google</span>
                        </button>

                        <button class="w-full inline-flex justify-center py-2 px-4 border border-slate-300 rounded-xl shadow-sm bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 hover:border-sky-500 transition-all duration-200 transform hover:scale-105">
                            <i class="fab fa-facebook text-blue-600"></i>
                            <span class="ml-2">Facebook</span>
                        </button>
                    </div>

                    <!-- Sign In Link -->
                    <div class="mt-6 text-center">
                        <p class="text-sm text-slate-700 font-normal">
                            Đã có tài khoản? 
                            <a href="{{ route('login') }}" class="text-sky-700 hover:text-sky-800 font-medium underline transition-colors duration-200">
                                Đăng nhập ngay
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <style>
        /* AI Particles Animation */
        .ai-particles {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            background: linear-gradient(135deg, #0ea5e9, #3b82f6);
            border-radius: 50%;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }

        .particle-1 {
            width: 80px;
            height: 80px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .particle-2 {
            width: 60px;
            height: 60px;
            top: 60%;
            right: 15%;
            animation-delay: 1s;
        }

        .particle-3 {
            width: 40px;
            height: 40px;
            bottom: 30%;
            left: 20%;
            animation-delay: 2s;
        }

        .particle-4 {
            width: 100px;
            height: 100px;
            top: 40%;
            left: 60%;
            animation-delay: 3s;
        }

        .particle-5 {
            width: 50px;
            height: 50px;
            bottom: 20%;
            right: 25%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
                opacity: 0.1;
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
                opacity: 0.3;
            }
        }

        /* Modern Glassmorphism */
        .backdrop-blur-xl {
            backdrop-filter: blur(24px);
        }

        /* Smooth Transitions */
        * {
            transition: all 0.2s ease-in-out;
        }
        </style>
    </div>
