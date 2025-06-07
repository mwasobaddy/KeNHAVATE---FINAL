<?php

use Livewire\Volt\Component;
use App\Models\Challenge;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Artesaos\SEOTools\Facades\SEOTools;

new class extends Component {
    public string $title = '';
    public string $description = '';
    public string $category = '';
    public string $prize_description = '';
    public string $deadline = '';
    public string $judging_criteria = '';
    public array $requirements = [''];
    public string $status = 'draft';
    
    public function mount()
    {
        // Set SEO meta tags
        SEOTools::setTitle('Create Challenge - KeNHAVATE Innovation Portal');
        SEOTools::setDescription('Create a new innovation challenge to engage the community in solving highway infrastructure problems.');
        
        // Authorize user
        $this->authorize('create', Challenge::class);
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
            'deadline' => ['required', 'date', 'after:' . now()->addDays(7)->toDateString()],
            'judging_criteria' => ['required', 'string', 'min:20'],
            'requirements' => ['required', 'array', 'min:1'],
            'requirements.*' => ['required', 'string', 'min:5'],
            'status' => ['required', 'string', Rule::in(['draft', 'active'])],
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
        
        // Create the challenge
        $challenge = Challenge::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'category' => $validated['category'],
            'prize_description' => $validated['prize_description'] ?: null,
            'deadline' => $validated['deadline'],
            'judging_criteria' => $validated['judging_criteria'],
            'requirements' => $validated['requirements'],
            'status' => $validated['status'],
            'author_id' => auth()->id(),
        ]);
        
        // Log the action
        app(App\Services\AuditService::class)->log(
            'challenge_creation',
            'Challenge',
            $challenge->id,
            null,
            $challenge->toArray()
        );
        
        // Send notification to administrators
        app(App\Services\NotificationService::class)->sendToRoles(
            ['administrator', 'developer'],
            'challenge_created',
            [
                'title' => 'New Challenge Created',
                'message' => "A new challenge '{$challenge->title}' has been created by " . auth()->user()->name,
                'related_id' => $challenge->id,
                'related_type' => 'Challenge',
            ]
        );
        
        session()->flash('success', 'Challenge created successfully!');
        
        return redirect()->route('challenges.show', $challenge);
    }
    
    public function saveDraft()
    {
        $this->status = 'draft';
        $this->save();
    }
    
    public function publish()
    {
        $this->status = 'active';
        $this->save();
    }
}; ?>

