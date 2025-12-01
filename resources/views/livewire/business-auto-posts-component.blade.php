@php
    use Illuminate\Support\Str;
@endphp
<div class="space-y-8">
    <div class="bg-gradient-to-r from-green-600 to-emerald-500 rounded-3xl p-8 text-white shadow-lg">
        <div class="flex items-start justify-between gap-6 flex-col lg:flex-row">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-white/70 mb-2">New Feature</p>
                <h1 class="text-3xl font-semibold mb-3">Business Auto-Pilot Posts</h1>
                <p class="text-white/80 text-sm max-w-3xl leading-relaxed">
                    Describe your product once, choose a tone, and xengager will craft daily posts (and optional visuals) for you.
                    Track everything in the calendar—tap any date to review what went live.
                </p>
            </div>
            <div class="bg-white/10 rounded-2xl px-5 py-3 text-sm text-white/80">
                <p class="font-semibold text-white text-lg">{{ $monthLabel }}</p>
                <p>Auto queue runs daily at 5:00am server time.</p>
            </div>
        </div>
    </div>

    @if($statusMessage)
        <div class="p-4 bg-emerald-50 text-emerald-700 rounded-2xl border border-emerald-100">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="grid lg:grid-cols-3 gap-8">
        <div class="space-y-6">
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Business blueprint</h2>
                    <button type="button" wire:click="createNewProfile" class="text-sm text-green-600 hover:text-green-700">
                        + New profile
                    </button>
                </div>
                <form wire:submit.prevent="saveProfile" class="space-y-4">
                    <div>
                        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Name</label>
                        <input type="text" wire:model.defer="form.name" class="mt-1 w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 focus:ring-2 focus:ring-green-500/30 focus:border-green-600 transition" placeholder="Acme Solar" />
                        @error('form.name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">One-liner</label>
                        <textarea wire:model.defer="form.description" rows="3" class="mt-1 w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 focus:ring-2 focus:ring-green-500/30 focus:border-green-600 transition" placeholder="What do you sell? Who do you help?"></textarea>
                        @error('form.description') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Keywords</label>
                        <input type="text" wire:model.defer="form.keywords" class="mt-1 w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 focus:ring-2 focus:ring-green-500/30 focus:border-green-600 transition" placeholder="solar panels, clean energy, smart home" />
                        <p class="text-xs text-gray-400 mt-1">Comma-separated, helps GPT stay on brand.</p>
                        @error('form.keywords') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Tone</label>
                            <input type="text" wire:model.defer="form.tone" class="mt-1 w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 focus:ring-2 focus:ring-green-500/30 focus:border-green-600 transition" placeholder="Bold, helpful, playful" />
                            @error('form.tone') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Posting time</label>
                            <input type="time" wire:model.defer="form.posting_time" class="mt-1 w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 focus:ring-2 focus:ring-green-500/30 focus:border-green-600 transition" />
                            @error('form.posting_time') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Timezone</label>
                        <select wire:model.defer="form.timezone" class="mt-1 w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 focus:ring-2 focus:ring-green-500/30 focus:border-green-600 transition">
                            @foreach($timezoneOptions as $zone)
                                <option value="{{ $zone }}">{{ $zone }}</option>
                            @endforeach
                        </select>
                        @error('form.timezone') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between rounded-2xl border border-gray-200 px-4 py-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Auto-generate matching image</p>
                                <p class="text-xs text-gray-500">Let GPT create a visual and store it in assets.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" wire:model.defer="form.include_images" class="sr-only peer">
                                <div class="w-12 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-green-400 rounded-full peer peer-checked:bg-green-500 transition"></div>
                                <span class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full shadow transition peer-checked:translate-x-6"></span>
                            </label>
                        </div>
                        <div class="{{ $form['include_images'] ? 'opacity-100' : 'opacity-60' }}">
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1 block">Image style</label>
                            <select wire:model.defer="form.image_style" class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 focus:ring-2 focus:ring-green-500/30 focus:border-green-600 transition" {{ $form['include_images'] ? '' : 'disabled' }}>
                                <option value="natural">Natural (photo-real)</option>
                                <option value="vivid">Vivid (bold & artistic)</option>
                            </select>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-gray-200 px-4 py-3 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Keep this profile running daily</p>
                            <p class="text-xs text-gray-500">Stay active so new posts get generated automatically.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model.defer="form.is_active" class="sr-only peer">
                            <div class="w-12 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-green-400 rounded-full peer peer-checked:bg-green-500 transition"></div>
                            <span class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full shadow transition peer-checked:translate-x-6"></span>
                        </label>
                    </div>
                    <div class="flex gap-3">
                        <button
                            type="submit"
                            wire:target="saveProfile"
                            wire:loading.attr="disabled"
                            class="flex-1 inline-flex justify-center items-center rounded-2xl bg-green-600 text-white py-3 font-semibold hover:bg-green-700 transition cursor-pointer disabled:opacity-70 disabled:cursor-not-allowed"
                        >
                            <span wire:loading.remove wire:target="saveProfile">
                                Save profile
                            </span>
                            <p wire:loading wire:target="saveProfile" class="inline-flex items-center">
                                <svg class="animate-spin h-4 w-4 mr-2" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4"></path>
                                </svg>
                                <span>Saving...</span>
                            </p>
                        </button>
                        <button
                            type="button"
                            wire:click="generateToday"
                            wire:target="generateToday"
                            wire:loading.attr="disabled"
                            class="px-4 py-3 rounded-2xl border border-gray-200 text-gray-700 font-semibold hover:border-green-300 hover:text-green-600 transition cursor-pointer disabled:opacity-70 disabled:cursor-not-allowed inline-flex items-center justify-center"
                        >
                            <span wire:loading.remove wire:target="generateToday">
                                Generate today
                            </span>
                            <p wire:loading wire:target="generateToday" class="inline-flex items-center">
                                <svg class="animate-spin h-4 w-4 mr-2" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4"></path>
                                </svg>
                                <span>Generating...</span>
                            </p>
                        </button>
                    </div>
                </form>
            </div>

            @if($profiles && $profiles->count() > 0)
                <div class="bg-white rounded-3xl p-5 shadow-sm border border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Profiles</h3>
                    <div class="space-y-2">
                        @foreach($profiles as $profile)
                            <div class="flex items-center gap-2">
                                <button wire:click="$set('activeProfileId', {{ $profile->id }})"
                                    class="flex-1 text-left px-4 py-3 rounded-2xl border {{ $activeProfileId === $profile->id ? 'border-green-500 bg-green-50 text-green-700' : 'border-gray-100 text-gray-700 hover:border-green-200' }}">
                                    <p class="text-sm font-semibold">{{ $profile->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $profile->keywordsList }}</p>
                                </button>
                                <button type="button"
                                    wire:click="deleteProfile({{ $profile->id }})"
                                    onclick="return confirm('Delete this profile? All scheduled posts under it will be removed.');"
                                    class="p-2 rounded-xl border border-red-100 text-red-500 hover:bg-red-50 hover:border-red-200 transition">
                                    <i class="bx bx-trash text-base"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <p class="text-xs uppercase tracking-[0.3em] text-gray-400">Calendar</p>
                        <h2 class="text-2xl font-semibold text-gray-900">{{ $monthLabel }}</h2>
                    </div>
                    <div class="flex items-center gap-2">
                        <button wire:click="previousMonth" class="p-2 rounded-2xl border border-gray-200 hover:border-green-300 hover:text-green-600">
                            <i class="bx bx-chevron-left text-xl"></i>
                        </button>
                        <button wire:click="nextMonth" class="p-2 rounded-2xl border border-gray-200 hover:border-green-300 hover:text-green-600">
                            <i class="bx bx-chevron-right text-xl"></i>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-7 text-xs font-semibold text-gray-400 uppercase tracking-[0.2em] mb-3">
                    @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)
                        <div class="text-center py-2">{{ $day }}</div>
                    @endforeach
                </div>

                <div class="grid grid-cols-7 gap-2">
                    @php
                        $statusStyles = [
                            'posted' => 'border-green-600 text-green-700 bg-green-50',
                            'scheduled' => 'border-blue-500 text-blue-600 bg-blue-50',
                            'generating' => 'border-amber-500 text-amber-600 bg-amber-50',
                            'failed' => 'border-red-500 text-red-600 bg-red-50',
                        ];
                    @endphp
                    @foreach($calendarDays as $day)
                        @php
                            $stateClass = $day['in_month'] ? 'bg-white' : 'bg-gray-50 text-gray-400';
                            $statusClass = $day['status'] ? ($statusStyles[$day['status']] ?? 'border-gray-200 text-gray-700 bg-white') : 'border-gray-200 text-gray-700';
                            $isSelected = $selectedDate === $day['date'];
                        @endphp
                        <button wire:click="selectDate('{{ $day['date'] }}')"
                            class="min-h-[90px] rounded-2xl border text-sm flex flex-col items-center justify-center gap-1 transition relative {{ $stateClass }} {{ $statusClass }} {{ $isSelected ? 'ring-2 ring-offset-2 ring-green-500' : '' }}">
                            <span class="text-lg font-semibold">{{ $day['label'] }}</span>
                            @if($day['status'])
                                <span class="text-xs capitalize">{{ $day['status'] }}</span>
                            @else
                                <span class="text-[11px] text-gray-400">pending</span>
                            @endif
                        </button>
                    @endforeach
                </div>

                <div class="flex items-center gap-4 text-xs text-gray-500 mt-6">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-green-400"></span> Posted
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-blue-400"></span> Scheduled
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-amber-400"></span> Generating
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-gray-300"></span> Pending
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-[0.2em]">Selected day</p>
                        <h3 class="text-xl font-semibold text-gray-900">
                            {{ \Carbon\Carbon::parse($selectedDate ?? now())->toFormattedDateString() }}
                        </h3>
                    </div>
                    <div class="flex gap-3">
                        <button
                            wire:click="generateForDate('{{ $selectedDate }}', true)"
                            wire:target="generateForDate('{{ $selectedDate }}', true)"
                            wire:loading.attr="disabled"
                            class="px-4 py-2 rounded-2xl border border-gray-200 text-gray-700 hover:border-green-300 hover:text-green-600 text-sm font-semibold cursor-pointer disabled:opacity-70 disabled:cursor-not-allowed inline-flex items-center justify-center"
                        >
                            <span wire:loading.remove wire:target="generateForDate('{{ $selectedDate }}', true)">
                                Regenerate
                            </span>
                            <p wire:loading wire:target="generateForDate('{{ $selectedDate }}', true)" class="inline-flex items-center">
                                <svg class="animate-spin h-4 w-4 mr-2" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4"></path>
                                </svg>
                                <span>Regenerating...</span>
                            </p>
                        </button>
                        @if($selectedPost && $selectedPost->post_id)
                            <a href="{{ route('queued-posts') }}" class="px-4 py-2 rounded-2xl bg-gray-900 text-white text-sm font-semibold hover:bg-black cursor-pointer">
                                View queue
                            </a>
                        @endif
                    </div>
                </div>

                @if($selectedPost)
                    <div class="space-y-4" wire:key="selected-post-{{ $selectedPost->id }}">
                        <div class="flex items-center gap-2 text-sm">
                            <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $selectedPost->statusBadgeColor() }}">
                                {{ ucfirst($selectedPost->status) }}
                            </span>
                            @if($selectedPost->scheduled_for)
                                <span class="text-gray-500">Scheduled {{ $selectedPost->scheduled_for->timezone($form['timezone'])->format('g:i a T') }}</span>
                            @endif
                            @if($selectedPost->posted_at)
                                <span class="text-green-600 text-sm">Posted {{ $selectedPost->posted_at->diffForHumans() }}</span>
                            @endif
                        </div>
                        <div class="bg-gray-50 rounded-2xl p-4 text-gray-900 leading-relaxed">
                            {{ $selectedPost->content }}
                        </div>
                        @if($selectedPost->image_url)
                            @php
                                $cacheBust = optional($selectedPost->updated_at)->timestamp ?? time();
                                $separator = Str::contains($selectedPost->image_url, '?') ? '&' : '?';
                            @endphp
                            <div wire:key="selected-image-{{ $selectedPost->id }}-{{ $cacheBust }}">
                                <p class="text-xs uppercase text-gray-400 mb-2 tracking-[0.2em]">Image preview</p>
                                <img src="{{ $selectedPost->image_url . $separator . 'v=' . $cacheBust }}" alt="Generated visual" class="rounded-2xl border border-gray-100 shadow-sm">
                            </div>
                        @endif
                        @if($selectedPost->error_message)
                            <div class="p-3 bg-red-50 text-red-600 rounded-2xl text-sm">
                                {{ $selectedPost->error_message }}
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-center py-12 text-gray-500">
                        <p>No post generated for this day yet.</p>
                        <button
                            wire:click="generateForDate('{{ $selectedDate }}')"
                            wire:target="generateForDate('{{ $selectedDate }}')"
                            wire:loading.attr="disabled"
                            class="mt-4 inline-flex items-center px-5 py-2.5 rounded-2xl bg-green-600 text-white font-semibold hover:bg-green-700 cursor-pointer disabled:opacity-70 disabled:cursor-not-allowed"
                        >
                            <span wire:loading.remove wire:target="generateForDate('{{ $selectedDate }}')">
                                Generate now
                            </span>
                            <p wire:loading wire:target="generateForDate('{{ $selectedDate }}')" class="inline-flex items-center">
                                <svg class="animate-spin h-4 w-4 mr-2" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4"></path>
                                </svg>
                                <span>Generating...</span>
                            </p>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

