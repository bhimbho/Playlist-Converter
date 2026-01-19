<?php

namespace App\Livewire;

use App\Models\Conversion;
use Livewire\Component;
use Livewire\WithPagination;

class ConversionHistory extends Component
{
    use WithPagination;

    public function render()
    {
        $conversions = Conversion::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('livewire.conversion-history', [
            'conversions' => $conversions,
        ]);
    }
}
