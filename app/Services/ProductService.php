<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\ProductRepository;
use App\DTOs\Product\ProductFilterDTO;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProductService
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly FileUploadService $fileUploadService
    ) {}

    public function getProducts(ProductFilterDTO $filters): LengthAwarePaginator|Collection
    {
        Log::info('ProductService::getProducts called', [
            'filters' => $filters,
            'user_id' => Auth::id(),
            'company_id' => $filters->company_id
        ]);
        return $this->productRepository->getAllProducts($filters);
    }

    public function getProductById(int $id, int $companyId): ?Product
    {
        Log::info('ProductService::getProductById called', [
            'id' => $id,
            'company_id' => $companyId,
            'user_id' => Auth::id()
        ]);
        return $this->productRepository->findById($id, $companyId);
    }

    public function createProduct(array $data, ?UploadedFile $imageFile = null): Product
    {
        Log::info('ProductService::createProduct started', [
            'product_name' => $data['product_name'] ?? 'unknown',
            'user_id' => Auth::id(),
            'company_id' => $data['company_id']
        ]);

        if ($imageFile) {
            $uploadResult = $this->fileUploadService->uploadDocument($imageFile, 0, 'products', 'product');
            $data['product_image'] = $uploadResult['filename'] ?? null;
            Log::info('Product image uploaded', ['filename' => $data['product_image']]);
        }

        if (!isset($data['created_at'])) {
            $data['created_at'] = now()->format('d-m-Y H:i:s');
        }
        if (!isset($data['status'])) {
            $data['status'] = true;
        }

        $product = $this->productRepository->create($data);
        Log::info('Product created successfully', ['product_id' => $product->product_id]);

        return $product;
    }

    public function updateProduct(int $id, int $companyId, array $data, ?UploadedFile $imageFile = null): ?Product
    {
        Log::info('ProductService::updateProduct started', [
            'product_id' => $id,
            'company_id' => $companyId,
            'user_id' => Auth::id()
        ]);

        $product = $this->productRepository->findById($id, $companyId);
        if (!$product) {
            Log::warning('Product not found for update', [
                'product_id' => $id,
                'user_id' => Auth::id(),
                'company_id' => $companyId
            ]);
            return null;
        }

        if ($imageFile) {
            // Optional: delete old image if exists
            // if ($product->product_image) { ... }

            $uploadResult = $this->fileUploadService->uploadDocument($imageFile, $id, 'products', 'product');
            $data['product_image'] = $uploadResult['filename'] ?? $product->product_image;
            Log::info('Product image updated', [
                'filename' => $data['product_image'],
                'product_id' => $id,
                'user_id' => Auth::id(),
                'company_id' => $companyId
            ]);
        }

        $this->productRepository->update($product, $data);
        Log::info('Product updated successfully', [
            'product_id' => $id,
            'user_id' => Auth::id(),
            'company_id' => $companyId
        ]);

        return $product->fresh(['warehouse', 'category']);
    }

    public function deleteProduct(int $id, int $companyId): bool
    {
        Log::info('ProductService::deleteProduct called', [
            'product_id' => $id,
            'company_id' => $companyId,
            'user_id' => Auth::id()
        ]);

        $product = $this->productRepository->findById($id, $companyId);
        if (!$product) {
            Log::warning('Product not found for deletion', [
                'product_id' => $id,
                'user_id' => Auth::id(),
                'company_id' => $companyId
            ]);
            return false;
        }

        $success = $this->productRepository->delete($product);
        if ($success) {
            Log::info('Product deleted successfully', [
                'product_id' => $id,
                'user_id' => Auth::id(),
                'company_id' => $companyId
            ]);
        }

        return $success;
    }

    public function updateRating(int $id, int $companyId, int $rating): ?Product
    {
        Log::info('ProductService::updateRating called', [
            'product_id' => $id,
            'rating' => $rating,
            'user_id' => Auth::id(),
            'company_id' => $companyId
        ]);

        $product = $this->productRepository->findById($id, $companyId);
        if (!$product) {
            Log::warning('Product not found for rating update', [
                'product_id' => $id,
                'user_id' => Auth::id(),
                'company_id' => $companyId
            ]);
            return null;
        }

        $this->productRepository->update($product, ['product_rating' => $rating]);
        Log::info('Product rating updated', [
            'product_id' => $id,
            'new_rating' => $rating,
            'user_id' => Auth::id(),
            'company_id' => $companyId
        ]);

        return $product->fresh(['warehouse', 'category']);
    }
}
