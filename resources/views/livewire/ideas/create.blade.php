<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\Idea;
use App\Models\Category;
use App\Services\AuditService;
use Illuminate\Support\Facades\Storage;

new #[Layout('components.layouts.app', title: 'Submit New Idea')] class extends Component
{
    use WithFileUploads;

    public $title = '';
    public $description = '';
    public $category_id = '';
    public $business_case = '';
    public $expected_impact = '';
    public $implementation_timeline = '';
    public $resource_requirements = '';
    public $attachments = [];
    public $collaboration_enabled = false;

    public $categories;
    public $isSubmitting = false;

    public function mount()
    {
        $this->categories = Category::where('active', true)->orderBy('name')->get();
    }

    public function rules()
    {
        return [
            'title' => 'required|string|max:255|min:10',
            'description' => 'required|string|min:50|max:5000',
            'category_id' => 'required|exists:categories,id',
            'business_case' => 'required|string|min:30|max:2000',
            'expected_impact' => 'required|string|min:20|max:1000',
            'implementation_timeline' => 'required|string|max:500',
            'resource_requirements' => 'required|string|max:1000',
            'attachments.*' => 'nullable|file|max:10240|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png',
            'collaboration_enabled' => 'boolean',
        ];
    }

    public function validationAttributes()
    {
        return [
            'title' => 'idea title',
            'description' => 'idea description',
            'category_id' => 'category',
            'business_case' => 'business case',
            'expected_impact' => 'expected impact',
            'implementation_timeline' => 'implementation timeline',
            'resource_requirements' => 'resource requirements',
        ];
    }

    public function saveDraft()
    {
        $this->validate([
            'title' => 'required|string|max:255|min:5',
            'description' => 'nullable|string|max:5000',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $idea = Idea::create([
            'title' => $this->title,
            'description' => $this->description ?: 'Draft idea - description pending',
            'category_id' => $this->category_id ?: null,
            'business_case' => $this->business_case,
            'expected_impact' => $this->expected_impact,
            'implementation_timeline' => $this->implementation_timeline,
            'resource_requirements' => $this->resource_requirements,
            'author_id' => auth()->id(),
            'current_stage' => 'draft',
            'collaboration_enabled' => $this->collaboration_enabled,
        ]);

        // Handle file attachments
        $this->handleAttachments($idea);

        // Log audit trail
        app(AuditService::class)->log(
            'idea_draft_saved',
            'Idea',
            $idea->id,
            null,
            $idea->toArray()
        );

        session()->flash('message', 'Idea saved as draft successfully!');
        return redirect()->route('ideas.show', $idea);
    }

    public function submit()
    {
        $this->validate();

        $this->isSubmitting = true;

        try {
            $idea = Idea::create([
                'title' => $this->title,
                'description' => $this->description,
                'category_id' => $this->category_id,
                'business_case' => $this->business_case,
                'expected_impact' => $this->expected_impact,
                'implementation_timeline' => $this->implementation_timeline,
                'resource_requirements' => $this->resource_requirements,
                'author_id' => auth()->id(),
                'current_stage' => 'submitted',
                'submitted_at' => now(),
                'collaboration_enabled' => $this->collaboration_enabled,
            ]);

            // Handle file attachments
            $this->handleAttachments($idea);

            // Log audit trail
            app(AuditService::class)->log(
                'idea_submission',
                'Idea',
                $idea->id,
                null,
                $idea->toArray()
            );

            // Send notifications (will implement later)
            // $this->notifyReviewers($idea);

            session()->flash('message', 'Idea submitted successfully! You will be notified as it progresses through the review process.');
            return redirect()->route('ideas.show', $idea);

        } catch (\Exception $e) {
            $this->isSubmitting = false;
            $this->addError('submission', 'An error occurred while submitting your idea. Please try again.');
        }
    }

    private function handleAttachments(Idea $idea)
    {
        if (empty($this->attachments)) {
            return;
        }

        foreach ($this->attachments as $attachment) {
            if ($attachment) {
                $filename = time() . '_' . $attachment->getClientOriginalName();
                $path = $attachment->storeAs('idea-attachments/' . $idea->id, $filename, 'public');
                
                $idea->attachments()->create([
                    'filename' => $filename,
                    'original_filename' => $attachment->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $attachment->getMimeType(),
                    'size' => $attachment->getSize(),
                ]);
            }
        }
    }

    public function removeAttachment($index)
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function resetForm()
    {
        $this->reset([
            'title', 'description', 'category_id', 'business_case',
            'expected_impact', 'implementation_timeline', 'resource_requirements',
            'attachments', 'collaboration_enabled'
        ]);
    }
}; ?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 overflow-hidden">
        {{-- Header --}}
        <div class="bg-[#F8EBD5] px-6 py-4 border-b border-[#9B9EA4]/20">
            <h1 class="text-2xl font-bold text-[#231F20]">Submit New Idea</h1>
            <p class="text-sm text-[#9B9EA4] mt-1">Share your innovative ideas to drive KeNHA forward</p>
        </div>

        {{-- Form --}}
        <form wire:submit="submit" class="p-6 space-y-6">
            {{-- Basic Information --}}
            <div class="space-y-4">
                <h2 class="text-lg font-semibold text-[#231F20] border-b border-[#9B9EA4]/20 pb-2">Basic Information</h2>
                
                {{-- Title --}}
                <div>
                    <label for="title" class="block text-sm font-medium text-[#231F20] mb-2">
                        Idea Title <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="title"
                        wire:model="title"
                        placeholder="Enter a clear, descriptive title for your idea"
                        class="w-full px-3 py-2 border border-[#9B9EA4] rounded-md focus:outline-none focus:ring-2 focus:ring-[#FFF200] focus:border-transparent"
                    />
                    @error('title')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Category --}}
                <div>
                    <label for="category_id" class="block text-sm font-medium text-[#231F20] mb-2">
                        Category <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="category_id"
                        wire:model="category_id"
                        class="w-full px-3 py-2 border border-[#9B9EA4] rounded-md focus:outline-none focus:ring-2 focus:ring-[#FFF200] focus:border-transparent"
                    >
                        <option value="">Select a category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Description --}}
                <div>
                    <label for="description" class="block text-sm font-medium text-[#231F20] mb-2">
                        Idea Description <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        id="description"
                        wire:model="description"
                        rows="4"
                        placeholder="Provide a detailed description of your idea, including the problem it solves and how it works"
                        class="w-full px-3 py-2 border border-[#9B9EA4] rounded-md focus:outline-none focus:ring-2 focus:ring-[#FFF200] focus:border-transparent"
                    ></textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Business Case --}}
            <div class="space-y-4">
                <h2 class="text-lg font-semibold text-[#231F20] border-b border-[#9B9EA4]/20 pb-2">Business Case</h2>
                
                {{-- Business Case --}}
                <div>
                    <label for="business_case" class="block text-sm font-medium text-[#231F20] mb-2">
                        Business Justification <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        id="business_case"
                        wire:model="business_case"
                        rows="3"
                        placeholder="Explain why this idea is important for KeNHA and how it aligns with organizational goals"
                        class="w-full px-3 py-2 border border-[#9B9EA4] rounded-md focus:outline-none focus:ring-2 focus:ring-[#FFF200] focus:border-transparent"
                    ></textarea>
                    @error('business_case')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Expected Impact --}}
                <div>
                    <label for="expected_impact" class="block text-sm font-medium text-[#231F20] mb-2">
                        Expected Impact <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        id="expected_impact"
                        wire:model="expected_impact"
                        rows="3"
                        placeholder="Describe the expected benefits, improvements, or outcomes"
                        class="w-full px-3 py-2 border border-[#9B9EA4] rounded-md focus:outline-none focus:ring-2 focus:ring-[#FFF200] focus:border-transparent"
                    ></textarea>
                    @error('expected_impact')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Implementation Details --}}
            <div class="space-y-4">
                <h2 class="text-lg font-semibold text-[#231F20] border-b border-[#9B9EA4]/20 pb-2">Implementation Details</h2>
                
                {{-- Timeline --}}
                <div>
                    <label for="implementation_timeline" class="block text-sm font-medium text-[#231F20] mb-2">
                        Implementation Timeline <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        id="implementation_timeline"
                        wire:model="implementation_timeline"
                        rows="2"
                        placeholder="Estimated timeframe for implementation (e.g., 3 months, 1 year)"
                        class="w-full px-3 py-2 border border-[#9B9EA4] rounded-md focus:outline-none focus:ring-2 focus:ring-[#FFF200] focus:border-transparent"
                    ></textarea>
                    @error('implementation_timeline')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Resource Requirements --}}
                <div>
                    <label for="resource_requirements" class="block text-sm font-medium text-[#231F20] mb-2">
                        Resource Requirements <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        id="resource_requirements"
                        wire:model="resource_requirements"
                        rows="3"
                        placeholder="What resources (budget, personnel, technology, etc.) would be needed?"
                        class="w-full px-3 py-2 border border-[#9B9EA4] rounded-md focus:outline-none focus:ring-2 focus:ring-[#FFF200] focus:border-transparent"
                    ></textarea>
                    @error('resource_requirements')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Attachments --}}
            <div class="space-y-4">
                <h2 class="text-lg font-semibold text-[#231F20] border-b border-[#9B9EA4]/20 pb-2">Supporting Documents</h2>
                
                <div>
                    <label for="attachments" class="block text-sm font-medium text-[#231F20] mb-2">
                        Attachments (Optional)
                    </label>
                    <input
                        type="file"
                        id="attachments"
                        wire:model="attachments"
                        multiple
                        accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png"
                        class="w-full px-3 py-2 border border-[#9B9EA4] rounded-md focus:outline-none focus:ring-2 focus:ring-[#FFF200] focus:border-transparent"
                    />
                    <p class="mt-1 text-xs text-[#9B9EA4]">
                        Supported formats: PDF, Word, PowerPoint, Images. Max size: 10MB per file.
                    </p>
                    @error('attachments.*')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    {{-- Show uploaded files --}}
                    @if($attachments)
                        <div class="mt-3 space-y-2">
                            @foreach($attachments as $index => $attachment)
                                @if($attachment)
                                    <div class="flex items-center justify-between p-2 bg-[#F8EBD5] rounded">
                                        <span class="text-sm text-[#231F20]">{{ $attachment->getClientOriginalName() }}</span>
                                        <button
                                            type="button"
                                            wire:click="removeAttachment({{ $index }})"
                                            class="text-red-600 hover:text-red-800"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Collaboration Settings --}}
            <div class="space-y-4">
                <h2 class="text-lg font-semibold text-[#231F20] border-b border-[#9B9EA4]/20 pb-2">Collaboration</h2>
                
                <div class="flex items-center">
                    <input
                        type="checkbox"
                        id="collaboration_enabled"
                        wire:model="collaboration_enabled"
                        class="h-4 w-4 text-[#FFF200] focus:ring-[#FFF200] border-[#9B9EA4] rounded"
                    />
                    <label for="collaboration_enabled" class="ml-2 block text-sm text-[#231F20]">
                        Enable collaboration on this idea
                    </label>
                </div>
                <p class="text-xs text-[#9B9EA4]">
                    Allow other users to contribute to the development of this idea during the review process.
                </p>
            </div>

            {{-- Form Actions --}}
            <div class="flex items-center justify-between pt-6 border-t border-[#9B9EA4]/20">
                <button
                    type="button"
                    wire:click="saveDraft"
                    class="px-4 py-2 border border-[#9B9EA4] text-[#231F20] rounded-md hover:bg-[#F8EBD5] transition-colors"
                >
                    Save as Draft
                </button>
                
                <div class="flex space-x-3">
                    <button
                        type="button"
                        wire:click="resetForm"
                        class="px-4 py-2 border border-[#9B9EA4] text-[#231F20] rounded-md hover:bg-gray-50 transition-colors"
                    >
                        Reset
                    </button>
                    
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="submit"
                        class="px-6 py-2 bg-[#FFF200] text-[#231F20] rounded-md hover:bg-[#FFF200]/80 transition-colors disabled:opacity-50 flex items-center"
                    >
                        <span wire:loading.remove wire:target="submit">Submit Idea</span>
                        <span wire:loading wire:target="submit" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-[#231F20]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Submitting...
                        </span>
                    </button>
                </div>
            </div>

            @error('submission')
                <div class="p-4 border border-red-200 bg-red-50 rounded-md">
                    <p class="text-sm text-red-600">{{ $message }}</p>
                </div>
            @enderror
        </form>
    </div>
</div>
