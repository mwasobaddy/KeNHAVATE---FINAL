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

<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/5 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/20 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/3 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 max-w-4xl mx-auto py-8 px-4 sm:px-6">
        {{-- Main Form Container with Glass Morphism --}}
        <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
            {{-- Header with Modern Design --}}
            <div class="relative overflow-hidden p-8 border-b border-gray-100/50 dark:border-zinc-700/50">
                {{-- Gradient Overlay --}}
                <div class="absolute inset-0 bg-gradient-to-r from-[#FFF200]/10 via-transparent to-[#F8EBD5]/20 dark:from-yellow-400/10 dark:via-transparent dark:to-amber-400/10"></div>
                
                <div class="relative flex items-center space-x-5">
                    <div class="w-14 h-14 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 rounded-2xl flex items-center justify-center shadow-lg">
                        <svg class="w-7 h-7 text-[#231F20] dark:text-zinc-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-[#231F20] dark:text-zinc-100">Submit New Idea</h1>
                        <p class="text-[#9B9EA4] dark:text-zinc-400 text-lg">
                            Share your innovative solutions to transform Kenya's highway infrastructure
                        </p>
                    </div>
                </div>
            </div>

            {{-- Form with Enhanced Styling --}}
            <form wire:submit="submit" class="p-8 space-y-8">
                {{-- Basic Information --}}
                <section aria-labelledby="basic-info-heading" class="group space-y-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 rounded-xl flex items-center justify-center shadow-lg">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h2 id="basic-info-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Basic Information</h2>
                    </div>
                    
                    <div class="ml-14 space-y-5">
                        {{-- Title --}}
                        <div>
                            <label for="title" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-200 mb-2">
                                Idea Title <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="title"
                                wire:model="title"
                                placeholder="Enter a clear, descriptive title for your idea"
                                class="w-full px-4 py-3 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm border border-white/30 dark:border-zinc-700/50 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#FFF200] dark:focus:ring-yellow-400 focus:border-transparent shadow-sm transition-all duration-200"
                            />
                            @error('title')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Category --}}
                        <div>
                            <label for="category_id" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-200 mb-2">
                                Category <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="category_id"
                                wire:model="category_id"
                                class="w-full px-4 py-3 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm border border-white/30 dark:border-zinc-700/50 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#FFF200] dark:focus:ring-yellow-400 focus:border-transparent shadow-sm transition-all duration-200"
                            >
                                <option value="">Select a category</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Description --}}
                        <div>
                            <label for="description" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-200 mb-2">
                                Idea Description <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                id="description"
                                wire:model="description"
                                rows="5"
                                placeholder="Provide a detailed description of your idea, including the problem it solves and how it works"
                                class="w-full px-4 py-3 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm border border-white/30 dark:border-zinc-700/50 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#FFF200] dark:focus:ring-yellow-400 focus:border-transparent shadow-sm transition-all duration-200"
                            ></textarea>
                            @error('description')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </section>

                {{-- Business Case --}}
                <section aria-labelledby="business-case-heading" class="group space-y-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-gradient-to-br from-amber-500 to-amber-600 dark:from-amber-400 dark:to-amber-500 rounded-xl flex items-center justify-center shadow-lg">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h2 id="business-case-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Business Case</h2>
                    </div>
                    
                    <div class="ml-14 space-y-5">
                        {{-- Business Case --}}
                        <div>
                            <label for="business_case" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-200 mb-2">
                                Business Justification <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                id="business_case"
                                wire:model="business_case"
                                rows="3"
                                placeholder="Explain why this idea is important for KeNHA and how it aligns with organizational goals"
                                class="w-full px-4 py-3 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm border border-white/30 dark:border-zinc-700/50 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#FFF200] dark:focus:ring-yellow-400 focus:border-transparent shadow-sm transition-all duration-200"
                            ></textarea>
                            @error('business_case')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Expected Impact --}}
                        <div>
                            <label for="expected_impact" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-200 mb-2">
                                Expected Impact <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                id="expected_impact"
                                wire:model="expected_impact"
                                rows="3"
                                placeholder="Describe the expected benefits, improvements, or outcomes"
                                class="w-full px-4 py-3 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm border border-white/30 dark:border-zinc-700/50 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#FFF200] dark:focus:ring-yellow-400 focus:border-transparent shadow-sm transition-all duration-200"
                            ></textarea>
                            @error('expected_impact')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </section>

                {{-- Implementation Details --}}
                <section aria-labelledby="implementation-heading" class="group space-y-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 rounded-xl flex items-center justify-center shadow-lg">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                            </svg>
                        </div>
                        <h2 id="implementation-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Implementation Details</h2>
                    </div>
                    
                    <div class="ml-14 space-y-5">
                        {{-- Timeline --}}
                        <div>
                            <label for="implementation_timeline" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-200 mb-2">
                                Implementation Timeline <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                id="implementation_timeline"
                                wire:model="implementation_timeline"
                                rows="2"
                                placeholder="Estimated timeframe for implementation (e.g., 3 months, 1 year)"
                                class="w-full px-4 py-3 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm border border-white/30 dark:border-zinc-700/50 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#FFF200] dark:focus:ring-yellow-400 focus:border-transparent shadow-sm transition-all duration-200"
                            ></textarea>
                            @error('implementation_timeline')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Resource Requirements --}}
                        <div>
                            <label for="resource_requirements" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-200 mb-2">
                                Resource Requirements <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                id="resource_requirements"
                                wire:model="resource_requirements"
                                rows="3"
                                placeholder="What resources (budget, personnel, technology, etc.) would be needed?"
                                class="w-full px-4 py-3 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm border border-white/30 dark:border-zinc-700/50 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#FFF200] dark:focus:ring-yellow-400 focus:border-transparent shadow-sm transition-all duration-200"
                            ></textarea>
                            @error('resource_requirements')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </section>

                {{-- Attachments --}}
                <section aria-labelledby="attachments-heading" class="group space-y-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 rounded-xl flex items-center justify-center shadow-lg">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                            </svg>
                        </div>
                        <h2 id="attachments-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Supporting Documents</h2>
                    </div>
                    
                    <div class="ml-14 space-y-5">
                        <div class="relative">
                            <label for="attachments" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-200 mb-2">
                                Attachments (Optional)
                            </label>
                            <div class="relative group/upload">
                                <input
                                    type="file"
                                    id="attachments"
                                    wire:model="attachments"
                                    multiple
                                    accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png"
                                    class="w-full px-4 py-6 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm border-2 border-dashed border-white/30 dark:border-zinc-700/50 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#FFF200] dark:focus:ring-yellow-400 focus:border-transparent shadow-sm transition-all duration-200 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-medium file:bg-[#FFF200]/70 file:text-[#231F20] dark:file:bg-yellow-400/70 dark:file:text-zinc-900 hover:file:bg-[#FFF200] dark:hover:file:bg-yellow-400"
                                />
                                <div class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-0 group-hover/upload:opacity-100 transition-opacity duration-300">
                                    <div class="p-2 bg-[#FFF200]/20 dark:bg-yellow-400/20 rounded-full">
                                        <svg class="w-6 h-6 text-[#231F20] dark:text-zinc-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-[#9B9EA4] dark:text-zinc-400">
                                Supported formats: PDF, Word, PowerPoint, Images. Max size: 10MB per file.
                            </p>
                            @error('attachments.*')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror

                            {{-- Show uploaded files with enhanced styling --}}
                            @if($attachments)
                                <div class="mt-4 space-y-3">
                                    @foreach($attachments as $index => $attachment)
                                        @if($attachment)
                                            <div class="group/file flex items-center justify-between p-3 bg-white/50 dark:bg-zinc-800/50 backdrop-blur-sm border border-white/30 dark:border-zinc-700/50 rounded-xl shadow-sm hover:shadow-md transition-all duration-300">
                                                <div class="flex items-center space-x-3">
                                                    <div class="w-8 h-8 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center">
                                                        <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                        </svg>
                                                    </div>
                                                    <span class="text-sm font-medium text-[#231F20] dark:text-zinc-200 truncate max-w-xs">
                                                        {{ $attachment->getClientOriginalName() }}
                                                    </span>
                                                </div>
                                                <button
                                                    type="button"
                                                    wire:click="removeAttachment({{ $index }})"
                                                    class="p-1.5 text-[#9B9EA4] hover:text-red-600 dark:text-zinc-400 dark:hover:text-red-400 bg-white/50 dark:bg-zinc-700/50 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-200"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </section>

                {{-- Collaboration Settings --}}
                <section aria-labelledby="collaboration-heading" class="group space-y-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-indigo-500 dark:from-blue-400 dark:to-indigo-400 rounded-xl flex items-center justify-center shadow-lg">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <h2 id="collaboration-heading" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Collaboration</h2>
                    </div>
                    
                    <div class="ml-14 space-y-3">
                        <div class="flex items-center">
                            <div class="relative">
                                <input
                                    type="checkbox"
                                    id="collaboration_enabled"
                                    wire:model="collaboration_enabled"
                                    class="peer h-5 w-5 opacity-0 absolute"
                                />
                                <div class="w-5 h-5 border border-[#9B9EA4] dark:border-zinc-600 rounded peer-checked:bg-[#FFF200] dark:peer-checked:bg-yellow-400 peer-checked:border-0 transition-colors duration-200"></div>
                                <svg class="absolute w-3 h-3 text-[#231F20] dark:text-zinc-900 top-1 left-1 pointer-events-none opacity-0 peer-checked:opacity-100 transition-opacity duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <label for="collaboration_enabled" class="ml-3 block text-sm font-medium text-[#231F20] dark:text-zinc-200">
                                Enable collaboration on this idea
                            </label>
                        </div>
                        <div class="bg-blue-50/50 dark:bg-blue-900/20 p-4 rounded-xl">
                            <p class="text-sm text-[#9B9EA4] dark:text-zinc-400">
                                <span class="text-blue-600 dark:text-blue-400 font-medium">Collaboration enabled:</span> Other users can contribute to your idea during the review process, potentially improving its chances of success. You'll remain the primary author.
                            </p>
                        </div>
                    </div>
                </section>

                {{-- Form Actions with Glass Morphism --}}
                <div class="pt-8 border-t border-gray-100/50 dark:border-zinc-700/50">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                        {{-- Draft Button with Enhanced Styling --}}
                        <button
                            type="button"
                            wire:click="saveDraft"
                            class="group relative px-5 py-3 bg-white/70 dark:bg-zinc-800/70 backdrop-blur-sm border border-[#9B9EA4]/30 dark:border-zinc-600/30 text-[#231F20] dark:text-zinc-200 rounded-xl hover:shadow-md transition-all duration-300 hover:-translate-y-0.5 flex items-center justify-center space-x-2"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                            </svg>
                            <span>Save as Draft</span>
                        </button>
                        
                        <div class="flex space-x-4">
                            {{-- Reset Button --}}
                            <button
                                type="button"
                                wire:click="resetForm"
                                class="px-5 py-3 bg-white/70 dark:bg-zinc-800/70 backdrop-blur-sm border border-[#9B9EA4]/30 dark:border-zinc-600/30 text-[#231F20] dark:text-zinc-200 rounded-xl hover:shadow-md transition-all duration-300 hover:-translate-y-0.5 flex items-center justify-center"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                <span class="ml-2">Reset</span>
                            </button>
                            
                            {{-- Submit Button with Gradient --}}
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                wire:target="submit"
                                class="group relative px-6 py-3 bg-gradient-to-r from-[#FFF200] to-[#F8EBD5] dark:from-yellow-400 dark:to-amber-400 hover:from-[#231F20] hover:to-[#231F20] dark:hover:from-zinc-800 dark:hover:to-zinc-700 text-[#231F20] dark:text-zinc-900 hover:text-[#FFF200] dark:hover:text-yellow-400 font-bold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 disabled:opacity-50 disabled:hover:transform-none flex items-center justify-center space-x-2"
                            >
                                <span wire:loading.remove wire:target="submit">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                    </svg>
                                    Submit Idea
                                </span>
                                <span wire:loading wire:target="submit" class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Submitting...
                                </span>
                                
                                {{-- Ripple Effect --}}
                                <div class="absolute inset-0 rounded-xl overflow-hidden">
                                    <div class="absolute inset-0 bg-white/20 dark:bg-yellow-400/20 scale-0 group-hover:scale-100 transition-transform duration-500 rounded-xl"></div>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Error Message with Glass Morphism --}}
                @error('submission')
                    <div class="p-5 bg-red-50/70 dark:bg-red-900/30 backdrop-blur-sm border border-red-200/50 dark:border-red-700/50 rounded-xl flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-800/50 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    </div>
                @enderror
            </form>
        </div>
    </div>
</div>
