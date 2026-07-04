<?php

namespace App\Livewire;

use App\Models\Slide;
use Livewire\Component;
use Livewire\Attributes\Lazy;

#[Lazy]
class CustomerReviews extends Component
{
    /**
     * How many latest review images to show in the home-page slider.
     */
    public int $limit = 20;

    /**
     * Skeleton shown while the component lazy-loads, so it never blocks
     * the initial home page paint.
     */
    public function placeholder()
    {
        return view('livewire.customer-reviews-placeholder');
    }

    public function render()
    {
        $query = Slide::where('status', 1);

        $total = (clone $query)->count();

        $reviews = $query->orderByDesc('id')
            ->take($this->limit)
            ->get();

        return view('livewire.customer-reviews', compact('reviews', 'total'));
    }
}
