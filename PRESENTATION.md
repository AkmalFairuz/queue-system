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

### Fokus penjelasan
- Menjelaskan entitas utama dalam sistem
- Menjelaskan pembagian role: owner, admin, staff, dan user publik
- Menjelaskan pemisahan area public, counter, dan admin

### Gambaran sederhana
Bagian ini adalah fondasi dari seluruh aplikasi.  
Di sini dijelaskan siapa saja pengguna sistem, data apa saja yang disimpan, dan bagaimana sistem membedakan halaman public, counter, dan admin.

Kalau bagian ini sudah dipahami, maka bagian lain seperti ambil antrian, operasional counter, dan display realtime akan jauh lebih mudah dipahami.

### Cara kerja yang disampaikan saat presentasi
Bagian ini menjelaskan fondasi sistem:
1. User masuk ke aplikasi lewat route yang berbeda-beda, misalnya route public, route counter, dan route admin.
2. Sebelum user masuk ke halaman tertentu, middleware akan mengecek apakah user punya hak akses atau tidak.
3. Hak akses ditentukan dari relasi user ke tenant:
   - owner langsung punya akses penuh
   - admin ada di pivot `tenant_user` dengan role `admin`
   - staff ada di pivot `tenant_user` dengan role `staff`
4. Kalau staff ingin bekerja di counter, nanti masih ada pengecekan tambahan apakah staff itu memang ditugaskan ke counter tertentu.

Intinya, anggota 1 menjelaskan bahwa seluruh fitur lain berdiri di atas 3 hal ini:
- struktur route
- struktur tabel
- aturan role dan permission

### Source code yang dijelaskan

