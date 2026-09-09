<?php

namespace App\Http\Requests\Pos;

use App\Models\Order;
use App\Models\OrderRound;
use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddOrderLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $restaurant = $this->route('restaurant');
        $order = $this->route('order');
        $round = $this->route('round');

        return $restaurant instanceof Restaurant && $order instanceof Order && $round instanceof OrderRound && $order->restaurant_id === $restaurant->id && $round->order_id === $order->id && $this->user()->can('usePos', $restaurant);
    }

    public function rules(): array
    {
        return ['product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('restaurant_id', $this->route('restaurant')?->id)], 'format_id' => ['nullable', 'integer'], 'quantity' => ['required', 'integer', 'min:1', 'max:99'], 'selections' => ['nullable', 'array'], 'selections.*' => ['array'], 'notes' => ['nullable', 'string', 'max:1000']];
    }
}
