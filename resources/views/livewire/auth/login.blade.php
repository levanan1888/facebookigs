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

<div class="min-h-screen bg-gray-50 flex flex-col font-sans">
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

        <!-- Right Section - Login Form -->
        <div class="flex-1 flex items-center justify-center px-8 py-12">
            <div class="w-full max-w-md">
                <!-- Login Form Card -->
                <div class="bg-white rounded-lg shadow-lg p-8 border border-gray-200">
                    <form method="POST" wire:submit="login" class="space-y-4">
                        <!-- Email/Phone Input -->
                        <div>
                            <input
                                wire:model="email"
                                id="email"
                                type="email"
                                required
                                autofocus
                                autocomplete="email"
                                placeholder="Email hoặc số điện thoại"
                                class="block w-full px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
                                autocomplete="current-password"
                                placeholder="Mật khẩu"
                                class="block w-full px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                            @error('password')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Login Button -->
                        <div>
                            <button
                                type="submit"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-base py-3 px-4 rounded-lg transition-colors duration-200"
                            >
                                Đăng nhập
                            </button>
                        </div>

                        <!-- Forgot Password Link -->
                        <div class="text-center">
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="text-blue-600 hover:text-blue-700 text-sm">
                                    Quên mật khẩu?
                                </a>
                            @endif
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

                    <!-- Create Account Button -->
                    @if (Route::has('register'))
                        <div class="mt-6">
                            <a href="{{ route('register') }}" 
                               class="w-full bg-green-500 hover:bg-green-600 text-white font-bold text-base py-3 px-4 rounded-lg transition-colors duration-200 inline-block text-center">
                                Tạo tài khoản mới
                            </a>
                        </div>
                    @endif
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
</div>
