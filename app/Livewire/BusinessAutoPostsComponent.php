<?php

namespace App\Livewire;

use App\Models\BusinessAutoPost;
use App\Models\BusinessAutoProfile;
use App\Services\BusinessAutoPostService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

class BusinessAutoPostsComponent extends Component
{
    public array $form = [
        'name' => '',
        'description' => '',
        'keywords' => '',
        'tone' => 'Friendly',
        'posting_time' => '09:00',
        'timezone' => '',
        'include_images' => false,
        'image_style' => 'natural',
        'is_active' => true,
    ];

    public $profiles;
    public ?int $activeProfileId = null;
    public int $calendarMonth;
    public int $calendarYear;
    public ?string $selectedDate = null;
    public ?BusinessAutoPost $selectedPost = null;
    public array $calendarDays = [];
    public string $statusMessage = '';
    public bool $saving = false;
    public bool $generating = false;

    protected BusinessAutoPostService $generator;
    protected array $imageStyleOptions = [
        'natural' => 'Natural (photo-real, default)',
        'vivid' => 'Vivid (bold & artistic)',
    ];

    public function boot(BusinessAutoPostService $generator): void
    {
        $this->generator = $generator;
    }

    public function mount(): void
    {
        $now = now();
        $this->calendarMonth = $now->month;
        $this->calendarYear = $now->year;
        $this->selectedDate = $now->toDateString();
        $this->form['timezone'] = config('app.timezone');
        $this->loadProfiles();
        $this->refreshCalendar();
    }

    public function loadProfiles(): void
    {
        $this->profiles = BusinessAutoProfile::where('user_id', Auth::id())
            ->orderBy('created_at', 'asc')
            ->get();

        if ($this->profiles->isNotEmpty()) {
            $this->activeProfileId = $this->activeProfileId ?? $this->profiles->first()->id;
            $this->hydrateFormFromProfile($this->profiles->firstWhere('id', $this->activeProfileId));
        } else {
            $this->activeProfileId = null;
        }
    }

    public function hydrateFormFromProfile(?BusinessAutoProfile $profile): void
    {
        if (!$profile) {
            return;
        }

        $this->form = [
            'name' => $profile->name,
            'description' => $profile->description,
            'keywords' => collect($profile->keywords ?? [])->implode(', '),
            'tone' => $profile->tone,
            'posting_time' => $profile->posting_time,
            'timezone' => $profile->timezone,
            'include_images' => $profile->include_images,
            'image_style' => $this->normalizeImageStyle($profile->image_style),
            'is_active' => $profile->is_active,
        ];
    }

    public function updatedActiveProfileId(): void
    {
        $profile = $this->profiles->firstWhere('id', $this->activeProfileId);
        $this->hydrateFormFromProfile($profile);
        $this->refreshCalendar();
    }

    public function createNewProfile(): void
    {
        $this->activeProfileId = null;
        $this->form = [
            'name' => '',
            'description' => '',
            'keywords' => '',
            'tone' => 'Friendly',
            'posting_time' => '09:00',
            'timezone' => config('app.timezone'),
            'include_images' => false,
            'image_style' => 'natural',
            'image_style' => 'natural',
            'is_active' => true,
        ];
        $this->selectedPost = null;
    }

    public function deleteProfile(int $profileId): void
    {
        $profile = BusinessAutoProfile::where('id', $profileId)
            ->where('user_id', Auth::id())
            ->first();

        if (!$profile) {
            return;
        }

        $profile->delete();

        $this->statusMessage = 'Profile deleted successfully.';
        $this->activeProfileId = null;
        $this->selectedPost = null;

        $this->loadProfiles();
        $this->refreshCalendar();
    }

    public function saveProfile(): void
    {
        $this->saving = true;
        $data = $this->validate([
            'form.name' => 'required|string|min:3|max:120',
            'form.description' => 'nullable|string|max:600',
            'form.keywords' => 'required|string|max:255',
            'form.tone' => 'required|string|max:60',
            'form.posting_time' => 'required|date_format:H:i',
            'form.timezone' => 'required|string|max:80',
            'form.include_images' => 'boolean',
            'form.image_style' => 'nullable|string|in:' . implode(',', array_keys($this->imageStyleOptions)),
            'form.is_active' => 'boolean',
        ])['form'];

        $payload = [
            'user_id' => Auth::id(),
            'name' => $data['name'],
            'description' => $data['description'],
            'keywords' => $this->keywordsToArray($data['keywords']),
            'tone' => $data['tone'],
            'posting_time' => $data['posting_time'],
            'timezone' => $data['timezone'],
            'include_images' => $data['include_images'],
            'image_style' => $this->normalizeImageStyle($data['image_style']),
            'is_active' => $data['is_active'],
        ];

        if ($this->activeProfileId) {
            BusinessAutoProfile::where('id', $this->activeProfileId)
                ->where('user_id', Auth::id())
                ->update($payload);
        } else {
            $profile = BusinessAutoProfile::create($payload);
            $this->activeProfileId = $profile->id;
        }

        $this->saving = false;
        $this->statusMessage = 'Profile saved successfully.';
        $this->loadProfiles();
        $this->refreshCalendar();
    }

