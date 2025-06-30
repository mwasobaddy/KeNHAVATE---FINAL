<?php

use Livewire\Volt\Component;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\{Layout, Title};

new #[Layout('components.layouts.app')] #[Title('Terms and Conditions')] class extends Component
    public bool $hasScrolledToBottom = false;
    public bool $termsAccepted = false;
    public bool $isLoading = false;
    
    protected AuditService $auditService;

    public function boot(AuditService $auditService): void
    {
        $this->auditService = $auditService;
    }

    public function mount(): void
    {
        // Check if user has already accepted terms
        if (Auth::check() && Auth::user()->terms_accepted) {
            // Redirect to dashboard if already accepted
            $this->redirectToUserDashboard();
        }
    }

    public function markScrolledToBottom(): void
    {
        $this->hasScrolledToBottom = true;
    }

    public function acceptTerms(): void
    {
        if (!$this->hasScrolledToBottom) {
            $this->addError('terms', 'Please read the complete terms and conditions before accepting.');
            return;
        }

        if (!$this->termsAccepted) {
            $this->addError('terms', 'You must accept the terms and conditions to continue.');
            return;
        }

        $this->isLoading = true;

        try {
            $user = Auth::user();
            
            // Update user's terms acceptance
            $user->update(['terms_accepted' => true]);

            // Create audit log
            $this->auditService->log(
                'terms_accepted',
                'user',
                $user->id,
                ['terms_accepted' => false],
                ['terms_accepted' => true]
            );

            // Redirect to appropriate dashboard
            $this->redirectToUserDashboard();

        } catch (\Exception $e) {
            $this->isLoading = false;
            $this->addError('terms', 'An error occurred while processing your request. Please try again.');
        }
    }

    public function disagreeTerms(): void
    {
        // Log disagreement
        if (Auth::check()) {
            $this->auditService->log(
                'terms_disagreed',
                'user',
                Auth::id(),
                null,
                ['action' => 'terms_disagreed', 'redirect' => 'login']
            );
        }

        // Logout and redirect to login
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        
        $this->redirect(route('login'), navigate: true);
    }

    private function redirectToUserDashboard(): void
    {
        $user = Auth::user();
        $userRole = $user->roles->first()?->name ?? 'user';
        
        $redirectRoute = match($userRole) {
            'developer', 'administrator' => route('dashboard.admin'),
            'board_member' => route('dashboard.board-member'),
            'manager' => route('dashboard.manager'),
            'sme' => route('dashboard.sme'),
            'challenge_reviewer' => route('dashboard.challenge-reviewer'),
            default => route('dashboard.user'),
        };

        $this->redirect($redirectRoute, navigate: true);
    }
}; ?>

