<?php
/**
 * Cart Controller (API)
 */

class CartController extends Controller
{
    public function index(): void
    {
        $summary = CartService::getCartSummary();
        $this->success($summary);
    }

    public function add(): void
    {
        $input = $this->getInput();
        
        $errors = $this->validate($input, [
            'product_id' => 'required|integer',
            'quantity' => 'integer|min:1',
            'variant_id' => 'integer',
        ]);
        
        if (!empty($errors)) {
            $this->validationError($errors);
        }

        try {
            $item = CartService::addToCart(
                $input['product_id'],
                $input['quantity'] ?? 1,
                $input['variant_id'] ?? null
            );

            if (!$item) {
                $this->error('Failed to add item to cart', 500);
            }

            $summary = CartService::getCartSummary();
            $this->success($summary, 'Item added to cart');
            
        } catch (Exception $e) {
            $this->error($e->getMessage(), 422);
        }
    }

    public function update(string $id): void
    {
        $input = $this->getInput();
        
        $errors = $this->validate($input, [
            'quantity' => 'required|integer|min:0',
        ]);
        
        if (!empty($errors)) {
            $this->validationError($errors);
        }

        $result = CartService::updateQuantity((int)$id, $input['quantity']);
        
        if (!$result) {
            $this->notFound('Cart item not found');
        }

        $summary = CartService::getCartSummary();
        $this->success($summary, 'Cart updated');
    }

    public function remove(string $id): void
    {
        $result = CartService::removeFromCart((int)$id);
        
        if (!$result) {
            $this->notFound('Cart item not found');
        }

        $summary = CartService::getCartSummary();
        $this->success($summary, 'Item removed from cart');
    }

    public function clear(): void
    {
        CartService::clearCart();
        $summary = CartService::getCartSummary();
        $this->success($summary, 'Cart cleared');
    }

    public function setCurrency(): void
    {
        $input = $this->getInput();
        
        $errors = $this->validate($input, [
            'currency' => 'required|in:USD,ZWG',
        ]);
        
        if (!empty($errors)) {
            $this->validationError($errors);
        }

        try {
            CartService::setCurrency($input['currency']);
            $summary = CartService::getCartSummary();
            $this->success($summary, 'Currency updated');
        } catch (Exception $e) {
            $this->error($e->getMessage(), 422);
        }
    }
}