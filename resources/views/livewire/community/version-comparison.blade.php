<?php

use Livewire\Volt\Component;
use App\Models\Idea;
use App\Models\IdeaVersion;

new class extends Component {
    public $idea;
    public $versions = [];
    public $selectedVersion1 = null;
    public $selectedVersion2 = null;
    public $showComparison = false;
    public $differences = [];

    public function mount(Idea $idea)
    {
        $this->idea = $idea;
        $this->loadVersions();
    }

    public function loadVersions()
    {
        $this->versions = $this->idea->versions()
            ->with(['createdBy'])
            ->orderBy('version_number', 'desc')
            ->get()
            ->map(function ($version) {
                return [
                    'id' => $version->id,
                    'version' => "v{$version->version_number}",
                    'title' => $version->title,
                    'description' => $version->description,
                    'created_at' => $version->created_at,
                    'created_by' => $version->createdBy,
                    'changes' => $version->notes ?? 'No notes provided',
                    'is_current' => $version->is_current,
                ];
            });

        // Set default selection to latest version
        if ($this->versions->count() > 0) {
            $this->selectedVersion1 = $this->versions->first()['id'];
        }
    }

    public function compareVersions()
    {
        if (!$this->selectedVersion1 || !$this->selectedVersion2) {
            session()->flash('error', 'Please select two versions to compare.');
            return;
        }

        if ($this->selectedVersion1 === $this->selectedVersion2) {
            session()->flash('error', 'Please select different versions to compare.');
            return;
        }

        $version1 = $this->versions->firstWhere('id', $this->selectedVersion1);
        $version2 = $this->versions->firstWhere('id', $this->selectedVersion2);

        if (!$version1 || !$version2) {
            session()->flash('error', 'Invalid version selection.');
            return;
        }

        $this->differences = $this->calculateDifferences($version1, $version2);
        $this->showComparison = true;
    }

    private function calculateDifferences($version1, $version2)
    {
        $differences = [];

        // Compare title
        if ($version1['title'] !== $version2['title']) {
            $differences['title'] = [
                'field' => 'Title',
                'old' => $version1['title'],
                'new' => $version2['title'],
                'type' => 'modified'
            ];
        }

        // Compare description
        if ($version1['description'] !== $version2['description']) {
            $differences['description'] = [
                'field' => 'Description',
                'old' => $version1['description'],
                'new' => $version2['description'],
                'type' => 'modified'
            ];
        }

        return $differences;
    }

    public function resetComparison()
    {
        $this->showComparison = false;
        $this->differences = [];
        $this->selectedVersion2 = null;
    }

    public function createNewVersion($changes = null)
    {
        // Check authorization
        if (!$this->canCreateVersion()) {
            session()->flash('error', 'You are not authorized to create versions.');
            return;
        }

        $lastVersion = $this->idea->versions()->orderBy('version_number', 'desc')->first();
        $nextVersionNumber = $lastVersion ? $lastVersion->version_number + 1 : 1;

        // Mark all previous versions as not current
        $this->idea->versions()->update(['is_current' => false]);

        // Create new version with current idea data
        $newVersion = IdeaVersion::create([
            'idea_id' => $this->idea->id,
            'version_number' => $nextVersionNumber,
            'title' => $this->idea->title,
            'description' => $this->idea->description,
            'category_id' => $this->idea->category_id,
            'notes' => $changes ?? 'Manual save point',
            'created_by' => auth()->id(),
            'is_current' => true,
        ]);

        $this->loadVersions();
        session()->flash('message', 'New version created successfully!');
    }

    public function restoreVersion($versionId)
    {
        $version = IdeaVersion::find($versionId);
        
        if (!$version || $version->idea_id !== $this->idea->id) {
            session()->flash('error', 'Version not found.');
            return;
        }

        // Check authorization
        if (!$this->canRestoreVersion()) {
            session()->flash('error', 'You are not authorized to restore versions.');
            return;
        }

        // Update idea with version data
        $this->idea->update([
            'title' => $version->title,
            'description' => $version->description,
            'category_id' => $version->category_id,
        ]);

        // Mark all versions as not current
        $this->idea->versions()->update(['is_current' => false]);
        
        // Mark restored version as current
        $version->update(['is_current' => true]);

        $this->loadVersions();
        session()->flash('message', "Idea restored to version {$version->version_number}!");
    }

    private function canCreateVersion()
    {
        return $this->idea->author_id === auth()->id() 
            || auth()->user()->hasAnyRole([administrator, 'developer'])
            || auth()->user()->collaborations()->where('idea_id', $this->idea->id)->whereIn('status', ['accepted', 'active'])->exists();
    }

    private function canRestoreVersion()
    {
        return $this->idea->author_id === auth()->id() 
            || auth()->user()->hasAnyRole([administrator, 'developer']);
    }

    public function exportVersion($versionId)
    {
        $version = $this->versions->firstWhere('id', $versionId);
        
        if (!$version) {
            session()->flash('error', 'Version not found.');
            return;
        }

        // In a real implementation, this would generate a downloadable file
        session()->flash('message', "Version {$version['version']} exported successfully!");
    }

    public function getVersionStatusClass($version)
    {
        if ($version['id'] === 1) {
            return 'bg-blue-100 text-blue-800 border-blue-200';
        }
        
        return 'bg-green-100 text-green-800 border-green-200';
    }

    public function getChangeTypeClass($type)
    {
        return match($type) {
            'added' => 'bg-green-100 text-green-800',
            'removed' => 'bg-red-100 text-red-800',
            'modified' => 'bg-yellow-100 text-yellow-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}; ?>

{{-- Enhanced Version History & Comparison with Glass Morphism Design --}}
<div class="min-h-screen relative overflow-hidden">
    {{-- Animated Background Elements --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-[#FFF200]/20 dark:bg-yellow-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-[#F8EBD5]/30 dark:bg-amber-400/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/3 w-64 h-64 bg-[#FFF200]/10 dark:bg-yellow-400/5 rounded-full blur-2xl animate-pulse delay-500"></div>
    </div>

    <div class="relative z-10 space-y-8 max-w-7xl mx-auto p-6">
        {{-- Enhanced Header Section --}}
        <section aria-labelledby="version-header" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Header with Modern Typography --}}
                <div class="p-8 border-b border-gray-100/50 dark:border-zinc-700/50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-[#FFF200] to-[#F8EBD5] rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-[#231F20]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                                </svg>
                            </div>
                            <div>
                                <h3 id="version-header" class="text-2xl font-bold text-[#231F20] dark:text-zinc-100">Version History & Comparison</h3>
                                <p class="text-[#9B9EA4] dark:text-zinc-400 text-sm">Track changes and compare versions</p>
                            </div>
                        </div>
                        
                        @if($this->canRestoreVersion())
                            <button
                                wire:click="createNewVersion('Manual save point')"
                                class="group/btn relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 text-white px-6 py-3 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 font-medium"
                            >
                                <span class="absolute inset-0 bg-gradient-to-br from-emerald-400/20 to-emerald-600/20 opacity-0 group-hover/btn:opacity-100 transition-opacity duration-300"></span>
                                <span class="relative flex items-center space-x-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    <span>Create Save Point</span>
                                </span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Version Comparison Tool --}}
        <section aria-labelledby="comparison-tool" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                {{-- Animated Background --}}
                <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-purple-600/10 dark:from-blue-400/10 dark:via-transparent dark:to-purple-500/20 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                
                <div class="relative p-8">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 dark:from-blue-400 dark:to-purple-500 rounded-2xl flex items-center justify-center shadow-lg">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <h4 id="comparison-tool" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Compare Versions</h4>
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-end">
                        {{-- Base Version Selector --}}
                        <div class="group/field">
                            <label for="version1" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-100 mb-3 uppercase tracking-wider">
                                Base Version
                            </label>
                            <div class="relative">
                                <select
                                    wire:model="selectedVersion1"
                                    id="version1"
                                    class="w-full px-4 py-4 border border-white/20 dark:border-zinc-700/50 rounded-2xl focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white/80 dark:bg-zinc-800/80 backdrop-blur-sm text-[#231F20] dark:text-zinc-100 shadow-lg hover:shadow-xl transition-all duration-300 appearance-none cursor-pointer"
                                >
                                    <option value="">Select base version</option>
                                    @foreach($versions as $version)
                                        <option value="{{ $version['id'] }}">
                                            {{ $version['version'] }} - {{ $version['created_at']->format('M j, Y') }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                                    <svg class="w-5 h-5 text-[#9B9EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Compare Version Selector --}}
                        <div class="group/field">
                            <label for="version2" class="block text-sm font-semibold text-[#231F20] dark:text-zinc-100 mb-3 uppercase tracking-wider">
                                Compare With
                            </label>
                            <div class="relative">
                                <select
                                    wire:model="selectedVersion2"
                                    id="version2"
                                    class="w-full px-4 py-4 border border-white/20 dark:border-zinc-700/50 rounded-2xl focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white/80 dark:bg-zinc-800/80 backdrop-blur-sm text-[#231F20] dark:text-zinc-100 shadow-lg hover:shadow-xl transition-all duration-300 appearance-none cursor-pointer"
                                >
                                    <option value="">Select version to compare</option>
                                    @foreach($versions as $version)
                                        <option value="{{ $version['id'] }}" {{ $version['id'] === $selectedVersion1 ? 'disabled' : '' }}>
                                            {{ $version['version'] }} - {{ $version['created_at']->format('M j, Y') }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                                    <svg class="w-5 h-5 text-[#9B9EA4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Action Buttons --}}
                        <div class="flex space-x-3">
                            <button
                                wire:click="compareVersions"
                                class="group/btn relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 text-white px-6 py-4 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 font-medium flex-1 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                                {{ !$selectedVersion1 || !$selectedVersion2 ? 'disabled' : '' }}
                            >
                                <span class="absolute inset-0 bg-gradient-to-br from-blue-400/20 to-blue-600/20 opacity-0 group-hover/btn:opacity-100 transition-opacity duration-300"></span>
                                <span class="relative">Compare</span>
                            </button>
                            
                            @if($showComparison)
                                <button
                                    wire:click="resetComparison"
                                    class="group/btn relative overflow-hidden rounded-2xl bg-gradient-to-br from-gray-500 to-gray-600 dark:from-gray-400 dark:to-gray-500 text-white px-6 py-4 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 font-medium"
                                >
                                    <span class="absolute inset-0 bg-gradient-to-br from-gray-400/20 to-gray-600/20 opacity-0 group-hover/btn:opacity-100 transition-opacity duration-300"></span>
                                    <span class="relative">Reset</span>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Comparison Results --}}
        @if($showComparison)
            <section aria-labelledby="comparison-results" class="group">
                <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                    <div class="p-8">
                        <div class="flex items-center space-x-4 mb-6">
                            <div class="w-10 h-10 bg-gradient-to-br from-amber-500 to-amber-600 dark:from-amber-400 dark:to-amber-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </div>
                            <h4 id="comparison-results" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Comparison Results</h4>
                        </div>
                        
                        @if(count($differences) > 0)
                            <div class="space-y-6">
                                @foreach($differences as $field => $diff)
                                    <div class="group/diff relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-6 hover:shadow-lg transition-all duration-300">
                                        <div class="flex items-center space-x-3 mb-4">
                                            <span class="px-3 py-1.5 text-xs rounded-full font-medium {{ $this->getChangeTypeClass($diff['type']) }}">
                                                {{ ucfirst($diff['type']) }}
                                            </span>
                                            <h5 class="font-semibold text-[#231F20] dark:text-zinc-100 text-lg">{{ $diff['field'] }}</h5>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                            <div class="group/before">
                                                <h6 class="text-sm font-semibold text-red-700 dark:text-red-400 mb-3 uppercase tracking-wider">Before:</h6>
                                                <div class="p-4 bg-red-50/80 dark:bg-red-900/20 border border-red-200/50 dark:border-red-700/50 rounded-xl text-sm text-[#231F20] dark:text-zinc-100 backdrop-blur-sm">
                                                    {{ $diff['old'] }}
                                                </div>
                                            </div>
                                            <div class="group/after">
                                                <h6 class="text-sm font-semibold text-emerald-700 dark:text-emerald-400 mb-3 uppercase tracking-wider">After:</h6>
                                                <div class="p-4 bg-emerald-50/80 dark:bg-emerald-900/20 border border-emerald-200/50 dark:border-emerald-700/50 rounded-xl text-sm text-[#231F20] dark:text-zinc-100 backdrop-blur-sm">
                                                    {{ $diff['new'] }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-12 relative">
                                <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <h3 class="text-xl font-bold text-[#231F20] dark:text-zinc-100 mb-2">No Differences Found</h3>
                                <p class="text-[#9B9EA4] dark:text-zinc-400">The selected versions are identical.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        {{-- Enhanced Version History List --}}
        <section aria-labelledby="version-history" class="group">
            <div class="relative overflow-hidden rounded-3xl bg-white/70 dark:bg-zinc-800/70 backdrop-blur-xl border border-white/20 dark:border-zinc-700/50 shadow-xl">
                <div class="p-8">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 rounded-2xl flex items-center justify-center shadow-lg">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h4 id="version-history" class="text-xl font-bold text-[#231F20] dark:text-zinc-100">Version History</h4>
                    </div>
                    
                    <div class="space-y-6">
                        @foreach($versions as $version)
                            <div class="group/version relative overflow-hidden rounded-2xl bg-gradient-to-r from-white/50 to-white/30 dark:from-zinc-800/50 dark:to-zinc-700/30 border border-white/40 dark:border-zinc-600/40 backdrop-blur-sm p-6 hover:shadow-lg transition-all duration-500 hover:-translate-y-1">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 dark:from-blue-400 dark:to-purple-500 rounded-2xl flex items-center justify-center text-white font-bold shadow-lg">
                                            {{ $version['version'] }}
                                        </div>
                                        
                                        <div>
                                            <div class="flex items-center space-x-3 mb-2">
                                                <h5 class="font-bold text-[#231F20] dark:text-zinc-100 text-lg">Version {{ $version['version'] }}</h5>
                                                <span class="px-3 py-1 text-xs rounded-full border font-medium {{ $this->getVersionStatusClass($version) }}">
                                                    {{ $version['is_current'] ? 'Current' : ($version['id'] === 1 ? 'Original' : 'Revision') }}
                                                </span>
                                            </div>
                                            <p class="text-sm text-[#9B9EA4] dark:text-zinc-400 mb-2">{{ $version['changes'] }}</p>
                                            <div class="flex items-center space-x-4 text-xs text-[#9B9EA4] dark:text-zinc-500">
                                                <span class="flex items-center space-x-1">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                    </svg>
                                                    <span>{{ $version['created_by']->name }}</span>
                                                </span>
                                                <span class="flex items-center space-x-1">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    <span>{{ $version['created_at']->format('M j, Y \a\t g:i A') }}</span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center space-x-2">
                                        @if($this->canRestoreVersion() && !$version['is_current'])
                                            <button
                                                wire:click="restoreVersion({{ $version['id'] }})"
                                                wire:confirm="Are you sure you want to restore to this version? This will create a new version with these contents."
                                                class="group/btn relative overflow-hidden rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 text-white px-4 py-2 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 font-medium text-sm"
                                            >
                                                <span class="absolute inset-0 bg-gradient-to-br from-blue-400/20 to-blue-600/20 opacity-0 group-hover/btn:opacity-100 transition-opacity duration-300"></span>
                                                <span class="relative">Restore</span>
                                            </button>
                                        @endif
                                        
                                        <button
                                            wire:click="exportVersion({{ $version['id'] }})"
                                            class="group/btn relative overflow-hidden rounded-xl bg-gradient-to-br from-gray-500 to-gray-600 dark:from-gray-400 dark:to-gray-500 text-white px-4 py-2 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 font-medium text-sm"
                                        >
                                            <span class="absolute inset-0 bg-gradient-to-br from-gray-400/20 to-gray-600/20 opacity-0 group-hover/btn:opacity-100 transition-opacity duration-300"></span>
                                            <span class="relative">Export</span>
                                        </button>
                                    </div>
                                </div>
                                
                                {{-- Enhanced Version Preview --}}
                                <div class="mt-6 pt-6 border-t border-gray-200/50 dark:border-zinc-700/50">
                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 text-sm">
                                        <div class="group/field">
                                            <h6 class="font-semibold text-[#231F20] dark:text-zinc-100 mb-2 uppercase tracking-wider text-xs">Title:</h6>
                                            <p class="text-[#9B9EA4] dark:text-zinc-400 line-clamp-2 leading-relaxed">{{ $version['title'] }}</p>
                                        </div>
                                        <div class="group/field">
                                            <h6 class="font-semibold text-[#231F20] dark:text-zinc-100 mb-2 uppercase tracking-wider text-xs">Description:</h6>
                                            <p class="text-[#9B9EA4] dark:text-zinc-400 line-clamp-3 leading-relaxed">{{ Str::limit($version['description'], 150) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- Enhanced Flash Messages with Toast Style --}}
        @if (session()->has('message'))
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform translate-y-2"
                 x-transition:enter-end="opacity-100 transform translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 transform translate-y-0"
                 x-transition:leave-end="opacity-0 transform translate-y-2"
                 class="fixed top-6 right-6 bg-gradient-to-br from-emerald-500 to-emerald-600 dark:from-emerald-400 dark:to-emerald-500 text-white px-6 py-4 rounded-2xl shadow-xl backdrop-blur-xl border border-white/20 z-50 max-w-sm">
                <div class="flex items-center space-x-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="font-medium">{{ session('message') }}</span>
                </div>
            </div>
        @endif

        @if (session()->has('error'))
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform translate-y-2"
                 x-transition:enter-end="opacity-100 transform translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 transform translate-y-0"
                 x-transition:leave-end="opacity-0 transform translate-y-2"
                 class="fixed top-6 right-6 bg-gradient-to-br from-red-500 to-red-600 dark:from-red-400 dark:to-red-500 text-white px-6 py-4 rounded-2xl shadow-xl backdrop-blur-xl border border-white/20 z-50 max-w-sm">
                <div class="flex items-center space-x-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="font-medium">{{ session('error') }}</span>
                </div>
            </div>
        @endif
    </div>
</div>
