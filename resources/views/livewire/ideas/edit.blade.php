<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\Idea;
use App\Models\Category;
use App\Services\AuditService;
use Illuminate\Support\Facades\Storage;

new #[Layout('components.layouts.app', title: $idea->title)] class extends Component
{
    use WithFileUploads;

    public Idea $idea;
    public $title = '';
    public $description = '';
    public $category_id = '';
    public $business_case = '';
    public $expected_impact = '';
    public $implementation_timeline = '';
    public $resource_requirements = '';
    public $collaboration_enabled = false;
    public $newAttachments = [];

    public $categories;
    public $isSubmitting = false;

    public function mount(Idea $idea)
    {
        // Check if user can edit this idea
        if ($idea->author_id !== auth()->id() || $idea->current_stage !== 'draft') {
            abort(403, 'You can only edit your own ideas in draft stage.');
        }

        $this->idea = $idea->load(['category', 'attachments']);
        $this->categories = Category::active()->ordered()->get();
        
        // Fill form with existing data
        $this->title = $idea->title;
        $this->description = $idea->description;
        $this->category_id = $idea->category_id;
        $this->business_case = $idea->business_case ?? '';
        $this->expected_impact = $idea->expected_impact ?? '';
        $this->implementation_timeline = $idea->implementation_timeline ?? '';
        $this->resource_requirements = $idea->resource_requirements ?? '';
        $this->collaboration_enabled = $idea->collaboration_enabled;
    }

    public function rules()
    {
        return [
            'title' => 'required|string|max:255|min:10',
            'description' => 'required|string|min:50|max:5000',
            'category_id' => 'required|exists:categories,id',
            'business_case' => 'nullable|string|max:2000',
            'expected_impact' => 'nullable|string|max:1000',
            'implementation_timeline' => 'nullable|string|max:500',
            'resource_requirements' => 'nullable|string|max:1000',
            'newAttachments.*' => 'nullable|file|max:10240|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png',
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

    public function update()
    {
        $this->validate();

        $this->isSubmitting = true;

        try {
            $oldData = $this->idea->toArray();

            $this->idea->update([
                'title' => $this->title,
                'description' => $this->description,
                'category_id' => $this->category_id,
                'business_case' => $this->business_case,
                'expected_impact' => $this->expected_impact,
                'implementation_timeline' => $this->implementation_timeline,
                'resource_requirements' => $this->resource_requirements,
                'collaboration_enabled' => $this->collaboration_enabled,
            ]);

            // Handle new attachments
            $this->handleNewAttachments();

            // Log audit trail
            app(AuditService::class)->log(
                'idea_updated',
                'Idea',
                $this->idea->id,
                $oldData,
                $this->idea->fresh()->toArray()
            );

            session()->flash('message', 'Idea updated successfully!');
            return redirect()->route('ideas.show', $this->idea);

        } catch (\Exception $e) {
            $this->isSubmitting = false;
            $this->addError('update', 'An error occurred while updating your idea. Please try again.');
        }
    }

    private function handleNewAttachments()
    {
        if (empty($this->newAttachments)) {
            return;
        }

        foreach ($this->newAttachments as $attachment) {
            if ($attachment) {
                $filename = time() . '_' . $attachment->getClientOriginalName();
                $path = $attachment->storeAs('idea-attachments/' . $this->idea->id, $filename, 'public');
                
                $this->idea->attachments()->create([
                    'filename' => $filename,
                    'original_filename' => $attachment->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $attachment->getMimeType(),
                    'size' => $attachment->getSize(),
                ]);
            }
        }
    }

    public function removeAttachment($attachmentId)
    {
        $attachment = $this->idea->attachments()->find($attachmentId);
        if ($attachment) {
            // Delete file from storage
            Storage::disk('public')->delete($attachment->path);
            
            // Delete database record
            $attachment->delete();
            
            // Refresh idea with attachments
            $this->idea = $this->idea->fresh(['attachments']);
            
            session()->flash('message', 'Attachment removed successfully.');
        }
    }

    public function removeNewAttachment($index)
    {
        unset($this->newAttachments[$index]);
        $this->newAttachments = array_values($this->newAttachments);
    }
}; ?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-sm border border-[#9B9EA4]/20 overflow-hidden">
        {{-- Header --}}
        <div class="bg-[#F8EBD5] px-6 py-4 border-b border-[#9B9EA4]/20">
            <h1 class="text-2xl font-bold text-[#231F20]">Edit Idea</h1>
            <p class="text-sm text-[#9B9EA4] mt-1">Update your idea details</p>
        </div>

        {{-- Form --}}
        <form wire:submit="update" class="p-6 space-y-6">
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
                        placeholder="Enter a compelling title for your idea..."
                        class="w-full rounded-md border-[#9B9EA4] focus:border-[#FFF200] focus:ring-[#FFF200]"
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
                        class="w-full rounded-md border-[#9B9EA4] focus:border-[#FFF200] focus:ring-[#FFF200]"
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
                        Description <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        id="description"
                        wire:model="description"
                        rows="4"
                        placeholder="Provide a detailed description of your idea..."
                        class="w-full rounded-md border-[#9B9EA4] focus:border-[#FFF200] focus:ring-[#FFF200]"
                    ></textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Business Case --}}
            <div class="space-y-4">
                <h2 class="text-lg font-semibold text-[#231F20] border-b border-[#9B9EA4]/20 pb-2">Business Case</h2>
                
                <div>
                    <label for="business_case" class="block text-sm font-medium text-[#231F20] mb-2">
                        Business Case
                    </label>
                    <textarea
                        id="business_case"
                        wire:model="business_case"
                        rows="4"
                        placeholder="Explain the business justification for this idea..."
                        class="w-full rounded-md border-[#9B9EA4] focus:border-[#FFF200] focus:ring-[#FFF200]"
                    ></textarea>
                    @error('business_case')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="expected_impact" class="block text-sm font-medium text-[#231F20] mb-2">
                        Expected Impact
                    </label>
                    <textarea
                        id="expected_impact"
                        wire:model="expected_impact"
                        rows="3"
                        placeholder="Describe the expected benefits and impact..."
                        class="w-full rounded-md border-[#9B9EA4] focus:border-[#FFF200] focus:ring-[#FFF200]"
                    ></textarea>
                    @error('expected_impact')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Implementation Details --}}
            <div class="space-y-4">
                <h2 class="text-lg font-semibold text-[#231F20] border-b border-[#9B9EA4]/20 pb-2">Implementation</h2>
                
                <div>
                    <label for="implementation_timeline" class="block text-sm font-medium text-[#231F20] mb-2">
                        Implementation Timeline
                    </label>
                    <textarea
                        id="implementation_timeline"
                        wire:model="implementation_timeline"
                        rows="3"
                        placeholder="Outline the proposed timeline for implementation..."
                        class="w-full rounded-md border-[#9B9EA4] focus:border-[#FFF200] focus:ring-[#FFF200]"
                    ></textarea>
                    @error('implementation_timeline')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="resource_requirements" class="block text-sm font-medium text-[#231F20] mb-2">
                        Resource Requirements
                    </label>
                    <textarea
                        id="resource_requirements"
                        wire:model="resource_requirements"
                        rows="3"
                        placeholder="Detail the resources needed (budget, personnel, equipment, etc.)..."
                        class="w-full rounded-md border-[#9B9EA4] focus:border-[#FFF200] focus:ring-[#FFF200]"
                    ></textarea>
                    @error('resource_requirements')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Existing Attachments --}}
            @if($idea->attachments->count() > 0)
                <div class="space-y-4">
                    <h2 class="text-lg font-semibold text-[#231F20] border-b border-[#9B9EA4]/20 pb-2">Current Attachments</h2>
                    <div class="space-y-2">
                        @foreach($idea->attachments as $attachment)
                            <div class="flex items-center justify-between p-3 bg-[#F8EBD5] rounded-lg">
                                <span class="text-sm text-[#231F20]">{{ $attachment->original_filename }}</span>
                                <button
                                    type="button"
                                    wire:click="removeAttachment({{ $attachment->id }})"
                                    class="text-red-600 hover:text-red-800"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- New Attachments --}}
            <div class="space-y-4">
                <h2 class="text-lg font-semibold text-[#231F20] border-b border-[#9B9EA4]/20 pb-2">Add New Attachments</h2>
                
                <div>
                    <label class="block text-sm font-medium text-[#231F20] mb-2">
                        Upload Files <span class="text-xs text-[#9B9EA4]">(PDF, Word, PowerPoint, Images - Max 10MB each)</span>
                    </label>
                    <input
                        type="file"
                        wire:model="newAttachments"
                        multiple
                        accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png"
                        class="w-full text-sm text-[#231F20] file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-[#FFF200] file:text-[#231F20] hover:file:bg-[#FFF200]/80"
                    />
                    @error('newAttachments.*')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    
                    @if($newAttachments)
                        <div class="mt-3 space-y-2">
                            @foreach($newAttachments as $index => $attachment)
                                @if($attachment)
                                    <div class="flex items-center justify-between p-2 bg-[#F8EBD5] rounded">
                                        <span class="text-sm text-[#231F20]">{{ $attachment->getClientOriginalName() }}</span>
                                        <button
                                            type="button"
                                            wire:click="removeNewAttachment({{ $index }})"
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
                <a
                    href="{{ route('ideas.show', $idea) }}"
                    class="px-4 py-2 border border-[#9B9EA4] text-[#231F20] rounded-md hover:bg-[#F8EBD5] transition-colors"
                >
                    Cancel
                </a>
                
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="update"
                    class="px-6 py-2 bg-[#FFF200] text-[#231F20] rounded-md hover:bg-[#FFF200]/80 transition-colors disabled:opacity-50 flex items-center"
                >
                    <span wire:loading.remove wire:target="update">Update Idea</span>
                    <span wire:loading wire:target="update" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-[#231F20]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Updating...
                    </span>
                </button>
            </div>

            @error('update')
                <div class="p-4 border border-red-200 bg-red-50 rounded-md">
                    <p class="text-sm text-red-600">{{ $message }}</p>
                </div>
            @enderror
        </form>
    </div>
</div>
