<?php

use Livewire\Volt\Component;
use App\Models\AppNotification;

new class extends Component
{
    public array $notifications = [];
    public array $shownNotifications = [];

    public function mount()
    {
        $this->loadPointNotifications();
    }

    public function loadPointNotifications()
    {
        $this->notifications = AppNotification::where('user_id', auth()->id())
            ->where('type', 'points_awarded')
            ->whereNull('read_at')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
    }

    public function showNotification($notificationId)
    {
        $this->shownNotifications[] = $notificationId;
        
        // Mark as read after 5 seconds
        $this->dispatch('mark-notification-read', notificationId: $notificationId);
    }

    public function markAsRead($notificationId)
    {
        AppNotification::where('id', $notificationId)
            ->update(['read_at' => now()]);
            
        $this->loadPointNotifications();
    }

    public function dismissAll()
    {
        AppNotification::where('user_id', auth()->id())
            ->where('type', 'points_awarded')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
            
        $this->notifications = [];
        $this->shownNotifications = [];
    }

    public function getPointsFromMessage(string $message): int
    {
        if (preg_match('/(\d+)\s+points/', $message, $matches)) {
            return (int) $matches[1];
        }
        return 0;
    }

    public function getNotificationIcon(string $message): string
    {
        if (str_contains($message, 'streak')) {
            return '🔥';
        } elseif (str_contains($message, 'review')) {
            return '⭐';
        } elseif (str_contains($message, 'challenge')) {
            return '🏆';
        } elseif (str_contains($message, 'collaboration')) {
            return '🤝';
        } elseif (str_contains($message, 'idea')) {
            return '💡';
        }
        return '🎉';
    }
}; ?>

<div class="fixed top-4 right-4 z-50 space-y-3" style="max-width: 400px;">
    @foreach($notifications as $notification)
        <div 
            wire:key="notification-{{ $notification['id'] }}"
            x-data="{ 
                show: false, 
                init() { 
                    setTimeout(() => this.show = true, 100);
                    setTimeout(() => {
                        this.show = false;
                        setTimeout(() => $wire.markAsRead({{ $notification['id'] }}), 300);
                    }, 5000);
                }
            }"
            x-show="show"
            x-transition:enter="transform ease-out duration-300 transition"
            x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl transform transition-all duration-500 hover:shadow-2xl hover:-translate-y-1 max-w-sm w-full"
        >
            <!-- Animated gradient background -->
            <div class="absolute inset-0 bg-gradient-to-r from-[#FFF200]/10 via-transparent to-[#F8EBD5]/20 dark:from-yellow-400/10 dark:via-transparent dark:to-amber-400/10"></div>
            
            <!-- Content -->
            <div class="relative p-4">
                <div class="flex items-start space-x-4">
                    <!-- Icon with animation -->
                    <div class="flex-shrink-0">
                        <div class="relative">
                            <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                                <span class="text-xl text-[#231F20] dark:text-zinc-900">{{ $this->getNotificationIcon($notification['message']) }}</span>
                            </div>
                            <div class="absolute -inset-2 bg-[#FFF200]/30 dark:bg-yellow-400/20 rounded-2xl blur-xl opacity-50"></div>
                        </div>
                    </div>
                    
                    <!-- Message -->
                    <div class="flex-1 min-w-0">
                        <p class="text-base font-bold text-[#231F20] dark:text-zinc-100 mb-1">{{ $notification['title'] }}</p>
                        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 leading-relaxed">{{ $notification['message'] }}</p>
                        
                        <!-- Points highlight -->
                        @if($this->getPointsFromMessage($notification['message']) > 0)
                            <div class="mt-2 inline-flex items-center px-3 py-1.5 rounded-full bg-[#FFF200]/20 dark:bg-yellow-400/20 text-[#231F20] dark:text-yellow-400 border border-[#FFF200]/30 dark:border-yellow-400/30">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                <span class="text-xs font-bold">+{{ $this->getPointsFromMessage($notification['message']) }} points</span>
                            </div>
                        @endif
                    </div>
                    
                    <!-- Close button -->
                    <button 
                        @click="show = false; setTimeout(() => $wire.markAsRead({{ $notification['id'] }}), 300)"
                        class="flex-shrink-0 text-[#9B9EA4] dark:text-zinc-400 hover:text-[#231F20] dark:hover:text-white p-1 hover:bg-white/30 dark:hover:bg-zinc-700/30 rounded-full transition-all duration-300"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <!-- Enhanced progress bar -->
                <div class="mt-3">
                    <div class="w-full bg-white/30 dark:bg-zinc-700/30 rounded-full h-1.5 overflow-hidden">
                        <div 
                            class="bg-gradient-to-r from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 h-1.5 rounded-full transition-all duration-[5000ms] ease-linear"
                            x-data="{ width: '100%' }"
                            x-init="setTimeout(() => width = '0%', 100)"
                            :style="`width: ${width}`"
                        ></div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <!-- Enhanced Dismiss All Button -->
    @if(count($notifications) > 1)
        <div class="text-center mt-4 opacity-90 hover:opacity-100 transition-opacity">
            <button 
                wire:click="dismissAll"
                class="group/btn inline-flex items-center space-x-2 text-sm text-[#9B9EA4] dark:text-zinc-400 hover:text-[#231F20] dark:hover:text-white font-medium bg-white/50 dark:bg-zinc-800/50 hover:bg-white/70 dark:hover:bg-zinc-700/50 backdrop-blur-sm px-4 py-2 rounded-xl shadow-md hover:shadow-lg border border-white/20 dark:border-zinc-700/50 transition-all duration-300"
            >
                <span>Dismiss all notifications</span>
                <svg class="w-4 h-4 transform group-hover/btn:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                </svg>
            </button>
        </div>
    @endif
</div>

@script
<script>
    $wire.on('mark-notification-read', (data) => {
        setTimeout(() => {
            $wire.markAsRead(data.notificationId);
        }, 5000);
    });
    
    // Listen for new point notifications from Livewire events
    window.addEventListener('points-awarded', (event) => {
        $wire.loadPointNotifications();
    });
</script>
@endscript
