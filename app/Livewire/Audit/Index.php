<?php

namespace App\Livewire\Audit;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public $search = '';

    public function mount()
    {
        Gate::authorize('viewAny', AuditLog::class);
    }

    public function render()
    {
        $logs = AuditLog::with('user')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('ip_address', 'like', "%{$this->search}%")
                      ->orWhere('action', 'like', "%{$this->search}%")
                      ->orWhere('entity_type', 'like', "%{$this->search}%")
                      ->orWhereHas('user', function ($uq) {
                          $uq->where('name', 'like', "%{$this->search}%")
                             ->orWhere('email', 'like', "%{$this->search}%");
                      });
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('livewire.audit.index', [
            'logs' => $logs,
        ]);
    }
}