#### 1. Struktur route utama
Source: [routes/web.php:L19](routes/web.php#L19)

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

Cara kerja:
- saat request masuk, Laravel mencocokkan URL ke route
- route public langsung bisa dibuka
- route counter/admin masuk ke middleware dulu sebelum controller dijalankan

#### 2. Struktur tabel inti
Source: [database/migrations/2026_05_23_115510_create_initial_tables.php:L14](database/migrations/2026_05_23_115510_create_initial_tables.php#L14)

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

Cara kerja:
- `tenant_user` menjawab pertanyaan: “user ini bagian dari tenant mana?”
- `counter_staff` menjawab pertanyaan: “kalau dia staff, dia boleh kerja di counter mana?”

#### 3. Hak akses di middleware
Source: [app/Http/Middleware/EnsureTenantAccess.php:L12](app/Http/Middleware/EnsureTenantAccess.php#L12)

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

Cara kerja:
- middleware membaca parameter scope
- jika scope `work`, cukup cek user anggota tenant
- jika scope `manage`, cek user owner/admin tenant

#### 4. Logika role di model `User`
Source: [app/Models/User.php:L69](app/Models/User.php#L69)

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

Cara kerja:
- method di model dipakai ulang di banyak tempat
- jadi logika akses tidak ditulis berulang-ulang di controller

### Saran demo
- buka halaman home
- tunjukkan tenant list
- login sebagai staff lalu tunjukkan tidak bisa masuk admin

### Referensi pengujian
- [tests/Feature/TenantAccessTest.php:L14](tests/Feature/TenantAccessTest.php#L14)
- [tests/Feature/HomePageAccessTest.php:L14](tests/Feature/HomePageAccessTest.php#L14)

</details>

---

<details>
<summary><strong>Anggota 2: Fitur Ambil Antrian</strong></summary>

### Fokus penjelasan
- Menjelaskan flow ambil antrian dari halaman publik
- Menjelaskan pemilihan layanan, tanggal, dan jadwal
- Menjelaskan kuota dan pre-queue (antrian boleh diambil sebelum jam layanan mulai)

### Gambaran sederhana
Bagian ini menjelaskan pengalaman user publik saat mengambil antrian.  
Alurnya dibuat bertahap: pilih layanan, pilih tanggal, pilih jadwal, lalu sistem membuat tiket.

Yang penting dipahami di sini adalah sistem tidak asal membuat tiket.  
Backend tetap mengecek apakah jadwalnya valid, apakah kuota masih tersedia, dan apakah antrian memang boleh diambil pada waktu tersebut.

### Cara kerja yang disampaikan saat presentasi
Bagian ini menjelaskan alur user publik:
1. User membuka halaman tenant untuk ambil antrian.
2. Sistem menampilkan daftar layanan yang sedang bisa diambil.
3. Setelah user memilih layanan, sistem menampilkan tanggal yang valid.
4. Setelah user memilih tanggal, sistem menampilkan jadwal yang tersedia beserta sisa kuota.
5. Saat user submit, backend membuat tiket baru dengan sequence berikutnya.
6. Setelah tiket berhasil dibuat, user diarahkan ke halaman hasil tiket.

Intinya, flow public adalah:
`pilih layanan -> pilih tanggal -> pilih jadwal -> buat tiket -> tampilkan hasil tiket`

### Source code yang dijelaskan

#### 1. Controller halaman ambil antrian
Source: [app/Http/Controllers/Public/QueueTicketController.php:L19](app/Http/Controllers/Public/QueueTicketController.php#L19)

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

Cara kerja:
- controller memanggil `TicketIssuer->resolveQueueableSchedules(...)`
- kalau hasilnya tidak kosong, layanan dianggap masih bisa diambil

#### 2. Detail layanan: tanggal dan jadwal
Source: [app/Http/Controllers/Public/QueueTicketController.php:L48](app/Http/Controllers/Public/QueueTicketController.php#L48)

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

Cara kerja:
- semua jadwal yang valid dikelompokkan berdasarkan `service_date`
- hasil grouping itulah yang menjadi pilihan tanggal di frontend

#### 3. Remaining quota per jadwal
Source: [app/Http/Controllers/Public/QueueTicketController.php:L78](app/Http/Controllers/Public/QueueTicketController.php#L78)

```php
$remainingQuota = $schedule->max_tickets !== null
    ? max($schedule->max_tickets - $issuedCount, 0)
    : null;
```

Yang dijelaskan:
- kalau jadwal punya kuota, sistem hitung sisa slot
- hasilnya ditampilkan ke dropdown jadwal

Cara kerja:
- backend menghitung berapa tiket yang sudah keluar pada jadwal + tanggal itu
- lalu mengurangi dari `max_tickets`

#### 4. Logika pembuatan tiket
Source: [app/Services/TicketIssuer.php:L83](app/Services/TicketIssuer.php#L83)

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

Cara kerja:
- backend lock data tiket untuk mencegah nomor ganda
- hitung tiket terakhir
- sequence berikutnya = sequence maksimum + 1

#### 5. Logika pre-queue
Source: [app/Models/ServiceSchedule.php:L56](app/Models/ServiceSchedule.php#L56)

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

Cara kerja:
- `pre_queue_minutes` menggeser waktu buka antrian ke belakang
- jadi walaupun layanan belum mulai, tiket sudah bisa diambil lebih awal

#### 6. Frontend date picker dan submit
Source: [resources/js/pages/public-ticket.js:L96](resources/js/pages/public-ticket.js#L96)

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

Cara kerja:
- backend kirim payload JSON (data mentah untuk frontend)
- JS membaca payload itu
- JS render kalender dan pilihan jadwal secara dinamis tanpa reload penuh

### Saran demo
- buka `/tenant/{code}/antrian`
- pilih satu layanan
- pilih tanggal dan jam
- submit sampai keluar halaman hasil tiket

### Referensi pengujian
- [tests/Feature/PublicTicketTest.php:L18](tests/Feature/PublicTicketTest.php#L18)

</details>

---

<details>
<summary><strong>Anggota 3: Fitur Counter / Operasional Petugas</strong></summary>

### Fokus penjelasan
- Menjelaskan bagaimana petugas memanggil tiket
- Menjelaskan perubahan status tiket
- Menjelaskan pembatasan staff hanya ke counter yang ditugaskan

### Gambaran sederhana
Bagian ini menjelaskan pekerjaan petugas counter.  
Petugas harus memilih dulu counter dan layanan aktif, lalu dari situ sistem akan membantu memanggil tiket berikutnya, memulai pelayanan, menyelesaikan tiket, atau memanggil ulang.

Yang penting dipahami adalah petugas tidak bisa sembarang menekan tombol.  
Setiap tombol hanya aktif pada kondisi tertentu, dan staff juga dibatasi hanya ke counter yang memang ditugaskan oleh admin.

### Cara kerja yang disampaikan saat presentasi
Bagian ini menjelaskan alur kerja petugas counter:
1. Petugas memilih counter dan layanan aktif.
2. Pilihan itu disimpan ke session (data sementara milik user yang login).
3. Saat tombol `Panggil Berikutnya` ditekan, backend mengambil tiket `waiting` pertama untuk layanan tersebut.
4. Status tiket berubah menjadi `called`.
5. Saat petugas mulai melayani, status berubah menjadi `serving`.
6. Saat selesai, status berubah menjadi `completed`.
7. Jika perlu, petugas bisa `recall` tanpa mengubah status tiket.

Intinya, flow counter adalah:
`pilih context -> panggil -> layani -> selesai`

### Source code yang dijelaskan

#### 1. Counter context dan layanan disimpan di session
Source: [app/Http/Controllers/Counter/CounterWorkflowController.php:L15](app/Http/Controllers/Counter/CounterWorkflowController.php#L15)

```php
$request->session()->put($this->counterKey($tenant), $validated['counter_id']);
$request->session()->put($this->serviceKey($tenant), $validated['service_id']);
```

Yang dijelaskan:
- petugas memilih counter aktif dan layanan aktif dulu
- pilihan itu disimpan di session, jadi aksi berikutnya tahu sedang bekerja di counter mana

Cara kerja:
- session menyimpan `counter_id` dan `service_id`
- jadi tombol aksi berikutnya tidak perlu mengirim context berulang-ulang

#### 2. Panggil tiket berikutnya
Source: [app/Services/CounterWorkflow.php:L17](app/Services/CounterWorkflow.php#L17)

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

Cara kerja:
- sistem cek dulu apakah masih ada tiket aktif di counter
- kalau ada, request ditolak
- kalau tidak ada, sistem ambil tiket waiting paling depan

#### 3. Mulai layani dan selesai
Source: [app/Services/CounterWorkflow.php:L62](app/Services/CounterWorkflow.php#L62)

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

Cara kerja:
- perubahan status juga menyimpan timestamp
- timestamp ini nanti dipakai di UI, misalnya untuk stopwatch pelayanan

#### 4. Recall / panggil ulang
Source: [app/Services/CounterWorkflow.php:L104](app/Services/CounterWorkflow.php#L104)

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

Cara kerja:
- ticket aktif tetap ticket yang sama
- backend hanya broadcast event agar display tahu harus mengumumkan ulang

#### 5. Staff hanya boleh akses counter tertentu
Source: [app/Models/User.php:L81](app/Models/User.php#L81)

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

Cara kerja:
- saat user memilih counter atau menekan aksi counter, sistem cek `canAccessCounter()`
- jika tidak assigned, request langsung ditolak

#### 6. UI action button di halaman counter
Source: [resources/js/pages/counter.js:L145](resources/js/pages/counter.js#L145)

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

Cara kerja:
- frontend membaca status tiket sekarang
- lalu mengaktifkan atau menonaktifkan tombol yang sesuai

### Saran demo
- login sebagai staff
- pilih counter yang assigned
- panggil tiket
- mulai layani
- selesaikan

### Referensi pengujian
- [tests/Feature/CounterWorkflowTest.php:L19](tests/Feature/CounterWorkflowTest.php#L19)
- [tests/Feature/CounterAssignmentTest.php:L14](tests/Feature/CounterAssignmentTest.php#L14)

</details>

---

<details>
<summary><strong>Anggota 4: Fitur Admin Tenant</strong></summary>

### Fokus penjelasan
- Menjelaskan halaman admin untuk mengelola tenant
- Menjelaskan CRUD layanan, jadwal, counter, akses user, dan pengaturan tenant
- Menjelaskan pagination server-side dan modal form

### Gambaran sederhana
Bagian ini adalah pusat pengelolaan tenant.  
Admin bisa mengatur layanan, jadwal, counter, akses user, dan pengaturan tenant dari satu area yang dibagi menjadi beberapa tab.

Yang penting dipahami adalah halaman admin memakai kombinasi backend snapshot (paket data kondisi halaman saat ini) dan frontend render (proses membentuk tampilan di browser).  
Jadi setelah admin menambah atau mengubah data, tampilan bisa diperbarui dengan cepat tanpa harus pindah-pindah halaman terus.

### Cara kerja yang disampaikan saat presentasi
Bagian ini menjelaskan area pengelolaan tenant:
1. Admin membuka halaman tenant admin.
2. Admin berpindah antar tab: ringkasan, layanan, counter, akses, pengaturan.
3. Data tiap tab diambil dari snapshot backend.
4. Saat admin tambah/edit/hapus data, frontend membuka modal.
5. Setelah submit berhasil, frontend refresh snapshot agar tampilan langsung ikut update.

Intinya, halaman admin memakai pola:
`load snapshot -> render section -> open modal -> submit -> refresh snapshot`

### Source code yang dijelaskan

#### 1. Controller admin multi-page
Source: [app/Http/Controllers/Admin/AdminDashboardController.php:L15](app/Http/Controllers/Admin/AdminDashboardController.php#L15)

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

Cara kerja:
- controller menentukan section mana yang sedang dibuka
- lalu mengirim data awal yang sesuai ke blade

#### 2. Tab admin dan dataset frontend
Source: [resources/views/admin/page.blade.php:L26](resources/views/admin/page.blade.php#L26)

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

Cara kerja:
- blade hanya menyiapkan shell halaman
- data mentah ditaruh di script JSON
- JavaScript yang menyusun isi tabel/form berdasarkan section

#### 3. Snapshot admin + pagination
Source: [app/Services/DashboardDataService.php:L149](app/Services/DashboardDataService.php#L149)

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

Cara kerja:
- query seperti `services_page=2` dikirim ke backend
- backend hanya mengembalikan 10 data untuk halaman itu
- lebih ringan dan lebih aman daripada load semua data sekaligus

#### 4. Render section admin
Source: [resources/js/pages/admin/render.js:L10](resources/js/pages/admin/render.js#L10)

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

Cara kerja:
- `admin.js` menangani event utama
- `render.js` fokus membuat HTML
- `modals.js` fokus pada form create/edit/delete

#### 5. Modal create/edit layanan
Source: [resources/js/pages/admin/modals.js:L7](resources/js/pages/admin/modals.js#L7)

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

Cara kerja:
- saat tombol diklik, frontend membangun form modal sesuai data
- saat submit sukses, modal ditutup dan snapshot di-refresh

#### 6. Assignment staff ke counter
Source: [app/Http/Controllers/Admin/CounterController.php:L14](app/Http/Controllers/Admin/CounterController.php#L14)

```php
$counter = $tenant->counters()->create(collect($validated)->except('staff_ids')->all());
$counter->staff()->sync($validated['staff_ids'] ?? []);
```

Yang dijelaskan:
- admin tidak hanya membuat counter
- admin juga assign petugas ke counter tersebut

Cara kerja:
- data counter disimpan dulu
- setelah itu relasi `staff` disinkronkan ke tabel pivot `counter_staff`

#### 7. Tambah akses admin/staff
Source: [app/Http/Controllers/Admin/TenantAdminController.php:L15](app/Http/Controllers/Admin/TenantAdminController.php#L15)

```php
$tenant->users()->syncWithoutDetaching([
    $user->id => ['role' => $validated['role']],
]);
```

Yang dijelaskan:
- admin bisa menambahkan user ke tenant
- role disimpan di pivot `tenant_user`

Cara kerja:
- kalau email sudah ada, user lama dipakai
- kalau belum ada, sistem buat user baru
- setelah itu user di-attach ke tenant dengan role tertentu

### Saran demo
- buka tab layanan
- tambah layanan
- buka jadwal layanan
- tambah counter dan assign staff
- buka tab akses

### Referensi pengujian
- [tests/Feature/AdminSnapshotPaginationTest.php:L20](tests/Feature/AdminSnapshotPaginationTest.php#L20)

</details>

---

<details>
<summary><strong>Anggota 5: Queue Display, Realtime, dan TTS</strong></summary>

### Fokus penjelasan
- Menjelaskan layar display antrian
- Menjelaskan update realtime (perubahan langsung tampil tanpa reload manual)
- Menjelaskan antrian TTS supaya suara tidak overlap

### Gambaran sederhana
Bagian ini menjelaskan halaman display antrian yang ditampilkan ke pengunjung.  
Display menunjukkan kondisi antrian per layanan, lalu memperbarui tampilannya secara realtime ketika ada perubahan.

Yang penting dipahami adalah ada dua mekanisme utama di sini:
- update realtime melalui event broadcast (backend mengirim sinyal perubahan ke browser)
- pengumuman suara melalui TTS queue agar audio tidak bertabrakan

### Cara kerja yang disampaikan saat presentasi
Bagian ini menjelaskan apa yang dilihat user di layar display:
1. Display mengambil snapshot awal dari backend (data kondisi awal halaman).
2. Setelah itu display subscribe ke channel realtime tenant.
3. Kalau ada tiket baru dipanggil atau diubah statusnya, backend broadcast event.
4. Display refresh data dan update card layanan terkait.
5. Jika ada panggilan baru, text TTS dimasukkan ke queue suara.
6. Queue suara memastikan pengumuman diputar satu per satu, tidak tabrakan.

Intinya, flow display adalah:
`load snapshot -> listen realtime -> refresh data -> update UI -> play TTS`

### Source code yang dijelaskan

#### 1. Event broadcast realtime
Source: [app/Events/QueueDisplayUpdated.php:L10](app/Events/QueueDisplayUpdated.php#L10)

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

Cara kerja:
- event ini menjadi “sinyal” bahwa ada perubahan di tenant tertentu
- frontend yang mendengar event akan refresh snapshot

#### 2. Data display dari backend
Source: [app/Services/DashboardDataService.php:L46](app/Services/DashboardDataService.php#L46)

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

Cara kerja:
- backend mengolah data tiket tenant
- lalu mengelompokkan hasilnya per layanan

#### 3. Payload TTS dari ticket
Source: [app/Services/DashboardDataService.php:L440](app/Services/DashboardDataService.php#L440)

```php
'tts_text' => $tenant->renderTtsTemplate($ticket->queueNumberForTts(), $ticket->counter?->name),
```

Dan:

Source: [app/Models/Tenant.php:L87](app/Models/Tenant.php#L87)

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

Cara kerja:
- backend membangun kalimat TTS (text-to-speech, teks yang akan dibacakan suara) dari template tenant
- placeholder seperti `{queue}` dan `{counter}` diganti saat runtime

#### 4. Shared realtime subscriber
Source: [resources/js/lib/realtime.js:L7](resources/js/lib/realtime.js#L7)

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
- jika gagal, masih ada fallback polling (frontend cek data berkala tiap beberapa detik)

Cara kerja:
- sistem mencoba Echo/Reverb lebih dulu (`Laravel Echo` = client JavaScript untuk realtime, `Reverb` = server websocket Laravel)
- kalau tidak tersedia, `onFallback` tetap memanggil refresh berkala

#### 5. TTS queue di halaman display
Source: [resources/js/pages/display.js:L162](resources/js/pages/display.js#L162)

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
- announcement masuk queue dulu (antrian audio)
- `isSpeaking` mencegah overlap

Cara kerja:
- setiap announcement baru masuk ke array queue
- jika ada suara yang masih berjalan, announcement baru menunggu
- setelah selesai, queue berikutnya baru dijalankan

#### 6. Tampilan display per layanan
Source: [resources/js/pages/display.js:L115](resources/js/pages/display.js#L115)

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

Cara kerja:
- frontend membandingkan state lama dan state baru
- kalau ada perubahan angka/tiket, UI di-render ulang dan efek visual bisa ditampilkan

### Saran demo
- buka halaman display
- dari halaman counter panggil tiket
- tunjukkan display berubah realtime dan TTS diputar

### Referensi pengujian
- [tests/Feature/QueueDisplayTest.php:L18](tests/Feature/QueueDisplayTest.php#L18)

</details>

---

## Penutup Presentasi

> Proyek ini membangun sistem antrian digital end-to-end: mulai dari pengambilan tiket oleh user, pengelolaan tenant oleh admin, operasional counter oleh petugas, sampai tampilan display realtime dengan TTS.
