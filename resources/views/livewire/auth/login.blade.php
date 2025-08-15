<?php

use Livewire\Volt\Component;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        // Regenerate session to prevent fixation after successful login
        request()->session()->regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
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
        
        <!-- Brand Background -->
        <div class="absolute inset-0 bg-white"></div>
    </div>

    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="max-w-md w-full space-y-8">
            <!-- Logo and Header -->
            <div class="text-center">
                <div class="mx-auto h-20 w-20 flex items-center justify-center rounded-2xl bg-[#1877F2] shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:scale-105">
                    <i class="fab fa-facebook text-white text-3xl"></i>
                </div>
                <h2 class="mt-6 text-3xl font-semibold text-slate-800">
                    Chào mừng trở lại
                </h2>
                <p class="mt-2 text-lg text-slate-600 font-normal">
                    Đăng nhập vào tài khoản của bạn
                </p>
            </div>

            <!-- Login Form -->
            <div class="bg-white/80 backdrop-blur-xl py-8 px-6 shadow-2xl rounded-2xl border border-white/20">
                <!-- Session Status -->
                <x-auth-session-status class="mb-4 text-center" :status="session('status')" />

                <form method="POST" wire:submit="login" class="space-y-6">
                    @if ($errors->any())
                        <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                            {{ __('Thông tin đăng nhập không đúng. Vui lòng kiểm tra và thử lại.') }}
                        </div>
                    @endif
                    <!-- Email Address -->
                    <div>
                        <label for="email" class="block text-sm font-semibold text-slate-900 mb-2">
                            Địa chỉ email
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-envelope text-[#1877F2]"></i>
                            </div>
                            <input
                                wire:model="email"
                                id="email"
                                type="email"
                                required
                                autofocus
                                autocomplete="email"
                                placeholder="Nhập email của bạn"
                                class="block w-full pl-10 pr-3 py-3 border border-slate-300 rounded-xl shadow-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-[#1877F2] focus:border-[#1877F2] transition-all duration-200 bg-white text-slate-900 font-normal"
                            />
                        </div>
                        @error('email')
                            <p class="mt-1 text-sm text-red-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-semibold text-slate-900 mb-2">
                            Mật khẩu
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-[#1877F2]"></i>
                            </div>
                            <input
                                wire:model="password"
                                id="password"
                                type="password"
                                required
                                autocomplete="current-password"
                                placeholder="Nhập mật khẩu của bạn"
                                class="block w-full pl-10 pr-12 py-3 border border-slate-300 rounded-xl shadow-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-[#1877F2] focus:border-[#1877F2] transition-all duration-200 bg-white text-slate-900 font-normal"
                            />
                            <button type="button" aria-label="Hiển thị mật khẩu" title="Hiển thị mật khẩu"
                                onclick="const i=document.getElementById('password'); i.type = i.type==='password'?'text':'password'"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-700">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-1 text-sm text-red-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Remember Me and Forgot Password -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input
                                wire:model="remember"
                                id="remember"
                                type="checkbox"
                                class="h-4 w-4 text-sky-600 focus:ring-sky-500 border-slate-300 rounded"
                            />
                            <label for="remember" class="ml-2 block text-sm text-slate-600 font-normal">
                                Ghi nhớ đăng nhập
                            </label>
                        </div>

                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-sm text-[#1877F2] hover:text-[#166FE5] font-medium underline underline-offset-2">
                                Quên mật khẩu?
                            </a>
                        @endif
                    </div>

                    <!-- Submit Button -->
                    <div>
                        <button
                            type="submit"
                            class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-semibold rounded-xl text-white bg-[#1877F2] hover:bg-[#166FE5] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#1877F2] transition-colors shadow-md"
                        >
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i class="fas fa-sign-in-alt text-blue-100 group-hover:text-white transition-colors"></i>
                            </span>
                            Đăng nhập
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
                            <span class="px-2 bg-white/80 text-slate-500 font-medium">Hoặc đăng nhập với</span>
                        </div>
                    </div>

                    <!-- Social Login Buttons -->
                    <div class="mt-6 grid grid-cols-2 gap-3">
                        <button class="w-full inline-flex justify-center py-2 px-4 border border-slate-300 rounded-xl shadow-sm bg-white text-sm font-medium text-slate-800 hover:bg-slate-50">
                            <i class="fab fa-google text-red-500"></i>
                            <span class="ml-2">Google</span>
                        </button>

                        <button class="w-full inline-flex justify-center py-2 px-4 border border-slate-300 rounded-xl shadow-sm bg-white text-sm font-medium text-slate-800 hover:bg-slate-50">
                            <i class="fab fa-facebook text-blue-600"></i>
                            <span class="ml-2">Facebook</span>
                        </button>
                    </div>
                </div>

                <!-- Sign Up Link -->
                @if (Route::has('register'))
                    <div class="mt-6 text-center">
                        <p class="text-sm text-slate-800 font-normal">
                            Chưa có tài khoản? 
                            <a href="{{ route('register') }}" class="text-[#1877F2] hover:text-[#166FE5] font-medium underline">
                                Đăng ký ngay
                            </a>
                        </p>
                    </div>
                @endif
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
        background: linear-gradient(135deg, #1877F2, #166FE5);
        border-radius: 50%;
        opacity: 0.1;
        animation: float-particle 6s ease-in-out infinite;
    }

    .particle-1 {
        width: 100px;
        height: 100px;
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
        width: 80px;
        height: 80px;
        bottom: 30%;
        left: 20%;
        animation-delay: 2s;
    }

    .particle-4 {
        width: 40px;
        height: 40px;
        top: 40%;
        left: 60%;
        animation-delay: 3s;
    }

    .particle-5 {
        width: 70px;
        height: 70px;
        bottom: 20%;
        right: 25%;
        animation-delay: 4s;
    }

    @keyframes float-particle {
        0%, 100% {
            transform: translateY(0px) rotate(0deg);
            opacity: 0.1;
        }
        50% {
            transform: translateY(-20px) rotate(180deg);
            opacity: 0.2;
        }
    }

    /* Modern Glassmorphism */
    .backdrop-blur-xl {
        backdrop-filter: blur(24px);
    }

    /* Reduce excessive transitions for accessibility */
    .transition-all { transition: all 0.15s ease-in-out; }
    </style>
</div>
