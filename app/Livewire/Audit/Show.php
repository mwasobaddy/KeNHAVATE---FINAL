<?php

namespace App\Livewire\Audit;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

class Show extends Component
{
    public AuditLog $log;

    public function mount($id)
    {
        $this->log = AuditLog::with('user')->findOrFail($id);
        Gate::authorize('view', $this->log);
    }

    public function render()
    {
        return view('livewire.audit.show', [
            'log' => $this->log,
        ]);
    }
}