    protected function keywordsToArray(string $keywords): array
    {
        return collect(explode(',', $keywords))
            ->map(fn ($keyword) => trim($keyword))
            ->filter()
            ->values()
            ->all();
    }

    protected function normalizeImageStyle(?string $style): string
    {
        $style = $style ?: 'natural';
        return array_key_exists($style, $this->imageStyleOptions) ? $style : 'natural';
    }

    public function refreshCalendar(): void
    {
        if (!$this->activeProfileId) {
            $this->calendarDays = [];
            $this->selectedPost = null;
            return;
        }

        $start = Carbon::create($this->calendarYear, $this->calendarMonth)->startOfMonth()->startOfWeek();
        $end = Carbon::create($this->calendarYear, $this->calendarMonth)->endOfMonth()->endOfWeek();

        $posts = BusinessAutoPost::where('business_auto_profile_id', $this->activeProfileId)
            ->whereBetween('post_date', [$start, $end])
            ->get()
            ->keyBy(fn ($post) => $post->post_date->toDateString());

        $days = [];
        $current = $start->copy();

        while ($current->lte($end)) {
            $dateKey = $current->toDateString();
            $post = $posts->get($dateKey);

            $days[] = [
                'date' => $dateKey,
                'label' => $current->day,
                'in_month' => $current->month === $this->calendarMonth,
                'is_today' => $current->isToday(),
                'status' => $post?->status,
                'post' => $post,
            ];

            $current->addDay();
        }

        $this->calendarDays = $days;
        $this->selectDate($this->selectedDate ?? now()->toDateString());
    }

    public function nextMonth(): void
    {
        $date = Carbon::create($this->calendarYear, $this->calendarMonth)->addMonth();
        $this->calendarMonth = $date->month;
        $this->calendarYear = $date->year;
        $this->refreshCalendar();
    }

    public function previousMonth(): void
    {
        $date = Carbon::create($this->calendarYear, $this->calendarMonth)->subMonth();
        $this->calendarMonth = $date->month;
        $this->calendarYear = $date->year;
        $this->refreshCalendar();
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
        $post = BusinessAutoPost::where('business_auto_profile_id', $this->activeProfileId)
            ->whereDate('post_date', $date)
            ->first();
        $this->selectedPost = $post ? $post->fresh() : null;
    }

    public function generateToday(): void
    {
        if (!$this->activeProfileId) {
            return;
        }

        $this->generateForDate($this->selectedDate ?? now()->toDateString(), true);
    }

    public function generateForDate(string $date, bool $force = false): void
    {
        if (!$this->activeProfileId) {
            return;
        }

        $profile = BusinessAutoProfile::find($this->activeProfileId);
        if (!$profile) {
            return;
        }

        $this->generating = true;
        $this->statusMessage = '';

        try {
            $autoPost = $this->generator->generateForDate($profile, Carbon::parse($date), $force);
            $this->statusMessage = 'Post generated successfully for ' . Carbon::parse($date)->toFormattedDateString();
            $this->selectedPost = $autoPost;
        } catch (\Throwable $e) {
            Log::error('Manual auto-post generation failed', [
                'profile_id' => $profile->id,
                'date' => $date,
                'error' => $e->getMessage(),
            ]);
            $this->statusMessage = 'Failed to generate post: ' . $e->getMessage();
        } finally {
            $this->generating = false;
        }

        $this->refreshCalendar();
    }

    public function render()
    {
        return view('livewire.business-auto-posts-component', [
            'timezoneOptions' => $this->timezoneOptions(),
            'monthLabel' => Carbon::create($this->calendarYear, $this->calendarMonth)->format('F Y'),
        ]);
    }

    #[Computed]
    public function hasProfile(): bool
    {
        return $this->profiles && $this->profiles->isNotEmpty();
    }

    protected function timezoneOptions(): array
    {
        return [
            'UTC',
            'Africa/Lagos',
            'Europe/London',
            'Europe/Paris',
            'America/New_York',
            'America/Los_Angeles',
            'America/Chicago',
            'Asia/Singapore',
            'Asia/Tokyo',
            'Australia/Sydney',
        ];
    }
}

