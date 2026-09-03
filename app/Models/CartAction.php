<?php

namespace App\Models;

use Illuminate\Support\Collection;

class CartAction
{
    public $cart_id;

    public $description;

    public $items;

    public function __construct($cart_id)
    {
        $this->cart_id = $cart_id;
        $this->items = collect([]);
    }

    public function getFinalPrice()
    {
        return $this->items->sum('price_final');
    }

    public function addItem(array $data)
    {
        $this->items->push($data);
        $this->syncToCart();
    }

    public function findItem($id)
    {
        return $this->items->search(function ($item, $key) use ($id) {
            return $item['id'] == $id;
        });
    }

    public function updateItem(array $data)
    {
        $itemIndex = $this->findItem($data['id']);
        if ($itemIndex !== false) {
            $this->items->put($itemIndex, $data);
            $this->syncToCart();
        }
    }

    public function removeItem($item_id)
    {
        $itemIndex = $this->findItem($item_id);
        if ($itemIndex !== false) {
            $this->items->forget($itemIndex);
            $this->syncToCart();
        }
    }

    public function resetItem()
    {
        $this->items = collect([]);
        $this->syncToCart();

        return true;
    }

    public function setDesc($desc)
    {
        $this->description = $desc;
        $this->syncToCart();
    }

    public function syncToCart()
    {
        Cart::updateItem($this);
    }
}
