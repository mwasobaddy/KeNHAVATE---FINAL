<?php

use Livewire\Volt\Component;
use App\Models\Challenge;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Artesaos\SEOTools\Facades\SEOTools;
use Livewire\Attributes\{Layout, Title};

new #[Layout('components.layouts.app')] #[Title('Edit Challenge')] class extends Component
{
    public Challenge $challenge;
    public string $title = '';
    public string $description = '';
    public string $category = '';
    public string $prize_description = '';
    public string $deadline = '';
    public string $judging_criteria = '';
    public array $requirements = [''];
    public string $status = 'draft';
    
    public function mount(Challenge $challenge)
    {
        $this->challenge = $challenge;
        
        // Authorize user
        $this->authorize('update', $challenge);
        
        // Populate form with existing data
        $this->title = $challenge->title;
        $this->description = $challenge->description;
        $this->category = $challenge->category;
        $this->prize_description = $challenge->prize_description ?? '';
        $this->deadline = $challenge->deadline ? Carbon::parse($challenge->deadline)->format('Y-m-d') : '';
        $this->judging_criteria = $challenge->judging_criteria;
        $this->requirements = $challenge->requirements ?: [''];
        $this->status = $challenge->status;
        
        // Set SEO meta tags
        SEOTools::setTitle('Edit Challenge - ' . $challenge->title . ' - KeNHAVATE Innovation Portal');
        SEOTools::setDescription('Edit the ' . $challenge->title . ' innovation challenge.');
    }
    
    public function addRequirement()
    {
        $this->requirements[] = '';
    }
    
    public function removeRequirement($index)
    {
        if (count($this->requirements) > 1) {
            unset($this->requirements[$index]);
            $this->requirements = array_values($this->requirements);
        }
    }
    
    public function rules()
    {
        return [
            'title' => ['required', 'string', 'max:255', 'min:10'],
            'description' => ['required', 'string', 'min:50'],
            'category' => ['required', 'string', Rule::in(['technology', 'sustainability', 'safety', 'innovation', 'infrastructure', 'efficiency'])],
            'prize_description' => ['nullable', 'string', 'max:500'],
            'deadline' => ['required', 'date', 'after:' . now()->toDateString()],
            'judging_criteria' => ['required', 'string', 'min:20'],
            'requirements' => ['required', 'array', 'min:1'],
            'requirements.*' => ['required', 'string', 'min:5'],
            'status' => ['required', 'string', Rule::in(['draft', 'active', 'judging', 'completed', 'cancelled'])],
        ];
    }
    
    public function validationAttributes()
    {
        return [
            'title' => 'challenge title',
            'description' => 'challenge description',
            'category' => 'challenge category',
            'prize_description' => 'prize description',
            'deadline' => 'submission deadline',
            'judging_criteria' => 'judging criteria',
            'requirements' => 'challenge requirements',
            'requirements.*' => 'requirement',
        ];
    }
    
    public function save()
    {
        $validated = $this->validate();
        
        // Filter out empty requirements
        $validated['requirements'] = array_filter($validated['requirements'], fn($req) => !empty(trim($req)));
        
        // Store old values for audit
        $oldValues = $this->challenge->toArray();
        
        // Update the challenge
        $this->challenge->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'category' => $validated['category'],
            'prize_description' => $validated['prize_description'] ?: null,
            'deadline' => $validated['deadline'],
            'judging_criteria' => $validated['judging_criteria'],
            'requirements' => $validated['requirements'],
            'status' => $validated['status'],
        ]);
        
        // Log the action
        app(App\Services\AuditService::class)->log(
            'challenge_update',
            'Challenge',
            $this->challenge->id,
            $oldValues,
            $this->challenge->fresh()->toArray()
        );
        
        // Send notification if status changed to active
        if ($oldValues['status'] !== 'active' && $validated['status'] === 'active') {
            app(App\Services\NotificationService::class)->sendToRoles(
                ['user', 'manager', 'sme'],
                'challenge_published',
                [
                    'title' => 'New Challenge Published',
                    'message' => "Challenge '{$this->challenge->title}' is now open for submissions!",
                    'related_id' => $this->challenge->id,
                    'related_type' => 'Challenge',
                ]
            );
        }
        
        session()->flash('success', 'Challenge updated successfully!');
        
        return redirect()->route('challenges.show', $this->challenge);
    }
    
    public function delete()
    {
        $this->authorize('delete', $this->challenge);
        
        // Check if challenge has submissions
        if ($this->challenge->submissions()->count() > 0) {
            session()->flash('error', 'Cannot delete challenge with existing submissions.');
            return;
        }
        
        // Log the action before deletion
        app(App\Services\AuditService::class)->log(
            'challenge_deletion',
            'Challenge',
            $this->challenge->id,
            $this->challenge->toArray(),
            null
        );
        
        $challengeTitle = $this->challenge->title;
        $this->challenge->delete();
        
        session()->flash('success', "Challenge '{$challengeTitle}' deleted successfully.");
        
        return redirect()->route('challenges.index');
    }
    
    public function canDelete()
    {
        return auth()->user()->can('delete', $this->challenge) && 
               $this->challenge->submissions()->count() === 0;
    }
}; ?>

