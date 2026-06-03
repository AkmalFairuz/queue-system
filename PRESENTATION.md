# Pembagian Presentasi Kelompok (5 Orang)

Dokumen ini membagi presentasi berdasarkan source code yang benar-benar sudah diimplementasikan.  
Setiap anggota menjelaskan 1 bagian besar sistem, supaya presentasi tidak tumpang tindih.

Urutan yang disarankan:
1. Struktur sistem, database, dan role
2. Fitur ambil antrian
3. Fitur counter / operasional petugas
4. Fitur admin tenant
5. Fitur display realtime dan TTS

---

<details open>
<summary><strong>Anggota 1: Struktur Sistem, Database, dan Role Akses</strong></summary>

## Anggota 1: Struktur Sistem, Database, dan Role Akses

### Fokus penjelasan
- Menjelaskan entitas utama dalam sistem
- Menjelaskan pembagian role: owner, admin, staff, dan user publik
- Menjelaskan pemisahan area public, counter, dan admin

### Source code yang dijelaskan

#### 1. Struktur route utama
Sumber: [routes/web.php](routes/web.php#L19)

```php
Route::get('/', HomeController::class)->name('home');

Route::get('/tenant/{tenant:code}/antrian', [QueueTicketController::class, 'index'])->name('tenant.queue');
Route::get('/tenant/{tenant:code}/display', [QueueDisplayController::class, 'show'])->name('tenant.display');

Route::middleware(['auth', 'tenant.access:work'])->prefix('/tenant/{tenant}')->group(function () {
    Route::get('/counter', [CounterPageController::class, 'show'])->name('counter.show');
});

Route::middleware(['auth', 'tenant.access:manage'])->prefix('/tenant/{tenant}')->group(function () {
    Route::get('/admin', [AdminDashboardController::class, 'show'])->name('admin.show');
});
```

Yang dijelaskan:
- halaman publik tidak butuh login
- halaman counter butuh login + hak `work`
- halaman admin butuh login + hak `manage`

#### 2. Struktur tabel inti
Sumber: [database/migrations/2026_05_23_115510_create_initial_tables.php](database/migrations/2026_05_23_115510_create_initial_tables.php#L14)

```php
Schema::create('tenant_user', function (Blueprint $table) {
    $table->foreignId('tenant_id')->constrained('tenants', 'id')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained('users', 'id')->cascadeOnDelete();
    $table->enum('role', ['admin', 'staff'])->default('staff');
});

Schema::create('counter_staff', function (Blueprint $table) {
    $table->foreignId('counter_id')->constrained()->cascadeOnDelete();
    $table->foreignId('staff_id')->constrained('users', 'id')->cascadeOnDelete();
});
```

Yang dijelaskan:
- `tenant_user` = relasi user ke tenant, sekaligus menyimpan role
- `counter_staff` = assignment petugas ke counter tertentu

#### 3. Hak akses di middleware
Sumber: [app/Http/Middleware/EnsureTenantAccess.php](app/Http/Middleware/EnsureTenantAccess.php#L12)

```php
public function handle(Request $request, Closure $next, string $scope = 'manage'): Response
{
    $allowed = $scope === 'work'
        ? $user->belongsToTenant($tenant)
        : $user->managesTenant($tenant);
}
```

Yang dijelaskan:
- `work` dipakai untuk petugas counter
- `manage` dipakai untuk owner/admin tenant

#### 4. Logika role di model `User`
Sumber: [app/Models/User.php](app/Models/User.php#L69)

```php
public function belongsToTenant(Tenant $tenant): bool
{
    return $tenant->owner_id === $this->id
        || $this->tenantMemberships()->whereKey($tenant->id)->exists();
}

public function managesTenant(Tenant $tenant): bool
{
    return $tenant->owner_id === $this->id
        || $this->adminTenants()->whereKey($tenant->id)->exists();
}
```

Yang dijelaskan:
- owner otomatis bisa semuanya
- admin bisa manage tenant
- staff hanya anggota tenant, bukan pengelola

### Saran demo
- buka halaman home
- tunjukkan tenant list
- login sebagai staff lalu tunjukkan tidak bisa masuk admin

### Referensi pengujian
- [tests/Feature/TenantAccessTest.php](tests/Feature/TenantAccessTest.php#L14)
- [tests/Feature/HomePageAccessTest.php](tests/Feature/HomePageAccessTest.php#L14)

</details>

---

<details>
<summary><strong>Anggota 2: Fitur Ambil Antrian</strong></summary>

## Anggota 2: Fitur Ambil Antrian

### Fokus penjelasan
- Menjelaskan flow ambil antrian dari halaman publik
- Menjelaskan pemilihan layanan, tanggal, dan jadwal
- Menjelaskan kuota dan pre-queue

### Source code yang dijelaskan

#### 1. Controller halaman ambil antrian
Sumber: [app/Http/Controllers/Public/QueueTicketController.php](app/Http/Controllers/Public/QueueTicketController.php#L19)

```php
public function index(Tenant $tenant, TicketIssuer $ticketIssuer): View
{
    $services = $tenant->services()
        ->with('schedules')
        ->orderBy('name')
        ->get()
        ->map(function (Service $service) use ($ticketIssuer, $now) {
            $queueableSchedules = $ticketIssuer->resolveQueueableSchedules($service, $now);

            return [
                'id' => $service->id,
                'name' => $service->name,
                'is_open' => $queueableSchedules->isNotEmpty(),
            ];
        });
}
```

Yang dijelaskan:
- halaman pertama hanya memilih layanan
- status buka/tutup tidak hardcoded, tapi dihitung dari jadwal

#### 2. Detail layanan: tanggal dan jadwal
Sumber: [app/Http/Controllers/Public/QueueTicketController.php](app/Http/Controllers/Public/QueueTicketController.php#L48)

```php
$dateOptions = $queueableSchedules
    ->groupBy(fn (array $option) => $option['service_date']->toDateString())
    ->map(function ($options, string $dateKey) use ($now): array {
        $serviceDate = $options->first()['service_date'];

        return [
            'key' => $dateKey,
            'label' => $this->serviceDateLabel($serviceDate, $now),
        ];
    });
```

Yang dijelaskan:
- setelah pilih layanan, user memilih tanggal
- tanggal yang muncul berasal dari schedule yang memang available

#### 3. Sisa kuota per jadwal
Sumber: [app/Http/Controllers/Public/QueueTicketController.php](app/Http/Controllers/Public/QueueTicketController.php#L78)

```php
$remainingQuota = $schedule->max_tickets !== null
    ? max($schedule->max_tickets - $issuedCount, 0)
    : null;
```

Yang dijelaskan:
- kalau jadwal punya kuota, sistem hitung sisa slot
- hasilnya ditampilkan ke dropdown jadwal

#### 4. Logika pembuatan tiket
Sumber: [app/Services/TicketIssuer.php](app/Services/TicketIssuer.php#L83)

```php
$issuedCount = (clone $dailyTicketsQuery)->count();

if ($schedule->max_tickets !== null && $issuedCount >= $schedule->max_tickets) {
    throw ValidationException::withMessages([
        $errorKey => 'Kuota antrian untuk hari ini sudah habis.',
    ]);
}

$nextSequence = ((clone $dailyTicketsQuery)->max('sequence') ?? 0) + 1;
```

Yang dijelaskan:
- ticket number tidak diisi manual
- sequence otomatis dihitung berdasarkan tanggal layanan dan schedule

#### 5. Logika pre-queue
Sumber: [app/Models/ServiceSchedule.php](app/Models/ServiceSchedule.php#L56)

```php
$queueOpensAt = $opensAt->copy()->subMinutes($this->pre_queue_minutes);

if ($time->lt($queueOpensAt) || $time->gt($closesAt)) {
    return null;
}

'is_pre_queue' => $time->lt($opensAt),
```

Yang dijelaskan:
- antrian bisa dibuka sebelum jam layanan mulai
- contoh: besok jam 09.00, tapi boleh ambil antrian dari hari sebelumnya

#### 6. Frontend date picker dan submit
Sumber: [resources/js/pages/public-ticket.js](resources/js/pages/public-ticket.js#L96)

```js
const scheduleOptions = buildScheduleOptions(service, selectedDateKey);

details.innerHTML = `
    <form id="queue-ticket-form" class="grid gap-5">
        ...
        <select name="service_schedule_id" class="field" required>
            ${scheduleOptions}
        </select>
    </form>
`;
```

Yang dijelaskan:
- frontend menerima data dari backend
- lalu render date picker dan dropdown jadwal yang sesuai tanggal

### Saran demo
- buka `/tenant/{code}/antrian`
- pilih satu layanan
- pilih tanggal dan jam
- submit sampai keluar halaman hasil tiket

### Referensi pengujian
- [tests/Feature/PublicTicketTest.php](tests/Feature/PublicTicketTest.php#L18)

</details>

---

<details>
<summary><strong>Anggota 3: Fitur Counter / Operasional Petugas</strong></summary>

## Anggota 3: Fitur Counter / Operasional Petugas

### Fokus penjelasan
- Menjelaskan bagaimana petugas memanggil tiket
- Menjelaskan perubahan status tiket
- Menjelaskan pembatasan staff hanya ke counter yang ditugaskan

### Source code yang dijelaskan

#### 1. Context counter dan layanan disimpan di session
Sumber: [app/Http/Controllers/Counter/CounterWorkflowController.php](app/Http/Controllers/Counter/CounterWorkflowController.php#L15)

```php
$request->session()->put($this->counterKey($tenant), $validated['counter_id']);
$request->session()->put($this->serviceKey($tenant), $validated['service_id']);
```

Yang dijelaskan:
- petugas memilih counter aktif dan layanan aktif dulu
- pilihan itu disimpan di session, jadi aksi berikutnya tahu sedang bekerja di counter mana

#### 2. Panggil tiket berikutnya
Sumber: [app/Services/CounterWorkflow.php](app/Services/CounterWorkflow.php#L17)

```php
$currentTicket = $this->currentTicketQuery($counter)->lockForUpdate()->first();

if ($currentTicket) {
    throw ValidationException::withMessages([
        'counter_id' => 'Selesaikan tiket aktif di counter ini terlebih dahulu.',
    ]);
}

$nextTicket->update([
    'counter_id' => $counter->id,
    'status' => TicketStatus::Called,
    'called_at' => now(),
]);
```

Yang dijelaskan:
- tidak boleh panggil tiket baru kalau tiket lama belum selesai
- saat dipanggil, status berubah jadi `called`

#### 3. Mulai layani dan selesai
Sumber: [app/Services/CounterWorkflow.php](app/Services/CounterWorkflow.php#L62)

```php
$ticket->update([
    'status' => TicketStatus::Serving,
    'serving_started_at' => now(),
]);
```

Dan:

```php
return $this->finish($tenant, $counter, TicketStatus::Completed, 'completed_at', 'ticket-completed');
```

Yang dijelaskan:
- `called` berarti baru dipanggil
- `serving` berarti benar-benar sedang dilayani
- `completed` berarti pelayanan selesai

#### 4. Recall / panggil ulang
Sumber: [app/Services/CounterWorkflow.php](app/Services/CounterWorkflow.php#L104)

```php
public function recall(Tenant $tenant, Counter $counter): Ticket
{
    $ticket = $this->currentTicketQuery($counter)->first();
    $this->broadcastUpdate($tenant->id, 'ticket-recalled', $counter->id, $ticket->id);
    return $ticket;
}
```

Yang dijelaskan:
- panggil ulang tidak mengubah status tiket
- hanya mengirim event lagi ke display untuk memutar suara ulang

#### 5. Staff hanya boleh akses counter tertentu
Sumber: [app/Models/User.php](app/Models/User.php#L81)

```php
public function canAccessCounter(Tenant $tenant, Counter $counter): bool
{
    return $this->managesTenant($tenant)
        || $this->assignedCounters()->whereKey($counter->id)->exists();
}
```

Yang dijelaskan:
- owner/admin bebas akses
- staff hanya boleh counter yang assigned

#### 6. UI action button di halaman counter
Sumber: [resources/js/pages/counter.js](resources/js/pages/counter.js#L145)

```js
<button type="button" class="btn btn-secondary" data-action="recall" ${disableIf(!current || current.status !== 'called')}>
    Panggil Ulang
</button>
<button type="button" class="btn btn-info" data-action="start" ${disableIf(!current || current.status !== 'called')}>
    Mulai Layani
</button>
<button type="button" class="btn btn-success" data-action="complete" ${disableIf(!current || current.status !== 'serving')}>
    Selesaikan
</button>
```

Yang dijelaskan:
- tombol dibuat mengikuti status tiket
- jadi operasional lebih aman, tidak semua aksi boleh kapan saja

### Saran demo
- login sebagai staff
- pilih counter yang assigned
- panggil tiket
- mulai layani
- selesaikan

### Referensi pengujian
- [tests/Feature/CounterWorkflowTest.php](tests/Feature/CounterWorkflowTest.php#L19)
- [tests/Feature/CounterAssignmentTest.php](tests/Feature/CounterAssignmentTest.php#L14)

</details>

---

<details>
<summary><strong>Anggota 4: Fitur Admin Tenant</strong></summary>

## Anggota 4: Fitur Admin Tenant

### Fokus penjelasan
- Menjelaskan halaman admin untuk mengelola tenant
- Menjelaskan CRUD layanan, jadwal, counter, akses user, dan pengaturan tenant
- Menjelaskan pagination server-side dan modal form

### Source code yang dijelaskan

#### 1. Controller admin multi-page
Sumber: [app/Http/Controllers/Admin/AdminDashboardController.php](app/Http/Controllers/Admin/AdminDashboardController.php#L15)

```php
public function show(Request $request, Tenant $tenant, DashboardDataService $dashboardData): View
{
    return $this->renderPage($request, $tenant, $dashboardData, 'overview', 'Ringkasan');
}

public function services(Request $request, Tenant $tenant, DashboardDataService $dashboardData): View
{
    return $this->renderPage($request, $tenant, $dashboardData, 'services', 'Layanan');
}
```

Yang dijelaskan:
- admin area dipisah jadi beberapa page/section
- bukan 1 halaman besar campur semua

#### 2. Tab admin dan dataset frontend
Sumber: [resources/views/admin/page.blade.php](resources/views/admin/page.blade.php#L26)

```blade
<div class="tabs rounded-md border border-stone-200 px-2 pb-0 pt-2">
    <a href="{{ route('admin.show', $tenant->id) }}" class="tab-button {{ $navSection === 'overview' ? 'is-active' : '' }}">
        <span>Ringkasan</span>
    </a>
    <a href="{{ route('admin.services.page', $tenant->id) }}" class="tab-button {{ $navSection === 'services' ? 'is-active' : '' }}">
        <span>Layanan</span>
    </a>
</div>
```

Yang dijelaskan:
- navigasi admin dibuat seperti tab
- backend kirim `initialData`, frontend render sesuai section

#### 3. Snapshot admin + pagination
Sumber: [app/Services/DashboardDataService.php](app/Services/DashboardDataService.php#L149)

```php
$servicesPaginator = $this->paginateQuery(
    $tenant->services()->withCount('schedules')->orderBy('name'),
    10,
    (int) ($pageOptions['services_page'] ?? 1),
    'services_page',
);
```

Yang dijelaskan:
- pagination dilakukan di backend
- frontend hanya menerima halaman yang sedang diminta

#### 4. Render section admin
Sumber: [resources/js/pages/admin/render.js](resources/js/pages/admin/render.js#L10)

```js
export function renderAdminSection({ root, section, snapshot }) {
    if (section === 'overview') {
        ...
    }

    if (section === 'services') {
        ...
    }

    if (section === 'counters') {
        ...
    }
}
```

Yang dijelaskan:
- setelah refactor, file admin dibagi lebih rapi
- `admin.js` untuk orchestration
- `render.js` untuk tampilan
- `modals.js` untuk form modal

#### 5. Modal create/edit layanan
Sumber: [resources/js/pages/admin/modals.js](resources/js/pages/admin/modals.js#L7)

```js
export function openServiceModal(context, service = null) {
    openFormModal(
        service ? 'Ubah Layanan' : 'Tambah Layanan',
        `
            <form id="service-form" class="grid gap-4">
                <input name="name" class="field" value="${escapeHtml(service?.name ?? '')}" required>
                <input name="ticket_prefix" class="field" maxlength="10" value="${escapeHtml(service?.ticket_prefix ?? '')}" required>
            </form>
        `,
    );
}
```

Yang dijelaskan:
- CRUD admin banyak dilakukan lewat modal
- lebih cepat tanpa pindah halaman

#### 6. Assignment staff ke counter
Sumber: [app/Http/Controllers/Admin/CounterController.php](app/Http/Controllers/Admin/CounterController.php#L14)

```php
$counter = $tenant->counters()->create(collect($validated)->except('staff_ids')->all());
$counter->staff()->sync($validated['staff_ids'] ?? []);
```

Yang dijelaskan:
- admin tidak hanya membuat counter
- admin juga assign petugas ke counter tersebut

#### 7. Tambah akses admin/staff
Sumber: [app/Http/Controllers/Admin/TenantAdminController.php](app/Http/Controllers/Admin/TenantAdminController.php#L15)

```php
$tenant->users()->syncWithoutDetaching([
    $user->id => ['role' => $validated['role']],
]);
```

Yang dijelaskan:
- admin bisa menambahkan user ke tenant
- role disimpan di pivot `tenant_user`

### Saran demo
- buka tab layanan
- tambah layanan
- buka jadwal layanan
- tambah counter dan assign staff
- buka tab akses

### Referensi pengujian
- [tests/Feature/AdminSnapshotPaginationTest.php](tests/Feature/AdminSnapshotPaginationTest.php#L20)

</details>

---

<details>
<summary><strong>Anggota 5: Queue Display, Realtime, dan TTS</strong></summary>

## Anggota 5: Queue Display, Realtime, dan TTS

### Fokus penjelasan
- Menjelaskan layar display antrian
- Menjelaskan update realtime
- Menjelaskan antrian TTS supaya suara tidak overlap

### Source code yang dijelaskan

#### 1. Event broadcast realtime
Sumber: [app/Events/QueueDisplayUpdated.php](app/Events/QueueDisplayUpdated.php#L10)

```php
class QueueDisplayUpdated implements ShouldBroadcastNow
{
    public function broadcastOn(): Channel
    {
        return new Channel('tenant.'.$this->tenantId.'.display');
    }

    public function broadcastAs(): string
    {
        return 'queue.display.updated';
    }
}
```

Yang dijelaskan:
- setiap perubahan penting mengirim event ke channel tenant
- display dan counter sama-sama bisa subscribe

#### 2. Data display dari backend
Sumber: [app/Services/DashboardDataService.php](app/Services/DashboardDataService.php#L46)

```php
'services' => $services->map(function (Service $service) use ($lastCalledTicketsByService, $serviceStats, $tenant) {
    $ticket = $lastCalledTicketsByService->get($service->id);

    return [
        'id' => $service->id,
        'name' => $service->name,
        'stats' => $serviceStats->get($service->id, $this->emptyTicketStats()),
        'last_called_ticket' => $ticket ? $this->ticketPayload($ticket, $tenant) : null,
    ];
})->all(),
```

Yang dijelaskan:
- display fokus ke per-service
- tiap service punya stats dan last called ticket sendiri

#### 3. Payload TTS dari ticket
Sumber: [app/Services/DashboardDataService.php](app/Services/DashboardDataService.php#L440)

```php
'tts_text' => $tenant->renderTtsTemplate($ticket->queueNumberForTts(), $ticket->counter?->name),
```

Dan:

Sumber: [app/Models/Tenant.php](app/Models/Tenant.php#L87)

```php
public function renderTtsTemplate(string $queue, ?string $counter = null): string
{
    return strtr($this->tts_template, [
        '{queue}' => $queue,
        '{counter}' => $counter ?? '',
    ]);
}
```

Yang dijelaskan:
- teks TTS tidak hardcoded
- setiap tenant bisa punya template suara sendiri

#### 4. Shared realtime subscriber
Sumber: [resources/js/lib/realtime.js](resources/js/lib/realtime.js#L7)

```js
export function subscribeToChannel({
    channelName,
    eventName,
    onMessage,
    onFallback = null,
    fallbackInterval = 15000,
}) {
    const echo = ensureEcho();
    let intervalId = onFallback ? window.setInterval(onFallback, fallbackInterval) : null;
}
```

Yang dijelaskan:
- jika websocket aktif, pakai realtime
- jika gagal, masih ada fallback polling

#### 5. TTS queue di halaman display
Sumber: [resources/js/pages/display.js](resources/js/pages/display.js#L162)

```js
function enqueueAnnouncement(text, language) {
    announcementQueue.push({ text, language });
    drainAnnouncementQueue();
}

function drainAnnouncementQueue() {
    if (!('speechSynthesis' in window) || isSpeaking || announcementQueue.length === 0) {
        return;
    }
}
```

Yang dijelaskan:
- suara tidak langsung diputar semua sekaligus
- announcement masuk queue dulu
- `isSpeaking` mencegah overlap

#### 6. Tampilan display per layanan
Sumber: [resources/js/pages/display.js](resources/js/pages/display.js#L115)

```js
<article class="panel overflow-hidden">
    <div class="panel-header border-b border-amber-700 bg-amber-600 py-3">
        <h2 class="text-xl font-semibold tracking-tight text-white">${escapeHtml(service.name)}</h2>
    </div>
    <div class="panel-body space-y-4">
        ${renderServiceStats(service.stats, { waitingChanged, servingChanged })}
    </div>
</article>
```

Yang dijelaskan:
- tiap kartu mewakili 1 layanan
- ada `Menunggu`, `Dilayani`, dan `Panggilan Terakhir`

### Saran demo
- buka halaman display
- dari halaman counter panggil tiket
- tunjukkan display berubah realtime dan TTS diputar

### Referensi pengujian
- [tests/Feature/QueueDisplayTest.php](tests/Feature/QueueDisplayTest.php#L18)

</details>

---

## Penutup Presentasi

> Proyek ini membangun sistem antrian digital end-to-end: mulai dari pengambilan tiket oleh user, pengelolaan tenant oleh admin, operasional counter oleh petugas, sampai tampilan display realtime dengan TTS.
