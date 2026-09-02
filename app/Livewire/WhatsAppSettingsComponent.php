<?php

namespace App\Livewire;

use App\Models\WhatsAppCommandLog;
use App\Services\ZernioService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class WhatsAppSettingsComponent extends Component
{
    public bool $botEnabled = false;

    public bool $quickMode = false;

    public bool $notifyPostPublished = false;

    public bool $notifyPostFailed = false;

    public bool $notifyNewMentions = false;

    public string $language = 'en';

    public string $phoneInput = '';

    public string $verificationCodeInput = '';

    public array $permissions = [];

    public string $successMessage = '';

    public string $errorMessage = '';

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->botEnabled = (bool) $user->whatsapp_bot_enabled;
        $this->quickMode = (bool) $user->whatsapp_quick_mode;
        $this->notifyPostPublished = (bool) $user->whatsapp_notify_post_published;
        $this->notifyPostFailed = (bool) $user->whatsapp_notify_post_failed;
        $this->notifyNewMentions = (bool) $user->whatsapp_notify_new_mentions;
        $this->language = $user->whatsapp_language ?? 'en';
        $this->phoneInput = $user->whatsapp_phone ?? '';
        $this->permissions = $user->whatsAppPermissions();
    }

    public function sendVerificationCode(): void
    {
        $this->resetMessages();

        $user = Auth::user();
        if (! $user) {
            $this->errorMessage = 'You must be logged in.';

            return;
        }

        $normalized = ZernioService::normalizePhone($this->phoneInput);
        if (! $normalized || strlen($normalized) < 8) {
            $this->errorMessage = 'Enter a valid phone number with country code (e.g. +14155551234).';

            return;
        }

        $taken = \App\Models\User::where('whatsapp_phone', $normalized)
            ->where('id', '!=', $user->id)
            ->whereNotNull('whatsapp_verified_at')
            ->exists();

        if ($taken) {
            $this->errorMessage = 'This WhatsApp number is already linked to another account.';

            return;
        }

        $code = (string) random_int(100000, 999999);

        $user->update([
            'whatsapp_phone' => $normalized,
            'whatsapp_verified_at' => null,
            'whatsapp_verification_code' => $code,
            'whatsapp_verification_expires_at' => now()->addMinutes(15),
        ]);

        $this->phoneInput = $normalized;
        $sentViaTemplate = app(ZernioService::class)->sendVerificationCode($normalized, $code);

        $this->successMessage = $sentViaTemplate
            ? "Verification code sent to WhatsApp. Code: {$code} (expires in 15 min)."
            : "Verification code generated: {$code} (expires in 15 min). Enter it below, or reply on WhatsApp: verify {$code}";
    }

    public function verifyInApp(): void
    {
        $this->resetMessages();

        $user = Auth::user();
        if (! $user) {
            $this->errorMessage = 'You must be logged in.';

            return;
        }

        if ($user->whatsapp_verification_code !== $this->verificationCodeInput) {
            $this->errorMessage = 'Invalid verification code.';

            return;
        }

        if ($user->whatsapp_verification_expires_at && $user->whatsapp_verification_expires_at->isPast()) {
            $this->errorMessage = 'Verification code expired. Send a new code.';

            return;
        }

        $user->update([
            'whatsapp_verified_at' => now(),
            'whatsapp_verification_code' => null,
            'whatsapp_verification_expires_at' => null,
            'whatsapp_bot_enabled' => true,
            'whatsapp_permissions' => $this->permissions ?: $user->defaultWhatsAppPermissions(),
        ]);

        $this->botEnabled = true;
        $this->verificationCodeInput = '';
        $this->successMessage = 'WhatsApp linked successfully! You can now send commands to the bot number.';
    }

    public function saveSettings(): void
    {
        $this->resetMessages();

        $user = Auth::user();
        if (! $user) {
            $this->errorMessage = 'You must be logged in.';

            return;
        }

        if (! $user->isWhatsAppVerified()) {
            $this->errorMessage = 'Link and verify your WhatsApp number first.';

            return;
        }

        $user->update([
            'whatsapp_bot_enabled' => $this->botEnabled,
            'whatsapp_quick_mode' => $this->quickMode,
            'whatsapp_permissions' => $this->permissions,
            'whatsapp_notify_post_published' => $this->notifyPostPublished,
            'whatsapp_notify_post_failed' => $this->notifyPostFailed,
            'whatsapp_notify_new_mentions' => $this->notifyNewMentions,
            'whatsapp_language' => $this->language,
        ]);

        $this->successMessage = 'WhatsApp settings saved.';
    }

    public function unlinkWhatsApp(): void
    {
        $this->resetMessages();

        $user = Auth::user();
        if (! $user) {
            return;
        }

        $user->update([
            'whatsapp_phone' => null,
            'whatsapp_verified_at' => null,
            'whatsapp_bot_enabled' => false,
            'whatsapp_verification_code' => null,
            'whatsapp_verification_expires_at' => null,
            'zernio_conversation_id' => null,
        ]);

        $this->phoneInput = '';
        $this->botEnabled = false;
        $this->quickMode = false;
        $this->permissions = (new \App\Models\User)->defaultWhatsAppPermissions();
        $this->successMessage = 'WhatsApp unlinked.';
    }

    public function getCommandLogsProperty()
    {
        $user = Auth::user();
        if (! $user) {
            return collect();
        }

        return WhatsAppCommandLog::where('user_id', $user->id)
            ->latest()
            ->limit(20)
            ->get();
    }

    public function render()
    {
        $user = Auth::user();

        return view('livewire.whatsapp-settings-component', [
            'user' => $user,
            'botNumber' => config('services.zernio.bot_phone_number'),
            'isVerified' => $user?->isWhatsAppVerified() ?? false,
            'commandLogs' => $this->commandLogs,
        ]);
    }

    protected function resetMessages(): void
    {
        $this->successMessage = '';
        $this->errorMessage = '';
    }
}