{{-- Modern Challenge Edit Form with Glass Morphism & Enhanced UI --}}
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/80 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/50 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 max-w-7xl mx-auto p-6 space-y-8">
        {{-- Enhanced Header with Navigation --}}
        <section class="group">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                <div class="flex items-center gap-4">
                    <flux:button 
                        wire:navigate 
                        href="{{ route('challenges.show', $challenge) }}" 
                        class="group/btn relative overflow-hidden rounded-2xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-4 py-2"
                    >
                        <div class="absolute inset-0 bg-gradient-to-br from-blue-500/10 to-blue-600/20 dark:from-blue-400/20 dark:to-blue-500/30 opacity-0 group-hover/btn:opacity-100 transition-opacity duration-300"></div>
                        <div class="relative flex items-center space-x-2">
                            <flux:icon.arrow-left class="w-5 h-5 text-[#9B9EA4] group-hover/btn:text-blue-600 dark:group-hover/btn:text-blue-400 transition-colors duration-300" />
                            <span class="text-[#9B9EA4] group-hover/btn:text-blue-600 dark:group-hover/btn:text-blue-400 transition-colors duration-300 font-medium">Back to Challenge</span>
                        </div>
                    </flux:button>
                    
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                            <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-[#231F20] dark:text-zinc-100">Edit Challenge</h1>
                            <p class="text-[#9B9EA4] dark:text-zinc-400">Update your innovation challenge details and requirements</p>
                        </div>
                    </div>
                </div>
                
                {{-- Delete Button with Enhanced Styling --}}
                @if($this->canDelete())
                    <flux:button 
                        wire:click="delete"
                        wire:confirm="Are you sure you want to delete this challenge? This action cannot be undone."
                        class="group/delete relative overflow-hidden rounded-2xl bg-gradient-to-br from-red-500 to-red-600 dark:from-red-400 dark:to-red-500 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-6 py-3"
                    >
                        <div class="absolute inset-0 bg-gradient-to-br from-red-600/20 to-red-700/30 opacity-0 group-hover/delete:opacity-100 transition-opacity duration-300"></div>
                        <div class="relative flex items-center space-x-2 text-white">
                            <flux:icon.trash class="w-4 h-4" />
                            <span class="font-semibold">Delete Challenge</span>
                        </div>
                    </flux:button>
                @endif
            </div>
        </section>

        {{-- Enhanced Challenge Status Card --}}
        <section class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-blue-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-blue-500/20"></div>
                
                <div class="relative p-8">
                    <div class="flex items-start gap-6">
                        <div class="relative">
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <flux:icon.information-circle class="w-8 h-8 text-white" />
                            </div>
                            <div class="absolute -inset-2 bg-blue-500/20 dark:bg-blue-400/30 rounded-2xl blur-xl opacity-50"></div>
                        </div>
                        
                        <div class="flex-1">
                            <h3 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100 mb-4">Challenge Status Overview</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="group/metric relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <div class="flex flex-col space-y-2">
                                        <span class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 uppercase tracking-wider">Current Status</span>
                                        <div class="inline-flex items-center space-x-2">
                                            @php
                                                $statusColors = [
                                                    'draft' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                                    'active' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                                                    'judging' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
                                                    'completed' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
                                                    'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                                ];
                                            @endphp
                                            <span class="px-3 py-1.5 rounded-full text-sm font-medium {{ $statusColors[$challenge->status] ?? 'bg-gray-100 text-gray-800' }}">
                                                {{ ucfirst($challenge->status) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="group/metric relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <div class="flex flex-col space-y-2">
                                        <span class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 uppercase tracking-wider">Total Submissions</span>
                                        <span class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">{{ number_format($challenge->submissions()->count()) }}</span>
                                    </div>
                                </div>
                                
                                <div class="group/metric relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <div class="flex flex-col space-y-2">
                                        <span class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400 uppercase tracking-wider">Created Date</span>
                                        <span class="text-lg font-semibold text-[#231F20] dark:text-zinc-100">{{ $challenge->created_at->format('M j, Y') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Edit Form --}}
        <form wire:submit="save" class="space-y-8">
            {{-- Challenge Details Section --}}
            <section class="group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 via-transparent to-amber-600/10 dark:from-amber-400/10 dark:via-transparent dark:to-amber-500/20"></div>
                    
                    <div class="relative p-8">
                        <div class="flex items-center space-x-4 mb-6">
                            <div class="w-12 h-12 bg-gradient-to-br from-amber-500 to-amber-600 dark:from-amber-400 dark:to-amber-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Challenge Details</h2>
                                <p class="text-[#9B9EA4] dark:text-zinc-400">Basic information about your challenge</p>
                            </div>
                        </div>
                        
                        <div class="space-y-6">
                            <div>
                                <flux:field>
                                    <flux:label class="text-[#231F20] dark:text-zinc-100 font-semibold">Challenge Title</flux:label>
                                    <flux:input 
                                        wire:model="title" 
                                        placeholder="Enter a compelling challenge title..."
                                        class="rounded-2xl border-[#9B9EA4]/30 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:ring-[#FFF200] focus:border-[#FFF200] transition-all duration-300"
                                    />
                                    <flux:error name="title" />
                                </flux:field>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <flux:field>
                                        <flux:label class="text-[#231F20] dark:text-zinc-100 font-semibold">Category</flux:label>
                                        <flux:select wire:model="category" class="rounded-2xl border-[#9B9EA4]/30 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:ring-[#FFF200] focus:border-[#FFF200] transition-all duration-300">
                                            <option value="">Select a category</option>
                                            <option value="technology">Technology & Innovation</option>
                                            <option value="sustainability">Sustainability & Environment</option>
                                            <option value="safety">Safety & Security</option>
                                            <option value="innovation">Process Innovation</option>
                                            <option value="infrastructure">Infrastructure Development</option>
                                            <option value="efficiency">Efficiency & Optimization</option>
                                        </flux:select>
                                        <flux:error name="category" />
                                    </flux:field>
                                </div>
                                
                                <div>
                                    <flux:field>
                                        <flux:label class="text-[#231F20] dark:text-zinc-100 font-semibold">Status</flux:label>
                                        <flux:select wire:model="status" class="rounded-2xl border-[#9B9EA4]/30 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:ring-[#FFF200] focus:border-[#FFF200] transition-all duration-300">
                                            <option value="draft">Draft</option>
                                            <option value="active">Active</option>
                                            <option value="judging">Judging</option>
                                            <option value="completed">Completed</option>
                                            <option value="cancelled">Cancelled</option>
                                        </flux:select>
                                        <flux:error name="status" />
                                    </flux:field>
                                </div>
                            </div>
                            
                            <div>
                                <flux:field>
                                    <flux:label class="text-[#231F20] dark:text-zinc-100 font-semibold">Challenge Description</flux:label>
                                    <flux:textarea 
                                        wire:model="description" 
                                        rows="6"
                                        placeholder="Provide a detailed description of the challenge..."
                                        class="rounded-2xl border-[#9B9EA4]/30 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:ring-[#FFF200] focus:border-[#FFF200] transition-all duration-300"
                                    />
                                    <flux:error name="description" />
                                </flux:field>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Requirements Section with Enhanced UI --}}
            <section class="group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 via-transparent to-purple-600/10 dark:from-purple-400/10 dark:via-transparent dark:to-purple-500/20"></div>
                    
                    <div class="relative p-8">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 rounded-2xl flex items-center justify-center shadow-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Challenge Requirements</h2>
                                    <p class="text-[#9B9EA4] dark:text-zinc-400">Define specific requirements for submissions</p>
                                </div>
                            </div>
                            
                            <flux:button 
                                type="button"
                                wire:click="addRequirement"
                                class="group/add relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#FFF200] to-yellow-400 dark:from-yellow-400 dark:to-yellow-500 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-4 py-2"
                            >
                                <div class="absolute inset-0 bg-gradient-to-br from-yellow-400/20 to-yellow-500/30 opacity-0 group-hover/add:opacity-100 transition-opacity duration-300"></div>
                                <div class="relative flex items-center space-x-2 text-[#231F20] font-semibold">
                                    <flux:icon.plus class="w-4 h-4" />
                                    <span>Add Requirement</span>
                                </div>
                            </flux:button>
                        </div>
                        
                        <div class="space-y-4">
                            @foreach($requirements as $index => $requirement)
                                <div class="group/req relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <div class="flex gap-4">
                                        <div class="flex-1">
                                            <flux:input 
                                                wire:model="requirements.{{ $index }}" 
                                                placeholder="Enter requirement {{ $index + 1 }}..."
                                                class="rounded-xl border-[#9B9EA4]/30 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:ring-purple-500 focus:border-purple-500 transition-all duration-300"
                                            />
                                            @error("requirements.{$index}")
                                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        
                                        @if(count($requirements) > 1)
                                            <flux:button 
                                                type="button"
                                                wire:click="removeRequirement({{ $index }})"
                                                class="group/remove relative overflow-hidden rounded-xl bg-red-500 hover:bg-red-600 shadow-lg transform hover:-translate-y-1 transition-all duration-300 px-3 py-2"
                                            >
                                                <flux:icon.trash class="w-4 h-4 text-white" />
                                            </flux:button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        @error('requirements')
                            <p class="text-red-500 text-sm mt-4">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </section>

            {{-- Judging & Timeline Section --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                {{-- Judging Criteria --}}
                <section class="group">
                    <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl h-full">
                        <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 via-transparent to-emerald-600/10 dark:from-emerald-400/10 dark:via-transparent dark:to-emerald-500/20"></div>
                        
                        <div class="relative p-8">
                            <div class="flex items-center space-x-4 mb-6">
                                <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 rounded-2xl flex items-center justify-center shadow-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Judging Criteria</h2>
                                    <p class="text-[#9B9EA4] dark:text-zinc-400">How submissions will be evaluated</p>
                                </div>
                            </div>
                            
                            <div>
                                <flux:field>
                                    <flux:textarea 
                                        wire:model="judging_criteria" 
                                        rows="6"
                                        placeholder="Describe how submissions will be evaluated..."
                                        class="rounded-2xl border-[#9B9EA4]/30 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:ring-emerald-500 focus:border-emerald-500 transition-all duration-300"
                                    />
                                    <flux:error name="judging_criteria" />
                                </flux:field>
                            </div>
                        </div>
                    </div>
                </section>
                
                {{-- Timeline & Prize --}}
                <div class="space-y-6">
                    {{-- Timeline --}}
                    <section class="group">
                        <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                            <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-blue-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-blue-500/20"></div>
                            
                            <div class="relative p-6">
                                <div class="flex items-center space-x-3 mb-4">
                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 rounded-xl flex items-center justify-center shadow-lg">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Timeline</h2>
                                        <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Set submission deadline</p>
                                    </div>
                                </div>
                                
                                <div>
                                    <flux:field>
                                        <flux:label class="text-[#231F20] dark:text-zinc-100 font-semibold">Submission Deadline</flux:label>
                                        <flux:input 
                                            type="date"
                                            wire:model="deadline" 
                                            min="{{ now()->toDateString() }}"
                                            class="rounded-xl border-[#9B9EA4]/30 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:ring-blue-500 focus:border-blue-500 transition-all duration-300"
                                        />
                                        <flux:error name="deadline" />
                                        <flux:description class="text-[#9B9EA4] dark:text-zinc-400">Deadline cannot be in the past</flux:description>
                                    </flux:field>
                                </div>
                            </div>
                        </div>
                    </section>
                    
                    {{-- Prize --}}
                    <section class="group">
                        <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                            <div class="absolute inset-0 bg-gradient-to-br from-orange-500/5 via-transparent to-orange-600/10 dark:from-orange-400/10 dark:via-transparent dark:to-orange-500/20"></div>
                            
                            <div class="relative p-6">
                                <div class="flex items-center space-x-3 mb-4">
                                    <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-orange-600 dark:from-orange-400 dark:to-orange-500 rounded-xl flex items-center justify-center shadow-lg">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Prize & Recognition</h2>
                                        <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Optional rewards information</p>
                                    </div>
                                </div>
                                
                                <div>
                                    <flux:field>
                                        <flux:textarea 
                                            wire:model="prize_description" 
                                            rows="3"
                                            placeholder="Describe the prizes, recognition, or opportunities for winners (optional)..."
                                            class="rounded-xl border-[#9B9EA4]/30 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:ring-orange-500 focus:border-orange-500 transition-all duration-300"
                                        />
                                        <flux:error name="prize_description" />
                                    </flux:field>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            {{-- Enhanced Form Actions --}}
            <section class="group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    <div class="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br from-[#FFF200]/10 via-[#F8EBD5]/5 to-transparent dark:from-yellow-400/10 dark:via-amber-400/5 dark:to-transparent rounded-full -mr-48 -mt-48 blur-3xl"></div>
                    
                    <div class="relative p-8">
                        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                            <flux:button 
                                type="button"
                                wire:navigate 
                                href="{{ route('challenges.show', $challenge) }}"
                                class="group/cancel relative overflow-hidden rounded-2xl bg-gradient-to-br from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border border-[#9B9EA4]/30 dark:border-zinc-600/50 backdrop-blur-sm shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-8 py-3"
                            >
                                <span class="absolute inset-0 bg-gradient-to-br from-gray-500/10 to-gray-600/20 dark:from-gray-400/20 dark:to-gray-500/30 opacity-0 group-hover/cancel:opacity-100 transition-opacity duration-300"></span>
                                <span class="relative text-[#9B9EA4] dark:text-zinc-400 group-hover/cancel:text-[#231F20] dark:group-hover/cancel:text-zinc-100 font-semibold transition-colors duration-300">Cancel</span>
                            </flux:button>
                            
                            <flux:button 
                                type="submit"
                                class="group/submit relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#FFF200] to-yellow-400 dark:from-yellow-400 dark:to-yellow-500 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-8 py-3"
                                wire:loading.attr="disabled"
                            >
                                <div class="absolute inset-0 bg-gradient-to-br from-yellow-400/20 to-yellow-500/30 opacity-0 group-hover/submit:opacity-100 transition-opacity duration-300"></div>
                                <div class="relative flex items-center space-x-2 text-[#231F20] font-semibold">
                                    <span wire:loading.remove>Update Challenge</span>
                                    <span wire:loading class="flex items-center space-x-2">
                                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span>Updating...</span>
                                    </span>
                                </div>
                            </flux:button>
                        </div>
                        
                        <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 mt-6 text-center leading-relaxed">
                            Changes will be saved and participants will be notified of significant updates.
                        </p>
                    </div>
                </div>
            </section>
        </form>
    </div>
</div>
