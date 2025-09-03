<?php

namespace App\Livewire;

use App\Models\Asset;
use App\Services\CloudinaryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithFileUploads;

class AssetsComponent extends Component
{
    use WithFileUploads;

    public $assets = [];
    public $assetUpload;
    public $successMessage = '';
    public $errorMessage = '';

    public function mount()
    {
        $this->loadAssets();
    }

    public function loadAssets()
    {
        $user = Auth::user();
        if ($user) {
            $this->assets = Asset::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
        }
    }

    public function updatedAssetUpload()
    {
        if ($this->assetUpload) {
            $this->uploadAsset();
        }
    }

    public function uploadAsset()
    {
        if (!$this->assetUpload) {
            $this->errorMessage = 'No file selected for upload.';
            return;
        }

        // Check file size based on type
        $file = $this->assetUpload;
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();
        
        // Disable video uploads for now due to Twitter API issues
        if (str_starts_with($mimeType, 'video/')) {
            $this->errorMessage = 'Video uploads are temporarily disabled due to Twitter API limitations. Please use images or GIFs instead.';
            return;
        }
        
        // Images: 5MB max
        $maxSize = 5 * 1024; // 5MB in KB
        $this->validate([
            'assetUpload' => 'required|file|mimes:jpg,jpeg,png,gif|max:' . $maxSize,
        ]);

        $user = Auth::user();
        if (!$user) {
            $this->errorMessage = 'You must be logged in to upload.';
            return;
        }

        try {
            $file = $this->assetUpload;
            $cloudinaryService = new CloudinaryService();
            
            // Upload to Cloudinary - let it determine the type
            $uploadResult = $cloudinaryService->uploadFile($file);
            
            $code = uniqid();
            
            $asset = Asset::create([
                'user_id' => $user->id,
                'type' => $uploadResult['file_type'], // Use the detected type
                'path' => $uploadResult['file_path'], // Store Cloudinary URL
                'original_name' => $uploadResult['original_name'],
                'code' => $code,
            ]);

            $this->assetUpload = null;
            $this->successMessage = ucfirst($uploadResult['file_type']) . ' uploaded to Cloudinary successfully!';
            $this->errorMessage = '';
            $this->loadAssets(); // Refresh the assets list

            Log::info('Asset uploaded to Cloudinary successfully', [
                'user_id' => $user->id,
                'code' => $code,
                'type' => $uploadResult['file_type'],
                'original_name' => $uploadResult['original_name'],
                'cloudinary_url' => $uploadResult['file_path']
            ]);

        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to upload file: ' . $e->getMessage();
            Log::error('Asset upload to Cloudinary failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function deleteAsset($assetId)
    {
        $user = Auth::user();
        if (!$user) {
            $this->errorMessage = 'You must be logged in to delete assets.';
            return;
        }

        $asset = Asset::where('id', $assetId)
            ->where('user_id', $user->id)
            ->first();

        if (!$asset) {
            $this->errorMessage = 'Asset not found.';
            return;
        }

        try {
            // If it's a Cloudinary URL, extract the public_id and delete from Cloudinary
            if (str_contains($asset->path, 'cloudinary.com')) {
                $cloudinaryService = new CloudinaryService();
                // Extract public_id from the URL (this is a simple approach, you might need to store public_id separately)
                $urlParts = parse_url($asset->path);
                $pathParts = explode('/', $urlParts['path']);
                $publicId = end($pathParts);
                $publicId = pathinfo($publicId, PATHINFO_FILENAME); // Remove extension
                
                $cloudinaryService->deleteFile($publicId);
            }

            // Delete the database record
            $asset->delete();

            $this->successMessage = 'Asset deleted successfully!';
            $this->errorMessage = '';
            $this->loadAssets(); // Refresh the assets list

            Log::info('Asset deleted successfully', [
                'user_id' => $user->id,
                'asset_id' => $assetId,
                'code' => $asset->code,
                'path' => $asset->path
            ]);

        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to delete asset: ' . $e->getMessage();
            Log::error('Asset deletion failed', [
                'user_id' => $user->id,
                'asset_id' => $assetId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function copyAssetCode($code)
    {
        // Get the asset to determine its type
        $asset = Asset::where('code', $code)->first();
        if ($asset) {
            $tag = match($asset->type) {
                'video' => 'vid',
                'image' => str_contains($asset->original_name, '.gif') ? 'gif' : 'img',
                default => 'img'
            };
            $fullCode = "[$tag:$code]";
        } else {
            // Fallback to img if asset not found
            $fullCode = "[img:$code]";
        }
        
        $this->dispatch('copy-to-clipboard', code: $fullCode);
        $this->successMessage = 'Asset code copied to clipboard: ' . $fullCode;
    }

    public function render()
    {
        return view('livewire.assets-component');
    }
}