{{-- Modern Challenge Creation with Glass Morphism & Enhanced UI --}}
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/80 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/50 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 py-8 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto">
        {{-- Enhanced Header with Glass Morphism --}}
        <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl p-8 mb-8">
            {{-- Background Gradient --}}
            <div class="absolute inset-0 bg-gradient-to-br from-[#FFF200]/5 via-transparent to-[#F8EBD5]/10 dark:from-yellow-400/10 dark:via-transparent dark:to-amber-400/20"></div>
            
            <div class="relative z-10">
                {{-- Navigation Breadcrumb --}}
                <div class="flex items-center gap-4 mb-6">
                    <flux:button 
                        wire:navigate 
                        href="{{ route('challenges.index') }}" 
                        variant="subtle"
                        class="group relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm hover:shadow-lg transition-all duration-300 hover:-translate-y-1 px-4 py-2"
                    >
                        <span class="absolute inset-0 bg-gradient-to-br from-blue-500/10 to-blue-600/20 dark:from-blue-400/20 dark:to-blue-500/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                        <div class="relative flex items-center space-x-2 text-[#9B9EA4] group-hover:text-[#231F20] dark:group-hover:text-zinc-100 transition-colors duration-300">
                            <flux:icon.arrow-left class="w-5 h-5" />
                            <span class="font-medium">Back to Challenges</span>
                        </div>
                    </flux:button>
                </div>
                
                {{-- Header Content --}}
                <div class="flex items-center space-x-4">
                    <div class="w-16 h-16 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-3xl flex items-center justify-center shadow-lg">
                        <svg class="w-8 h-8 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-4xl font-bold text-[#231F20] dark:text-zinc-100 mb-2">Create Innovation Challenge</h1>
                        <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg leading-relaxed">Design a challenge to engage the community in solving real-world highway infrastructure problems</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Challenge Creation Form --}}
        <form wire:submit="save" class="space-y-8">
            {{-- Challenge Details Section --}}
            <div class="group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    {{-- Animated Background --}}
                    <div class="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br from-blue-500/5 via-blue-400/5 to-transparent dark:from-blue-400/10 dark:via-blue-500/5 dark:to-transparent rounded-full -mr-48 -mt-48 blur-3xl"></div>
                    
                    <div class="relative z-10 p-8">
                        {{-- Section Header --}}
                        <div class="flex items-center space-x-4 mb-6">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Challenge Details</h2>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Define the core information for your challenge</p>
                            </div>
                        </div>
                        
                        <div class="space-y-6">
                            {{-- Challenge Title --}}
                            <div class="group/field">
                                <flux:field>
                                    <flux:label class="text-[#231F20] dark:text-zinc-100 font-semibold">Challenge Title</flux:label>
                                    <flux:input 
                                        wire:model="title" 
                                        placeholder="Enter a compelling challenge title..."
                                        class="rounded-2xl border-[#9B9EA4]/30 dark:border-zinc-600/50 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:ring-2 focus:ring-[#FFF200]/50 dark:focus:ring-yellow-400/50 transition-all duration-300"
                                    />
                                    <flux:error name="title" />
                                </flux:field>
                            </div>
                            
                            {{-- Category Selection --}}
                            <div class="group/field">
                                <flux:field>
                                    <flux:label class="text-[#231F20] dark:text-zinc-100 font-semibold">Category</flux:label>
                                    <flux:select 
                                        wire:model="category" 
                                        class="rounded-2xl border-[#9B9EA4]/30 dark:border-zinc-600/50 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:ring-2 focus:ring-[#FFF200]/50 dark:focus:ring-yellow-400/50 transition-all duration-300"
                                    >
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
                            
                            {{-- Challenge Description --}}
                            <div class="group/field">
                                <flux:field>
                                    <flux:label class="text-[#231F20] dark:text-zinc-100 font-semibold">Challenge Description</flux:label>
                                    <flux:textarea 
                                        wire:model="description" 
                                        rows="8"
                                        placeholder="Provide a detailed description of the challenge, including the problem statement, objectives, and expected outcomes..."
                                        class="rounded-2xl border-[#9B9EA4]/30 dark:border-zinc-600/50 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:ring-2 focus:ring-[#FFF200]/50 dark:focus:ring-yellow-400/50 transition-all duration-300"
                                    />
                                    <flux:error name="description" />
                                </flux:field>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Requirements Section --}}
            <div class="group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    {{-- Animated Background --}}
                    <div class="absolute top-0 left-0 w-96 h-96 bg-gradient-to-br from-purple-500/5 via-purple-400/5 to-transparent dark:from-purple-400/10 dark:via-purple-500/5 dark:to-transparent rounded-full -ml-48 -mt-48 blur-3xl"></div>
                    
                    <div class="relative z-10 p-8">
                        {{-- Section Header --}}
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 rounded-2xl flex items-center justify-center shadow-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Challenge Requirements</h2>
                                    <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Define specific requirements and criteria</p>
                                </div>
                            </div>
                            
                            <flux:button 
                                type="button"
                                wire:click="addRequirement"
                                variant="primary"
                                class="group relative overflow-hidden rounded-2xl bg-gradient-to-r from-[#FFF200] to-yellow-400 hover:from-yellow-400 hover:to-[#FFF200] text-[#231F20] font-semibold shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-6 py-3"
                            >
                                <span class="absolute inset-0 bg-gradient-to-r from-yellow-300/20 to-amber-300/20 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                <div class="relative flex items-center space-x-2">
                                    <flux:icon.plus class="w-5 h-5" />
                                    <span>Add Requirement</span>
                                </div>
                            </flux:button>
                        </div>
                        
                        <div class="space-y-4">
                            @foreach($requirements as $index => $requirement)
                                <div class="group/requirement relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-4 hover:shadow-lg transition-all duration-300">
                                    <div class="flex gap-4">
                                        <div class="flex-1">
                                            <flux:input 
                                                wire:model="requirements.{{ $index }}" 
                                                placeholder="Enter requirement {{ $index + 1 }}..."
                                                class="rounded-xl border-[#9B9EA4]/30 dark:border-zinc-600/50 bg-white/70 dark:bg-zinc-800/70 backdrop-blur-sm focus:ring-2 focus:ring-[#FFF200]/50 dark:focus:ring-yellow-400/50 transition-all duration-300"
                                            />
                                            @error("requirements.{$index}")
                                                <p class="text-red-500 text-sm mt-2 font-medium">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        
                                        @if(count($requirements) > 1)
                                            <flux:button 
                                                type="button"
                                                wire:click="removeRequirement({{ $index }})"
                                                variant="danger"
                                                class="group relative overflow-hidden rounded-xl bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-500 text-white shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-4"
                                            >
                                                <flux:icon.trash class="w-5 h-5" />
                                            </flux:button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        @error('requirements')
                            <p class="text-red-500 text-sm mt-4 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Judging & Timeline Grid --}}
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                {{-- Judging Criteria --}}
                <div class="group">
                    <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl h-full">
                        {{-- Animated Background --}}
                        <div class="absolute bottom-0 right-0 w-72 h-72 bg-gradient-to-br from-emerald-500/5 via-emerald-400/5 to-transparent dark:from-emerald-400/10 dark:via-emerald-500/5 dark:to-transparent rounded-full -mr-36 -mb-36 blur-3xl"></div>
                        
                        <div class="relative z-10 p-8">
                            {{-- Section Header --}}
                            <div class="flex items-center space-x-4 mb-6">
                                <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 rounded-2xl flex items-center justify-center shadow-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Judging Criteria</h2>
                                    <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Define evaluation standards</p>
                                </div>
                            </div>
                            
                            <div>
                                <flux:field>
                                    <flux:textarea 
                                        wire:model="judging_criteria" 
                                        rows="8"
                                        placeholder="Describe how submissions will be evaluated (e.g., innovation, feasibility, impact, presentation quality)..."
                                        class="rounded-2xl border-[#9B9EA4]/30 dark:border-zinc-600/50 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:ring-2 focus:ring-[#FFF200]/50 dark:focus:ring-yellow-400/50 transition-all duration-300"
                                    />
                                    <flux:error name="judging_criteria" />
                                </flux:field>
                            </div>
                        </div>
                    </div>
                </div>
                
                {{-- Timeline & Prize Section --}}
                <div class="space-y-8">
                    {{-- Timeline --}}
                    <div class="group">
                        <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                            <div class="absolute top-0 left-0 w-72 h-72 bg-gradient-to-br from-amber-500/5 via-amber-400/5 to-transparent dark:from-amber-400/10 dark:via-amber-500/5 dark:to-transparent rounded-full -ml-36 -mt-36 blur-3xl"></div>
                            
                            <div class="relative z-10 p-8">
                                {{-- Section Header --}}
                                <div class="flex items-center space-x-4 mb-6">
                                    <div class="w-12 h-12 bg-gradient-to-br from-amber-500 to-amber-600 dark:from-amber-400 dark:to-amber-500 rounded-2xl flex items-center justify-center shadow-lg">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Timeline</h2>
                                        <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Set submission deadline</p>
                                    </div>
                                </div>
                                
                                <div>
                                    <flux:field>
                                        <flux:label class="text-[#231F20] dark:text-zinc-100 font-semibold">Submission Deadline</flux:label>
                                        <flux:input 
                                            type="date"
                                            wire:model="deadline" 
                                            min="{{ now()->addDays(7)->toDateString() }}"
                                            class="rounded-2xl border-[#9B9EA4]/30 dark:border-zinc-600/50 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:ring-2 focus:ring-[#FFF200]/50 dark:focus:ring-yellow-400/50 transition-all duration-300"
                                        />
                                        <flux:error name="deadline" />
                                        <flux:description class="text-[#9B9EA4] dark:text-zinc-400">Minimum 7 days from today</flux:description>
                                    </flux:field>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Prize Description --}}
                    <div class="group">
                        <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                            <div class="absolute bottom-0 left-0 w-72 h-72 bg-gradient-to-br from-indigo-500/5 via-indigo-400/5 to-transparent dark:from-indigo-400/10 dark:via-indigo-500/5 dark:to-transparent rounded-full -ml-36 -mb-36 blur-3xl"></div>
                            
                            <div class="relative z-10 p-8">
                                {{-- Section Header --}}
                                <div class="flex items-center space-x-4 mb-6">
                                    <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-indigo-600 dark:from-indigo-400 dark:to-indigo-500 rounded-2xl flex items-center justify-center shadow-lg">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Prize & Recognition</h2>
                                        <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Define rewards for winners</p>
                                    </div>
                                </div>
                                
                                <div>
                                    <flux:field>
                                        <flux:textarea 
                                            wire:model="prize_description" 
                                            rows="4"
                                            placeholder="Describe the prizes, recognition, or opportunities for winners (optional)..."
                                            class="rounded-2xl border-[#9B9EA4]/30 dark:border-zinc-600/50 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:ring-2 focus:ring-[#FFF200]/50 dark:focus:ring-yellow-400/50 transition-all duration-300"
                                        />
                                        <flux:error name="prize_description" />
                                    </flux:field>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Enhanced Form Actions --}}
            <div class="group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    {{-- Animated Background --}}
                    <div class="absolute inset-0 bg-gradient-to-r from-[#FFF200]/5 via-transparent to-[#F8EBD5]/10 dark:from-yellow-400/10 dark:via-transparent dark:to-amber-400/20"></div>
                    
                    <div class="relative z-10 p-8">
                        {{-- Action Buttons --}}
                        <div class="flex flex-col sm:flex-row gap-6 justify-end mb-6">
                            <flux:button 
                                type="button"
                                wire:click="saveDraft"
                                variant="outline"
                                class="group relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/90 to-white/60 dark:from-zinc-700/90 dark:to-zinc-800/60 border-2 border-[#9B9EA4]/50 dark:border-zinc-600/50 text-[#9B9EA4] hover:text-[#231F20] dark:hover:text-zinc-100 backdrop-blur-sm shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 px-8 py-4 font-semibold"
                                wire:loading.attr="disabled"
                            >
                                <span class="absolute inset-0 bg-gradient-to-br from-gray-500/10 to-gray-600/20 dark:from-gray-400/20 dark:to-gray-500/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                <div class="relative flex items-center space-x-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                    </svg>
                                    <span wire:loading.remove wire:target="saveDraft">Save as Draft</span>
                                    <span wire:loading wire:target="saveDraft">Saving...</span>
                                </div>
                            </flux:button>
                            
                            <flux:button 
                                type="button"
                                wire:click="publish"
                                variant="primary"
                                class="group relative overflow-hidden rounded-2xl bg-gradient-to-r from-[#FFF200] to-yellow-400 hover:from-yellow-400 hover:to-[#FFF200] text-[#231F20] shadow-xl hover:shadow-2xl transform hover:-translate-y-2 transition-all duration-500 px-8 py-4 font-bold"
                                wire:loading.attr="disabled"
                            >
                                <span class="absolute inset-0 bg-gradient-to-r from-yellow-300/30 to-amber-300/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                                <div class="relative flex items-center space-x-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                    </svg>
                                    <span wire:loading.remove wire:target="publish">Publish Challenge</span>
                                    <span wire:loading wire:target="publish">Publishing...</span>
                                </div>
                            </flux:button>
                        </div>
                        
                        {{-- Enhanced Help Text --}}
                        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-6 text-center">
                            <div class="space-y-3">
                                <div class="flex items-center justify-center space-x-8">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-3 h-3 bg-[#9B9EA4] dark:bg-zinc-400 rounded-full"></div>
                                        <span class="text-sm font-semibold text-[#9B9EA4] dark:text-zinc-400">Draft:</span>
                                        <span class="text-sm text-[#231F20] dark:text-zinc-100">Saves privately for later editing</span>
                                    </div>
                                    <div class="hidden sm:block w-px h-8 bg-[#9B9EA4]/30 dark:bg-zinc-600/50"></div>
                                    <div class="flex items-center space-x-2">
                                        <div class="w-3 h-3 bg-[#FFF200] dark:bg-yellow-400 rounded-full"></div>
                                        <span class="text-sm font-semibold text-[#FFF200] dark:text-yellow-400">Publish:</span>
                                        <span class="text-sm text-[#231F20] dark:text-zinc-100">Makes challenge live for submissions</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
