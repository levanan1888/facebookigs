<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Str;
use Illuminate\Validation\ValidationException;
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
        Session::regenerate();

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
    <!-- Background Video/Animation -->
    <div class="fixed inset-0 z-0 overflow-hidden">
        <!-- Dragon Animation -->
        <div class="absolute inset-0">
            <div class="dragon-container">
                <div class="dragon-body">
                    <div class="dragon-head">🐉</div>
                    <div class="dragon-tail">✨</div>
                </div>
            </div>
        </div>
        
        <!-- Gradient Background -->
        <div class="absolute inset-0 bg-gradient-to-br from-blue-50 via-white to-blue-100"></div>
    </div>

    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="max-w-md w-full space-y-8">
            <!-- Logo and Header -->
            <div class="text-center">
                <div class="mx-auto h-20 w-20 flex items-center justify-center rounded-full bg-gradient-to-r from-blue-600 to-blue-700 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                    <i class="fab fa-facebook text-white text-3xl"></i>
                </div>
                <h2 class="mt-6 text-3xl font-bold text-blue-800">
                    Chào mừng trở lại
                </h2>
                <p class="mt-2 text-lg text-blue-700 font-medium">
                    Đăng nhập vào tài khoản của bạn
                </p>
            </div>

            <!-- Login Form -->
            <div class="bg-white py-8 px-6 shadow-2xl rounded-2xl border border-blue-200">
                <!-- Session Status -->
                <x-auth-session-status class="mb-4 text-center" :status="session('status')" />

                <form method="POST" wire:submit="login" class="space-y-6">
                    <!-- Email Address -->
                    <div>
                        <label for="email" class="block text-sm font-semibold text-blue-800 mb-2">
                            Địa chỉ email
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-envelope text-blue-600"></i>
                            </div>
                            <input
                                wire:model="email"
                                id="email"
                                type="email"
                                required
                                autofocus
                                autocomplete="email"
                                placeholder="Nhập email của bạn"
                                class="block w-full pl-10 pr-3 py-3 border-2 border-blue-300 rounded-lg shadow-sm placeholder-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-white text-blue-900 font-medium"
                            />
                        </div>
                        @error('email')
                            <p class="mt-1 text-sm text-red-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-semibold text-blue-800 mb-2">
                            Mật khẩu
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-blue-600"></i>
                            </div>
                            <input
                                wire:model="password"
                                id="password"
                                type="password"
                                required
                                autocomplete="current-password"
                                placeholder="Nhập mật khẩu của bạn"
                                class="block w-full pl-10 pr-3 py-3 border-2 border-blue-300 rounded-lg shadow-sm placeholder-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-white text-blue-900 font-medium"
                            />
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
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-blue-300 rounded"
                            />
                            <label for="remember" class="ml-2 block text-sm text-blue-700 font-medium">
                                Ghi nhớ đăng nhập
                            </label>
                        </div>

                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-sm text-blue-600 hover:text-blue-700 font-semibold transition-colors duration-200">
                                Quên mật khẩu?
                            </a>
                        @endif
                    </div>

                    <!-- Submit Button -->
                    <div>
                        <button
                            type="submit"
                            class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-semibold rounded-lg text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:scale-105"
                        >
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i class="fas fa-sign-in-alt text-blue-200 group-hover:text-blue-100 transition-colors duration-200"></i>
                            </span>
                            Đăng nhập
                        </button>
                    </div>
                </form>

                <!-- Divider -->
                <div class="mt-6">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-blue-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-blue-600 font-medium">Hoặc đăng nhập với</span>
                        </div>
                    </div>

                    <!-- Social Login Buttons -->
                    <div class="mt-6 grid grid-cols-2 gap-3">
                        <button class="w-full inline-flex justify-center py-2 px-4 border-2 border-blue-300 rounded-lg shadow-sm bg-white text-sm font-medium text-blue-700 hover:bg-blue-50 hover:border-blue-400 transition-all duration-200 transform hover:scale-105">
                            <i class="fab fa-google text-red-500"></i>
                            <span class="ml-2">Google</span>
                        </button>

                        <button class="w-full inline-flex justify-center py-2 px-4 border-2 border-blue-300 rounded-lg shadow-sm bg-white text-sm font-medium text-blue-700 hover:bg-blue-50 hover:border-blue-400 transition-all duration-200 transform hover:scale-105">
                            <i class="fab fa-facebook text-blue-600"></i>
                            <span class="ml-2">Facebook</span>
                        </button>
                    </div>
                </div>

                <!-- Sign Up Link -->
                @if (Route::has('register'))
                    <div class="mt-6 text-center">
                        <p class="text-sm text-blue-700 font-medium">
                            Chưa có tài khoản? 
                            <a href="{{ route('register') }}" class="text-blue-600 hover:text-blue-700 font-semibold underline transition-colors duration-200">
                                Đăng ký ngay
                            </a>
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <style>
    /* Dragon Animation */
    .dragon-container {
        position: absolute;
        width: 100%;
        height: 100%;
        overflow: hidden;
    }

    .dragon-body {
        position: absolute;
        top: 50%;
        left: -100px;
        animation: dragon-fly 15s linear infinite;
    }

    .dragon-head {
        font-size: 4rem;
        animation: dragon-breathe 2s ease-in-out infinite;
    }

    .dragon-tail {
        font-size: 2rem;
        position: absolute;
        top: -20px;
        right: -30px;
        animation: tail-wag 1s ease-in-out infinite;
    }

    @keyframes dragon-fly {
        0% {
            left: -100px;
            transform: translateY(-50%) rotate(0deg);
        }
        25% {
            transform: translateY(-50%) rotate(5deg);
        }
        50% {
            left: 50%;
            transform: translateY(-50%) rotate(0deg);
        }
        75% {
            transform: translateY(-50%) rotate(-5deg);
        }
        100% {
            left: calc(100% + 100px);
            transform: translateY(-50%) rotate(0deg);
        }
    }

    @keyframes dragon-breathe {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }

    @keyframes tail-wag {
        0%, 100% { transform: rotate(0deg); }
        50% { transform: rotate(20deg); }
    }
    </style>
</div>
