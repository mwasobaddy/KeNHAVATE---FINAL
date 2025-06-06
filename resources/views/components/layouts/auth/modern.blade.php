<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <style>
            /* Custom gradient for the right panel */
            .auth-gradient {
                background: linear-gradient(135deg, #0ea5e9 0%, #06b6d4 50%, #0891b2 100%);
            }
            
            /* 3D geometric shapes animation */
            @keyframes float {
                0%, 100% { transform: translateY(0px) rotate(0deg); }
                50% { transform: translateY(-20px) rotate(180deg); }
            }
            
            @keyframes pulse-glow {
                0%, 100% { opacity: 0.8; }
                50% { opacity: 1; }
            }
            
            .floating-shape {
                animation: float 6s ease-in-out infinite;
            }
            
            .pulse-glow {
                animation: pulse-glow 3s ease-in-out infinite;
            }
        </style>
    </head>
    <body class="min-h-screen bg-gray-50 antialiased font-sans">
        <div class="min-h-screen flex">
            <!-- Left Panel - Authentication Form -->
            <div class="flex-1 flex flex-col justify-center py-12 px-4 sm:px-6 lg:flex-none lg:px-20 xl:px-24 bg-white">
                <div class="mx-auto w-full max-w-sm lg:w-96">
                    <!-- Logo and Brand -->
                    <div class="mb-8">
                        <a href="{{ route('home') }}" class="flex items-center gap-3 text-xl font-bold text-gray-900" wire:navigate>
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-cyan-500">
                                <x-app-logo-icon class="size-6 fill-current text-white" />
                            </div>
                            <span>KeNHAVATE</span>
                        </a>
                        <h2 class="mt-6 text-3xl font-bold tracking-tight text-gray-900">
                            {{ $title ?? 'Welcome back' }}
                        </h2>
                        <p class="mt-2 text-sm text-gray-600">
                            {{ $description ?? 'Sign in to your account to continue' }}
                        </p>
                    </div>

                    <!-- Main Content -->
                    <div class="space-y-6">
                        {{ $slot }}
                    </div>

                    <!-- Footer Links -->
                    <div class="mt-8 text-center">
                        <div class="flex items-center justify-center space-x-4 text-sm text-gray-500">
                            <a href="#" class="hover:text-gray-700 transition-colors">Help</a>
                            <span>•</span>
                            <a href="#" class="hover:text-gray-700 transition-colors">Privacy</a>
                            <span>•</span>
                            <a href="#" class="hover:text-gray-700 transition-colors">Terms</a>
                        </div>
                        <p class="mt-4 text-xs text-gray-400">
                            © {{ date('Y') }} Kenya National Highways Authority. All rights reserved.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Right Panel - Branded Content -->
            <div class="hidden lg:block relative w-0 flex-1">
                <div class="auth-gradient absolute inset-0 h-full w-full">
                    <!-- Background Pattern -->
                    <div class="absolute inset-0 opacity-10">
                        <svg class="h-full w-full" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
                            <defs>
                                <pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse">
                                    <path d="M 10 0 L 0 0 0 10" fill="none" stroke="white" stroke-width="0.5"/>
                                </pattern>
                            </defs>
                            <rect width="100" height="100" fill="url(#grid)" />
                        </svg>
                    </div>

                    <!-- Content Container -->
                    <div class="relative z-10 flex h-full flex-col justify-center px-12 xl:px-16">
                        <!-- Main Headline -->
                        <div class="max-w-md">
                            <h1 class="text-4xl font-bold text-white leading-tight">
                                Innovation Revolutionizing the way we
                                <span class="text-cyan-200">create, collaborate, and experience</span>
                                solutions.
                            </h1>
                            <p class="mt-6 text-lg text-cyan-100">
                                Streamline innovation management with AI-powered insights, collaborative workflows, and data-driven decision making.
                            </p>
                        </div>

                        <!-- 3D Geometric Illustration -->
                        <div class="mt-12 relative">
                            <!-- Main 3D Container -->
                            <div class="relative w-72 h-48 mx-auto">
                                <!-- Large Cube -->
                                <div class="floating-shape absolute top-0 left-8 w-20 h-20 bg-white bg-opacity-20 rounded-lg transform rotate-12" style="animation-delay: 0s;">
                                    <div class="absolute inset-2 bg-white bg-opacity-30 rounded-md"></div>
                                </div>
                                
                                <!-- Medium Cube -->
                                <div class="floating-shape absolute top-8 right-4 w-16 h-16 bg-cyan-200 bg-opacity-40 rounded-lg transform -rotate-12" style="animation-delay: 1s;">
                                    <div class="absolute inset-2 bg-white bg-opacity-20 rounded-md"></div>
                                </div>
                                
                                <!-- Small Cube -->
                                <div class="floating-shape absolute bottom-4 left-12 w-12 h-12 bg-white bg-opacity-30 rounded-lg transform rotate-45" style="animation-delay: 2s;">
                                </div>
                                
                                <!-- Connecting Lines -->
                                <svg class="absolute inset-0 w-full h-full pulse-glow" viewBox="0 0 288 192">
                                    <line x1="60" y1="40" x2="200" y2="80" stroke="rgba(255,255,255,0.3)" stroke-width="1" stroke-dasharray="4,4"/>
                                    <line x1="200" y1="80" x2="120" y2="140" stroke="rgba(255,255,255,0.3)" stroke-width="1" stroke-dasharray="4,4"/>
                                </svg>
                            </div>
                        </div>

                        <!-- Status Indicator -->
                        <div class="mt-8 flex items-center space-x-3">
                            <div class="flex items-center justify-center w-12 h-12 bg-white bg-opacity-20 rounded-full">
                                <div class="w-6 h-6 bg-green-400 rounded-full pulse-glow flex items-center justify-center">
                                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            </div>
                            <div>
                                <div class="text-white font-medium">Innovating</div>
                                <div class="text-cyan-200 text-sm">Connecting ideas with impact</div>
                            </div>
                        </div>

                        <!-- User Testimonial -->
                        <div class="mt-12 bg-white bg-opacity-10 backdrop-blur-sm rounded-xl p-6">
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full flex items-center justify-center">
                                        <span class="text-white font-semibold text-sm">KN</span>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <p class="text-white text-sm leading-relaxed">
                                        "KeNHAVATE has transformed how we approach innovation. The collaborative features and streamlined workflows have increased our project success rate by 40%."
                                    </p>
                                    <div class="mt-3">
                                        <div class="text-cyan-200 font-medium text-sm">Dr. Sarah Kimani</div>
                                        <div class="text-cyan-300 text-xs">Innovation Manager, KeNHA</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Stats -->
                        <div class="mt-8 grid grid-cols-2 gap-6">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-white">500+</div>
                                <div class="text-cyan-200 text-sm">Ideas Submitted</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-white">98%</div>
                                <div class="text-cyan-200 text-sm">User Satisfaction</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
