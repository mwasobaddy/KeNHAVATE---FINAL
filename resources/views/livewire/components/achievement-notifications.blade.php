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

<div class="fixed top-4 right-4 z-50 space-y-2" style="max-width: 400px;">
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
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="relative bg-white rounded-lg shadow-2xl border border-[#FFF200]/50 overflow-hidden max-w-sm w-full"
        >
            <!-- Animated background -->
            <div class="absolute inset-0 bg-gradient-to-r from-[#FFF200]/10 to-[#F8EBD5]/20"></div>
            
            <!-- Content -->
            <div class="relative p-4">
                <div class="flex items-start space-x-3">
                    <!-- Icon with animation -->
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 bg-[#FFF200] rounded-full flex items-center justify-center animate-bounce">
                            <span class="text-lg">{{ $this->getNotificationIcon($notification['message']) }}</span>
                        </div>
                    </div>
                    
                    <!-- Message -->
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-[#231F20] mb-1">{{ $notification['title'] }}</p>
                        <p class="text-sm text-[#9B9EA4] leading-relaxed">{{ $notification['message'] }}</p>
                        
                        <!-- Points highlight -->
                        @if($this->getPointsFromMessage($notification['message']) > 0)
                            <div class="mt-2 inline-flex items-center px-2 py-1 rounded-full bg-[#FFF200]/20 text-[#231F20]">
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
                        class="flex-shrink-0 text-[#9B9EA4] hover:text-[#231F20] transition-colors"
                    >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
                
                <!-- Progress bar -->
                <div class="mt-3">
                    <div class="w-full bg-[#9B9EA4]/20 rounded-full h-1">
                        <div 
                            class="bg-[#FFF200] h-1 rounded-full transition-all duration-[5000ms] ease-linear"
                            x-data="{ width: '100%' }"
                            x-init="setTimeout(() => width = '0%', 100)"
                            :style="`width: ${width}`"
                        ></div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <!-- Dismiss All Button (only show if there are notifications) -->
    @if(count($notifications) > 1)
        <div class="text-center mt-4">
            <button 
                wire:click="dismissAll"
                class="text-xs text-[#9B9EA4] hover:text-[#231F20] transition-colors underline"
            >
                Dismiss all notifications
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
