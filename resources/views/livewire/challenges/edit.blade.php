<?php

use Livewire\Volt\Component;
use App\Models\Challenge;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Artesaos\SEOTools\Facades\SEOTools;

new class extends Component {
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

<div class="min-h-screen bg-gradient-to-br from-[#F8EBD5] via-white to-[#F8EBD5] py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-4 mb-4">
                <x-flux:button 
                    wire:navigate 
                    href="{{ route('challenges.show', $challenge) }}" 
                    variant="subtle"
                    class="text-[#9B9EA4] hover:text-[#231F20]"
                >
                    <x-flux:icon.arrow-left class="w-5 h-5 mr-2" />
                    Back to Challenge
                </x-flux:button>
                
                <!-- Delete Button -->
                @if($this->canDelete())
                    <div class="ml-auto">
                        <x-flux:button 
                            wire:click="delete"
                            wire:confirm="Are you sure you want to delete this challenge? This action cannot be undone."
                            variant="danger"
                            class="bg-red-600 hover:bg-red-700 text-white rounded-xl"
                        >
                            <x-flux:icon.trash class="w-4 h-4 mr-2" />
                            Delete Challenge
                        </x-flux:button>
                    </div>
                @endif
            </div>
            
            <h1 class="text-3xl font-bold text-[#231F20] mb-2">Edit Challenge</h1>
            <p class="text-[#9B9EA4] text-lg">Update your innovation challenge details and requirements</p>
        </div>

        <!-- Challenge Info Card -->
        <div class="bg-blue-50/70 backdrop-blur-sm rounded-2xl shadow-lg border border-blue-200/50 p-6 mb-8">
            <div class="flex items-start gap-4">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <x-flux:icon.information-circle class="w-6 h-6 text-blue-600" />
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-blue-900 mb-2">Challenge Status</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <span class="text-blue-600 font-medium">Current Status:</span>
                            <span class="ml-2 px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">{{ ucfirst($challenge->status) }}</span>
                        </div>
                        <div>
                            <span class="text-blue-600 font-medium">Submissions:</span>
                            <span class="ml-2 text-blue-900">{{ $challenge->submissions()->count() }}</span>
                        </div>
                        <div>
                            <span class="text-blue-600 font-medium">Created:</span>
                            <span class="ml-2 text-blue-900">{{ $challenge->created_at->format('M j, Y') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Form -->
        <form wire:submit="save" class="space-y-6">
            <!-- Challenge Details -->
            <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                <h2 class="text-xl font-semibold text-[#231F20] mb-4">Challenge Details</h2>
                
                <div class="space-y-4">
                    <div>
                        <x-flux:field>
                            <x-flux:label>Challenge Title</x-flux:label>
                            <x-flux:input 
                                wire:model="title" 
                                placeholder="Enter a compelling challenge title..."
                                class="rounded-xl border-[#9B9EA4]/30"
                            />
                            <x-flux:error name="title" />
                        </x-flux:field>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-flux:field>
                                <x-flux:label>Category</x-flux:label>
                                <x-flux:select wire:model="category" class="rounded-xl border-[#9B9EA4]/30">
                                    <option value="">Select a category</option>
                                    <option value="technology">Technology & Innovation</option>
                                    <option value="sustainability">Sustainability & Environment</option>
                                    <option value="safety">Safety & Security</option>
                                    <option value="innovation">Process Innovation</option>
                                    <option value="infrastructure">Infrastructure Development</option>
                                    <option value="efficiency">Efficiency & Optimization</option>
                                </x-flux:select>
                                <x-flux:error name="category" />
                            </x-flux:field>
                        </div>
                        
                        <div>
                            <x-flux:field>
                                <x-flux:label>Status</x-flux:label>
                                <x-flux:select wire:model="status" class="rounded-xl border-[#9B9EA4]/30">
                                    <option value="draft">Draft</option>
                                    <option value="active">Active</option>
                                    <option value="judging">Judging</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </x-flux:select>
                                <x-flux:error name="status" />
                            </x-flux:field>
                        </div>
                    </div>
                    
                    <div>
                        <x-flux:field>
                            <x-flux:label>Challenge Description</x-flux:label>
                            <x-flux:textarea 
                                wire:model="description" 
                                rows="6"
                                placeholder="Provide a detailed description of the challenge..."
                                class="rounded-xl border-[#9B9EA4]/30"
                            />
                            <x-flux:error name="description" />
                        </x-flux:field>
                    </div>
                </div>
            </div>

            <!-- Requirements Section -->
            <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-[#231F20]">Challenge Requirements</h2>
                    <x-flux:button 
                        type="button"
                        wire:click="addRequirement"
                        variant="primary"
                        size="sm"
                        class="bg-[#FFF200] hover:bg-yellow-400 text-[#231F20] rounded-xl"
                    >
                        <x-flux:icon.plus class="w-4 h-4 mr-1" />
                        Add Requirement
                    </x-flux:button>
                </div>
                
                <div class="space-y-3">
                    @foreach($requirements as $index => $requirement)
                        <div class="flex gap-3">
                            <div class="flex-1">
                                <x-flux:input 
                                    wire:model="requirements.{{ $index }}" 
                                    placeholder="Enter requirement {{ $index + 1 }}..."
                                    class="rounded-xl border-[#9B9EA4]/30"
                                />
                                @error("requirements.{$index}")
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            @if(count($requirements) > 1)
                                <x-flux:button 
                                    type="button"
                                    wire:click="removeRequirement({{ $index }})"
                                    variant="danger"
                                    size="sm"
                                    class="px-3 rounded-xl"
                                >
                                    <x-flux:icon.trash class="w-4 h-4" />
                                </x-flux:button>
                            @endif
                        </div>
                    @endforeach
                </div>
                
                @error('requirements')
                    <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                @enderror
            </div>

            <!-- Judging & Timeline -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Judging Criteria -->
                <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                    <h2 class="text-xl font-semibold text-[#231F20] mb-4">Judging Criteria</h2>
                    
                    <div>
                        <x-flux:field>
                            <x-flux:textarea 
                                wire:model="judging_criteria" 
                                rows="6"
                                placeholder="Describe how submissions will be evaluated..."
                                class="rounded-xl border-[#9B9EA4]/30"
                            />
                            <x-flux:error name="judging_criteria" />
                        </x-flux:field>
                    </div>
                </div>
                
                <!-- Timeline & Prize -->
                <div class="space-y-6">
                    <!-- Deadline -->
                    <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                        <h2 class="text-xl font-semibold text-[#231F20] mb-4">Timeline</h2>
                        
                        <div>
                            <x-flux:field>
                                <x-flux:label>Submission Deadline</x-flux:label>
                                <x-flux:input 
                                    type="date"
                                    wire:model="deadline" 
                                    min="{{ now()->toDateString() }}"
                                    class="rounded-xl border-[#9B9EA4]/30"
                                />
                                <x-flux:error name="deadline" />
                                <x-flux:description>Deadline cannot be in the past</x-flux:description>
                            </x-flux:field>
                        </div>
                    </div>
                    
                    <!-- Prize -->
                    <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                        <h2 class="text-xl font-semibold text-[#231F20] mb-4">Prize & Recognition</h2>
                        
                        <div>
                            <x-flux:field>
                                <x-flux:textarea 
                                    wire:model="prize_description" 
                                    rows="3"
                                    placeholder="Describe the prizes, recognition, or opportunities for winners (optional)..."
                                    class="rounded-xl border-[#9B9EA4]/30"
                                />
                                <x-flux:error name="prize_description" />
                            </x-flux:field>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                <div class="flex flex-col sm:flex-row gap-4 justify-end">
                    <x-flux:button 
                        type="button"
                        wire:navigate 
                        href="{{ route('challenges.show', $challenge) }}"
                        variant="outline"
                        class="border-[#9B9EA4] text-[#9B9EA4] hover:bg-[#9B9EA4]/10 rounded-xl px-8 py-3"
                    >
                        Cancel
                    </x-flux:button>
                    
                    <x-flux:button 
                        type="submit"
                        variant="primary"
                        class="bg-[#FFF200] hover:bg-yellow-400 text-[#231F20] rounded-xl px-8 py-3 font-semibold"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove>Update Challenge</span>
                        <span wire:loading>Updating...</span>
                    </x-flux:button>
                </div>
                
                <p class="text-sm text-[#9B9EA4] mt-3 text-center">
                    Changes will be saved and participants will be notified of significant updates.
                </p>
            </div>
        </form>
    </div>
</div>
