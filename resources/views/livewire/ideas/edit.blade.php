<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\Idea;
use App\Models\Category;
use App\Services\AuditService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\{Layout, Title};

new #[Layout('components.layouts.app')] #[Title('Edit Idea')] class extends Component
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
        
        // Set dynamic title using SEO tools
        if (class_exists('\Artesaos\SEOTools\Facades\SEOTools')) {
            \Artesaos\SEOTools\Facades\SEOTools::setTitle('Edit: ' . $idea->title . ' - KeNHAVATE Innovation Portal');
        }
        
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
            // Log the error for debugging
            \Log::error('Error updating idea: ' . $e->getMessage(), [
                'idea_id' => $this->idea->id,
                'user_id' => auth()->id(),
                'error' => $e->getTraceAsString(),
            ]);
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

<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/5 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/3 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 max-w-4xl mx-auto space-y-6 p-6">
        {{-- Enhanced Header with Glass Morphism --}}
        <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
            {{-- Gradient Overlay --}}
            <div class="absolute inset-0 bg-gradient-to-r from-[#FFF200]/10 via-transparent to-[#F8EBD5]/20 dark:from-yellow-400/10 dark:via-transparent dark:to-amber-400/10"></div>
            
            <div class="relative p-8 flex items-center space-x-4">
                <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                    <svg class="w-6 h-6 text-[#231F20] dark:text-zinc-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                    </svg>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-[#231F20] dark:text-zinc-100">Edit Idea</h1>
                    <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Update your innovation idea details</p>
                </div>
            </div>
        </div>

        {{-- Form Content --}}
        <form wire:submit="update" class="space-y-6">
            {{-- Basic Information --}}
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-purple-500/5 dark:from-blue-400/10 dark:via-transparent dark:to-purple-500/10"></div>
                
                <div class="relative p-6 md:p-8 space-y-6">
                    <div class="flex items-center space-x-3 pb-4 border-b border-white/10 dark:border-zinc-700/30">
                        <div class="w-8 h-8 bg-gradient-to-br from-[#FFF200]/80 to-[#F8EBD5]/80 dark:from-yellow-400/80 dark:to-amber-400/80 rounded-xl flex items-center justify-center shadow">
                            <svg class="w-4 h-4 text-[#231F20] dark:text-zinc-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Basic Information</h2>
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {{-- Title --}}
                        <div class="lg:col-span-2">
                            <label for="title" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-200 mb-2">
                                Idea Title <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input
                                    type="text"
                                    id="title"
                                    wire:model="title"
                                    placeholder="Enter a compelling title for your idea..."
                                    class="w-full rounded-xl border-white/20 dark:border-zinc-600 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:border-[#FFF200] focus:ring-[#FFF200] dark:focus:border-yellow-400 dark:focus:ring-yellow-400 pl-10 pr-4 py-3 shadow-sm"
                                />
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="w-5 h-5 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            @error('title')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Category --}}
                        <div class="lg:col-span-2">
                            <label for="category_id" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-200 mb-2">
                                Category <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <select
                                    id="category_id"
                                    wire:model="category_id"
                                    class="w-full rounded-xl border-white/20 dark:border-zinc-600 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:border-[#FFF200] focus:ring-[#FFF200] dark:focus:border-yellow-400 dark:focus:ring-yellow-400 pl-10 pr-4 py-3 shadow-sm appearance-none"
                                >
                                    <option value="">Select a category</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="w-5 h-5 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                                    </svg>
                                </div>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <svg class="w-5 h-5 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                            </div>
                            @error('category_id')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Description --}}
                        <div class="lg:col-span-2">
                            <label for="description" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-200 mb-2">
                                Description <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <textarea
                                    id="description"
                                    wire:model="description"
                                    rows="4"
                                    placeholder="Provide a detailed description of your idea..."
                                    class="w-full rounded-xl border-white/20 dark:border-zinc-600 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:border-[#FFF200] focus:ring-[#FFF200] dark:focus:border-yellow-400 dark:focus:ring-yellow-400 pl-10 pr-4 py-3 shadow-sm"
                                ></textarea>
                                <div class="absolute top-3 left-0 pl-3 flex items-start pointer-events-none">
                                    <svg class="w-5 h-5 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16m-7 6h7"></path>
                                    </svg>
                                </div>
                            </div>
                            @error('description')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Business Case --}}
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                <div class="absolute inset-0 bg-gradient-to-br from-green-500/5 via-transparent to-teal-500/5 dark:from-green-400/10 dark:via-transparent dark:to-teal-400/10"></div>
                
                <div class="relative p-6 md:p-8 space-y-6">
                    <div class="flex items-center space-x-3 pb-4 border-b border-white/10 dark:border-zinc-700/30">
                        <div class="w-8 h-8 bg-gradient-to-br from-green-400/80 to-teal-400/80 dark:from-green-500/80 dark:to-teal-500/80 rounded-xl flex items-center justify-center shadow">
                            <svg class="w-4 h-4 text-[#231F20] dark:text-zinc-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Business Case</h2>
                    </div>
                    
                    <div class="space-y-6">
                        {{-- Business Case --}}
                        <div>
                            <label for="business_case" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-200 mb-2">
                                Business Case
                            </label>
                            <div class="relative">
                                <textarea
                                    id="business_case"
                                    wire:model="business_case"
                                    rows="4"
                                    placeholder="Explain the business justification for this idea..."
                                    class="w-full rounded-xl border-white/20 dark:border-zinc-600 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:border-[#FFF200] focus:ring-[#FFF200] dark:focus:border-yellow-400 dark:focus:ring-yellow-400 pl-10 pr-4 py-3 shadow-sm"
                                ></textarea>
                                <div class="absolute top-3 left-0 pl-3 flex items-start pointer-events-none">
                                    <svg class="w-5 h-5 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            @error('business_case')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Expected Impact --}}
                        <div>
                            <label for="expected_impact" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-200 mb-2">
                                Expected Impact
                            </label>
                            <div class="relative">
                                <textarea
                                    id="expected_impact"
                                    wire:model="expected_impact"
                                    rows="3"
                                    placeholder="Describe the expected benefits and impact..."
                                    class="w-full rounded-xl border-white/20 dark:border-zinc-600 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:border-[#FFF200] focus:ring-[#FFF200] dark:focus:border-yellow-400 dark:focus:ring-yellow-400 pl-10 pr-4 py-3 shadow-sm"
                                ></textarea>
                                <div class="absolute top-3 left-0 pl-3 flex items-start pointer-events-none">
                                    <svg class="w-5 h-5 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                </div>
                            </div>
                            @error('expected_impact')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Implementation Details --}}
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 via-transparent to-indigo-500/5 dark:from-purple-400/10 dark:via-transparent dark:to-indigo-400/10"></div>
                
                <div class="relative p-6 md:p-8 space-y-6">
                    <div class="flex items-center space-x-3 pb-4 border-b border-white/10 dark:border-zinc-700/30">
                        <div class="w-8 h-8 bg-gradient-to-br from-purple-400/80 to-indigo-400/80 dark:from-purple-500/80 dark:to-indigo-500/80 rounded-xl flex items-center justify-center shadow">
                            <svg class="w-4 h-4 text-[#231F20] dark:text-zinc-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Implementation</h2>
                    </div>
                    
                    <div class="space-y-6">
                        {{-- Implementation Timeline --}}
                        <div>
                            <label for="implementation_timeline" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-200 mb-2">
                                Implementation Timeline
                            </label>
                            <div class="relative">
                                <textarea
                                    id="implementation_timeline"
                                    wire:model="implementation_timeline"
                                    rows="3"
                                    placeholder="Outline the proposed timeline for implementation..."
                                    class="w-full rounded-xl border-white/20 dark:border-zinc-600 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:border-[#FFF200] focus:ring-[#FFF200] dark:focus:border-yellow-400 dark:focus:ring-yellow-400 pl-10 pr-4 py-3 shadow-sm"
                                ></textarea>
                                <div class="absolute top-3 left-0 pl-3 flex items-start pointer-events-none">
                                    <svg class="w-5 h-5 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                            </div>
                            @error('implementation_timeline')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Resource Requirements --}}
                        <div>
                            <label for="resource_requirements" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-200 mb-2">
                                Resource Requirements
                            </label>
                            <div class="relative">
                                <textarea
                                    id="resource_requirements"
                                    wire:model="resource_requirements"
                                    rows="3"
                                    placeholder="Detail the resources needed (budget, personnel, equipment, etc.)..."
                                    class="w-full rounded-xl border-white/20 dark:border-zinc-600 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm focus:border-[#FFF200] focus:ring-[#FFF200] dark:focus:border-yellow-400 dark:focus:ring-yellow-400 pl-10 pr-4 py-3 shadow-sm"
                                ></textarea>
                                <div class="absolute top-3 left-0 pl-3 flex items-start pointer-events-none">
                                    <svg class="w-5 h-5 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            @error('resource_requirements')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Attachments --}}
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 via-transparent to-orange-500/5 dark:from-amber-400/10 dark:via-transparent dark:to-orange-400/10"></div>
                
                <div class="relative p-6 md:p-8 space-y-6">
                    <div class="flex items-center space-x-3 pb-4 border-b border-white/10 dark:border-zinc-700/30">
                        <div class="w-8 h-8 bg-gradient-to-br from-amber-400/80 to-orange-400/80 dark:from-amber-500/80 dark:to-orange-500/80 rounded-xl flex items-center justify-center shadow">
                            <svg class="w-4 h-4 text-[#231F20] dark:text-zinc-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Attachments</h2>
                    </div>
                    
                    {{-- Existing Attachments --}}
                    @if(optional($idea->attachments)->count() > 0)
                        <div>
                            <h3 class="text-sm font-semibold text-[#231F20] dark:text-zinc-200 mb-3">Current Attachments</h3>
                            <div class="space-y-2">
                                @foreach($idea->attachments as $attachment)
                                    <div class="group flex items-center justify-between p-3 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm rounded-xl border border-white/20 dark:border-zinc-700/50 hover:bg-[#F8EBD5]/40 dark:hover:bg-amber-900/20 transition-colors duration-300">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 bg-[#F8EBD5]/60 dark:bg-amber-900/30 rounded-xl flex items-center justify-center">
                                                <svg class="w-4 h-4 text-[#231F20] dark:text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                            </div>
                                            <span class="text-sm text-[#231F20] dark:text-zinc-200 font-medium truncate max-w-xs">
                                                {{ $attachment->original_filename }}
                                            </span>
                                        </div>
                                        <button
                                            type="button"
                                            wire:click="removeAttachment({{ $attachment->id }})"
                                            class="p-2 text-[#9B9EA4] dark:text-zinc-400 hover:text-red-600 dark:hover:text-red-400 transition-colors duration-300"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- New Attachments --}}
                    <div>
                        <h3 class="text-sm font-semibold text-[#231F20] dark:text-zinc-200 mb-3">Add New Attachments</h3>
                        <div class="p-6 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm rounded-xl border border-dashed border-[#9B9EA4]/40 dark:border-zinc-600/40">
                            <label class="flex flex-col items-center justify-center cursor-pointer">
                                <div class="w-12 h-12 bg-[#F8EBD5]/40 dark:bg-amber-900/20 rounded-full flex items-center justify-center mb-3">
                                    <svg class="w-6 h-6 text-[#9B9EA4] dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                    </svg>
                                </div>
                                <div class="text-center">
                                    <p class="text-sm font-medium text-[#231F20] dark:text-zinc-200">Drop files here or click to upload</p>
                                    <p class="text-xs text-[#9B9EA4] dark:text-zinc-400 mt-1">PDF, Word, PowerPoint, Images - Max 10MB each</p>
                                </div>
                                <input
                                    type="file"
                                    wire:model="newAttachments"
                                    multiple
                                    accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png"
                                    class="hidden"
                                />
                            </label>
                        </div>
                        @error('newAttachments.*')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror

                        {{-- Preview New Attachments --}}
                        @if($newAttachments)
                            <div class="mt-4 space-y-2">
                                @foreach($newAttachments as $index => $attachment)
                                    @if($attachment)
                                        <div class="group flex items-center justify-between p-3 bg-[#F8EBD5]/30 dark:bg-amber-900/20 backdrop-blur-sm rounded-xl border border-white/20 dark:border-amber-700/30">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-8 h-8 bg-[#FFF200]/30 dark:bg-yellow-400/30 rounded-xl flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-[#231F20] dark:text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                    </svg>
                                                </div>
                                                <span class="text-sm text-[#231F20] dark:text-zinc-200 font-medium truncate max-w-xs">
                                                    {{ $attachment->getClientOriginalName() }}
                                                </span>
                                            </div>
                                            <button
                                                type="button"
                                                wire:click="removeNewAttachment({{ $index }})"
                                                class="p-2 text-[#9B9EA4] dark:text-zinc-400 hover:text-red-600 dark:hover:text-red-400 transition-colors duration-300"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
            </div>

            {{-- Collaboration Settings --}}
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                <div class="absolute inset-0 bg-gradient-to-br from-pink-500/5 via-transparent to-purple-500/5 dark:from-pink-400/10 dark:via-transparent dark:to-purple-400/10"></div>
                
                <div class="relative p-6 md:p-8 space-y-4">
                    <div class="flex items-center space-x-3 pb-4 border-b border-white/10 dark:border-zinc-700/30">
                        <div class="w-8 h-8 bg-gradient-to-br from-pink-400/80 to-purple-400/80 dark:from-pink-500/80 dark:to-purple-500/80 rounded-xl flex items-center justify-center shadow">
                            <svg class="w-4 h-4 text-[#231F20] dark:text-zinc-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Collaboration Settings</h2>
                    </div>
                    
                    <div class="p-4 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm rounded-xl">
                        <label class="flex items-center cursor-pointer group">
                            <div class="relative">
                                <input
                                    type="checkbox"
                                    id="collaboration_enabled"
                                    wire:model.live="collaboration_enabled"
                                    class="sr-only"
                                />
                                <div class="block w-14 h-8 rounded-full border border-white/20 dark:border-zinc-700/50 bg-white/30 dark:bg-zinc-900/30 group-hover:bg-[#F8EBD5]/20 dark:group-hover:bg-amber-900/20 transition-colors duration-300"></div>
                                <div class="absolute left-1 top-1 w-6 h-6 rounded-full transition-all duration-300 transform 
                                    {{ $collaboration_enabled ? 'bg-[#FFF200] dark:bg-yellow-400 translate-x-6' : 'bg-[#9B9EA4]/50 dark:bg-zinc-600' }}
                                    group-hover:scale-110"></div>
                            </div>
                            <div class="ml-4">
                                <span class="text-sm font-medium text-[#231F20] dark:text-zinc-200">
                                    {{ $collaboration_enabled ? 'Collaboration Enabled' : 'Enable Collaboration' }}
                                </span>
                                <p class="text-xs text-[#9B9EA4] dark:text-zinc-400 mt-1">
                                    Allow other users to contribute to the development of this idea during the review process.
                                </p>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            {{-- Form Actions --}}
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                <div class="absolute inset-0 bg-gradient-to-r from-[#FFF200]/10 via-transparent to-[#F8EBD5]/20 dark:from-yellow-400/10 dark:via-transparent dark:to-amber-400/10"></div>
                
                <div class="relative p-6 md:p-8 flex items-center justify-between">
                    <a
                        href="{{ route('ideas.show', $idea) }}"
                        class="group flex items-center px-5 py-3 border border-[#9B9EA4]/30 dark:border-zinc-600/30 text-[#231F20] dark:text-zinc-200 bg-white/30 dark:bg-zinc-800/30 backdrop-blur-sm rounded-xl hover:bg-[#F8EBD5]/50 dark:hover:bg-amber-900/20 transition-all duration-300 transform hover:-translate-y-1 shadow hover:shadow-md"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Cancel
                    </a>
                    
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="update"
                        class="group flex items-center px-6 py-3 bg-gradient-to-r from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 hover:from-[#231F20] hover:to-[#231F20] dark:hover:from-zinc-800 dark:hover:to-zinc-700 text-[#231F20] dark:text-zinc-900 hover:text-[#FFF200] dark:hover:text-yellow-400 font-bold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="update">Update Idea</span>
                        <span wire:loading wire:target="update" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Updating...
                        </span>
                        <svg class="ml-2 w-5 h-5 transform group-hover:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                    </button>
                </div>
            </div>

            @error('update')
                <div class="relative overflow-hidden rounded-3xl bg-red-50/80 dark:bg-red-900/20 backdrop-blur-xl border border-red-300/50 dark:border-red-700/50 shadow-xl p-6">
                    <div class="flex items-center space-x-3">
                        <div class="shrink-0 w-10 h-10 bg-red-100 dark:bg-red-800/60 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-red-800 dark:text-red-300">Error</h3>
                            <p class="text-sm text-red-700 dark:text-red-400 mt-1">{{ $message }}</p>
                        </div>
                    </div>
                </div>
            @enderror
        </form>
    </div>
</div>
