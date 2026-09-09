<?php

namespace App\Http\Requests\Pos;

use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderRound;
use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;

class ChangeOrderLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $restaurant = $this->route('restaurant');
        $order = $this->route('order');
        $round = $this->route('round');
        $line = $this->route('line');

        return $restaurant instanceof Restaurant && $order instanceof Order && $round instanceof OrderRound && $line instanceof OrderLine && $order->restaurant_id === $restaurant->id && $round->order_id === $order->id && $line->order_round_id === $round->id && $this->user()->can('usePos', $restaurant);
    }

    public function rules(): array
    {
        return ['quantity' => ['required', 'integer', 'min:0', 'max:99']];
    }
}