<div class="min-h-screen bg-gradient-to-br from-[#F8EBD5] via-[#F8EBD5]/90 to-[#F8EBD5]/80 dark:from-zinc-900 dark:via-zinc-800/90 dark:to-zinc-900/80 flex items-center justify-center p-4">
    <div class="w-full max-w-4xl">
        <!-- Header Card -->
        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl rounded-2xl border border-white/20 dark:border-zinc-700/50 shadow-2xl p-8 mb-6">
            <div class="text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-[#FFF200]/20 dark:bg-yellow-400/20 mb-4">
                    <svg class="h-8 w-8 text-[#FFF200] dark:text-yellow-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h4.125m11.25-8.25l-4.5 4.5m0 0l-4.5-4.5m9 .75V8.25" />
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-[#231F20] dark:text-white mb-2">Terms and Conditions</h1>
                <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg">KeNHAVATE Innovation Portal</p>
                <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 mt-2">Please read and accept our terms to continue</p>
            </div>
        </div>

        <!-- Terms Content Card -->
        <div class="bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl rounded-2xl border border-white/20 dark:border-zinc-700/50 shadow-2xl overflow-hidden">
            <!-- Scrollable Terms Content -->
            <div 
                class="h-96 overflow-y-auto p-6 text-sm text-[#231F20] dark:text-zinc-300 leading-relaxed"
                x-data="{ 
                    scrollToBottom: false,
                    checkScroll() {
                        const element = this.$el;
                        const isAtBottom = element.scrollTop + element.clientHeight >= element.scrollHeight - 10;
                        if (isAtBottom && !this.scrollToBottom) {
                            this.scrollToBottom = true;
                            $wire.markScrolledToBottom();
                        }
                    }
                }"
                @scroll="checkScroll()"
            >
                <!-- Terms Content from TERMS.MD -->
                <div class="prose prose-sm dark:prose-invert max-w-none">
                    <p class="text-xs text-[#9B9EA4] dark:text-zinc-400 mb-4">Last Revised: June, 2023</p>
                    
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-[#FFF200] dark:border-yellow-400 p-4 mb-6">
                        <p class="font-semibold text-[#231F20] dark:text-white">IMPORTANT NOTICE</p>
                        <p class="text-sm">PLEASE READ THESE TERMS OF USE CAREFULLY. ACCESSING OR USING THIS WEBSITE CONSTITUTES ACCEPTANCE OF THESE TERMS OF USE ("TERMS"), AS SUCH MAY BE REVISED BY KeNHA FROM TIME TO TIME, AND IS A BINDING AGREEMENT BETWEEN YOU, THE USER ("USER") AND KENYA NATIONAL HIGHWAYS AUTHORITY ("KeNHA") GOVERNING THE USE OF THE WEBSITE.</p>
                    </div>

                    <h3 class="text-lg font-semibold text-[#231F20] dark:text-white mb-3">1. LIMITATION OF LIABILITY</h3>
                    <p class="mb-4">These Terms apply to your access to, and use of, all or part of any website or mobile application of KeNHA or its Contractors, Consultants, Service Providers and affiliates (collectively, "KeNHA"), and any other site, mobile application or online service where these Terms are posted (collectively, the "Sites"). These Terms do not alter in any way the terms or conditions of any other agreement you may have with KeNHA for products, services or otherwise.</p>
                    
                    <p class="mb-4">It is required by Law that all engagements on this site shall be governed by the provisions of the Access to Information Act and the Data Protection Act, Laws of Kenya. In the event there is any conflict or inconsistency between these Terms and any other terms of use that appear on the Sites, these Terms will govern.</p>

                    <h3 class="text-lg font-semibold text-[#231F20] dark:text-white mb-3">2. ELIGIBILITY</h3>
                    <p class="mb-4">The Sites are not targeted towards, nor intended for use by, anyone under the age of 18. <strong>A USER MUST BE AT LEAST AGE 18 TO ACCESS AND USE THE SITES.</strong> If the User is between the ages of 13 and 18, he or she may only use the Sites under the supervision of a parent or legal guardian who agrees to be bound by these Terms.</p>
                    
                    <p class="mb-4">User represents and warrants that:</p>
                    <ul class="list-disc list-inside mb-4 space-y-1">
                        <li>He/she is not located in a country that is subject to a Kenya government embargo</li>
                        <li>He/she is not listed on any Kenya government list of prohibited or restricted parties</li>
                    </ul>

                    <h4 class="font-semibold text-[#231F20] dark:text-white mb-2">Account Requirements</h4>
                    <p class="mb-2">You agree to:</p>
                    <ol class="list-decimal list-inside mb-4 space-y-1">
                        <li>Create only one account</li>
                        <li>Provide accurate, truthful, current and complete information when creating your account</li>
                        <li>Maintain and promptly update your account information</li>
                        <li>Maintain the security of your account by not sharing your password with others</li>
                        <li>Promptly notify KeNHA if you discover any security breaches</li>
                        <li>Take responsibility for all activities that occur under your account</li>
                    </ol>

                    <h3 class="text-lg font-semibold text-[#231F20] dark:text-white mb-3">3. PRIVACY</h3>
                    <p class="mb-4">Please read the Privacy Policy carefully to understand how KeNHA collects, uses and discloses personally identifiable information from its users. By accessing or using the Sites, you consent to all actions that we take with respect to your data consistent with our Privacy Policy.</p>

                    <h3 class="text-lg font-semibold text-[#231F20] dark:text-white mb-3">4. EMAIL COMMUNICATIONS</h3>
                    <p class="mb-4">If a User signs up for a KeNHA account on the Sites, the User is, by default, opted in to receive promotional email communications from KeNHA ("Email Communications"). The User may opt out of receiving Email Communications by adjusting the User's profile settings in the User's KeNHA account via www.kenha.co.ke.</p>

                    <h3 class="text-lg font-semibold text-[#231F20] dark:text-white mb-3">5. INTELLECTUAL PROPERTY</h3>
                    <p class="mb-4">Unless otherwise indicated, the Sites and all content and other materials therein, including the KeNHA logo and all designs, text, graphics, pictures, information, data, software, sound files, other files and the selection and arrangement thereof (collectively, "Site Materials") are the property of KeNHA or its licensors and are protected by KENYA and international copyright laws.</p>

                    <h3 class="text-lg font-semibold text-[#231F20] dark:text-white mb-3">6. ACCEPTABLE USE</h3>
                    <p class="mb-2">User's use of the Sites is limited to the contemplated functionality. In no event may the Sites be used in a manner that:</p>
                    <ul class="list-disc list-inside mb-4 space-y-1">
                        <li>Harasses, abuses, stalks, threatens, defames others</li>
                        <li>Is unlawful, fraudulent, or deceptive</li>
                        <li>Provides sensitive personal information unless specifically requested</li>
                        <li>Includes spam or unsolicited advertising</li>
                        <li>Uses unauthorized technology to access KeNHA or Content</li>
                        <li>Attempts to introduce viruses or malicious code</li>
                        <li>Attempts to gain unauthorized access to systems</li>
                        <li>Impersonates any person or entity</li>
                    </ul>

                    <h3 class="text-lg font-semibold text-[#231F20] dark:text-white mb-3">7. SUBMISSION OF IDEAS</h3>
                    <p class="mb-4">You may submit questions, comments, feedback, suggestions, ideas, improvements, plans, notes, drawings, original or creative materials or other information about KeNHA, our Sites and our products (collectively, "Ideas"). The Ideas you submit are voluntary, non-confidential, gratuitous and non-committal.</p>

                    <p class="mb-4">By submitting your Idea, you grant KeNHA and its designees a worldwide, perpetual, irrevocable, non-exclusive, fully-paid up and royalty free license to use, sell, reproduce, prepare derivative works, combine with other works, alter, translate, distribute copies, display, perform, publish, license or sub-license the Idea.</p>

                    <div class="bg-[#F8EBD5] dark:bg-zinc-700/50 border border-[#FFF200] dark:border-yellow-400 rounded-lg p-4 mt-6">
                        <p class="text-sm font-semibold text-[#231F20] dark:text-white">Contact Information</p>
                        <p class="text-sm text-[#9B9EA4] dark:text-zinc-300 mt-1">
                            Director, Policy, Research and Compliance<br>
                            Block C, 4th Floor, Barabara Plaza<br>
                            Mazao Road, JKIA Airport<br>
                            Nairobi, Kenya<br>
                            Email: dpsc@kenha.co.ke
                        </p>
                    </div>
                </div>
            </div>

            <!-- Scroll Indicator -->
            <div class="px-6 py-2 bg-[#F8EBD5]/50 dark:bg-zinc-700/50 border-t border-[#9B9EA4]/20 dark:border-zinc-600/50">
                <div class="flex items-center justify-center">
                    @if (!$hasScrolledToBottom)
                        <div class="flex items-center text-xs text-[#9B9EA4] dark:text-zinc-400">
                            <svg class="h-4 w-4 mr-1 animate-bounce" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" />
                            </svg>
                            Please scroll to read all terms and conditions
                        </div>
                    @else
                        <div class="flex items-center text-xs text-[#FFF200] dark:text-yellow-400">
                            <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            You have read all terms and conditions
                        </div>
                    @endif
                </div>
            </div>

            <!-- Agreement Section -->
            <div class="p-6 bg-white/50 dark:bg-zinc-900/50 border-t border-[#9B9EA4]/20 dark:border-zinc-600/50">
                <!-- Agreement Checkbox -->
                <div class="mb-6">
                    <label class="flex items-start space-x-3 cursor-pointer">
                        <input 
                            type="checkbox" 
                            wire:model.live="termsAccepted"
                            @if(!$hasScrolledToBottom) disabled @endif
                            class="mt-1 h-4 w-4 rounded border-[#9B9EA4] text-[#FFF200] focus:ring-[#FFF200] focus:ring-offset-0 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                        <span class="text-sm text-[#231F20] dark:text-white @if(!$hasScrolledToBottom) opacity-50 @endif">
                            I have read, understood, and agree to be bound by the Terms and Conditions of the KeNHAVATE Innovation Portal.
                        </span>
                    </label>
                    @error('terms') 
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4">
                    <flux:button 
                        wire:click="disagreeTerms" 
                        variant="outline" 
                        class="flex-1 justify-center rounded-lg border border-[#9B9EA4] dark:border-zinc-600 px-6 py-3 text-sm font-semibold text-[#231F20] dark:text-white shadow-sm hover:bg-[#9B9EA4]/10 dark:hover:bg-zinc-700/50 transition-all duration-200"
                    >
                        Disagree & Return to Login
                    </flux:button>
                    
                    <flux:button 
                        wire:click="acceptTerms" 
                        variant="primary"
                        :disabled="!$hasScrolledToBottom || !$termsAccepted || $isLoading"
                        class="flex-1 justify-center rounded-lg bg-[#FFF200] dark:bg-yellow-400 px-6 py-3 text-sm font-semibold text-[#231F20] dark:text-zinc-900 shadow-lg hover:bg-[#FFF200]/90 dark:hover:bg-yellow-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#FFF200] dark:focus-visible:outline-yellow-400 transition-all duration-200 hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        @if ($isLoading)
                            <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-[#231F20]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processing...
                        @else
                            I Agree & Continue
                        @endif
                    </flux:button>
                </div>

                <!-- Legal Notice -->
                <div class="mt-6 text-center">
                    <p class="text-xs text-[#9B9EA4] dark:text-zinc-400">
                        By accepting these terms, you acknowledge that you have read and understood all provisions and agree to be legally bound by them.
                    </p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6">
            <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">
                KeNHAVATE Innovation Portal • Kenya National Highways Authority
            </p>
        </div>
    </div>
</div>
