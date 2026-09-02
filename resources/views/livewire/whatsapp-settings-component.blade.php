<div class="max-w-4xl mx-auto p-6">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
        <div class="flex items-start gap-4 mb-6">
            <div class="p-3 bg-green-100 rounded-2xl">
                <i class="bx bxl-whatsapp text-2xl text-green-600"></i>
            </div>
            <div>
                <h2 class="text-2xl font-bold text-gray-900">WhatsApp Remote Control</h2>
                <p class="text-gray-600 mt-1">
                    Control XEngager from WhatsApp — post tweets, manage your queue, and get ideas on the go.
                </p>
            </div>
        </div>

        @if ($botNumber)
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl">
                <p class="text-sm font-semibold text-green-800">Bot number to message</p>
                <p class="text-lg font-bold text-green-900 mt-1">{{ $botNumber }}</p>
                <p class="text-xs text-green-700 mt-1">Save this contact and send <code class="bg-green-100 px-1 rounded">help</code> after linking.</p>
            </div>
        @endif

        @if ($successMessage)
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">{{ $successMessage }}</div>
        @endif

        @if ($errorMessage)
            <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">{{ $errorMessage }}</div>
        @endif

        {{-- Link WhatsApp --}}
        <div class="border-b border-gray-200 pb-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Link your WhatsApp</h3>

            @if ($isVerified)
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl mb-4">
                    <div>
                        <p class="text-sm text-gray-500">Linked number</p>
                        <p class="font-semibold text-gray-900">{{ $user->whatsapp_phone }}</p>
                        <p class="text-xs text-green-600 mt-1">Verified {{ $user->whatsapp_verified_at?->diffForHumans() }}</p>
                    </div>
                    <button type="button" wire:click="unlinkWhatsApp" wire:confirm="Unlink WhatsApp from this account?"
                        class="px-4 py-2 text-sm text-red-600 border border-red-200 rounded-xl hover:bg-red-50">
                        Unlink
                    </button>
                </div>
            @else
                <div class="space-y-4">
                    <div>
                        <label for="whatsapp-phone" class="form-label">Your WhatsApp number</label>
                        <input type="tel" id="whatsapp-phone" wire:model="phoneInput" placeholder="+14155551234" class="form-input">
                        <p class="form-help">Include country code (E.164 format).</p>
                    </div>
                    <button type="button" wire:click="sendVerificationCode"
                        class="px-5 py-2.5 bg-green-600 text-white rounded-xl hover:bg-green-700 text-sm font-medium">
                        Send verification code
                    </button>

                    @if ($user?->whatsapp_verification_code)
                        <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl">
                            <p class="text-sm text-amber-800 mb-2">Enter the 6-digit code below, or reply on WhatsApp:</p>
                            <code class="text-sm bg-amber-100 px-2 py-1 rounded">verify {{ $user->whatsapp_verification_code }}</code>
                        </div>
                        <div>
                            <label for="whatsapp-code" class="form-label">Verification code</label>
                            <input type="text" id="whatsapp-code" wire:model="verificationCodeInput" maxlength="6" placeholder="123456" class="form-input form-input-sm">
                        </div>
                        <button type="button" wire:click="verifyInApp"
                            class="px-5 py-2.5 bg-green-600 text-white rounded-xl hover:bg-green-700 text-sm font-medium">
                            Verify & link
                        </button>
                    @endif
                </div>
            @endif
        </div>

        @if ($isVerified)
            <form wire:submit.prevent="saveSettings" class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Enable remote control</label>
                        <p class="text-sm text-gray-500">Allow WhatsApp commands to run on your account</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="botEnabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-green-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Quick mode</label>
                        <p class="text-sm text-gray-500">Skip confirmation for post & schedule commands</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="quickMode" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-green-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                    </label>
                </div>

                <div class="border-t border-gray-200 pt-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Proactive alerts</h4>
                    <p class="text-xs text-gray-500 mb-3">Requires an active WhatsApp conversation (message the bot within 24h). Alerts run via scheduler.</p>
                    <div class="space-y-3">
                        <label class="flex items-center justify-between text-sm text-gray-700">
                            <span>Post published</span>
                            <input type="checkbox" wire:model="notifyPostPublished" class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                        </label>
                        <label class="flex items-center justify-between text-sm text-gray-700">
                            <span>Post failed</span>
                            <input type="checkbox" wire:model="notifyPostFailed" class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                        </label>
                        <label class="flex items-center justify-between text-sm text-gray-700">
                            <span>New mentions (every 15 min)</span>
                            <input type="checkbox" wire:model="notifyNewMentions" class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                        </label>
                    </div>
                </div>

                <div>
                    <label for="bot-language" class="form-label">Bot language</label>
                    <select id="bot-language" wire:model="language" class="form-input max-w-xs">
                        <option value="en">English</option>
                        <option value="es">Español</option>
                        <option value="fr">Français</option>
                    </select>
                    <p class="form-help">Or send <code class="bg-gray-100 px-1 rounded">lang es</code> on WhatsApp.</p>
                </div>

                <div>
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Permissions</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach ([
                            'post' => 'Post tweets',
                            'schedule' => 'Schedule posts',
                            'queue' => 'View queue',
                            'delete' => 'Delete scheduled',
                            'ideas' => 'Daily ideas',
                            'generate' => 'Generate ideas',
                            'draft' => 'Save drafts',
                            'mentions' => 'View mentions',
                            'reply' => 'Reply to tweets',
                            'keywords' => 'Manage keywords',
                            'search' => 'Search X',
                            'analytics' => 'Tweet analytics',
                            'automation' => 'Automation toggles',
                            'auto_posts' => 'Business auto posts',
                            'image' => 'AI image generation',
                            'assets' => 'View assets',
                            'notifications' => 'Alert toggles',
                            'thread' => 'Post threads',
                            'bookmarks' => 'Bookmarks',
                        ] as $key => $label)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" wire:model="permissions.{{ $key }}" class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <button type="submit"
                    class="px-6 py-3 bg-green-600 text-white rounded-xl hover:bg-green-700 font-medium">
                    Save settings
                </button>
            </form>
        @endif
    </div>

    {{-- Command cheat sheet --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Command cheat sheet</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm font-mono text-gray-700">
            <div>help</div>
            <div>status</div>
            <div>post: your tweet</div>
            <div>schedule: tomorrow 9am | text</div>
            <div>queue</div>
            <div>delete queue 1</div>
            <div>ideas</div>
            <div>generate: prompt</div>
            <div>draft: text</div>
            <div>drafts</div>
            <div>mentions</div>
            <div>reply 1: text</div>
            <div>keywords</div>
            <div>add keyword: word</div>
            <div>search: query</div>
            <div>analytics 1234567890</div>
            <div>auto posts</div>
            <div>auto posts 1 on</div>
            <div>image: sunset over city</div>
            <div>assets</div>
            <div>notify posts on</div>
            <div>thread: part 1 | part 2</div>
            <div>bookmark: tweet url</div>
            <div>bookmarks</div>
            <div>lang es</div>
            <div>start</div>
            <div>notify mentions on</div>
            <div>settings</div>
            <div>auto mentions on/off</div>
            <div>confirm</div>
            <div>unlink</div>
        </div>
    </div>

    @if ($isVerified && $commandLogs->isNotEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent commands</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b">
                            <th class="pb-2 pr-4">Time</th>
                            <th class="pb-2 pr-4">Command</th>
                            <th class="pb-2 pr-4">Action</th>
                            <th class="pb-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($commandLogs as $log)
                            <tr class="border-b border-gray-100">
                                <td class="py-2 pr-4 text-gray-500 whitespace-nowrap">{{ $log->created_at->diffForHumans() }}</td>
                                <td class="py-2 pr-4 max-w-xs truncate">{{ $log->command }}</td>
                                <td class="py-2 pr-4">{{ $log->parsed_action ?? '—' }}</td>
                                <td class="py-2">
                                    <span @class([
                                        'px-2 py-0.5 rounded-full text-xs font-medium',
                                        'bg-green-100 text-green-700' => $log->status === 'success',
                                        'bg-red-100 text-red-700' => $log->status === 'failed',
                                        'bg-gray-100 text-gray-600' => ! in_array($log->status, ['success', 'failed']),
                                    ])>{{ $log->status }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
