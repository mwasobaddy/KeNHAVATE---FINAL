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

<div class="space-y-6">
    <!-- Version History Header -->
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-[#231F20]">
            Version History & Comparison
        </h3>
        
        @if($this->canRestoreVersion())
            <button
                wire:click="createNewVersion('Manual save point')"
                class="px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 transition-all duration-200 font-medium text-sm"
            >
                Create Save Point
            </button>
        @endif
    </div>

    <!-- Version Comparison Tool -->
    <div class="bg-white/40 backdrop-blur-md rounded-xl border border-white/20 p-6 shadow-lg">
        <h4 class="text-md font-medium text-[#231F20] mb-4">Compare Versions</h4>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div>
                <label for="version1" class="block text-sm font-medium text-[#231F20] mb-2">
                    Base Version
                </label>
                <select
                    wire:model="selectedVersion1"
                    id="version1"
                    class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white/80 backdrop-blur-sm"
                >
                    <option value="">Select base version</option>
                    @foreach($versions as $version)
                        <option value="{{ $version['id'] }}">
                            v{{ $version['version'] }} - {{ $version['created_at']->format('M j, Y') }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            <div>
                <label for="version2" class="block text-sm font-medium text-[#231F20] mb-2">
                    Compare With
                </label>
                <select
                    wire:model="selectedVersion2"
                    id="version2"
                    class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white/80 backdrop-blur-sm"
                >
                    <option value="">Select version to compare</option>
                    @foreach($versions as $version)
                        <option value="{{ $version['id'] }}" {{ $version['id'] === $selectedVersion1 ? 'disabled' : '' }}>
                            v{{ $version['version'] }} - {{ $version['created_at']->format('M j, Y') }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            <div class="flex space-x-2">
                <button
                    wire:click="compareVersions"
                    class="px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium text-sm"
                    {{ !$selectedVersion1 || !$selectedVersion2 ? 'disabled' : '' }}
                >
                    Compare
                </button>
                
                @if($showComparison)
                    <button
                        wire:click="resetComparison"
                        class="px-4 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors font-medium text-sm"
                    >
                        Reset
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Comparison Results -->
    @if($showComparison)
        <div class="bg-white/40 backdrop-blur-md rounded-xl border border-white/20 p-6 shadow-lg">
            <h4 class="text-md font-medium text-[#231F20] mb-4">Comparison Results</h4>
            
            @if(count($differences) > 0)
                <div class="space-y-4">
                    @foreach($differences as $field => $diff)
                        <div class="border border-gray-200 rounded-lg p-4 bg-white/60">
                            <div class="flex items-center space-x-2 mb-3">
                                <span class="px-2 py-1 text-xs rounded-full {{ $this->getChangeTypeClass($diff['type']) }}">
                                    {{ ucfirst($diff['type']) }}
                                </span>
                                <h5 class="font-medium text-[#231F20]">{{ $diff['field'] }}</h5>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <h6 class="text-sm font-medium text-red-700 mb-2">Before:</h6>
                                    <div class="p-3 bg-red-50 border border-red-200 rounded text-sm">
                                        {{ $diff['old'] }}
                                    </div>
                                </div>
                                <div>
                                    <h6 class="text-sm font-medium text-green-700 mb-2">After:</h6>
                                    <div class="p-3 bg-green-50 border border-green-200 rounded text-sm">
                                        {{ $diff['new'] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="text-lg font-medium text-[#231F20] mb-2">No differences found</h3>
                    <p class="text-gray-500">The selected versions are identical.</p>
                </div>
            @endif
        </div>
    @endif

    <!-- Version History List -->
    <div class="space-y-4">
        <h4 class="text-md font-medium text-[#231F20]">Version History</h4>
        
        @foreach($versions as $version)
            <div class="bg-white/40 backdrop-blur-md rounded-xl border border-white/20 p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-gradient-to-r from-blue-600 to-purple-600 rounded-full flex items-center justify-center text-white font-medium">
                            v{{ $version['version'] }}
                        </div>
                        
                        <div>
                            <div class="flex items-center space-x-2 mb-1">
                                <h5 class="font-medium text-[#231F20]">Version {{ $version['version'] }}</h5>
                                <span class="px-2 py-1 text-xs rounded-full border {{ $this->getVersionStatusClass($version) }}">
                                    {{ $version['id'] === 1 ? 'Original' : 'Revision' }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-600">{{ $version['changes'] }}</p>
                            <div class="flex items-center space-x-4 mt-1 text-xs text-gray-500">
                                <span>By {{ $version['created_by']->name }}</span>
                                <span>{{ $version['created_at']->format('M j, Y \a\t g:i A') }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        @if($this->canRestoreVersion() && $version['id'] !== 1)
                            <button
                                wire:click="restoreVersion({{ $version['id'] }})"
                                wire:confirm="Are you sure you want to restore to this version? This will create a new version with these contents."
                                class="px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors"
                            >
                                Restore
                            </button>
                        @endif
                        
                        <button
                            wire:click="exportVersion({{ $version['id'] }})"
                            class="px-3 py-1 text-xs bg-gray-600 text-white rounded hover:bg-gray-700 transition-colors"
                        >
                            Export
                        </button>
                    </div>
                </div>
                
                <!-- Version Preview -->
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <h6 class="font-medium text-[#231F20] mb-1">Title:</h6>
                            <p class="text-gray-700 line-clamp-2">{{ $version['title'] }}</p>
                        </div>
                        <div>
                            <h6 class="font-medium text-[#231F20] mb-1">Description:</h6>
                            <p class="text-gray-700 line-clamp-3">{{ Str::limit($version['description'], 150) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Flash Messages -->
    @if (session()->has('message'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show"
             class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show"
             class="fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            {{ session('error') }}
        </div>
    @endif
</div>
