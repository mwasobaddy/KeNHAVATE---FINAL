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
            ['admin', 'developer'],
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

<div class="min-h-screen bg-gradient-to-br from-[#F8EBD5] via-white to-[#F8EBD5] py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-4 mb-4">
                <flux:button 
                    wire:navigate 
                    href="{{ route('challenges.index') }}" 
                    variant="subtle"
                    class="text-[#9B9EA4] hover:text-[#231F20]"
                >
                    <flux:icon.arrow-left class="w-5 h-5 mr-2" />
                    Back to Challenges
                </flux:button>
            </div>
            
            <h1 class="text-3xl font-bold text-[#231F20] mb-2">Create Innovation Challenge</h1>
            <p class="text-[#9B9EA4] text-lg">Design a challenge to engage the community in solving real-world highway infrastructure problems</p>
        </div>

        <!-- Challenge Form -->
        <form wire:submit="save" class="space-y-6">
            <!-- Challenge Title -->
            <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                <h2 class="text-xl font-semibold text-[#231F20] mb-4">Challenge Details</h2>
                
                <div class="space-y-4">
                    <div>
                        <flux:field>
                            <flux:label>Challenge Title</flux:label>
                            <flux:input 
                                wire:model="title" 
                                placeholder="Enter a compelling challenge title..."
                                class="rounded-xl border-[#9B9EA4]/30"
                            />
                            <flux:error name="title" />
                        </flux:field>
                    </div>
                    
                    <div>
                        <flux:field>
                            <flux:label>Category</flux:label>
                            <flux:select wire:model="category" class="rounded-xl border-[#9B9EA4]/30">
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
                            <flux:label>Challenge Description</flux:label>
                            <flux:textarea 
                                wire:model="description" 
                                rows="6"
                                placeholder="Provide a detailed description of the challenge, including the problem statement, objectives, and expected outcomes..."
                                class="rounded-xl border-[#9B9EA4]/30"
                            />
                            <flux:error name="description" />
                        </flux:field>
                    </div>
                </div>
            </div>

            <!-- Requirements Section -->
            <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-[#231F20]">Challenge Requirements</h2>
                    <flux:button 
                        type="button"
                        wire:click="addRequirement"
                        variant="primary"
                        size="sm"
                        class="bg-[#FFF200] hover:bg-yellow-400 text-[#231F20] rounded-xl"
                    >
                        <flux:icon.plus class="w-4 h-4 mr-1" />
                        Add Requirement
                    </flux:button>
                </div>
                
                <div class="space-y-3">
                    @foreach($requirements as $index => $requirement)
                        <div class="flex gap-3">
                            <div class="flex-1">
                                <flux:input 
                                    wire:model="requirements.{{ $index }}" 
                                    placeholder="Enter requirement {{ $index + 1 }}..."
                                    class="rounded-xl border-[#9B9EA4]/30"
                                />
                                @error("requirements.{$index}")
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            @if(count($requirements) > 1)
                                <flux:button 
                                    type="button"
                                    wire:click="removeRequirement({{ $index }})"
                                    variant="danger"
                                    size="sm"
                                    class="px-3 rounded-xl"
                                >
                                    <flux:icon.trash class="w-4 h-4" />
                                </flux:button>
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
                        <flux:field>
                            <flux:textarea 
                                wire:model="judging_criteria" 
                                rows="6"
                                placeholder="Describe how submissions will be evaluated (e.g., innovation, feasibility, impact, presentation quality)..."
                                class="rounded-xl border-[#9B9EA4]/30"
                            />
                            <flux:error name="judging_criteria" />
                        </flux:field>
                    </div>
                </div>
                
                <!-- Timeline & Prize -->
                <div class="space-y-6">
                    <!-- Deadline -->
                    <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                        <h2 class="text-xl font-semibold text-[#231F20] mb-4">Timeline</h2>
                        
                        <div>
                            <flux:field>
                                <flux:label>Submission Deadline</flux:label>
                                <flux:input 
                                    type="date"
                                    wire:model="deadline" 
                                    min="{{ now()->addDays(7)->toDateString() }}"
                                    class="rounded-xl border-[#9B9EA4]/30"
                                />
                                <flux:error name="deadline" />
                                <flux:description>Minimum 7 days from today</flux:description>
                            </flux:field>
                        </div>
                    </div>
                    
                    <!-- Prize -->
                    <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                        <h2 class="text-xl font-semibold text-[#231F20] mb-4">Prize & Recognition</h2>
                        
                        <div>
                            <flux:field>
                                <flux:textarea 
                                    wire:model="prize_description" 
                                    rows="3"
                                    placeholder="Describe the prizes, recognition, or opportunities for winners (optional)..."
                                    class="rounded-xl border-[#9B9EA4]/30"
                                />
                                <flux:error name="prize_description" />
                            </flux:field>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6">
                <div class="flex flex-col sm:flex-row gap-4 justify-end">
                    <flux:button 
                        type="button"
                        wire:click="saveDraft"
                        variant="outline"
                        class="border-[#9B9EA4] text-[#9B9EA4] hover:bg-[#9B9EA4]/10 rounded-xl px-8 py-3"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="saveDraft">Save as Draft</span>
                        <span wire:loading wire:target="saveDraft">Saving...</span>
                    </flux:button>
                    
                    <flux:button 
                        type="button"
                        wire:click="publish"
                        variant="primary"
                        class="bg-[#FFF200] hover:bg-yellow-400 text-[#231F20] rounded-xl px-8 py-3 font-semibold"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="publish">Publish Challenge</span>
                        <span wire:loading wire:target="publish">Publishing...</span>
                    </flux:button>
                </div>
                
                <p class="text-sm text-[#9B9EA4] mt-3 text-center">
                    <strong>Draft:</strong> Saves the challenge but keeps it hidden from users.<br>
                    <strong>Publish:</strong> Makes the challenge live and available for submissions.
                </p>
            </div>
        </form>
    </div>
</div>
